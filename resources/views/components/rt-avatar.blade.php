@props(['name'])
@php($initials=collect(preg_split('/\s+/u',trim($name)))->take(2)->map(fn($part)=>mb_strtoupper(mb_substr($part,0,1)))->implode(''))
<span class="rt-avatar" aria-hidden="true">{{ $initials }}</span>
