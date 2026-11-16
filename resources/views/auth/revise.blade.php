@extends('layouts.auth')
@section('title','Perbaiki Pendaftaran')
@section('card-width','frontend-wide')
@section('page-class','revision-page')
@section('content')
<nav aria-label="Jejak halaman" class="frontend-breadcrumb"><a href="{{ route('home') }}">SIPURI</a><span aria-hidden="true">›</span><a href="{{ route('registration.status') }}">Pendaftaran Warga</a><span aria-hidden="true">›</span><span aria-current="page">Perbaiki Data Pendaftaran</span></nav>
<section class="login-card frontend-card frontend-revision-card">
    <div class="d-flex align-items-start gap-3"><span class="frontend-edit-icon"><x-login-icon name="edit"/></span><div><h1>Perbaiki Data Pendaftaran</h1><p class="login-intro">Perbarui data yang diperlukan sesuai catatan pengurus RT lalu ajukan kembali.</p></div></div>
    @include('partials.messages')
    <x-notice tone="error" title="Panduan Perbaikan Pengurus RT"><p class="pre-wrap mb-0">{{ auth()->user()->rejection_reason }}</p></x-notice>
    <form action="{{ route('registration.update') }}" method="post" data-submit-form class="frontend-form">@csrf @method('PUT')
        @include('partials.resident-fields',['resident'=>auth()->user()])
        <div class="frontend-field"><label class="form-label" for="username">Username Akun Warga <x-login-icon name="lock"/></label><input class="form-control" id="username" value="{{ auth()->user()->username }}" readonly aria-describedby="username-help"><div class="form-text" id="username-help">Username bersifat permanen dan tidak dapat diubah selama proses revisi.</div></div>
        <div class="frontend-form-actions"><a class="frontend-cancel" href="{{ route('registration.status') }}">Batal</a><x-submit-button icon="send" busy="Mengirim…">Simpan dan Ajukan Ulang</x-submit-button></div>
    </form>
</section>
<div class="frontend-context"><x-login-icon name="support"/> Butuh bantuan pengisian? Hubungi pengurus RT Anda.</div>
@endsection
