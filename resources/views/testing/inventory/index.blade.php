@extends('testing.layouts.app')

@section('title', 'إدارة المخزون - بن وبندق')
@section('page_title', 'إدارة المخزون')
@section('page_subtitle', 'عرض المنتجات والكميات والتنبيه عند انخفاض المخزون')

@section('content')
<div class="content-card mb-4">
    <form method="GET" action="{{ route('testing.inventory.index') }}" class="row g-3 align-items-end">
        <div class="col-lg-5">
            <label class="form-label">بحث</label>
            <input name="search" value="{{ request('search') }}" class="form-control" placeholder="اسم المنتج">
        </div>
        <div class="col-lg-4">
            <label class="form-label">التصنيف</label>
            <select name="category" class="form-select">
                <option value="">كل التصنيفات</option>
                @foreach($categories as $category)
                    <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-lg-3 d-flex gap-2">
            <button class="btn btn-primary flex-fill">تصفية</button>
            <a class="btn btn-outline-light" href="{{ route('testing.inventory.products.create') }}">إضافة منتج</a>
        </div>
    </form>
</div>

<div class="row g-3">
    @forelse($products as $product)
        <div class="col-lg-4 col-md-6">
            <div class="list-card h-100">
                <div class="d-flex justify-content-between gap-3 mb-3">
                    <div>
                        <h3 class="h5 mb-1">{{ $product->name }}</h3>
                        <div class="muted small">{{ $product->category }} - {{ $product->branch?->name }}</div>
                    </div>
                    @if((float) $product->quantity <= (float) $product->minimum_quantity)
                        <span class="badge-soft badge-cancelled">منخفض</span>
                    @else
                        <span class="badge-soft badge-completed">جيد</span>
                    @endif
                </div>
                <div class="display-6 fw-bold">{{ $product->quantity }}</div>
                <div class="muted">الوحدة: {{ $product->unit }} - الحد الأدنى: {{ $product->minimum_quantity }}</div>

                <form method="POST" action="{{ route('testing.inventory.products.quantity.update', $product) }}" class="mt-3">
                    @csrf
                    <label class="form-label small">تعديل يدوي للكمية الحالية</label>
                    <div class="d-flex gap-2 align-items-start">
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="quantity"
                            value="{{ $product->quantity }}"
                            class="form-control form-control-sm"
                            required
                        >
                        <button class="btn btn-outline-light btn-sm">حفظ</button>
                    </div>
                    <input type="hidden" name="notes" value="Manual quantity update from inventory page for {{ $product->name }}">
                </form>

                <form method="POST" action="{{ route('testing.inventory.products.minimum-quantity.update', $product) }}" class="mt-3">
                    @csrf
                    <label class="form-label small">تعديل الحد الأدنى</label>
                    <div class="d-flex gap-2 align-items-start">
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="minimum_quantity"
                            value="{{ $product->minimum_quantity }}"
                            class="form-control form-control-sm"
                            required
                        >
                        <button class="btn btn-outline-light btn-sm">حفظ</button>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="col-12">@include('testing.partials.empty-state', ['message' => 'لا توجد منتجات مطابقة.'])</div>
    @endforelse
</div>

<div class="mt-4">
    {{ $products->links() }}
</div>
@endsection
