<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FreeGift;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * Admin Dashboard Page
     */
    public function index()
    {
        $admin = auth('admin')->user();
        $isSuperAdmin = $admin->isSuperAdmin();

        /* ====== Core Stats ====== */
        $totalGifts  = FreeGift::count();                 // total gift records
        $totalViews  = FreeGift::sum('view_count');       // total views across all gifts
        $todayGifts  = FreeGift::whereDate('created_at', today())->count();
        $monthGifts  = FreeGift::whereMonth('created_at', now()->month)->count();

        /* ====== Daily Trend (Gifts + Views) ====== */
        $dailyTrend = FreeGift::selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as total')              // total sends per day
            ->selectRaw('SUM(view_count) as views')       // total views per day
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        /* ====== Gift Type Distribution (Total Sends) ====== */
        $giftTypes = FreeGift::select(
                'gift_type',
                DB::raw('COUNT(*) as total')              // total sends per gift type
            )
            ->groupBy('gift_type')
            ->orderByDesc('total')
            ->get();

        /* ====== Hourly Activity (Total Sends) ====== */
        $hourly = FreeGift::selectRaw('HOUR(created_at) as hour')
            ->selectRaw('COUNT(*) as total')              // total sends per hour
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(function ($row) {
                return [
                    'hour'  => str_pad($row->hour, 2, '0', STR_PAD_LEFT) . ':00',
                    'total' => $row->total,
                ];
            });

        /* ====== Top Gifts (Super Admin ONLY – transactional) ====== */
        $topGifts = $isSuperAdmin
            ? FreeGift::orderByDesc('created_at')->paginate(10)
            : null;

        /* ====== Admin-Safe Aggregates (CORRECT SEMANTICS) ====== */
        $topGiftTypesByViews = FreeGift::select(
                'gift_type',
                DB::raw('COUNT(*) as total_sends'),       // TOTAL times sent (not people)
                DB::raw('SUM(view_count) as total_views') // TOTAL views across all sends
            )
            ->groupBy('gift_type')
            ->orderByDesc('total_views')
            ->limit(10)
            ->get();

        /* ====== Best Performing Gift ====== */
        $bestGift = $topGiftTypesByViews->first();

        return view('admin.dashboard', compact(
            'isSuperAdmin',
            'totalGifts',
            'totalViews',
            'todayGifts',
            'monthGifts',
            'dailyTrend',
            'giftTypes',
            'hourly',
            'topGifts',
            'topGiftTypesByViews',
            'bestGift'
        ));
    }

    /**
     * Export CSV (Super Admin Only)
     */
    public function exportCsv()
    {
        $filename = 'free_gifts_' . now()->format('Y_m_d') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
        ];

        $callback = function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['ID', 'Gift Type', 'Recipient', 'Sender', 'Views', 'Created At']);

            FreeGift::chunk(500, function ($gifts) use ($file) {
                foreach ($gifts as $gift) {
                    fputcsv($file, [
                        $gift->id,
                        $gift->gift_type,
                        $gift->recipient_name,
                        $gift->sender_name,
                        $gift->view_count,
                        $gift->created_at,
                    ]);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
