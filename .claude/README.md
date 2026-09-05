# Agent posture — ai-memory-web

> **Status:** `ACTIVE` · What an agent may run here without asking, and why.
> Granting a permission is the owner's act: it is written here with its reason
> and how to revert it, never applied silently.

## Allowed without asking

Read-only inspection and the checks that gate a commit: `git pull` / `status` /
`diff` / `log`, `php -l`, `route:list`, `view:cache` and `view:clear` (which is
how Blade syntax is validated in a repo with no browser), `php artisan test`,
and Pint.

They are allowed because each of them is either read-only or writes nothing
outside `storage/framework/views`, and because an agent that has to ask before
running the test suite stops running it.

## Denied on purpose

| Denied | Why |
|---|---|
| `Read(./.env)` | It holds `APP_KEY` and any database password. The **example** file documents every variable; the real one is never needed to reason about the code |
| `Bash(php artisan aimemory:user:*)` | It creates or resets a login for the panel. Creating an account is an owner's act, not an agent's — and the password would land in the transcript |

## Not listed, therefore asked for

Everything else, including `git commit` and `git push`. Committing **is** the
agent's job here ([CLAUDE.md](../CLAUDE.md#commits--you-commit-and-nothing-is-delivered-until-you-have)),
but it is a write to shared history and the prompt is the moment a human can
still say no.

## Reverting

Delete the line from `.claude/settings.json`. There is no cache and no
regeneration step: the file is read at session start.
