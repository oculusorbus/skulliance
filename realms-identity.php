<?php
/*
 * realms-identity.php -- the realm's own panel: name, artwork, theme, faction.
 *
 * Replaces a block that was a big image with two labelled dropdowns stacked
 * under it, each of which submitted a form and reloaded the whole page.
 * Realms is heavy enough to have needed a loading screen, so spending a full
 * reload to change one picture was the worst trade on the page -- and a
 * rejection came back as a JS alert() over the reloaded page.
 *
 * Both controls talk to ajax/realm-identity.php now and report inline. The
 * POST handlers in realms.php stay as the no-JS path.
 *
 * Expects: $conn, $realm_id, $realm_status, $projects (core), $image.
 */
if (!function_exists('ri_e')) { function ri_e($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
$ri_theme   = (int)getRealmThemeID($conn, $realm_id);
$ri_faction = (int)getRealmFaction($conn, $realm_id);
$ri_core    = getProjects($conn, 'core');
$ri_partner = getProjects($conn, 'partner');
$ri_founder = isset($_SESSION['userData']['discord_id'])
           && $_SESSION['userData']['discord_id'] === '772831523899965440';
/* The faction list excludes project 7, as it always has. */
$ri_fac_core = $ri_core; unset($ri_fac_core[7]);
?>
<div class="ri">
	<div class="ri-head">
		<h2 id="realmName-wrap">
			<span id="realmName"><?php echo ri_e(getRealmName($conn)); ?></span>
			<button type="button" class="ri-edit" onclick="editRealmName(this)"
				title="Rename your realm" aria-label="Rename your realm">
				<img src="icons/edit.png" alt=""></button>
		</h2>
	</div>

	<div class="ri-art">
		<img id="ri-image" src="images/themes/<?php echo (int)(isset($image) ? $image : 7); ?>.jpg"
			alt="Your realm" onerror="this.src='icons/skull.png'">
	</div>

	<?php /* SIDE BY SIDE, LABELLED, AND THEY SAY WHAT THEY DO. Stacked under
	         the image with bold labels they read as a form to fill in; they
	         are two settings. */ ?>
	<div class="ri-controls">
		<label class="ri-field">
			<span>Theme</span>
			<select id="filterNFTs"
				onchange="setRealmIdentity('theme', this.value, this)">
				<optgroup label="Core Projects">
				<?php foreach (array_reverse($ri_core, true) as $id => $p): ?>
					<option value="<?php echo (int)$id; ?>"<?php
						echo $ri_theme === (int)$id ? ' selected' : ''; ?>><?php
						echo ri_e($p['name']); ?></option>
				<?php endforeach; ?>
				</optgroup>
				<optgroup label="Partner Projects">
				<?php foreach ($ri_partner as $id => $p): ?>
					<option value="<?php echo (int)$id; ?>"<?php
						echo $ri_theme === (int)$id ? ' selected' : ''; ?>><?php
						echo ri_e($p['name']); ?></option>
				<?php endforeach; ?>
				</optgroup>
				<?php if ($ri_founder): ?>
				<optgroup label="Founder">
					<option value="0"<?php echo $ri_theme === 0 ? ' selected' : ''; ?>>Oculus Orbus</option>
				</optgroup>
				<?php endif; ?>
			</select>
			<i>The artwork above. Needs one NFT from that project.</i>
		</label>

		<label class="ri-field">
			<span>Faction</span>
			<select id="faction"
				onchange="setRealmIdentity('faction', this.value, this)">
				<optgroup label="Core Factions">
				<?php foreach ($ri_fac_core as $id => $p): ?>
					<option value="<?php echo (int)$id; ?>"<?php
						echo $ri_faction === (int)$id ? ' selected' : ''; ?>><?php
						echo ri_e($p['name']); ?></option>
				<?php endforeach; ?>
				</optgroup>
				<optgroup label="Partner Factions">
				<?php foreach ($ri_partner as $id => $p): ?>
					<option value="<?php echo (int)$id; ?>"<?php
						echo $ri_faction === (int)$id ? ' selected' : ''; ?>><?php
						echo ri_e($p['name']); ?></option>
				<?php endforeach; ?>
				</optgroup>
			</select>
			<i>Who you raid alongside. Needs one NFT from that project.</i>
		</label>
	</div>
	<?php /* Where a refusal lands, instead of a JS alert over a reloaded page. */ ?>
	<p class="ri-msg" id="ri-msg" hidden></p>
</div>
