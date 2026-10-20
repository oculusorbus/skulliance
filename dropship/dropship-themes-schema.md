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

## `active` is what puts a theme in front of players

Ticking it used to do nothing: which projects a player saw was two names
hardcoded in `getProjects()`. `dropshipActiveProjectIds()` makes the flag
real, and `getProjects()` consults it.

That one list does two jobs - it builds the dashboard's Select Project
dropdown **and** is what `dropship.php` validates `?project_id` against
before the value reaches SQL - so the flag closes both doors at once. If it
only drove the dropdown, a hidden theme would still be reachable by typing
the id into the URL.

It returns `null`, and the old name-exclusion stands, whenever it cannot
answer: no table, no themes, or no theme active. The first two keep the
change invisible until somebody saves a theme. The third is a judgement -
an empty picker is indistinguishable from an outage, and the flag is for
choosing between themes rather than for closing the game.

## Why there are no label columns

An earlier cut of this table carried `currency`, `squad_label` and
`character_label`. They were the same three words the wording already owns -
`SCRIP`, `Super Soldier`, `Troops` - and holding them twice meant a theme
could rename SCRIP in its wording and still read "SCRIP" on the dashboard,
with nothing in the admin saying why. They are rows in `dropship_theme_text`
now, and `dashboard.php` reads them through `evaluateText()` like every other
label on the page.

`trait_source` is **not** one of them, though it was removed once by mistake.
Two different things were called the trait source: this column, which picks
between two real NFT-loading pipelines, and `$source` in
`dropship-traitdrop.php`, the key naming which *game* a Discord trait drop
came from. The second really is the slug with the hyphens taken out and
really is derived. The first is a choice nothing can infer, and removing it
left `dashboard.php` picking the pipeline on a hardcoded `project_id == 4`,
which is what the column existed to replace.

## Tables

```sql
CREATE TABLE IF NOT EXISTS dropship_themes (
	id               INT AUTO_INCREMENT PRIMARY KEY,
	project_id       INT          NOT NULL,            -- projects.id
	slug             VARCHAR(64)  NOT NULL,            -- image folder under dropship/
	active           TINYINT(1)   NOT NULL DEFAULT 0,  -- shows in the project picker
	-- MEDIA extension: 'gif' (animates itself) or a still like 'png',
	-- which gets the Ken Burns pan instead. See the note below.
	extension        VARCHAR(8)   NOT NULL DEFAULT 'gif',
	-- Where this theme's NFT holdings come from: 'koios' reads the
	-- player's connected wallets live, 'skulliance' reads the already
	-- synced nfts table. A real choice; nothing derives it.
	trait_source     VARCHAR(16)  NOT NULL DEFAULT 'koios',
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

## Media extension decides the motion

`extension` is what a theme's round art is stored as, and it picks the
treatment:

| Extension | Treatment |
|---|---|
| `gif` | none. It animates itself, and a pan laid over it fights it. |
| anything still (`png`) | the **Ken Burns** pan, `.image-wrap` in `dist/flexbox.css`, a 40s drift across the frame. |

That pan used to be switched on for project 4 and nothing else, which was
right only by coincidence: Oculus Lounge is the theme that happens to use
stills. It follows the extension now, so a new `png` theme gets the pan
without anyone remembering to wire it, and a `gif` theme never has one
dragged across its own animation.

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

That is now the *only* reason it is `utf8mb4_bin`. The pairs that forced it
were the icon filenames, and those are derived rather than stored, so no
seeded row collides any more. The column stays case sensitive because the
thing reading it is: under a case-insensitive column two rows differing only
by case would be one row in the database and two in the dictionary, whether
or not today's data happens to contain such a pair.

### The one file to run

`dropship-themes-seed.sql` is self-contained: it DROPS both tables, creates
them, and loads every theme. That is deliberate -- a seed that assumed the
tables were already right let the schema and the data drift half a version
apart, which is how a live theme ended up with columns this document had
already removed. Running that file is the whole operation.

It is destructive by design. Everything below is for repairing a table in
place instead.

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

### If the table already has the label columns

They were dropped once the wording took over (see above). Nothing reads
them, so this is safe to run at any time:

```sql
ALTER TABLE dropship_themes
  DROP COLUMN currency,
  DROP COLUMN squad_label,
  DROP COLUMN character_label;
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
