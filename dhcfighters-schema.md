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

---

## Migration — anti-replay on the monthly board

Run once on an existing install. New installs get these from the CREATE above
once it is amended; both are additive and safe to re-run guarded.

```sql
ALTER TABLE dhc_fighters
  ADD COLUMN disassembled_at DATETIME NULL DEFAULT NULL AFTER invalid,
  ADD COLUMN newest_trait_at DATETIME NULL DEFAULT NULL AFTER disassembled_at,
  ADD INDEX idx_live (user_id, disassembled_at),
  ADD INDEX idx_newest (newest_trait_at);
```

**`disassembled_at`** — disassembly stops deleting the row and stamps this
instead. A deleted row took its history with it, which is what let a Fighter be
rebuilt as if new. Rows with this set are excluded from availability, the
roster and both boards: its traits are free again, so counting it would be
counting a Fighter that no longer exists. Serials are still never reused, and
now that is enforced by the row surviving rather than by a MAX() that would
drift if rows vanished.

**`newest_trait_at`** — the most recent award date among the traits a Fighter
uses, stamped at save. The monthly board filters on THIS rather than
`created_at`, so re-saving a Fighter built entirely from last month's traits
does not re-enter it. Competing in a month requires having earned something in
that month, which is what the board is meant to measure.

Backfill for rows that predate the columns:

```sql
UPDATE dhc_fighters SET newest_trait_at = created_at WHERE newest_trait_at IS NULL;
```

---

## Migration — originality bonus

```sql
ALTER TABLE dhc_fighters
  ADD COLUMN traits_hash CHAR(40) NULL DEFAULT NULL AFTER traits,
  ADD INDEX idx_hash (traits_hash);
```

**`traits_hash`** — a canonical fingerprint of the trait set, so two Fighters
wearing exactly the same pieces are recognisable as the same configuration.

The **first staker to build a configuration keeps a bonus on it**; later
builders of the same set do not get one. Deliberately a bonus for discovery
rather than a penalty for duplication: a player must never lose score because
somebody else copied them afterwards, which is the same "punishes draw order"
unfairness that made blocking duplicates the wrong call.

Backfill so existing Fighters are credited:

```sql
UPDATE dhc_fighters SET traits_hash = SHA1(traits) WHERE traits_hash IS NULL;
```

That is approximate — PHP writes a canonical hash with slots sorted, while
`SHA1(traits)` hashes the stored JSON as-is. Re-saving or rescoring corrects
it. Running `dhcf_rescore_all()` after the migration rewrites every hash
properly.


---

## Migration — release serials on disassembly

Run once on an existing install. Until editing existed, changing a Fighter
meant disassembling and rebuilding, and disassembly retired the number for
good — 422 and 423 were burned that way in the first week. `dhcf_delete_fighter()`
now sets `serial = NULL` instead, and `dhcf_next_serial()` hands out the lowest
unused number rather than `MAX+1`.

`serial` has to be nullable for that. A MySQL unique index permits any number of
NULLs and exactly one of any real value, so `uniq_serial` still guarantees no two
Fighters share a number.

```sql
ALTER TABLE dhc_fighters MODIFY serial INT NULL;
```

Then free the numbers already retired, and optionally close the existing gaps:

```
php dhcf-renumber.php --release            # preview
php dhcf-renumber.php --release --confirm  # apply
```

`--compact` additionally renumbers live Fighters contiguously in build order.
That changes the displayed name of any Fighter without a custom name, so it is
reasonable at a handful of Fighters and not once people have shared links.


---

## Disassembly hard-deletes (15 September 2026)

`dhcf_delete_fighter()` now runs a real `DELETE`. It used to set
`disassembled_at` and keep the row.

The soft delete was justified as stopping a Fighter being "disassembled and
rebuilt as though newly made" — but that protection never lived in this table.
The monthly board ranks on `newest_trait_at`, which `dhcf_newest_trait_at()`
computes from `dhc_trait_drops.awarded_at` — when the *traits* were awarded, not
when the Fighter was saved. Rebuilding the same Fighter reproduces the same
timestamp, and the drops ledger is append-only and never deleted. The
originality check already ignored disassembled rows. So the row carried history
and nothing else.

`dhc_trait_drops` still records everything ever earned, so a player's holdings
stay reconstructible even though the Fighters they scrapped are not.

**The `disassembled_at` column and every `IS NULL` filter stay in place.** They
are no-ops once nothing sets the column, and they cost nothing — but they keep
any legacy marked row correctly hidden, on this install or any other. Do not
remove them without purging those rows first.

To clear legacy marked rows:

```sql
DELETE FROM dhc_fighters WHERE disassembled_at IS NOT NULL;
```
