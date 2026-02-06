<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FreeGift;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;

class FreeGiftController extends Controller
{
    /**
     * Create a free gift
     * POST /api/free-gifts
     */
    public function store(Request $request)
    {
        \Log::info('AUTH CHECK', [
        'auth_id' => Auth::id(),
        'user' => Auth::user(),
        'session' => session()->all(),
        ]);

        $giftType = $request->input('gift_type');

        return match ($giftType) {
            'polaroid'   => $this->storePolaroid($request),
            'hug'        => $this->storeHug($request),
            'kisses'     => $this->storeKisses($request),
            'flowers'    => $this->storeFlowers($request),
            'balloons'   => $this->storeBalloons($request),
            'letter'     => $this->storeLetter($request),
            'chocolates' => $this->storeChocolates($request),
            'moment' => $this->storeMoment($request),
            default      => response()->json([
                'message' => 'Invalid gift type',
            ], 422),
        };
    }

    /**
     * STORE: POLAROID (IMAGE UPLOAD)
     */
private function storePolaroid(Request $request)
{
    /* =========================
       LOG: Incoming request
    ========================= */
    \Log::info('POLAROID REQUEST RECEIVED', [
        'all' => $request->all(),
        'has_image' => $request->hasFile('image'),
        'auth_id' => Auth::id(),
    ]);

    /* =========================
       VALIDATION
    ========================= */
    $validated = $request->validate([
        'gift_type' => 'required|string|max:50',
        'recipient_name' => 'required|string|max:100',
        'sender_name' => 'nullable|string|max:100',
        'message' => 'nullable|string',
        'image' => 'required|image|max:5120',
    ]);

    \Log::info('POLAROID VALIDATION PASSED', [
        'validated' => $validated,
    ]);

    /* =========================
       FILE INFO
    ========================= */
    $image = $request->file('image');

    if (!$image) {
        \Log::error('POLAROID IMAGE MISSING AFTER VALIDATION');
        return response()->json(['message' => 'Image missing'], 422);
    }

    \Log::info('POLAROID IMAGE INFO', [
        'original_name' => $image->getClientOriginalName(),
        'size_kb' => round($image->getSize() / 1024, 2),
        'mime' => $image->getMimeType(),
    ]);

    /* =========================
       FOLDER PREP
    ========================= */
    $folder = public_path('images/polaroid');

    if (!File::exists($folder)) {
        File::makeDirectory($folder, 0755, true);
        \Log::info('POLAROID FOLDER CREATED', ['path' => $folder]);
    } else {
        \Log::info('POLAROID FOLDER EXISTS', ['path' => $folder]);
    }

    /* =========================
       FILE MOVE
    ========================= */
    $filename = Str::uuid() . '.' . $image->getClientOriginalExtension();

    try {
        $image->move($folder, $filename);

        \Log::info('POLAROID IMAGE MOVED', [
            'filename' => $filename,
            'full_path' => $folder . '/' . $filename,
        ]);
    } catch (\Exception $e) {
        \Log::error('POLAROID IMAGE MOVE FAILED', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'message' => 'Image upload failed',
        ], 500);
    }

    /* =========================
       URL GENERATION
    ========================= */
    $imageUrl = asset('images/polaroid/' . $filename);

    \Log::info('POLAROID IMAGE URL', [
        'url' => $imageUrl,
    ]);

