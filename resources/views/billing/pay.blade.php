@extends('layouts.finance')
@section('title','Bayar Tagihan')
@section('page-title','Bayar Tagihan')
@section('content')
<a class="rt-back" href="{{ route('resident.invoices.index') }}">← Kembali ke Tagihan Saya</a>
<section class="rt-card finance-card finance-pay-card"><div class="d-flex flex-wrap justify-content-between gap-3"><div><span class="finance-eyebrow">Ringkasan Pembayaran</span><h1>Iuran Bulanan — {{ $invoice->period->label }}</h1></div><x-payment-status :status="$invoice->status"/></div><div class="finance-amount"><small>Nominal Tagihan</small><strong>{{ \App\Support\Dues::money($invoice->amount) }}</strong><p>Termasuk keamanan, kebersihan, dan layanan lingkungan lainnya.</p></div>@include('billing.upload-form')<a class="btn btn-light w-100 mt-3" href="{{ route('resident.invoices.index') }}">← Kembali ke Tagihan Saya</a></section>
@endsection
