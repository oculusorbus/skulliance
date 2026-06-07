# Locations

Every Realm contains **seven locations**. Each one is upgraded independently using core project points, and purchased upgrades cap at **Level 10** - though raid performance can push locations beyond it (see below). Your locations are split into offensive and defensive roles, and several of them passively produce resources every night.

## Upgrade Cost

Upgrading a location to its next level costs **next level × 100** core project points (e.g. Level 0→1 costs 100, Level 9→10 costs 1,000). If you don't have enough of the matching core project points, you can upgrade with **any points at 3× the cost** - this lets partner-only stakers participate in Realms.

Upgrades complete on a timer and are claimed automatically when you visit the Realms page. A completed upgrade overrides any damage taken while it was in progress.

**Upgrade window strategy:** because completed raids are also auto-claimed when you load the page, a freshly upgraded location can be damaged immediately by an incoming raid that resolves at the same time. Monitor your upgrade timers against your incoming/outgoing raids - claim a finished upgrade and queue the next one *before* a raid resolves, or you risk repeating (or dropping below) the level you just paid for. At max level, keep re-queuing Level 10 upgrades to instantly repair any incoming damage.

## Beyond Level 10

Level 10 is a **soft limit**: it's the most you can *buy*. Purchased upgrades max out at Level 10, and at that point upgrades can only *maintain* the location at 10. But raids can take locations further - when an incoming raid against you **fails**, the matching offense location in your Realm is upgraded by 1 level, with no cap. Raid and defend well, and your locations push past the soft limit over time, raising your offense and defense ratings beyond what points alone can purchase. Total location levels are ranked on the **Realm Power leaderboard**. See [[realms-raids]].

## The Seven Locations

### 1. Portal (Offense)
Controls your raiding capacity. The number of raids you can run at once equals your **Portal level**, and your Portal also influences how many soldiers you can send per raid. A failed raid has a 1-in-3 chance of damaging your Portal, reducing your concurrent raid slots.

### 2. Armory (Offense)
Produces **gear** (weapons and armor) for your soldiers every night. At Level 1 it drops 1 piece per night; at Level 2+ it drops a random `1` to `min(10, level)` pieces per night, with higher levels rolling from better gear tiers. See [[realms-soldiers]] for how gear is equipped.

### 3. Tower (Defense)
Houses your defensive **garrison** of up to **10 trained soldiers**. The more soldiers stationed in the Tower, the stronger your defense - Tower contributes a score of `(garrison ÷ 10) × 10` to your defense rating.

### 4. Barracks (Offense)
Trains your soldiers and sets your army size. Training time is **`(11 − level) × 24` hours** (so a Level 10 Barracks trains in 24 hours; a Level 1 Barracks takes 240 hours). Your **deployment cap** - the number of soldiers you can field - is **`min(100, Barracks level × 10)`**. Trained, deployed soldiers feed your offense rating.

### 5. Factory (Defense)
Produces **mission/raid consumables** every night. The number of items produced per day equals your **Factory level**. These are the same consumable items used in missions (success boosts, Fast Forward, Double Rewards, Random Reward) and can be stocked on locations or attached to raids.

### 6. Crypt (Defense)
Resurrects **dead soldiers**. Soldiers killed in raids can be revived after **`(11 − level) × 24` hours** (same formula as Barracks training). A Level 0 Crypt cannot resurrect. The Fast Forward consumable halves resurrection time. Resurrected soldiers return to your reserve with their gear intact.

### 7. Mine (Defense)
Generates **CARBON** every night equal to **`level × 100`** (a Level 10 Mine produces 1,000 CARBON/night). CARBON is the lifeblood of a Realm - burn it for DIAMOND and shatter that into the core project points you need to keep upgrading. See [[staking-crafting]].

## Offense vs. Defense

Your locations combine into two ratings that decide raids:

* **Offense** = `ceil((Armory + Barracks + Crypt + BarracksScore) ÷ 4)`
* **Defense** = `ceil((Tower + Factory + Mine + TowerScore) ÷ 4)`

…where **BarracksScore** scales with how full your deployed army is and **TowerScore** scales with your Tower garrison. This means raw location levels aren't enough - you also need trained soldiers deployed and garrisoned. See [[realms-raids]] for the full raid math.

## Fast Forward & Shields

* **Fast Forward** consumable halves the remaining time on an upgrade, training, or resurrection.
* **Double Rewards** consumable, when stocked on a location, can act as a shield that absorbs one instance of raid damage without dropping the location's level.
