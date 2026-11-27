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

/**
 * Fast Forward: swap the fighter you were dealt.
 *
 * A RUN CONCERN RENDERED INSIDE THE FIGHT, because it is still a thing
 * the player can DO and belongs beside the other choices rather than
 * below the battle log. Extracted so both callers emit it -- without
 * that, the AJAX fragment would drop it and Fast Forward would vanish
 * after the first swing.
 */
function gauntletRenderFastForward($enc, $ff_count, $ff_hand) {
	if ((int)$ff_count < 1 || !count($ff_hand)) return '';
	ob_start(); ?>
	<?php if ($ff_count > 0 && count($ff_hand) > 0): ?>
	<button class="btn-ff" onclick="toggleFF()" type="button"><img src="icons/fast-forward.png" onerror="this.style.display='none'" style="width:16px;height:16px;object-fit:contain;vertical-align:middle;margin-right:6px;">Fast Forward — Swap Card (<?php echo $ff_count; ?> available)</button>
	<div class="ff-overlay" id="ff-overlay">
		<div class="ff-overlay-title">Pick a replacement card from your hand</div>
		<div class="ff-hand-grid">
		<?php foreach ($ff_hand as $card):
			$img = getIPFS($card['ipfs'], $card['collection_id'], $card['project_id']);
		?>
			<div class="nft-card" onclick="submitFF(<?php echo intval($enc['id']); ?>, <?php echo intval($card['id']); ?>)">
				<img class="nft-img" src="<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($card['name']); ?>" onload="this.style.background='none'" onerror="this.src='icons/skull.png';this.style.background='none'">
				<div class="nft-card-info">
					<div class="nft-card-name"><?php echo htmlspecialchars($card['name']); ?></div>
					<div class="nft-card-project"><?php echo htmlspecialchars($card['project_name']); ?></div>
				</div>
			</div>
		<?php endforeach; ?>
		</div>
	</div>
	<form method="POST" id="ff-form">
		<input type="hidden" name="action" value="fast_forward">
		<input type="hidden" name="encounter_id" value="<?php echo intval($enc['id']); ?>">
		<input type="hidden" name="new_nft_id" id="ff-new-nft-id" value="0">
	</form>
	<?php endif; ?>
	<?php
	return ob_get_clean();
}

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
/**
 * $extra  markup to place with the CHOICES rather than after them --
 *         Fast Forward lives in gauntlets.php (it swaps the fighter,
 *         which is a run concern, not a fight one) but belongs beside
 *         the other things a player can do, not below the battle log.
 */
function gauntletRenderFight($enc, $fight, $loadout, $just = array(), $bg = '', $extra = '') {
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
    <div class="gf-side gf-you<?php echo (int)$fight['you']['hp'] <= 0 ? ' is-down' : ''; ?>">
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

    <div class="gf-side gf-foe<?php echo (int)$fight['foe']['hp'] <= 0 ? ' is-down' : ''; ?>">
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
        /* Payout, not combat -- the only economic decision in the game,
           and kept visually last so it reads as a different kind of
           choice from the three that resolve the fight. */
        'Stake'   => isset($loadout['stakes']) ? $loadout['stakes'] : array(),
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
                <em class="gf-left" title="<?php echo (int)$it['left']; ?> swings left this run<?php
                    if (!empty($it['qty'])) echo ' — you hold ' . (int)$it['qty']; ?>"><?php echo (int)$it['left']; ?></em>
              </button>
            <?php elseif ($t === 'armor'): $dis = $it['left'] < 1; ?>
              <button type="submit" name="turn" value="armor:<?php echo (int)$it['id']; ?>"
                      class="gf-btn gf-arm<?php echo $dis ? ' is-spent' : ''; ?>"
                      <?php echo $dis ? 'disabled' : ''; ?> data-sfx="equip">
                <span><?php echo htmlspecialchars($it['name']); ?></span>
                <em class="gf-left" title="<?php echo (int)$it['left']; ?> uses left this run<?php
                    if (!empty($it['qty'])) echo ' — you hold ' . (int)$it['qty']; ?>"><?php echo (int)$it['left']; ?></em>
              </button>
            <?php elseif ($t === 'stake'): ?>
              <button type="submit" name="turn" value="stake:<?php echo (int)$it['id']; ?>"
                      class="gf-btn gf-stake" data-sfx="equip">
                <span><?php echo htmlspecialchars($it['name']); ?></span>
                <em class="gf-left" title="in your inventory"><?php echo (int)$it['have']; ?></em>
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
    <p class="gf-hint">Armour, healing and staking all cost you the turn.
       Weapon swings are rationed for the whole run, not this fight.</p>
    <?php if ($extra !== '') echo $extra; ?>
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
