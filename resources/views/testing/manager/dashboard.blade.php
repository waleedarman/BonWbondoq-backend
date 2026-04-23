@extends('testing.layouts.app')

@section('title', 'لوحة المدير - بن وبندق')
@section('page_title', 'لوحة المدير')
@section('page_subtitle', 'نظرة سريعة على التحميص والتوزيع والمخزون')

@section('content')
<div class="content-card mb-4">
    <div class="d-flex flex-wrap justify-content-between gap-3">
        <div>
            <h2 class="h4 mb-2">صباح الخير، {{ auth()->user()->name }}</h2>
            <div class="muted">من هنا تتابع الموافقات، العمليات، والتقارير اليومية.</div>
        </div>
        <a href="{{ route('testing.manager.employee-requests') }}" class="btn btn-primary">مراجعة طلبات الانضمام</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="stat-card"><div class="muted">تحميص مكتمل</div><div class="display-6 fw-bold">{{ $stats['completed_roasting'] }}</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="muted">تحميص غير مكتمل</div><div class="display-6 fw-bold">{{ $stats['incomplete_roasting'] }}</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="muted">وظائف التوزيع</div><div class="display-6 fw-bold">{{ $stats['distribution_jobs'] }}</div></div></div>
    <div class="col-md-3"><div class="stat-card"><div class="muted">وظائف التحميص</div><div class="display-6 fw-bold">{{ $stats['roasting_jobs'] }}</div></div></div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="content-card h-100">
            <h3 class="h5 mb-3">آخر نشاطات التحميص</h3>
            @forelse($recentRoasting as $request)
                <div class="list-card mb-3">
                    <div class="d-flex justify-content-between gap-3">
                        <div>
                            <div class="fw-bold">{{ $request->product?->name ?? 'منتج غير محدد' }}</div>
                            <div class="muted small">{{ $request->code }} - الموظف: {{ $request->assignedEmployee?->name ?? 'غير معين' }}</div>
                        </div>
                        @include('testing.partials.status-badge', ['status' => $request->status])
                    </div>
                </div>
            @empty
                @include('testing.partials.empty-state', ['message' => 'لا توجد عمليات تحميص بعد.'])
            @endforelse
        </div>
    </div>
    <div class="col-lg-5">
        <div class="content-card h-100">
            <h3 class="h5 mb-3">مؤشرات تحتاج انتباه</h3>
            <div class="list-card mb-3 d-flex justify-content-between"><span>طلبات انضمام معلقة</span><strong>{{ $stats['pending_requests'] }}</strong></div>
            <div class="list-card mb-3 d-flex justify-content-between"><span>منتجات منخفضة المخزون</span><strong>{{ $stats['low_stock'] }}</strong></div>
            <div class="list-card d-flex justify-content-between"><span>آخر الشحنات</span><strong>{{ $recentShipments->count() }}</strong></div>
        </div>
    </div>
</div>
@endsection
