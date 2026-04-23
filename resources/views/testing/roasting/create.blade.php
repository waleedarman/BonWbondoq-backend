@extends('testing.layouts.app')

@section('title', 'إنشاء عملية تحميص - بن وبندق')
@section('page_title', 'إنشاء عملية تحميص')
@section('page_subtitle', 'الفرع يتم أخذه من المنتج المختار لتسهيل الاختبار')

@section('content')
<div class="content-card">
    <form method="POST" action="{{ route('testing.roasting.store') }}" class="row g-3">
        @csrf
        <div class="col-md-6">
            <label class="form-label">المنتج</label>
            <select name="product_id" class="form-select" required>
                <option value="">اختر المنتج</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>
                        {{ $product->name }} - {{ $product->quantity }} {{ $product->unit }} - {{ $product->branch?->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">الكمية</label>
            <input type="number" step="0.01" min="0.01" name="quantity" value="{{ old('quantity') }}" class="form-control" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">الأولوية</label>
            <select name="priority" class="form-select" required>
                @foreach($priorities as $priority)
                    <option value="{{ $priority }}" @selected(old('priority') === $priority)>{{ $priority }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">موظف التحميص</label>
            <select name="assigned_to" class="form-select">
                <option value="">بدون تعيين الآن</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((string) old('assigned_to') === (string) $employee->id)>{{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">ملاحظات</label>
            <input name="notes" value="{{ old('notes') }}" class="form-control" placeholder="اختياري">
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary">حفظ العملية</button>
            <a href="{{ route('testing.roasting.index') }}" class="btn btn-outline-light">رجوع</a>
        </div>
    </form>
</div>
@endsection
