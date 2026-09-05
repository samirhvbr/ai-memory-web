<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>@yield('title', 'Panel') — {{ config('aimemory.brand', 'ai-memory') }}</title>
    @include('layouts._styles')
    @stack('styles')
</head>
<body>
<header class="topbar">
    <a class="brand" href="{{ route('ai-memory.dashboard') }}">
        <span class="brand__mark" aria-hidden="true">aim</span>
        <span class="brand__text">{{ config('aimemory.brand', 'ai-memory') }}<span>.</span></span>
    </a>
    <h1 class="topbar__title">@yield('title', 'Panel')</h1>
    <div class="topbar__spacer"></div>
    <div class="topbar__actions">@yield('topbar-actions')</div>
    <div class="topbar__user">
        @if(! empty($appVersion))
            <span class="topbar__version" title="Panel version (version.md)"><x-icon name="branch"/> v{{ $appVersion }}</span>
        @endif
        <b>{{ auth()->user()?->name }}</b>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="linkbtn"><x-icon name="logout"/> Sign out</button>
        </form>
    </div>
</header>

<main class="content">
    @if(session('status'))
        <div class="alert alert--ok">{{ session('status') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert--error">{{ session('error') }}</div>
    @endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
