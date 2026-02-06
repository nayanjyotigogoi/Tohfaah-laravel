@extends('admin.layouts.app')
@section('title', 'Dashboard')

@section('content')

<style>
/* ---------- RESPONSIVE DASHBOARD HELPERS ---------- */

@media (max-width: 768px) {
    h1 { font-size: 22px !important; }
    h3 { font-size: 18px !important; }
}

/* KPI cards grid fix */
.dashboard-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
}

/* ================= PERFECTLY ALIGNED MODERN TABLE ================= */

/* Shared column system */
.table-grid {
    display: grid;
    grid-template-columns: 3fr 1.5fr 1.5fr;
    align-items: center;
    column-gap: 16px; /* ✅ FIX */
}

.table-grid-4 {
    display: grid;
    grid-template-columns: 2.5fr 2.5fr 1.5fr 1.5fr;
    align-items: center;
    column-gap: 16px; /* ✅ FIX */
}


/* Table wrapper */
.table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* Header */
.table-head {
    padding: 0 16px;
    margin-bottom: 8px;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: .05em;
}

/* Row card */
.table-row {
    background: #ffffff;
    border-radius: 14px;
    padding: 14px 16px;
    margin-bottom: 10px;
    transition: all .2s ease;
}

.table-row:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px rgba(0,0,0,.08);
}

/* Cells */
.cell {
    font-size: 14px;
    color: #0f172a;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* Gift pill */
.gift-pill {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 999px;
    font-weight: 600;
    font-size: 13px;
    background: #eef2ff;
    color: #4338ca;
}

/* Numeric badge */
.metric {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 10px;
    font-weight: 600;
    background: #f1f5f9;
    font-variant-numeric: tabular-nums;
}

.metric.views {
    background: #ecfdf5;
    color: #166534;
}

.num {
    text-align: right;
}

/* Charts responsive */
canvas {
    max-width: 100%;
}
</style>

<!-- PAGE HEADER -->
<div style="margin-bottom:32px;">
    <h1 style="margin:0;font-size:28px;font-weight:700;">Dashboard</h1>
    <p style="margin-top:6px;color:#64748b;">
        High-level overview of platform activity and engagement
    </p>
</div>

<!-- KPI CARDS -->
<div class="dashboard-kpis">
    <div class="card"><p>Total Gifts</p><h2>{{ number_format($totalGifts) }}</h2></div>
    <div class="card"><p>Total Views</p><h2>{{ number_format($totalViews) }}</h2></div>
    <div class="card"><p>Gifts Today</p><h2>{{ number_format($todayGifts) }}</h2></div>
    <div class="card"><p>This Month</p><h2>{{ number_format($monthGifts) }}</h2></div>
</div>

<!-- HERO CHART -->
<div class="card" style="margin-top:40px;">
    <h3>Gift Creation Trend</h3>
    <canvas id="trendChart" height="120"></canvas>
</div>

<!-- SECONDARY CHARTS -->
<div class="grid" style="margin-top:40px;">
    <div class="card"><h3>Gift Type Distribution</h3><canvas id="typeChart" height="220"></canvas></div>
    <div class="card"><h3>Peak Activity Hours</h3><canvas id="hourChart" height="220"></canvas></div>
</div>

<!-- ROLE-BASED TABLES -->
<div style="margin-top:40px;">

{{-- ================= ADMIN VIEW ================= --}}
@if (!$isSuperAdmin)
    <h3>Most Viewed Gift Types</h3>

    <div class="table-wrap">

        <!-- Header -->
        <div class="table-head table-grid">
            <div>Gift Type</div>
            <div class="num">Total Sends</div>
            <div class="num">Total Views</div>
        </div>


        <!-- Rows -->
        @foreach ($topGiftTypesByViews as $row)
            <div class="table-row table-grid">
                <div class="cell">
                    <span class="gift-pill">{{ ucfirst($row->gift_type) }}</span>
                </div>
                <div class="cell num">
                    <span class="metric">{{ number_format($row->total_sends) }}</span>
                </div>
                <div class="cell num">
                    <span class="metric views">{{ number_format($row->total_views) }}</span>
                </div>
            </div>
        @endforeach


    </div>
@endif

{{-- ================= SUPER ADMIN VIEW ================= --}}
@if ($isSuperAdmin && $topGifts && $topGifts->count())
    <h3 style="margin-top:48px;">Recent Gift Transactions</h3>

    <div class="table-wrap">

        <!-- Header -->
        <div class="table-head table-grid-4">
            <div>Gift Type</div>
            <div>Recipient</div>
            <div class="num">Views</div>
            <div>Created</div>
        </div>

        <!-- Rows -->
        @foreach ($topGifts as $gift)
            <div class="table-row table-grid-4">
                <div class="cell">
                    <span class="gift-pill">{{ ucfirst($gift->gift_type) }}</span>
                </div>
                <div class="cell">{{ $gift->recipient_name }}</div>
                <div class="cell num">
                    <span class="metric views">{{ number_format($gift->view_count) }}</span>
                </div>
                <div class="cell">{{ $gift->created_at->format('d M Y') }}</div>
            </div>
        @endforeach

    </div>

    <div style="margin-top:16px;">
        {{ $topGifts->links('admin.components.pagination') }}
    </div>
@endif

</div>

<!-- CHARTS -->
<script>
new Chart(trendChart,{type:'line',data:{labels:{!! json_encode($dailyTrend->pluck('date')) !!},datasets:[
{label:'Gifts',data:{!! json_encode($dailyTrend->pluck('total')) !!},borderColor:'#6366f1',fill:true},
{label:'Views',data:{!! json_encode($dailyTrend->pluck('views')) !!},borderColor:'#22c55e',fill:true}
]}});
new Chart(typeChart,{type:'doughnut',data:{labels:{!! json_encode($giftTypes->pluck('gift_type')) !!},datasets:[{data:{!! json_encode($giftTypes->pluck('total')) !!}}]}});
new Chart(hourChart,{type:'bar',data:{labels:{!! json_encode($hourly->pluck('hour')) !!},datasets:[{data:{!! json_encode($hourly->pluck('total')) !!}}]}});
</script>

@endsection
