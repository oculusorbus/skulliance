# DHC Bosses: crew resurrection

**Status: built 2026-11-11.** `dhcb_rez()` in `dhcbosses-lib.php`, the `'rez'`
timeline event in `dhcarena.php`, pinned by `dhcbosses-rez-harness.php` (15
mutations). Preview at **Admin > Boss Rez**; the cue tuner is **Admin > Boss Rez
Sound**.

**ONE MIGRATION, RUN IT BY HAND:**

```sql
ALTER TABLE dhc_boss_runs ADD COLUMN waves_cleared TINYINT NOT NULL DEFAULT 0;
```

Optional at runtime -- the settle retries without the column if it is missing,
because losing a wave count is far better than losing a settle, which would
leave the run open, the Crew unbenched and the damage unreported. Until it is
run, resurrections work and go uncounted.

Still in `skullpaper/`'s sibling rather than in it: move this page there once
the Skull Paper entry for DHC Bosses is updated.

## The report

> "What if when u defeat a boss its not the end of the game and the next ones
> load up? If i go ahead with this game the max points il get is 902 and
> therefore drastically bringing down my average on the leaderboard... or
> there a bonus points to final blows to a boss?"

He is right, and the mechanism is specific. The ladder
(`dhcb_ladder()`, `dhcbosses-lib.php`) ranks on

```
SUM(dealt) / SUM(CASE WHEN dealt > 0 THEN crew_size ELSE 0 END)
```

damage per Fighter. `crew_size` is fixed when the run starts; `dealt` is capped
by whatever HP is left on bars that are **shared across the whole player base**.
So 902 was not a cap on the game, it was what was standing when he arrived. His
whole crew went into the denominator; the numerator was capped by the boss.

He is penalised for arriving at a worn-down bar, and penalised again for being
the one who finishes it.

This class of bug was already found once. The comment above that query describes
runs where "everyone still out there when the crew dies commits against empty
bars -- added three to the bottom and nothing to the top", and the fix was to
exclude runs that dealt **exactly zero**. A run that deals *some* damage before
the bars empty is still charged for the full crew. That is the remaining hole.

## What was rejected, and why

### Fix the denominator (mine, wrong)

Count only Fighters who dealt damage or fell, dropping the ones left standing
with nothing to hit. Damage is attributed per Fighter
(`$b[$side][$fi]['dealt']` in `dhcarena-engine.php`), so this is implementable.

**It cannot work.** The owner found the hole: if the crew is small and the boss
is nearly dead, every Fighter lands something before it dies, the denominator is
full, and the numerator is still capped. *No denominator adjustment repairs a
capped numerator.* The fix was on the wrong side of the division.

### Leave truncated runs off the ladder (mine, incomplete)

A run against a bar that died before the crew was spent is not a measurement of
that crew, so do not score it.

**It fails on incentives**, which the owner caught immediately: "if a player
knows their score will be excluded and they get nothing in return, why bother
sweeping a boss crew. Let someone else do it and then beat on the fresh crew to
improve your ladder?" Exactly so. The dominant strategy becomes never landing
the kill, and the last sliver of HP sits there while everyone waits.

### Bank an extra highest-rarity trait for the sweep (owner's, mostly already shipped)

**`placement_1` is already the table everyone rolls on.** `dhcb_tier_table()`
returns it the moment the first crew falls in a week, and the flag it reads
(`downed`) only ever rises. So an "extra trait at highest rarity" is a second
roll on a table the player already has, not an upgrade.

And quantity is already the lever, with a stated cap:

> "SO THE LEVER IS QUANTITY, NOT RARITY. A tier above placement_1 would break a
> ceiling eight other games share, and the trait config is explicit that
> guaranteeing mythics on a clearable boss would mint more in a fortnight than
> exist. A round fought against wave 2 banks two traits, wave 3 banks three, and
> that is where it stops. CAPPED AT THREE."

Extending past that cap trades a principle the owner set during the Portal
design -- "it would begin skewing the collection with a greater prevalence of
rare traits" -- for a problem resurrection solves for free. `placement_1` is 8%
mythic and 22% legendary; at up to three kills a wave on the simulated wave
curve of `1 1 1 1 1 2 4`, that is roughly a mythic a week minted for an event
that is substantially timing.

