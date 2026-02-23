<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\MemoryMapInviteMail;
use Illuminate\Support\Facades\Mail;
use App\Models\MemoryMap;
use App\Models\MemoryMapParticipant;
use App\Models\MapMemory;
use App\Models\Coupon;
use Illuminate\Support\Facades\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class MemoryMapController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | TOKEN GENERATOR
    |--------------------------------------------------------------------------
    */
    private function generateShareToken()
    {
        do {
            $token = Str::uuid()->toString();
        } while (MemoryMap::where('share_token', $token)->exists());

        return $token;
    }

    /*
    |--------------------------------------------------------------------------
    | 1️⃣ CREATE DRAFT
    |--------------------------------------------------------------------------
    */
    public function createDraft(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'has_password' => 'boolean',
            'password' => 'nullable|string|min:4',
            'password_hint' => 'nullable|string',

        ]);

        $map = MemoryMap::create([
            'owner_id' => Auth::id(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'status' => 'draft',
            'payment_status' => 'unpaid',
            'max_participants' => 10,
            'has_password' => $request->has_password ?? false,
            'password_hash' => $request->password
                ? Hash::make($request->password)
                : null,
            'password_hint' => $request->password_hint,

        ]);

        // Insert owner as participant
        MemoryMapParticipant::create([
            'memory_map_id' => $map->id,
            'email' => Auth::user()->email,
            'user_id' => Auth::id(),
            'role' => 'owner',
            'status' => 'active',
        ]);
        
        return response()->json([
            'success' => true,
            'memory_map_id' => $map->id
        ]);
    }

