@extends('layouts.finance')
@section('title','Laporan Keuangan')
@section('page-title','Laporan Keuangan')
@include('cash.assets')
@section('content')
<div class="cash-module"><h1>Laporan Keuangan Lingkungan</h1><p class="text-secondary mb-4">Lihat ringkasan pemasukan dan pengeluaran lingkungan berdasarkan periode.</p>@include('cash.filters')
@if(!$report)<section class="cash-card cash-empty"><x-login-icon name="calendar"/><h2>Laporan periode ini belum tersedia</h2><p>Laporan ditampilkan setelah Admin menerbitkannya untuk Warga.</p></section>@else
<section class="cash-card mb-4"><div class="cash-section-heading"><div><h2>Ringkasan Keuangan — {{ $label }}</h2><p>Diterbitkan Admin pada {{ $report->published_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB · Versi {{ $report->version }}</p></div><span class="cash-publication">Laporan Terbit</span></div>@include('cash.summary',['summary'=>$report->summary])</section>
<section class="cash-card cash-table-card"><div class="cash-section-heading"><div><h2>Pemasukan dan Pengeluaran</h2><p>Rincian transaksi yang telah dipublikasikan untuk {{ $label }}.</p><p class="form-text">Pilih Simpan sebagai PDF pada dialog cetak.</p></div><a class="btn btn-light" href="{{ route('resident.reports.print',$filters) }}" target="_blank" rel="noopener">Cetak / Simpan PDF</a></div>@include('cash.public-table',['publicRows'=>$report->public_rows,'summary'=>$report->summary])<p class="cash-table-caption">Menampilkan seluruh {{ count($report->public_rows) }} rincian terbit periode {{ $label }}.</p></section>
@endif</div>
@endsection
