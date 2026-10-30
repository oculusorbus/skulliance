#!/usr/bin/env python3
"""
make-web.py -- build the web derivatives of the DHC2 trait art.

    python3 dhc/make-web.py --check              report, write nothing
    python3 dhc/make-web.py --run background     write one category
    python3 dhc/make-web.py --run weapon/axe     write one trait

NAMING A TARGET IS REQUIRED and there is no "build everything", because the
index's `source` is PROVENANCE AND NOT A BUILD INPUT. Fourteen pieces on disk
are hand-edited away from the file they came from, and a blanket run would
have quietly replaced every one of them with a fresh render of the original:

  scythe / scythe-sash and skull-krusher / skull-krusher-sash each name ONE
  source and were separated by hand into a weapon and the sash it hangs from.
  Rebuilding either half from that source makes the two identical.

  axe lost its torso chains by hand, because another trait supplies them.

  eight heads were cut out of a larger image -- 64,000 pixels on disk against
  300,000 in the source.

  xlon-s-code-attack is three times the source's footprint.

HAND_EDITED below is that measured list, and it is refused even when named.
Add to it, never silently rebuild past it. The check is available any time:
compare the alpha footprint on disk against a fresh render and look at the
intersection over union -- these fourteen all come in under 97%.

Reads dhc/web/trait-index.json for the slug -> source mapping, finds each
source under dhc/_originals (or the two delivery folders), and writes
dhc/web/1000/<cat>/<slug>.png and dhc/web/250/<cat>/<slug>.png.

The output is gitignored and goes to the server by FTP. This script is not.
It used to be a code block in TRAIT-RECONCILIATION.md that got retyped at
each delivery, which is how the two rules below came to be applied by hand.

-----------------------------------------------------------------------------
RULE 1: A FULL-CANVAS DOWNSCALE, NEVER A CROP OR A TRIM.

Every trait is drawn on the same 2500x2500 canvas and that is exactly what
makes the layers stack without nudging. Trimming one to its bounding box
would save a few KB and move the piece.

RULE 2: BACKGROUNDS ARE DITHERED, EVERYTHING ELSE IS NOT.

Both are PNG-8, because 256 colours is invisible on character art and a third
of the size. On a background it is not invisible at all: a background is the
one layer that is full-frame and carries a smooth gradient across hundreds of
pixels, and a 256-colour palette with no dithering turns that into flat
plateaus with a visible step between them. Reported on the XLONs Room
Entrance, whose green doorway glow banded into rings.

Measured on that background, over the glow -- the fraction of colour changes
along a scanline that jump by 3 or more, where a smooth gradient should be
mostly 1:

    source                   35.4%      (21,046 steps)
    FASTOCTREE, no dither   100.0%      ( 5,994 steps)  <- every change visible
    MAXCOVERAGE, no dither  100.0%      ( 7,615 steps)
    ADAPTIVE + Floyd-Steinberg 38.1%    (19,181 steps)  <- what we do now

So it is the DITHER that fixes it rather than a better palette: a smarter
quantiser picks better colours and still lands them in flat blocks. The cost
is file size, because dithering is noise and noise does not deflate -- 66K to
219K on average at 1000px. One background loads at a time, which makes that a
normal full-frame image; the 45 250px thumbnails load together in the picker
and come to 0.88MB against 0.38MB.

WHY THE RULE IS SAFE, and why it is written as "opaque" rather than as a list
of categories: PIL's ADAPTIVE palette conversion works in RGB and drops the
alpha channel. Dithering a trait with transparency would flatten its cutout
onto black. Checked across the whole set -- all 45 backgrounds are fully
opaque and all 176 other traits carry transparency, with no exceptions either
way -- so the test is a property of the art, and a future background with a
transparent corner falls back to the safe path on its own rather than being
silently mangled by a category name.
"""
import io, json, os, sys
from PIL import Image

