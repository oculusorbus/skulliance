# The Crypt Crawl Loss Screen Bug

**Status: root cause found and fixed 2026-08-31 (commit `54f1b711`), after being
reproduced deterministically in a live browser for the first time.**

This bug survived **seven** prior "fixes" across several days. Every one of them
found and fixed something genuinely real. None of them were this bug. If it ever
appears to come back, read this file before touching anything — especially the
"If this recurs" checklist at the bottom, and especially the lesson in "What
actually went wrong in the debugging."

---

## The symptom

Sporadically, finishing a Crypt Crawl delve (usually dying) would not show the
win/loss result screen. Instead the player would be looking at a live-looking
game board — often a completely fresh one (HP 20/20, 0 crypts cleared). The
player's own description: *"it seemed like it was almost trying to restart the
game before the loss screen rendered."*

Two properties turned out to be the decisive clues, and both were reported by
the user well before they were understood:

1. **"Once it happens, it seems to happen over and over."** Not random —
   *sticky*. That points at persistent state, not timing.
2. **"It seemed like it was trying to restart the game."** Not a blank screen,
   not a hang — a *different, fresh game board*.

Meanwhile CARBON was credited and the Discord result posted correctly, which
repeatedly (and wrongly) pointed the investigation at server timing.

---

## THE ACTUAL ROOT CAUSE

`cryptcrawlStartRun()` inserted a new `status='active'` row **without checking
whether the player already had an active run.** One player could therefore hold
several simultaneously-active runs.

This was trivially easy to trigger by accident: `cryptcrawlgame.php`'s
"Start Delve" button POSTs `start_run` unconditionally, and that page **is the
Play-menu nav link**. So simply navigating to Crypt Crawl through the site menu
while a delve was already in progress silently created a duplicate active row.

`cryptcrawlGetActiveRun()` returns the **newest** active row
(`ORDER BY id DESC LIMIT 1`), so the older row just became invisible — until the
newer run **ended**. At that moment:

1. The run the player was actually playing flips to `won`/`lost`.
2. `cryptcrawlRenderGameArea()` calls `cryptcrawlGetActiveRun()`, which now
   finds the **orphaned older run**, still `active`.
3. `state` is therefore computed as `'active'`, not `'game_over'`.
4. It renders a **live board** — the orphan's board, usually untouched at
   HP 20/20 — instead of the result screen.

That is the entire bug. It recurred forever afterward ("over and over") because
the orphan stayed `active` indefinitely, hijacking every subsequent death.

**And this is why every server-side timing fix changed nothing: the server was
never slow or broken here. It was faithfully rendering exactly the state it was
asked about — the question being asked was just wrong.**

### The deterministic repro (now a regression test)

1. Start run A. Damage it so its HP is distinguishable (e.g. HP 10/20).
2. Navigate to `cryptcrawlgame.php` and click **Start Delve** → creates run B at
   HP 20/20 while A is still active.
3. End run B (die, or Abandon).
4. **Observed: no loss screen. A playable HP 10/20 board appears instead.**

Covered by `cc_dupe_run_test.php` (the guard, the normal first-delve/Delve-Again
path, and the guest path).

### The fix

- **`cryptcrawlStartRun()` (db.php)** — if an active run exists, *resume it*
  (return its id) instead of inserting a duplicate. Resume rather than replace:
  an in-progress delve is real player progress and "Start Delve" is an easily
  mis-clicked nav link, so silently discarding it — or recording an unearned
  loss for it — would be the wrong trade. **Abandon Run** remains the explicit
  way to end a delve. Guests are unaffected (one session slot, cannot duplicate).
- **`ajax/cryptcrawl-action.php`** — when a request *ends* a delve, render that
  delve's result directly instead of going through
  `cryptcrawlRenderGameArea()`, which decides what to show by asking "does this
  player have any active run?" That is the wrong question at that moment. This
  also **heals accounts that already accumulated orphaned rows** before the
  guard existed, without rewriting run history or inventing losses.
- **`cryptcrawl.php`** — a 700ms `pointer-events` guard on the freshly revealed
  result overlay. Secondary/defence-in-depth, **not** the cause: every ordinary
  action already got a 400ms tap guard via `lockGameAreaBriefly()`, while the
  game-ending path returned early with none, leaving "Delve Again" instantly
  live under the player's finger.

### Known data cleanup (optional, needs a human)

Accounts that hit this before the guard existed may still hold orphaned
`active` rows. They are harmless now — the render fix means they no longer
hijack a result screen, and they'll simply be resumed the next time that player
starts a delve. If you'd rather clear them out, inspect first:

```sql
SELECT user_id, COUNT(*) AS active_runs
FROM cryptcrawls WHERE status = 'active'
GROUP BY user_id HAVING active_runs > 1;
```

