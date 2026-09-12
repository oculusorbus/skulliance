# Player Car — realistic set, remaining work

The straight-on view is done (`sprites-realistic/player_straight.png`, 804x459)
and its brake variant was generated from it. **Five poses still need drawing.**
They are genuine re-draws, not transforms — measured against the pixel-art
originals, a turned car differs from the straight one by 30% of its pixels, and
the uphill set is a taller canvas (80x45 vs 80x41), so neither can be derived.

## What to draw

| file to produce | pose | derive from |
|---|---|---|
| `player_left.png` | front 3/4, car banked LEFT | draw |
| `player_right.png` | mirror of left | **free — I can flip it** |
| `player_uphill_straight.png` | straight, seen cresting a rise | draw |
| `player_uphill_left.png` | uphill + banked left | draw |
| `player_uphill_right.png` | mirror of uphill_left | **free — I can flip it** |

So **three** actual drawings: one left-banked, one uphill-straight, one
uphill-left. I mirror the two right-hand poses, and generate all six brake
variants by the measured colour shift.

## Hard requirements

Match `player_straight.png`, which is the reference:

- **Canvas 804x459**, content 744x437, aspect **1.703**
- **Bottom edge flush.** Content must touch the bottom of the canvas — zero
  transparent rows below it. `Render.sprite` anchors bottom-centre (`offsetY -1`),
  so any bottom padding floats the car above the road.
- **Horizontally centred** to within a pixel.
- **PNG with real alpha.** No matte, no white box, no semi-transparent halo —
  these composite over a procedurally drawn road.
- **Same car, same camera distance, same light direction** as the straight view.
  The poses cut between each other every few frames; any drift in size or
  lighting reads as the car changing shape while you steer.

### The uphill poses

The pixel-art uphill sprites are **80x45** against straight's 80x41 — 10% taller.
So the uphill renders should be proportionally taller too: at this resolution,
roughly **804x504 canvas**. Same width, more headroom — the car is pitched back.

### The turn angle

Measured off the originals, the banked poses are a modest 3/4 rotation, not a
hard turn — the car stays mostly rear-on with the far side just visible. Keep
it subtle: this sprite swaps in and out at speed, and a big angle change
strobes.

## When they arrive

Drop them in `images/sprites-realistic/` and tell me. I will: verify each crop,
mirror the two right-hand poses, generate all six brake variants, and replace
the stopgap entries in `REALISTIC_SPRITES` so the lean animation comes back.

Until then every player pose points at the straight render, so the car looks
right but does not bank into corners.
