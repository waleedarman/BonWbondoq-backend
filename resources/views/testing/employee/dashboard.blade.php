@extends('testing.layouts.app')

@section('title', 'لوحة الموظف - بن وبندق')
@section('page_title', 'لوحة الموظف')
@section('page_subtitle', 'ملخص المهام الحالية للحساب المسجل')

@section('content')
<div class="content-card mb-4">
    <h2 class="h4 mb-1">أهلا، {{ auth()->user()->name }}</h2>
    <div class="muted">لديك {{ $roastingTasks->count() }} مهمة تحميص و {{ $distributionTasks->count() }} مهمة توزيع.</div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="content-card h-100">
            <h3 class="h5 mb-3">مهام التحميص</h3>
            @forelse($roastingTasks as $task)
                <div class="list-card mb-3">
                    <div class="d-flex justify-content-between">
                        <strong>{{ $task->product?->name }}</strong>
                        @include('testing.partials.status-badge', ['status' => $task->status])
                    </div>
                    <div class="muted small mt-1">{{ $task->quantity }} {{ $task->product?->unit }} - {{ $task->branch?->name }}</div>
                    <a class="btn btn-outline-light btn-sm mt-3" href="{{ route('testing.roasting.show', $task) }}">فتح المهمة</a>
                </div>
            @empty
                @include('testing.partials.empty-state', ['message' => 'لا توجد مهام تحميص.'])
            @endforelse
        </div>
    </div>
    <div class="col-lg-6">
        <div class="content-card h-100">
            <h3 class="h5 mb-3">مهام التوزيع</h3>
            @forelse($distributionTasks as $shipment)
                <div class="list-card mb-3">
                    <div class="d-flex justify-content-between">
                        <strong>{{ $shipment->product?->name }}</strong>
                        @include('testing.partials.status-badge', ['status' => $shipment->status])
                    </div>
                    <div class="muted small mt-1">{{ $shipment->shipment_code }} - {{ $shipment->destination }}</div>
                </div>
            @empty
                @include('testing.partials.empty-state', ['message' => 'لا توجد مهام توزيع.'])
            @endforelse
        </div>
    </div>
</div>
@endsection
