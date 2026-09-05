@extends('layouts.app')

@section('title', 'Observation')

@section('topbar-actions')
    <a href="{{ route('ai-memory.observations') }}" class="btn btn--sm"><x-icon name="arrow-left"/> Observations</a>
@endsection

@section('content')
<div class="aim">
    @include('ai-memory._tabs')

    @unless($available)
        @include('ai-memory._unavailable')
    @else
        @php
            $T = \App\Services\AiMemory\AiMemoryTime::class;
            $imp = (int) $observation->importance;
            $impClass = $imp >= 8 ? 'aim-imp--high' : ($imp >= 5 ? 'aim-imp--mid' : '');
        @endphp

        <header class="aim-hero">
            <div>
                <h1>{{ $observation->title }}</h1>
                <div class="aim-hero__chips">
                    <span class="aim-chip">{{ $observation->kind }}</span>
                    @if($observation->agent_kind)<span class="aim-chip aim-chip--accent">{{ $observation->agent_kind }}</span>@endif
                    <span class="aim-imp {{ $impClass }}" title="Importance {{ $imp }} out of 10">
                        <span class="aim-imp__track"><span class="aim-imp__fill" style="width:{{ min(100, $imp * 10) }}%"></span></span>
                        <span class="aim-imp__n">importance {{ $imp }}/10</span>
                    </span>
                </div>
            </div>
        </header>

        <dl class="aim-facts aim-facts--cols" style="margin-bottom:22px">
            <div><dt>Project</dt><dd>{{ $observation->project }}</dd></div>
            <div><dt>Recorded</dt><dd class="aim-mono">{{ $T::format($observation->created_at) }}</dd></div>
            <div>
                <dt>Session</dt>
                <dd>
                    @if($observation->session_hex)
                        <a href="{{ route('ai-memory.sessions.show', $observation->session_hex) }}">open the session ↗</a>
                    @else
                        <span class="aim-mono">—</span>
                    @endif
                </dd>
            </div>
            <div><dt>Kind</dt><dd class="aim-mono">{{ $observation->kind }}</dd></div>
        </dl>

        <section class="card aim-card">
            <header class="aim-card__head">
                <div>
                    <h2>The recorded fact</h2>
                    <p class="aim-sub">the text exactly as the agent wrote it</p>
                </div>
            </header>

            @if(trim((string) $observation->body) === '')
                <p class="aim-empty">This observation has no body — only the title above.</p>
            @else
                <p class="aim-raw">{{ $observation->body }}</p>
            @endif
        </section>
    @endunless
</div>
@endsection
