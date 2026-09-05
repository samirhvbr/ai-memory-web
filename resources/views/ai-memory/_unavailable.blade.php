{{-- Degradation notice: rendered by EVERY screen when the ai-memory SQLite
     index is not reachable. It is the in-UI explanation of "why it stopped".
     `$unavailableReason` comes from AiMemoryDatabase::unavailableReason() and
     names the actual failure (permission, path, driver, schema) — see
     docs/permissions.md. --}}
<div class="card" style="border-color:rgba(245,158,11,.35);background:rgba(245,158,11,.05)">
    <h2 style="color:#fcd34d;display:flex;align-items:center;gap:10px">
        <x-icon name="warning"/> ai-memory is not reachable on this host
    </h2>

    <p style="color:#e2e8f0;line-height:1.6">
        This panel reads, <b>read-only</b>, the <b>ai-memory SQLite index</b> — the long-term
        memory of the coding agents. That file belongs to the <code>ai-memory</code> process and is
        read <b>straight off this host's filesystem</b>. Right now it is <b>not reachable</b>, so
        there is nothing to show.
    </p>

    @if(! empty($unavailableReason))
        <p class="card__sub" style="margin:14px 0 6px">What failed just now:</p>
        <div style="overflow-x:auto;line-height:1.6;color:#fde68a">{{ $unavailableReason }}</div>
    @endif

    <p class="card__sub" style="margin:14px 0 6px">Configured path (<code>AI_MEMORY_SQLITE_PATH</code>):</p>
    <div style="overflow-x:auto"><code style="color:#a5b4fc">{{ $aimemoryPath ?: '(empty)' }}</code></div>

    <p class="card__sub" style="margin:18px 0 6px">Likely causes:</p>
    <ul class="list" style="max-width:760px">
        <li><span>The web server user <b>has no write permission on the directory</b> holding the database.
            That is not a typo: the database is in <b>WAL</b> mode, and any reader has to be able to create
            the <code>-shm</code>/<code>-wal</code> files whenever ai-memory is not holding them open.</span></li>
        <li><span>ai-memory <b>changed its layout on upgrade</b> (2.x installs under
            <code>/opt/ai-memory/data/db</code>; 1.x used the Docker volume <code>ai-memory-data</code>)
            and <code>AI_MEMORY_SQLITE_PATH</code> still points at the old path.</span></li>
        <li><span>This app <b>is no longer on the host</b> where ai-memory runs — the file only exists there.</span></li>
        <li><span>The PHP extension <code>pdo_sqlite</code> is not installed in this environment.</span></li>
    </ul>

    <p class="card__sub" style="margin-top:16px">
        The host coupling is <b>expected</b>; a 500 was not. The diagnosis walkthrough and the permission
        commands are in <code>docs/permissions.md</code>.
    </p>
</div>
