<?php
/*
 * admin-swaptiles.php -- Skull Swap tile treatments, side by side.
 *
 * The tiles are a flat background-color with a white glyph on top and one
 * drop shadow. This page puts candidate treatments next to the current one
 * on the real board colour, at the real geometry, with the real icons, so
 * the choice is made by looking rather than by describing.
 *
 * WHY A PAGE. Every treatment here is a few lines of CSS, and the only
 * question that matters is which one looks right at tile size on the board
 * -- which cannot be read off a diff. The owner is also usually on a phone,
 * where these are physically smaller than any mockup would suggest.
 *
 * READS ONLY, and it reads exactly what the game reads: the same projects
 * query skullswap.php runs for its tile set, so the glyphs are the real
 * ones rather than stand-ins.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();

/* The game's own palette and specials, copied from skullswap.php so this
   page cannot flatter a treatment with colours the board never uses. */
$st_palette = array('#800000', '#008080', '#408000', '#4B0082', '#666633', '#804000', '#004080');
$st_special = array('carbon' => '#333300', 'diamond' => '#000000');

/* The same query the game uses to build its tile set. */
$st_icons = array();
$r = @$conn->query("SELECT LOWER(currency) AS currency FROM projects
                    WHERE currency NOT IN ('DIAMOND','CARBON') AND currency != ''
                    GROUP BY LOWER(currency) ORDER BY currency ASC");
if ($r) while ($row = $r->fetch_assoc()) $st_icons[] = 'icons/' . $row['currency'] . '.png';
$st_icons = array_slice($st_icons, 0, 7);
while (count($st_icons) < 7) $st_icons[] = 'icons/skull.png';

$st_treatments = array(
	'flat' => array('Flat', 'What is on the board now: one colour, one outer shadow.'),
	'grad' => array('Gradient', 'A light-to-dark wash over the same colour. Nothing else changes.'),
	'emb'  => array('Gradient + emboss',
	                'The wash, plus a lit top edge and a dark bottom edge inside the tile.'),
	'deep' => array('Gradient + emboss + inset',
	                'As above with a deeper well and the glyph lifted off the face.'),
);

include 'header.php';
admin_chrome('swaptiles');
?>
<?php include 'admin-css.php'; ?>
<style>
/* The board, as the game draws it. */
/* Mirrors the live board: platform navy, and a 1px black border on every
   tile. Both were added after this page showed that the dark end of the
   palette had no edge against the old grey. */
.st-board { display:grid; grid-template-columns:repeat(9, 62px); gap:5px;
  background:#0a1929; padding:10px; width:max-content; max-width:100%; }
.st-t { width:62px; height:62px; position:relative; display:flex;
  align-items:center; justify-content:center; box-sizing:border-box; padding:3px;
  border:1px solid #000; }
.st-t img { width:75%; height:75%; object-fit:contain; position:relative; z-index:1; }

/* --- flat: today ------------------------------------------------------ */
.st-flat .st-t { box-shadow:0 1px 5px #000; }

/* --- gradient --------------------------------------------------------- */
/* Over the colour, not instead of it: background-image composites on top of
   background-color, so one rule works for all nine tiles and the per-tile
   hue the game sets inline is untouched. */
.st-grad .st-t { box-shadow:0 1px 5px #000;
  background-image:linear-gradient(180deg, rgba(255,255,255,.22) 0,
    rgba(255,255,255,.05) 45%, rgba(0,0,0,.16) 55%, rgba(0,0,0,.38) 100%); }

/* --- gradient + emboss ------------------------------------------------ */
.st-emb .st-t { background-image:linear-gradient(180deg, rgba(255,255,255,.24) 0,
    rgba(255,255,255,.05) 45%, rgba(0,0,0,.16) 55%, rgba(0,0,0,.40) 100%);
  box-shadow:inset 0 1px 0 rgba(255,255,255,.38),
             inset 0 -2px 0 rgba(0,0,0,.45),
             inset 1px 0 0 rgba(255,255,255,.10),
             inset -1px 0 0 rgba(0,0,0,.28),
             0 2px 4px rgba(0,0,0,.55); }
.st-emb .st-t img { filter:drop-shadow(0 1px 1px rgba(0,0,0,.5)); }

/* --- deeper ----------------------------------------------------------- */
.st-deep .st-t { background-image:linear-gradient(180deg, rgba(255,255,255,.28) 0,
    rgba(255,255,255,.06) 42%, rgba(0,0,0,.20) 58%, rgba(0,0,0,.48) 100%);
  box-shadow:inset 0 2px 0 rgba(255,255,255,.42),
             inset 0 -3px 0 rgba(0,0,0,.55),
             inset 2px 0 0 rgba(255,255,255,.12),
             inset -2px 0 0 rgba(0,0,0,.34),
             inset 0 0 14px rgba(0,0,0,.45),
             0 3px 6px rgba(0,0,0,.6); }
.st-deep .st-t img { filter:drop-shadow(0 2px 2px rgba(0,0,0,.65))
                            drop-shadow(0 0 5px rgba(255,255,255,.18)); }

.st-row { margin:0 0 26px; }
.st-row h4 { margin-bottom:2px; }
.st-row small { display:block; margin:0 0 10px; }
.st-side { display:flex; gap:20px; flex-wrap:wrap; align-items:flex-start; }
.st-note { font-size:.78rem; color:#5a7888; margin:6px 0 0; }
</style>
<div class="row"><div class="adm">
  <h3>Skull Swap tiles</h3>
  <small>
    The same nine tiles under each treatment, on the board's own grey, at the size
    they are played at. Colours and glyphs are the game's own, so nothing here is
    flattered by a palette the board never uses. Pick one and I will apply it.
  </small>

  <?php foreach ($st_treatments as $key => $t): ?>
  <div class="st-row">
    <h4><?php echo htmlspecialchars($t[0]); ?></h4>
    <small><?php echo htmlspecialchars($t[1]); ?></small>
    <div class="st-board st-<?php echo $key; ?>">
      <?php foreach ($st_icons as $i => $ic): ?>
        <div class="st-t" style="background-color:<?php echo $st_palette[$i % count($st_palette)]; ?>">
          <img src="<?php echo htmlspecialchars($ic); ?>" alt="" onerror="this.style.visibility='hidden'">
        </div>
      <?php endforeach; ?>
      <?php foreach ($st_special as $name => $col): ?>
        <div class="st-t" style="background-color:<?php echo $col; ?>">
          <img src="icons/<?php echo $name; ?>.png" alt="" onerror="this.style.visibility='hidden'">
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endforeach; ?>

  <div class="adm-card">
    <h4>Worth knowing before choosing</h4>
    <p class="adm-note">
      The two specials are the hardest case and the reason to look rather than
      guess: Carbon is near-black olive and Diamond is pure black, so a wash that
      reads well on the maroon or the teal can leave those two looking flat or,
      worse, muddy. Judge the treatment on the last two tiles of each row.
    </p>
    <p class="adm-note">
      None of this touches the palette itself. The seven colours are dark and a
      little muddy next to the rest of the platform, which is a separate question
      from the surface treatment -- and a riskier one, because seven tiles have to
      stay instantly distinguishable from each other mid-slide.
    </p>
  </div>
</div></div>
</html>
