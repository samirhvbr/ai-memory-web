# Security Policy

> This file exists for **the path GitHub recognises** — a `SECURITY.md` at the
> root is what enables the *"Report a vulnerability"* button. It **does not
> duplicate** the norm; it points at the single source.
>
> **The source is [docs/security.md](docs/security.md), binding from the first
> commit.**

## Reporting a vulnerability

A vulnerability, an exposed secret or a suspected leak: report it to
**samir@blue3.com.br**, **immediately**. Please do not open a public issue for a
security problem.

You will get an acknowledgement, and a structural fix becomes an ADR in
[docs/decisions.md](docs/decisions.md).

See [docs/security.md §11](docs/security.md#11-incident-response) for the
normative incident-response text, and
[§5](docs/security.md#5-secrets-and-configuration) for why an exposed secret is
**rotated, not reverted** — removing the line from HEAD leaves it in the history
and in every clone.

## Supported versions

The current `master` is the supported state. There is no released artefact yet
beyond the tagged versions of this repository.

## Context

**ai-memory-web is a public repository, and it was public from its first
commit** — there is no private history behind it to audit.

Two things about this app shape what a security report means here:

- **It only reads.** The `aimemory` connection is pinned with
  `PRAGMA query_only = 1` and every repository class issues `SELECT` alone. A
  finding that this app can write to `memory.sqlite` is a **critical** report.
- **It renders another product's data.** Page bodies, observation titles and
  handoff notes are written by coding agents, not by an operator. They are
  untrusted input: Markdown is rendered with HTML escaped and unsafe links
  dropped ([docs/read-only.md](docs/read-only.md)). A way to get markup or a
  script past that is a **high** report.

The panel is also expected to be reachable only from a trusted network — it
shows, in plain text, everything the agents remember about every project on the
host. See [docs/security.md](docs/security.md).
