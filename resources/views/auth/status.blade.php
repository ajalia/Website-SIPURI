@extends('layouts.auth')
@section('title','Status Pendaftaran')
@section('card-width','stitch-wide')
@section('page-class','status-page')
@section('content')
@php($resident=auth()->user())
@php($rejected=$resident->registration_status==='rejected')
<section class="login-card stitch-card stitch-status-card {{ $rejected?'stitch-rejected':'stitch-pending' }}">
    @include('partials.messages')
    <div class="text-center">
        <div class="stitch-status-icon {{ $rejected?'stitch-icon-error':'stitch-icon-pending' }}"><x-login-icon :name="$rejected?'edit':'clock'"/></div>
        <span class="stitch-status-badge {{ $rejected?'stitch-badge-error':'' }}">{{ $rejected?'Ditolak — Perlu Perbaikan':'Menunggu Verifikasi RT' }}</span>
        <h1 class="mt-3">{{ $rejected?'Pendaftaran Anda Perlu Diperbaiki':'Pendaftaran Anda Sedang Ditinjau' }}</h1>
        <p class="login-intro">{{ $rejected?'Silakan pelajari catatan verifikasi dari pengurus RT, perbaiki data pendaftaran Anda, lalu ajukan kembali untuk pemeriksaan ulang.':'Data pendaftaran Anda telah tersimpan dan akan diperiksa oleh pengurus RT. Layanan warga dapat diakses setelah pendaftaran disetujui.' }}</p>
    </div>
    @if($rejected)<x-notice tone="error" icon="info" title="Catatan dari Pengurus RT"><p class="pre-wrap mb-0">{{ $resident->rejection_reason }}</p></x-notice>@endif
    <section class="stitch-summary" aria-labelledby="summary-title">
        <h2 id="summary-title"><x-login-icon name="person"/> {{ $rejected?'Rincian Data Pendaftaran Terkirim':'Ringkasan Pendaftaran' }}</h2>
        <dl class="row g-3 mb-0">
            <div class="col-sm-6"><dt>Nama Lengkap</dt><dd>{{ $resident->name }}</dd></div>
            <div class="col-sm-6"><dt>Rukun Tetangga (RT)</dt><dd>{{ $resident->rt?->label() ?? 'Belum ditentukan' }}</dd></div>
            <div class="col-sm-6"><dt>Alamat / Blok &amp; No. Rumah</dt><dd><x-house-address :block="$resident->block" :number="$resident->house_number"/></dd></div>
            <div class="col-sm-6"><dt>Username</dt><dd>{{ $resident->username }}</dd></div>
            <div class="col-12"><dt>Waktu Pendaftaran</dt><dd>{{ $resident->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</dd></div>
        </dl>
    </section>
    <x-notice tone="sage">Pengurus RT akan mencocokkan data pendaftaran dengan data rumah tangga. Jika membutuhkan bantuan, hubungi pengurus RT Anda.</x-notice>
    <div class="text-center mt-4">
        @if($rejected)<a class="btn btn-primary stitch-action" href="{{ route('registration.edit') }}"><x-login-icon name="edit"/> Perbaiki Pendaftaran</a>
        @else<a class="btn btn-primary stitch-action" href="{{ route('dashboard') }}"><x-login-icon name="refresh"/> Periksa Status</a>@endif
        <a class="stitch-back-link" href="{{ route('home') }}">Kembali ke Beranda</a>
    </div>
</section>
<aside class="stitch-context text-center"><p><x-login-icon name="home"/> Lingkungan Puri Indah Jatinangor • Sistem Administrasi RT Terpadu</p><p class="mb-0">Verifikasi dilakukan langsung oleh kepengurusan RT setempat demi menjaga keamanan dan akurasi data warga terdaftar.</p></aside>
@endsection