# Measured 2026-10-30: every trait whose art on disk disagrees with a fresh
# render of its named source by more than 3% of its own footprint. These are
# edits that live only in the web derivative, so the derivative IS the master.
HAND_EDITED = {
    'weapon/scythe', 'weapon/scythe-sash',
    'weapon/skull-krusher', 'weapon/skull-krusher-sash',
    'weapon/axe',
    'effects/xlon-s-code-attack',
    'head/inferno-wasp-guardian', 'head/c73', 'head/dh-cop-2',
    'head/planet-8tz-specter', 'head/golden-cyborg', 'head/silver-cyborg',
    'head/mk200', 'head/dh-alien-punk',
}

HERE  = os.path.dirname(os.path.abspath(__file__))
WEB   = os.path.join(HERE, 'web')
SIZES = (1000, 250)
# _originals first: a delivery folder may still hold a superseded copy.
SOURCE_DIRS = ('_originals', 'Missing Traits', 'DHC New traits')


def source_map():
    """filename -> path, searched in SOURCE_DIRS order."""
    found = {}
    for root in SOURCE_DIRS:
        base = os.path.join(HERE, root)
        for dirpath, _, files in os.walk(base):
            for f in files:
                found.setdefault(f, os.path.join(dirpath, f))
    return found


def render(src, size):
    """The bytes we would write for this source at this size."""
    im = Image.open(src).convert('RGBA').resize((size, size), Image.LANCZOS)
    opaque = im.getchannel('A').getextrema() == (255, 255)
    if opaque:
        # Rule 2. ADAPTIVE works in RGB; safe here precisely because opaque.
        out = im.convert('RGB').convert('P', palette=Image.ADAPTIVE, colors=256,
                                        dither=Image.FLOYDSTEINBERG)
    else:
        # Transparency must survive, so no dither and no RGB round trip.
        out = im.quantize(colors=256, method=Image.FASTOCTREE)
    buf = io.BytesIO()
    out.save(buf, 'PNG', optimize=True)
    return buf.getvalue()


def main():
    run  = '--run' in sys.argv
    only = [a for a in sys.argv[1:] if not a.startswith('--')]
    if run and not only:
        print('Name a category or a trait to write. There is no build-everything:\n'
              'see the note at the top of this file.', file=sys.stderr)
        return 2
    idx  = json.load(open(os.path.join(WEB, 'trait-index.json')))
    found = source_map()

    missing, changed, same, refused = [], [], 0, []
    for cat, items in sorted(idx.items()):
        for slug, v in sorted(items.items()):
            key = cat + '/' + slug
            if only and cat not in only and key not in only:
                continue
            if key in HAND_EDITED:
                # Reported even when not targeted, so the list stays visible.
                if only:
                    refused.append(key)
                continue
            src = found.get(v['source'])
            if not src:
                missing.append(f"{cat}/{slug}  (source {v['source']})")
                continue
            for size in SIZES:
                dest = os.path.join(WEB, str(size), cat, slug + '.png')
                data = render(src, size)
                old  = open(dest, 'rb').read() if os.path.exists(dest) else None
                if old == data:
                    same += 1
                    continue
                changed.append((dest, len(old) if old else 0, len(data)))
                if run:
                    os.makedirs(os.path.dirname(dest), exist_ok=True)
                    with open(dest, 'wb') as fh:
                        fh.write(data)

    for m in missing:
        print('  NO SOURCE  ' + m)
    for r in refused:
        print('  HAND-EDITED, not rebuilt  ' + r)
    for dest, was, now in changed:
        rel = os.path.relpath(dest, HERE)
        print(f"  {'wrote' if run else 'differs':8} {rel:46} {was//1024:4}K -> {now//1024:4}K")
    print(f"\n{len(changed)} file(s) {'written' if run else 'differ'}, {same} already correct, "
          f"{len(missing)} missing a source")
    return 1 if missing else 0


if __name__ == '__main__':
    sys.exit(main())
