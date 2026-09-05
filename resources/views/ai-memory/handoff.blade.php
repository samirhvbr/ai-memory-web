@extends('layouts.app')

@section('title', 'Handoff')

@section('topbar-actions')
    <a href="{{ route('ai-memory.handoffs') }}" class="btn btn--sm"><x-icon name="arrow-left"/> Handoffs</a>
@endsection

@section('content')
<div class="aim">
    @include('ai-memory._tabs')

    @unless($available)
        @include('ai-memory._unavailable')
    @else
        @php
            $T = \App\Services\AiMemory\AiMemoryTime::class;
            $stateChip = ['open' => 'aim-chip--warn', 'accepted' => 'aim-chip--ok', 'expired' => ''];
            $decode = fn ($json) => is_array($d = json_decode((string) $json, true)) ? $d : [];
            $openQuestions = $decode($handoff->open_questions);
            $nextSteps = $decode($handoff->next_steps);
            $filesTouched = $decode($handoff->files_touched);
        @endphp

        <header class="aim-hero">
            <div>
                <h1>{{ $handoff->from_agent }} → {{ $handoff->to_agent ?: 'any agent' }}</h1>
                <div class="aim-hero__chips">
                    <span class="aim-chip {{ $stateChip[$handoff->state] ?? '' }}">{{ $handoff->state }}</span>
                    <span class="aim-chip">{{ $handoff->project }}</span>
                    <span class="aim-chip">{{ $T::human($handoff->created_at) }}</span>
                </div>
                <code class="aim-hero__path">{{ $handoff->cwd ?? 'no directory recorded' }}</code>
            </div>
        </header>

        <dl class="aim-facts aim-facts--cols" style="margin-bottom:22px">
            <div><dt>Created</dt><dd class="aim-mono">{{ $T::format($handoff->created_at) }}</dd></div>
            <div>
                <dt>Accepted</dt>
                <dd class="aim-mono">
                    @if($handoff->accepted_by)
                        {{ $handoff->accepted_by }} · {{ $T::format($handoff->accepted_at) }}
                    @else
                        not yet
                    @endif
                </dd>
            </div>
        </dl>

        <section class="card aim-card">
            <header class="aim-card__head">
                <div>
                    <h2>Summary left for the next session</h2>
                    <p class="aim-sub">the note the agent wrote on its way out</p>
                </div>
            </header>

            @if(trim((string) $handoff->summary) === '')
                <p class="aim-empty">No summary — this handoff only carried the lists below.</p>
            @else
                <p class="aim-raw">{{ $handoff->summary }}</p>
            @endif
        </section>

        <div class="aim-grid2 aim-grid2--even" style="grid-template-columns:repeat(auto-fit,minmax(280px,1fr))">
            <section class="card aim-card">
                <header class="aim-card__head">
                    <div>
                        <h2>Open questions</h2>
                        <p class="aim-sub">{{ count($openQuestions) }} {{ count($openQuestions) === 1 ? 'question' : 'questions' }}</p>
                    </div>
                </header>
                @if(empty($openQuestions))
                    <p class="aim-empty">Nothing waiting on a decision.</p>
                @else
                    <ul class="aim-items">
                        @foreach($openQuestions as $question)
                            <li><span>{{ is_string($question) ? $question : json_encode($question, JSON_UNESCAPED_UNICODE) }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="card aim-card">
                <header class="aim-card__head">
                    <div>
                        <h2>Next steps</h2>
                        <p class="aim-sub">{{ count($nextSteps) }} {{ count($nextSteps) === 1 ? 'step' : 'steps' }}</p>
                    </div>
                </header>
                @if(empty($nextSteps))
                    <p class="aim-empty">No step agreed on.</p>
                @else
                    <ul class="aim-items">
                        @foreach($nextSteps as $step)
                            <li><span>{{ is_string($step) ? $step : json_encode($step, JSON_UNESCAPED_UNICODE) }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </section>

            <section class="card aim-card">
                <header class="aim-card__head">
                    <div>
                        <h2>Files touched</h2>
                        <p class="aim-sub">{{ count($filesTouched) }} {{ count($filesTouched) === 1 ? 'file' : 'files' }}</p>
                    </div>
                </header>
                @if(empty($filesTouched))
                    <p class="aim-empty">No file recorded.</p>
                @else
                    <ul class="aim-items aim-items--files">
                        @foreach($filesTouched as $file)
                            <li><code>{{ is_string($file) ? $file : json_encode($file, JSON_UNESCAPED_UNICODE) }}</code></li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    @endunless
</div>
@endsection
