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

Two things arrived after the first release. Run both once; everything else below
is unchanged and `CREATE TABLE IF NOT EXISTS` makes re-running it harmless.

**1. `ko` on `dhc_arena_fighters`:**

```sql
ALTER TABLE dhc_arena_fighters
  ADD COLUMN ko TINYINT(1) NOT NULL DEFAULT 0 AFTER benched_until;
```

**2. An index for the all-time ladder:**

```sql
ALTER TABLE dhc_arena_battles
  ADD INDEX idx_outcome (outcome, attacker_id);
```

The monthly ladder narrows on `idx_season (season, outcome)` first, so it only
ever groups one month of rows. The all-time board has no season to narrow on and
groups the whole table, which grows forever. This is not urgent at today's row
count and will be: add it before the table gets large, not after.

**3. `dhc_arena_live`**, for human-vs-human matches — the `CREATE TABLE` is at
the bottom of this file under *Live battles*. Live play is inert until it
exists: the Challenge button will simply report that it could not send.

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
	-- WRITTEN AND READ BY MYSQL, NEVER BY PHP. dhca_bench() stores
	-- DATE_ADD(NOW(), ...) and dhca_crew() asks for the remainder with
	-- TIMESTAMPDIFF(SECOND, NOW(), benched_until). MySQL's zone on this
	-- host runs behind php.ini's UTC and nothing sets either, so reading
	-- this with strtotime() and comparing it to time() takes hours off
	-- every cooldown -- which is exactly what made a Fighter that won and
	-- survived available again the moment it was benched.
	benched_until DATETIME     DEFAULT NULL,       -- unavailable until this time
	ko            TINYINT(1)   NOT NULL DEFAULT 0, -- did it FALL, or only fight?
	-- OPEN ISSUE: these do not count what they look like they count.
	-- A "win" is SURVIVAL (!$fell), both Crews are recorded so offence and
	-- defence are blended, and forfeits corrupt both directions.
	-- See dhcarena-winloss-unresolved.md before building on them.
	wins          INT          NOT NULL DEFAULT 0, -- career, public (see §8c)
	losses        INT          NOT NULL DEFAULT 0,
	season_wins   INT          NOT NULL DEFAULT 0,
	season_losses INT          NOT NULL DEFAULT 0,
	season        CHAR(7)      DEFAULT NULL,
	INDEX idx_user (user_id),
	INDEX idx_bench (user_id, benched_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## `dhc_arena_battle_fighters` — who was in it, and what happened to them

One row per Fighter per battle, six per battle. **Nothing recorded which
Fighters were in a battle until this table.** `dhc_arena_battles` holds the
seed, the moves and the outcome but not the Crew, and `dhc_arena_state` is
deleted the moment a battle resolves -- which is why a single corrupted
forfeit could not be repaired: there was no record of whose record it was.

Counters cannot be audited or fixed. A ledger can be recomputed. Every
per-Fighter number below is a query against this, not a column that drifts.

```sql
CREATE TABLE IF NOT EXISTS dhc_arena_battle_fighters (
	battle_id  INT        NOT NULL,          -- dhc_arena_battles.id
	fighter_id INT        NOT NULL,          -- dhc_fighters.id
	side       TINYINT    NOT NULL,          -- 1 attacker, 2 defender
	-- BACKTICKED: `rank` is a reserved word from MySQL 8.0.2 (the window
	-- function), so an unquoted column of that name fails at CREATE time.
	-- dhca_log_participants() quotes it in the INSERT for the same reason.
	`rank`     TINYINT    NOT NULL,          -- 0 front, 1 mid, 2 back
	fell       TINYINT(1) NOT NULL,          -- knocked out before the end
	dealt      INT        NOT NULL DEFAULT 0,-- damage this Fighter dealt
	PRIMARY KEY (battle_id, fighter_id),
	INDEX idx_fighter (fighter_id, side),
	INDEX idx_battle (battle_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Why `side` is on the row

`dhca_finish()` records BOTH Crews, so a Fighter's old `wins`/`losses` blend
battles you chose with battles the game chose for you -- and it is weighted
toward the latter, because `dhca_defenders()` fields your best three
automatically. A strong Fighter is defending constantly in matchups you never
saw. With `side` the two separate, which also makes the player-level defensive
record visible for the first time.

### Why `rank` is on the row

A match of 3 reaches the FRONT rank, so the front Fighter absorbs
disproportionate damage by design. Survivability compared across positions is
mostly measuring position. Store it and the comparison can be made fairly.

### Why `dealt` is on the row

Survival is a proxy for contribution. `dealt` is contribution. A Fighter that
survived a win by doing nothing did not carry it either, which is the question
this whole table exists to answer.

### The crew outcome is NOT stored

It is `dhc_arena_battles.outcome` joined against `side`: outcome 1 means the
attacker won, 2 the defender. Denormalising it would be a second copy of a
fact that can disagree with the first.

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

## Live battles — `dhc_arena_live`

Added after the first release, with §8d. **Run this once; it is independent of
everything above and nothing else changes.**

A fourth table rather than a flag on `dhc_arena_battles`, and the separation is
the design. A live battle is a sport: no ladder, no traits, no Fighter record,
no bench, no allowance. Keeping it in its own table means there is no code path
from a live battle to the economy at all — "live never pays" cannot be softened
later by an `unless`, because there is nothing to add the exception to.

It also has to stay clear of two ranked rules that key on
`dhc_arena_state.user_id` with `outcome = 0`: `dhca_open_battle()` would let a
live match block the player's real battle, and `dhca_sweep_stale()` would
forfeit it as a DEFEAT after six hours and bench Fighters over a match that
carried no stake.

One row is the whole match — the invite, the board and the result — because a
live battle is short, is never replayed for a dispute (nothing was at stake) and
is not a ledger anything is derived from. `moves` is kept anyway: it costs
nothing and makes a reported bug reproducible.

```sql
CREATE TABLE IF NOT EXISTS dhc_arena_live (
	id            INT AUTO_INCREMENT PRIMARY KEY,
	host_id       INT          NOT NULL,          -- users.id, who challenged
	guest_id      INT          NOT NULL,          -- who was challenged
	status        TINYINT(1)   NOT NULL DEFAULT 0,-- 0 invited, 1 playing, 2 finished, 3 closed
	seed          INT UNSIGNED NOT NULL,
	host_crew     VARCHAR(128) DEFAULT NULL,      -- JSON [dhc_fighters.id,...]
	guest_crew    VARCHAR(128) DEFAULT NULL,
	state         MEDIUMTEXT   DEFAULT NULL,      -- the engine battle, host as 'mine'
	moves         MEDIUMTEXT   DEFAULT NULL,      -- JSON [[from,to],...], both sides
	winner        TINYINT(1)   NOT NULL DEFAULT 0,-- 0 none, 1 host, 2 guest
	turn_at       DATETIME     DEFAULT NULL,      -- when the current turn began: the clock
	created_at    DATETIME     NOT NULL,
	updated_at    DATETIME     NOT NULL,
	ended_at      DATETIME     DEFAULT NULL,
	INDEX idx_host  (host_id, status),
	INDEX idx_guest (guest_id, status),
	INDEX idx_open  (status, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`state` stores the battle with the **host as `mine`**, always. Both players are
`mine` to themselves, so `dhcal_view()` swaps `mine`/`foes`, `turn`, `over` and
every `fx` event's `side` on the way out to the guest. The board itself needs no
transformation — `dhca_fighter_for_gem()` resolves a gem to a rank within the
asking side, so the 7x7 is symmetric.
