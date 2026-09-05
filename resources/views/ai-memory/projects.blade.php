@extends('layouts.app')

@section('title', 'Projects')

@section('content')
<div class="aim">
    @include('ai-memory._tabs')

    @unless($available)
        @include('ai-memory._unavailable')
    @else
        @php
            $T = \App\Services\AiMemory\AiMemoryTime::class;
            $n = fn ($v) => number_format((int) $v);
            // Proportional bar: where the memory is concentrated, at a glance.
            $maxObs = max(1, (int) collect($projects)->max('observations'));
        @endphp

        <section class="card aim-card">
            <header class="aim-card__head">
                <div>
                    <h2>Projects</h2>
                    <p class="aim-sub">{{ count($projects) }} in memory · most recent activity first</p>
                </div>
            </header>

            @if(empty($projects))
                <div class="aim-blank">
                    <x-icon name="folder"/>
                    <p>No project in memory yet.</p>
                    <p>A project shows up here the first time an agent opens a session inside it.</p>
                </div>
            @else
                <div class="aim-scroll">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Project</th>
                                <th scope="col">Repository</th>
                                <th scope="col">Pages</th>
                                <th scope="col">Sessions</th>
                                <th scope="col">Observations</th>
                                <th scope="col">Last activity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($projects as $p)
                                <tr>
                                    <td>
                                        <a href="{{ route('ai-memory.projects.show', $p->id_hex) }}" class="aim-strong">{{ $p->name }}</a>
                                        <div class="aim-mono">{{ $p->workspace }}</div>
                                    </td>
                                    <td><span class="aim-path" style="--w:300px" title="{{ $p->repo_path }}">{{ $p->repo_path ?? '—' }}</span></td>
                                    <td class="aim-mono">{{ $n($p->pages) }}</td>
                                    <td class="aim-mono">{{ $n($p->sessions) }}</td>
                                    <td>
                                        <span class="aim-bar">
                                            <span class="aim-bar__track"><span class="aim-bar__fill" style="width:{{ round($p->observations / $maxObs * 100, 1) }}%"></span></span>
                                            <span class="aim-bar__n">{{ $n($p->observations) }}</span>
                                        </span>
                                    </td>
                                    <td class="aim-when" title="{{ $T::format($p->last_session_at) }}">{{ $T::human($p->last_session_at) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endunless
</div>
@endsection
