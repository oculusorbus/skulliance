# Skull Racer

Skull Racer is a **pseudo-3D endless-highway racer** - press the gas, hold your line through the curves, and cross the finish line as fast as you can. It's built on Jake Gordon's MIT-licensed "javascript-racer" tutorial engine, completely reskinned into an original dark-wasteland theme (the tutorial's own sprite art and music were never MIT-licensed, so none of it survived the reskin) - a genuinely different kind of game from the card-based [[games-cryptcrawl]] and [[games-cryptconquest]], and the first one here that's entirely client-side: the whole race runs in your browser with no server round-trip per frame, and only reports back once, at the finish line.

`skullracer.php` is the page linked in the site nav (Play > Skull Racer) - it wraps the game in the normal Skulliance header so you can navigate away and back like any other page, and it works whether you're logged in or just visiting.

## The Race

A race is **3 laps** of a fixed, curvy highway - sized against the game's own 4-track chiptune soundtrack (~505 seconds back to back), so a typical race runs long enough to hear all four tracks start without dragging on. Traffic cars and roadside obstacles are randomized each lap, so no two races play out quite the same way even on the same track - a genuine "luck factor" alongside raw driving skill.

* **Lap** and **Fastest Lap** track in the HUD as you drive, same convention as the underlying engine's own dev build.
* **Total Time** is the sum of all 3 laps - the number the leaderboard actually ranks on.
* A **minimap** in the corner traces the track's real shape (reconstructed from the same curve data the 3D view renders from) with a dot marking your current position and a white tick at the start/finish line.
* Crossing the finish line after lap 3 ends the race outright - no infinite loop, no restart without choosing to.

## Crests & Boost Pads

Carrying near-top speed over a steep enough rise sends your car briefly airborne - it never touches steering or speed, but while you're up there traffic can't touch you either. Time a jump right over a car in your lane and you sail clean over it instead of crashing. Off-road obstacles (billboards, rocks) are unaffected by a jump - those are a lane mistake, not something you're meant to dodge.

Each boost pad has its own jump ramp waiting further down that same straightaway, same lane as the pad so grabbing the boost lines you up for it automatically. It's the same jump as cresting a hill, just deliberately placed instead of left to chance - so a well-timed ramp jump is the difference between keeping your 180 mph run alive and getting knocked out of it by a car in the way.

Each of the track's four longest straightaways hides a bright striped power-up strip laid across one lane, right at the start of the straight. Drive over it and you're instantly boosted to 180 mph for the rest of that straightaway, dropping back to normal top speed the moment it ends. Which lane it's in is random every race, so you can't just memorize a line and ignore the road - you have to actually react.

## Controls

**Desktop:** arrow keys or WASD to steer/accelerate/brake, space bar also brakes. **Mobile:** driving is automatic (no need to hold anything just to go forward) - hold the ◀/▶ buttons to steer, hold BRAKE to slow down, any combination at once (steer left while braking, etc.) works exactly like holding multiple keys would on a keyboard.

## CARBON & Leaderboard

Finishing a race (any time) earns a flat CARBON credit, paid the moment the race ends - separate from the leaderboard pool below, same two-tier shape [[games-cryptcrawl]] uses (a per-action reward on top of a weekly pool). Logged-in players only; a guest's race still finishes and shows a result, there's just no account to save it to or pay into.

Every finished race feeds the Skull Racer leaderboard, ranked by **your own best (fastest) Total Time**, fastest lap as the tiebreak - the opposite ranking direction from Crypt Crawl/Crypt Conquest's "most wins," since a race is won by being fastest, not by accumulating victories. See [[platform-leaderboards]].

* **All-Time** shows your career best.
* **Weekly** resets each cycle and pays out - same weekly cadence and pool size as [[games-cryptcrawl]]: the **1st place** finisher earns **50,000 CARBON (= 500 DIAMOND)**, dividing down the rankings from there. Convertible to DIAMOND at 100:1 - see [[staking-crafting]].

Every race you finish while logged in also posts a quick result to the Skull Racer Discord channel - your total time and fastest lap, plus a callout if it's a new personal best or puts you in 1st place (all-time and/or this week).

## Playing as a Guest

Skull Racer is playable **logged out** - a guest's race finishes and shows a result screen same as anyone else's, it just never reaches the leaderboard or earns CARBON. Log in to have your races saved to your account and counted toward the leaderboard.
