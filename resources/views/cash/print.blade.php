@extends('layouts.base')
@section('title',$documentTitle)
@include('cash.assets')
@section('shell')
<main id="main" class="cash-module cash-print"><div class="cash-print-controls"><button class="btn btn-primary" type="button" data-cash-print>Cetak / Simpan PDF</button><p>Pada dialog cetak browser, pilih Simpan sebagai PDF. Seluruh rincian periode disertakan.</p></div><header class="cash-document-header"><h1>SIPURI · Puri Indah Jatinangor</h1><p>RW 07</p><h2>{{ $documentTitle }}</h2><p>Periode {{ $label }}</p>@if($internal)<p>Dokumen internal Bendahara — bukan laporan publik yang telah diterbitkan.</p>@else<p>Versi {{ $report->version }} · Terbit {{ $report->published_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</p>@endif</header>
@if($internal&&!$statement['configured'])<p>Saldo awal belum diatur. Angka saldo belum merupakan posisi pembukuan yang lengkap.</p>@endif
@if($internal)@foreach($statement['problems'] as $problem)<p>{{ $problem }}</p>@endforeach @endif
@include('cash.summary',['available'=>!$internal||$statement['balanceAvailable']])
@include('cash.public-table',['publicRows'=>$rows,'available'=>!$internal||$statement['balanceAvailable']])
<p class="cash-print-footer">Dicetak {{ now('Asia/Jakarta')->format('d/m/Y H:i') }} WIB · SIPURI RW 07</p></main>
@endsection