Deciding what to do with them is a judgement call with leaderboard
consequences (marking them `lost` adds losses the player didn't really earn),
which is why nothing does it automatically.

---

## What actually went wrong in the debugging

This is the part worth keeping. Seven fixes shipped before the real one, and the
failure was not a shortage of effort or care — it was **method**:

- **Reasoning from code instead of reproducing.** Fixes #1–#7 were all derived
  by reading code and forming a plausible story. The bug was found within
  minutes of actually driving the live game in a browser and instrumenting it.
- **Confirmation without disconfirmation.** Each fix was verified to *work*
  (tests passed, the mechanism was sound) but never tested against a live
  reproduction of the actual symptom, so "I fixed a real thing" kept getting
  reported as "I fixed the thing."
- **An unverified infrastructure theory hardened into fact.** Fix #6's design,
  and the previous version of this document, were built on the belief that a
  CDN was buffering responses. **That was never true.** The live response
  headers are `Server: Apache` with *no* CDN headers whatsoever
  (`cf-cache-status`, `via`, `x-cache` all absent).
- **The user's own observations were the highest-value evidence available and
  were under-weighted.** "It happens over and over" (⇒ persistent state) and
  "it tried to restart the game" (⇒ a *different run's board*) between them
  describe the root cause almost exactly, and predate the fix that found it.

---

## The seven earlier fixes

All still in the codebase; all still correct on their own terms. Listed so
nobody re-litigates them.

| # | Commit | What it fixed | Why it wasn't the bug |
|---|---|---|---|
| 1 | `e64476b5` | Wrapped CARBON payout + Discord announce in try/catch. PHP 8.1's mysqli throws on query errors; this codebase's guards all assume the pre-8.1 "return false". | Stops a *crash* blocking the response; the response was never crashing. |
| 2 | `d169dd27` | Queued payout/announce and flushed them after the response via `fastcgi_finish_request()`. | Almost certainly a no-op: this is Apache/mod_php (`X-Powered-By: PHP/8.1.34`, `Server: Apache`), where that function doesn't exist. |
| 3 | `a170c51c` | `mysqli_report(MYSQLI_REPORT_OFF)` platform-wide — same PHP 8.1 issue as #1, found via an unrelated staker report (Missions/Realms). | Genuinely valuable, unrelated to this bug. |
| 4 | `9b62aaf2` | `cryptcrawlHandleAction()` returns the ended run; added `cryptcrawlMinimalGameOverHtml()` as a zero-dependency fallback render. | Good hardening — and it became load-bearing for the real fix. But it only ran when the render *threw*, and the render wasn't throwing. |
| 5 | `b807aa4e` | Added `X-Accel-Buffering: no` + explicit `flush()`. | **`X-Accel-Buffering` is an nginx header. This server is Apache. It did nothing.** |
| 6 | `509ab7ca` | Split CARBON/Discord into a separate fire-and-forget request (`ajax/cryptcrawl-finalize.php`). | Sound architecture, worth keeping — the action request really is fast now (measured 99–105ms). But the response was already arriving fine. |
| 7 | `2cf0975b` | Removed a page-load "safety net" added by #6 that re-ran finalize on *every* page load behind a `$_SESSION` guard — which a fresh login resets, so it re-ran a full payout + Discord POST on every load. | A real bug introduced by #6 and correctly removed, but not the loss-screen bug. |

---

## If this recurs

Do these **in order**. Do not start by reading code.

1. **Reproduce it live first.** Drive the actual game in a browser. Instrument
   before playing:
   - wrap `window.fetch` to log URL / status / duration / whether the response
     contains `class="cc-result "`;
   - log every `submit` (capture phase) with its `action` value;
   - persist the log to `sessionStorage` so it **survives a navigation**.
2. **Check for duplicate active runs — this bug's signature.** If the player
   sees a board instead of a result, check whether it's *a different run's*
   board (unexpected HP / crypts-cleared is the tell), then run the
   `SELECT ... HAVING active_runs > 1` query above.
3. **Is the confirmation HTML in the response?** A fast 200 whose body lacks
   `class="cc-result "` means the server chose the wrong state — look at
   `cryptcrawlRenderGameArea()`'s active-vs-recent decision, not at timing.
4. **Did a navigation happen?** `performance.getEntriesByType('navigation')[0].type`
   plus a fresh document (JS state wiped) means something submitted a form. A
   brand-new HP 20/20 / 0-crypts board means specifically a `start_run`.
5. **Only then consider timing.** And if you do: this is **Apache, no CDN**.
   `fastcgi_finish_request()` and `X-Accel-Buffering` are both no-ops here.
   Don't rebuild fixes #2 or #5.

---

## Crypt Conquest

Crypt Conquest shares the rendering architecture and has never exhibited this
bug — **and now there's a concrete reason why**, not just luck:
`cryptconquestStartRun()` should be checked against the same duplicate-active-run
guard. Conquest's own marketing page has the same unconditional-`start_run` CTA
shape, so it is plausibly exposed to the identical failure and simply hasn't
been hit yet. That check is the single highest-value follow-up from this whole
saga.
