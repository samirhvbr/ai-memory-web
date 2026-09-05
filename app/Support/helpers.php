<?php

if (! function_exists('vasset')) {
    /**
     * Asset URL with a cache-busting query string.
     *
     * There is no build step in this app — the CSS and the one JS file are
     * served straight from public/ — so nothing hashes their filenames. Without
     * a version in the URL, a deploy that changes a stylesheet reaches every
     * browser that already has the old one only when its cache expires.
     *
     * The version is the file's mtime: it changes exactly when the file does,
     * needs no manifest, and costs one `stat` per asset per request (memoised
     * here, so once per request in practice). A missing file falls back to `0`
     * rather than throwing — a broken URL is easier to diagnose than a 500 on
     * every page.
     */
    function vasset(string $path): string
    {
        static $versions = [];

        if (! array_key_exists($path, $versions)) {
            $full = public_path($path);
            $versions[$path] = is_file($full) ? (string) filemtime($full) : '0';
        }

        return asset($path).'?v='.$versions[$path];
    }
}
