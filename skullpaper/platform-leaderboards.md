# Leaderboards

Leaderboards track competitive performance across the entire platform. Most modes have both a **monthly** track (which often distributes rewards) and an **all-time** track for bragging rights.

## Leaderboard Types

* **Overall** and **per-project** point standings.
* **Missions** - all-time and monthly (the monthly track pays the CARBON pool; see [[missions-monthly-rewards]]).
* **Raids** and **Factions** - Realms competition (see [[realms-raids]] and [[realms-factions]]).
* **Streaks** - daily-reward login streaks.
* **Monstrocity**, **Boss Battles**, **Skull Swap**, **Gauntlets**, **Crypt Crawl**, **Crypt Conquest**, **Skull Racer** - the game leaderboards (see [[games]], [[games-cryptcrawl]], [[games-cryptconquest]], and [[games-skullracer]]).
Landing on the leaderboards page shows a **hub**: a card for every board, grouped into Platform, Missions, Realms and Games, each showing who currently leads it. Click a card for the full board, or use the small All-Time / Weekly / Monthly links under it to jump straight to a period. Every board on the platform has a card here, and any board page has an **All Leaderboards** link back.

The **Projects** selector does one job: jumping to a project's holdings leaderboard. It lists projects only - the game and platform boards have their own selector, so this one no longer has to be waded through to reach them.

* **Drop Ship** and **Oculus Lounge** - each has three boards: **Weekly** (the game round currently running), **All-Time** (best single run ever) and **XP** (lifetime total score). These live in their own database as a sub-system, so their boards refresh on the same hourly cycle as the rest of the hub. Players who joined those games before Skulliance existed still appear; they just have no profile to link to.
* **Missions Unlocked** - all-time, how far through the mission ladders each player has climbed across every project. Distinct from the Missions board above, which counts missions run: this one only moves when you get *deeper*, so grinding easy missions doesn't climb it. See [[missions]].
* **Activity** - overall platform engagement, all-time/monthly/weekly. A single weighted score
  combining how much you've done across the platform - daily streaks, missions, Skull Swap,
  Gauntlets, completed Crypt Crawl delves, completed Crypt Conquest runs, finished Skull Racer
  races, raids, boss encounters, and Monstrocity attempts each count toward it, weighted by roughly
  how substantial that action is (a daily streak claim counts for much less than a Monstrocity
  attempt). A finished race counts the same as a completed delve or conquest.

## How It Works

Each mode ranks by its own calculated score. The page shows a top-3 podium with avatars and medals, followed by an extended ranked list. Only stakers who've set their profile to visible appear publicly. Reward-bearing leaderboards divide their pool down the rankings by rank, so finishers beyond the podium still earn a share.

![All-time activity leaderboard with podium](https://www.skulliance.io/staking/images/screenshots/leaderboard.png)

## Switching periods

Most boards are kept for more than one stretch of time - all-time, and a
monthly or weekly cut alongside it. Those live together now: open any board
and the periods it has sit at the top right, next to the way back to the
hub, with the one you are reading marked.

Before this you had to return to the leaderboard hub and find the card again
to change from all-time to monthly, which made comparing the two a chore.
Boards that only have one period, like Realm Power, show no switcher at all
rather than a button that does nothing.

## Moving between boards

Every board page carries three controls on one row: the way back to the
hub, the period switcher, and a **Games** dropdown listing every board
grouped the way the hub groups them.

The dropdown points at each board's shortest timeframe - weekly where a
board has one, otherwise monthly - rather than all-time. That is on
purpose: the period switcher is right beside it, so stepping back to
all-time is one press, and it keeps the list to one entry per board
instead of one per board per period. There is no hub entry in it, because
the link next to it already is that.
