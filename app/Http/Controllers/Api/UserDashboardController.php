<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class UserDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        Log::info('DASHBOARD[0]: Request hit', [
            'auth_user' => $user ? $user->id : null,
            'has_session' => session()->isStarted(),
        ]);

        if (!$user) {
            Log::error('DASHBOARD[AUTH_FAIL]: No authenticated user');
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $userId = $user->id;

        /* ------------------ FREE GIFTS ------------------ */

        $freeGifts = DB::table('free_gifts')
            ->where('sender_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        Log::info('DASHBOARD[1]: Free gifts fetched', [
            'count' => $freeGifts->count(),
        ]);

        /* ------------------ PREMIUM GIFTS ------------------ */

        $premiumGifts = DB::table('gifts')
            ->where('sender_id', $userId)
            ->orderByDesc('updated_at')
            ->get();

        Log::info('DASHBOARD[2]: Premium gifts fetched', [
            'count' => $premiumGifts->count(),
            'statuses' => $premiumGifts->pluck('status')->unique()->values(),
        ]);

        /* ------------------ MEMORY MAPS ------------------ */

        /* ------------------ MEMORY MAPS ------------------ */

$memoryMaps = DB::table('memory_maps as mm')
    ->leftJoin('memory_map_participants as mmp', function ($join) use ($userId) {
        $join->on('mm.id', '=', 'mmp.memory_map_id')
             ->where('mmp.user_id', '=', $userId);
    })
    ->where(function ($query) use ($userId) {
        $query->where('mm.owner_id', $userId)
              ->orWhereNotNull('mmp.user_id');
    })
    ->select('mm.*')
    ->distinct()
    ->orderByDesc('mm.updated_at')
    ->get();

Log::info('DASHBOARD[MM]: Memory maps fetched', [
    'count' => $memoryMaps->count(),
]);



        Log::info('DASHBOARD[MM]: Memory maps fetched', [
            'count' => $memoryMaps->count(),
        ]);


        /* ------------------ COUNTS ------------------ */

        $stats = [
            'free_gifts' => $freeGifts->count(),
            'premium_live' => $premiumGifts->where('status', 'published')->count(),
            'premium_drafts' => $premiumGifts->where('status', 'draft')->count(),
            'memory_maps' => $memoryMaps->count(), // ADD THIS
        ];


        Log::info('DASHBOARD[3]: Stats computed', $stats);

        /* ------------------ MAP RESPONSE ------------------ */

        $frontend = rtrim(config('app.frontend_url'), '/');

        $freeMapped = $freeGifts->map(fn ($g) => [
            'id' => $g->id,
            'type' => ucfirst($g->gift_type),
            'recipient' => $g->recipient_name,
            'date' => Carbon::parse($g->created_at)->format('M d, Y'),
            'link' => "{$frontend}/free-gifts/{$g->gift_type}/{$g->share_token}",
        ]);


        $premiumMapped = $premiumGifts->map(fn ($g) => [
            'id' => $g->id,
            'type' => 'Premium Experience',
            'recipient' => $g->recipient_name,
            'status' => $g->status,
            'date' => Carbon::parse($g->updated_at)->format('M d, Y'),
            'share_token' => $g->share_token,
            'link' => $g->status === 'published'
                ? "{$frontend}/premium-gifts/valentine/{$g->share_token}"
                : null,
        ]);

$memoryMapped = $memoryMaps->map(function ($map) use ($frontend, $userId) {

    $isOwner = $map->owner_id == $userId;

    $participantRole = DB::table('memory_map_participants')
        ->where('memory_map_id', $map->id)
        ->where('user_id', $userId)
        ->value('role');

    $participantsCount = DB::table('memory_map_participants')
        ->where('memory_map_id', $map->id)
        ->whereIn('status', ['invited', 'active'])
        ->count();

    return [
        'id' => $map->id,
        'title' => $map->title ?? 'Untitled Map',
        'description' => $map->description,
        'status' => $map->status,
        'payment_status' => $map->payment_status,
        'max_participants' => $map->max_participants,
        'participants_count' => $participantsCount,
        'is_owner' => $isOwner,
        'role' => $isOwner ? 'owner' : $participantRole,
        'has_password' => (bool) $map->has_password,
        'published_at' => $map->published_at,
        'date' => Carbon::parse($map->updated_at)->format('M d, Y'),
        'share_token' => $map->share_token,
        'link' => $map->status === 'active'
            ? "{$frontend}/memory-map/{$map->share_token}"
            : null,
    ];
});





        $recent = collect()
            ->merge($freeMapped->map(fn ($g) => $g + ['category' => 'free']))
            ->merge($premiumMapped->map(fn ($g) => $g + ['category' => 'premium']))
            ->sortByDesc(fn ($g) => strtotime($g['date']))
            ->take(3)
            ->values();

        Log::info('DASHBOARD[4]: Recent activity', [
            'count' => $recent->count(),
        ]);

        return response()->json([
            'stats' => $stats,
            'free' => $freeMapped,
            'paid' => $premiumMapped->where('status', 'published')->values(),
            'drafts' => $premiumMapped->where('status', 'draft')->values(),
            'memory_maps' => $memoryMapped->values(), // ADD THIS
            'recent_activity' => $recent,
        ]);

    }
}
