<?php
/**
 * admin-crafting.php — the three crafting reveals, playable on demand.
 *
 * WHY IT EXISTS. Each sequence only runs when somebody has the balance
 * to run it, and running it spends that balance. Convert needs all six
 * core points, shatter needs DIAMOND, burn needs 100 CARBON -- so
 * checking a change to any of them meant finding an account in the right
 * state and then destroying that state to look once.
 *
 * IT CRAFTS NOTHING. The buttons below call SK_CRAFT_PREVIEW(), which
 * plays a sequence and prints a made-up result without ever reaching
 * ajax/craft.php. No balance is read and none is written.
 *
 * THE REAL PARTIAL, NOT A COPY. It includes crafting-modal.php itself,
 * which is the only way a preview stays honest: a second copy of the
 * markup drifts from the one players see, which is the whole failure
 * this page exists to prevent.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php

include 'header.php';
admin_chrome('crafting');
?>
<?php include 'admin-css.php'; ?>
<div class="row"><div class="adm">
	<h3>Crafting reveals</h3>
	<p class="adm-note">The same partial the crafting panel renders. Nothing here crafts
	   anything: these buttons play a sequence and print a sample result without going
	   near <code>ajax/craft.php</code>, so no balance is read or written.</p>

	<div class="adm-card">
		<div class="adm-chest-ctl">
			<label class="adm-note" for="adm-cft-amt">Amount</label>
			<input type="number" id="adm-cft-amt" value="40" min="1" step="1" style="width:110px;">
			<button type="button" class="adm-btn" data-cft="convert">Convert</button>
			<button type="button" class="adm-btn" data-cft="shatter">Shatter</button>
			<button type="button" class="adm-btn" data-cft="burn">Burn</button>
			<span class="adm-note" id="adm-cft-say">Pick an amount and play one.</span>
		</div>
		<p class="adm-note">Burn is quoted in CARBON and forms one DIAMOND per hundred, so an
		   amount below 100 forms nothing. The sequences are about two and a half seconds
		   each; the result lands when the animation does.</p>
	</div>

	<div class="adm-card">
		<h4>What each one is</h4>
		<p class="adm-note"><b>Convert</b> puts the six core project icons on a ring and spins
		   them inward until they are on top of each other, and a DIAMOND is where they met.
		   <b>Shatter</b> is the same choreography backwards: the DIAMOND shakes, breaks, and
		   throws the six back out to the ring they came from. They are deliberately each
		   other's mirror, because the two operations are each other's undo.
		   <b>Burn</b> is the odd one out and should be: CARBON catches, burns down, and a
		   DIAMOND comes up through the flame.</p>
		<p class="adm-note">Reduced motion is honoured: the dialog still shows what was spent
		   and what arrived, it just arrives without the spin, the shake or the flame. To check
		   that, turn on Reduce Motion in your system settings and reload. It is deliberately
		   not simulated here, because a copy of those rules on this page could disagree with
		   the ones that ship.</p>
	</div>
</div></div>

<?php /* The reveal itself, the same partial, rendered once. */ ?>
<?php include __DIR__ . '/crafting-modal.php'; ?>

<style>
.adm-chest-ctl { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.adm-chest-ctl .adm-note { margin: 0; }
</style>
<script>
(function () {
	var say = document.getElementById('adm-cft-say');
	var amt = document.getElementById('adm-cft-amt');
	if (!say) return;
	Array.prototype.forEach.call(document.querySelectorAll('[data-cft]'), function (b) {
		b.addEventListener('click', function () {
			var kind = b.getAttribute('data-cft');
			var n    = parseInt(amt.value, 10) || 0;
			if (!window.SK_CRAFT_PREVIEW) { say.textContent = 'The reveal partial did not load.'; return; }
			SK_CRAFT_PREVIEW(kind, n);
			say.textContent = 'Playing ' + kind + '. Nothing was crafted.';
		});
	});
})();
</script>
</html>
