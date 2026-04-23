@extends('testing.layouts.app')

@section('title', 'عمليات التحميص - بن وبندق')
@section('page_title', 'عمليات التحميص')
@section('page_subtitle', 'إدارة طلبات التحميص ومتابعة حالاتها')

@section('content')
<div class="content-card mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div>
            <h2 class="h4 mb-1">إضافة عملية جديدة</h2>
            <div class="muted">اختر المنتج والكمية والموظف المسؤول عن التحميص.</div>
        </div>
        <a href="{{ route('testing.roasting.create') }}" class="btn btn-primary">+ عملية تحميص</a>
    </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4">
    <a class="btn {{ $filter === 'active' ? 'btn-primary' : 'btn-outline-light' }}" href="{{ route('testing.roasting.index', ['filter' => 'active']) }}">نشطة</a>
    <a class="btn {{ $filter === 'pending' ? 'btn-primary' : 'btn-outline-light' }}" href="{{ route('testing.roasting.index', ['filter' => 'pending']) }}">معلقة</a>
    <a class="btn {{ $filter === 'completed' ? 'btn-primary' : 'btn-outline-light' }}" href="{{ route('testing.roasting.index', ['filter' => 'completed']) }}">مكتملة</a>
</div>

<div class="row g-3">
    @forelse($requests as $request)
        <div class="col-lg-6">
            <div class="list-card h-100">
                <div class="d-flex justify-content-between gap-3 mb-3">
                    <div>
                        <h3 class="h5 mb-1">{{ $request->product?->name ?? 'منتج غير محدد' }}</h3>
                        <div class="muted small">{{ $request->code }} - {{ $request->quantity }} {{ $request->product?->unit }}</div>
                    </div>
                    @include('testing.partials.status-badge', ['status' => $request->status])
                </div>
                <p class="muted mb-3">{{ $request->notes ?: 'طلب تحميص جاهز للمتابعة من صفحة التفاصيل.' }}</p>
                <div class="row g-2 small mb-3">
                    <div class="col-6">الموظف: <span class="muted">{{ $request->assignedEmployee?->name ?? 'غير معين' }}</span></div>
                    <div class="col-6">الفرع: <span class="muted">{{ $request->branch?->name ?? '-' }}</span></div>
                    <div class="col-6">بدء: <span class="muted">{{ $request->started_at?->format('Y-m-d H:i') ?? '-' }}</span></div>
                    <div class="col-6">إنهاء: <span class="muted">{{ $request->completed_at?->format('Y-m-d H:i') ?? '-' }}</span></div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-light btn-sm" href="{{ route('testing.roasting.show', $request) }}">التفاصيل</a>
                    @if($request->status === \App\Models\RoastingRequest::STATUS_PENDING)
                        <form method="POST" action="{{ route('testing.roasting.assign', $request) }}" class="d-flex gap-2">
                            @csrf
                            <select name="assigned_to" class="form-select form-select-sm" required>
                                <option value="">تعيين موظف</option>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn-primary btn-sm">تعيين</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">@include('testing.partials.empty-state', ['message' => 'لا توجد عمليات ضمن هذا الفلتر.'])</div>
    @endforelse
</div>
@endsection
