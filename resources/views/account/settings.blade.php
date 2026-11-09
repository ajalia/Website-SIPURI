@extends($layout)
@section('title','Pengaturan Akun')
@section('page-title','Pengaturan Akun')
@section('card-width','stitch-wide')
@section('content')
@if($layout==='layouts.auth')@include('partials.messages')@endif
<section class="account-card surface"><h1>Pengaturan Akun</h1><p class="text-secondary">Kelola kata sandi akun SIPURI Anda.</p><dl class="data-list mb-4"><div><dt>Nama</dt><dd>{{ $user->name }}</dd></div><div><dt>Username</dt><dd>{{ $user->username }}</dd></div><div><dt>Peran</dt><dd>{{ $user->role->label() }}</dd></div></dl>
@if($user->role===\App\Enums\Role::Resident)<div class="mb-4"><label class="form-label" for="current-address">Alamat Saat Ini</label><input class="form-control" id="current-address" readonly value="{{ \App\Support\HouseAddress::display($user->house?->block ?? $user->latestResidency?->block ?? $user->block,$user->house?->house_number ?? $user->latestResidency?->house_number ?? $user->house_number) }}"><p class="form-text">Untuk memperbaiki alamat, hubungi pengurus RT Anda.</p>@if($user->registration_status==='rejected')<a href="{{ route('registration.edit') }}">Perbaiki pendaftaran sesuai catatan RT</a>@endif</div>@endif
<h2 class="mb-3">Ganti Kata Sandi</h2><form action="{{ route('account.password') }}" method="post" data-submit-form class="account-password-form">@csrf @method('PUT')
<x-password-input name="current_password" label="Kata Sandi Saat Ini" autocomplete="current-password" placeholder="Masukkan kata sandi saat ini"/>
<x-password-input name="password" label="Kata Sandi Baru" :help="\App\Support\PasswordPolicy::HELP" placeholder="Masukkan kata sandi baru"/>
<x-password-input name="password_confirmation" label="Konfirmasi Kata Sandi Baru" placeholder="Ulangi kata sandi baru"/>
<p class="form-text">Kata sandi baru harus berbeda dari sebelumnya. Perangkat ini tetap masuk; sesi perangkat lain akan dicabut.</p><button class="btn btn-primary w-100" type="submit" data-busy-label="Menyimpan…">Simpan Kata Sandi Baru</button></form></section>
@endsection
