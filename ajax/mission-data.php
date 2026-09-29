<?php
/*
 * ajax/mission-data.php — everything the missions page reads, as JSON.
 *
 * ONE ENDPOINT, not four, because these are four views of the same thing
 * and a file each would be four copies of the same preamble drifting
 * apart. `what` selects; it is whitelisted rather than trusted.
 *
 * All of it is read-only and scoped to the session's own user. The writer
 * is ajax/mission-launch.php and lives on its own.
 *
 * WHY THIS EXISTS AT ALL: choosing a mission used to be a full page POST.
 * Every card was its own <form action='missions.php#inventory'>, so a
 * click reloaded the page and re-ran verify.php -- which is why the page
 * shipped a nine-second fake progress bar to cover the wait. Maximise and
 * Balance were two more. None of that was ever a navigation; it was a
 * panel changing.
 */
include '../db.php';
require_once __DIR__ . '/../missions-lib.php';
require_once __DIR__ . '/../dhc-json.php';

/* Same merge-restore every other entry point does: iOS Safari and the PWA
   drop PHPSESSID while keeping the 6-month SessionCookie, and a staker
   whose missions vanish on their phone has no way to know why. MERGE,
   never replace -- see MAINTENANCE.md. */
if (session_status() === PHP_SESSION_ACTIVE
    && !isset($_SESSION['logged_in'])
    && isset($_COOKIE['SessionCookie'])) {
	$ck = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($ck)) $_SESSION = array_merge((array)$_SESSION, $ck);
}

if (mission_user_id() <= 0) dhc_json(array('ok' => false, 'message' => 'Not signed in.'));

$what = isset($_GET['what']) ? (string)$_GET['what'] : '';

switch ($what) {
	/* HTML, not JSON, for the two lists -- they are rendered by partials that
	   missions.php's first paint also includes, so there is exactly one copy
	   of that markup. The drawer below is JSON because the browser needs the
	   per-NFT rates to do Maximise, Balance and the live success total
	   without asking the server again. */
	case 'ladder':
		$ms_project = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
		if ($ms_project <= 0) dhc_json(array('ok' => false, 'message' => 'No project.'));
		/* Remembered so the next first paint lands where they left off --
		   the one thing the old select-project-filter.php endpoint did. */
		$_SESSION['userData']['project_id'] = $ms_project;
		$ms_quests = mission_quests($conn, $ms_project);
		ob_start(); include __DIR__ . '/../missions-ladder.php'; $html = ob_get_clean();
		dhc_json(array('ok' => true, 'project_id' => $ms_project, 'html' => $html));

	case 'field':
		/* The cap is the default; `all` lifts it. Kept out of the partial so
		   the first paint and the refresh answer to the same switch. */
		$ms_all    = !empty($_GET['all']);
		$ms_active = mission_active($conn, $ms_all ? 0 : 24);
		$ms_total  = mission_active_total($conn);
		ob_start(); include __DIR__ . '/../missions-field.php'; $html = ob_get_clean();
		dhc_json(array('ok' => true, 'html' => $html, 'overview' => mission_overview($conn),
		               'total' => $ms_total,
		               'ready' => count(array_filter($ms_active, function($a){ return !empty($a['ready']); }))));

	case 'news':
		$ms_new_rungs = mission_frontier($conn);
		ob_start(); include __DIR__ . '/../missions-news.php'; $html = ob_get_clean();
		dhc_json(array('ok' => true, 'html' => $html, 'count' => count($ms_new_rungs)));

	case 'loadout':
		$qid = isset($_GET['quest_id']) ? (int)$_GET['quest_id'] : 0;
		$lo  = mission_loadout($conn, $qid);
		if (!$lo) dhc_json(array('ok' => false, 'message' => 'That mission is not available.'));
		if (!empty($lo['locked'])) dhc_json(array('ok' => false, 'message' => 'That mission is still locked.'));
		dhc_json(array('ok' => true, 'loadout' => $lo));

	default:
		dhc_json(array('ok' => false, 'message' => 'Unknown request.'));
}
