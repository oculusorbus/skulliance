# UNRESOLVED — what a Fighter's win/loss record actually counts

**Status: open. Nothing here is fixed.** Written down mid-conversation so it
can be picked up cold later.

A Fighter's `wins` / `losses` in `dhc_arena_fighters` do not mean what the
platform implies they mean, in three separate ways. One of them is already
visible to players.

---

## 1. A "win" is survival, not victory

`dhca_finish()` in `dhcarena-lib.php` writes both Crews' records, and the flag
is `!$fell` on both sides:

```php
dhca_bench($conn, $att, (int)$fid, $hours, $fell ? false : true);  // attackers
dhca_record($conn, $def, (int)$fid, !$fell);                       // defenders
```

`$fell` is that Fighter's `ko` flag in the final battle state. So a **W means
the Fighter was still standing when the battle ended** — not that its side won.
A Fighter can take a W in a battle you lost and an L in a battle you won.

## 2. It blends offence and defence, and you only chose half

Both Crews are recorded. The defending Crew is picked automatically —
`dhca_defenders()` fields your best three by strength — so a strong Fighter's
record is mostly battles **you never initiated and never chose it for**.

The two cannot be separated retroactively: there is one pair of counters, and
`dhca_record()` is called identically from both sides. Splitting them needs
`def_wins` / `def_losses` on `dhc_arena_fighters` plus a side flag, and would
only accrue from the day it ships.

Per-Fighter battle *outcomes* are also not recoverable from history:
`dhc_arena_battles` stores the seed, the moves and the outcome but **not the
Crew composition**, and `dhc_arena_state` is cleared when a battle resolves.

## 3. Forfeits corrupt it — two different ways

`dhca_sweep_stale()` closes a battle abandoned for `DHCA_STALE_H` (6) hours.

**3a. The forfeiting side's survivors are credited with wins.** It sets
`over = 'foes'` and calls `dhca_finish()`, which has no early return for a
forfeit. Every Fighter of the forfeiter's that had not yet been knocked out is
judged `$fell = false` and banks a **win** — in a battle the code itself calls
a loss ("*they walked away from a battle, so they lost it*").

**3b. When the state is gone, nothing is recorded at all.** The other branch
closes the ledger row directly:

```php
$conn->query("UPDATE dhc_arena_battles SET outcome = 2, ended_at = NOW()
              WHERE id = ".$open['battle_id']." AND outcome = 0");
```

No `dhca_finish()`, so neither Crew gets a record. The battle exists in the
ledger and in no Fighter's history, so `wins + losses` does not equal a
Fighter's battle count and the two tables disagree.

**The ladder is unaffected.** A forfeit is `outcome = 2` with the forfeiter as
attacker, and `dhca_ladder()` counts it as a loss correctly. The damage is
confined to the per-Fighter stat.

### Size it before deciding

A real battle never runs six hours, so this is roughly the forfeit count:

```sql
SELECT COUNT(*) FROM dhc_arena_battles
WHERE outcome <> 0 AND ended_at IS NOT NULL
  AND TIMESTAMPDIFF(HOUR, started_at, ended_at) >= 6;
```

---

## What is already shipped and wrong

**`dhc-fighter-modal.php` shows a tile labelled "Arena record"** (shipped
2026-10-09, commit `1bd9a9f9`). The number is correct for what it is; the label
is not. It reads as battle wins and is neither battle-based nor
offence-only. A Fighter never fielded reads "Not fought yet", which is fine.

The data plumbing is sound and worth keeping either way: `dhcgallery.php` and
`ajax/dhc-fighter.php` both `LEFT JOIN dhc_arena_fighters` with `COALESCE`, and
`dhc-share-harness.js` pins it.

`dhcarena.php`'s Crew picker has shown `21W/10L` for much longer and has the
same meaning.

## What was planned on top of it, and is blocked

A **Winner / Loser / Coin flip / Rookie** filter on the Collection and the
Arena picker, bucketed by tilt (`wins - losses`), with `= 0 && battles = 0`
falling out as Rookie. Not built — the words would be lying about the data
until the above is settled. Honest terms for the current number would be
**Survivor / Casualty / Coin flip / Rookie**.

## The one thing here that is clean

**Player-level defensive record is fully derivable today** and shown nowhere.
`dhc_arena_battles` has `defender_id` and `outcome` (1 = attacker won,
2 = defender held), so "this Crew has held 12 of 19 defences" is one query with
no schema change and no corruption from the above — forfeits land on the
attacker. `dhca_ladder()` joins on `attacker_id` only, so no player currently
has any idea how their defence performs.

For the opponent picker this is also the *better* measure: how often attackers
bounce off a Crew tells you how hard it is to beat, where their attacking
ladder rank tells you nothing useful about attacking them.

---

## Suggested order when this is picked up

1. Run the sizing query. If forfeits are rare the urgency drops a lot.
2. Fix the recording: a forfeit should mark the forfeiting side's Fighters as
   fallen, and the lost-state branch should either record both Crews or leave
   the row for the normal path.
3. Then relabel the modal tile to match whatever it honestly counts.
4. Then the filter, with terms that match.
5. Separately and independently: the player-level defensive record, which
   needs none of the above.