### A CARBON bounty for the sweep (mine, survives but unnecessary)

Workable -- `DHCB_CARBON` is 50,000 a week already -- and bounty-sniping is not
the problem rank-sniping is, since it just means the chore gets done. But it is
a patch stack: an exclusion rule, a bounty, a bounty size to tune, and copy
explaining both.

### Per-boss resurrection (mine, actively bad)

Resurrecting individual bosses at the next tier while their crewmates live.
Breaks wave-as-a-unit, which the schema key `(day, wave, boss_key)`,
`dhcb_spawn()`'s `rows >= $n` check and `MAX(wave)` are all built on. Do not.

## The design

**When the whole boss crew falls, the next wave stands up in its place and the
run continues.** The run ends only when the player's crew falls.

This fixes the cause rather than the metric. Every run becomes a complete
measurement, so there is nothing to exclude, nothing to normalise, and nothing
to compensate. It also fixes the *more common* truncation nobody had named: your
run already ends when **another** crew finishes the wave mid-battle (the board
syncs their kill onto your screen and logs "fell to another Crew"). Resurrection
catches that identically -- your board picks up the new wave and you keep going.

And the incentive inverts without being bought. The crew that sweeps is already
mid-run, Fighters standing, when a fresh full-HP wave appears in front of them.
That is the best position on the board. Free-riding now means arriving *after*
the good part.

### What is already built

More than expected. The board is **live-synced to the shared bars every move**,
not reconciled at the end:

- Damage commits per move under `FOR UPDATE`.
- A boss dying to another crew mid-run already works and already logs.
- Concurrent chip damage already surfaces ("Another Crew is on X").
- `INSERT IGNORE` on `(day, wave, boss_key)` already handles two runs raising
  the same wave in the same second.
- Each new wave's HP is re-budgeted against the days remaining, so a chained run
  cannot eat the week's pacing.
- The spawn memo is already invalidated on a mid-run kill, with the comment:
  *"a kill is no longer something only the end of a run can do."*

The invariant that looked like a blocker is already handled.

### What is not

Four accounting items, all in `dhcbosses-lib.php`:

1. **`hp0` becomes per-segment.** `dealt` is `hp0 - remaining` per boss; across a
   chain that is a sum over waves.
2. **`$b['meta']['sent'][$k]` is keyed by boss_key**, which repeats every wave.
   Qualify it by wave or the second wave's ledger collides with the first's.
3. **`UPDATE ... WHERE wave = $wave`** targets a wave read at run start. It has
   to follow the chain.
4. **`dhc_boss_runs.wave` is one column**, recorded at start deliberately. Add
   `waves_cleared` beside it.

### Chain depth

Cap at two or three resurrections per run. Not for balance -- power compounds
`1.18x` per wave (`DHCB_WAVE_POW`) against flat crew output, so a chain from
wave 1 converges at about 6.5x a single fight's damage and self-limits. The cap
is for **equity**: your resurrection is everyone else's wave skip, and a strong
crew chaining deep pushes the server into 1.94x-power bosses that weaker crews
then contribute less against, on a per-Fighter ladder.

### The cost

"You Win" stops existing. Every run ends with your crew down. Reframe the end
screen on the kill count -- "three crews down before they got you" is a better
brag, and it surfaces the kill credit Michael wanted in the first place.

## The cue

Audition it at **Admin > Boss Rez Sound** (`admin-bossrez.php`).

