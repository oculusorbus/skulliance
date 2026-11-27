<?php
/**
 * ajax/gauntlet-page.php -- a Gauntlet action, and the page body after it.
 *
 * WHY IT EXISTS. Every action on gauntlets.php was a POST and a
 * redirect, so ending a round and picking the next card reloaded the
 * document twice -- and each reload tore down the ambient <audio> and
 * started the soundtrack again from the top. ajax/gauntlet-fight.php
 * already solved that WITHIN a fight; this does it BETWEEN fights.
 *
 * It runs the same gauntlets-actions.php the page runs, recomputes the
 * same gauntlets-state.php, and renders the same gauntlets-body.php --
 * no second copy of any of it. gauntlets.php keeps its POST-and-redirect
 * path untouched, so a player without JavaScript still gets the whole
 * game and loses only the unbroken music.
 *
 * The <audio>, the fight's stylesheet and its script are NOT in here.
 * They live in the shell and are never replaced, which is the point.
 */
include_once '../db.php';
include '../message.php';
include '../verify.php';
include_once '../gauntlets-fight-lib.php';
include_once '../gauntlets-fight-render.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$user_id = intval($_SESSION['userData']['user_id'] ?? 0);
if ($user_id <= 0) { http_response_code(403); echo '<div class="gf-err">Signed out.</div>'; exit; }

/* The action. Identical code to the page's own, by inclusion rather
   than by copy -- the two must never be able to disagree. */
define('GAUNTLET_ACTIONS_OK', 1);
require __DIR__ . '/../gauntlets-actions.php';

/* Then the state it produced, and the body that draws it. */
define('GAUNTLET_STATE_OK', 1);
require __DIR__ . '/../gauntlets-state.php';

/* The mood travels on the wrapper so the shell's player can read it off
   the swapped fragment without being told separately what happened. */
echo '<div id="g-area" data-mood="'
   . htmlspecialchars(gaunt_page_mood($state, $run_stats)) . '">';
define('GAUNTLET_BODY_OK', 1);
require __DIR__ . '/../gauntlets-body.php';
echo '</div>';
