@extends('layouts.app')

@section('title', 'Sessions')

@section('content')
<div class="aim">
    @include('ai-memory._tabs')

    @unless($available)
        @include('ai-memory._unavailable')
    @else
        @php
            $T = \App\Services\AiMemory\AiMemoryTime::class;
            $sort = $filters['sort'] ?? 'recent';
            // Sort links keep the current filters and go back to page 1.
            $sortUrl = fn ($s) => route('ai-memory.sessions', array_merge(\Illuminate\Support\Arr::except(request()->query(), 'page'), ['sort' => $s]));
            $startNext = $sort === 'oldest' ? 'recent' : 'oldest';
            $startArrow = $sort === 'recent' ? '▼' : ($sort === 'oldest' ? '▲' : '');
            $durNext = $sort === 'longest' ? 'shortest' : 'longest';
            $durArrow = $sort === 'longest' ? '▼' : ($sort === 'shortest' ? '▲' : '');
            $hasFilters = ! empty(array_filter($filters, fn ($v, $k) => $k !== 'sort' && $v !== null && $v !== '', ARRAY_FILTER_USE_BOTH));
            $maxObs = max(1, (int) collect($sessions->items())->max('obs_count'));
        @endphp

        <section class="card aim-card">
            <header class="aim-card__head">
                <div>
                    <h2>Sessions</h2>
                    <p class="aim-sub">{{ number_format($sessions->total()) }} in total · each session is one agent at work</p>
                </div>
            </header>

            <form method="GET" action="{{ route('ai-memory.sessions') }}" class="aim-filters">
                <div class="form-row">
                    <label for="f-agent">Agent</label>
                    <select name="agent" id="f-agent">
                        <option value="">All</option>
                        @foreach($agentKinds as $ak)
                            <option value="{{ $ak }}" @selected(($filters['agent'] ?? null) === $ak)>{{ $ak }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <label for="f-project">Project</label>
                    <select name="project" id="f-project">
                        <option value="">All</option>
                        @foreach($projectOptions as $opt)
                            <option value="{{ $opt->id_hex }}" @selected(($filters['project'] ?? null) === $opt->id_hex)>{{ $opt->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <label for="f-days">Started</label>
                    <select name="days" id="f-days">
                        <option value="">Any time</option>
                        @foreach([1 => 'Today', 7 => '7 days', 30 => '30 days', 90 => '90 days'] as $v => $lbl)
                            <option value="{{ $v }}" @selected((int) ($filters['days'] ?? 0) === $v)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- keep the current sort when applying filters --}}
                <input type="hidden" name="sort" value="{{ $sort }}">
                <div class="aim-filters__go">
                    <button type="submit" class="btn btn--primary">Filter</button>
                    @if($hasFilters)<a href="{{ route('ai-memory.sessions') }}" class="btn">Clear</a>@endif
                </div>
            </form>

            @if($sessions->isEmpty())
                <div class="aim-blank">
                    <x-icon name="chat"/>
                    <p>{{ $hasFilters ? 'No session matches this filter.' : 'No session recorded yet.' }}</p>
                    <p>{{ $hasFilters ? 'Try widening the period or clearing the filters.' : 'A session is opened by the agent hooks (Claude Code, Codex…) when work starts in a project.' }}</p>
                </div>
            @else
                <div class="aim-scroll">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Agent</th>
                                <th scope="col">Project</th>
                                <th scope="col">Directory</th>
                                <th scope="col" aria-sort="{{ $sort === 'recent' ? 'descending' : ($sort === 'oldest' ? 'ascending' : 'none') }}">
                                    <a href="{{ $sortUrl($startNext) }}" class="aim-th-sort" title="Sort by start time">Started <i>{{ $startArrow }}</i></a>
                                </th>
                                <th scope="col" aria-sort="{{ $sort === 'longest' ? 'descending' : ($sort === 'shortest' ? 'ascending' : 'none') }}">
                                    <a href="{{ $sortUrl($durNext) }}" class="aim-th-sort" title="Sort by duration">Duration <i>{{ $durArrow }}</i></a>
                                </th>
                                <th scope="col">Observations</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sessions as $s)
                                <tr>
                                    <td>
                                        <a href="{{ route('ai-memory.sessions.show', $s->id_hex) }}">
                                            <span class="aim-chip aim-chip--accent">{{ $s->agent_kind }}</span>
                                        </a>
                                    </td>
                                    <td class="aim-mono">{{ $s->project }}</td>
                                    <td><span class="aim-path" style="--w:240px" title="{{ $s->cwd }}">{{ $s->cwd ?? '—' }}</span></td>
                                    <td class="aim-when" title="{{ $T::format($s->started_at) }}">{{ $T::format($s->started_at) }}</td>
                                    <td>
                                        @if($s->ended_at)
                                            <span class="aim-when">{{ $T::duration($s->started_at, $s->ended_at) }}</span>
                                        @else
                                            <span class="aim-chip aim-chip--live">still open</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="aim-bar">
                                            <span class="aim-bar__track"><span class="aim-bar__fill" style="width:{{ round(($s->obs_count ?: 0) / $maxObs * 100, 1) }}%"></span></span>
                                            <span class="aim-bar__n">{{ number_format((int) $s->obs_count) }}</span>
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $sessions->links() }}
            @endif
        </section>
    @endunless
</div>
@endsection
