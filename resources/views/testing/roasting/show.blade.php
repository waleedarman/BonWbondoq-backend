@extends('testing.layouts.app')

@section('title', 'تفاصيل عملية التحميص - بن وبندق')
@section('page_title', 'تفاصيل عملية التحميص')
@section('page_subtitle', $request->code)

@section('content')
<div class="row g-4">
    <div class="col-lg-7">
        <div class="content-card mb-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h2 class="h4">{{ $request->product?->name ?? 'منتج غير محدد' }}</h2>
                    <div class="muted">{{ $request->quantity }} {{ $request->product?->unit }} - {{ $request->branch?->name }}</div>
                </div>
                @include('testing.partials.status-badge', ['status' => $request->status])
            </div>
            <div class="row g-3">
                <div class="col-md-6">المنشئ: <strong>{{ $request->creator?->name ?? '-' }}</strong></div>
                <div class="col-md-6">الموظف: <strong>{{ $request->assignedEmployee?->name ?? 'غير معين' }}</strong></div>
                <div class="col-md-6">تاريخ الطلب: <strong>{{ $request->created_at?->format('Y-m-d H:i') }}</strong></div>
                <div class="col-md-6">الأولوية: @include('testing.partials.priority-badge', ['priority' => $request->priority])</div>
                <div class="col-md-6">بدأت: <strong>{{ $request->started_at?->format('Y-m-d H:i') ?? '-' }}</strong></div>
                <div class="col-md-6">اكتملت: <strong>{{ $request->completed_at?->format('Y-m-d H:i') ?? '-' }}</strong></div>
            </div>
        </div>

        <div class="content-card">
            <h3 class="h5 mb-3">خط سير التنفيذ</h3>
            @forelse($request->statusLogs as $log)
                <div class="list-card mb-3">
                    <div class="d-flex justify-content-between">
                        @include('testing.partials.status-badge', ['status' => $log->status])
                        <span class="muted small">{{ $log->created_at?->format('Y-m-d H:i') }}</span>
                    </div>
                    <div class="mt-2 muted">{{ $log->note ?: 'بدون ملاحظات' }}</div>
                    <div class="small mt-1">بواسطة: {{ $log->changer?->name ?? 'النظام' }}</div>
                </div>
            @empty
                @include('testing.partials.empty-state', ['message' => 'لا توجد سجلات حالة بعد.'])
            @endforelse
        </div>
    </div>
    <div class="col-lg-5">
        <div class="content-card mb-4">
            <h3 class="h5 mb-3">تحديث الحالة</h3>
            <form method="POST" action="{{ route('testing.roasting.status', $request) }}" class="row g-3">
                @csrf
                <div class="col-12">
                    <select name="status" class="form-select" required>
                        @foreach($statuses as $status)
                            <option value="{{ $status }}" @selected($request->status === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <textarea name="note" class="form-control" rows="3" placeholder="ملاحظة اختيارية"></textarea>
                </div>
                <div class="col-12 d-grid">
                    <button class="btn btn-primary">حفظ الحالة</button>
                </div>
            </form>
        </div>

        @if(auth()->user()?->role?->slug === \App\Models\Role::MANAGER)
            <div class="content-card">
                <h3 class="h5 mb-3">تعيين موظف</h3>
                <form method="POST" action="{{ route('testing.roasting.assign', $request) }}" class="row g-3">
                    @csrf
                    <div class="col-12">
                        <select name="assigned_to" class="form-select" required>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected($request->assigned_to === $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 d-grid">
                        <button class="btn btn-outline-light">تحديث التعيين</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
