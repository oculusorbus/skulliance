<?php
/**
 * ajax/get-realm.php
 * The realm panel, re-rendered. toggleSections() swaps this in whenever the
 * desktop layout shows Locations, which puts the realm column back beside it.
 *
 * ONE COPY, like the locations panel. This file carried its own version of
 * the panel -- the old <h2> outside .content, "Theme:" and "Faction:" labels,
 * the hidden #filterNFTsForm that reloaded the whole page -- so the redesigned
 * panel in realms.php was replaced by the old one the moment anything called
 * this. On desktop that is immediately, on the first toggle; the rebuilt panel
 * was never actually seen. Exactly the drift that had already bitten
 * ajax/get-locations.php.
 */
include '../db.php';
include '../skulliance.php';

if(!isset($_SESSION['userData']['user_id'])){ exit; }

$realm_status = checkRealm($conn);
$projects     = getProjects($conn, "core");
$realm_id     = $realm_status ? getRealmID($conn) : 0;
?>
<a name="realm-image" id="realm-image"></a>
<div class="content realm">
<?php
if ($realm_status) {
	$image = getRealmThemeID($conn, $realm_id);
	include __DIR__ . '/../realms-identity.php';
} else {
	echo '<h2>Realm</h2>';
}
?>
</div>
<?php $conn->close(); ?>
