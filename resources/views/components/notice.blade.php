@props(['icon' => 'info', 'tone' => '', 'title' => null])
<div {{ $attributes->class(['stitch-notice', 'stitch-notice-'.$tone => (bool)$tone]) }}>
    <x-login-icon :name="$icon" />
    <div>@if($title)<strong>{{ $title }}</strong>@endif<div>{{ $slot }}</div></div>
</div>
