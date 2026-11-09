@extends('layouts.auth')
@section('title','Lupa Kata Sandi')
@section('content')
<section class="login-card stitch-card"><div class="text-center"><div class="stitch-status-icon"><x-login-icon name="lock"/></div><h1>Lupa kata sandi?</h1><p class="login-intro">Pemulihan akun dilakukan setelah identitas pemilik diperiksa oleh petugas berwenang.</p></div><x-notice title="Untuk Warga">Hubungi pengurus RT di wilayah Anda. Petugas memeriksa catatan RT atau melakukan konfirmasi langsung.</x-notice><x-notice title="Untuk Pengurus RT dan Bendahara">Hubungi Admin SIPURI.</x-notice><p class="form-text">Kontak pengurus belum tersedia di portal. Tidak perlu mengirim NIK atau dokumen identitas baru melalui halaman ini.</p><p class="form-text">Untuk akun Admin, pemulihan dilakukan melalui terminal lokal interaktif oleh pengelola yang berwenang.</p><a class="btn btn-primary w-100" href="{{ route('login') }}">Kembali ke Halaman Masuk</a></section>
@endsection
