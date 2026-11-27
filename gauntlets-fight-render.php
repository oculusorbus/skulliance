<?php
/**
 * gauntlets-fight-render.php -- the fight, as markup.
 *
 * ONE COPY OF THIS MARKUP, rendered by two callers: gauntlets.php's own
 * GET (full page load, and the no-JS POST->redirect->GET fallback) and
 * ajax/gauntlet-fight.php (fragment response after a turn, so the client
 * swaps #gf-area in place without a real navigation).
 *
 * WHY THE AJAX PATH EXISTS AT ALL, and it is not speed: the music. A
 * full page reload tears down and rebuilds the <audio> element every
 * time, which audibly stutters it -- Crypt Crawl hit exactly this and
 * says so in cryptcrawl-render.php. A fight has many more actions than
 * the old three clicks, so on a reload-per-turn page the soundtrack
 * would restart constantly. The <audio> lives OUTSIDE #gf-area in
 * gauntlets.php for the same reason.
 *
 * The fragment carries its own timeline in data-tl, so the client
 * animates and sounds exactly what the server decided and nothing else.
 */

/** Health bar with a damage trail and the figure on the bar itself. */
function gf_bar($hp, $max, $side) {
	$max = max(1, (int)$max);
	$pct = max(0, min(100, round(100 * (int)$hp / $max)));
	$cls = $pct > 50 ? 'ok' : ($pct > 25 ? 'warn' : 'crit');
	ob_start(); ?>
	<div class="gf-bar <?php echo $cls; ?>" data-side="<?php echo htmlspecialchars($side); ?>">
		<div class="gf-trail" style="width:<?php echo $pct; ?>%"></div>
		<div class="gf-fill"  style="width:<?php echo $pct; ?>%"></div>
		<?php /* TWO COPIES, clipped at the fill edge. One ink cannot read
		         against both the fill and the empty rail: white measures
		         about 1.6:1 on the yellow band, which is the exact
		         complaint the Monstrocity bar got. Near-black over the
		         colour, white over the rail. */ ?>
		<span class="gf-num" style="--hp-pct:<?php echo $pct; ?>%">
			<span class="gf-ink gf-ink-on"><?php echo max(0, (int)$hp); ?> / <?php echo $max; ?></span>
			<span class="gf-ink gf-ink-off"><?php echo max(0, (int)$hp); ?> / <?php echo $max; ?></span>
		</span>
	</div>
	<?php return ob_get_clean();
}

/**
 * $enc      the encounter row (player + opponent names, art, user)
 * $fight    engine state
 * $loadout  gaunt_loadout() -- the SAME list the turn endpoint validates
 *           against, so what is offered and what is allowed cannot differ
 */
