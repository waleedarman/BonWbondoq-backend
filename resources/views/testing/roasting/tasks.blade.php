@extends('testing.layouts.app')

@section('title', 'مهام التحميص اليومية - بن وبندق')
@section('page_title', 'مهام التحميص اليومية')
@section('page_subtitle', 'المهام المعينة لحسابك الحالي فقط')

@section('content')
<div class="row g-3">
    @forelse($tasks as $task)
        <div class="col-lg-6">
            <div class="list-card h-100">
                <div class="d-flex justify-content-between gap-3 mb-3">
                    <div>
                        <h3 class="h5 mb-1">{{ $task->product?->name ?? 'منتج غير محدد' }}</h3>
                        <div class="muted small">{{ $task->quantity }} {{ $task->product?->unit }} - {{ $task->branch?->name }}</div>
                    </div>
                    @include('testing.partials.priority-badge', ['priority' => $task->priority])
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    @include('testing.partials.status-badge', ['status' => $task->status])
                    <span class="muted small">بدأت: {{ $task->started_at?->diffForHumans() ?? '-' }}</span>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('testing.roasting.show', $task) }}" class="btn btn-outline-light btn-sm">التفاصيل</a>
                    @if(in_array($task->status, [\App\Models\RoastingRequest::STATUS_PENDING, \App\Models\RoastingRequest::STATUS_ASSIGNED], true))
                        <form method="POST" action="{{ route('testing.roasting.tasks.start', $task) }}">
                            @csrf
                            <button class="btn btn-primary btn-sm">بدء المهمة</button>
                        </form>
                    @elseif($task->status === \App\Models\RoastingRequest::STATUS_IN_PROGRESS)
                        <form method="POST" action="{{ route('testing.roasting.tasks.complete', $task) }}">
                            @csrf
                            <button class="btn btn-outline-success btn-sm">إنهاء المهمة</button>
                        </form>
                    @else
                        <span class="badge-soft badge-completed">لا يوجد إجراء مطلوب</span>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">@include('testing.partials.empty-state', ['message' => 'لا توجد مهام تحميص معينة لك حاليا.'])</div>
    @endforelse
</div>
@endsection
