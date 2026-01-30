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

        /* ------------------ COUNTS ------------------ */

        $stats = [
            'free_gifts' => $freeGifts->count(),
            'premium_live' => $premiumGifts->where('status', 'live')->count(),
            'premium_drafts' => $premiumGifts->where('status', 'draft')->count(),
        ];

        Log::info('DASHBOARD[3]: Stats computed', $stats);

        /* ------------------ MAP RESPONSE ------------------ */

        $freeMapped = $freeGifts->map(fn ($g) => [
            'id' => $g->id,
            'type' => ucfirst($g->gift_type),
            'recipient' => $g->recipient_name,
            'date' => Carbon::parse($g->created_at)->format('M d, Y'),
            'link' => "tohfaah.com/free-gifts/{$g->gift_type}/{$g->share_token}",
        ]);

        $premiumMapped = $premiumGifts->map(fn ($g) => [
            'id' => $g->id,
            'type' => 'Premium Experience',
            'recipient' => $g->recipient_name,
            'status' => $g->status,
            'date' => Carbon::parse($g->updated_at)->format('M d, Y'),
            'link' => $g->status === 'live'
                ? "tohfaah.com/gift/{$g->share_token}"
                : null,
        ]);

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
            'paid' => $premiumMapped->where('status', 'live')->values(),
            'drafts' => $premiumMapped->where('status', 'draft')->values(),
            'recent_activity' => $recent,
        ]);
    }
}
