# Saved squads — database schema

Run once against the live Drop Ship database. Nothing in the app creates
tables, same as `dropship-themes-schema.md`.

```sql
CREATE TABLE IF NOT EXISTS squads (
	id          INT AUTO_INCREMENT PRIMARY KEY,
	user_id     INT          NOT NULL,         -- dropship users.id
	project_id  INT          NOT NULL,         -- projects.id, so a squad belongs to one game
	name        VARCHAR(40)  NOT NULL,
	created_at  DATETIME     NOT NULL,
	INDEX idx_owner (user_id, project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS squad_members (
	squad_id    INT     NOT NULL,              -- squads.id
	soldier_id  INT     NOT NULL,              -- soldiers.id
	slot        TINYINT NOT NULL DEFAULT 0,    -- display order within the squad
	PRIMARY KEY (squad_id, soldier_id),
	INDEX idx_soldier (soldier_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Why `soldiers.id` and not `asset_name`

Because the row outlives the ownership. `updateSoldiers()` does not delete a
soldier when you sell the NFT behind it -- it sets `user_id = 0` for your whole
roster and then re-associates the assets you still hold, so the row, its level
and its record all survive and only the owner changes.

That is what makes a lost member *detectable* rather than dangling: the squad
still points at a real soldier, and `soldiers.user_id` says it is no longer
yours. Keying on `asset_name` would have been a string that matches a row
belonging to somebody else, which is the same fact wearing a worse disguise.

## Why a squad is never silently deleted

**A squad list must be readable from `squads` alone.** Joining it against the
soldiers you own is the obvious way to build this screen and it is wrong: a
squad whose members you have all sold would vanish from the list while its rows
sat in the table, so the player could neither see it nor get rid of it. The
members are resolved in a second pass and labelled, and a squad with nothing
left in it still renders, still says why, and still has a Delete on it.

## What a member can be

Resolved per member, never stored, because every one of these is a fact about
another table that can change without anybody touching a squad:

| state     | how it is read                                  |
|-----------|-------------------------------------------------|
| `ready`   | yours, alive, not on the field                   |
| `fielded` | yours, alive, already deployed (`active = 1`)    |
| `fallen`  | yours, `deceased = 1` -- it fought this game      |
| `sold`    | `user_id` is no longer yours                     |
| `gone`    | the soldier row itself is missing                |

`fallen` is the common one and is not a fault. `killSquad()` kills your whole
deployed squad after every run, and the only thing that brings anybody back is
`resetSoldiers()`, which runs project-wide when an admin deactivates the game.
So a saved squad fields once per GAME, not once per run, and for the rest of
that game it reads as fallen. The wording has to say "already fought this
game" rather than anything that sounds broken.

## The daily limit this does not change

Deploying a squad sets `active = 1` on each member, exactly as the Deploy
button always did, and `deploySoldier()`'s four-soldier ceiling still applies.
A saved squad of four cannot be fielded alongside a loose soldier.
