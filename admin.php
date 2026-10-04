<?php
/**
 * admin.php — kept as a redirect, not deleted.
 *
 * The panel was one page with tabs and is now three (projects,
 * collections, missions), because each is a different job: you onboard a
 * project once, add collections rarely, and write missions for an hour.
 * Anyone who bookmarked the tabbed version lands on the first page
 * instead of a 404. Same reasoning as raids.php -> realms.php.
 */
header('Location: admin-projects.php', true, 302);
exit;
