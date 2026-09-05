@extends('layouts.app')

@section('title', 'Observations')

@section('content')
<div class="aim">
    @include('ai-memory._tabs')

    @unless($available)
        @include('ai-memory._unavailable')
    @else
        @php
            $T = \App\Services\AiMemory\AiMemoryTime::class;
            // Importance is a 0–10 scale: a meter reads better than a bare number.
            $impClass = fn ($i) => $i >= 8 ? 'aim-imp--high' : ($i >= 5 ? 'aim-imp--mid' : '');
            $hasFilters = ! empty(array_filter($filters, fn ($v) => $v !== null && $v !== ''));
        @endphp

        <section class="card aim-card">
            <header class="aim-card__head">
                <div>
                    <h2>Observations</h2>
                    <p class="aim-sub">{{ number_format($observations->total()) }} facts learned · most recent first</p>
                </div>
            </header>

            <form method="GET" action="{{ route('ai-memory.observations') }}" class="aim-filters">
                <div class="form-row">
                    <label for="f-kind">Kind</label>
                    <select name="kind" id="f-kind">
                        <option value="">All</option>
                        @foreach($kinds as $k)
                            <option value="{{ $k }}" @selected(($filters['kind'] ?? null) === $k)>{{ $k }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-row">
                    <label for="f-imp">Min. importance</label>
                    <select name="importance" id="f-imp">
                        <option value="">Any</option>
                        @foreach([3 => '≥ 3', 5 => '≥ 5', 8 => '≥ 8'] as $v => $lbl)
                            <option value="{{ $v }}" @selected((int) ($filters['importance'] ?? 0) === $v)>{{ $lbl }}</option>
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
                    <label for="f-days">Period</label>
                    <select name="days" id="f-days">
                        <option value="">Any time</option>
                        @foreach([1 => 'Today', 7 => '7 days', 30 => '30 days', 90 => '90 days'] as $v => $lbl)
                            <option value="{{ $v }}" @selected((int) ($filters['days'] ?? 0) === $v)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="aim-filters__go">
                    <button type="submit" class="btn btn--primary">Filter</button>
                    @if($hasFilters)<a href="{{ route('ai-memory.observations') }}" class="btn">Clear</a>@endif
                </div>
            </form>

            @if($observations->isEmpty())
                <div class="aim-blank">
                    <x-icon name="bulb"/>
                    <p>{{ $hasFilters ? 'No observation matches this filter.' : 'No observation recorded yet.' }}</p>
                    <p>{{ $hasFilters ? 'Lower the minimum importance or widen the period.' : 'Each observation is one fact an agent learned during a session.' }}</p>
                </div>
            @else
                <div class="aim-scroll">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Kind</th>
                                <th scope="col">Title</th>
                                <th scope="col">Importance</th>
                                <th scope="col">Project</th>
                                <th scope="col">When</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($observations as $o)
                                <tr>
                                    <td><span class="aim-chip">{{ $o->kind }}</span></td>
                                    <td>
                                        <a href="{{ route('ai-memory.observations.show', $o->id_hex) }}" class="aim-strong">{{ \Illuminate\Support\Str::limit($o->title, 90) }}</a>
                                        @if($o->session_hex)
                                            <div><a href="{{ route('ai-memory.sessions.show', $o->session_hex) }}" class="aim-mono" title="Open the session that produced this observation">in session ↗</a></div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="aim-imp {{ $impClass($o->importance) }}" title="Importance {{ $o->importance }} out of 10">
                                            <span class="aim-imp__track"><span class="aim-imp__fill" style="width:{{ min(100, (int) $o->importance * 10) }}%"></span></span>
                                            <span class="aim-imp__n">{{ $o->importance }}</span>
                                        </span>
                                    </td>
                                    <td class="aim-mono">{{ $o->project }}</td>
                                    <td class="aim-when" title="{{ $T::format($o->created_at) }}">{{ $T::human($o->created_at) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $observations->links() }}
            @endif
        </section>
    @endunless
</div>
@endsection
