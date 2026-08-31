# Crypt Crawl

Crypt Crawl is a **solo Scoundrel-style dungeon-delve card game**, played with a single 44-card deck reskinned in Crypties NFT art. No party, no dice - just the deck, your HP, and whatever crypt turns up next. Every one of the 44 cards is hand-assigned to a specific Crypties NFT (from the Crypties - Season 2 collection) rather than drawn from a shuffled art pool - the highest-ranking enemy cards use the rarest pieces the owner holds.

Crypt Crawl is two separate pages. `cryptcrawlgame.php` is the public marketing page you land on from the site nav or homepage - a standalone page (no site nav), same treatment as [[games-skull-swap]] and Monstrocity: hero, feature highlights, two counter-scrolling rows showing every Crypties NFT actually used as card art, mechanics/tips/FAQ, and a "Start Delve" button that starts a real delve on `cryptcrawl.php`, ending in a footer (same style as Skull Swap's own) - it's the front door, not a page you need an escape hatch from. `cryptcrawl.php` is the game itself, and unlike the marketing page it's a normal in-platform page with the regular site nav (same as Missions, Dashboard, or any other staking page) - a simple rules prompt if you don't have a delve going, the live board while one's in progress, and a result screen (win/loss, crypts cleared, CARBON earned) once it ends, with a "Delve Again" button. See `skullpaper/MAINTENANCE.md` for why it's a normal nav'd page rather than standalone - that was tried and reverted.

## The Deck

44 cards, three kinds:

* **♦ Diamonds (2-10)** are **weapons**. Equip one and it stays equipped until you use it - there's only ever one weapon at a time.
* **♥ Hearts (2-10)** are **medkits**. They heal you.
* **♣♠ Clubs & Spades (2-14, Ace high)** are **enemies**. Fight them bare-handed or with your weapon.

## A Crypt (Room)

Four cards face up at a time:

* Resolve any **3 of the 4** and the last one carries into the next crypt alongside 3 fresh cards.
* **Fight an enemy** bare-handed and take its full rank in damage, or spend your equipped weapon and take only the difference between the enemy's rank and the weapon's power.
* A weapon **degrades** the moment it's used: it can only be used again on an enemy at or below the rank of the one it just fought.
* **Use a medkit** and heal by its rank. The first one you use in a crypt heals in full; any more after that in the *same* crypt still heal, just for **half** (rounded down, minimum 1) - so hoarding several in one crypt is never a total waste, just a worse trade than spacing them out.
* **Flee** a crypt you haven't touched yet (not twice in a row) to reshuffle all 4 cards back into the deck and draw a fresh crypt.

## Last Stand

The **first hit that would take you to 0 HP** in a delve doesn't - it leaves you standing at **1 HP** instead. This happens automatically, once per delve, whether you're fighting bare-handed or with a weapon. It's the one guaranteed save for a genuinely bad stretch (weapon worn out, flee already spent, a run of big enemies back to back) - after that one save, the next lethal hit ends the delve for real.

## Winning & Losing

* Clear the entire 44-card deck to **win** the delve.
* Run out of HP (after Last Stand is spent) and the delve **ends in a loss**.
* Giving up mid-delve counts as a loss too - there's no separate "abandoned" outcome.

## CARBON Per Delve

Every card you resolve - a weapon equipped, a medkit used, an enemy fought, win or lose - earns **10x its own rank** in CARBON, stacking up over the whole delve. A running total is visible right in the HUD as you play, and the moment your delve ends (cleared, died, or abandoned), the total is credited straight to your balance with a matching transaction and shown again on the game_over screen. Logged-in players only - a guest's delve still tracks the same total internally, but neither the HUD nor the result screen shows it, since there's no account to actually pay it into.

## Leaderboard & Rewards

Every completed delve (won or lost - an in-progress one doesn't count yet) feeds the Crypt Crawl leaderboard, ranked by **wins first, then your best single delve's crypt depth, then fewest losses**. See [[platform-leaderboards]].

* **All-Time** shows your career totals.
* **Weekly** resets each cycle and pays out - the **1st place** finisher earns **50,000 CARBON (= 500 DIAMOND)**, and the pool divides down the rankings from there (each rank gets roughly its share of the pool, same distribution [[games-gauntlets]] uses for its own weekly pool). Convertible to DIAMOND at 100:1 - see [[staking-crafting]].

Every completed delve also counts toward the platform's **Activity** leaderboards (all-time,
monthly, and weekly) - see [[platform-leaderboards]].

Every delve you finish while logged in - cleared or not - also posts a quick result to the Crypt Crawl Discord channel: how deep you got, illustrated with the theme art for the crypt you reached, the CARBON you earned that delve, plus a callout if it's a new personal best or puts you in 1st place (all-time and/or this week).

## Ambience

A small music player sits below the buttons on every screen - play/pause and
prev/next controls cycling between the theme song and its reprise, plus a
volume slider (starts at 50%, since the tracks are mixed loud). It's on by
default; your on/off choice, current track, playback position, and volume
stick for the rest of your browser session. Actions themselves no longer
reload the page either - fighting, healing, fleeing, all of it updates in
place - so the music just keeps playing straight through a delve instead of
restarting.

The music also reacts to how the delve is actually going, automatically:
score a win and Triumph plays; die and it's Death instead. Land in a crypt
where every enemy still standing would force your Last Stand no matter how
well you play it, and the music turns Frantic; if Last Stand is already
spent and that's still true, it turns to Doom - there's no safety net left.
None of these four are reachable through prev/next - the only way to hear
Triumph is to actually win. All of these transitions crossfade smoothly into
each other rather than cutting off - handy if you're darting in and out of
Frantic. Manually skipping tracks yourself with prev/next is still an
instant switch, on purpose.

The crypt's own backdrop art slowly drifts and zooms while music is playing
too - a different pan direction and pace every time, so it never settles
into one predictable pattern. It's on by default so you notice it, with a
🎥 button on the player to turn it off if it's more distracting than
atmospheric.

A 🔔 button on the player also lets you turn off the pop-up notices for
fleeing, medkits, and Last Stand specifically - handy once you know what
they say and just want them out of the way. Everything else (like the
Abandon Run confirmation) still shows regardless.

## Playing as a Guest

Crypt Crawl is playable **logged out** - a guest's delve lives in their browser session only and is gone once that session ends, so it never reaches the leaderboard or earns a reward. Log in to have your delves saved to your account and counted toward both leaderboards.
