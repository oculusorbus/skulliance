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

Your hand draws from a **tavern deck**: the number cards 2-10 of all four suits, plus 4 **Animal Companions** (one per suit, always worth 1 regardless of rank). Numbered cards are worth their own rank. On your turn you either **attack** with one of the following, **or Yield** - no card played, no suit power triggers, straight to covering whatever the court card hits back with.

* **One card** - any card, always legal.
* **2-4 cards of the same rank**, totalling 10 or less.
* **An Animal Companion plus one other card** - any card, at any value. This pairing is exempt from the 10 limit, so a Companion can ride along with a 10.
* **Two Animal Companions** together.

## Your Hand Only Shrinks

This is the single most important thing to understand, and the easiest to miss:
**your hand is your health, and it does not refill at the end of a turn.**

Cards leave your hand two ways, and both are permanent:

* **Playing them** - any card you attack with goes to the discard pile, whatever its suit.
* **Covering damage** - cards discarded to survive a hit are gone too, *unless* you cover the hit exactly (see Perfect Guard).

You never play cards *out of* the discard pile; every play comes from your hand.
The only ways to gain cards back are **♦ Diamonds** and a **Jester flip**.

Card flow is one direction - **deck → hand → discard** - with ♥ Hearts the only
route back, and it returns cards to the *deck*, not your hand.

## Suit Powers

Playing a card whose suit differs from the current court card's own suit (the court card's own suit is immune) triggers that suit's power:

* **♣ Clubs double your attack** - the whole total against the court card is doubled. Clubs resolves **first**, so any other suit in the same play scales off the doubled number too.
* **♥ Hearts heal** - that many *random* cards return from your discard pile to the **bottom** of the tavern deck. Not into your hand, and you won't draw them again for a while: it keeps the deck from running dry rather than helping you this turn.
* **♦ Diamonds draw** - draw that many fresh cards from the deck into your hand. The only routine way to refill, which makes Diamonds a lifeline rather than a bonus. Draws stop at the **8-card hand limit** and any beyond it are forfeited.
* **♠ Spades shield** - reduces that court card's attack. Shield **accumulates across turns and never wears off**, lasting the whole fight rather than a single attack: two Spades plays worth 9 and 8 against a King leave it hitting for 3 every turn thereafter.

Hearts resolves before Diamonds when a combo triggers both at once.

### Powers Scale With the Whole Play

**A suit power is worth the total value of everything you played that turn, not the value of the card carrying the suit.** This is the most easily missed rule in the game and the one that most changes how it's played.

Pair an **Animal Companion** (worth 1) with your biggest card and that Companion's suit fires at the *combined* total. A Companion of Diamonds played alongside an 8 draws **9 cards**, not 1. That is what Animal Companions are for: they contribute a single point of damage but hand their suit the whole play's value, which means you never need to spend a big Diamond to refill your hand.

## Covering an Attack

Whatever the court card hits back with (after Spades shield, if any) has to be covered by discarding cards from your hand worth at least that much - a partial selection that doesn't reach the total is just rejected so you can try again, but discarding your **entire hand** and still coming up short is what actually threatens the run.

**A Jester can be flipped mid-attack.** Facing a hit your hand can't cover, flipping a Jester right then discards your hand, deals a fresh 8, and leaves the attack still standing to be covered with the new hand. It's the strongest escape available and it costs only one of the two Jester charges - see Jesters below.

## Perfect Guard

Cover an incoming hit **exactly** - not a point over - and your **two highest-value**
spent cards return to your hand. Everything else you spent still goes to the discard.

* A **2-card exact match costs you nothing at all** - both come back.
* A 4-card exact match still costs you the smaller two.
* **Overpaying by even one point returns nothing**, exactly as before.

It's the defensive twin of the exact-kill rule: precision is rewarded on both sides
of the turn. Two cards rather than all of them is a deliberate balance point - see
`cryptconquest.md` for the simulation behind it.

## Strategy Tips

Two habits that feel intuitive but aren't rewarded by the rules above:

