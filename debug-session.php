<?php
// TEMPORARY DEBUG TOOL -- delete this file once done testing. It has no
// login gate on purpose (the whole point is to see session state even when
// something is treating a real login as a guest), so anyone with the URL
// can see it while it exists -- nothing here is more sensitive than a
// Discord ID/username/avatar hash, but it shouldn't be left live longer
// than needed.
include_once 'db.php'; // same conditional session_start() every other page here uses
?>
<pre style="background:#111;color:#0f0;padding:20px;font-size:13px;white-space:pre-wrap;word-break:break-all;">
=== session_id() ===
<?php echo session_id() ?: '(no active session)'; ?>

=== $_SESSION (live, as PHP sees it on THIS request) ===
<?php print_r($_SESSION); ?>

=== $_COOKIE['PHPSESSID'] ===
<?php echo isset($_COOKIE['PHPSESSID']) ? htmlspecialchars($_COOKIE['PHPSESSID']) : '(not set)'; ?>

=== $_COOKIE['SessionCookie'] -- raw value ===
<?php echo isset($_COOKIE['SessionCookie']) ? htmlspecialchars($_COOKIE['SessionCookie']) : '(not set)'; ?>

=== $_COOKIE['SessionCookie'] -- decoded ===
<?php
if (isset($_COOKIE['SessionCookie'])) {
	$decoded = json_decode($_COOKIE['SessionCookie'], true);
	print_r($decoded);
} else {
	echo '(not set)';
}
?>
</pre>
