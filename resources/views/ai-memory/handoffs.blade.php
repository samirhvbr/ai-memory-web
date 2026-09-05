@extends('layouts.app')

@section('title', 'Handoffs')

@section('content')
<div class="aim">
    @include('ai-memory._tabs')

    @unless($available)
        @include('ai-memory._unavailable')
    @else
        @php
            $T = \App\Services\AiMemory\AiMemoryTime::class;
            $stateChip = ['open' => 'aim-chip--warn', 'accepted' => 'aim-chip--ok', 'expired' => ''];
        @endphp

        <section class="card aim-card">
            <header class="aim-card__head">
                <div>
                    <h2>Handoffs</h2>
                    <p class="aim-sub">{{ number_format($handoffs->total()) }} batons passed between sessions</p>
                </div>
            </header>

            <form method="GET" action="{{ route('ai-memory.handoffs') }}" class="aim-filters">
                <div class="form-row">
                    <label for="f-state">State</label>
                    <select name="state" id="f-state" onchange="this.form.submit()">
                        <option value="">All</option>
                        @foreach(['open' => 'Open', 'accepted' => 'Accepted', 'expired' => 'Expired'] as $v => $lbl)
                            <option value="{{ $v }}" @selected($state === $v)>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="aim-filters__go">
                    <button type="submit" class="btn btn--primary">Filter</button>
                    @if($state)<a href="{{ route('ai-memory.handoffs') }}" class="btn">Clear</a>@endif
                </div>
            </form>

            @if($handoffs->isEmpty())
                <div class="aim-blank">
                    <x-icon name="swap"/>
                    <p>{{ $state ? 'No handoff in this state.' : 'No handoff recorded yet.' }}</p>
                    <p>A handoff is the note one session leaves for the next: a summary, the open questions and the next steps.</p>
                </div>
            @else
                <div class="aim-scroll">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Baton</th>
                                <th scope="col">State</th>
                                <th scope="col">Project</th>
                                <th scope="col">Open</th>
                                <th scope="col">Next</th>
                                <th scope="col">Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($handoffs as $h)
                                <tr>
                                    <td>
                                        <a href="{{ route('ai-memory.handoffs.show', $h->id_hex) }}">
                                            <span class="aim-chip aim-chip--accent">{{ $h->from_agent }}</span>
                                            <span class="aim-mono" style="margin:0 4px">→</span>
                                            <span class="aim-chip">{{ $h->to_agent ?: 'any agent' }}</span>
                                        </a>
                                    </td>
                                    <td><span class="aim-chip {{ $stateChip[$h->state] ?? '' }}">{{ $h->state }}</span></td>
                                    <td class="aim-mono">{{ $h->project }}</td>
                                    <td class="aim-mono">{{ $h->open_questions }}</td>
                                    <td class="aim-mono">{{ $h->next_steps }}</td>
                                    <td class="aim-when" title="{{ $T::format($h->created_at) }}">{{ $T::human($h->created_at) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $handoffs->links() }}
            @endif
        </section>
    @endunless
</div>
@endsection
