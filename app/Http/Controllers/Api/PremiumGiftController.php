<?php

namespace App\Http\Controllers\Api;

use App\Models\Gift;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Exception;

class PremiumGiftController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | TOKEN GENERATOR (ONLY AT PUBLISH)
    |--------------------------------------------------------------------------
    */
    private function generateShareToken()
    {
        Log::info('[GIFT:TOKEN] Generating share token');

        do {
            $token = Str::uuid()->toString();
        } while (Gift::where('share_token', $token)->exists());

        Log::info('[GIFT:TOKEN] Token generated', ['token' => $token]);

        return $token;
    }

    /*
    |--------------------------------------------------------------------------
    | 1️⃣ CREATE DRAFT
    |--------------------------------------------------------------------------
    */
    public function createDraft(Request $request)
    {
        Log::info('[GIFT:CREATE] Attempt', [
            'user_id' => Auth::id()
        ]);

        try {

            $validated = $request->validate([
                'template_type'  => 'required|string|max:100',
                'recipient_name' => 'required|string|max:255',
                'sender_name'    => 'required|string|max:255',
                'config'         => 'required|json',
            ]);

            $config = json_decode($validated['config'], true);

            $gift = Gift::create([
                'sender_id' => Auth::id(),
                'template_type' => $validated['template_type'],
                'status' => 'draft',
                'recipient_name' => $validated['recipient_name'],
                'sender_name' => $validated['sender_name'],
                'config' => $config,
                'payment_status' => 'unpaid',
                'has_secret_question' => false,
            ]);

            Log::info('[GIFT:CREATE] Draft created', [
                'gift_id' => $gift->id
            ]);

            return response()->json([
                'success' => true,
                'gift_id' => $gift->id,
            ]);

        } catch (Exception $e) {
            Log::error('[GIFT:CREATE] Failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false], 500);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 2️⃣ UPDATE DRAFT
    |--------------------------------------------------------------------------
    */
    public function updateDraft(Request $request, $id)
    {
        Log::info('[GIFT:UPDATE] Attempt', [
            'gift_id' => $id,
            'user_id' => Auth::id()
        ]);

        try {

            $gift = Gift::where('id', $id)
                ->where('sender_id', Auth::id())
                ->where('status', 'draft')
                ->firstOrFail();

            $gift->fill($request->only([
                'recipient_name',
                'sender_name',
                'config'
            ]));

            // ===== LOCK LOGIC =====
            if ($request->filled('lock_question') && $request->filled('lock_answer')) {

                $gift->has_secret_question = true;
                $gift->secret_question = $request->lock_question;
                $gift->secret_answer_hash = Hash::make($request->lock_answer);
                $gift->secret_hint = $request->lock_hint;

                Log::info('[GIFT:UPDATE] Lock stored', [
                    'has_secret_question' => true,
                    'question' => $request->lock_question
                ]);

            } else {
                $gift->has_secret_question = false;
            }

            $gift->save();

            Log::info('[GIFT:UPDATE] Saved', [
                'gift_id' => $gift->id,
                'has_secret_question' => $gift->has_secret_question
            ]);

            return response()->json(['success' => true]);

        } catch (Exception $e) {
            Log::error('[GIFT:UPDATE] Failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 3️⃣ UPLOAD IMAGES (DRAFT ONLY)
    |--------------------------------------------------------------------------
    */
    public function uploadImages(Request $request, $id)
    {
        Log::info('[GIFT:IMAGES] Upload attempt', [
            'gift_id' => $id,
            'user_id' => Auth::id(),
            'has_photo1' => $request->hasFile('photo1'),
            'has_photo2' => $request->hasFile('photo2')
        ]);

        try {

            $gift = Gift::where('id', $id)
                ->where('sender_id', Auth::id())
                ->where('status', 'draft')
                ->firstOrFail();

            $request->validate([
                'photo1' => 'nullable|image|max:5120',
                'photo2' => 'nullable|image|max:5120',
            ]);

            $folder = public_path("images/premium/{$gift->id}");

            if (!File::exists($folder)) {
                File::makeDirectory($folder, 0755, true);
                Log::info('[GIFT:IMAGES] Folder created', ['path' => $folder]);
            }

            $config = $gift->config ?? [];

            foreach (['photo1', 'photo2'] as $photoKey) {
                if ($request->hasFile($photoKey)) {
                    $file = $request->file($photoKey);
                    $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
                    $file->move($folder, $filename);

                    $config['visuals'][$photoKey] =
                        asset("images/premium/{$gift->id}/{$filename}");

                    Log::info('[GIFT:IMAGES] Stored', [
                        'photo_key' => $photoKey,
                        'filename' => $filename
                    ]);
                }
            }

            $gift->config = $config;
            $gift->save();

            Log::info('[GIFT:IMAGES] Upload complete');

            return response()->json(['success' => true]);

        } catch (Exception $e) {

            Log::error('[GIFT:IMAGES] Failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['success' => false], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 4️⃣ APPLY COUPON
    |--------------------------------------------------------------------------
    */
    public function applyCoupon(Request $request, $id)
    {
        try {

            $gift = Gift::where('id', $id)
                ->where('sender_id', Auth::id())
                ->where('status', 'draft')
                ->firstOrFail();

            $coupon = Coupon::where('code', $request->coupon_code)
                ->where('is_active', true)
                ->first();

            if (!$coupon) {
                return response()->json([
                    'success' => false,
                    'message' => 'Coupon not found.'
                ], 404);
            }

            if ($coupon->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Coupon has expired.'
                ], 403);
            }

            if (!$coupon->hasRemainingUses()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Coupon usage limit reached.'
                ], 403);
            }

            $gift->payment_status = 'coupon_redeemed';
            $gift->save();

            $coupon->increment('used_count');

            return response()->json([
                'success' => true,
                'message' => 'Coupon applied successfully! 🎉',
                'gift' => $gift
            ]);

        } catch (Exception $e) {

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.'
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 5️⃣ PUBLISH
    |--------------------------------------------------------------------------
    */
 public function publishGift($id)
    {
        Log::info('[GIFT:PUBLISH] Attempt', [
            'gift_id' => $id
        ]);

        try {

            $gift = Gift::where('id', $id)
                ->where('sender_id', Auth::id())
                ->where('status', 'draft')
                ->firstOrFail();

            if (!$gift->isPaid()) {
                Log::warning('[GIFT:PUBLISH] Blocked unpaid');
                return response()->json(['success' => false], 403);
            }

            $gift->status = 'published';
            $gift->share_token = $this->generateShareToken();
            $gift->published_at = now();
            $gift->save();

            Log::info('[GIFT:PUBLISH] Published', [
                'gift_id' => $gift->id,
                'share_token' => $gift->share_token,
                'has_secret_question' => $gift->has_secret_question
            ]);

            return response()->json([
                'success' => true,
                'gift' => $gift
            ]);

        } catch (Exception $e) {
            Log::error('[GIFT:PUBLISH] Failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 6️⃣ PUBLIC VIEW (GATED)
    |--------------------------------------------------------------------------
    */
public function viewGift(Request $request, $token)
    {
        Log::info('[GIFT:VIEW] Attempt', [
            'token' => $token,
            'unlock_token' => $request->unlock_token
        ]);

        try {

            $gift = Gift::where('share_token', $token)
                ->where('status', 'published')
                ->firstOrFail();

            Log::info('[GIFT:VIEW] Lock status', [
                'has_secret_question' => $gift->has_secret_question
            ]);

            $isUnlocked = false;

            if ($gift->has_secret_question && $request->filled('unlock_token')) {
                $cachedGiftId = Cache::get("gift_unlock:{$request->unlock_token}");
                $isUnlocked = $cachedGiftId === $gift->id;
            }

            if ($gift->has_secret_question && !$isUnlocked) {

                Log::info('[GIFT:VIEW] Returning LOCKED state');

                return response()->json([
                    'locked' => true,
                    'lock_question' => $gift->secret_question,
                    'lock_hint' => $gift->secret_hint,
                ]);
            }

            $gift->recordView();

            Log::info('[GIFT:VIEW] Returning FULL gift');

            return response()->json([
                'locked' => false,
                'gift' => $gift,
            ]);

        } catch (Exception $e) {
            Log::error('[GIFT:VIEW] Failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false], 404);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 7️⃣ VERIFY SECRET → ISSUE UNLOCK TOKEN
    |--------------------------------------------------------------------------
    */
public function verifyAndUnlock(Request $request, $token)
    {
        Log::info('[GIFT:UNLOCK] Attempt', ['token' => $token]);

        try {

            $request->validate(['answer' => 'required|string']);

            $gift = Gift::where('share_token', $token)
                ->where('status', 'published')
                ->where('has_secret_question', true)
                ->firstOrFail();

            if (!Hash::check($request->answer, $gift->secret_answer_hash)) {

                Log::warning('[GIFT:UNLOCK] Incorrect answer');

                return response()->json([
                    'success' => false,
                    'message' => 'Incorrect answer.'
                ], 403);
            }

            $unlockToken = Str::uuid()->toString();

            Cache::put(
                "gift_unlock:{$unlockToken}",
                $gift->id,
                now()->addMinutes(30)
            );

            Log::info('[GIFT:UNLOCK] Success', [
                'gift_id' => $gift->id
            ]);

            return response()->json([
                'success' => true,
                'unlock_token' => $unlockToken
            ]);

        } catch (Exception $e) {
            Log::error('[GIFT:UNLOCK] Failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PREVIEW DRAFT
    |--------------------------------------------------------------------------
    */
    public function previewDraft($id)
    {
        Log::info('[GIFT:PREVIEW] Attempt', [
            'gift_id' => $id,
            'user_id' => Auth::id()
        ]);

        try {

            $gift = Gift::where('id', $id)
                ->where('sender_id', Auth::id())
                ->where('status', 'draft')
                ->firstOrFail();

            Log::info('[GIFT:PREVIEW] Loaded', [
                'gift_id' => $gift->id
            ]);

            return response()->json([
                'gift' => $gift,
                'is_preview' => true
            ]);

        } catch (Exception $e) {

            Log::error('[GIFT:PREVIEW] Failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['success' => false], 404);
        }
    }

    public function myGifts()
    {
        Log::info('[GIFT:DASHBOARD] Fetching user gifts', [
            'user_id' => Auth::id()
        ]);

        $gifts = Gift::where('sender_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'gifts' => $gifts
        ]);
    }

}
