@extends('layouts.finance')
@section('title','Tagihan Saya')
@section('page-title','Tagihan Saya')
@section('content')
<section class="rt-card finance-card mb-4"><span class="finance-eyebrow">PORTAL WARGA RESMI</span><h1>Tagihan Iuran Warga</h1><p>Periksa iuran bulanan dan status pembayaran rumah tangga Anda.</p><div class="finance-note"><strong>Informasi Layanan Terpadu</strong><p class="mb-0">Satu tagihan per rumah per periode, termasuk keamanan, kebersihan, dan layanan lingkungan lainnya.</p></div></section>
<x-dues-summary :summary="$summary"/>
@include('billing.filters',['allMonths'=>true,'statusOptions'=>['all'=>'Semua Status','unpaid'=>'Belum Lunas','paid'=>'Lunas']])
@include('billing.resident-table')
<div class="finance-note mt-4">Bukti pembayaran diperiksa Bendahara. Unggahan bukti tidak otomatis melunasi tagihan.</div>
@endsection
