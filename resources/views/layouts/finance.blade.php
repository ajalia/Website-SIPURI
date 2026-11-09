@extends('layouts.base')
@push('styles')<link rel="stylesheet" href="{{ asset('assets/rt.css') }}"><link rel="stylesheet" href="{{ asset('assets/finance.css') }}">@endpush
@php
 $viewer=auth()->user();$treasurer=$viewer->role===\App\Enums\Role::Treasurer;
 $menus=$treasurer?[
 ['treasurer.dashboard','Beranda Bendahara','home',['treasurer.dashboard']],
 ['treasurer.invoices.index','Tagihan Iuran','receipt',['treasurer.invoices.*','treasurer.periods.*','treasurer.account*']],
 ['treasurer.payments.index','Verifikasi Pembayaran','shield',['treasurer.payments.*']],
 ['treasurer.reports','Kas & Laporan Keuangan','wallet',['treasurer.reports*']],
 ]:[
 ['resident.dashboard','Beranda Warga','home',['resident.dashboard']],
 ['resident.invoices.index','Tagihan Saya','receipt',['resident.invoices.*','resident.payments.*']],
 ['resident.reports','Laporan Keuangan','wallet',['resident.reports*']],
 ];
 $home=$treasurer?'treasurer.dashboard':'resident.dashboard';
@endphp
@section('shell')
<div class="rt-shell finance-shell {{ $treasurer?'treasurer-shell':'resident-shell' }}">
 <aside class="rt-sidebar offcanvas-md offcanvas-start" tabindex="-1" id="financeMenu" aria-label="Menu {{ $treasurer?'Bendahara':'Warga' }}"><div class="rt-brand-row"><a class="rt-brand" href="{{ route($home) }}"><span class="rt-brand-mark"><x-login-icon :name="$treasurer?'building':'leaf'"/></span><span><strong>SIPURI</strong><small>Puri Indah Jatinangor</small></span></a><button class="btn-close d-md-none" type="button" data-bs-dismiss="offcanvas" data-bs-target="#financeMenu" aria-label="Tutup menu"></button></div>
 <nav class="rt-navigation" aria-label="Menu utama">@foreach($menus as [$route,$label,$icon,$patterns])<a class="rt-nav-link {{ request()->routeIs(...$patterns)?'active':'' }}" href="{{ route($route) }}" @if(request()->routeIs(...$patterns)) aria-current="page" @endif><x-login-icon :name="$icon"/><span>{{ $label }}</span></a>@endforeach</nav>
 <form class="rt-logout" data-logout-trigger-form action="{{ route('logout') }}" method="post">@csrf<button class="btn" type="submit"><x-login-icon name="logout"/> Keluar</button></form></aside>
 <div class="rt-body"><header class="rt-header"><div class="d-flex align-items-center gap-3"><button class="btn d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#financeMenu" aria-controls="financeMenu"><x-login-icon name="menu"/> Menu</button><span class="rt-page-title">@yield('page-title',$treasurer?'Beranda Bendahara':'Beranda Warga')</span></div><div class="rt-account"><span>@if($treasurer)Bendahara · RW 07 @else<span class="finance-account-text"><strong>{{ $viewer->name }}</strong><small><x-house-address :block="$viewer->house?->block ?? $viewer->latestResidency?->block ?? $viewer->block" :number="$viewer->house?->house_number ?? $viewer->latestResidency?->house_number ?? $viewer->house_number"/>, {{ $viewer->rt?->label() }} · RW 07</small></span>@endif</span><x-account-menu/></div></header><main id="main" class="rt-main">@include('partials.messages') @yield('content')</main></div>
</div>
@endsection
@push('scripts')<script src="{{ asset('assets/rt.js') }}" defer></script>@endpush
