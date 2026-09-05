@extends('layouts.app')

@section('title', 'Session')

@section('topbar-actions')
    <a href="{{ route('ai-memory.sessions') }}" class="btn btn--sm"><x-icon name="arrow-left"/> Sessions</a>
@endsection

@section('content')
<div class="aim">
    @include('ai-memory._tabs')

    @unless($available)
        @include('ai-memory._unavailable')
    @else
        @php
            $T = \App\Services\AiMemory\AiMemoryTime::class;
            $impClass = fn ($i) => $i >= 8 ? 'aim-imp--high' : ($i >= 5 ? 'aim-imp--mid' : '');
            $live = ! $session->ended_at;
        @endphp

        <header class="aim-hero">
            <div>
                <h1>{{ $session->project }}</h1>
                <div class="aim-hero__chips">
                    <span class="aim-chip aim-chip--accent">{{ $session->agent_kind }}</span>
                    @if($live)
                        <span class="aim-chip aim-chip--live">still open</span>
                    @else
                        <span class="aim-chip">{{ $T::duration($session->started_at, $session->ended_at) }}</span>
                    @endif
                    <span class="aim-chip">{{ number_format((int) $session->obs_count) }} observations</span>
                </div>
                <code class="aim-hero__path">{{ $session->cwd ?? 'no directory recorded' }}</code>
            </div>
        </header>

        <dl class="aim-facts aim-facts--cols" style="margin-bottom:22px">
            <div><dt>Started</dt><dd class="aim-mono">{{ $T::format($session->started_at) }}</dd></div>
            <div><dt>Ended</dt><dd class="aim-mono">{{ $session->ended_at ? $T::format($session->ended_at) : 'still open' }}</dd></div>
            <div><dt>Duration</dt><dd class="aim-mono">{{ $T::duration($session->started_at, $session->ended_at) }}</dd></div>
            <div>
                <dt>Summary</dt>
                <dd>
                    @if($session->summary_page_hex)
                        <a href="{{ route('ai-memory.pages.show', $session->summary_page_hex) }}">{{ $session->summary_title ?? 'open the page' }} ↗</a>
                    @else
                        <span class="aim-mono">not consolidated yet</span>
                    @endif
                </dd>
            </div>
        </dl>

        <section class="card aim-card">
            <header class="aim-card__head">
                <div>
                    <h2>What this session learned</h2>
                    <p class="aim-sub">{{ count($observations) }} {{ count($observations) === 1 ? 'observation' : 'observations' }} · in chronological order</p>
                </div>
            </header>

            @if(empty($observations))
                <div class="aim-blank">
                    <x-icon name="bulb"/>
                    <p>No observation in this session.</p>
                    <p>{{ $live ? 'The session is still open — the facts may come later.' : 'The session ended without recording any fact: short work, or hooks turned off.' }}</p>
                </div>
            @else
                <ul class="aim-tl">
                    @foreach($observations as $o)
                        <li>
                            <div class="aim-tl__time">{{ $T::format($o->created_at, 'm-d H:i:s') }}</div>
                            <div class="aim-tl__row">
                                <span class="aim-chip">{{ $o->kind }}</span>
                                <span class="aim-imp {{ $impClass($o->importance) }}" title="Importance {{ $o->importance }} out of 10">
                                    <span class="aim-imp__track"><span class="aim-imp__fill" style="width:{{ min(100, (int) $o->importance * 10) }}%"></span></span>
                                    <span class="aim-imp__n">{{ $o->importance }}</span>
                                </span>
                                <a href="{{ route('ai-memory.observations.show', $o->id_hex) }}">{{ $o->title }}</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endunless
</div>
@endsection
