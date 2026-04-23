@extends('testing.layouts.app')

@section('title', 'إضافة منتج - بن وبندق')
@section('page_title', 'إضافة منتج')
@section('page_subtitle', 'إنشاء صنف جديد في المخزون')

@section('content')
<div class="content-card">
    <form method="POST" action="{{ route('testing.inventory.products.store') }}" class="row g-3">
        @csrf
        <div class="col-md-6">
            <label class="form-label">اسم المنتج</label>
            <input name="name" value="{{ old('name') }}" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">SKU اختياري</label>
            <input name="sku" value="{{ old('sku') }}" class="form-control">
        </div>
        <div class="col-md-4">
            <label class="form-label">التصنيف</label>
            <select name="category" class="form-select" required>
                @foreach($categories as $category)
                    <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">الوحدة</label>
            <select name="unit" class="form-select" required>
                @foreach($units as $unit)
                    <option value="{{ $unit }}" @selected(old('unit') === $unit)>{{ $unit }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">الفرع</label>
            <select name="branch_id" class="form-select" required>
                @foreach($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', auth()->user()?->branch_id) === (string) $branch->id)>{{ $branch->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">الكمية الحالية</label>
            <input type="number" step="0.01" min="0" name="quantity" value="{{ old('quantity', 0) }}" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">الحد الأدنى</label>
            <input type="number" step="0.01" min="0" name="minimum_quantity" value="{{ old('minimum_quantity', 0) }}" class="form-control" required>
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary">حفظ المنتج</button>
            <a href="{{ route('testing.inventory.index') }}" class="btn btn-outline-light">رجوع</a>
        </div>
    </form>
</div>
@endsection
