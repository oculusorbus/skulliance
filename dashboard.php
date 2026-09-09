<?php
/*
 * dashboard.php -- permanent redirect to my-nfts.php.
 *
 * This page was never a dashboard. It shows your staked NFTs, and the name
 * misled people into expecting an overview of the platform -- which is what
 * launchpad.php now actually is. So the page moved to a name that describes
 * it, and this stub stays behind forever.
 *
 * WHY A STUB RATHER THAN UPDATING EVERY LINK: there were 46 references to
 * dashboard.php across the codebase, plus bookmarks, Discord posts and the
 * "back to Skulliance" buttons inside several games -- none of which can be
 * edited retroactively. The visible entry points (nav, Launchpad) were
 * repointed; everything else lands here and is forwarded. Deleting this file
 * breaks all of it silently, so don't.
 *
 * 301, not 302: the move is permanent, and it lets browsers and search
 * engines stop asking. Query strings are preserved so the pagination and
 * filter links that games and old pages hand out still arrive intact.
 *
 * NOTE dropship/dashboard.php is a DIFFERENT page belonging to Drop Ship's
 * own sub-system. It is untouched by any of this.
 */
$qs = $_SERVER['QUERY_STRING'] ?? '';
header('Location: my-nfts.php' . ($qs !== '' ? '?' . $qs : ''), true, 301);
exit;
