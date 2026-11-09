@props(['name'])
<svg {{ $attributes->class(['login-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name)
    @case('home')<path d="M12 22s8-7 8-13a8 8 0 1 0-16 0c0 6 8 13 8 13Z"/><path d="m8 10 4-3 4 3v6h-3v-4h-2v4H8Z"/>@break
    @case('lock')<rect x="6" y="10" width="12" height="11" rx="2"/><path d="M9 10V6a3 3 0 0 1 6 0"/><circle cx="12" cy="15" r=".8"/><path d="M12 16v2"/>@break
    @case('person')<circle cx="12" cy="8" r="3"/><path d="M5 21v-2a7 5 0 0 1 14 0v2Z"/>@break
    @case('eye')<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>@break
    @case('login')<path d="M13 4h6v16h-6M3 12h12m-5-5 5 5-5 5"/>@break
    @case('support')<path d="M4 14v-3a8 8 0 0 1 16 0v6c0 3-3 4-7 4M4 11H2v6h4v-6Zm16 0h2v6h-4v-6Z"/><path d="M9 9h.01M15 9h.01M10 15h4"/>@break
    @case('shield')<path d="m12 2 8 3v6c0 5-4 9-8 11-4-2-8-6-8-11V5Z"/><path d="m8 12 3 3 5-6"/>@break
    @case('info')<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>@break
    @case('clock')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
    @case('refresh')<path d="M20 8a9 9 0 1 0 1 7M20 3v5h-5"/>@break
    @case('reset')<path d="M4 8a9 9 0 1 1-1 7M4 3v5h5"/><rect x="9" y="11" width="6" height="6" rx="1"/><path d="M10 11V9a2 2 0 0 1 4 0v2"/>@break
    @case('edit')<path d="M20 14v6H4V4h10M11 13l-1 4 4-1 8-8-3-3Z"/>@break
    @case('check')<path d="m4 12 5 5L20 6"/>@break
    @case('send')<path d="m3 3 19 9-19 9 4-9Zm4 9h15"/>@break
    @case('menu')<path d="M3 6h18M3 12h18M3 18h18"/>@break
    @case('leaf')<path d="M4 20C0 3 17 5 21 2c1 17-10 20-17 18Zm0 0L16 8"/>@break
    @case('people')<circle cx="9" cy="7" r="3"/><path d="M2 21v-3a7 6 0 0 1 14 0v3M17 4a3 3 0 0 1 0 6M18 13c3 1 4 3 4 6"/>@break
    @case('food')<path d="M4 3v7m3-7v7m3-7v7M4 7h6m-3 3v11M20 3c-5 3-5 10 0 10V3Zm0 10v8"/>@break
    @case('building')<path d="m2 9 10-6 10 6M4 10v11h16V10M8 11v5m4-5v5m4-5v5M2 21h20"/>@break
    @case('search')<circle cx="10" cy="10" r="6"/><path d="m15 15 6 6"/>@break
    @case('calendar')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18"/>@break
    @case('logout')<path d="M10 3H4v18h6m-1-9h12m-5-5 5 5-5 5"/>@break
    @case('close')<path d="m5 5 14 14M5 19 19 5"/>@break
    @case('move')<path d="m3 10 7 3 10-8 2 2-9 11H4l-2-2 5-1-4-5Zm0 12h18"/>@break
    @case('receipt')<path d="M5 2h14v20l-3-2-4 2-4-2-3 2V2Zm3 5h8M8 11h8m-8 4h5"/>@break
    @case('wallet')<path d="M3 6h17v15H3V6Zm0 0V3h14v3m-1 7h6v5h-6Z"/>@break
    @case('upload')<path d="M12 16V3m-5 5 5-5 5 5M3 15v6h18v-6"/>@break
@endswitch
</svg>
