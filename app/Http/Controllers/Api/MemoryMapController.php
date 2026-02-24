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
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Razorpay\Api\Api;
use App\Models\Transaction;
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

            // Transform photo_url to proxy URL for secure access
            $map->memories->each(function ($memory) use ($token) {
                if ($memory->photo_url && !str_starts_with($memory->photo_url, 'http')) {
                    $memory->photo_url = route('memory-maps.image', [
                        'token' => $token,
                        'filename' => basename($memory->photo_url),
                    ]);
                }
            });

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

        // 🔥 Handle File Upload with EXIF stripping & resize
        if ($request->hasFile('photo')) {

            $file = $request->file('photo');
            $folder = public_path('images/memory/' . $map->id);

            if (!file_exists($folder)) {
                mkdir($folder, 0755, true);
            }

            $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
            $destinationPath = $folder . '/' . $filename;

            try {
                // Use Intervention Image to strip GPS/EXIF and resize
                $manager = new ImageManager(new Driver());
                $image = $manager->read($file);

                // Resize if larger than 1080px wide
                if ($image->width() > 1080) {
                    $image->scale(width: 1080);
                }

                // Save (Intervention strips metadata automatically on save)
                $image->save($destinationPath);

                Log::info('[MEMORY:IMAGE] Processed & saved', [
                    'filename' => $filename,
                    'width' => $image->width(),
                ]);

            } catch (Exception $e) {
                Log::error('[MEMORY:IMAGE] Processing failed', ['error' => $e->getMessage()]);
                return response()->json(['message' => 'Image processing failed'], 500);
            }

            // Store relative path (used by proxy)
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

        // Build proxy URL if image was uploaded
        $memoryData = $memory->toArray();
        if ($memory->photo_url) {
            $memoryData['photo_url'] = route('memory-maps.image', [
                'token' => $map->share_token,
                'filename' => basename($memory->photo_url),
            ]);
        }

        return response()->json([
            'success' => true,
            'memory' => $memoryData
        ]);
    }


    public function getManageMap($id)
    {
        $map = MemoryMap::where('id', $id)
            ->where('owner_id', Auth::id())
            ->firstOrFail();

        $map->load(['participants.user', 'memories.user']);

        // Transform photo_url to direct asset URL (owner only, no proxy needed)
        $map->memories->each(function ($memory) {
            if ($memory->photo_url && !str_starts_with($memory->photo_url, 'http')) {
                $memory->photo_url = asset($memory->photo_url);
            }
        });

        return response()->json([
            'success' => true,
            'memory_map' => $map
        ]);
    }

    /**
     * SERVE MEMORY IMAGE (PROXY)
     * GET /memory-maps/image/{token}/{filename}
     */
    public function serveMemoryImage(string $token, string $filename)
    {
        // 1. Find active map by share token
        $map = MemoryMap::where('share_token', $token)
            ->where('status', 'active')
            ->first();

        if (!$map) {
            abort(404);
        }

        // 2. Validate filename belongs to this map
        $isValidFile = MapMemory::where('memory_map_id', $map->id)
            ->whereRaw("photo_url LIKE ?", ['%' . $filename])
            ->exists();

        if (!$isValidFile) {
            abort(403);
        }

        // 3. Resolve full path on disk
        $path = public_path('images/memory/' . $map->id . '/' . $filename);

        if (!file_exists($path)) {
            abort(404);
        }

        // 4. Serve file
        return response()->file($path);
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

        if (
            $memory->user_id !== $user->id &&
            $map->owner_id !== $user->id
        ) {
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

    /*
    |--------------------------------------------------------------------------
    | 9️⃣ RAZORPAY PAYMENT
    |--------------------------------------------------------------------------
    */

    public function createOrder($id)
    {
        Log::info('[MEMORY_MAP:PAYMENT] Create Order Attempt', ['memory_map_id' => $id]);

        try {
            $map = MemoryMap::where('id', $id)
                ->where('owner_id', Auth::id())
                ->where('status', 'draft')
                ->firstOrFail();

            $api = new \Razorpay\Api\Api(env('RAZORPAY_KEY_ID'), env('RAZORPAY_KEY_SECRET'));

            // Price from config
            $priceConfig = config('prices.memory_map');
            $amount = $priceConfig['amount'] * 100; // Amount in paise

            $orderData = [
                'receipt' => (string) $map->id,
                'amount' => $amount,
                'currency' => $priceConfig['currency'],
                'notes' => [
                    'memory_map_id' => $map->id,
                    'type' => 'memory_map'
                ]
            ];

            $razorpayOrder = $api->order->create($orderData);

            // Create Transaction Record
            \App\Models\Transaction::create([
                'id' => Str::uuid(),
                'user_id' => Auth::id(),
                'memory_map_id' => $map->id,
                'package_id' => 'memory_map',
                'amount_cents' => $amount, // stored in smallest unit
                'currency' => $priceConfig['currency'],
                'razorpay_order_id' => $razorpayOrder['id'],
                'status' => 'created',
                'credits_purchased' => 0 // Not applicable
            ]);

            Log::info('[MEMORY_MAP:PAYMENT] Order Created', [
                'memory_map_id' => $map->id,
                'order_id' => $razorpayOrder['id']
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $razorpayOrder['id'],
                'amount' => $amount,
                'currency' => 'INR',
                'key_id' => env('RAZORPAY_KEY_ID'),
                'contact' => Auth::user()->email // Pre-fill email if possible
            ]);

        } catch (Exception $e) {
            Log::error('[MEMORY_MAP:PAYMENT] Order Creation Failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function verifyPayment(Request $request, $id)
    {
        Log::info('[MEMORY_MAP:PAYMENT] Verification Attempt', ['memory_map_id' => $id]);

        try {
            $request->validate([
                'razorpay_payment_id' => 'required|string',
                'razorpay_order_id' => 'required|string',
                'razorpay_signature' => 'required|string',
            ]);

            $map = MemoryMap::where('id', $id)
                ->where('owner_id', Auth::id())
                ->firstOrFail();

            $api = new \Razorpay\Api\Api(env('RAZORPAY_KEY_ID'), env('RAZORPAY_KEY_SECRET'));

            $attributes = [
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature
            ];

            $api->utility->verifyPaymentSignature($attributes);

            // Update Transaction Record
            $transaction = \App\Models\Transaction::where('razorpay_order_id', $request->razorpay_order_id)->first();
            if ($transaction) {
                $transaction->update([
                    'razorpay_payment_id' => $request->razorpay_payment_id,
                    'razorpay_signature' => $request->razorpay_signature,
                    'status' => 'paid'
                ]);
            }

            // If signature verification is successful, update the map
            $map->payment_status = 'paid';
            $map->amount = config('prices.memory_map.amount');
            $map->save();

            Log::info('[MEMORY_MAP:PAYMENT] Payment Verified', ['memory_map_id' => $map->id]);

            return response()->json([
                'success' => true,
                'message' => 'Payment successful! Map is ready to publish.',
                'memory_map' => $map,
                'payment_status' => 'paid'
            ]);

        } catch (Exception $e) {
            Log::error('[MEMORY_MAP:PAYMENT] Verification Failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Payment verification failed.'], 400);
        }
    }
}
