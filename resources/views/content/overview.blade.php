@extends('layouts.portal')
@section('title','Kelola Konten Publik')
@section('page-title','Konten Publik')
@include('content.assets')
@section('content')
<div class="staff-module content-module"><h1>Kelola Konten Publik</h1><p class="staff-description">Perbarui informasi yang ditampilkan pada beranda publik SIPURI.</p><section class="staff-card content-table-card"><table class="table content-table mb-0"><thead><tr><th>Bagian</th><th>Keterangan</th><th class="content-actions-column">Aksi</th></tr></thead><tbody>@foreach(\App\Models\PublicContent::SECTIONS as $key=>[$name,$short,$description])<tr><td data-label="Bagian"><strong>{{ $name }}</strong></td><td data-label="Keterangan">{{ $description }}</td><td data-label="Aksi"><a class="btn btn-light" href="{{ route('admin.content.list',['section'=>$key]) }}"><x-login-icon :name="\App\Models\PublicContent::singleton($key)?'edit':'building'"/> {{ \App\Models\PublicContent::singleton($key)?'Edit':'Kelola' }}</a></td></tr>@endforeach</tbody></table></section><a class="btn btn-light mt-4" href="{{ route('home') }}" target="_blank" rel="noopener">Lihat Beranda Publik</a>
@if(($mode??'')==='edit')@include('content.form')@endif</div>
@endsection
