@extends('testing.layouts.app')

@section('title', 'الإشعارات - بن وبندق')
@section('page_title', 'الإشعارات')
@section('page_subtitle', 'إشعارات النظام الخاصة بالحساب الحالي')

@section('content')
<div class="content-card mb-4">
    <form method="POST" action="{{ route('testing.notifications.read-all') }}">
        @csrf
        <button class="btn btn-primary">تعليم الكل كمقروء</button>
    </form>
</div>

<div class="row g-3">
    @forelse($notifications as $notification)
        <div class="col-lg-6">
            <div class="list-card h-100 {{ $notification->is_read ? 'opacity-75' : '' }}">
                <div class="d-flex justify-content-between gap-3 mb-2">
                    <h3 class="h5 mb-0">{{ $notification->title }}</h3>
                    <span class="badge-soft {{ $notification->is_read ? 'badge-neutral' : 'badge-pending' }}">
                        {{ $notification->is_read ? 'مقروء' : 'جديد' }}
                    </span>
                </div>
                <div class="muted mb-3">{{ $notification->message }}</div>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small muted">{{ $notification->type }} - {{ $notification->created_at?->diffForHumans() }}</span>
                    @unless($notification->is_read)
                        <form method="POST" action="{{ route('testing.notifications.read', $notification) }}">
                            @csrf
                            <button class="btn btn-outline-light btn-sm">تعليم كمقروء</button>
                        </form>
                    @endunless
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">@include('testing.partials.empty-state', ['message' => 'لا توجد إشعارات حاليا.'])</div>
    @endforelse
</div>
@endsection
