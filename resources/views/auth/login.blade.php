@extends('layouts.auth')
@section('title', 'Masuk')
@section('content')
<section class="login-card" aria-labelledby="login-title">
            <div class="login-badge"><x-login-icon name="lock" /><span>Portal Layanan Warga &amp; Pengurus</span></div>
            <h1 id="login-title">Masuk ke SIPURI</h1>
            <p class="login-intro">Akses layanan warga dan administrasi Puri Indah melalui akun resmi Anda.</p>
            @include('partials.messages')
            <form action="{{ route('login.store') }}" method="post" data-submit-form class="login-form">
                @csrf
                <div class="login-field">
                    <label for="username" class="form-label">Username</label>
                    <div class="position-relative">
                        <input type="text" class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username') }}" placeholder="Masukkan username Anda" autocomplete="username" maxlength="50" required @error('username') aria-invalid="true" aria-describedby="username-error" @enderror>
                        <span class="login-input-icon"><x-login-icon name="person" /></span>
                    </div>
                    @error('username')<div class="field-error" id="username-error">{{ $message }}</div>@enderror
                </div>
                <div class="login-field">
                    <div class="login-password-label d-flex justify-content-between align-items-baseline gap-2">
                        <label for="password" class="form-label">Kata Sandi</label>
                        <span id="password-hint">Peka huruf besar/kecil</span>
                    </div>
                    <div class="position-relative">
                        <input type="password" class="form-control @error('password') is-invalid @enderror" id="password" name="password" placeholder="Masukkan kata sandi Anda" autocomplete="current-password" required maxlength="128" aria-describedby="password-hint @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
                        <button type="button" class="login-eye" data-password-target="password" data-icon-toggle aria-controls="password" aria-pressed="false" aria-label="Tampilkan kata sandi"><x-login-icon name="eye" /><span class="login-eye-slash" aria-hidden="true"></span></button>
                    </div>
                    @error('password')<div class="field-error" id="password-error">{{ $message }}</div>@enderror
                </div>
                <a class="text-end" href="{{ route('password.help') }}">Lupa kata sandi?</a><x-submit-button class="w-100" busy="Memeriksa…">Masuk ke Portal</x-submit-button>
            </form>
            <div class="login-support text-center">
                <p>Belum punya akun warga? <a href="{{ route('register') }}">Daftar sebagai Warga</a></p>
                <div class="login-help d-flex align-items-center justify-content-center gap-2"><x-login-icon name="support" /><span>Kesulitan masuk? Hubungi admin atau pengurus RT yang ditunjuk.</span></div>
            </div>
        </section>
@endsection
