@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="aim">
    @include('ai-memory._tabs')

    @unless($available)
        @include('ai-memory._unavailable')
    @else
        @php
            $n = fn ($v) => number_format((int) $v);
            $T = \App\Services\AiMemory\AiMemoryTime::class;

            $obs = $summary->series($observationsByDay);
            $ses = $summary->series($sessionsByDay);
            $days = array_keys($observationsByDay);
            $labelEvery = 5;
            // mm-dd, the same shape dashboard.js rebuilds when it refreshes the axis
            $fmtDay = fn ($ymd) => \Illuminate\Support\Carbon::parse($ymd)->format('m-d');

            $deltas = [
                'observations' => $summary->delta($history, 'observations', (int) $counts['observations']),
                'pages' => $summary->delta($history, 'pages', (int) $counts['pages']),
                'sessions' => $summary->delta($history, 'sessions', (int) $counts['sessions']),
            ];

            $histMetrics = ['observations' => 'Observations', 'pages' => 'Pages', 'sessions' => 'Sessions'];
            $histData = $summary->historySeries($history, array_keys($histMetrics));
            $histDates = $history->map(fn ($s) => $s->captured_on->format('Y-m-d'))->values()->all();
            $histTop = $summary->niceMax(max($histData['observations'] ?: [0]));
            $histPath = $summary->areaPath($histData['observations'], $histTop);

            $sparks = [
                'observations' => $summary->sparkline($histData['observations']),
                'pages' => $summary->sparkline($histData['pages']),
                'sessions' => $summary->sparkline($histData['sessions']),
            ];
            $hasHistory = $history->count() > 1;
        @endphp

        {{-- ── Live totals: one instrument panel, not eight identical cards ── --}}
        <section class="aim-panel" aria-label="Totals in memory">
            <div class="aim-panel__row aim-panel__row--lede">
                @php
                    $lede = [
                        ['key' => 'observations', 'label' => 'Observations', 'count' => $counts['observations'], 'route' => route('ai-memory.observations'), 'hero' => true],
                        ['key' => 'pages', 'label' => 'Pages', 'count' => $counts['pages'], 'route' => route('ai-memory.pages'), 'hero' => false],
                        ['key' => 'sessions', 'label' => 'Sessions', 'count' => $counts['sessions'], 'route' => route('ai-memory.sessions'), 'hero' => false],
                    ];
                @endphp
                @foreach($lede as $cell)
                    @php $delta = $deltas[$cell['key']]; @endphp
                    <div class="aim-cell {{ $cell['hero'] ? 'aim-cell--lede' : '' }}">
                        <div class="aim-cell__top">
                            <a class="aim-cell__link" href="{{ $cell['route'] }}">
                                <p class="aim-label">{{ $cell['label'] }}</p>
                                <p class="aim-value {{ $cell['hero'] ? 'aim-value--hero' : 'aim-value--md' }}" data-live="counts.{{ $cell['key'] }}">{{ $n($cell['count']) }}</p>
                            </a>
                            @if($hasHistory)
                                <span class="aim-spark {{ $cell['hero'] ? 'aim-spark--lede' : '' }}" aria-hidden="true">
                                    <svg viewBox="0 0 100 30" preserveAspectRatio="none"><path class="l" d="{{ $sparks[$cell['key']]['line'] }}"></path></svg>
                                    <span class="aim-spark__dot" style="top:{{ round($sparks[$cell['key']]['last_y'] / 30 * 100, 2) }}%"></span>
                                    @if($cell['hero'])
                                        <span class="aim-spark__cap">{{ $history->count() }} snapshots</span>
                                    @endif
                                </span>
                            @endif
                        </div>
                        @if($delta !== null)
                            <p class="aim-delta {{ $delta['value'] > 0 ? 'aim-delta--up' : ($delta['value'] < 0 ? 'aim-delta--down' : 'aim-delta--flat') }}"
                               data-live-delta="{{ $cell['key'] }}" data-ref="{{ (int) $cell['count'] - $delta['value'] }}">
                                <i>{{ $delta['value'] > 0 ? '▲' : ($delta['value'] < 0 ? '▼' : '•') }}</i>
                                <b>{{ $delta['value'] > 0 ? '+' : '' }}{{ $n($delta['value']) }}</b>
                                in {{ $delta['days'] }} {{ $delta['days'] === 1 ? 'day' : 'days' }}@if($cell['hero']) · <b data-live="obs.today">{{ $n($obs['today']) }}</b> today @endif
                            </p>
                        @elseif($cell['hero'])
                            <p class="aim-delta aim-delta--flat"><b data-live="obs.today">{{ $n($obs['today']) }}</b> today · no earlier snapshot to compare against</p>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="aim-panel__row aim-panel__row--facts">
                <div class="aim-cell">
                    <p class="aim-label">Workspaces</p>
                    <a class="aim-fact" href="{{ route('ai-memory.workspaces') }}">
                        <span class="aim-value aim-value--sm" data-live="counts.workspaces">{{ $n($counts['workspaces']) }}</span>
                    </a>
                </div>
                <div class="aim-cell">
                    <p class="aim-label">Projects</p>
                    <a class="aim-fact" href="{{ route('ai-memory.projects') }}">
                        <span class="aim-value aim-value--sm" data-live="counts.projects">{{ $n($counts['projects']) }}</span>
                    </a>
                </div>
                <div class="aim-cell">
                    <p class="aim-label">Embeddings</p>
                    <p class="aim-value aim-value--sm" data-live="counts.embeddings">{{ $n($counts['embeddings']) }}</p>
                    @if((int) $counts['embeddings'] === 0)
                        <p class="aim-hint">semantic index not generated yet</p>
                    @endif
                </div>
                <div class="aim-cell">
                    <p class="aim-label">Open handoffs</p>
                    <a class="aim-fact" href="{{ route('ai-memory.handoffs', ['state' => 'open']) }}">
                        <span class="aim-dot {{ (int) $counts['handoffs_open'] > 0 ? 'aim-dot--warn' : 'aim-dot--idle' }}"></span>
                        <span class="aim-value aim-value--sm" data-live="counts.handoffs_open">{{ $n($counts['handoffs_open']) }}</span>
                    </a>
                    <p class="aim-hint">{{ (int) $counts['handoffs_open'] > 0 ? 'waiting to be accepted' : 'none pending' }}</p>
                </div>
                <div class="aim-cell">
                    <p class="aim-label">Pending proposals</p>
                    <span class="aim-fact">
                        <span class="aim-dot {{ (int) $counts['proposals_pending'] > 0 ? 'aim-dot--warn' : 'aim-dot--idle' }}"></span>
                        <span class="aim-value aim-value--sm" data-live="counts.proposals_pending">{{ $n($counts['proposals_pending']) }}</span>
                    </span>
                    <p class="aim-hint">{{ (int) $counts['proposals_pending'] > 0 ? 'in the auto-improve queue' : 'nothing in the auto-improve queue' }}</p>
                </div>
            </div>
        </section>

        {{-- Provenance + "live": where each number came from, and when it arrived. --}}
        <div class="aim-source">
            <span>source: <b>{{ basename($aimemoryPath ?: 'memory.sqlite') }}</b> (read-only)</span>
            <span>in <b>{{ $aimemoryPath ? dirname($aimemoryPath) : '(path not configured)' }}</b></span>
            <span>days bucketed in <b>UTC</b></span>

            {{-- ai-memory is written by the agents, not by this app: there is no event
                 of ours to broadcast. "Live" here is short browser polling (no daemon,
                 no WebSocket) — see dashboard.js. --}}
            <span class="aim-live" data-aim-live data-url="{{ route('ai-memory.live') }}" data-every="15">
                <button type="button" class="aim-live__btn" data-aim-livetoggle aria-pressed="true"
                        title="Pause/resume the automatic refresh">
                    <span class="aim-live__dot" aria-hidden="true"></span>
                    <span data-aim-livelabel>live</span>
                </button>
                <span class="aim-live__when" data-aim-livewhen role="status" aria-live="polite"></span>
            </span>
        </div>

        {{-- ── Activity: observations and sessions sharing ONE day axis ── --}}
        <section class="card aim-card">
            <header class="aim-card__head">
                <div>
                    <h2>Activity</h2>
                    <p class="aim-sub">last {{ count($days) }} days · {{ $fmtDay($days[0]) }} → {{ $fmtDay(end($days)) }}</p>
                </div>
                <button type="button" class="btn btn--sm" data-aim-table aria-pressed="false" aria-expanded="false" aria-controls="aim-activity-table">table</button>
            </header>

            <p class="aim-serie">
                <span class="aim-serie__name"><span class="aim-key aim-key--obs"></span>Observations</span>
                <span>
                    <b data-live="obs.total">{{ $n($obs['total']) }}</b> in the period · average <b data-live="obs.avg">{{ $n($obs['avg']) }}</b>/day
                    @if($obs['peak_day']) · peak <b data-live="obs.max">{{ $n($obs['max']) }}</b> on <span data-live="obs.peak">{{ $fmtDay($obs['peak_day']) }}</span> @endif
                </span>
            </p>

            <div class="aim-plot" data-aim-plot tabindex="0" role="img"
                 data-days='@json(array_map($fmtDay, $days), JSON_HEX_APOS | JSON_HEX_QUOT)'
                 data-obs='@json(array_values($observationsByDay), JSON_HEX_APOS | JSON_HEX_QUOT)'
                 data-ses='@json(array_values($sessionsByDay), JSON_HEX_APOS | JSON_HEX_QUOT)'
                 aria-label="Observations and sessions per day over the last {{ count($days) }} days. Observations: {{ $n($obs['total']) }} in the period, {{ $n($obs['avg']) }} per day on average. Sessions: {{ $n($ses['total']) }} in the period, {{ $n($ses['avg']) }} per day on average. Use the arrow keys to walk the days, or the table button to read the numbers.">
                <div class="aim-row aim-row--obs">
                    <span class="aim-gridline" style="bottom:100%"></span>
                    <span class="aim-gridline" style="bottom:50%"></span>
                    <span class="aim-ymax" data-live="obs.top">{{ $n($obs['top']) }}</span>
                    @foreach($observationsByDay as $day => $value)
                        <div class="aim-col">
                            <span class="aim-colbar{{ $value === 0 ? ' aim-colbar--zero' : ($value === $obs['max'] ? ' aim-colbar--peak' : '') }}"
                                  @if($value > 0) style="height:{{ round($value / $obs['top'] * 100, 2) }}%" @endif></span>
                        </div>
                    @endforeach
                </div>

                <p class="aim-serie aim-serie--stack">
                    <span class="aim-serie__name"><span class="aim-key aim-key--ses"></span>Sessions</span>
                    <span>
                        <b data-live="ses.total">{{ $n($ses['total']) }}</b> in the period · average <b data-live="ses.avg">{{ $n($ses['avg']) }}</b>/day
                        @if($ses['peak_day']) · peak <b data-live="ses.max">{{ $n($ses['max']) }}</b> on <span data-live="ses.peak">{{ $fmtDay($ses['peak_day']) }}</span> @endif
                    </span>
                </p>

                <div class="aim-row aim-row--ses">
                    <span class="aim-gridline" style="bottom:100%"></span>
                    <span class="aim-ymax" data-live="ses.top">{{ $n($ses['top']) }}</span>
                    @foreach($sessionsByDay as $day => $value)
                        <div class="aim-col">
                            <span class="aim-colbar{{ $value === 0 ? ' aim-colbar--zero' : ($value === $ses['max'] ? ' aim-colbar--peak' : '') }}"
                                  @if($value > 0) style="height:{{ round($value / $ses['top'] * 100, 2) }}%" @endif></span>
                        </div>
                    @endforeach
                </div>

                <div class="aim-axis">
                    @foreach($days as $idx => $day)
                        @php
                            $isLast = $idx === count($days) - 1;
                            $show = $idx % $labelEvery === 0 || $isLast;
                            // on a narrow screen only the sparse labels survive (one in two)
                            $dense = $show && ! $isLast && $idx % ($labelEvery * 2) !== 0;
                        @endphp
                        <span class="{{ $isLast ? 'is-today' : '' }}{{ $dense ? ' is-dense' : '' }}">{{ $show ? $fmtDay($day) : '' }}</span>
                    @endforeach
                </div>

                <div class="aim-cross" data-aim-cross></div>
                <div class="aim-tip" data-aim-tip role="status" aria-live="polite"></div>
            </div>

            @if($obs['total'] === 0 && $ses['total'] === 0)
                <p class="aim-empty" style="margin-top:16px">
                    No activity in these {{ count($days) }} days — the agents opened no session and recorded no
                    observation. The totals above still hold: they are the running total of the memory.
                </p>
            @endif

            {{-- Every reading of the chart also exists as text: the tooltip is never the only way in. --}}
            <div class="aim-tablewrap" id="aim-activity-table" data-aim-tablewrap hidden>
                <table class="table">
                    <caption class="sr-only">Observations and sessions per day, most recent first</caption>
                    <thead><tr><th scope="col">Day</th><th scope="col">Observations</th><th scope="col">Sessions</th></tr></thead>
                    <tbody>
                        @foreach(array_reverse($days) as $day)
                            <tr>
                                <td>{{ $fmtDay($day) }}</td>
                                <td>{{ $n($observationsByDay[$day]) }}</td>
                                <td>{{ $n($sessionsByDay[$day] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div class="aim-grid2">
            {{-- ── DURABLE historical evolution (this app's own database — survives an ai-memory reset) ── --}}
            <section class="card aim-card aim-card--tall">
                <header class="aim-card__head">
                    <div>
                        <h2>Historical evolution</h2>
                        <p class="aim-sub">{{ $history->count() }} daily {{ $history->count() === 1 ? 'snapshot' : 'snapshots' }} · durable table</p>
                    </div>
                    @if($hasHistory)
                        <div class="aim-seg" role="group" aria-label="History metric">
                            @foreach($histMetrics as $key => $label)
                                <button type="button" data-aim-metric="{{ $key }}" aria-pressed="{{ $key === 'observations' ? 'true' : 'false' }}">{{ $label }}</button>
                            @endforeach
                        </div>
                    @endif
                </header>

                @if(! $hasHistory)
                    <p class="aim-empty">
                        @if($history->isEmpty())
                            No snapshot yet. The daily <code>aimemory:snapshot</code> job has not run —
                            run <code>php artisan aimemory:snapshot</code> to write the first one
                            (and check the Laravel scheduler on this host).
                        @else
                            Only one snapshot so far ({{ $histDates[0] }}). The curve appears from the second one on —
                            the <code>aimemory:snapshot</code> job writes one per day.
                        @endif
                    </p>
                @else
                    <div class="aim-area" data-aim-area
                         data-dates='@json($histDates, JSON_HEX_APOS | JSON_HEX_QUOT)'
                         data-series='@json($histData, JSON_HEX_APOS | JSON_HEX_QUOT)'
                         data-labels='@json($histMetrics, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)'>
                        <div class="aim-area__grid" data-aim-areagrid>
                            <span style="top:0"></span><i style="top:0" data-aim-ytop>{{ $n($histTop) }}</i>
                            <span style="top:50%"></span><i style="top:50%" data-aim-ymid>{{ $n((int) ($histTop / 2)) }}</i>
                            <span style="bottom:0"></span><i style="bottom:0" class="is-zero">0</i>
                        </div>
                        <svg viewBox="0 0 1000 220" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="aimFade" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#6366f1" stop-opacity=".26"></stop>
                                    <stop offset="100%" stop-color="#6366f1" stop-opacity="0"></stop>
                                </linearGradient>
                            </defs>
                            <path class="a" data-aim-areapath d="{{ $histPath['area'] }}"></path>
                            <path class="l" data-aim-linepath d="{{ $histPath['line'] }}"></path>
                        </svg>
                        <div class="aim-area__dot" data-aim-areadot style="left:100%;top:{{ round($histPath['last_y'] / 220 * 100, 2) }}%"></div>
                        <div class="aim-area__end" data-aim-areaend style="left:100%;top:{{ round($histPath['last_y'] / 220 * 100, 2) }}%">{{ $n(end($histData['observations'])) }}</div>
                        <div class="aim-area__hit" data-aim-areahit tabindex="0" role="img"
                             aria-label="Historical evolution of the observations, from {{ $histDates[0] }} to {{ end($histDates) }}: from {{ $n($histData['observations'][0]) }} to {{ $n(end($histData['observations'])) }}."></div>
                        <div class="aim-tip" data-aim-areatip role="status" aria-live="polite"></div>
                    </div>
                    <p class="aim-sub aim-area__foot">{{ $histDates[0] }} → {{ end($histDates) }} · the snapshots survive an ai-memory reset</p>
                @endif
            </section>

            {{-- ── Ranking: where the memory grew ── --}}
            <section class="card aim-card">
                <header class="aim-card__head">
                    <div>
                        <h2>Most active projects</h2>
                        <p class="aim-sub">by accumulated observations</p>
                    </div>
                </header>

                @if($topProjects->isEmpty())
                    <p class="aim-empty">No project in memory yet.</p>
                @else
                    @php $rankMax = max(1, (int) $topProjects->max('observations')); @endphp
                    <ul class="aim-rank">
                        @foreach($topProjects as $project)
                            <li>
                                <a href="{{ route('ai-memory.projects.show', $project->id_hex) }}">
                                    <span class="aim-rank__name">{{ $project->name }}</span>
                                    <span class="aim-rank__n">{{ $n($project->observations) }}</span>
                                    <span class="aim-rank__track"><span class="aim-rank__fill" style="width:{{ round($project->observations / $rankMax * 100, 1) }}%"></span></span>
                                    <span class="aim-rank__meta">{{ $T::human($project->last_session_at) }} · {{ $n($project->sessions) }} sessions · {{ $n($project->pages) }} pages</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <a class="aim-more" href="{{ route('ai-memory.projects') }}">see all {{ $n($counts['projects']) }} projects →</a>
                @endif
            </section>
        </div>
    @endunless
</div>
@endsection

@push('scripts')
<script defer src="{{ vasset('js/dashboard.js') }}"></script>
@endpush
