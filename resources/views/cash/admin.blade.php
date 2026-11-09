@extends('layouts.portal')
@section('title','Penerbitan Laporan')
@section('page-title','Penerbitan Laporan')
@include('cash.assets')
@section('content')
<div class="cash-module"><h1>Penerbitan Laporan Keuangan</h1><p class="text-secondary mb-4">Tinjau laporan bersama Bendahara sebelum diterbitkan untuk Warga.</p>@include('cash.filters')
@if(!$matches)<section class="cash-card cash-empty"><h2>Tidak Ada Laporan Sesuai Filter Status</h2><p>Pilih Semua Status untuk meninjau periode {{ $label }}.</p></section>@else
<div class="cash-section-heading"><h2>Periode: {{ $label }}</h2><span class="cash-publication">{{ !$latest?'Belum Diterbitkan':($stale?'Perlu Diperbarui':'Sudah Diterbitkan') }}</span></div>
@if(!$statement['configured'])<div class="alert alert-warning">Saldo awal belum diatur. Minta Bendahara menyelesaikan pengaturan pembukuan.</div>@endif
@if(!$statement['period'])<div class="cash-notice">Belum ada transaksi pada periode ini. Penerbitan dinonaktifkan.</div>@endif
@foreach($statement['problems'] as $problem)<div class="alert alert-warning">{{ $problem }}</div>@endforeach
@if($stale)<div class="alert alert-warning">Kas berubah setelah publikasi. Warga tetap membaca snapshot terakhir sampai versi baru diterbitkan.</div>@endif
@include('cash.summary',['summary'=>$statement['summary'],'available'=>$statement['balanceAvailable']])
<section class="cash-card"><h2>Pratinjau Laporan Warga</h2><p>Nominal berasal dari kas. Iuran dikelompokkan per tanggal tanpa identitas pembayar. Tinjau setiap keterangan publik untuk transaksi lainnya; jangan sertakan nama pribadi, username, alamat rumah, rekening, atau informasi internal.</p>
<form method="post" action="{{ route('admin.reports.preview',$filters) }}" data-submit-form>@csrf
<table class="table cash-table"><thead><tr><th>Tanggal</th><th>Keterangan untuk Laporan Warga</th><th>Pemasukan</th><th>Pengeluaran</th></tr></thead><tbody>
@php($duesGroups=collect($statement['period'])->where('kind','dues')->groupBy('date'))
@foreach($duesGroups as $date=>$items)<tr><td data-label="Tanggal">{{ $date }}</td><td data-label="Keterangan">Penerimaan iuran warga</td><td class="cash-income" data-label="Pemasukan">{{ \App\Support\Dues::money($items->sum('income')) }}</td><td data-label="Pengeluaran">—</td></tr>@endforeach
@foreach(collect($statement['period'])->where('kind','manual') as $row)<tr><td data-label="Tanggal">{{ $row['date'] }}</td><td data-label="Keterangan"><div class="w-100"><p class="form-text">Catatan internal: {{ $row['description'] }}</p><label class="form-label" for="public-{{ $row['id'] }}">Keterangan untuk laporan warga</label><textarea class="form-control" id="public-{{ $row['id'] }}" name="descriptions[{{ $row['id'] }}]" maxlength="500" rows="3" required>{{ old('descriptions.'.$row['id'],mb_substr($row['description'],0,500)) }}</textarea></div></td><td class="cash-income" data-label="Pemasukan">{{ $row['income']?\App\Support\Dues::money($row['income']):'—' }}</td><td class="cash-expense" data-label="Pengeluaran">{{ $row['expense']?\App\Support\Dues::money($row['expense']):'—' }}</td></tr>@endforeach</tbody></table>
<div class="form-check my-3"><input class="form-check-input" type="checkbox" name="privacy_reviewed" id="privacy-reviewed" value="1" required><label class="form-check-label" for="privacy-reviewed">Saya telah meninjau seluruh keterangan publik dan memastikan tidak ada informasi pribadi atau internal yang perlu dirahasiakan.</label></div><button class="btn btn-primary" type="submit" @disabled(!$statement['ready']) data-busy-label="Menyiapkan…">Tinjau Sebelum Terbitkan</button></form></section>
@endif
@if($history->isNotEmpty())<section class="cash-card mt-4"><h2>Riwayat Versi Internal</h2><ul class="cash-history">@foreach($history as $item)<li><a href="{{ route('admin.reports',$filters+['version'=>$item->version]) }}">Versi {{ $item->version }}</a> · {{ $item->published_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB · {{ $item->publisher?->name }}</li>@endforeach</ul><p class="form-text">Warga hanya melihat versi terbit terbaru. Riwayat tidak dihapus.</p></section>@endif
@if($historic)<section class="cash-card cash-table-card mt-4"><div class="cash-section-heading"><h2>Snapshot Versi {{ $historic->version }} (Riwayat Internal)</h2></div>@include('cash.summary',['summary'=>$historic->summary])@include('cash.public-table',['publicRows'=>$historic->public_rows,'summary'=>$historic->summary])</section>@endif
</div>
@endsection
