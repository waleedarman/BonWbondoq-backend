@extends('testing.layouts.app')

@section('title', 'حركات المخزون - بن وبندق')
@section('page_title', 'حركات المخزون')
@section('page_subtitle', 'تسجيل ومراجعة الإدخال والإخراج والتعديل')

@section('content')
<div class="content-card mb-4">
    <h2 class="h5 mb-3">تسجيل حركة جديدة</h2>
    <form method="POST" action="{{ route('testing.inventory.movements.store') }}" class="row g-3">
        @csrf
        <div class="col-lg-3">
            <select name="product_id" class="form-select" required>
                <option value="">المنتج</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}">{{ $product->name }} - {{ $product->quantity }} {{ $product->unit }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2">
            <select name="movement_type" class="form-select" required>
                @foreach($types as $type)
                    <option value="{{ $type }}">{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2">
            <input type="number" step="0.01" min="0.01" name="quantity" class="form-control" placeholder="الكمية" required>
        </div>
        <div class="col-lg-3">
            <select name="reason" class="form-select" required>
                @foreach($reasons as $reason)
                    <option value="{{ $reason }}">{{ $reason }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-2 d-grid">
            <button class="btn btn-primary">تسجيل</button>
        </div>
        <div class="col-12">
            <input name="notes" class="form-control" placeholder="ملاحظات اختيارية">
        </div>
    </form>
</div>

<div class="content-card">
    <form method="GET" action="{{ route('testing.inventory.movements') }}" class="row g-3 mb-4">
        <div class="col-md-4">
            <select name="movement_type" class="form-select">
                <option value="">كل الأنواع</option>
                @foreach($types as $type)
                    <option value="{{ $type }}" @selected(request('movement_type') === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <select name="reason" class="form-select">
                <option value="">كل الأسباب</option>
                @foreach($reasons as $reason)
                    <option value="{{ $reason }}" @selected(request('reason') === $reason)>{{ $reason }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <input type="month" name="month" value="{{ request('month') }}" class="form-control">
        </div>
        <div class="col-md-1 d-grid">
            <button class="btn btn-outline-light">فلتر</button>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>المنتج</th><th>النوع</th><th>الكمية</th><th>السبب</th><th>المنفذ</th><th>التاريخ</th><th>مرجع</th></tr></thead>
            <tbody>
            @forelse($movements as $movement)
                <tr>
                    <td>{{ $movement->product?->name }}</td>
                    <td>@include('testing.partials.status-badge', ['status' => $movement->movement_type])</td>
                    <td>{{ $movement->quantity }}</td>
                    <td>{{ $movement->reason }}</td>
                    <td>{{ $movement->performer?->name ?? '-' }}</td>
                    <td>{{ $movement->created_at?->format('Y-m-d H:i') }}</td>
                    <td>{{ $movement->reference_type ? class_basename($movement->reference_type).' #'.$movement->reference_id : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="7">@include('testing.partials.empty-state', ['message' => 'لا توجد حركات مخزون.'])</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
