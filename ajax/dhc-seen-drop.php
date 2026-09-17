<?php
/**
 * ajax/dhc-seen-drop.php
 * Clears the held reveal queue once the browser has actually shown it.
 *
 * Deliberately only called AFTER a reveal renders, never when the drop is
 * awarded: a trait the player never saw is still owed them, and the queue is
 * what makes sure the next page they open pays that debt. Clearing early would
 * quietly swallow exactly the case this exists for.
 *
 * Touches nothing but the session -- the ledger is the record, this is only
 * about whether a moment has been delivered.
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../dhc-json.php';

if (session_status() === PHP_SESSION_ACTIVE) {
	unset($_SESSION['dhcf_unseen'], $_SESSION['dhcf_unseen_at']);

	/*
	 * AND PUT THAT IN THE COOKIE, because for a lot of sessions the cookie IS
	 * the session.
	 *
	 * skulliance.php serialises the whole of $_SESSION into SessionCookie at
	 * the END of its include -- which is several lines ABOVE this unset. So the
	 * cookie this request just sent still had the queue in it, and clearing the
	 * server session cleared the copy that, for these sessions, nobody reads.
	 *
	 * Where PHPSESSID survives, the server session is authoritative and the
	 * stale cookie never gets looked at, so this was invisible. Where it does
	 * not -- iOS Safari ITP, PWA standalone -- db.php starts a fresh empty
	 * session every request and skulliance.php restores it from the cookie, so
	 * the queue came straight back, the modal fired again, and it called this
	 * endpoint again, which wrote the queue back into the cookie again. A drop
	 * revealed once replayed on every page open, for the six-month life of the
	 * cookie, and there was no action the player could take from inside the PWA
	 * to break it.
	 *
	 * A second setcookie() for the same name emits a second Set-Cookie header;
	 * the browser applies them in order, so this one wins over the copy
	 * skulliance.php sent.
	 */
	setcookie('SessionCookie', json_encode($_SESSION), time() + (6 * 30 * 24 * 3600));
}
$conn->close();
dhc_json(array('ok' => true));