    /* =========================
       DB INSERT
    ========================= */
    try {
        $gift = FreeGift::create([
            'sender_id' => Auth::id(),
            'gift_type' => 'polaroid',
            'recipient_name' => $validated['recipient_name'],
            'sender_name' => $validated['sender_name'] ?? null,
            'gift_data' => [
                'message' => $validated['message'] ?? null,
                'image_url' => $imageUrl,
            ],
            'share_token' => $this->generateUniqueToken(),
        ]);

        \Log::info('POLAROID DB STORED', [
            'gift_id' => $gift->id,
            'token' => $gift->share_token,
        ]);

    } catch (\Exception $e) {
        \Log::error('POLAROID DB INSERT FAILED', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'message' => 'Database insert failed',
        ], 500);
    }

    /* =========================
       SUCCESS RESPONSE
    ========================= */
    return response()->json([
        'token' => $gift->share_token,
        'share_url' =>
            config('app.frontend_url') . '/free-gifts/polaroid/' . $gift->share_token,
        'image_url' => $imageUrl,
    ], 201);
}


    /**
     * STORE: HUG
     */
    private function storeHug(Request $request)
    {
        $validated = $request->validate([
            'gift_type' => 'required|string|max:50',
            'recipient_name' => 'required|string|max:100',
            'sender_name' => 'required|string|max:100',
            'gift_data.hug_style' => 'required|integer|min:1|max:10',
        ]);

        $gift = FreeGift::create([
            'sender_id' => Auth::id(),
            'gift_type' => 'hug',
            'recipient_name' => $validated['recipient_name'],
            'sender_name' => $validated['sender_name'],
            'gift_data' => [
                'hug_style' => $validated['gift_data']['hug_style'],
            ],
            'share_token' => $this->generateUniqueToken(),
        ]);

        return response()->json([
            'token' => $gift->share_token,
            'share_url' =>
                config('app.frontend_url') . '/free-gifts/hug/' . $gift->share_token,
        ], 201);
    }

    /**
     * STORE: KISSES
     */
    private function storeKisses(Request $request)
    {
        $validated = $request->validate([
            'gift_type' => 'required|string|max:50',
            'recipient_name' => 'required|string|max:100',
            'sender_name' => 'required|string|max:100',
            'gift_data.kisses' => 'required|array|min:1|max:20',
            'gift_data.kisses.*.x' => 'required|numeric|min:0|max:100',
            'gift_data.kisses.*.y' => 'required|numeric|min:0|max:100',
            'gift_data.kisses.*.rotation' => 'required|numeric',
            'gift_data.kisses.*.scale' => 'required|numeric',
        ]);

        $gift = FreeGift::create([
            'sender_id' => Auth::id(),
            'gift_type' => 'kisses',
            'recipient_name' => $validated['recipient_name'],
            'sender_name' => $validated['sender_name'],
            'gift_data' => [
                'kisses' => $validated['gift_data']['kisses'],
            ],
            'share_token' => $this->generateUniqueToken(),
        ]);

        return response()->json([
            'token' => $gift->share_token,
            'share_url' =>
                config('app.frontend_url') . '/free-gifts/kisses/' . $gift->share_token,
        ], 201);
    }

    /**
     * STORE: FLOWERS
     */
    private function storeFlowers(Request $request)
    {
        $validated = $request->validate([
            'gift_type' => 'required|string|max:50',
            'recipient_name' => 'required|string|max:100',
            'sender_name' => 'required|string|max:100',
            'gift_data.flower_id' => 'required|string|max:50',
            'gift_data.message' => 'nullable|string|max:500',
        ]);

        $gift = FreeGift::create([
            'sender_id' => Auth::id(),
            'gift_type' => 'flowers',
            'recipient_name' => $validated['recipient_name'],
            'sender_name' => $validated['sender_name'],
            'gift_data' => [
                'flower_id' => $validated['gift_data']['flower_id'],
                'message' => $validated['gift_data']['message'] ?? null,
            ],
            'share_token' => $this->generateUniqueToken(),
        ]);

        return response()->json([
            'token' => $gift->share_token,
            'share_url' =>
                config('app.frontend_url') . '/free-gifts/flowers/' . $gift->share_token,
        ], 201);
    }

    /**
     * STORE: BALLOONS
     */
    private function storeBalloons(Request $request)
    {
        $validated = $request->validate([
            'gift_type' => 'required|string|max:50',
            'recipient_name' => 'required|string|max:100',
            'sender_name' => 'required|string|max:100',
            'gift_data.messages' => 'required|array|min:1|max:10',
            'gift_data.messages.*' => 'required|string|max:100',
        ]);

        $gift = FreeGift::create([
            'sender_id' => Auth::id(),
            'gift_type' => 'balloons',
            'recipient_name' => $validated['recipient_name'],
            'sender_name' => $validated['sender_name'],
            'gift_data' => [
                'messages' => $validated['gift_data']['messages'],
            ],
            'share_token' => $this->generateUniqueToken(),
        ]);

        return response()->json([
            'token' => $gift->share_token,
            'share_url' =>
                config('app.frontend_url') . '/free-gifts/balloons/' . $gift->share_token,
        ], 201);
    }

    /**
     * STORE: LETTER
     */
