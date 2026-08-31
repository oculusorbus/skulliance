# The Crypt Crawl Loss Screen Bug

**Status: fixed (2026-08-31), confirmed by the user in both a normal browser and
the installed PWA.** This doc exists because getting here took multiple
sessions and, in the final push alone, five sequential "fixes" that each
solved something real without solving the actual problem. If this bug shows
any sign of coming back, read this whole file before touching the code again
— the failure mode is subtle and every earlier attempt looked correct in
isolation.

## The symptom

Sporadically — not every time, and worse on the installed PWA than in a
regular mobile/desktop browser tab — completing a Crypt Crawl delve (winning
or losing) would leave the player looking at a frozen or unresponsive board
instead of the win/loss result screen. No error, no visible failure, just
nothing happening. The player had actually won or lost; the game's own
Discord channel would post the result; CARBON would eventually land in their
balance. The screen just never showed it.

This is about as bad as a bug gets from a product standpoint: it strikes at
the exact moment a player most wants feedback (did I win?), it's silent (no
error to report), it's intermittent (hard to reproduce on demand), and it
was actively costing the user real things — at one point they lost an
attempt to record gameplay footage because the loss screen never appeared to
capture.

## Why it took so long

Two separate things made this unusually hard, and both are worth
understanding before assuming a future recurrence is the "same" bug:

1. **It has a long history predating this fix**, going back through several
   completely different architectural approaches to the same screen — AJAX,
   then a forced full-page reload ("foolproof" at the time), then back to an
   AJAX-driven local DOM swap, with multiple bugs found and fixed in each
   approach along the way (a rapid-double-tap race, `Doom` audio playing
   instead of `Death` because the old `#cc-mood` element wasn't cleared, a
   stale bfcache restore on mobile, session data leaking between guest and
   real-account runs). See `git log --oneline -- cryptcrawl.php` for the
   full trail — commits like `380c96e4`, `57ed4f2c`, `0a17a495`, `fd54f4c2`,
   `4982e8af` are all earlier rounds of "fix the loss screen," each correct
   for the bug it was fixing, none of them this bug.
2. **This session's own chain (below) kept finding real bugs that weren't
   THE bug.** Every fix in the chain was independently verified and
   independently correct — and every single one still left the actual
   symptom live, because the true cause was a layer underneath each one.
   That pattern is the actual lesson here: "I found and fixed a real
   problem in this code path" is not the same claim as "I fixed the
   symptom," and this bug punished conflating the two five times in a row.

## This session's chain, in order

Each entry: what was found, what was fixed, the commit, and — critically —
why it turned out not to be enough.

