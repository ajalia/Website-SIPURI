@extends('layouts.base')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/login.css') }}">
<link rel="stylesheet" href="{{ asset('assets/stitch.css') }}">
@endpush
@section('shell')
<div class="login-page stitch-page @yield('page-class')">
    @include('partials.public-header')
    <main id="main" class="login-main"><div class="stitch-container @yield('card-width')">@yield('content')</div></main>
    @include('partials.public-footer')
</div>
@endsection
