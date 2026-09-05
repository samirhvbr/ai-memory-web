@extends('layouts.app')

@section('title', 'Page')

@section('topbar-actions')
    <a href="{{ route('ai-memory.pages') }}" class="btn btn--sm"><x-icon name="arrow-left"/> Pages</a>
@endsection

@section('content')
<div class="aim">
    @include('ai-memory._tabs')

    @unless($available)
        @include('ai-memory._unavailable')
    @else
        @php
            $T = \App\Services\AiMemory\AiMemoryTime::class;
            $tierChip = ['working' => 'aim-chip--warn', 'episodic' => 'aim-chip--accent', 'semantic' => 'aim-chip--ok'];
            $frontmatter = trim((string) $page->frontmatter_json);
            $hasFrontmatter = $frontmatter !== '' && $frontmatter !== '{}';
        @endphp

        <header class="aim-hero">
            <div>
                <h1>{{ $page->title }}</h1>
                <div class="aim-hero__chips">
                    <span class="aim-chip {{ $tierChip[$page->tier] ?? '' }}">{{ $page->tier }}</span>
                    @if($page->is_latest)
                        <span class="aim-chip aim-chip--ok">current version</span>
                    @else
                        <span class="aim-chip aim-chip--warn">older version</span>
                    @endif
                    @if($page->pinned)<span class="aim-chip aim-chip--accent">pinned</span>@endif
                </div>
                <code class="aim-hero__path">{{ $page->path }}</code>
            </div>
        </header>

        @unless($page->is_latest)
            <div class="alert alert--warn">
                <x-icon name="history"/>
                You are reading an older version of this page. The current one is at the end of the history, on the right.
            </div>
        @endunless

        <div class="aim-grid2" style="grid-template-columns:minmax(0,1fr) 320px">
            <section class="card aim-card">
                @if(trim((string) $page->body) === '')
                    <p class="aim-empty">This page has no body.</p>
                @else
                    {{-- The body is Markdown written by an agent: untrusted input. Raw HTML
                         inside it is escaped and unsafe links are dropped, so a page can
                         never inject markup into this panel. --}}
                    <div class="aim-prose">
                        {!! \Illuminate\Support\Str::markdown($page->body, ['html_input' => 'escape', 'allow_unsafe_links' => false]) !!}
                    </div>
                @endif
            </section>

            <div>
                <section class="card aim-card">
                    <header class="aim-card__head"><div><h2>Metadata</h2></div></header>
                    <dl class="aim-facts">
                        <div><dt>Project</dt><dd>{{ $page->project }}</dd></div>
                        <div><dt>Workspace</dt><dd>{{ $page->workspace }}</dd></div>
                        <div><dt>Author</dt><dd class="aim-mono">{{ $page->author ?? '—' }}</dd></div>
                        <div><dt>Created</dt><dd class="aim-mono">{{ $T::format($page->created_at) }}</dd></div>
                        <div><dt>Updated</dt><dd class="aim-mono">{{ $T::format($page->updated_at) }}</dd></div>
                    </dl>

                    @if($hasFrontmatter)
                        <p class="aim-sub" style="margin:16px 0 6px">Frontmatter</p>
                        <pre class="aim-prose" style="margin:0"><code>{{ $frontmatter }}</code></pre>
                    @endif
                </section>

                <section class="card aim-card">
                    <header class="aim-card__head">
                        <div>
                            <h2>History</h2>
                            <p class="aim-sub">{{ count($history) }} {{ count($history) === 1 ? 'version' : 'versions' }}</p>
                        </div>
                    </header>

                    @if(count($history) <= 1)
                        <p class="aim-empty">First and only version — it has not been rewritten yet.</p>
                    @else
                        <ul class="aim-tl">
                            @foreach($history as $v)
                                <li>
                                    <div class="aim-tl__time">{{ $T::format($v->created_at) }}</div>
                                    <div class="aim-tl__row">
                                        @if($v->id_hex === $page->id_hex)
                                            <span class="aim-strong" style="font-size:.88rem">this version</span>
                                        @else
                                            <a href="{{ route('ai-memory.pages.show', $v->id_hex) }}">open</a>
                                        @endif
                                        @if($v->is_latest)<span class="aim-chip aim-chip--ok">current</span>@endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            </div>
        </div>
    @endunless
</div>
@endsection