### 1. Uncaught exceptions in the payout/announce path
**Commit `e64476b5`.** Found: CARBON payout and the Discord announce ran
inline, synchronously, *before* the render step that builds the win/loss
overlay HTML. This server runs PHP 8.1, whose mysqli extension throws an
exception on any query error by default (a change from pre-8.1's "just
return false") — and nothing in this codebase's connection setup opted back
into the old behavior. The Discord-announce code for one of the two games
had only ever been tested against mocked DB objects, never the live table.
Any real-world query hiccup there would throw uncaught and fatal the whole
request before the overlay's HTML was ever generated.
**Fix**: wrapped payout + announce in try/catch.
**Why it wasn't enough**: this stops a *crash* from blocking the response.
It does nothing about the same code running *slowly* without crashing —
which is a completely different failure mode with the identical symptom.

### 2. The render itself still had to wait on the slow side effects
**Commit `d169dd27`**, prompted by the user asking directly: "are you sure
the game hides the board and shows the loss screen, *then* worries about
CARBON?" Answer at the time: no. Even crash-proofed, payout + announce still
ran inline, in the same request, before the response was sent — several DB
queries plus a Discord webhook POST, all real latency sitting between the
action finishing and the player seeing anything.
**Fix**: queued the side effects instead of running them inline, and flushed
the queue via `fastcgi_finish_request()` *after* the response — the
textbook way to split "answer the client" from "keep working" in PHP-FPM.
**Why it wasn't enough**: `fastcgi_finish_request()` requires PHP-FPM to
actually do anything. Whether this server's stack honors it wasn't verified
at the time — that assumption turned out to be wrong (see #4).

### 3. Also fixed in this pass: a platform-wide version of #1
**Commit `a170c51c`.** Same PHP-8.1-mysqli root cause as #1, but fixed
*everywhere* (`mysqli_report(MYSQLI_REPORT_OFF)` once, at connection setup)
rather than just at the two call sites already patched — prompted by a
different, unrelated staker report (Missions not loading, a dead Realms
button) that turned out to share the identical root cause. Correct and
worth keeping, but also not the loss-screen bug's actual cause.

### 4. The render step's own DB dependencies had no safety net
**Commit `9b62aaf2`**, prompted by: "It's not working... I got through a
whole game only to not get the loss screen. Make it bulletproof — I want to
see the loss screen even if CARBON/notifications fail outright." Found: even
with side effects deferred, `cryptcrawlRenderGameArea()` itself did a fresh
DB re-read (not reusing data already known from the action that just ran)
and had no fallback if *that* failed for any reason.
**Fix**: `cryptcrawlHandleAction()` now returns the run it just acted on
(already known in memory, zero extra queries). A new
`cryptcrawlMinimalGameOverHtml($run, $user_id)` builds the win/loss
confirmation from that in-memory data alone — no DB, no art, nothing that
can fail. The real render is wrapped in try/catch; on failure for a run that
just ended, it falls back to the minimal version. Verified directly with a
forced-failure test (a stand-in render that unconditionally throws) — the
guaranteed fallback correctly took over.
**Why it wasn't enough**: none of this fixes the response *reaching the
browser* in the first place. If the whole request is stuck behind slow work
regardless of what's inside it, a better fallback inside that request
doesn't help — the browser is still waiting on the same envelope.

### 5. `fastcgi_finish_request()` wasn't actually deferring anything
**Commit `b807aa4e`.** The user reported the bug again, but this time with
the decisive clue: Discord notifications *were* firing, which only happens
after the entire request — including the Discord webhook call and its own
8-second timeout — completes. That's only possible if the "deferred" work
from fix #2 was never actually separated from the response at all, meaning
`fastcgi_finish_request()` was silently a no-op on this server (most likely:
it isn't actually running PHP-FPM, or FPM's finish-request behavior isn't
enabled).
**Fix**: switched to `header('X-Accel-Buffering: no')` + explicit
`ob_end_flush()` + `flush()` — a technique already proven working in this
exact production environment (`missions.php`'s own loading spinner uses it
successfully).
**Why it wasn't enough**: the user tested again — still broken, still slow.
Leading theory: a CDN or reverse proxy sits in front of this origin server
(Cloudflare is the common case) and buffers the *entire* response before
relaying anything to the browser, completely independent of what nginx or
PHP do at the origin. No server-side flush technique can work around a
buffering layer that isn't the server.

### 6. The actual fix: stop trying to flush one request early, use two
**Commit `509ab7ca`.** Gave up on making a single request answer fast *and*
still do slow work — three attempts at that had each failed for a different
infrastructure-level reason, none of them visible from the application code.
Split it instead:

- **`ajax/cryptcrawl-action.php`** now does *only* the game logic, the save,
  and the render. No CARBON, no Discord, no queuing, no buffering headers.
  This request is fast because there is nothing slow left inside it — not
  because of a trick that flushes it early.
- **`ajax/cryptcrawl-finalize.php`** (new) does the CARBON payout + Discord
  announce for one run. It is called by a completely separate,
  fire-and-forget `fetch()` (with `keepalive: true`, so it survives the tab
  closing immediately after) from `cryptcrawl.php`'s own JS — fired only
  *after* the win/loss screen has already been synchronously swapped into
  view. The client never awaits it and doesn't care if it fails.
- **`cryptcrawlFinalizeRun($conn, $user_id, $run_id)`** (new, in `db.php`)
  does the actual work, re-fetching the run fresh from the DB by ID rather
  than trusting anything the client claims. Guarded by
  `$_SESSION['cryptcrawl_finalized_runs'][$run_id]` so a duplicate call
  (a retry, a double-fire) can never credit CARBON twice — this guard is
  the one genuinely new risk this design introduces (payout now reachable
  from more than one code path), and it was verified directly with a test:
  calling it twice for the same run credits exactly once; a different run
  still pays out normally; a mismatched user or a still-active run both
  correctly no-op.
- **`cryptcrawl.php`'s no-JS POST handler** was simplified *back* to running
  payout/announce inline before its redirect — safe there, because a real
  page navigation has its own native browser loading state; there's no
  "looks silently stuck" risk the way a JS fetch has.
- A **passive safety-net finalize call** runs on every normal page load
  (idempotent, so free on every load except the rare one where it matters)
  in case the fire-and-forget request genuinely never reached the server at
  all.

This is the one that held. Confirmed by the user in both a regular browser
and the installed PWA, loading quickly.

## Why this is "overkill" for showing a result screen — and why it's not

Splitting a single user action into two independent HTTP requests, with a
session-based idempotency guard, just to guarantee a confirmation screen
shows up, is a lot of machinery for what is conceptually "hide one div, show
another." The user's own words: *"This is seriously overkill just to
separate the UI from the backend tasks."* That's a fair read of the diff.

The overkill isn't accidental complexity, though — it's the direct result of
three simpler, less invasive fixes each failing against a constraint
(probably CDN response buffering) that's invisible from inside this
codebase and was never confirmed directly, only inferred from behavior. Two
requests instead of one is the one design that doesn't depend on knowing
that constraint at all: the first request is fast because it does less
work, full stop, regardless of what any proxy in the middle does with the
response. If a future bug looks similar again, the honest framing is: this
already IS the version that doesn't rely on server/CDN timing behavior — the
next layer down, if there is one, is probably not another timing issue.

## If this recurs

Diagnostic questions and where to look, roughly in the order the actual
saga above needed them:

1. **Is the response reaching the browser at all, and how fast?** Check the
   Network tab (or ask the reporting user for one) on the actual request to
   `ajax/cryptcrawl-action.php` specifically — its timing, its status, its
   response body. If that request itself is slow, something has crept back
   into it that shouldn't be there (check for a new call that isn't just
   game logic + save + render).
2. **Is the confirmation HTML actually in the response body?** If the
   Network tab shows a fast 200 with a body that does NOT contain
   `class="cc-result "`, the bug has moved to the render step itself — see
   `cryptcrawlRenderGameArea()` / `cryptcrawlMinimalGameOverHtml()` in
   `cryptcrawl-render.php`.
3. **Is the client-side swap actually firing?** Browser console errors on
   the page, specifically around the `fetch(...).then(...)` handler in
   `cryptcrawl.php`'s script block. A JS error earlier on the page can
   silently prevent this listener from ever being attached.
4. **Did CARBON/Discord actually complete for a run where the screen didn't
   show?** If yes, that's a *different* bug than any of the six above —
   everything above already assumes finalize is decoupled from display. A
   report of "the screen didn't show AND nothing else happened either"
   points at `ajax/cryptcrawl-action.php` itself; "the screen didn't show
   but Discord/CARBON did happen" would be genuinely new territory this doc
   doesn't cover, since the whole point of the current design is that those
   two outcomes are supposed to be structurally independent now.
5. **Ask what browser/network the player is on.** This bug was consistently
   worse on the installed PWA than a normal browser tab, and the earlier
   "Discord fired but nothing displayed" clue only came from the user
   directly comparing the two. That single piece of live evidence did more
   to actually locate the cause than any amount of code reading — get it
   again if this recurs, don't try to re-derive the cause from the code
   alone first.

## Crypt Conquest

Crypt Conquest shares the exact same rendering architecture and, as of this
writing, has never exhibited this bug. It received the crash-proofing from
fix #1/#3's pattern for consistency, but was deliberately **not** given the
two-request finalize split from fix #6 — there was no reported symptom to
justify the added complexity there, and this doc's whole point is that this
complexity should be earned by an actual recurring failure, not applied
speculatively. If Conquest ever does show this symptom, this file (and
`ajax/cryptcrawl-finalize.php` as a working template) is the starting point,
not a from-scratch investigation.
