@extends('layouts.auth')
@section('title','Daftar sebagai Warga')
@section('card-width','stitch-wide')
@section('content')
<section class="login-card stitch-card">
    <div class="text-center"><span class="login-badge"><x-login-icon name="shield"/> Portal Layanan Warga</span><h1>Daftar sebagai Warga</h1><p class="login-intro">Buat akun untuk mengakses layanan warga Puri Indah.</p></div>
    @include('partials.messages')
    <x-notice title="Pemberitahuan Pendaftaran">Pendaftaran akan diperiksa oleh pengurus RT. Layanan warga dapat diakses setelah pendaftaran disetujui.</x-notice>
    <form action="{{ route('register.store') }}" method="post" data-submit-form class="stitch-form">@csrf
        @include('partials.resident-fields',['resident'=>null])
        <div class="stitch-field"><label class="form-label" for="username">Username <span class="required">*</span></label><div class="form-text mb-2" id="username-help">Gunakan huruf kecil, angka, titik, atau garis bawah tanpa spasi.</div><div class="position-relative"><input class="form-control @error('username') is-invalid @enderror" id="username" name="username" value="{{ old('username') }}" placeholder="Buat username akun" required minlength="3" maxlength="50" autocomplete="username" autocapitalize="none" spellcheck="false" aria-describedby="username-help @error('username') username-error @enderror" @error('username') aria-invalid="true" @enderror><span class="login-input-icon"><x-login-icon name="person"/></span></div>@error('username')<div class="field-error" id="username-error">{{ $message }}</div>@enderror</div>
        <x-password-input name="password" label="Kata Sandi" :help="\App\Support\PasswordPolicy::HELP"/>
        <x-password-input name="password_confirmation" label="Konfirmasi Kata Sandi" placeholder="Ulangi kata sandi"/>
        <x-submit-button class="w-100" icon="person" busy="Mengirim…">Daftar</x-submit-button>
    </form>
    <p class="text-center mt-4 mb-4">Sudah punya akun? <a href="{{ route('login') }}">Masuk</a></p>
    <x-notice icon="support" class="stitch-help">Butuh bantuan pendaftaran? Hubungi pengurus RT lingkungan Anda.</x-notice>
</section>
@endsection
