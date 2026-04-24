@extends('testing.layouts.app')

@section('title', 'شحنات التوزيع - بن وبندق')
@section('page_title', 'شحنات التوزيع')
@section('page_subtitle', 'تعديل الموزع والوجهة أو إلغاء الشحنة من نفس الشاشة')

@section('content')
<div class="content-card mb-4">
    <div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
        <div>
            <h2 class="h5 mb-1">قائمة الشحنات</h2>
            <div class="muted">ابحث حسب الكود أو الوجهة أو المستلم.</div>
        </div>
        <a href="{{ route('testing.distribution.create') }}" class="btn btn-primary">+ شحنة جديدة</a>
    </div>
    <form method="GET" action="{{ route('testing.distribution.index') }}" class="row g-3">
        <div class="col-md-6">
            <input name="search" value="{{ request('search') }}" class="form-control" placeholder="بحث">
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
                <div class="row g-2 small mb-3">
                    <div class="col-6">الوجهة: <span class="muted">{{ $shipment->destination }}</span></div>
                    <div class="col-6">المستلم: <span class="muted">{{ $shipment->recipient_name }}</span></div>
                    <div class="col-6">الموزع: <span class="muted">{{ $shipment->assignedEmployee?->name ?? 'غير معين' }}</span></div>
                    <div class="col-6">التاريخ: <span class="muted">{{ $shipment->created_at?->format('Y-m-d') }}</span></div>
                </div>

                <form method="POST" action="{{ route('testing.distribution.update-details', $shipment) }}" class="row g-2 mb-2">
                    @csrf
                    <div class="col-md-5">
                        <select name="assigned_to" class="form-select form-select-sm" required>
                            @foreach($employees as $employee)
                                <option value="{{ $employee->id }}" @selected($shipment->assigned_to === $employee->id)>{{ $employee->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input name="destination" value="{{ $shipment->destination }}" class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-primary btn-sm">تعديل</button>
                    </div>
                </form>

                <div class="d-flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('testing.distribution.cancel', $shipment) }}">
                        @csrf
                        <button class="btn btn-outline-danger btn-sm" @disabled($shipment->status === \App\Models\DistributionShipment::STATUS_DELIVERED)>إلغاء الشحنة</button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="col-12">@include('testing.partials.empty-state', ['message' => 'لا توجد شحنات توزيع.'])</div>
    @endforelse
</div>
@endsection
