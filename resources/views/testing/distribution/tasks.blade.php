@extends('testing.layouts.app')

@section('title', 'مهام التوزيع - بن وبندق')
@section('page_title', 'مهام التوزيع')
@section('page_subtitle', 'الشحنات المعينة لحسابك الحالي فقط')

@section('content')
<div class="content-card mb-4">
    <form method="GET" action="{{ route('testing.distribution.tasks') }}" class="row g-3">
        <div class="col-md-6">
            <input name="search" value="{{ request('search') }}" class="form-control" placeholder="بحث في الشحنات">
        </div>
        <div class="col-md-4">
            <select name="status" class="form-select">
                <option value="">كل الحالات</option>
                @foreach($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 d-grid">
            <button class="btn btn-outline-light">تصفية</button>
        </div>
    </form>
</div>

<div class="row g-3">
    @forelse($shipments as $shipment)
        <div class="col-lg-6">
            <div class="list-card h-100">
                <div class="d-flex justify-content-between gap-3 mb-3">
                    <div>
                        <h3 class="h5 mb-1">{{ $shipment->product?->name ?? 'منتج غير محدد' }}</h3>
                        <div class="muted small">{{ $shipment->shipment_code }} - {{ $shipment->quantity }} {{ $shipment->product?->unit }}</div>
                    </div>
                    @include('testing.partials.status-badge', ['status' => $shipment->status])
                </div>
                <div class="mb-3">
                    <div>الوجهة: <span class="muted">{{ $shipment->destination }}</span></div>
                    <div>المستلم: <span class="muted">{{ $shipment->recipient_name }}</span></div>
                    <div>الفرع: <span class="muted">{{ $shipment->branch?->name ?? '-' }}</span></div>
                </div>
                <div class="d-flex gap-2">
                    @if(in_array($shipment->status, [\App\Models\DistributionShipment::STATUS_PENDING, \App\Models\DistributionShipment::STATUS_READY_FOR_PICKUP], true))
                        <form method="POST" action="{{ route('testing.distribution.tasks.transfer', $shipment) }}">
                            @csrf
                            <button class="btn btn-primary btn-sm">تحديد كمنقولة</button>
                        </form>
                    @elseif($shipment->status === \App\Models\DistributionShipment::STATUS_TRANSFERRED)
                        <form method="POST" action="{{ route('testing.distribution.tasks.deliver', $shipment) }}">
                            @csrf
                            <button class="btn btn-outline-success btn-sm">تحديد كمسلمة</button>
                        </form>
                    @else
                        <span class="badge-soft badge-completed">انتهت المهمة</span>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">@include('testing.partials.empty-state', ['message' => 'لا توجد شحنات معينة لك حاليا.'])</div>
    @endforelse
</div>
@endsection
