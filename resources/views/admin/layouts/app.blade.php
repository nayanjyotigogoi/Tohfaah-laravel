<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield('title', 'Admin Dashboard')</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        :root {
            --primary:#6366f1;
            --bg:#f5f7fb;
            --card:#fff;
            --text:#0f172a;
            --muted:#64748b;
        }

        * { box-sizing:border-box; font-family:Inter,sans-serif; }
        body { margin:0; background:var(--bg); color:var(--text); }

        /* Layout */
        .layout { display:flex; min-height:100vh; }

        /* Sidebar */
        .sidebar {
            width:260px;
            background:#020617;
            color:#fff;
            padding:24px;
            position:fixed;
            inset:0 auto 0 0;
            transform:translateX(0);
            transition:.3s;
            z-index:1000;
        }

        .sidebar h1 { font-size:20px; margin-bottom:32px; }

        .nav a {
            display:flex;
            align-items:center;
            gap:12px;
            padding:12px 14px;
            border-radius:10px;
            text-decoration:none;
            color:#c7d2fe;
            margin-bottom:6px;
        }

        .nav a:hover { background:rgba(255,255,255,.08); color:#fff; }

        /* Main */
        .main {
            margin-left:260px;
            flex:1;
            display:flex;
            flex-direction:column;
            transition:.3s;
        }

        .topbar {
            background:#fff;
            padding:14px 20px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            border-bottom:1px solid #e5e7eb;
        }

        .menu-btn {
            display:none;
            cursor:pointer;
        }

        .content { padding:24px; }

        /* Cards */
        .grid {
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
            gap:20px;
        }

        .card {
            background:var(--card);
            border-radius:16px;
            padding:20px;
            box-shadow:0 10px 25px rgba(0,0,0,.06);
        }

        /* Tables */
        .table-wrap {
            overflow-x:auto;
        }

        table {
            width:100%;
            border-collapse:collapse;
            min-width:600px;
        }

        th,td {
            padding:14px;
            border-bottom:1px solid #e5e7eb;
        }

        th { background:#f1f5f9; }

        /* Buttons */
        button {
            border:none;
            padding:8px 14px;
            border-radius:8px;
            cursor:pointer;
            font-weight:500;
        }

        .btn { background:var(--primary); color:#fff; }
        .btn-outline { background:none; border:1px solid var(--primary); color:var(--primary); }
        .btn-danger { background:#ef4444; color:#fff; }

        /* Mobile */
        @media(max-width:1024px){
            .main { margin-left:0; }
            .sidebar { transform:translateX(-100%); }
            .sidebar.open { transform:translateX(0); }
            .menu-btn { display:block; }
        }
    </style>
</head>

<body>

<div class="layout">
    <aside class="sidebar" id="sidebar">
        <h1>Tohfaah Admin</h1>
        <nav class="nav">
            <a href="{{ route('admin.dashboard') }}"><span class="material-icons">dashboard</span>Dashboard</a>
            @if(auth('admin')->user()->isSuperAdmin())
                <a href="{{ route('admin.export.csv') }}">
                    <span class="material-icons">download</span>
                    Export CSV
                </a>
            @endif

            @if(auth('admin')->user()->isSuperAdmin())
                <a href="{{ route('admin.admins') }}"><span class="material-icons">admin_panel_settings</span>Admins</a>
            @endif
        </nav>
    </aside>

    <div class="main">
        <div class="topbar">
            <span class="material-icons menu-btn" onclick="toggleSidebar()">menu</span>
            <strong>Admin Panel</strong>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="btn-outline">Logout</button>
            </form>
        </div>

        <div class="content">@yield('content')</div>
    </div>
</div>

<script>
function toggleSidebar(){
    document.getElementById('sidebar').classList.toggle('open');
}
</script>

</body>
</html>
