<?php
/**
 * admin-testimonials.php — read them before anybody else does.
 *
 * The whole feature rests on this screen. Nothing a player writes
 * reaches the website or Discord until it is approved here, because
 * whatever they type would otherwise go out under the Skulliance name
 * and a Discord post cannot be recalled.
 *
 * ONE TAP EACH, and it works on a phone: the owner reads these
 * standing up, not at a desk.
 *
 * APPROVING IS WHAT POSTS TO DISCORD. Not submitting. The archive
 * channel is for the good ones, and the only way to know which those
 * are is to have read them.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php
require_once __DIR__ . '/testimonials-lib.php';
/* skl_discordmsg() and the webhook credentials. Normally these arrive
   through verify.php, which an admin page has no reason to include --
   so the first approval published to the site and posted nothing. */
include_once __DIR__ . '/webhooks.php';

/*
 * THE VERDICT IS TAKEN BEFORE ANY OUTPUT, so a reject can redirect
 * cleanly and an approve's Discord post is not racing the page.
 */
$tst_said = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tid'])) {
	$tid     = (int) $_POST['tid'];
	$approve = (isset($_POST['verdict']) && $_POST['verdict'] === 'approve');
	$admin   = isset($_SESSION['userData']['user_id']) ? (int) $_SESSION['userData']['user_id'] : 0;
	$row     = testimonial_review($conn, $tid, $approve, $admin, isset($_POST['note']) ? $_POST['note'] : '');
	/* The announce rule lives in the lib so it can be tested; see
	   testimonial_announce(). */
	if ($row && $approve) testimonial_announce($conn, $row);
	$tst_said = $row ? ($approve ? 'Approved, and posted to Discord.' : 'Rejected.')
	                 : 'Nothing to review there.';
}

include 'header.php';
admin_chrome('testimonials');
?>
<?php include 'admin-css.php'; ?>
<?php

if ($tst_said !== '') echo '<div class="row"><div class="adm"><p class="adm-note">'
	. htmlspecialchars($tst_said) . '</p></div></div>';

/*
 * IS THE DISCORD SIDE ACTUALLY WIRED?
 *
 * Every webhook branch guards with function_exists(), so a credential
 * that is missing OR MISNAMED behaves identically to one that was
 * never set up: nothing posts and nothing complains. The only way to
 * notice is that a channel stays quiet, which is a thing you notice
 * weeks late.
 *
 * So the page says. It checks the exact name webhooks.php reaches for,
 * which is the half a typo lands on.
 */
$tst_hook = function_exists('getTestimonialsWebhook');

$ready    = testimonials_ready($conn);
$pending  = $ready ? testimonial_queue($conn, 'pending')  : array();
$live     = $ready ? testimonial_queue($conn, 'approved') : array();
$rejected = $ready ? testimonial_queue($conn, 'rejected') : array();

/*
 * ONE CARD, USED FOR ALL THREE LISTS.
 *
 * Every testimonial carries the verdict it does not currently have, so
 * a decision is never final: approved ones can be pulled, rejected
 * ones can be let through. The first cut rendered the approved list
 * through testimonial_public(), which drops the row id, so there was
 * no control to press and changing your mind meant editing the
 * database.
 */
function tst_admin_card($t, $verdicts) {
	$prompts = testimonial_prompts();
	echo '<div class="adm-card">';
	echo '<div class="adm-card-head"><strong>' . htmlspecialchars($t['username']) . '</strong> ';
	echo '<span>' . htmlspecialchars($t['created_date']) . '</span> ';
	echo '<span>shows as: ' . htmlspecialchars($t['visibility']) . '</span>';
	if ((int) $t['discord_posted'] === 1) echo ' <span>posted to Discord</span>';
	echo '</div>';
	if (isset($prompts[$t['prompt_key']])) {
		echo '<p class="adm-asked">' . htmlspecialchars($prompts[$t['prompt_key']]) . '</p>';
	}
	echo '<blockquote>' . nl2br(htmlspecialchars($t['body'])) . '</blockquote>';
	if (trim((string) $t['review_note']) !== '') {
		echo '<p class="adm-note">Your note: ' . htmlspecialchars($t['review_note']) . '</p>';
	}
	echo '<form method="post" class="adm-verdict">';
	echo '<input type="hidden" name="tid" value="' . (int) $t['id'] . '">';
	echo '<input type="text" name="note" placeholder="Note to the player (optional)" maxlength="255"'
	   . ' value="' . htmlspecialchars($t['review_note']) . '">';
	foreach ($verdicts as $v => $label) {
		echo '<button type="submit" name="verdict" value="' . $v . '" class="'
		   . ($v === 'approve' ? 'adm-yes' : 'adm-no') . '">' . htmlspecialchars($label) . '</button>';
	}
	echo '</form></div>';
}
?>
<div class="row"><div class="adm">
<?php if (!$ready): ?>
	<p class="adm-note">The <code>testimonials</code> tables do not exist yet.
	   Run the SQL in <code>testimonials-schema.md</code> once and this screen fills itself.
	   Until then the modal never offers and the public pages are unchanged.</p>
<?php else: ?>
	<p class="adm-note">
		<?php if ($tst_hook): ?>
			Discord: <strong>wired</strong>. Approving posts to the testimonials channel.
		<?php else: ?>
			Discord: <strong class="warn">no credential</strong>. Approving still publishes to the
			site; the channel post is skipped. webhooks.php reaches for
			<code>getTestimonialsWebhook()</code> in <code>credentials/webhooks_credentials.php</code>,
			so a different function name reads exactly like this.
		<?php endif; ?>
	</p>
	<h3>Waiting on you (<?php echo count($pending); ?>)</h3>
	<?php if (!$pending): ?><p class="adm-note">Nothing pending.</p><?php endif; ?>
	<?php foreach ($pending as $t) tst_admin_card($t, array('approve' => 'Approve', 'reject' => 'Reject')); ?>

	<h3>Live on the site (<?php echo count($live); ?>)</h3>
	<?php if (!$live): ?><p class="adm-note">Nothing published yet.</p><?php endif; ?>
	<?php
	/* Approve again is how a testimonial that failed to post gets sent:
	   discord_posted is only set once a post actually went out, so a
	   row that never reached Discord will on the next approval. */
	foreach ($live as $t) tst_admin_card($t, array('reject' => 'Pull it down', 'approve' => 'Re-post to Discord'));
	?>

	<h3>Not published (<?php echo count($rejected); ?>)</h3>
	<?php if (!$rejected): ?><p class="adm-note">Nothing rejected.</p><?php endif; ?>
	<?php foreach ($rejected as $t) tst_admin_card($t, array('approve' => 'Approve after all')); ?>
<?php endif; ?>
</div></div>
<?php $conn->close(); ?>
</html>
