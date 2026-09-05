{{-- Sub-navigation. Included by EVERY screen — and it brings the visual system
     along (once per request), so the whole panel speaks one vocabulary. --}}
@include('ai-memory._styles')


@php
    $r = (string) request()->route()?->getName();
    $tabs = [
        'ai-memory.dashboard' => 'Dashboard',
        'ai-memory.projects' => 'Projects',
        'ai-memory.workspaces' => 'Workspaces',
        'ai-memory.pages' => 'Pages',
        'ai-memory.sessions' => 'Sessions',
        'ai-memory.observations' => 'Observations',
        'ai-memory.handoffs' => 'Handoffs',
        'ai-memory.search' => 'Search',
    ];
@endphp

<nav class="aimnav" aria-label="Sections">
    @foreach($tabs as $name => $label)
        {{-- a detail screen (…/{hexId}) keeps its listing's tab lit --}}
        <a href="{{ route($name) }}" @if($r === $name || str_starts_with($r, $name.'.')) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
