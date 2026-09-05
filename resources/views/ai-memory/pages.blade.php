@extends('layouts.app')

@section('title', 'Pages')

@section('content')
<div class="aim">
    @include('ai-memory._tabs')

    @unless($available)
        @include('ai-memory._unavailable')
    @else
        @php
            $T = \App\Services\AiMemory\AiMemoryTime::class;
            $tierChip = ['working' => 'aim-chip--warn', 'episodic' => 'aim-chip--accent', 'semantic' => 'aim-chip--ok'];
            $projectName = collect($projectOptions)->firstWhere('id_hex', $project)?->name;
        @endphp

        <section class="card aim-card">
            <header class="aim-card__head">
                <div>
                    <h2>Pages</h2>
                    <p class="aim-sub">
                        {{ number_format($pages->total()) }} at their current version
                        @if($projectName) · project {{ $projectName }} @endif
                    </p>
                </div>
            </header>

            <form method="GET" action="{{ route('ai-memory.pages') }}" class="aim-filters">
                <div class="form-row">
                    <label for="f-project">Project</label>
                    <select name="project" id="f-project" onchange="this.form.submit()">
                        <option value="">All</option>
                        @foreach($projectOptions as $opt)
                            <option value="{{ $opt->id_hex }}" @selected($project === $opt->id_hex)>{{ $opt->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="aim-filters__go">
                    <button type="submit" class="btn btn--primary">Filter</button>
                    @if($project)<a href="{{ route('ai-memory.pages') }}" class="btn">Clear</a>@endif
                </div>
            </form>

            @if($pages->isEmpty())
                <div class="aim-blank">
                    <x-icon name="file"/>
                    <p>{{ $project ? 'No page in this project.' : 'No consolidated page yet.' }}</p>
                    <p>Pages are the knowledge that has already been consolidated — they come from <code>memory_write_page</code> or from the automatic consolidation of sessions.</p>
                </div>
            @else
                <div class="aim-scroll">
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col">Title</th>
                                <th scope="col">Path</th>
                                <th scope="col">Tier</th>
                                <th scope="col">Project</th>
                                <th scope="col">Updated</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pages as $p)
                                <tr>
                                    <td>
                                        @if($p->pinned)
                                            <span title="Pinned" style="color:var(--warn)"><x-icon name="pin" style="width:.85em;height:.85em;margin-right:4px"/></span>
                                        @endif
                                        <a href="{{ route('ai-memory.pages.show', $p->id_hex) }}" class="aim-strong">{{ $p->title }}</a>
                                    </td>
                                    <td><span class="aim-path" title="{{ $p->path }}">{{ $p->path }}</span></td>
                                    <td><span class="aim-chip {{ $tierChip[$p->tier] ?? '' }}">{{ $p->tier }}</span></td>
                                    <td class="aim-mono">{{ $p->project }}</td>
                                    <td class="aim-when" title="{{ $T::format($p->updated_at) }}">{{ $T::human($p->updated_at) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{ $pages->links() }}
            @endif
        </section>
    @endunless
</div>
@endsection
