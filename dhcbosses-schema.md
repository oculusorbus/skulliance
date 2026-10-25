# DHC Bosses — database schema

Run once against the live database. Nothing in the app creates tables, same as
DHC Fighters and DHC Arena.

**This feature owns four tables and borrows everything else.** The Crew, the
engine, the board, the Fighter art pipeline and the trait tables are the
Arena's — see `dhcbosses.md` for what is reused and why none of it is copied.

- **`dhc_boss_day`** — one row per boss per day. The shared health bar. This is
  the only mutable state the whole feature has, and it is written with
  `GREATEST(0, health - n)` so two players landing damage at the same instant
  cannot overwrite each other.
- **`dhc_boss_runs`** — an append-only record of every Crew sent in. The ledger
  the leaderboard, the reports and the trait distribution are all derived from,
  so every number is a query rather than a counter that can drift.
- **`dhc_boss_fighters`** — per-Fighter cooldown for Bosses ONLY, deliberately
  separate from `dhc_arena_fighters`. Sending a Crew at the boss must not bench
  it for ranked play, or the boss quietly cannibalises the Arena it is measured
  against.
- **`dhc_boss_state`** — the live board of a run in progress. Same split as the
  Arena: it churns on every move and the ledger must not.

## Full schema

```sql
CREATE TABLE IF NOT EXISTS dhc_boss_day (
	day           DATE         NOT NULL,
	boss_key      VARCHAR(32)  NOT NULL,       -- 'cip-666' etc, matches the art slug
	max_health    INT          NOT NULL,       -- what it spawned with, after scaling
	health        INT          NOT NULL,       -- what is left
	scale         DECIMAL(6,3) NOT NULL DEFAULT 1.000,
	killed_at     DATETIME     DEFAULT NULL,
	killed_by     INT          DEFAULT NULL,   -- users.id who landed the last hit
	created_at    DATETIME     NOT NULL,
	PRIMARY KEY (day, boss_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dhc_boss_runs (
	id            INT AUTO_INCREMENT PRIMARY KEY,
	user_id       INT          NOT NULL,
	day           DATE         NOT NULL,
	boss_key      VARCHAR(32)  NOT NULL,
	seed          INT UNSIGNED NOT NULL,
	moves         MEDIUMTEXT   DEFAULT NULL,
	dealt         INT          NOT NULL DEFAULT 0,  -- damage that LANDED on the shared bar
	crew_size     TINYINT      NOT NULL DEFAULT 0,  -- Fighters sent, for the report column
	outcome       TINYINT(1)   NOT NULL DEFAULT 0,  -- 0 running, 1 crew survived, 2 crew wiped
	killed        TINYINT(1)   NOT NULL DEFAULT 0,  -- did this run drop the boss
	rounds        INT          NOT NULL DEFAULT 0,
	season        CHAR(7)      NOT NULL,            -- 'YYYY-MM', the monthly ladder
	started_at    DATETIME     NOT NULL,
	ended_at      DATETIME     DEFAULT NULL,
	INDEX idx_user_day (user_id, day),
	INDEX idx_day      (day, boss_key),
	INDEX idx_season   (season, user_id),
	INDEX idx_open     (user_id, outcome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dhc_boss_run_fighters (
	run_id     INT        NOT NULL,
	fighter_id INT        NOT NULL,
	`rank`     TINYINT    NOT NULL,          -- 0 front, 1 mid, 2 back
	fell       TINYINT(1) NOT NULL DEFAULT 0,
	dealt      INT        NOT NULL DEFAULT 0,
	PRIMARY KEY (run_id, fighter_id),
	INDEX idx_fighter (fighter_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dhc_boss_fighters (
	fighter_id    INT          NOT NULL PRIMARY KEY,   -- dhc_fighters.id
	user_id       INT          NOT NULL,
	-- WRITTEN AND READ BY MYSQL, NEVER BY PHP, for the reason the Arena's
	-- equivalent column documents at length: php.ini is UTC on this host and
	-- MySQL is not, so a cooldown compared across the two is hours out.
	benched_until DATETIME     DEFAULT NULL,
	ko            TINYINT(1)   NOT NULL DEFAULT 0,
	INDEX idx_user (user_id),
	INDEX idx_bench (user_id, benched_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dhc_boss_state (
	run_id        INT          NOT NULL PRIMARY KEY,
	user_id       INT          NOT NULL,
	state         MEDIUMTEXT   NOT NULL,
	updated_at    DATETIME     NOT NULL,
	INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dhc_boss_payouts (
	day           DATE         NOT NULL,
	user_id       INT          NOT NULL,
	tier_table    VARCHAR(24)  NOT NULL,       -- which dhcf table it rolled on
	dealt         INT          NOT NULL DEFAULT 0,
	paid_at       DATETIME     NOT NULL,
	PRIMARY KEY (day, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Why the daily allowance needs no column

Counted from `dhc_boss_runs` against the day, exactly as the Arena counts its
allowance from `dhc_arena_battles`. A count is always consistent with the
ledger; a counter can drift from it.

## Why `dhc_boss_payouts` has a primary key of (day, user_id)

It is the idempotency guard for the daily settle. The cron can be run late,
and it can be run twice; the second run inserts nothing and pays nothing.