private function storeLetter(Request $request)
{
    $validated = $request->validate([
        'gift_type' => 'required|string|max:50',
        'recipient_name' => 'required|string|max:100',
        'sender_name' => 'required|string|max:100',
        'gift_data.content' => 'required|string|max:5000',
        'gift_data.paper' => 'nullable|string|max:50',
    ]);

    $gift = FreeGift::create([
        'sender_id' => Auth::id(),
        'gift_type' => 'letter',
        'recipient_name' => $validated['recipient_name'],
        'sender_name' => $validated['sender_name'],
        'gift_data' => [
            'content' => $validated['gift_data']['content'],
            'paper'   => $validated['gift_data']['paper'] ?? 'classic',
        ],
        'share_token' => $this->generateUniqueToken(),
    ]);

    return response()->json([
        'token' => $gift->share_token,
        'share_url' =>
            config('app.frontend_url') . '/free-gifts/letter/' . $gift->share_token,
    ], 201);
}


    /**
     * STORE: CHOCOLATES
     */
    private function storeChocolates(Request $request)
    {
        $validated = $request->validate([
            'gift_type' => 'required|string|max:50',
            'recipient_name' => 'required|string|max:100',
            'sender_name' => 'required|string|max:100',
            'gift_data.messages' => 'required|array|min:1|max:12',
            'gift_data.messages.*' => 'required|string|max:200',
        ]);

        $gift = FreeGift::create([
            'sender_id' => Auth::id(),
            'gift_type' => 'chocolates',
            'recipient_name' => $validated['recipient_name'],
            'sender_name' => $validated['sender_name'],
            'gift_data' => [
                'messages' => $validated['gift_data']['messages'],
            ],
            'share_token' => $this->generateUniqueToken(),
        ]);

        return response()->json([
            'token' => $gift->share_token,
            'share_url' =>
                config('app.frontend_url') . '/free-gifts/chocolates/' . $gift->share_token,
        ], 201);
    }

    /**
 * STORE: MOMENT / SURPRISE
 */
private function storeMoment(Request $request)
{
    $validated = $request->validate([
        'gift_type' => 'required|string|max:50',
        'recipient_name' => 'required|string|max:100',
        'sender_name' => 'required|string|max:100',
        'gift_data.title' => 'required|string|max:150',
        'gift_data.message' => 'required|string|max:2000',
        'gift_data.date' => 'required|date',
        'gift_data.time' => 'nullable|string|max:10',
    ]);

    $gift = FreeGift::create([
        'sender_id' => Auth::id(), // nullable if guest
        'gift_type' => 'moment',
        'recipient_name' => $validated['recipient_name'],
        'sender_name' => $validated['sender_name'],
        'gift_data' => [
            'title' => $validated['gift_data']['title'],
            'message' => $validated['gift_data']['message'],
            'date' => $validated['gift_data']['date'],
            'time' => $validated['gift_data']['time'] ?? null,
        ],
        'share_token' => $this->generateUniqueToken(),
    ]);

    return response()->json([
        'token' => $gift->share_token,
        'share_url' =>
            config('app.frontend_url') . '/free-gifts/surprise/' . $gift->share_token,
        'gift' => $gift,
    ], 201);
}

    /**
     * VIEW (COMMON)
     */
public function show(string $token)
{
    $gift = FreeGift::where('share_token', $token)->first();

    if (!$gift) {
        return response()->json(['message' => 'Gift not found'], 404);
    }

    $gift->increment('view_count');

    $giftData = $gift->gift_data ?? [];

    /**
     * Normalize image URL ONLY if exists
     * (Polaroid support)
     */
    if (isset($giftData['image_url'])) {
        if (!str_starts_with($giftData['image_url'], 'http')) {
            $giftData['image_url'] = asset($giftData['image_url']);
        }
    }

    if (isset($giftData['image_path'])) {
        $giftData['image_url'] = asset($giftData['image_path']);
        unset($giftData['image_path']);
    }

    /**
     * RETURN ORIGINAL STRUCTURE
     */
    return response()->json([
        'gift_type' => $gift->gift_type,
        'recipient_name' => $gift->recipient_name,
        'sender_name' => $gift->sender_name,
        'gift_data' => $giftData,
    ]);
}
    



    private function generateUniqueToken(): string
    {
        do {
            $token = Str::random(32);
        } while (FreeGift::where('share_token', $token)->exists());

        return $token;
    }
}
