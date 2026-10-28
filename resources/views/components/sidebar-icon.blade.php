@props(['name'])
<svg {{ $attributes->merge(['class' => 'h-5 w-5 shrink-0', 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('clock')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
        @case('layers')<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 12 9 5 9-5M3 16l9 5 9-5"/>@break
        @case('building')<path d="M4 21V5l8-2v18M4 9H2m2 4H2m2 4H2m10-9h2m-2 4h2m-2 4h2m2 7V9l6 2v10M8 7v1m0 4v1m0 4v1m8-3v1m3-1v1"/>@break
        @case('ticket')<path d="M3 8a2 2 0 0 0 0 4v5h18v-5a2 2 0 0 1 0-4V5H3v3Z"/><path d="M13 5v2m0 3v2m0 3v2"/>@break
        @case('receipt')<path d="M5 3h14v18l-3-2-4 2-4-2-3 2V3Z"/><path d="M9 8h6m-6 4h6m-6 4h3"/>@break
        @case('shield')<path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/><path d="m9 12 2 2 4-4"/>@break
        @case('settings')<path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2" fill="currentColor"/><circle cx="15" cy="12" r="2" fill="currentColor"/><circle cx="7" cy="18" r="2" fill="currentColor"/>@break
        @default<circle cx="12" cy="12" r="9"/><path d="M8 12h8m-4-4v8"/>
    @endswitch
</svg>
