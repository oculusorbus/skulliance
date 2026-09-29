<?php
/*
 * ajax/item-edit.php — change a store listing after it has been submitted.
 *
 * WHY THIS EXISTS: items are submitted by creators through the form at the
 * bottom of store.php and then cannot be touched. A typo in a name, a dead
 * image URL, a price agreed after the fact -- every one of those has meant
 * opening phpMyAdmin and writing the UPDATE by hand.
 *
 * WHO CAN USE IT: user_id 1, and nobody else. That is checked HERE, against
 * the session, and not at the page: store.php only decides whether to draw
 * the button, and a button that is not drawn is not a permission. Anyone
 * can POST to this file, so this file is the gate.
 *
 * It deliberately does NOT take a user_id, a role, or an "admin" flag from
 * the request. There is exactly one authority on who is asking, and it is
 * the session.
 */
ob_start();
header('Content-Type: application/json');
include '../db.php';

function json_exit($data) { ob_clean(); echo json_encode($data); exit; }

/* Restore from the 6-month SessionCookie when PHPSESSID has lapsed -- iOS
   Safari and the PWA drop it routinely, and without this the admin gets
   "Not authorized" on exactly the device they carry around.
   MERGE, NEVER REPLACE: a raw assign here would wipe whatever else this
   session is holding. See MAINTENANCE.md.
   skulliance.php does this too, and is deliberately NOT included: its
   item-submission handler fires on any POSTed `name`, so including it
   would CREATE a duplicate listing on every save from this form. */
if (session_status() === PHP_SESSION_ACTIVE
    && !isset($_SESSION['logged_in'])
    && isset($_COOKIE['SessionCookie'])) {
	$cookieData = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($cookieData)) $_SESSION = array_merge((array)$_SESSION, $cookieData);
}

/* THE GATE. Not a helper, not a constant somewhere else -- the check that
   matters is in the file that does the writing. */
$user_id = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
if ($user_id !== 1) { json_exit(array('success' => false, 'message' => 'Not authorized.')); }

$item_id = (int)($_POST['item_id'] ?? 0);
if ($item_id <= 0) { json_exit(array('success' => false, 'message' => 'Invalid item.')); }

$exists = $conn->query("SELECT id FROM items WHERE id = '$item_id' LIMIT 1");
if (!$exists || !$exists->num_rows) { json_exit(array('success' => false, 'message' => 'No such item.')); }

$name      = trim((string)($_POST['name'] ?? ''));
$image_url = trim((string)($_POST['image_url'] ?? ''));

if ($name === '')      { json_exit(array('success' => false, 'message' => 'Name is required.')); }
if ($image_url === '') { json_exit(array('success' => false, 'message' => 'Image URL is required.')); }

/* <br> IS ALLOWED AND NOTHING ELSE IS. Several listings are written as
   "Galaxy of Sons<br>Claimer's Choice (DM Oculus Orbus)" so the qualifier
   sits on its own line, and store.php un-escapes exactly that one tag when
   it prints the name. Anything else typed here would be escaped on the way
   out, so this is belt-and-braces -- but it keeps the stored value honest
   rather than leaving the page as the only thing standing between a name
   and a <script> tag. */
if (preg_match('#<(?!br\s*/?>)[^>]*>#i', $name))
	json_exit(array('success' => false, 'message' => 'Only <br> is allowed in a name.'));

/* An image that is not an image URL renders as a broken card for everyone.
   Relative paths (images/foo.png, /staking/icons/skull.png) are how several
   existing rows are written, so those stay legal. */
if (!preg_match('#^(https?://|/|images/|icons/)#i', $image_url))
	json_exit(array('success' => false, 'message' => 'Image URL must be a http(s) link or a site path.'));

$price = (float)($_POST['price'] ?? 0);
if ($price < 0) { json_exit(array('success' => false, 'message' => 'Price cannot be negative.')); }

/* -1 IS UNLIMITED and 0 DELISTS: the store query is `WHERE quantity != 0`,
   so zero is how an item comes off the shelf without being deleted. Both are
   deliberate values a real listing uses, which is why neither is rejected. */
$quantity = (int)($_POST['quantity'] ?? 0);
if ($quantity < -1) { json_exit(array('success' => false, 'message' => 'Quantity must be -1 (unlimited), 0 (delist), or more.')); }

$project_id = (int)($_POST['project_id'] ?? 0);
$valid = $conn->query("SELECT id FROM projects WHERE id = '$project_id' LIMIT 1");
if (!$valid || !$valid->num_rows) { json_exit(array('success' => false, 'message' => 'Unknown project.')); }

$secondary = (int)($_POST['secondary_project_id'] ?? 0);
if ($secondary !== 0) {
	if ($secondary === $project_id)
		json_exit(array('success' => false, 'message' => 'Second currency must differ from the first.'));
	$vs = $conn->query("SELECT id FROM projects WHERE id = '$secondary' LIMIT 1");
	if (!$vs || !$vs->num_rows) { json_exit(array('success' => false, 'message' => 'Unknown second project.')); }
}

$featured = !empty($_POST['featured']) ? 1 : 0;

$result = adminUpdateItem($conn, $item_id, array(
	'name'                 => $name,
	'image_url'            => $image_url,
	'price'                => $price,
	'quantity'             => $quantity,
	'project_id'           => $project_id,
	'secondary_project_id' => $secondary,
	'featured'             => $featured,
));

$conn->close();
json_exit($result);
