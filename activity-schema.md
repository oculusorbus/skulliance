# Activity feed — database schema

Run once against the live database. Nothing in the app creates tables, same as
DHC Arena (`dhcarena-schema.md`) and DHC Fighters (`dhcfighters-schema.md`).

One table. Until it exists the feature is **inert, not broken**: `activity_log()`
suppresses the failed insert, so every Discord announce still posts exactly as
it always has, and `activity.php` renders an empty wall.

## Why a log table and not a query across the existing tables

The obvious build is to read the tables the events already live in — missions
run, battles fought, store purchases — and merge them by date. That cannot
produce this feed, and the reason is the whole design:

**A large share of what Skull Bot announces is assembled at announce time and
stored nowhere.** A monthly leaderboard standing, a raid outcome summary, a
trait-drop line that names the roll — those exist as a sentence in a Discord
embed and as nothing else. Reconstructing them would mean re-deriving each one
from whatever partial state survived, separately for every feature, forever.

So the row is written where the sentence already exists — inside `discordmsg()`,
from the embed it just posted. One writer covers all 100+ call sites, and a new
feature that announces anything appears in the feed the day it ships.

The cost: **history starts empty.** There is no backfill and there cannot be a
faithful one.

## What gets written, and what does not

- The insert happens **after a 2xx from Discord**, not before the post. An
  unconfigured webhook, a 401 from a rotated URL, a 400 from an over-length
  embed — none of those reached anybody, so none belong in a feed whose premise
  is "this was announced".
- **`alertAdmin()` posts are excluded.** Those are operational failure reports
  with a `@` ping down the default webhook — a dead cron, a rejected embed, a
  database error. They are not player activity and they would put internal
  failure detail on a page every member can read.
- Everything else is mirrored. `general`, `member`, `elite` and `innercircle`
  are dead channels that no longer post anything, so they will simply never
  appear.

## Two players

`user_id` is the player whose request triggered the announce, resolved from the
session. `user_id2` is the other player in a two-sided event, and has to be
passed explicitly by the caller, because nothing about the request can infer it.
The **My activity** filter matches *either* column, so the event shows up for
both players — the loser as much as the winner.

Today the only two-sided announce on the platform is **DHC Arena**:
`dhca_announce()` already holds both `$attId` and `$defId`, and passes them.
Every other announce is about one player (or none, for a cron run), so they pass
nothing and the column stays 0.

## Schema

```sql
CREATE TABLE IF NOT EXISTS activity (
	id          INT AUTO_INCREMENT PRIMARY KEY,
	channel     VARCHAR(32)   NOT NULL DEFAULT '',  -- discordmsg() channel key, '' = default webhook
	title       VARCHAR(255)  NOT NULL DEFAULT '',
	description TEXT          DEFAULT NULL,         -- Discord markdown, rendered by activity_format()
	url         VARCHAR(512)  DEFAULT NULL,         -- where the card links
	image_url   VARCHAR(512)  DEFAULT NULL,         -- the big embed image
	thumbnail   VARCHAR(512)  DEFAULT NULL,         -- corner image, usually the player's avatar
	color       VARCHAR(8)    NOT NULL DEFAULT '',  -- hex, no '#', as passed to discordmsg()
	author_name VARCHAR(190)  DEFAULT NULL,         -- embed author line, usually the player
	author_icon VARCHAR(512)  DEFAULT NULL,
	footer_text VARCHAR(255)  DEFAULT NULL,
	user_id     INT           NOT NULL DEFAULT 0,   -- users.id, 0 = cron / no session
	user_id2    INT           NOT NULL DEFAULT 0,   -- the other player in a two-sided event
	created_at  DATETIME      NOT NULL,
	INDEX idx_recent  (id),
	INDEX idx_channel (channel, id),
	INDEX idx_user    (user_id, id),
	INDEX idx_user2   (user_id2, id),
	INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

`utf8mb4` is not optional — titles and descriptions across the platform are full
of emoji (`⚔️`, `💀`, `🏆`), and on `utf8` the insert would either fail or mangle
them.

## Retention

`activity_prune()` drops rows older than `ACTIVITY_RETAIN_DAYS` (**120**, in
`activity-lib.php`) and is called from the nightly job in `verify.php`. The feed
is a "what's happening" wall, not an archive — the tables behind each event are
still the record of it.

To change the window, edit the constant; nothing else reads it.
