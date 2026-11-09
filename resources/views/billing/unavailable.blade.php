@extends('layouts.finance')
@section('title','Laporan Keuangan')
@section('page-title',auth()->user()->role===\App\Enums\Role::Treasurer?'Kas & Laporan Keuangan':'Laporan Keuangan')
@section('content')
<section class="rt-card rt-empty"><div class="rt-empty-stack"><x-login-icon name="wallet"/><h1>Belum tersedia</h1><p>Modul kas, laporan keuangan, dan penerbitan laporan Admin dikerjakan pada tahap berikutnya. Belum ada laporan keuangan yang diterbitkan melalui modul ini.</p><a class="btn btn-primary" href="{{ route(auth()->user()->role===\App\Enums\Role::Treasurer?'treasurer.dashboard':'resident.dashboard') }}">Kembali ke Beranda</a></div></section>
@endsection
