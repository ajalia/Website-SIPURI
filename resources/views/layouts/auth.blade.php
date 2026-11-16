@extends('layouts.base')
@push('styles')
<link rel="stylesheet" href="{{ asset('assets/login.css') }}">
<link rel="stylesheet" href="{{ asset('assets/frontend.css') }}">
@endpush
@section('shell')
<div class="login-page frontend-page @yield('page-class')">
    @include('partials.public-header')
    <main id="main" class="login-main"><div class="frontend-container @yield('card-width')">@yield('content')</div></main>
    @include('partials.public-footer')
</div>
@endsection
