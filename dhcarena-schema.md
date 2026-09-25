# DHC Arena — database schema

Run once against the live database. Nothing in the app creates tables, same as
DHC Fighters (see `dhcfighters-schema.md`).

Three tables, and the split matters:

- **`dhc_arena_battles`** — an APPEND-ONLY record of every battle fought. Never
  rewritten once resolved. It carries the seed and the move list, so any result
  can be replayed exactly: a disputed outcome is checkable and a bug is
  reproducible. It is also the source the ladder is derived from, so standings
  can be recomputed if the scoring changes.
- **`dhc_arena_state`** — the live board of a battle still in progress. One row
  per unfinished battle, deleted or marked resolved when it ends. Separate from
  the ledger because it churns on every move and the ledger must not.
- **`dhc_arena_fighters`** — per-Fighter Arena state: when it is available again
  after a battle, whether it fell in that battle, and its record. Keyed to
  `dhc_fighters.id`. `ko` exists because the player is shown two different words
  — a Fighter that fell is **resurrecting**, one that merely fought is
  **recovering** — and the two cannot be told apart from the clock, since the
  remaining time depends on roster depth as well.

## Already created the tables?

`ko` was added after the first release. Run this once; everything else below is
unchanged and `CREATE TABLE IF NOT EXISTS` makes re-running it harmless.

```sql
ALTER TABLE dhc_arena_fighters
  ADD COLUMN ko TINYINT(1) NOT NULL DEFAULT 0 AFTER benched_until;
```

## Full schema

```sql
CREATE TABLE IF NOT EXISTS dhc_arena_battles (
	id            INT AUTO_INCREMENT PRIMARY KEY,
	attacker_id   INT          NOT NULL,          -- users.id
	defender_id   INT          NOT NULL,
	seed          INT UNSIGNED NOT NULL,          -- replays the whole battle
	moves         MEDIUMTEXT   DEFAULT NULL,      -- JSON [[from,to],...]
	outcome       TINYINT(1)   NOT NULL DEFAULT 0,-- 0 running, 1 attacker won, 2 defender won
	rounds        INT          NOT NULL DEFAULT 0,
	bombs         INT          NOT NULL DEFAULT 0,
	blasts        INT          NOT NULL DEFAULT 0,
	best_chain    INT          NOT NULL DEFAULT 1,
	rewarded      TINYINT(1)   NOT NULL DEFAULT 0,-- did this battle pay out
	season        CHAR(7)      NOT NULL,          -- 'YYYY-MM', the monthly ladder
	started_at    DATETIME     NOT NULL,
	ended_at      DATETIME     DEFAULT NULL,
	INDEX idx_attacker (attacker_id, started_at),
	INDEX idx_defender (defender_id, started_at),
	INDEX idx_season   (season, outcome),
	INDEX idx_pair_day (attacker_id, defender_id, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dhc_arena_state (
	battle_id     INT          NOT NULL PRIMARY KEY,
	user_id       INT          NOT NULL,          -- whose turn-taking session owns it
	state         MEDIUMTEXT   NOT NULL,          -- JSON, the full engine battle
	updated_at    DATETIME     NOT NULL,
	INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dhc_arena_fighters (
	fighter_id    INT          NOT NULL PRIMARY KEY,   -- dhc_fighters.id
	user_id       INT          NOT NULL,
	benched_until DATETIME     DEFAULT NULL,       -- unavailable until this time
	ko            TINYINT(1)   NOT NULL DEFAULT 0, -- did it FALL, or only fight?
	wins          INT          NOT NULL DEFAULT 0, -- career, public (see §8c)
	losses        INT          NOT NULL DEFAULT 0,
	season_wins   INT          NOT NULL DEFAULT 0,
	season_losses INT          NOT NULL DEFAULT 0,
	season        CHAR(7)      DEFAULT NULL,
	INDEX idx_user (user_id),
	INDEX idx_bench (user_id, benched_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Why the daily allowance and the per-opponent limit need no columns

Both are counted from `dhc_arena_battles` against `CURDATE()`, the same way the
DHC trait cap is counted from `dhc_trait_drops`. A count is always consistent
with the ledger; a counter can drift from it.

- **Allowance** — `COUNT(*) WHERE attacker_id = ? AND DATE(started_at) = CURDATE()`
- **One rewarded battle per opponent per day** —
  `COUNT(*) WHERE attacker_id = ? AND defender_id = ? AND DATE(started_at) = CURDATE() AND rewarded = 1`

## Season

`'YYYY-MM'`, stamped at battle start so a battle belongs to the month it was
fought in even if it resolves after midnight on the last day.
