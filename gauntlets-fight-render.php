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
/**
 * $just  the entries from THIS turn only, as returned by
 *        gaunt_fight_turn(). data-tl carries these and nothing else:
 *        handing the client the whole six-entry tail replayed every
 *        shake and every sound on every turn, so a fight got noisier
 *        and noisier as it went.
 */
function gauntletRenderFight($enc, $fight, $loadout, $just = array(), $bg = '') {
	$over = !empty($fight['over']);
	$won  = ($fight['win'] ?? '') === 'you';

	/* Cues for THIS turn only. */
	$sfx = array();
	foreach ($just as $e) if (!empty($e['sfx'])) $sfx[] = $e['sfx'];

	/* The visible log is the tail; what the client animates is $just. */
	$tail = array_slice($fight['log'] ?? array(), -6);
	?>
<div id="gf-area"
     class="gf<?php echo $over ? ($won ? ' gf-won' : ' gf-lost') : ''; ?><?php echo $bg !== '' ? ' gf-has-bg' : ''; ?>"
     <?php if ($bg !== ''): ?>style="--gf-bg:url('<?php echo htmlspecialchars($bg); ?>')"<?php endif; ?>
     <?php /* So "where is the theme" is answerable from view-source next
              time instead of by bisecting the lookup. */ ?>
     data-bg="<?php echo $bg !== '' ? htmlspecialchars($bg) : 'none: no active theme with background=1 for this opponent'; ?>"
     data-sfx="<?php echo htmlspecialchars(implode(' ', $sfx)); ?>"
     data-tl="<?php echo htmlspecialchars(json_encode(array_values($just))); ?>"
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


  <?php if ($over): ?>
    <div class="gf-result <?php echo $won ? 'win' : 'loss'; ?>">
      <?php echo $won ? 'Opponent down' : 'Your fighter is down'; ?>
    </div>
    <form method="post" class="gf-done">
      <input type="hidden" name="action" value="close_fight">
      <input type="hidden" name="encounter_id" value="<?php echo (int)$enc['id']; ?>">
      <button type="submit" class="gf-btn gf-go">Continue</button>
    </form>
  <?php else: ?>
    <form method="post" class="gf-actions" id="gf-form">
      <input type="hidden" name="action" value="fight_turn">
      <input type="hidden" name="encounter_id" value="<?php echo (int)$enc['id']; ?>">

      <?php
      /*
       * GROUPED, NOT A HEAP. Everything a player owns in one flex row is
       * a wall of buttons they have to read every turn -- and the three
       * kinds do completely different things: one spends a swing, one
       * spends a turn to prevent damage, one spends a turn to undo it.
       * Labelling them is what makes the decision legible.
       */
      $gf_groups = array(
        'Attack'  => array_merge(array($loadout['bare']), $loadout['weapons']),
        'Defend'  => $loadout['armor'],
        'Recover' => $loadout['heals'],
      );
      foreach ($gf_groups as $label => $items):
        if (!$items) continue; ?>
        <div class="gf-group">
          <div class="gf-group-label"><?php echo htmlspecialchars($label); ?></div>
          <div class="gf-group-row">
          <?php foreach ($items as $it):
            $t = $it['type'];
            if ($t === 'bare'): ?>
              <button type="submit" name="turn" value="bare" class="gf-btn gf-bare" data-sfx="fist">
                <img src="icons/fist.png" alt="" onerror="this.style.display='none'">
                <span>Bare hands</span>
              </button>
            <?php elseif ($t === 'weapon'): $dis = $it['left'] < 1; ?>
              <button type="submit" name="turn" value="weapon:<?php echo (int)$it['id']; ?>"
                      class="gf-btn gf-wpn<?php echo $dis ? ' is-spent' : ''; ?>"
                      <?php echo $dis ? 'disabled' : ''; ?>
                      data-sfx="<?php echo htmlspecialchars($it['sfx'] ?: 'melee'); ?>">
                <img src="icons/<?php echo htmlspecialchars($it['slug']); ?>.png" alt=""
                     onerror="this.style.display='none'">
                <span><?php echo htmlspecialchars($it['name']); ?></span>
                <?php if (!empty($it['loot'])): ?><em class="gf-loot" title="taken off an opponent; gone when the run ends">salvage</em><?php endif; ?>
                <em class="gf-left" title="swings left this run"><?php echo (int)$it['left']; ?></em>
              </button>
            <?php elseif ($t === 'armor'): $dis = $it['left'] < 1; ?>
              <button type="submit" name="turn" value="armor:<?php echo (int)$it['id']; ?>"
                      class="gf-btn gf-arm<?php echo $dis ? ' is-spent' : ''; ?>"
                      <?php echo $dis ? 'disabled' : ''; ?> data-sfx="equip">
                <span><?php echo htmlspecialchars($it['name']); ?></span>
                <em class="gf-left" title="uses left this run"><?php echo (int)$it['left']; ?></em>
              </button>
            <?php else: ?>
              <button type="submit" name="turn" value="heal:<?php echo (int)$it['id']; ?>"
                      class="gf-btn gf-heal" data-sfx="heal">
                <span>Heal <?php echo (int)$it['pct']; ?>%</span>
                <em class="gf-left" title="in your inventory"><?php echo (int)$it['have']; ?></em>
              </button>
            <?php endif;
          endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </form>
    <p class="gf-hint">Armour and healing cost you the turn. Weapon swings are
       rationed for the whole run, not this fight.</p>
  <?php endif; ?>
<?php /* THE LOG SITS UNDER THE CHOICES. Above them it pushed the
           buttons down the page as it filled, and the thing a player
           reaches for every turn should not move. */ ?>
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
</div>
<?php
}
