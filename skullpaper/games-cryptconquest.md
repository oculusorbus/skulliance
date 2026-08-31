# Crypt Conquest

Crypt Conquest is a **solo Regicide-style card game** - no HP bar, no dice. You face down twelve court cards one at a time using a standard hand of cards, and the whole 52-card deck (plus 4 Animal Companions) is reskinned in Crypties NFT art, a different slice of the collection than [[games-cryptcrawl]] uses.

Crypt Conquest is two separate pages, the same split as Crypt Crawl. `cryptconquestgame.php` is the public marketing page you land on from the site nav or homepage - a standalone page (no site nav): hero, feature highlights, two counter-scrolling rows showing the court cards and Animal Companions actually on the board, mechanics/tips/FAQ, and a "Start Conquest" button that starts a real run on `cryptconquest.php`. `cryptconquest.php` is the game itself, a normal in-platform page with the regular site nav - a rules prompt if you don't have a run going, the live board while one's in progress, and a result screen (won/lost, court cards defeated, CARBON earned) once it ends, with a button to start again.

## The Court

Twelve **court cards** stand between you and conquering the Necropolis - the Jack, Queen, and King of all four suits:

* **Jacks** - 10 attack, 20 health.
* **Queens** - 15 attack, 30 health.
* **Kings** - 20 attack, 40 health.

You face them one at a time, in a fixed order (Jacks first, then Queens, then Kings), each shuffled within its rank so which suit comes first varies run to run.

## Your Hand

Your hand draws from a **tavern deck**: the number cards 2-10 of all four suits, plus 4 **Animal Companions** (one per suit, always worth 1 regardless of rank). Numbered cards are worth their own rank. On your turn you either:

* **Play a card, a same-rank combo of cards, or an Animal Companion** (a Companion can join at most one other card, never a bigger combo) to attack the current court card, **or**
* **Yield** - no card played, no suit power triggers, straight to covering whatever the court card hits back with.

## Suit Powers

Playing a card whose suit differs from the current court card's own suit (the court card's own suit is immune) triggers that suit's power, based on the total value of everything you just played:

* **♣ Clubs double your attack** - the whole total against the court card is doubled.
* **♥ Hearts heal** - that many cards return from your discard pile back into the tavern deck.
* **♦ Diamonds draw** - draw that many fresh cards, up to your hand size cap.
* **♠ Spades shield** - the court card takes that much off its next attack against you.

Hearts resolves before Diamonds when a combo triggers both at once.

## Covering an Attack

Whatever the court card hits back with (after Spades shield, if any) has to be covered by discarding cards from your hand worth at least that much - a partial selection that doesn't reach the total is just rejected so you can try again, but discarding your **entire hand** and still coming up short is what actually threatens the run.

## Jesters

Twice per run - once per Jester - you can discard your whole hand and draw a fresh one, on demand, in place of a normal turn. No cost beyond the charge itself. Flipping both Jesters (rather than zero or one) affects your final conquest tier - see Winning below.

## Last Rally

The **first time** your whole hand still can't cover an attack, you don't fall - you **Last Rally** instead: the attempt still happens (your whole hand still goes to the discard pile), but the run continues rather than ending. This fires **once per run**, automatically. The next time it happens, the run ends in a loss for real.

## Winning & Losing

* Defeat all **12 court cards** to **conquer the Necropolis** and win the run.
* Fail to cover an attack with your entire hand a **second** time (Last Rally already spent) and the run **ends in a loss**.
* Defeating a court card with the **exact** amount of damage recovers it face-down atop your tavern deck instead of sending it to the discard pile - a small edge for precise play.

### Conquest Tier

A won run is graded by how many Jesters you flipped along the way (Last Rally firing doesn't affect it):

* **Flawless Conquest** - zero Jesters flipped.
* **Hard-Fought Conquest** - one Jester flipped.
* **Narrow Conquest** - both Jesters flipped.

## CARBON Per Run

Every card you resolve - played into an attack, or discarded to cover an attack - earns **10x its own value** in CARBON (an Animal Companion is worth 1, same as its attack contribution), stacking up over the whole run, win or lose. A running total is visible in the HUD as you play, and the moment the run ends (won, lost, or abandoned), the total is credited straight to your balance with a matching transaction and shown again on the result screen. Logged-in players only - a guest's run still tracks the same total internally, but there's no account to actually pay it into.

## Leaderboard & Rewards

Every completed run (won or lost - an in-progress one doesn't count yet) feeds the Crypt Conquest leaderboard, ranked by **wins first, then your best single run's court cards defeated, then fewest losses**. See [[platform-leaderboards]].

* **All-Time** shows your career totals.
* **Monthly** resets each cycle and pays out - deliberately a monthly cadence rather than Crypt Crawl's weekly one, matching a bigger pool: the **1st place** finisher earns **100,000 CARBON (= 1,000 DIAMOND)**, and the pool divides down the rankings from there, same distribution shape [[games-cryptcrawl]] and [[games-gauntlets]] use for their own pools. Convertible to DIAMOND at 100:1 - see [[staking-crafting]].

Every completed run also counts toward the platform's **Activity** leaderboards (all-time, monthly, and weekly) - see [[platform-leaderboards]].

Every run you finish while logged in - won or not - also posts a quick result to the Crypt Conquest Discord channel: how many court cards you defeated, illustrated with the theme art for that depth, the CARBON you earned that run, plus a callout if it's a new personal best or puts you in 1st place (all-time and/or this month).

## Ambience

Same music player as Crypt Crawl, sitting below the game content - play/pause and prev/next controls, a volume slider, and your on/off choice, current track, and position sticking for the rest of your browser session. Actions update in place without reloading the page, so the music plays straight through a run.

The music also reacts to how the run is actually going: win and Triumph plays, lose and it's Death instead. Land in a fight your current hand genuinely can't survive and the music turns Frantic if Last Rally is still available, or Doom if it's already spent - there's no safety net left. The court card's own backdrop art slowly drifts and zooms while music plays, same Ken Burns treatment as Crypt Crawl's own crypt backdrops.

## Playing as a Guest

Crypt Conquest is playable **logged out** - a guest's run lives in their browser session only and is gone once that session ends, so it never reaches the leaderboard or earns a reward. Log in to have your runs saved to your account and counted toward both leaderboards.
