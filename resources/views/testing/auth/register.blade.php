@extends('testing.layouts.app')

@section('title', 'إنشاء حساب اختبار - بن وبندق')
@section('page_title', 'إنشاء حساب')
@section('page_subtitle', 'الموظف يرسل طلب انضمام، والمدير يسمح له بالدخول بعد الموافقة')

@section('content')
<div class="auth-wrap">
    <div class="content-card">
        <form method="POST" action="{{ route('testing.register.submit') }}" class="row g-3">
            @csrf
            <div class="col-12">
                <label class="form-label">نوع الحساب</label>
                <select name="account_type" class="form-select" required>
                    <option value="employee" @selected(old('account_type') === 'employee')>موظف - طلب انضمام</option>
                    <option value="manager" @selected(old('account_type') === 'manager')>مدير - للاختبار الداخلي فقط</option>
                </select>
                <small class="muted">حساب المدير هنا مسموح فقط لطبقة الاختبار وليس للـ API العام.</small>
            </div>
            <div class="col-md-6">
                <label class="form-label">الاسم الكامل</label>
                <input type="text" name="name" value="{{ old('name') }}" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">رقم الهاتف</label>
                <input type="text" name="phone" value="{{ old('phone') }}" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">البريد الإلكتروني</label>
                <input type="email" name="email" value="{{ old('email') }}" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">الفرع</label>
                <select name="branch_id" class="form-select" required>
                    <option value="">اختر الفرع</option>
                    @foreach($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>
                            {{ $branch->name }}{{ $branch->location ? ' - '.$branch->location : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">كلمة المرور</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">تأكيد كلمة المرور</label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>
            <div class="col-12">
                <label class="d-flex gap-2 align-items-center">
                    <input type="checkbox" name="terms" value="1" required>
                    <span>أوافق على استخدام هذه البيانات لغرض الاختبار الداخلي فقط.</span>
                </label>
            </div>
            <div class="col-12 d-grid">
                <button class="btn btn-primary btn-lg">إنشاء الحساب</button>
            </div>
        </form>

        <div class="text-center mt-4 muted">
            لديك حساب؟
            <a class="text-white fw-bold" href="{{ route('testing.login') }}">تسجيل الدخول</a>
        </div>
    </div>
</div>
@endsection
