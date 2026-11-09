@extends('layouts.auth')
@section('title','Buat Kata Sandi Baru')
@section('page-class','password-page')
@section('content')
<section class="login-card stitch-card">
    <div class="text-center"><div class="stitch-status-icon"><x-login-icon name="reset"/></div><h1>Buat Kata Sandi Baru</h1><p class="login-intro">Untuk keamanan akun, ganti kata sandi sementara dari petugas dengan kata sandi pribadi Anda.</p></div>
    @include('partials.messages')
    <form action="{{ route('password.initial.store') }}" method="post" data-submit-form class="stitch-form">@csrf
        <x-password-input name="current_password" label="Kata Sandi Awal" autocomplete="current-password" placeholder="Masukkan kata sandi awal"/>
        <x-password-input name="password" label="Kata Sandi Baru" placeholder="Masukkan kata sandi baru" :help="\App\Support\PasswordPolicy::HELP"/>
        <x-password-input name="password_confirmation" label="Konfirmasi Kata Sandi Baru" placeholder="Ulangi kata sandi baru"/>
        <x-submit-button class="w-100" icon="check">Simpan dan Lanjutkan</x-submit-button>
    </form>
</section>
@endsection
