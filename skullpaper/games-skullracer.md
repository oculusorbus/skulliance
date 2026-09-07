# Skull Racer

Skull Racer is a **pseudo-3D endless-highway racer** - press the gas, hold your line through the curves, and cross the finish line as fast as you can. It's built on Jake Gordon's MIT-licensed "javascript-racer" tutorial engine, completely reskinned into an original dark-wasteland theme (the tutorial's own sprite art and music were never MIT-licensed, so none of it survived the reskin) - a genuinely different kind of game from the card-based [[games-cryptcrawl]] and [[games-cryptconquest]], and the first one here that's entirely client-side: the whole race runs in your browser with no server round-trip per frame, and only reports back once, at the finish line.

`skullracer.php` is the page linked in the site nav (Play > Skull Racer) - it wraps the game in the normal Skulliance header so you can navigate away and back like any other page, and it works whether you're logged in or just visiting.

## The Race

A race is **3 laps** of a fixed, curvy highway - sized against the game's own chiptune soundtrack (a theme plus 2 more tracks, ~392 seconds back to back), so a typical race runs long enough to hear all of it start without dragging on. Traffic cars and roadside obstacles are randomized each lap, so no two races play out quite the same way even on the same track - a genuine "luck factor" alongside raw driving skill.

* **Lap** and **Fastest Lap** track in the HUD as you drive, same convention as the underlying engine's own dev build.
* **Total Time** is the sum of all 3 laps - the number the leaderboard actually ranks on.
* A **minimap** in the corner traces the track's real shape (reconstructed from the same curve data the 3D view renders from) with a dot marking your current position and a white tick at the start/finish line.
* Crossing the finish line after lap 3 ends the race outright - no infinite loop, no restart without choosing to.
* Up to **three translucent ghosts** drive alongside you every lap: your own fastest lap (plain), the current weekly leader's (gold), and the current all-time leader's (gold). Beat your own and it updates immediately, right there in the same race. All three are pure visual references, not real cars - driving through one (or it through a boost pad/jump ramp that wasn't there when it was recorded) is normal, not a bug. Whenever a run of yours holds more than one tier at once, the lower duplicate is hidden rather than stacking identical ghosts on top of each other - all-time over weekly, and either over your own personal-best if that's the very same run.
* Each ghost's icon (🎖️/🥇/🏆) floats above it always, gold-tinted like the car itself - that's what stays visible tracking it through the terrain, over a hill, past the draw-distance horizon, even when the car itself is out of sight. A weekly or all-time leader who has a Discord avatar also gets it decaled onto their ghost car's rear grille, in full color (not gold-tinted like the rest of the ghost) so it's actually recognizable once you're close enough to see it - a complement to the icon, not a replacement. No avatar linked (or your own personal-best ghost, which is always you) just shows the icon alone.

## Crests & Boost Pads

Carrying near-top speed over a steep enough rise sends your car briefly airborne - it never touches steering or speed, but while you're up there traffic can't touch you either. Time a jump right over a car in your lane and you sail clean over it instead of crashing. Off-road obstacles (billboards, rocks) are unaffected by a jump - those are a lane mistake, not something you're meant to dodge.

Each boost pad has its own jump ramp waiting further down that same straightaway, same lane as the pad so grabbing the boost lines you up for it automatically. Unlike cresting a hill, a ramp always launches you at qualifying speed, no exceptions - even right after a natural crest jump moments earlier. So a well-timed ramp jump is the difference between keeping your 180 mph run alive and getting knocked out of it by a car in the way.

Each of the track's four longest straightaways hides a bright striped power-up strip laid across one lane, right at the start of the straight. Drive over it and you're instantly boosted to 180 mph for the rest of that straightaway, dropping back to normal top speed the moment it ends. Which lane it's in is random every race, so you can't just memorize a line and ignore the road - you have to actually react. Crash while boosting and the boost ends outright - you only keep the full run by staying crash-free.

## Traffic

Tuck in close behind a car in your lane for a moment and you'll draft it - a small, continuous top-speed bump for as long as you hold the position. Break off or crash and it's gone; get back on someone's bumper and it builds again.

Traffic passing close by gets a quick, panned engine blip as it goes - a car passing on your left sounds like it's on your left, not just louder - so the road feels populated even with your eyes on what's ahead.

A crash doesn't just snap your speed anymore - it's a screen shake and a puff of dust at the point of impact. The car's own art is a fixed rear-view sprite (no side or front angle exists to turn into), so it's not an actual spin - same "shake and a puff" trick era-appropriate arcade racers leaned on instead of true rotation.

## Controls

**Desktop:** arrow keys or WASD to steer/accelerate/brake, space bar also brakes - the very first keypress just works, no click into the game window needed first. **Mobile:** driving is automatic (no need to hold anything just to go forward) - the ◀/BRAKE/▶ buttons below the game view are tall (30% of your screen height, not a thin strip), so there's much less empty margin around them where a stray tap can land on the page instead and pop up your phone's copy/select menu. Hold to steer or brake, any combination at once (steer left while braking, etc.) works exactly like holding multiple keys would on a keyboard. Your own car's tail lights light up bright orange the instant you brake, same car sprite otherwise - visual feedback for the one control that has none of its own sound effect.

**Controller:** a PS4/PS5 controller paired over Bluetooth (phone or desktop) works too, no app or extra setup beyond pairing it in your device's own Bluetooth settings - press any button on it once to let the game notice it. D-pad or the left stick steers, □ is gas, ✕ is brake (rock your thumb between them for quick on/off braking); R2/L2 work too as alternates. Connecting one turns mobile's auto-gas off automatically - gas becomes a button you hold, like a real racing game - and turns it back on if you disconnect. Options toggles sound on/off, same as tapping the mute icon. If the game ever stops responding to the controller, pressing any regular button hands control straight back, instantly - avoid the PS/Home button specifically to get there, though: on iPhone that one opens Game Center system-wide for every app, a phone-level shortcut this page has no way to override. On iPhone the very first *touch* of the screen is sometimes still what actually starts the music, even when you're driving entirely on the controller - the phone only lets a page begin audio from gestures it recognises, and it doesn't always count a controller button as one.

**No sound on an iPhone?** Check the ringer switch on the side of the phone. Skull Racer's audio follows it, so with the phone on silent the game is silent too - unlike video sites, which are allowed to ignore it. Flick the ringer on and the music and engine come back.

**Landscape on a phone** sizes the game screen itself to actually fit the shorter, wider view (rather than the portrait layout's math just running into the ceiling), and the control hint steps aside to make room. Visited through the site's own nav (Play > Skull Racer, not the standalone game file directly), the site menu and its chrome step aside too - just the game screen, full height. Built with connecting to a TV in mirroring mode in mind - flip the phone sideways for a bigger view on the big screen.

Touch controls move rather than disappear. ◀ sits on the left edge, ▶ on the right, each taking up the bottom 3/4 of that side; the top 1/4 of EACH side is its own BRAKE button, so braking is always one thumb-length away no matter which hand is more comfortable hitting it in the moment - not a single shared button forcing one side to own it. A connected controller still works exactly the same in this mode; if it disconnects mid-race, the touch buttons are right there as a fallback instead of leaving you with no way to steer.

The game screen itself goes properly widescreen here too, not just centered with empty space on either side - it fills the room between the two button columns, showing genuinely more of the road's sides rather than a taller or stretched version of the same view.

## CARBON & Leaderboard

Finishing a race (any time) earns a flat CARBON credit, paid the moment the race ends - separate from the leaderboard pool below, same two-tier shape [[games-cryptcrawl]] uses (a per-action reward on top of a weekly pool). Logged-in players only; a guest's race still finishes and shows a result, there's just no account to save it to or pay into.

Every finished race feeds the Skull Racer leaderboard, ranked by **your own best (fastest) Total Time**, fastest lap as the tiebreak - the opposite ranking direction from Crypt Crawl/Crypt Conquest's "most wins," since a race is won by being fastest, not by accumulating victories. See [[platform-leaderboards]].

* **All-Time** shows your career best.
* **Weekly** resets each cycle and pays out - same weekly cadence and pool size as [[games-cryptcrawl]]: the **1st place** finisher earns **50,000 CARBON (= 500 DIAMOND)**, dividing down the rankings from there. Convertible to DIAMOND at 100:1 - see [[staking-crafting]].

Every race you finish while logged in also posts a quick result to the Skull Racer Discord channel - your total time and fastest lap, plus a callout if it's a new personal best or puts you in 1st place (all-time and/or this week).

The race-complete screen has a **Weekly Leaderboard** button right next to Race Again, so you can check where your run landed without hunting through the site's own leaderboard filters.

## Playing as a Guest

Skull Racer is playable **logged out** - a guest's race finishes and shows a result screen same as anyone else's, it just never reaches the leaderboard or earns CARBON. Log in to have your races saved to your account and counted toward the leaderboard.