**The rise is the KO sound, reversed.** `skullcoinlose.ogg` is what plays when
anything is knocked out, so the player hears it three times as the crew falls
and then hears it again, undone. The thing that went down is the thing that
comes back. (`hypercube_create` was the first idea and is thematically close but
borrowed from elsewhere; the connection is decorative rather than causal. It is
also 2.60s against the KO's 1.90s, so it muddies faster when pitched down.)

**Reversed sounds swell and cut.** The cut is the instant the crew lands, so the
cue must FINISH on the visual, not start on it. Multiple layers are therefore
**end-aligned**: the longest sets the landing and every other starts later so
they all cut together. Aligning their starts -- what a naive mix does -- puts
several landings in one cue and reads as a mistake rather than a chord.

**The owner's stack**, found by ear in the tuner, all reversed and end-aligned
at rate 0.675:

| | sound | gain |
|---|---|---|
| 1 | `sounds/skullcoinlose.ogg` | 1.00 |
| 2 | `sounds/hypercube_create.ogg` | 0.55 |
| 3 | `sounds/bomb_explode.ogg` | 1.00 |
| 4 | `sounds/voice_gameover.ogg` | 1.00 |
| 5 | `audio/sounds/demolition.mp3` | 1.00 |
| 6 | `audio/sounds/death.mp3` | 1.00 |
| 7 | `audio/sounds/laststand.mp3` | 1.00 |

Seven layers summing at gain 1 peaks at 0.0 dB, mean -6.8. Some of the grit is
clipping. If that is wanted, do it deliberately with a shaper -- accidental
clipping sounds different on different hardware.

### One cue, tight

The first design pitched the cue down further every wave and added a layer each
time, so it gained mass as it gained depth. **Dropped, on the owner's call:**
"we don't have to pitch/slow down on each wave. Just let the sound be the sound
and keep it tight but dramatic."

Right call, and not only for build time. The wave curve simulated in
`dhcbosses-lib.php` is `1 1 1 1 1 2 4` across a week, so waves 5 and up are
content almost nobody reaches -- most of a per-wave ladder would never be heard,
and the half that was heard (waves 1 to 4) spanned rates 0.780 to 0.675, which is
too narrow to feel. A single cue that is right beats ten that are nearly the same.

**The shipped cue is five layers, all reversed, all at rate 0.675, end-aligned:**

| | sound | gain |
|---|---|---|
| 1 | `sounds/skullcoinlose.ogg` | 1.00 |
| 2 | `sounds/hypercube_create.ogg` | 0.55 |
| 3 | `sounds/bomb_explode.ogg` | 1.00 |
| 4 | `sounds/voice_gameover.ogg` | 1.00 |
| 5 | `audio/sounds/death.mp3` | 1.00 |

(The owner's first stack also carried `demolition.mp3` and `laststand.mp3`. Both
are long -- 11.0s and 12.7s reversed at this rate -- and they were what made the
cue 12.7 seconds. Dropping them is what makes it tight.)

**Length is trimmed from the FRONT, never the back.** The cut is the landing, so
a shorter cue loses its build and keeps its ending. `death.mp3` is 9.2s reversed
and sets the untrimmed length; 3s and 4s were both rendered for comparison.

**The animation timing is not invented; it falls out of end-alignment.** Every
swell cuts on the same instant, so a longer sound enters earlier and the entry
times are a storyboard. Untrimmed, for the five:

| t | enters | screen |
|---|---|---|
| 0.00 | `death` | dread under a dead board, shake begins |
| 5.39 | `hypercube_create` | boss 1 rises |
| 6.42 | `skullcoinlose` | boss 2 rises |
| 6.49 | `voice_gameover` | — |
| 6.68 | `bomb_explode` | boss 3 rises |
| 9.24 | **all cut** | three land, hue snaps to the new wave |

The three arena swells landing about a second apart near the end give one boss
per beat. That is a consequence of those files' lengths rather than a decision,
and it is worth keeping -- trimming to 3s or 4s preserves it, since the trim
only eats `death`'s build.

**Shake the board.** The owner's direction: "Get dramatic as you want, shake the
entire board. Whatever it takes to freak the player out the first time they see
it." The build is the shake ramping; the cut is the landing.

The hue is free: `dhcb_wave_hue()` already cycles five colours
(0/72/144/216/288) and `dhc-arena-card-harness.php` already checks the CSS
against it. Today you only see one per run because a run is one wave.

## Deploy note

The 15 arena `.ogg` files live at `/staking/sounds/` and are **not in the repo** --
they deploy like images. The 21 `.mp3` under `audio/sounds/` **are** committed.
A dozen other `sounds/*.mp3` paths appear in the source but belong to
`dropship/sounds/`, which is gitignored and 404s from this host; they were
curl-verified and excluded from the tuner for that reason.
