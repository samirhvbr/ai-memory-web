{{-- Base visual system. Two plain stylesheets, no build step: the CSS is served
     straight from public/ and cache-busted by the file's mtime (vasset()).
     Included by both layouts — the panel and the sign-in page. --}}
<link rel="stylesheet" href="{{ vasset('css/app.css') }}">
