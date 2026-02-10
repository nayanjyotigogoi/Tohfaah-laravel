<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gift;
use App\Models\GiftPhoto;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class PremiumGiftController extends Controller
{

    public function show($id)
    {
        Log::info('[GIFT:SHOW] Fetching draft', [
            'gift_id' => $id,
            'user_id' => Auth::id(),
        ]);

        $gift = Gift::where('id', $id)
            ->where('sender_id', Auth::id())
            ->with(['photos', 'memories']) // ✅ IMPORTANT
            ->firstOrFail();

        Log::info('[GIFT:SHOW] Draft found', [
            'has_love_letter' => $gift->has_love_letter,
            'has_map' => $gift->has_map,
            'has_proposal' => $gift->has_proposal,
            'proposed_datetime' => $gift->proposed_datetime,
            'photos_count' => $gift->photos->count(),
        ]);

        return response()->json([
            'id' => $gift->id,

            'recipient_name' => $gift->recipient_name,
            'sender_name' => $gift->sender_name,

            'secret_question' => $gift->secret_question,
            'message_body' => $gift->message_body,

            'love_letter_content' => $gift->love_letter_content,

            // ✅ FIXED
            'photos' => $gift->photos
                ->sortBy('display_order')
                ->map(fn ($p) => asset($p->image_url))
                ->values(),

            // OPTIONAL (but correct)
            'memories' => $gift->memories
                ->sortBy('display_order')
                ->pluck('caption')
                ->values(),

            'sender_location' => $gift->sender_location,
            'recipient_location' => $gift->recipient_location,

            'proposal_question' => $gift->proposal_question,
            'proposed_datetime' => optional($gift->proposed_datetime)->toISOString(),
        ]);
    }



    /* =========================================================
     |  1. CREATE DRAFT
     ========================================================= */
    public function store(Request $request)
    {

        Log::info('[GIFT:STORE] Creating draft', [
            'user_id' => Auth::id(),
            'payload' => $request->all(),
        ]);
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

        Log::info('[GIFT:STORE] Draft created', ['gift_id' => $gift->id]);


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
        Log::info('[GIFT:UPDATE] Incoming update', [
            'gift_id' => $id,
            'user_id' => Auth::id(),
            'payload' => $request->all(),
        ]);

        $gift = Gift::where('id', $id)
            ->where('sender_id', Auth::id())
            ->where('status', 'draft')
            ->first();

        if (! $gift) {
            Log::warning('[GIFT:UPDATE] Draft not found or locked', [
                'gift_id' => $id,
            ]);

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
        // ✅ SAVE CONVERSATION MEMORIES
        if ($request->has('conversation_messages')) {

            $gift->memories()->delete();

            foreach ($request->conversation_messages as $index => $message) {
                if (!trim($message)) continue;

                $gift->memories()->create([
                    'caption' => $message,
                    'image_url' => null, // ✅ IMPORTANT
                    'display_order' => $index,
                ]);
            }

            $gift->update([
                'has_memories' => true,
            ]);
        }



        Log::info('[GIFT:UPDATE] Draft updated', [
            'gift_id' => $gift->id,
            'has_proposal' => $gift->has_proposal,
            'proposed_datetime' => $gift->proposed_datetime,
        ]);

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
        Log::info('[GIFT:PUBLISH] Attempt', [
            'gift_id' => $id,
            'user_id' => Auth::id(),
        ]);


        $gift = Gift::where('id', $id)
            ->where('sender_id', Auth::id())
            ->where('status', 'draft')
            ->first();

        if (! $gift) {
            Log::warning('[GIFT:PUBLISH] Not found or already published', [
                'gift_id' => $id,
            ]);

            return response()->json([
                'message' => 'Gift not found or already published',
            ], 404);
        }

        $gift->update([
            'status' => 'published',
            'share_token' => $this->generateUniqueToken(),
        ]);

        Log::info('[GIFT:PUBLISH] Published', [
            'gift_id' => $gift->id,
            'share_token' => $gift->share_token,
        ]);

        return response()->json([
            'message' => 'Gift unlocked successfully',
            'share_token' => $gift->share_token,
            'share_url' => config('app.frontend_url')
                . '/gift/valentine/' . $gift->id
                . '?token=' . $gift->share_token,
        ]);


    }

    /* =========================================================
     |  4. VIEW PREMIUM GIFT (PUBLIC, SAFE)
     ========================================================= */
    public function view(Request $request, string $token)
    {
        Log::info('[GIFT:VIEW] Public view', [
            'token' => $token,
            'unlock_token' => $request->unlock_token,
        ]);
        $gift = Gift::where('share_token', $token)
            ->where('status', 'published')
            ->with(['memories', 'photos'])
            ->first();

        if (! $gift) {
            Log::warning('[GIFT:VIEW] Gift not found', ['token' => $token]);
            return response()->json(['message' => 'Gift not found'], 404);
        }

        $isUnlocked = false;

        if ($gift->has_secret_question && $request->filled('unlock_token')) {
            $cachedGiftId = cache()->get("gift_unlock:{$request->unlock_token}");
            $isUnlocked = $cachedGiftId === $gift->id;
        }

        $gift->increment('view_count');

        Log::info('[GIFT:VIEW] View state', [
            'gift_id' => $gift->id,
            'locked' => $gift->has_secret_question && ! $isUnlocked,
            'has_proposal' => $gift->has_proposal,
        ]);


        return response()->json([
                'locked' => $gift->has_secret_question && ! $isUnlocked,

                'recipient_name' => $gift->recipient_name,
                'sender_name' => $gift->sender_name,

                'secret_question' => $gift->has_secret_question && ! $isUnlocked
                    ? $gift->secret_question
                    : null,

                'message_body' => $isUnlocked ? $gift->message_body : null,

                'love_letter' => $isUnlocked && $gift->has_love_letter ? [
                    'content' => $gift->love_letter_content,
                ] : null,

                'memories' => $isUnlocked
                    ? $gift->memories->pluck('caption')->values()
                    : [],

                'photos' => $isUnlocked
                    ? $gift->photos->map(fn ($p) => asset($p->image_url))->values()
                    : [],

                'map' => $isUnlocked && $gift->has_map ? [
                    'sender_location' => $gift->sender_location,
                    'recipient_location' => $gift->recipient_location,
                ] : null,

                'proposal' => $isUnlocked && $gift->has_proposal ? [
                    'question' => $gift->proposal_question,
                    'response' => $gift->proposal_response,
                ] : null,
        ]);

    }

    /* =========================================================
     |  4. VIEW PREMIUM GIFT (PUBLIC, SAFE)
     ========================================================= */
    public function preview(string $id)
    {
        Log::info('[GIFT:PREVIEW] Preview request', [
            'gift_id' => $id,
            'user_id' => Auth::id(),
        ]);

        $gift = Gift::where('id', $id)
            ->where('sender_id', Auth::id())
            ->with(['memories', 'photos'])
            ->firstOrFail();

        Log::info('[GIFT:PREVIEW] Preview data', [
            'has_map' => $gift->has_map,
            'has_proposal' => $gift->has_proposal,
            'proposed_datetime' => $gift->proposed_datetime,
        ]);

        return response()->json([
            'id' => $gift->id,
            'template_type' => $gift->template_type,

            'recipient_name' => $gift->recipient_name,
            'sender_name' => $gift->sender_name,

            'message_title' => $gift->message_title,
            'message_body' => $gift->message_body,

            'love_letter' => $gift->has_love_letter ? [
                'content' => $gift->love_letter_content,
            ] : null,

            'memories' => $gift->memories->pluck('caption')->values(),

            'photos' => $gift->photos->map(fn ($p) => asset($p->image_url)),

            'map' => $gift->has_map ? [
                'sender_location' => $gift->sender_location,
                'recipient_location' => $gift->recipient_location,
            ] : null,

            'proposal' => $gift->has_proposal ? [
                'question' => $gift->proposal_question,
                'response' => null,
            ] : null,


            'is_preview' => true,
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
    |  7. IMAGE UPLOAD (PREMIUM) — FIXED
    ========================================================= */


    public function uploadImage(Request $request, string $id)
    {
        Log::info('[GIFT:IMAGE] Upload request received', [
            'gift_id' => $id,
            'user_id' => Auth::id(),
            'has_file' => $request->hasFile('image'),
        ]);

        $gift = Gift::where('id', $id)
            ->where('sender_id', Auth::id())
            ->where('status', 'draft')
            ->first();

        if (! $gift) {
            Log::warning('[GIFT:IMAGE] Gift not found or not editable', [
                'gift_id' => $id,
                'user_id' => Auth::id(),
            ]);

            return response()->json([
                'message' => 'Gift not found or not editable',
            ], 404);
        }

        Log::info('[GIFT:IMAGE] Gift found', [
            'gift_id' => $gift->id,
            'status' => $gift->status,
            'existing_photos' => $gift->photos()->count(),
        ]);

        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $path = public_path("images/premium/{$gift->id}");

        if (! File::exists($path)) {
            File::makeDirectory($path, 0755, true);
            Log::info('[GIFT:IMAGE] Directory created', [
                'path' => $path,
            ]);
        }

        $file = $request->file('image');

        Log::info('[GIFT:IMAGE] File received', [
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size_kb' => round($file->getSize() / 1024, 2),
        ]);

        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $file->move($path, $filename);

        $relativePath = "images/premium/{$gift->id}/{$filename}";

        Log::info('[GIFT:IMAGE] File moved', [
            'relative_path' => $relativePath,
            'exists_on_disk' => File::exists(public_path($relativePath)),
        ]);

        $photo = GiftPhoto::create([
            'gift_id' => $gift->id,
            'image_url' => $relativePath,
            'display_order' => $gift->photos()->count(),
        ]);

        Log::info('[GIFT:IMAGE] Photo DB record created', [
            'photo_id' => $photo->id,
            'gift_id' => $photo->gift_id,
            'image_url' => $photo->image_url,
        ]);

        // Optional but recommended
        $gift->update([
            'has_gallery' => true,
        ]);

        Log::info('[GIFT:IMAGE] Gift gallery flag updated', [
            'gift_id' => $gift->id,
            'has_gallery' => $gift->has_gallery,
        ]);

        return response()->json([
            'id' => $photo->id,
            'url' => asset($photo->image_url),
        ], 201);
    }

    public function applyCoupon(Request $request, string $id)
    {
        $request->validate([
            'code' => 'required|string',
        ]);

        Log::info('[COUPON] Apply attempt', [
            'gift_id' => $id,
            'user_id' => Auth::id(),
            'code' => $request->code,
        ]);

        return DB::transaction(function () use ($request, $id) {

            // 1️⃣ Fetch gift (must still be draft)
            $gift = Gift::where('id', $id)
                ->where('sender_id', Auth::id())
                ->where('status', 'draft')
                ->lockForUpdate()
                ->first();

            if (! $gift) {
                return response()->json([
                    'message' => 'Gift not found or already unlocked',
                ], 404);
            }

            // 2️⃣ Fetch coupon
            $coupon = Coupon::where('code', $request->code)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $coupon) {
                return response()->json([
                    'message' => 'Invalid coupon code',
                ], 403);
            }

            if ($coupon->isExpired()) {
                return response()->json([
                    'message' => 'Coupon expired',
                ], 403);
            }

            if (! $coupon->hasRemainingUses()) {
                return response()->json([
                    'message' => 'Coupon usage limit reached',
                ], 403);
            }

            // 3️⃣ Consume coupon
            $coupon->increment('used_count');

            // 4️⃣ Publish gift (existing behaviour)
            $gift->update([
                'status' => 'published',
                'coupon_id' => $coupon->id,
                'share_token' => $this->generateUniqueToken(),
            ]);

            Log::info('[COUPON] Gift unlocked via coupon', [
                'gift_id' => $gift->id,
                'coupon_id' => $coupon->id,
            ]);

            return response()->json([
                'message' => 'Gift unlocked successfully',
                'share_token' => $gift->share_token,
                'share_url' => config('app.frontend_url')
                    . '/gift/valentine/' . $gift->id
                    . '?token=' . $gift->share_token,
            ]);

        });
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
