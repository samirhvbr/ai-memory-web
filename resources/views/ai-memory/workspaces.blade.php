@extends('layouts.app')

@section('title', 'Workspaces')

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
            $maxObs = max(1, (int) collect($workspaces)->max('observations'));
        @endphp

        <section class="card aim-card">
            <header class="aim-card__head">
                <div>
                    <h2>Workspaces</h2>
                    <p class="aim-sub">{{ count($workspaces) }} in memory · most recent activity first</p>
                </div>
            </header>

            @if(empty($workspaces))
                <div class="aim-blank">
                    <x-icon name="layers"/>
                    <p>No workspace in memory yet.</p>
                    <p>A workspace shows up here the first time an agent opens a session in a project inside it.</p>
                </div>
            @else
                <div class="aim-scroll">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Workspace</th>
                                <th scope="col">Projects</th>
                                <th scope="col">Pages</th>
                                <th scope="col">Sessions</th>
                                <th scope="col">Observations</th>
                                <th scope="col">Last activity</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($workspaces as $w)
                                <tr>
                                    <td><span class="aim-strong">{{ $w->name }}</span></td>
                                    <td class="aim-mono">{{ $n($w->projects) }}</td>
                                    <td class="aim-mono">{{ $n($w->pages) }}</td>
                                    <td class="aim-mono">{{ $n($w->sessions) }}</td>
                                    <td>
                                        <span class="aim-bar">
                                            <span class="aim-bar__track"><span class="aim-bar__fill" style="width:{{ round($w->observations / $maxObs * 100, 1) }}%"></span></span>
                                            <span class="aim-bar__n">{{ $n($w->observations) }}</span>
                                        </span>
                                    </td>
                                    <td class="aim-when" title="{{ $T::format($w->last_session_at) }}">{{ $T::human($w->last_session_at) }}</td>
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
