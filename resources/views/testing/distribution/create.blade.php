@extends('testing.layouts.app')

@section('title', 'إنشاء شحنة - بن وبندق')
@section('page_title', 'إنشاء شحنة توزيع')
@section('page_subtitle', 'اختر المنتج وحدد الموزع والوجهة من نفس الشاشة')

@section('content')
<div class="content-card">
    <form method="POST" action="{{ route('testing.distribution.store') }}" class="row g-3">
        @csrf
        <div class="col-md-6">
            <label class="form-label">المنتج والكمية المتاحة</label>
            <select name="product_id" class="form-select" required>
                <option value="">اختر المنتج</option>
                @foreach($products as $product)
                    <option value="{{ $product->id }}" @selected((string) old('product_id') === (string) $product->id)>
                        {{ $product->name }} - المتاح {{ $product->quantity }} {{ $product->unit }} - {{ $product->branch?->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">كمية الشحنة</label>
            <input type="number" step="0.01" min="0.01" name="quantity" value="{{ old('quantity') }}" class="form-control" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">وحدة المنتج</label>
            <input class="form-control" value="حسب المنتج المختار" disabled>
        </div>
        <div class="col-md-6">
            <label class="form-label">الوجهة</label>
            <input name="destination" value="{{ old('destination') }}" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">اسم المستلم</label>
            <input name="recipient_name" value="{{ old('recipient_name') }}" class="form-control" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">موظف التوزيع</label>
            <select name="assigned_to" class="form-select" required>
                <option value="">اختر موظف التوزيع</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee->id }}" @selected((string) old('assigned_to') === (string) $employee->id)>{{ $employee->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-12">
            <label class="form-label">ملاحظات</label>
            <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
        </div>
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary">إنشاء الشحنة وتعيين الموزع</button>
            <a href="{{ route('testing.distribution.index') }}" class="btn btn-outline-light">رجوع</a>
        </div>
    </form>
</div>
@endsection
