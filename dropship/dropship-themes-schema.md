# Drop Ship theme schema

Two tables so a theme can be **added and edited without touching PHP**. Run
the SQL below by hand on the Drop Ship database; nothing in the app creates
them, the same way `monstrocity-schema.md` works on the Skulliance side.

**Nothing changes until a theme has rows.** `evaluateText()` checks the table
first and falls back to the hardcoded `if/else` chain in `dropship/db.php`
when a theme has no entries, so these tables can exist empty on production
with no effect at all. A theme goes live the moment somebody saves one in the
admin, and reverts by deleting its rows.

## Why two tables and not one

`evaluateText()` has always been two different lookups and the split is
load-bearing:

- an **exact** match on the whole string (`$replacements[$text]`), so
  `"Soldiers"` becomes `"Citizens"` but `"Soldiers of fortune"` does not;
- a **substring** pass (`$replacements_description`) that only runs when the
  exact lookup misses, so `"Stormed the beach"` becomes
  `"Stormed the downtown"`.

Collapsing them would either break whole-word labels or stop descriptions
rewriting. `match_type` keeps them apart in one table rather than two, since
they are otherwise identical.

## Why `projects.policy_id` is not duplicated here

The collection a theme draws its NFTs from is **already data** -
`getProjectPolicyId()` reads `projects.policy_id`, and the generic soldier
path filters a player's wallet against it. A theme is a skin over a
collection somebody else minted, and several themes can share one. That
binding stays where it is; this schema owns presentation only.

The one exception is Oculus Lounge, whose traits come from a different source
than the generic Koios path. `trait_source` records which path a theme uses
so that stays configuration rather than a function named after one theme.

## Tables

```sql
CREATE TABLE IF NOT EXISTS dropship_themes (
	id               INT AUTO_INCREMENT PRIMARY KEY,
	project_id       INT          NOT NULL,            -- projects.id
	slug             VARCHAR(64)  NOT NULL,            -- image folder under dropship/
	active           TINYINT(1)   NOT NULL DEFAULT 0,  -- shows in the project picker
	extension        VARCHAR(8)   NOT NULL DEFAULT 'gif',
	currency         VARCHAR(32)  NOT NULL DEFAULT 'SCRIP',
	squad_label      VARCHAR(64)  NOT NULL DEFAULT 'Super Soldier',
	character_label  VARCHAR(64)  NOT NULL DEFAULT 'Troops',
	trait_source     VARCHAR(32)  NOT NULL DEFAULT 'koios',
	created_at       DATETIME     NOT NULL,
	updated_at       DATETIME     DEFAULT NULL,
	UNIQUE KEY uniq_project (project_id),
	UNIQUE KEY uniq_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dropship_theme_text (
	id          INT AUTO_INCREMENT PRIMARY KEY,
	theme_id    INT          NOT NULL,
	match_type  ENUM('exact','description') NOT NULL DEFAULT 'exact',
	-- utf8mb4_bin: `find` is CASE SENSITIVE. See the note below; the default
	-- collation makes 'Medkit' and 'medkit' the same row and they are not.
	find        VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
	replacement VARCHAR(255) NOT NULL,
	sort_order  INT          NOT NULL DEFAULT 0,
	UNIQUE KEY uniq_pair (theme_id, match_type, find),
	INDEX idx_theme (theme_id, match_type, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## `find` has to be case sensitive

MySQL's default collation is case **in**sensitive, so `'Medkit'` and
`'medkit'` are the same value to a `UNIQUE` key. They are not the same thing
here:

| Term | What it is | A theme might say |
|---|---|---|
| `Medkit` | the word a player reads | Sexy Nurse |
| `medkit` | the **icon filename** | sexy-nurse |

Seven of these pairs exist in Oculus Lounge and Filthy Mermaid alone
(`Melee`, `Grenade`, `Demolition`, `Flamethrower`, `Radar`, `Radio`,
`Medkit`), and Dread City has one. Without `utf8mb4_bin` the second of each
pair is rejected with

```
#1062 - Duplicate entry '1-exact-melee' for key 'uniq_pair'
```

which is how this was found: the seed would not load.

The lookup side was always case sensitive -- `dropshipThemeDictionaries()`
keys a PHP array, and PHP array keys are byte-compared -- so only the column
needed fixing, not the code.

### If the table already exists

```sql
ALTER TABLE dropship_theme_text
  MODIFY find VARCHAR(190) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL;
```

That rebuilds `uniq_pair` with the new collation. A seed that failed part way
leaves rows behind, so clear both tables before re-running it:

```sql
DELETE FROM dropship_theme_text;
DELETE FROM dropship_themes;
```

## `slug` is stored, never derived

`dropship.php` currently builds the image path from the project **name**:

```php
$slug = strtolower(str_replace(" ", "-", $project_name));
```

so renaming a project silently moves its whole image directory. Storing the
slug breaks that link, which is the entire reason it is a column. Drop Ship's
own art sits flat under `dropship/` rather than in a slug folder, which the
prefix logic already special-cases.
