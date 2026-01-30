<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gift;
use App\Models\GiftPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PremiumGiftController extends Controller
{
    /* =========================================================
     |  1. CREATE DRAFT
     ========================================================= */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'template_type' => 'required|string',

            'recipient_name' => 'required|string|max:100',
            'recipient_nickname' => 'nullable|string|max:50',

            'sender_name' => 'required|string|max:100',
            'sender_nickname' => 'nullable|string|max:50',
        ]);

        $gift = Gift::create([
            'sender_id' => Auth::id(),
            'template_type' => $validated['template_type'],
            'status' => 'draft',

            'recipient_name' => $validated['recipient_name'],
            'recipient_nickname' => $validated['recipient_nickname'] ?? null,

            'sender_name' => $validated['sender_name'],
            'sender_nickname' => $validated['sender_nickname'] ?? null,
        ]);

        return response()->json([
            'id' => $gift->id,
            'status' => $gift->status,
        ], 201);
    }

    /* =========================================================
     |  2. UPDATE DRAFT
     ========================================================= */
    public function update(Request $request, string $id)
    {
        $gift = Gift::where('id', $id)
            ->where('sender_id', Auth::id())
            ->where('status', 'draft')
            ->first();

        if (! $gift) {
            return response()->json([
                'message' => 'Draft not found or not editable',
            ], 404);
        }

        $allowedFields = [
            'recipient_name',
            'recipient_nickname',
            'sender_name',
            'sender_nickname',

            'has_secret_question',
            'secret_question',
            'secret_hint',

            'message_title',
            'message_body',
            'message_style',

            'has_love_letter',
            'love_letter_content',
            'love_letter_style',

            'has_memories',
            'has_gallery',
            'has_map',
            'has_proposal',

            'sender_location',
            'recipient_location',
            'distance_message',

            'proposal_question',
            'proposed_datetime',
            'proposed_location',
            'proposed_activity',

            'intro_animation',
            'transition_style',
            'background_music',
        ];

        $data = $request->only($allowedFields);

        if ($request->filled('secret_answer')) {
            $data['secret_answer_hash'] = Hash::make($request->secret_answer);
        }

        $gift->update($data);

        return response()->json([
            'message' => 'Draft updated successfully',
            'id' => $gift->id,
        ]);
    }

    /* =========================================================
     |  3. PUBLISH GIFT
     ========================================================= */
    public function publish(string $id)
    {
        $gift = Gift::where('id', $id)
            ->where('sender_id', Auth::id())
            ->where('status', 'draft')
            ->first();

        if (! $gift) {
            return response()->json([
                'message' => 'Gift not found or already published',
            ], 404);
        }

        $gift->update([
            'status' => 'published',
            'share_token' => $this->generateUniqueToken(),
        ]);

        return response()->json([
            'share_token' => $gift->share_token,
            'share_url' => config('app.frontend_url') . '/gift/' . $gift->share_token,
        ]);
    }

    /* =========================================================
     |  4. VIEW PREMIUM GIFT (PUBLIC, SAFE)
     ========================================================= */
    public function view(Request $request, string $token)
    {
        $gift = Gift::where('share_token', $token)
            ->where('status', 'published')
            ->with(['memories', 'photos'])
            ->first();

        if (! $gift) {
            return response()->json([
                'message' => 'Gift not found',
            ], 404);
        }

        $isUnlocked = false;

        if ($gift->has_secret_question && $request->filled('unlock_token')) {
            $cachedGiftId = cache()->get("gift_unlock:{$request->unlock_token}");
            $isUnlocked = $cachedGiftId === $gift->id;
        }

        $gift->increment('view_count');

        return response()->json([
            'id' => $gift->id,
            'template_type' => $gift->template_type,
            'locked' => $gift->has_secret_question && ! $isUnlocked,

            'recipient_name' => $gift->recipient_name,
            'sender_name' => $gift->sender_name,

            'secret_question' => $gift->has_secret_question && ! $isUnlocked
                ? $gift->secret_question
                : null,

            'secret_hint' => $gift->has_secret_question && ! $isUnlocked
                ? $gift->secret_hint
                : null,

            'message_title' => $isUnlocked ? $gift->message_title : null,
            'message_body' => $isUnlocked ? $gift->message_body : null,
            'message_style' => $isUnlocked ? $gift->message_style : null,

            'love_letter' => $isUnlocked && $gift->has_love_letter ? [
                'content' => $gift->love_letter_content,
                'style' => $gift->love_letter_style,
            ] : null,

            'photos' => $isUnlocked
                ? $gift->photos->map(fn ($p) => asset($p->image_path))
                : [],

            'map' => $isUnlocked && $gift->has_map ? [
                'sender_location' => $gift->sender_location,
                'recipient_location' => $gift->recipient_location,
                'distance_message' => $gift->distance_message,
            ] : null,

            'proposal' => $isUnlocked && $gift->has_proposal ? [
                'question' => $gift->proposal_question,
                'response' => $gift->proposal_response,
            ] : null,
        ]);
    }

    /* =========================================================
     |  5. VERIFY + UNLOCK (ANTI-BRUTEFORCE)
     ========================================================= */
    public function verifySecret(Request $request, string $token)
    {
        $request->validate([
            'answer' => 'required|string',
        ]);

        $key = 'secret-attempt:' . $token . ':' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            return response()->json([
                'message' => 'Too many attempts. Try again later.',
            ], 429);
        }

        $gift = Gift::where('share_token', $token)
            ->where('status', 'published')
            ->first();

        if (! $gift || ! $gift->has_secret_question) {
            return response()->json([
                'message' => 'Invalid request',
            ], 400);
        }

        if (! Hash::check($request->answer, $gift->secret_answer_hash)) {
            RateLimiter::hit($key, 60);
            return response()->json([
                'message' => 'Incorrect answer',
            ], 403);
        }

        RateLimiter::clear($key);

        $unlockToken = Str::uuid()->toString();
        cache()->put("gift_unlock:{$unlockToken}", $gift->id, now()->addMinutes(30));

        return response()->json([
            'message' => 'Access granted',
            'unlock_token' => $unlockToken,
            'expires_in' => 1800,
        ]);
    }

    /* =========================================================
     |  6. ACCEPT PROPOSAL (YES ONLY, IDP)
     ========================================================= */
    public function acceptProposal(Request $request, string $token)
    {
        $request->validate([
            'unlock_token' => 'required|string',
        ]);

        $gift = Gift::where('share_token', $token)
            ->where('status', 'published')
            ->where('has_proposal', true)
            ->first();

        if (! $gift) {
            return response()->json([
                'message' => 'Invalid gift',
            ], 404);
        }

        if (cache()->get("gift_unlock:{$request->unlock_token}") !== $gift->id) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 403);
        }

        if ($gift->proposal_response === 'yes') {
            return response()->json([
                'message' => 'Already accepted',
            ]);
        }

        $gift->update([
            'proposal_response' => 'yes',
            'proposal_responded_at' => now(),
        ]);

        return response()->json([
            'message' => 'Proposal accepted',
        ]);
    }

    /* =========================================================
     |  7. IMAGE UPLOAD (PREMIUM)
     ========================================================= */
    public function uploadImage(Request $request, string $id)
    {
        $gift = Gift::where('id', $id)
            ->where('sender_id', Auth::id())
            ->where('status', 'draft')
            ->first();

        if (! $gift) {
            return response()->json([
                'message' => 'Gift not found or not editable',
            ], 404);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $path = public_path("images/premium/{$gift->id}");

        if (! File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        $file = $request->file('image');
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move($path, $filename);

        $photo = GiftPhoto::create([
            'gift_id' => $gift->id,
            'image_path' => "images/premium/{$gift->id}/{$filename}",
        ]);

        return response()->json([
            'id' => $photo->id,
            'url' => asset($photo->image_path),
        ], 201);
    }

    /* =========================================================
     |  TOKEN GENERATOR
     ========================================================= */
    private function generateUniqueToken(): string
    {
        do {
            $token = Str::random(32);
        } while (Gift::where('share_token', $token)->exists());

        return $token;
    }
}
