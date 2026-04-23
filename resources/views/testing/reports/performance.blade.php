@extends('testing.layouts.app')

@section('title', 'تقارير الأداء - بن وبندق')
@section('page_title', 'تقارير الأداء')
@section('page_subtitle', 'الأرقام محسوبة مباشرة من جداول التحميص والتوزيع')

@section('content')
<div class="content-card mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <div class="muted">إجمالي العمليات</div>
            <div class="display-4 fw-bold">{{ $totalOperations }}</div>
        </div>
        <div class="text-end">
            <div class="muted">نسبة تسليم الشحنات</div>
            <div class="display-6 fw-bold">{{ $deliveryRate }}%</div>
        </div>
    </div>
    <div class="progress mt-3" role="progressbar" aria-valuenow="{{ $deliveryRate }}" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar" style="width: {{ $deliveryRate }}%"></div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card"><div class="muted">تحميص اليوم</div><div class="display-6 fw-bold">{{ $roastingToday }}</div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="muted">تحميص هذا الأسبوع</div><div class="display-6 fw-bold">{{ $roastingWeek }}</div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="muted">تحميص هذا الشهر</div><div class="display-6 fw-bold">{{ $roastingMonth }}</div></div></div>
</div>

<div class="row g-3">
    <div class="col-md-4"><div class="stat-card"><div class="muted">إجمالي الشحنات</div><div class="display-6 fw-bold">{{ $totalShipments }}</div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="muted">شحنات اليوم</div><div class="display-6 fw-bold">{{ $distributionToday }}</div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="muted">شحنات مسلمة</div><div class="display-6 fw-bold">{{ $deliveredShipments }}</div></div></div>
</div>
@endsection
