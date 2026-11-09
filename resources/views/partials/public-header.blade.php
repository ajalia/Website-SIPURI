<header class="login-header">
    <div class="login-header-inner d-flex align-items-center justify-content-between flex-wrap">
        <a href="{{ route('home') }}" class="login-brand d-flex align-items-center" aria-label="SIPURI — Beranda">
            <span class="login-brand-mark"><x-login-icon name="home" /></span>
            <span><strong>SIPURI</strong><small>Perumahan Puri Indah Jatinangor</small></span>
        </a>
        @if(request()->routeIs('home'))
            <button class="btn stitch-menu d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#public-navigation" aria-controls="public-navigation" aria-expanded="false" aria-label="Buka navigasi"><x-login-icon name="menu" /></button>
            <nav id="public-navigation" class="collapse d-lg-flex public-navigation" aria-label="Navigasi utama">
                <a href="{{ route('home') }}" aria-current="page">Beranda</a>
                <a href="{{ route('home') }}#profil">Profil</a><a href="{{ route('home') }}#fasilitas">Fasilitas</a><a href="{{ route('home') }}#kuliner">Kuliner</a><a href="{{ route('home') }}#pengurus">Pengurus</a>
                @guest<a class="btn btn-primary" href="{{ route('login') }}">Masuk</a>@endguest
                @auth<a class="btn btn-primary" href="{{ route('dashboard') }}">Buka Portal</a><x-account-menu/>@endauth
            </nav>
        @else
            <nav class="d-flex align-items-center gap-3" aria-label="Navigasi utama"><a class="login-back" href="{{ route('home') }}"><span aria-hidden="true">←</span> Kembali ke Beranda</a>
                @auth<x-account-menu/>@endauth            </nav>
        @endif
    </div>
</header>
