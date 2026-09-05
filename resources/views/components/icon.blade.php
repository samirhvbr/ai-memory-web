{{-- Inline SVG icons.

     Deliberately not an icon font and not a CDN: this app is meant to run on a
     server that may have no outbound network, and a 12-glyph set does not
     justify a dependency. Stroke icons on a 24×24 grid, drawn with
     currentColor so they inherit whatever the surrounding text is. --}}
@props(['name'])

@php
    $paths = [
        'arrow-left' => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
        'swap' => '<path d="m8 3-4 4 4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/>',
        'bulb' => '<path d="M9 18h6"/><path d="M10 22h4"/><path d="M15.1 14.1a5 5 0 1 0-6.2 0c.6.5 1.1 1.3 1.1 2.1h4c0-.8.5-1.6 1.1-2.1Z"/>',
        'history' => '<path d="M3.2 10A9 9 0 1 1 3 13.2"/><path d="M3 4v6h6"/><path d="M12 7.5V12l3 2"/>',
        'file' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8Z"/><path d="M14 3v5h5"/><path d="M9 13h6"/><path d="M9 17h6"/>',
        'pin' => '<path d="M12 17v5"/><path d="M8.5 3h7l-1 6 2.5 2.5V14H7v-2.5L9.5 9Z"/>',
        'folder' => '<path d="M4 19V6a2 2 0 0 1 2-2h3.2l2 2H18a2 2 0 0 1 2 2v1"/><path d="m2.6 20 2.6-9H22l-2.6 9Z"/>',
        'question' => '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.4a2.5 2.5 0 1 1 3.3 2.4c-.6.2-.9.8-.9 1.4v.5"/><path d="M12 17.4h.01"/>',
        'search' => '<circle cx="11" cy="11" r="6"/><path d="m20 20-4.3-4.3"/>',
        'chat' => '<path d="M21 12a7 7 0 0 1-7 7H8.5L3 22l1.5-4.4A7 7 0 0 1 3 12a7 7 0 0 1 7-7h4a7 7 0 0 1 7 7Z"/>',
        'warning' => '<path d="M12 4 2.6 20h18.8Z"/><path d="M12 10v4"/><path d="M12 17.4h.01"/>',
        'layers' => '<path d="m12 3 9 5-9 5-9-5Z"/><path d="m3 13 9 5 9-5"/>',
        'branch' => '<path d="M6 6v12"/><circle cx="6" cy="4" r="2"/><circle cx="6" cy="20" r="2"/><circle cx="18" cy="6" r="2"/><path d="M18 8v2a4 4 0 0 1-4 4H8"/>',
        'logout' => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/>',
    ];
@endphp

<svg {{ $attributes->merge(['class' => 'icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor"
     stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"
>{!! $paths[$name] ?? '' !!}</svg>
