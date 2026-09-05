@extends('layouts.app')

@section('title', 'Search')

@section('content')
<div class="aim">
    @include('ai-memory._tabs')

    @unless($available)
        @include('ai-memory._unavailable')
    @else
        @php $tierChip = ['working' => 'aim-chip--warn', 'episodic' => 'aim-chip--accent', 'semantic' => 'aim-chip--ok']; @endphp

        <section class="card aim-card">
            <header class="aim-card__head">
                <div>
                    <h2>Search the knowledge</h2>
                    <p class="aim-sub">FTS5 index · title + body of the consolidated pages</p>
                </div>
            </header>

            <form method="GET" action="{{ route('ai-memory.search') }}" class="aim-filters aim-filters--search">
                <div class="form-row">
                    <label for="q">Search</label>
                    <input type="search" name="q" id="q" value="{{ $q }}" placeholder="e.g. oauth authentication" autofocus autocomplete="off" spellcheck="false">
                </div>
                <div class="aim-filters__go">
                    <button type="submit" class="btn btn--primary">Search</button>
                    @if($q !== '')<a href="{{ route('ai-memory.search') }}" class="btn">Clear</a>@endif
                </div>
            </form>

            @if($q === '')
                <div class="aim-blank">
                    <x-icon name="search"/>
                    <p>Look for a term that appears in the text of the pages.</p>
                    <p>The search is literal (FTS5): it matches words in the title and the body — it does not understand synonyms. Try <code>deploy</code>, <code>sqlite wal</code>, <code>"error 500"</code>.</p>
                </div>
            @elseif(empty($results))
                <div class="aim-blank">
                    <x-icon name="question"/>
                    <p>No result for <b>{{ $q }}</b>.</p>
                    <p>Because the index is literal, terms that do not appear in the text return nothing. Try a shorter word, or search for the file name / path.</p>
                </div>
            @else
                <p class="aim-sub" style="margin:0 0 4px">
                    {{ count($results) }} {{ count($results) === 1 ? 'result' : 'results' }} for “{{ $q }}”
                </p>

                @foreach($results as $r)
                    <article class="aim-hit">
                        <div class="aim-hit__top">
                            <a href="{{ route('ai-memory.pages.show', $r->id_hex) }}" class="aim-hit__title">{{ $r->title }}</a>
                            <span class="aim-chip {{ $tierChip[$r->tier] ?? '' }}">{{ $r->tier }}</span>
                            <span class="aim-mono">{{ $r->project }}</span>
                        </div>
                        <span class="aim-path" title="{{ $r->path }}">{{ $r->path }}</span>
                        {{-- ai-memory returns the snippet with <<< >>> around the match; we
                             escape the text FIRST and only then swap the sentinels for <mark>. --}}
                        <p class="aim-hit__snip">{!! str_replace(['&lt;&lt;&lt;', '&gt;&gt;&gt;'], ['<mark>', '</mark>'], e($r->snippet)) !!}</p>
                    </article>
                @endforeach
            @endif
        </section>
    @endunless
</div>
@endsection
