<?php
/*
 * The player's animation preference for a still theme that ships gifs.
 *
 * VALIDATED, unlike its neighbour toggle-audio.php, which writes
 * $_GET["status"] into the session verbatim. Anything stored here is read
 * back by dropshipMediaExtension() and decides which FILES the run asks
 * for, so the only two values it can ever hold are the two it means.
 */
session_start();
$status = isset($_GET['status']) && $_GET['status'] === 'true' ? 'true' : 'false';
$_SESSION['userData']['animation'] = $status;
header('Content-Type: application/json');
echo json_encode(array('animation' => $status));
