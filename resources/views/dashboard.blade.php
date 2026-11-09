@extends('layouts.portal')
@section('title','Beranda')
@section('content')
<h1>Selamat datang, {{ auth()->user()->role===\App\Enums\Role::Resident ? auth()->user()->name : auth()->user()->role->label() }}</h1>
@if(auth()->user()->role===\App\Enums\Role::Rt)
<p class="text-secondary mb-4">Periksa pendaftaran akun dan lihat data rumah di wilayah Anda.</p><div class="row g-3 mb-4"><div class="col-sm-6"><section class="surface stat-card"><p>Pendaftaran Menunggu</p><strong class="stat-value text-success">{{ $pending }}</strong><a class="d-block mt-3" href="{{ route('rt.registrations.index') }}">Periksa Pendaftaran →</a></section></div><div class="col-sm-6"><section class="surface stat-card"><p>Rumah Tangga Masih Tinggal</p><strong class="stat-value">{{ $houses }}</strong><a class="d-block mt-3" href="{{ route('rt.houses.index') }}">Buka Data Warga →</a></section></div></div>
@elseif(auth()->user()->role===\App\Enums\Role::Resident)
<p class="text-secondary mb-4">Akun Anda sudah disetujui oleh pengurus RT.</p><section class="surface p-4"><h2>Data Rumah Anda</h2><dl class="data-list"><div><dt>Penanggung Jawab</dt><dd>{{ auth()->user()->name }}</dd></div><div><dt>Alamat</dt><dd><x-house-address :block="auth()->user()->house?->block ?? auth()->user()->latestResidency?->block" :number="auth()->user()->house?->house_number ?? auth()->user()->latestResidency?->house_number"/></dd></div><div><dt>Wilayah</dt><dd>{{ auth()->user()->rt?->label() }} · RW 07</dd></div><div><dt>Status Akun</dt><dd><span class="badge rounded-pill badge-approved">Disetujui RT</span></dd></div></dl></section>
@else
<p class="text-secondary">Anda masuk sebagai {{ auth()->user()->role->label() }} untuk RW 07.</p><section class="surface p-4 mt-4"><h2>Informasi Akun</h2><dl class="data-list"><div><dt>Nama</dt><dd>{{ auth()->user()->name }}</dd></div><div><dt>Username</dt><dd>{{ auth()->user()->username }}</dd></div><div><dt>Peran</dt><dd>{{ auth()->user()->role->label() }}</dd></div></dl></section>
@endif
@endsection
