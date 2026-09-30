<?php
/**
 * ajax/get-realms.php
 * The Attack panel, re-rendered. toggleSections('realms') swaps this in, and
 * filterRealms() calls it again on every change of the Sort By control.
 *
 * ONE COPY, like the locations and realm panels. This file carried its own
 * version of the panel header -- the <h2> outside .content, the sort control
 * floated to the right of it -- so a redesign in realms.php was replaced by
 * the old markup the moment anything called this. That has now happened
 * three times on this page, which is why the markup is a partial.
 */
include '../db.php';
include '../skulliance.php';

if(!isset($_SESSION['userData']['user_id'])){ exit; }
if(!checkRealm($conn) || checkRealmState($conn) != 1){ exit; }

$ra_sort  = isset($_GET['sort'])  ? $_GET['sort']  : 'weakness';
$ra_group = isset($_GET['group']) ? $_GET['group'] : 'Eligible';
?>
<div class="main">
	<a name="realms" id="realms-anchor"></a>
	<?php include __DIR__ . '/../realms-attack.php'; ?>
</div>
<?php $conn->close(); ?>
