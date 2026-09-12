# Player Car — realistic set, remaining work

The straight-on view is done (`sprites-realistic/player_straight.png`, 804x459)
and its brake variant was generated from it. **Five poses still need drawing.**
They are genuine re-draws, not transforms — measured against the pixel-art
originals, a turned car differs from the straight one by 30% of its pixels, and
the uphill set is a taller canvas (80x45 vs 80x41), so neither can be derived.

## Status

| file | state |
|---|---|
| `player_straight.png` | ✅ done |
| `player_left.png` | ✅ done — **windows still checkered** |
| `player_right.png` | ✅ mirrored from left |
| `player_uphill_straight.png` | ❌ still needed |
| `player_uphill_left.png` | ❌ still needed |
| `player_uphill_right.png` | free — mirror of uphill_left |

All six brake variants are generated automatically.

**One outstanding defect: the baked transparency checkerboard in the left/right
windows.** It cannot be removed from the flattened PNG. Measured brightness
percentiles (10th/50th/90th):

```
  checkerboard      29    50    60
  the skull         29    87   155
  car paint         54    82   106
```

The checker sits inside both the skull's shadow range and the paint's range, so
no threshold takes it without eating one of the other two — attempting it
chewed holes in the skull. It has to be fixed where the layers still exist, in
the generator or an editor, exactly as the straight pose was.

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

## Which pose is which — use the roof-vs-body offset

Getting this backwards is easy and I did. Silhouette overlap against the
pixel-art originals was NOT reliable: it scored 0.759 vs 0.746, a 0.013 margin,
and it picked wrong.

The signal that actually works, measured on the realistic art: the horizontal
centre of the **greenhouse** (top 35% of the car) relative to the centre of the
**widest body row**.

```
  correct player_left    roof-body  -0.087   (roof sits LEFT of the body)
  correct player_right   roof-body  +0.087
```

Magnitude ~0.087 and unambiguous. Do not calibrate this from the 80x41
originals — there the same measure reads ±0.012, which is a few pixels and
indistinguishable from noise, and that is why it misled.

Apply the same test to the uphill poses when they arrive.

## Sizing a new pose — match car HEIGHT, not canvas width

`dw` scales the canvas, and renders of different poses do not frame the car
identically. The banked renders came in at content aspect 1.863 against the
straight pose's 1.699, so at a shared `dw: 80` the car rendered 43.6px tall
straight and 39.7px banked — it visibly shrank every time you steered.

The invariant is the car's height: a car does not get shorter when it turns, it
gets wider as more of its flank comes into view.

**So pick `dw` per pose such that `contentHeight * (dw / canvasWidth) = 43.6`.**

```
  player_straight   804x460, content 744x438   ->  dw 80   (43.6px tall)
  player_left/right 677x353, content 626x336   ->  dw 88   (43.7px tall)
```

This never affects the hitbox: `playerW` reads
`spriteLayoutW(SPRITES.PLAYER_STRAIGHT)` specifically, not the current pose.

Better still, frame new renders at the straight pose's own content aspect
(1.699) and `dw: 80` works directly.

## Why the uphill poses matter more than they look

`Render.player` (common.js:687) picks the pose from steer AND gradient:

```js
if (steer < 0) name = (updown > 0) ? 'PLAYER_UPHILL_LEFT' : 'PLAYER_LEFT';
```

So steering while climbing asks for the UPHILL pose, not the flat one. While
those pointed at the straight render, the car banked on level road and went
rigid on every rise — which read as the lean animation working only sometimes.

As a stopgap the uphill keys now borrow the BANKED flat poses, so steering
always leans; the only thing missing is the ~10% backward pitch on a climb.
When the real uphill art lands, point these at it and the pitch returns.
