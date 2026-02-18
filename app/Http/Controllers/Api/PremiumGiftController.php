<?php

namespace App\Http\Controllers\Api;

use App\Models\Gift;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Razorpay\Api\Api;
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
                'template_type' => 'required|string|max:100',
                'recipient_name' => 'required|string|max:255',
                'sender_name' => 'required|string|max:255',
                'config' => 'required|json',
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

        }
        catch (Exception $e) {
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

            }
            else {
                $gift->has_secret_question = false;
            }

            $gift->save();

            Log::info('[GIFT:UPDATE] Saved', [
                'gift_id' => $gift->id,
                'has_secret_question' => $gift->has_secret_question
            ]);

            return response()->json(['success' => true]);

        }
        catch (Exception $e) {
            Log::error('[GIFT:UPDATE] Failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false], 500);
        }
    }

    /*
     |--------------------------------------------------------------------------
     | 3️⃣ UPLOAD IMAGES (DRAFT ONLY)
     |--------------------------------------------------------------------------
     */
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

                    // Store RELATIVE path: "images/premium/{gift_id}/{filename}"
                    // This allows us to serve it via proxy later
                    $config['visuals'][$photoKey] = "images/premium/{$gift->id}/{$filename}";

                    Log::info('[GIFT:IMAGES] Stored relative path', [
                        'photo_key' => $photoKey,
                        'filename' => $filename
                    ]);
                }
            }

            $gift->config = $config;
            $gift->save();

            Log::info('[GIFT:IMAGES] Upload complete');

            return response()->json(['success' => true]);

        }
        catch (Exception $e) {

            Log::error('[GIFT:IMAGES] Failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json(['success' => false], 500);
        }
    }

    /**
     * SERVE PREMIUM IMAGE (PROXY)
     * GET /premium-gifts/image/{token}/{filename}
     */
    public function serveImage(string $token, string $filename)
    {
        // 1. Validate Token & Find Gift
        $gift = Gift::where('share_token', $token)
            ->where('status', 'published')
            ->first();

        if (!$gift) {
            abort(404);
        }

        // 2. Validate filename belongs to this gift (Security: prevent accessing other images)
        // Check if config.visuals contains this filename
        $visuals = $gift->config['visuals'] ?? [];
        $isValidFile = false;

        foreach ($visuals as $key => $path) {
            if (str_contains($path, $filename)) {
                $isValidFile = true;
                break;
            }
        }

        if (!$isValidFile) {
            abort(403);
        }

        // 3. Construct Path (Public Compatible)
        $path = public_path("images/premium/{$gift->id}/{$filename}");

        if (!file_exists($path)) {
            abort(404);
        }

        // 4. Serve File
        return response()->file($path);
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

        }
        catch (Exception $e) {

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

        }
        catch (Exception $e) {
            Log::error('[GIFT:PUBLISH] Failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false], 500);
        }
    }

    /*
     |--------------------------------------------------------------------------
     | 6️⃣ PUBLIC VIEW (GATED)
     |--------------------------------------------------------------------------
     */public function viewGift(Request $request, $token)
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

            // TRANSFORM IMAGE URLS TO PROXY
            $config = $gift->config;
            if (isset($config['visuals'])) {
                foreach ($config['visuals'] as $key => $path) {
                    // Check if path is relative (e.g. images/premium/...) and NOT a full URL
                    if ($path && is_string($path) && !str_starts_with($path, 'http')) {
                        // usage: /api/premium-gifts/image/{token}/{filename}
                        // Extract filename from path
                        $filename = basename($path);
                        $config['visuals'][$key] = route('premium-gifts.image', [
                            'token' => $token,
                            'filename' => $filename
                        ]);
                    }
                }
                $gift->config = $config; // Only for response, not saving to DB
            }

            Log::info('[GIFT:VIEW] Returning FULL gift');

            return response()->json([
                'locked' => false,
                'gift' => $gift,
            ]);

        }
        catch (Exception $e) {
            Log::error('[GIFT:VIEW] Failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false], 404);
        }
    }

    /*
     |--------------------------------------------------------------------------
     | 7️⃣ VERIFY SECRET → ISSUE UNLOCK TOKEN
     |--------------------------------------------------------------------------
     */public function verifyAndUnlock(Request $request, $token)
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

        }
        catch (Exception $e) {
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

            // TRANSFORM IMAGE URLS FOR PREVIEW (USE ASSET() FOR OWNER)
            $config = $gift->config;
            if (isset($config['visuals'])) {
                foreach ($config['visuals'] as $key => $path) {
                    if ($path && is_string($path) && !str_starts_with($path, 'http')) {
                        // Drafts have no Share Token, so we serve the direct asset URL
                        // Only the authenticated Owner sees this, so it's acceptable.
                        $config['visuals'][$key] = asset($path);
                    }
                }
                $gift->config = $config;
            }

            return response()->json([
                'gift' => $gift,
                'is_preview' => true
            ]);

        }
        catch (Exception $e) {

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

    public function deleteDraft($id)
    {
        $user = auth()->user();

        $gift = \DB::table('gifts')
            ->where('id', $id)
            ->where('sender_id', $user->id)
            ->where('status', 'draft')
            ->first();

        if (!$gift) {
            return response()->json([
                'success' => false,
                'message' => 'Draft not found.'
            ], 404);
        }

        \DB::table('gifts')
            ->where('id', $id)
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Draft deleted successfully.'
        ]);
    }

    /*     |--------------------------------------------------------------------------     | TEASER CHECK (ONLY VERIFY PUBLISHED EXISTS)     |--------------------------------------------------------------------------     */public function teaserCheck($token)
    {
        $gift = Gift::where('share_token', $token)
            ->where('status', 'published')
            ->first();

        if (!$gift) {
            return response()->json([
                'exists' => false
            ], 404);
        }

        return response()->json([
            'exists' => true
        ]);
    }

    /*
     |--------------------------------------------------------------------------
     | 8️⃣ RAZORPAY PAYMENT
     |--------------------------------------------------------------------------
     */

    public function createOrder($id)
    {
        Log::info('[GIFT:PAYMENT] Create Order Attempt', ['gift_id' => $id]);

        try {
            $gift = Gift::where('id', $id)
                ->where('sender_id', Auth::id())
                ->where('status', 'draft')
                ->firstOrFail();

            $api = new \Razorpay\Api\Api(env('RAZORPAY_KEY_ID'), env('RAZORPAY_KEY_SECRET'));

            // Price from config
            $priceConfig = config('prices.valentine_premium');
            $amount = $priceConfig['amount'] * 100; // Amount in paise

            $orderData = [
                'receipt' => (string)$gift->id,
                'amount' => $amount,
                'currency' => $priceConfig['currency'],
                'notes' => [
                    'gift_id' => $gift->id,
                    'type' => 'valentine_premium'
                ]
            ];

            $razorpayOrder = $api->order->create($orderData);

            Log::info('[GIFT:PAYMENT] Order Created', [
                'gift_id' => $gift->id,
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

        }
        catch (Exception $e) {
            Log::error('[GIFT:PAYMENT] Order Creation Failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function verifyPayment(Request $request, $id)
    {
        Log::info('[GIFT:PAYMENT] Verification Attempt', ['gift_id' => $id]);

        try {
            $request->validate([
                'razorpay_payment_id' => 'required|string',
                'razorpay_order_id' => 'required|string',
                'razorpay_signature' => 'required|string',
            ]);

            $gift = Gift::where('id', $id)
                ->where('sender_id', Auth::id())
                ->firstOrFail();

            $api = new \Razorpay\Api\Api(env('RAZORPAY_KEY_ID'), env('RAZORPAY_KEY_SECRET'));

            $attributes = [
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature
            ];

            $api->utility->verifyPaymentSignature($attributes);

            // If signature verification is successful, update the gift
            $gift->payment_status = 'paid';
            $gift->amount = config('prices.valentine_premium.amount');
            $gift->save();

            Log::info('[GIFT:PAYMENT] Payment Verified', ['gift_id' => $gift->id]);

            return response()->json([
                'success' => true,
                'message' => 'Payment successful! Gift is ready to publish.',
                'gift' => $gift,
                'payment_status' => 'paid'
            ]);

        }
        catch (Exception $e) {
            Log::error('[GIFT:PAYMENT] Verification Failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Payment verification failed.'], 400);
        }
    }


}
