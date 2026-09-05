# The "live" dashboard — polling, on purpose

> **Status:** `ACTIVE` · Why there is no WebSocket here, and what the front end
> actually does.

## 1. Why not broadcasting

`memory.sqlite` is written by **the agents**, through the ai-memory service —
not by this app. There is no event of ours to broadcast. A WebSocket layer
(Reverb, or anything else) would need a daemon on the host whose only job would
be to *poll the file* and re-emit what it found. That is one more thing to keep
alive in exchange for nothing.

So "real time" here is **short browser polling**, which needs no server-side
process at all.

## 2. What the browser does

`public/js/dashboard.js` calls `GET /live` — same session, same middleware as
the panel — every 15 seconds and swaps **only the values**: the numbers, the bar
heights, the axis ceiling, the legend and the equivalent table. No node is
recreated, so nothing flickers and the layout never jumps.

| Behaviour | Why |
|---|---|
| **A hidden tab does not poll** (`visibilitychange`), and refreshes on return | a background tab burning a request every 15s for nobody |
| **Pausable**, and the choice is kept in `localStorage` | reading a fixed number while it changes under you is worse than stale |
| **Repeated errors back off** exponentially, up to 2 minutes; the dot turns red | a broken backend must not be hammered, and the user must be told |
| **During a refetch the previous drawing stays up, dimmed** | a skeleton would be a layout jump every 15 seconds |
| **`aim-refetching` respects `prefers-reduced-motion`** | the dimming is animation |

The interval is `data-every` (seconds) on the `[data-aim-live]` element.

## 3. The endpoint

`AiMemoryController::live()` returns the same numbers the screen was rendered
with, as JSON. It is behind the same `auth` middleware as everything else.

It never 500s either: if the database went away between two polls it answers
`{"available": false}`, the dot turns red, and the page that is already on
screen stays readable. Polled every 15s, an exception here would otherwise fill
the log four times a minute.

## 4. Nothing is JS-only

The charts arrive **already drawn from the server** — the bars are `<span>`s
with a `height` percentage, and the history is an SVG path computed in
`DashboardSummary`. With JavaScript off, the dashboard still reads correctly;
what is lost is the crosshair, the tooltip, the metric switch and the live
refresh.

And every value the tooltip can show also exists as text: the **table** button
reveals a real `<table>` of the same series, the plot is focusable and walks day
by day with the arrow keys (`Home`/`End`/`Escape`), and the `aria-label` states
the totals, the averages and how to read it.

## 5. One duplicated function, deliberately

`niceMax()` exists twice: in `DashboardSummary` (PHP, server render) and in
`dashboard.js` (JS, live refresh). They must agree, or the axis ceiling would
jump on the first poll. It is eight lines of arithmetic; the alternative —
shipping the ceiling in the JSON, or an extra round trip — costs more than the
duplication. If you change one, change the other.