public function getDraft($id)
{
    $map = MemoryMap::where('id', $id)
        ->where('owner_id', Auth::id())
        ->where('status', 'draft')
        ->firstOrFail();

    return response()->json([
        'success' => true,
        'memory_map' => $map->load([
            'participants.user',
            'memories.user'
        ])
    ]);
}

    /*
    |--------------------------------------------------------------------------
    | 2️⃣ APPLY COUPON (Seat Based)
    |--------------------------------------------------------------------------
    */
    public function applyCoupon(Request $request, $id)
    {
        $map = MemoryMap::where('id', $id)
            ->where('owner_id', Auth::id())
            ->where('status', 'draft')
            ->firstOrFail();

        $coupon = Coupon::where('code', $request->coupon_code)
            ->where('is_active', true)
            ->first();

        if (!$coupon || $coupon->isExpired() || !$coupon->hasRemainingUses()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired coupon.'
            ], 403);
        }

        $map->payment_status = 'coupon_redeemed';
        $map->max_participants = 4; // Coupon version
        $map->save();

        $coupon->increment('used_count');

        return response()->json(['success' => true]);
    }

    /*
    |--------------------------------------------------------------------------
    | 3️⃣ PUBLISH MAP
    |--------------------------------------------------------------------------
    */
    public function publishMap($id)
    {
        $map = MemoryMap::where('id', $id)
            ->where('owner_id', Auth::id())
            ->where('status', 'draft')
            ->firstOrFail();

        if (!$map->isPaid()) {
            return response()->json(['success' => false], 403);
        }

        $map->status = 'active';
        $map->share_token = $this->generateShareToken();
        $map->published_at = now();
        $map->save();

        return response()->json([
            'success' => true,
            'memory_map' => $map
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 4️⃣ VIEW MAP (GATED)
    |--------------------------------------------------------------------------
    */
    public function viewMap(Request $request, $token)
    {
        try {

            // 🔎 Find Active Map
            $map = MemoryMap::where('share_token', $token)
                ->where('status', 'active')
                ->firstOrFail();

            // 🔐 Require Bearer Token
            $authHeader = $request->header('Authorization');

            if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
                return response()->json([
                    'message' => 'Auth required'
                ], 401);
            }

            $plainToken = substr($authHeader, 7);

            $accessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($plainToken);

            if (!$accessToken) {
                return response()->json([
                    'message' => 'Invalid token'
                ], 401);
            }

            $user = $accessToken->tokenable;

            // 👑 OWNER ALWAYS ALLOWED
            if ($map->owner_id === $user->id) {

                $map->current_user_id = $user->id;
                $map->current_user_role = 'owner';

            } else {

                // 🔐 Check if user is invited (by email OR linked user_id)
                $participant = MemoryMapParticipant::where('memory_map_id', $map->id)
                    ->where(function ($query) use ($user) {
                        $query->where('user_id', $user->id)
                            ->orWhere('email', $user->email);
                    })
                    ->first();

                if (!$participant) {
                    return response()->json([
                        'message' => 'Access denied'
                    ], 403);
                }

                // 🔄 If invited but not yet linked to account → attach user_id
                if (!$participant->user_id) {
                    $participant->update([
                        'user_id' => $user->id,
                        'status' => 'active'
                    ]);
                }

                $map->current_user_id = $user->id;
                $map->current_user_role = $participant->role ?? 'participant';
            }

            // 🔐 Password Gate (if enabled)
            if ($map->has_password) {

                $unlockToken = $request->unlock_token;

                $cached = Cache::get("memory_map_unlock:{$unlockToken}");

                if ($cached !== $map->id) {
                    return response()->json([
                        'locked' => true,
                        'password_hint' => $map->password_hint
                    ]);
                }
            }

            // 📦 Load Relations
            $map->load([
                'participants.user',
                'memories.user'
            ]);

            return response()->json([
                'locked' => false,
                'memory_map' => $map
            ]);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Map not found'
            ], 404);
        }
    }
    /*
    |--------------------------------------------------------------------------
    | 5️⃣ VERIFY PASSWORD
    |--------------------------------------------------------------------------
    */
    public function verifyPassword(Request $request, $token)
    {
        $request->validate([
            'password' => 'required|string'
        ]);

        $map = MemoryMap::where('share_token', $token)
            ->where('status', 'active')
            ->where('has_password', true)
            ->firstOrFail();

        if (!Hash::check($request->password, $map->password_hash)) {
            return response()->json([   
                'success' => false,
                'message' => 'Incorrect password.'
            ], 403);
        }

        $unlockToken = Str::uuid()->toString();

        Cache::put(
            "memory_map_unlock:{$unlockToken}",
            $map->id,
            now()->addMinutes(30)
        );

        return response()->json([
            'success' => true,
            'unlock_token' => $unlockToken
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | 6️⃣ INVITE PARTICIPANTS
    |--------------------------------------------------------------------------
    */


public function inviteParticipants(Request $request, $id)
{
    $map = MemoryMap::where('id', $id)
        ->where('owner_id', Auth::id())
        ->firstOrFail();

    $request->validate([
        'emails' => 'required|array',
        'emails.*' => 'email'
    ]);

    $inviter = Auth::user();

    foreach ($request->emails as $email) {

        // 🔒 Check seat limit
        if (!$map->hasAvailableSeats()) {
            return response()->json([
                'success' => false,
                'message' => 'Seat limit reached.'
            ], 403);
        }

        // 🚫 Avoid duplicate invites
        $participant = MemoryMapParticipant::firstOrCreate(
            [
                'memory_map_id' => $map->id,
                'email' => $email,
            ],
            [
                'role' => 'participant',
                'status' => 'invited',
                'invited_by' => $inviter->id,
            ]
        );

        // ✉️ Send email
        Mail::to($email)->send(
            new MemoryMapInviteMail($map, $inviter)
        );
    }

    return response()->json([
        'success' => true,
        'message' => 'Invites sent successfully'
    ]);
}

    /*
    |--------------------------------------------------------------------------
    | 7️⃣ ADD MEMORY (Transaction Safe)
    |--------------------------------------------------------------------------
    */
    public function addMemory(Request $request, $id)
    {
        $map = MemoryMap::findOrFail($id);
        $user = Auth::user();

        // 🚫 Map must be active and paid
        if (!$map->isActive() || !$map->isPaid()) {
            return response()->json([
                'message' => 'Map not available'
            ], 403);
        }

        // 🔐 Owner OR active participant
        $participant = MemoryMapParticipant::where('memory_map_id', $map->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if (!$participant && $map->owner_id !== $user->id) {
            return response()->json([
                'message' => 'Forbidden'
            ], 403);
        }


        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'badge' => 'required|string',
            'message' => 'nullable|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'memory_date' => 'nullable|date',
            'photo' => 'nullable|image|max:5120', // 🔥 ADD THIS
        ]);

        $photoPath = null;

        // 🔥 Handle File Upload
        if ($request->hasFile('photo')) {

            $file = $request->file('photo');

            $folder = public_path('images/memory/' . $map->id);

            if (!file_exists($folder)) {
                mkdir($folder, 0755, true);
            }

            $filename = uniqid() . '.' . $file->getClientOriginalExtension();

            $file->move($folder, $filename);

            $photoPath = 'images/memory/' . $map->id . '/' . $filename;
        }

        $memory = null;

        DB::transaction(function () use ($map, $validated, $user, $photoPath, &$memory) {

            $maxOrder = MapMemory::where('memory_map_id', $map->id)
                ->lockForUpdate()
                ->max('display_order');

            $nextOrder = $maxOrder ? $maxOrder + 1 : 1;

            $memory = MapMemory::create([
                'memory_map_id' => $map->id,
                'user_id' => $user->id,
                'title' => $validated['title'],
                'badge' => $validated['badge'],
                'message' => $validated['message'] ?? null,
                'photo_url' => $photoPath, // 🔥 SAVE PATH
                'latitude' => $validated['latitude'],
                'longitude' => $validated['longitude'],
                'memory_date' => $validated['memory_date'] ?? null,
                'display_order' => $nextOrder,
            ]);
        });

        $memory->load('user');

        return response()->json([
            'success' => true,
            'memory' => $memory
        ]);
    }


    public function getManageMap($id)
    {
        $map = MemoryMap::where('id', $id)
            ->where('owner_id', Auth::id())
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'memory_map' => $map->load([
                'participants.user',
                'memories.user'
            ])
        ]);
    }
    /*
    |--------------------------------------------------------------------------
    | 8️⃣ DELETE MEMORY
    |--------------------------------------------------------------------------
    */
    public function deleteMemory($memoryId)
    {
        $memory = MapMemory::findOrFail($memoryId);
        $map = $memory->memoryMap;

        $user = Auth::user();

        if ($memory->user_id !== $user->id &&
            $map->owner_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($memory->photo_url) {
            $fullPath = public_path($memory->photo_url);
            if (File::exists($fullPath)) {
                File::delete($fullPath);
            }
        }


        $memory->delete();

        return response()->json(['success' => true]);
    }
}
