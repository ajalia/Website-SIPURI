@extends('layouts.portal')
@section('title','Pratinjau Laporan Warga')
@section('page-title','Penerbitan Laporan')
@include('cash.assets')
@section('content')
<div class="cash-module"><h1>Pratinjau Laporan Warga</h1><p class="text-secondary">{{ $label }} · Inilah rincian yang akan dibaca Warga. Tidak ada pemeriksaan atau pengesahan otomatis.</p><a class="btn btn-light mb-4" href="{{ route('admin.reports',$filters) }}">Kembali dan Tinjau Keterangan</a>
@include('cash.summary',['summary'=>$statement['summary']])
<section class="cash-card cash-table-card mb-4">@include('cash.public-table',['publicRows'=>$preview['rows'],'summary'=>$statement['summary']])</section>
<button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#cash-publish">Terbitkan Laporan</button>
<x-rt-modal id="cash-publish" title="Terbitkan Laporan untuk Warga?"><form method="post" action="{{ route('admin.reports.publish',$filters) }}" data-submit-form>@csrf<input type="hidden" name="token" value="{{ $token }}"><div class="modal-body"><p>Laporan {{ $label }} akan disimpan sebagai versi terbit baru. Versi sebelumnya tetap menjadi riwayat internal.</p><div class="form-check"><input class="form-check-input" type="checkbox" id="cash-confirmed" name="confirmed" value="1" required><label class="form-check-label" for="cash-confirmed">Saya menyatakan laporan telah diperiksa bersama Bendahara dan keterangan publik aman untuk Warga.</label></div></div><div class="modal-footer"><button class="btn btn-light" type="button" data-bs-dismiss="modal">Batal</button><button class="btn btn-primary" type="submit" data-busy-label="Menerbitkan…">Ya, Terbitkan</button></div></form></x-rt-modal>
</div>
@endsection
