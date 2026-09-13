# DHC Fighters — database schema

Run once against the live database. Nothing in the app creates tables.

```sql
-- DHC FIGHTERS -- schema
--
-- Run once. Nothing in the app creates tables.
--
-- Two tables, and the split between them is the important part:
--
--   dhc_trait_drops   APPEND-ONLY LEDGER of every trait ever awarded. Never
--                     updated, never deleted. It records what dropped, to whom,
--                     from which game, and at what odds -- so a draw can be
--                     audited later and history can be recomputed if the rarity
--                     maths changes. A duplicate is simply another row.
--
--   dhc_fighters      SAVED ASSEMBLIES. A layout referencing traits, never a
--                     rendered image. Layering rules therefore apply
--                     retroactively: improve the renderer and every saved
--                     fighter improves with it.
--
-- Because holdings live in the ledger and arrangements live here, an assembly
-- that a later exception rule invalidates can never put a player's traits at
-- risk -- worst case they rearrange pieces they still own.

CREATE TABLE IF NOT EXISTS dhc_trait_drops (
	id            INT AUTO_INCREMENT PRIMARY KEY,
	user_id       INT          NOT NULL,
	category      VARCHAR(20)  NOT NULL,   -- background|torso|head|headgear|arms|weapon|effects|companion
	slug          VARCHAR(64)  NOT NULL,   -- trait file slug, e.g. 'skull-krusher'
	tier          VARCHAR(12)  NOT NULL,   -- common|uncommon|epic|legendary|mythic AT TIME OF DROP
	drop_rate     DECIMAL(6,3) NOT NULL,   -- the odds this trait actually had when drawn
	source        VARCHAR(32)  NOT NULL,   -- game key: skullswap|skullracer|obscura|...
	source_detail VARCHAR(120) DEFAULT NULL, -- free text: 'won', 'wave 41', 'boss: Xlon', placement
	awarded_at    DATETIME     NOT NULL,
	INDEX idx_user       (user_id),
	INDEX idx_user_slug  (user_id, slug),
	INDEX idx_awarded    (awarded_at),
	INDEX idx_source     (source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dhc_fighters (
	id            INT AUTO_INCREMENT PRIMARY KEY,
	user_id       INT          NOT NULL,
	serial        INT          NOT NULL,   -- continues the collection's numbering
	name          VARCHAR(48)  DEFAULT NULL, -- player override; NULL means use the serial
	traits        TEXT         NOT NULL,   -- JSON: {"slot":"slug", ...}
	rarity_score  INT          NOT NULL DEFAULT 0,
	rules_version VARCHAR(16)  NOT NULL DEFAULT '1',
	invalid       TINYINT(1)   NOT NULL DEFAULT 0, -- set when a later exception rule strands it
	created_at    DATETIME     NOT NULL,   -- decides the monthly leaderboard
	updated_at    DATETIME     NOT NULL,
	UNIQUE KEY uniq_serial (serial),
	INDEX idx_user    (user_id),
	INDEX idx_score   (rarity_score),
	INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