function gauntletRenderFight($enc, $fight, $loadout, $csrf = '') {
	$over = !empty($fight['over']);
	$won  = ($fight['win'] ?? '') === 'you';

	/* Cues for this turn, in order, read off the timeline the engine
	   produced. The client plays them; the no-JS path simply ignores them. */
	$sfx = array();
	foreach (($fight['log'] ?? array()) as $e)
		if (!empty($e['sfx'])) $sfx[] = $e['sfx'];
	$sfx = array_slice($sfx, -4);

	/* Only the tail is handed over -- the whole log would grow without
	   bound across a long fight and the client only animates what just
	   happened. */
	$tail = array_slice($fight['log'] ?? array(), -6);
	?>
<div id="gf-area"
     class="gf<?php echo $over ? ($won ? ' gf-won' : ' gf-lost') : ''; ?>"
     data-sfx="<?php echo htmlspecialchars(implode(' ', $sfx)); ?>"
     data-tl="<?php echo htmlspecialchars(json_encode($tail)); ?>"
     data-hp="<?php echo (int)$fight['you']['hp']; ?>"
     data-hpmax="<?php echo (int)$fight['you']['max']; ?>">

  <div class="gf-arena">
    <div class="gf-side gf-you">
      <div class="gf-portrait">
        <img src="<?php echo htmlspecialchars($enc['player_img']); ?>" alt=""
             onerror="this.src='icons/skull.png'">
        <div class="gf-fx"></div>
      </div>
      <div class="gf-name"><?php echo htmlspecialchars($enc['player_nft_name']); ?></div>
      <?php echo gf_bar($fight['you']['hp'], $fight['you']['max'], 'you'); ?>
      <?php if ((int)$fight['you']['arm'] > 0): ?>
        <div class="gf-armour" title="Armour soak remaining">
          <span class="gf-shield">&#9679;</span> <?php echo (int)$fight['you']['arm']; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="gf-vs">VS</div>

    <div class="gf-side gf-foe">
      <div class="gf-portrait">
        <img src="<?php echo htmlspecialchars($enc['opponent_img']); ?>" alt=""
             onerror="this.src='icons/skull.png'">
        <div class="gf-fx"></div>
      </div>
      <div class="gf-name"><?php echo htmlspecialchars($enc['opponent_nft_name']); ?></div>
      <?php echo gf_bar($fight['foe']['hp'], $fight['foe']['max'], 'foe'); ?>
      <?php if ((int)$fight['foe']['arm'] > 0): ?>
        <div class="gf-armour"><span class="gf-shield">&#9679;</span> <?php echo (int)$fight['foe']['arm']; ?></div>
      <?php endif; ?>
    </div>
  </div>

  <ul class="gf-log">
    <?php foreach (array_reverse($tail) as $e): ?>
      <li class="gf-li gf-<?php echo htmlspecialchars($e['who']); ?> gf-k-<?php echo htmlspecialchars($e['kind']); ?>">
        <span class="gf-who"><?php echo $e['who'] === 'you' ? 'You' : 'Them'; ?></span>
        <?php echo htmlspecialchars(($e['name'] ?? '') !== '' ? $e['name'] . ' ' : ''); ?>
        <strong><?php echo htmlspecialchars($e['text']); ?></strong>
        <?php if (!empty($e['soaked'])): ?>
          <span class="gf-soak">(<?php echo (int)$e['soaked']; ?> soaked)</span>
        <?php endif; ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($over): ?>
    <div class="gf-result <?php echo $won ? 'win' : 'loss'; ?>">
      <?php echo $won ? 'Opponent down' : 'Your fighter is down'; ?>
    </div>
    <form method="post" class="gf-actions">
      <input type="hidden" name="action" value="close_fight">
      <input type="hidden" name="encounter_id" value="<?php echo (int)$enc['id']; ?>">
      <button type="submit" class="gf-btn gf-go">Continue</button>
    </form>
  <?php else: ?>
    <form method="post" class="gf-actions" id="gf-form">
      <input type="hidden" name="action" value="fight_turn">
      <input type="hidden" name="encounter_id" value="<?php echo (int)$enc['id']; ?>">

      <button type="submit" name="turn" value="bare" class="gf-btn gf-bare" data-sfx="fist">
        <img src="icons/fist.png" alt="" onerror="this.style.display='none'">
        <span>Bare hands</span>
      </button>

      <?php foreach ($loadout['weapons'] as $w):
        $dis = $w['left'] < 1; ?>
        <button type="submit" name="turn" value="weapon:<?php echo (int)$w['id']; ?>"
                class="gf-btn gf-wpn<?php echo $dis ? ' is-spent' : ''; ?>"
                <?php echo $dis ? 'disabled' : ''; ?>
                data-sfx="<?php echo htmlspecialchars($w['sfx'] ?: 'melee'); ?>">
          <img src="icons/<?php echo htmlspecialchars($w['slug']); ?>.png" alt=""
               onerror="this.style.display='none'">
          <span><?php echo htmlspecialchars($w['name']); ?></span>
          <em class="gf-left"><?php echo (int)$w['left']; ?></em>
        </button>
      <?php endforeach; ?>

      <?php foreach ($loadout['armor'] as $a):
        $dis = $a['left'] < 1; ?>
        <button type="submit" name="turn" value="armor:<?php echo (int)$a['id']; ?>"
                class="gf-btn gf-arm<?php echo $dis ? ' is-spent' : ''; ?>"
                <?php echo $dis ? 'disabled' : ''; ?> data-sfx="equip">
          <span><?php echo htmlspecialchars($a['name']); ?></span>
          <em class="gf-left"><?php echo (int)$a['left']; ?></em>
        </button>
      <?php endforeach; ?>

      <?php foreach ($loadout['heals'] as $h): ?>
        <button type="submit" name="turn" value="heal:<?php echo (int)$h['id']; ?>"
                class="gf-btn gf-heal" data-sfx="heal">
          <span>Heal <?php echo (int)$h['pct']; ?>%</span>
          <em class="gf-left"><?php echo (int)$h['have']; ?></em>
        </button>
      <?php endforeach; ?>
    </form>
    <p class="gf-hint">Armour and healing cost you the turn. Weapon swings are
       rationed for the whole run, not this fight.</p>
  <?php endif; ?>
</div>
<?php
}
