@extends('layouts.app')

@section('title', 'Project')

@section('topbar-actions')
    <a href="{{ route('ai-memory.projects') }}" class="btn btn--sm"><x-icon name="arrow-left"/> Projects</a>
@endsection

@section('content')
<div class="aim">
    @include('ai-memory._tabs')

    @unless($available)
        @include('ai-memory._unavailable')
    @else
        @php
            $T = \App\Services\AiMemory\AiMemoryTime::class;
            $n = fn ($v) => number_format((int) $v);
            $tierChip = ['working' => 'aim-chip--warn', 'episodic' => 'aim-chip--accent', 'semantic' => 'aim-chip--ok'];
        @endphp

        <header class="aim-hero">
            <div>
                <h1>{{ $project->name }}</h1>
                <div class="aim-hero__chips">
                    <span class="aim-chip">workspace {{ $project->workspace }}</span>
                    <span class="aim-chip">created {{ $T::format($project->created_at, 'Y-m-d') }}</span>
                </div>
                <code class="aim-hero__path">{{ $project->repo_path ?? 'no repository path' }}</code>
            </div>
        </header>

        <section class="aim-panel" aria-label="Project totals">
            <div class="aim-panel__row aim-panel__row--3">
                <div class="aim-cell">
                    <p class="aim-label">Pages</p>
                    <a class="aim-cell__link" href="{{ route('ai-memory.pages', ['project' => $project->id_hex]) }}">
                        <p class="aim-value aim-value--md">{{ $n($project->pages) }}</p>
                    </a>
                </div>
                <div class="aim-cell">
                    <p class="aim-label">Sessions</p>
                    <a class="aim-cell__link" href="{{ route('ai-memory.sessions', ['project' => $project->id_hex]) }}">
                        <p class="aim-value aim-value--md">{{ $n($project->sessions) }}</p>
                    </a>
                </div>
                <div class="aim-cell">
                    <p class="aim-label">Observations</p>
                    <a class="aim-cell__link" href="{{ route('ai-memory.observations', ['project' => $project->id_hex]) }}">
                        <p class="aim-value aim-value--md">{{ $n($project->observations) }}</p>
                    </a>
                </div>
            </div>
        </section>

        <div class="aim-grid2 aim-grid2--even">
            <section class="card aim-card">
                <header class="aim-card__head">
                    <div>
                        <h2>Recent pages</h2>
                        <p class="aim-sub">consolidated knowledge of this project</p>
                    </div>
                    <a href="{{ route('ai-memory.pages', ['project' => $project->id_hex]) }}" class="btn btn--sm">see all</a>
                </header>

                @forelse($recentPages as $pg)
                    <div class="aim-tl__row" style="justify-content:space-between;margin:0;padding:9px 0;border-bottom:1px solid var(--hair)">
                        <a href="{{ route('ai-memory.pages.show', $pg->id_hex) }}" title="{{ $pg->path }}" style="min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">{{ $pg->title }}</a>
                        <span class="aim-chip {{ $tierChip[$pg->tier] ?? '' }}">{{ $pg->tier }}</span>
                    </div>
                @empty
                    <p class="aim-empty">No consolidated page in this project yet.</p>
                @endforelse
            </section>

            <section class="card aim-card">
                <header class="aim-card__head">
                    <div>
                        <h2>Recent sessions</h2>
                        <p class="aim-sub">who worked here, and how much came out of it</p>
                    </div>
                    <a href="{{ route('ai-memory.sessions', ['project' => $project->id_hex]) }}" class="btn btn--sm">see all</a>
                </header>

                @forelse($recentSessions as $s)
                    <div class="aim-tl__row" style="justify-content:space-between;margin:0;padding:9px 0;border-bottom:1px solid var(--hair)">
                        <a href="{{ route('ai-memory.sessions.show', $s->id_hex) }}">
                            <span class="aim-chip aim-chip--accent">{{ $s->agent_kind }}</span>
                            <span class="aim-when" style="margin-left:8px">{{ $T::format($s->started_at, 'm-d H:i') }}</span>
                        </a>
                        <span class="aim-mono">{{ $n($s->obs_count) }} obs</span>
                    </div>
                @empty
                    <p class="aim-empty">No session recorded in this project yet.</p>
                @endforelse
            </section>
        </div>
    @endunless
</div>
@endsection
