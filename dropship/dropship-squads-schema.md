# Saved squads — database schema

Run once against the live Drop Ship database. Nothing in the app creates
tables, same as `dropship-themes-schema.md`.

```sql
CREATE TABLE IF NOT EXISTS squads (
	id          INT AUTO_INCREMENT PRIMARY KEY,
	user_id     INT          NOT NULL,         -- dropship users.id
	project_id  INT          NOT NULL,         -- projects.id, so a squad belongs to one game
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

## Already created the tables?

The first version had a `name VARCHAR(40) NOT NULL` on `squads`. Squads are
identified by what they ARE now -- their armour spread and what it is worth a
round -- so the column is unused. Drop it:

```sql
ALTER TABLE squads DROP COLUMN name;
```

Harmless to leave in place if you would rather not: nothing reads or writes it.

## A soldier belongs to one squad

Enforced in `dropshipSquadSave()`, which refuses when any soldier on the field
is already in a saved squad and names them, because the way out is to disperse
that squad and you cannot do that unless you know which one.

It is a real hole rather than a theoretical one. Deploy a saved squad, withdraw
one member, field somebody else in the gap and save: three soldiers end up in
two squads, neither squad can be fielded while the other is out, and nothing on
either row says why. `dropship-squads-walk-harness.php` found it, and then found
a second way in eight steps after the first fix -- save `{D,E}`, withdraw D,
save again, and `{E}` slips past a guard that only fired when SOME of the field
was spoken for. A subset is not a duplicate.

`dropshipSquadShared()` stays as the net under the rule, for a row this code did
not create. The walk asserts both halves: that no order of operations produces
an overlap, and that one would still be reported if it did.

## Members are shown heaviest armour first

`dropshipThreshold()` takes the best tier worn as the floor and adds the lower
tiers as credits, so Heavy, Medium, Light, Base is the order the squad's own
number is built in. Sorted for display only: `squad_members.slot` keeps the
order they were saved in, because armour changes when a soldier is swapped and
a stored order would then disagree with the card it is drawn on.

## Why there is no name

A squad already has an identity, and it is a better one than a text field:
four armour tiers and three gear types is a **Super Soldier Squad**, and
anything less says what it is worth per round. Asking a player to invent a
name is a step between them and the thing they came to do, and a typed name
goes stale the moment a soldier is swapped out.

Derived by `dropshipSquadPower()` from the SAME `dropshipThreshold()` the run
rolls against, so the figure on the row and the figure the dice meet cannot
disagree. It is the squad's ARMOUR only: a purchased suit overrides armour in
that chain, so folding one in would report the same number for every squad a
player owns, which is exactly when "order by most powerful" needs to work.

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

## Average depth per squad, which is not built

Raised and deferred by the owner as possibly too much to start. Recording it
needs no schema change whenever it is wanted: `results_soldiers` already
stores one row per soldier per run (`result_id`, `soldier_id`, `project_id`)
and `results.score` is the round the run died on, so a squad's average depth
is a join from `squad_members` through `results_soldiers` to `results`, over
runs where every member of the squad was fielded together.

Worth knowing before building it: a soldier can be fielded in a run WITHOUT
the squad being deployed as a squad, so "runs this squad did" has to mean
"runs whose fielded set was exactly these members", not "runs any of them
appeared in". Otherwise a soldier that is in two saved squads credits its
score to both.
