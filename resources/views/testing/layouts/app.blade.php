<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'لوحة اختبار بن وبندق')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #14091f;
            --panel: #21102f;
            --panel-2: #2c1740;
            --purple: #7c4dff;
            --purple-2: #a875ff;
            --gold: #f4c95d;
            --muted: #b8a8c9;
            --line: rgba(255,255,255,.12);
        }
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(124,77,255,.22), transparent 32rem),
                linear-gradient(135deg, #11081b, #21102f 48%, #12091c);
            color: #fff;
            font-family: "Cairo", "Tahoma", "Arial", sans-serif;
            font-weight: 700;
        }
        a { color: inherit; text-decoration: none; }
        .testing-navbar {
            background: rgba(22, 10, 34, .94);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(14px);
        }
        .brand-mark {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--purple), var(--gold));
            font-weight: 900;
        }
        .navbar .nav-link {
            color: #e9ddf7;
            border-radius: 14px;
            padding: 9px 12px;
        }
        .navbar .nav-link:hover, .navbar .nav-link.active {
            background: rgba(124,77,255,.2);
            color: #fff;
        }
        .testing-main {
            width: min(1220px, calc(100% - 32px));
            margin: 0 auto;
            padding: 28px 0 44px;
        }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 24px;
        }
        .content-card, .stat-card, .list-card {
            background: rgba(33,16,47,.86);
            border: 1px solid var(--line);
            border-radius: 22px;
            box-shadow: 0 16px 44px rgba(0,0,0,.18);
        }
        .content-card { padding: 24px; }
        .stat-card { padding: 18px; }
        .list-card { padding: 18px; }
        .muted { color: var(--muted); }
        .form-control, .form-select {
            color: #fff;
            background-color: rgba(255,255,255,.07);
            border-color: var(--line);
            border-radius: 14px;
        }
        .form-control:focus, .form-select:focus {
            color: #fff;
            background-color: rgba(255,255,255,.1);
            border-color: var(--purple-2);
            box-shadow: 0 0 0 .2rem rgba(124,77,255,.18);
        }
        .form-select option { color: #20122d; }
        .btn-primary {
            background: linear-gradient(135deg, var(--purple), #5f37d6);
            border: 0;
            border-radius: 14px;
        }
        .btn-outline-light, .btn-outline-warning, .btn-outline-danger, .btn-outline-success {
            border-radius: 14px;
        }
        .badge-soft {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: 7px 11px;
            font-size: .78rem;
            font-weight: 700;
        }
        .badge-pending { background: rgba(244,201,93,.16); color: #ffe08a; }
        .badge-assigned, .badge-ready_for_pickup { background: rgba(99,179,237,.16); color: #9bd7ff; }
        .badge-in_progress, .badge-transferred { background: rgba(168,117,255,.18); color: #cfb8ff; }
        .badge-completed, .badge-delivered, .badge-accepted, .badge-in { background: rgba(72,187,120,.16); color: #9df2bf; }
        .badge-cancelled, .badge-rejected, .badge-out { background: rgba(245,101,101,.16); color: #ffb0b0; }
        .badge-adjustment, .badge-neutral { background: rgba(255,255,255,.12); color: #ddd1e9; }
        .table { --bs-table-color: #fff; --bs-table-bg: transparent; --bs-table-border-color: var(--line); }
        .empty-state {
            border: 1px dashed var(--line);
            border-radius: 18px;
            padding: 28px;
            color: var(--muted);
            text-align: center;
        }
        .auth-wrap {
            max-width: 560px;
            margin: 5vh auto;
        }
        @media (max-width: 992px) {
            .testing-main { width: min(100% - 24px, 1220px); padding-top: 18px; }
            .navbar .nav-link { margin-top: 6px; }
        }
    </style>
</head>
<body>
@php
    $user = auth()->user();
    $role = $user?->role?->slug;
@endphp
<nav class="navbar navbar-expand-lg testing-navbar sticky-top">
    <div class="container-fluid px-3 px-lg-4">
        <a class="navbar-brand d-flex align-items-center gap-3 text-white" href="{{ auth()->check() ? route('testing.employee.dashboard') : route('testing.login') }}">
            <span class="brand-mark">ب</span>
            <span>
                <span class="d-block fw-black">بن وبندق</span>
                <small class="muted">واجهة الاختبار الداخلي</small>
            </span>
        </a>
        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#testingNavbar" aria-controls="testingNavbar" aria-expanded="false" aria-label="فتح القائمة">
            <span class="text-white fs-3">☰</span>
        </button>
        <div class="collapse navbar-collapse" id="testingNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 gap-lg-1">
                @auth
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('testing.employee.dashboard') ? 'active' : '' }}" href="{{ route('testing.employee.dashboard') }}">لوحتي</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('testing.notifications.*') ? 'active' : '' }}" href="{{ route('testing.notifications.index') }}">الإشعارات</a>
                    </li>

                    @if($role === \App\Models\Role::MANAGER)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('testing.manager.dashboard') ? 'active' : '' }}" href="{{ route('testing.manager.dashboard') }}">لوحة المدير</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('testing.manager.employee-requests') ? 'active' : '' }}" href="{{ route('testing.manager.employee-requests') }}">طلبات الانضمام</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('testing.roasting.*') ? 'active' : '' }}" href="{{ route('testing.roasting.index') }}">التحميص</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('testing.inventory.*') ? 'active' : '' }}" href="{{ route('testing.inventory.index') }}">المخزون</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('testing.distribution.*') ? 'active' : '' }}" href="{{ route('testing.distribution.index') }}">التوزيع</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('testing.reports.*') ? 'active' : '' }}" href="{{ route('testing.reports.performance') }}">التقارير</a>
                        </li>
                    @endif

                    @if($role === \App\Models\Role::ROASTING_EMPLOYEE)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('testing.roasting.tasks') ? 'active' : '' }}" href="{{ route('testing.roasting.tasks') }}">مهام التحميص</a>
                        </li>
                    @endif

                    @if($role === \App\Models\Role::INVENTORY_EMPLOYEE)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('testing.inventory.index') ? 'active' : '' }}" href="{{ route('testing.inventory.index') }}">المخزون</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('testing.inventory.movements') ? 'active' : '' }}" href="{{ route('testing.inventory.movements') }}">حركات المخزون</a>
                        </li>
                    @endif

                    @if($role === \App\Models\Role::DISTRIBUTION_EMPLOYEE)
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('testing.distribution.tasks') ? 'active' : '' }}" href="{{ route('testing.distribution.tasks') }}">مهام التوزيع</a>
                        </li>
                    @endif
                @else
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('testing.login') ? 'active' : '' }}" href="{{ route('testing.login') }}">تسجيل الدخول</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('testing.register') ? 'active' : '' }}" href="{{ route('testing.register') }}">إنشاء حساب</a>
                    </li>
                @endauth
            </ul>
            <div class="d-flex align-items-center gap-2">
                @auth
                    <span class="badge-soft badge-neutral">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('testing.logout') }}">
                        @csrf
                        <button class="btn btn-outline-light btn-sm">تسجيل الخروج</button>
                    </form>
                @else
                    <a class="btn btn-outline-light btn-sm" href="{{ route('testing.login') }}">دخول</a>
                    <a class="btn btn-primary btn-sm" href="{{ route('testing.register') }}">حساب جديد</a>
                @endauth
            </div>
        </div>
    </div>
</nav>

<div>
    <main class="testing-main">
        <div class="topbar">
            <div>
                <h1 class="h3 mb-1">@yield('page_title', 'واجهة الاختبار')</h1>
                <div class="muted">@yield('page_subtitle', 'اختبار تدفقات النظام من المتصفح')</div>
            </div>
        </div>

        @if(session('status'))
            <div class="alert alert-success border-0 rounded-4">{{ session('status') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger border-0 rounded-4">
                <div class="fw-bold mb-1">يوجد خطأ يحتاج انتباهك:</div>
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
