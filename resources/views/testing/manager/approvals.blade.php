@extends('testing.layouts.app')

@section('title', 'طلبات الانضمام - بن وبندق')
@section('page_title', 'طلبات انضمام الموظفين')
@section('page_subtitle', 'راجع الطلبات المعلقة وحدد دور الموظف عند القبول')

@section('content')
<div class="row g-3">
    @forelse($requests as $employeeRequest)
        <div class="col-lg-6">
            <div class="list-card h-100">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h3 class="h5 mb-1">{{ $employeeRequest->user?->name }}</h3>
                        <div class="muted small">{{ $employeeRequest->created_at?->diffForHumans() }}</div>
                    </div>
                    @include('testing.partials.status-badge', ['status' => $employeeRequest->status])
                </div>
                <div class="mb-3">
                    <div>البريد: <span class="muted">{{ $employeeRequest->user?->email }}</span></div>
                    <div>الهاتف: <span class="muted">{{ $employeeRequest->user?->phone }}</span></div>
                    <div>الفرع: <span class="muted">{{ $employeeRequest->user?->branch?->name ?? 'غير محدد' }}</span></div>
                    <span class="badge-soft badge-neutral mt-2">موظف جديد</span>
                </div>

                <form method="POST" action="{{ route('testing.manager.employee-requests.approve', $employeeRequest) }}" class="row g-2 mb-2">
                    @csrf
                    <div class="col">
                        <select name="role_id" class="form-select" required>
                            <option value="">اختر الدور</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }} - {{ $role->slug }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-primary">قبول</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('testing.manager.employee-requests.reject', $employeeRequest) }}" class="row g-2">
                    @csrf
                    <div class="col">
                        <input name="rejection_reason" class="form-control" placeholder="سبب الرفض اختياري">
                    </div>
                    <div class="col-auto">
                        <button class="btn btn-outline-danger">رفض</button>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="col-12">@include('testing.partials.empty-state', ['message' => 'لا توجد طلبات انضمام معلقة حاليا.'])</div>
    @endforelse
</div>
@endsection