* **Covering an attack only needs to reach the total, not match it exactly.** There's no bonus for landing on a clean number, and no penalty for overpaying by a card or two - discard the fewest cards you can spare, not the most. Every card left in hand is a card you can still play.
* **Refill with a Diamond Companion, not a big Diamond.** A Companion of Diamonds paired with your biggest card draws the full combined total, so you get the refill *and* keep the damage. Spend real Diamonds on damage, and never play one into a full hand - draws stop at 8 and the rest are forfeited.
* **Stack Spades early against Kings.** Shield never wears off, so shield spent on turn one keeps paying every turn of that fight. Against a King it compounds faster than any other suit.
* **Spend Jesters as escapes, not refreshes.** Their real value is flipping mid-attack to survive a hit your hand can't cover. Plenty of lost runs end with Jesters unused.
* **Cover the number exactly whenever you can.** Overpaying by one point loses everything you spent; hitting it exactly hands your two best cards back.
* **Attack for the suit power you need, not just the highest number.** A small Diamond when your hand is thin (to refill it) is worth more than a big off-suit card that doesn't do anything useful against the current court card. Hoard high cards and Diamonds for the fight that actually needs them rather than spending them on the first legal play.

Exact damage kills (see Winning & Losing below) are the one place precision *is* rewarded - the distinction is worth keeping straight: exact on offense is a bonus, exact on defense is not required.

## Jesters

Twice per run - once per Jester - you can discard your whole hand and draw a fresh one, on demand. No cost beyond the charge itself. Flipping both Jesters (rather than zero or one) affects your final conquest tier - see Winning below.

Crucially, a Jester can be flipped at **either** point in a turn: in place of a normal attack, **or** while an incoming attack is waiting to be covered. The second is what makes Jesters a genuine lifeline rather than a convenience - a hand that can't survive the hit in front of it becomes a fresh 8 cards that can.

One edge case the engine resolves on your behalf: an empty hand with both Jesters already spent is unrecoverable (Diamonds need a card in hand to play in the first place), so yielding there ends the run rather than looping forever.

## Last Stand

The **first time** your whole hand still can't cover an attack, you don't fall - **Last Stand** fires instead: the attempt still happens (your whole hand still goes to the discard pile), the shortfall is forgiven, and you **rally 4 fresh cards from the deck** so you have something to fight on with. This fires **once per run**, automatically. The next time it happens, the run ends in a loss for real.

The rally is a *draw*, not a return of the cards you just spent - those are by definition the ones that already failed to cover the hit. Four is a deliberate balance point: it cuts deaths shortly after a Last Stand from ~26% to ~10% without materially changing how hard the game is (see `cryptconquest.md`).

## Winning & Losing

* Defeat all **12 court cards** to **conquer the Necropolis** and win the run.
* Fail to cover an attack with your entire hand a **second** time (Last Stand already spent) and the run **ends in a loss**.
* Defeating a court card with the **exact** amount of damage recovers it face-down atop your tavern deck instead of sending it to the discard pile - so it's the very next card you draw, and it then fights *for* you.

A recruited court card is worth its own **attack** stat in your hand - a **Jack is 10, a Queen 15, a King 20** - and triggers its suit power at that value. These are by far the largest cards in the game (no number card exceeds 10), which makes an exact kill a much bigger prize than "a small edge": a recruited King paired with an Animal Companion is the single strongest play available.

### Conquest Tier

A won run is graded by how many Jesters you flipped along the way (Last Stand firing doesn't affect it):

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

The music also reacts to how the run is actually going: win and Triumph plays, lose and it's Death instead. Land in a fight your current hand genuinely can't survive and the music turns Frantic if Last Stand is still available, or Doom if it's already spent - there's no safety net left. The court card's own backdrop art slowly drifts and zooms while music plays, same Ken Burns treatment as Crypt Crawl's own crypt backdrops.

## Playing as a Guest

Crypt Conquest is playable **logged out** - a guest's run lives in their browser session only and is gone once that session ends, so it never reaches the leaderboard or earns a reward. Log in to have your runs saved to your account and counted toward both leaderboards.
