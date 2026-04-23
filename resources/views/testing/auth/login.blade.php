@extends('testing.layouts.app')

@section('title', 'تسجيل الدخول - اختبار بن وبندق')
@section('page_title', 'تسجيل الدخول')
@section('page_subtitle', 'ادخل إلى لوحة الاختبار حسب دور الحساب')

@section('content')
<div class="auth-wrap">
    <div class="content-card">
        <div class="text-center mb-4">
            <div class="brand-mark mx-auto mb-3">ب</div>
            <h2 class="h4 mb-1">أهلا بك في بن وبندق</h2>
            <div class="muted">واجهة داخلية لاختبار تدفقات تطبيق الموبايل</div>
        </div>

        <form method="POST" action="{{ route('testing.login.submit') }}" class="row g-3">
            @csrf
            <div class="col-12">
                <label class="form-label">البريد الإلكتروني</label>
                <input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus>
            </div>
            <div class="col-12">
                <label class="form-label">كلمة المرور</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="col-12 d-grid">
                <button class="btn btn-primary btn-lg">دخول لوحة الاختبار</button>
            </div>
        </form>

        <div class="text-center mt-4 muted">
            لا تملك حساب اختبار؟
            <a class="text-white fw-bold" href="{{ route('testing.register') }}">إنشاء حساب جديد</a>
        </div>
    </div>
</div>
@endsection
