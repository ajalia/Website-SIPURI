@extends('layouts.base')
@push('styles')<link rel="stylesheet" href="{{ asset('assets/rt.css') }}">@endpush
@section('shell')
@php($viewer=auth()->user())
<div class="rt-shell">
    <aside class="rt-sidebar offcanvas-md offcanvas-start" tabindex="-1" id="rtMenu" aria-label="Menu Pengurus RT">
        <div class="rt-brand-row"><a href="{{ route('rt.dashboard') }}" class="rt-brand"><span class="rt-brand-mark"><x-login-icon name="building"/></span><span><strong>SIPURI</strong><small>Puri Indah Jatinangor</small></span></a><button class="btn-close d-md-none" type="button" data-bs-dismiss="offcanvas" data-bs-target="#rtMenu" aria-label="Tutup menu"></button></div>
        <nav class="rt-navigation" aria-label="Menu utama">
            @foreach([['rt.dashboard','Beranda RT','home'],['rt.registrations.index','Verifikasi Warga','check'],['rt.houses.index','Data Warga','people']] as [$route,$label,$icon])
                @php($active=$route==='rt.dashboard'?request()->routeIs($route):request()->routeIs(str_replace('.index','.*',$route)))
                <a href="{{ route($route) }}" class="rt-nav-link {{ $active?'active':'' }}" @if($active) aria-current="page" @endif><x-login-icon :name="$icon"/><span>{{ $label }}</span></a>
            @endforeach
        </nav>
        <form data-logout-trigger-form action="{{ route('logout') }}" method="post" class="rt-logout">@csrf<button class="btn" type="submit"><x-login-icon name="logout"/> Keluar</button></form>
    </aside>
    <div class="rt-body"><header class="rt-header"><div class="d-flex align-items-center gap-3"><button class="btn rt-menu-button d-md-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#rtMenu" aria-controls="rtMenu" aria-label="Buka menu"><x-login-icon name="menu"/> Menu</button><span class="rt-page-title">@yield('page-title','Beranda RT')</span></div><div class="rt-account"><span><x-login-icon name="shield"/> Pengurus RT <span aria-hidden="true">•</span> {{ $viewer->rt?->label() }} · RW 07</span><x-account-menu/></div></header>
        <main id="main" class="rt-main">@include('partials.messages') @yield('content')</main>
    </div>
</div>
@endsection
@push('scripts')<script src="{{ asset('assets/rt.js') }}" defer></script>@endpush
