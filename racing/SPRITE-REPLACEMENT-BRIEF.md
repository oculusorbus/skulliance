# Skull Racer — Sprite Replacement Brief

Reference doc for regenerating every raster asset in this folder into an
original "dark skull racer" theme. Every sprite listed here is currently
borrowed Genesis-era OutRun art (see this folder's own `README.md`) —
none of it is cleared for reuse, so this is a full replacement, not a
touch-up. Written to hand to an image-gen tool alongside the actual PNGs
as a reference sheet.

## Hard constraints (get these wrong and the game breaks, not just looks off)

- **Transparent background on every sprite/prop PNG.** These render over a
  procedurally-drawn sky/road, not their own background — any opaque
  background box will show as a visible box on the road.
- **Preserve each sprite's aspect ratio and rough silhouette.** The
  renderer scales sprites by width/height ratio to fake distance/depth;
  a car that's suddenly a different shape than the original will scale
  and clip oddly against the road perspective math in `common.js`.
- **Bottom-center of the image = the object's ground contact point.**
  All of these (trees, billboards, boulders, cars) are anchored at their
  visual base when placed on the road — keep whatever currently sits at
  the bottom edge sitting at the bottom edge.
- **Consistent light source across every piece.** These are all meant to
  read as one scene — pick one light direction (moonlight from upper-left
  is a reasonable fit for a night desert-highway theme) and hold it for
  every single sprite, not just within a batch.
- **The 3 background layers must tile seamlessly left-to-right.** Confirmed
  in `common.js`: each layer is drawn twice side-by-side to wrap as the
  camera pans horizontally. A visible seam at the left/right edge will be
  obvious and constant during play, not a one-off glitch.

## Canvas size, for scale reference

Game renders at **640×480** (`#racer`/`#canvas` in `common.css`). Sprites
this small on screen — verify silhouettes actually read clearly at that
size before finalizing, not just at full asset resolution.

## The 3 background layers (`background.png`, 1290×1470 combined sheet)

Each band is **1280×480**, stacked vertically in the source file with a
5px margin. Regenerate as 3 separate seamless-tiling images:

| Layer | Size | Notes |
|---|---|---|
| HILLS | 1280×480 | Midground — distant terrain silhouette |
| SKY | 1280×480 | Backmost layer — night sky |
| TREES | 1280×480 | Foreground-most parallax layer — treeline/skyline silhouette |

Suggested direction: SKY = night sky, moon, maybe a faint nebula/stars;
HILLS = distant skeletal/rocky ridgeline silhouette; TREES = closer
dead-tree or gravestone-topped ridge silhouette, all in the same cool
dark palette so the parallax layers read as depth, not as mismatched art.

## The 34 road sprites (`images/sprites/*.png`, combined into `sprites.png`)

All transparent PNG, all bottom-anchored. Grouped by shape family —
generate one consistent look per group rather than treating all 34 as
independent decisions, so the set holds together as one theme.

**Player car (6 — same car, 6 angles):** all same size, same vehicle,
different steering/hill angle. Suggest: skeletal/bone-plated hot-rod or
hearse-style car, teal or bone-white with dark chrome.

| Sprite | Size |
|---|---|
| PLAYER_STRAIGHT | 80×41 |
| PLAYER_LEFT | 80×41 |
| PLAYER_RIGHT | 80×41 |
| PLAYER_UPHILL_STRAIGHT | 80×45 |
| PLAYER_UPHILL_LEFT | 80×45 |
| PLAYER_UPHILL_RIGHT | 80×45 |

**Traffic vehicles (6):** other cars/trucks sharing the road. Keep
visually distinct from the player car (different silhouette/color) so
they're readable as obstacles at a glance.

| Sprite | Size |
|---|---|
| CAR01 | 80×56 |
| CAR02 | 80×59 |
| CAR03 | 88×55 |
| CAR04 | 80×57 |
| TRUCK | 100×78 |
| SEMI | 122×144 |

**Billboards (9):** roadside signage. Good place for skull/skeleton
iconography, cracked wood, weathered paint — these are the most
"branding-visible" props since they're large and text/graphic-friendly.

| Sprite | Size |
|---|---|
| BILLBOARD01 | 300×170 |
| BILLBOARD02 | 215×220 |
| BILLBOARD03 | 230×220 |
| BILLBOARD04 | 268×170 |
| BILLBOARD05 | 298×190 |
| BILLBOARD06 | 298×190 |
| BILLBOARD07 | 298×190 |
| BILLBOARD08 | 385×265 |
| BILLBOARD09 | 328×282 |

**Trees / foliage (7):** suggest dead/gnarled trees throughout rather than
healthy greenery, reserving one "special" tree design (e.g. the tall
PALM_TREE slot) for a standout landmark shape.

| Sprite | Size |
|---|---|
| PALM_TREE | 215×540 |
| TREE1 | 360×360 |
| TREE2 | 282×295 |
| DEAD_TREE1 | 135×332 |
| DEAD_TREE2 | 150×260 |
| BUSH1 | 240×155 |
| BUSH2 | 232×152 |

**Rocks / roadside debris (6):** boulders/column/stump/cactus. Good
candidates for gravestones, skull piles, broken statuary, cracked
obelisks instead of literal desert rocks.

| Sprite | Size |
|---|---|
| BOULDER1 | 168×248 |
| BOULDER2 | 298×140 |
| BOULDER3 | 320×220 |
| COLUMN | 200×315 |
| STUMP | 195×140 |
| CACTUS | 235×118 |

## UI icon (`images/mute.png`, 64×32)

Two 32×32 states side by side in one file, toggled via CSS
`background-position` (`common.css`): **x:0** = unmuted (default),
**x:-32** = muted (`.on` class). Keep the same 32×32-and-32×32
side-by-side layout — the CSS positions are hardcoded to that split.

## Suggested style direction

Dark navy/teal night palette (matches the rest of the Skulliance
platform's own branding) with warm accent color reserved for taillights,
embers, or lantern glow — gives the whole scene a clear focal-point
color against the cool palette. Skull/bone/gravestone motifs on the
props that read well at small size (billboards, rocks, columns);
skeletal silhouettes for trees/bushes rather than literal skull shapes
on foliage-scale objects, since fine detail won't survive at 150-300px.
