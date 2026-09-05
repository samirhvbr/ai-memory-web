{{-- The panel's own vocabulary, in public/css/ai-memory.css. Pushed once per
     request through _tabs, which every screen includes, so the sign-in page
     never loads it. --}}
@once
@push('styles')
<link rel="stylesheet" href="{{ vasset('css/ai-memory.css') }}">
@endpush
@endonce
