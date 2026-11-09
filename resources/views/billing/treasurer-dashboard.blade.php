@extends('layouts.finance')
@section('title','Beranda Bendahara')
@section('content')
<h1>Selamat datang, Bendahara</h1><p class="rt-description">Periksa pengajuan pembayaran dan kelola iuran lingkungan RW 07.</p>
@include('billing.filters')
<p class="mb-4"><strong>{{ $summary['count'] }} tagihan · Total {{ \App\Support\Dues::money($summary['amount']) }}</strong></p>
<x-dues-summary :summary="$summary"/>
<div class="rt-section-heading"><div><h2>Pengajuan Perlu Diperiksa</h2><p>{{ $summary['pending']['count'] }} pengajuan menunggu pemeriksaan untuk periode terpilih.</p></div><a href="{{ route('treasurer.payments.index',['month'=>$filters['month'],'year'=>$filters['year']]) }}">Lihat Semua Pengajuan →</a></div>
<div class="rt-queue">@forelse($pending as $payment)<article class="rt-card rt-queue-item"><x-rt-avatar :name="$payment->invoice->resident_name"/><div class="rt-queue-detail"><h3>{{ $payment->invoice->resident_name }}</h3><p><x-house-address :block="$payment->invoice->block" :number="$payment->invoice->house_number"/> · {{ $payment->invoice->period->label }} · {{ \App\Support\Dues::money($payment->amount) }}</p></div><a class="btn btn-light" href="{{ route('treasurer.payments.review',$payment) }}">Periksa Pembayaran →</a></article>@empty<div class="rt-card rt-empty"><h2>Tidak Ada Pengajuan Menunggu</h2><p>Belum ada bukti yang perlu diperiksa untuk periode ini.</p></div>@endforelse</div>
<div class="row g-3 mt-4"><div class="col-md-6"><section class="rt-card finance-card h-100"><h2>Tagihan Iuran</h2><p>Lihat dan terbitkan iuran bulanan.</p><a class="btn btn-primary" href="{{ route('treasurer.invoices.index') }}">Buka Tagihan Iuran →</a></section></div><div class="col-md-6"><section class="rt-card finance-card h-100"><h2>Kas &amp; Laporan Keuangan</h2><p>Catat transaksi lain, pantau saldo kas, dan cetak laporan internal.</p><a class="btn btn-light" href="{{ route('treasurer.reports') }}">Kas &amp; Laporan Keuangan</a></section></div></div>
@endsection
