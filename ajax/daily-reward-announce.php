<?php
/**
 * ajax/daily-reward-announce.php
 * Posts the Discord announcement for a daily reward the player has already
 * been paid.
 *
 * A SEPARATE REQUEST ON PURPOSE. discordmsg() is a synchronous curl POST with
 * an 8 second timeout and it used to sit inside the claim, between the press
 * and the reply that redraws the strip -- so every claim waited on a third
 * party. Deferring it past the response does not work on this server:
 * cryptcrawlFlushPendingSideEffects() in db.php records three attempts that
 * failed in production. The only reliable way to answer fast and still do the
 * slow thing is to do the slow thing in a request the browser was never
 * waiting on, which is this one.
 *
 * FIRE AND FORGET. The claim is already committed and the player has already
 * seen it. Nothing here can fail in a way that should reach them, so the reply
 * is the same either way.
 *
 * THE CLIENT CANNOT DICTATE THE MESSAGE. It carries no parameters at all: the
 * only thing it can do is flush the marker getRandomReward() left in this
 * player's own session, and dailyRewardAnnounce() builds the text from the
 * database and the session. Calling it without a marker does nothing.
 */
include '../db.php';
include '../webhooks.php';
include '../skulliance.php';

$dr = isset($_SESSION['dr_announce']) ? $_SESSION['dr_announce'] : null;

/*
 * CLEARED BEFORE THE POST, not after. A double-fire -- an impatient reload, a
 * flaky connection, two tabs -- must not produce two announcements, and the
 * 8 second window while curl runs is exactly when a second call would arrive.
 */
unset($_SESSION['dr_announce']);
if (session_status() === PHP_SESSION_ACTIVE) {
	/*
	 * AND PUT THAT IN THE COOKIE. skulliance.php serialises the whole of
	 * $_SESSION into SessionCookie at the end of its include, several lines
	 * above this unset -- so without a second setcookie() the marker comes
	 * straight back on any session where the cookie IS the session (iOS
	 * Safari ITP, PWA standalone) and the claim gets announced again on every
	 * page open. ajax/dhc-seen-drop.php documents the full story; it cost a
	 * player a drop modal that replayed for months.
	 */
	setcookie('SessionCookie', json_encode($_SESSION), time() + (6 * 30 * 24 * 3600));
}

/*
 * STALE MARKERS ARE DROPPED. The browser fires this within a second of the
 * claim; anything older than fifteen minutes means the tab was closed before
 * it ran and the moment has passed. Announcing it on the player's next visit
 * would put yesterday's claim in the channel as though it just happened.
 */
if (is_array($dr) && isset($dr['at']) && (time() - (int)$dr['at']) <= 900) {
	try {
		dailyRewardAnnounce($conn, $dr['day'], $dr['amount'], $dr['currency']);
	} catch (Throwable $e) {
		error_log('daily-reward-announce: ' . $e->getMessage());
	}
}

$conn->close();
header('Content-Type: application/json');
echo '{"ok":true}';
