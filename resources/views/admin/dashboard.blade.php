@extends('layouts.portal')
@section('title','Beranda Admin')
@section('page-title','Beranda Admin')
@include('admin.staff-assets')
@section('content')
<div class="staff-module"><h1>Selamat datang, Admin</h1><p class="staff-description">Kelola akun pengurus, konten publik, pemulihan akun Warga, dan penerbitan laporan keuangan SIPURI.</p>
<div class="row g-4 mt-1">@foreach([
 ['people','Kelola Akun','Kelola akun Pengurus RT dan Bendahara.','admin.accounts','Buka Kelola Akun'],
 ['building','Konten Publik','Perbarui banner, profil kawasan, fasilitas, pengurus lingkungan, dan kuliner.','admin.content.index','Buka Konten Publik'],
 ['reset','Pemulihan Akun Warga','Pulihkan akses akun Warga setelah identitas pemilik dikonfirmasi.','admin.resident-recovery','Buka Pemulihan Warga'],
 ['calendar','Penerbitan Laporan','Tinjau laporan bersama Bendahara sebelum diterbitkan untuk Warga.','admin.reports','Buka Penerbitan Laporan']
] as [$icon,$title,$description,$route,$button])<div class="col-md-6 col-xl-3"><section class="staff-card staff-action-card"><div><span class="staff-action-icon"><x-login-icon :name="$icon"/></span><h2>{{ $title }}</h2><p>{{ $description }}</p></div><a class="btn btn-primary" href="{{ route($route) }}">{{ $button }} <span aria-hidden="true">→</span></a></section></div>@endforeach</div></div>
@endsection
