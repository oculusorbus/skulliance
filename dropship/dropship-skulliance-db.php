<?php
/**
 * A CONNECTION TO THE SKULLIANCE DATABASE, WITHOUT LEAKING THE CREDENTIALS.
 *
 * Drop Ship runs on its own database. Four places need to reach across to
 * Skulliance's -- wallet lookups, the Oculus Lounge trait sync, cached NFT
 * images, and the DHC trait award -- and each one did it the same way:
 *
 *     include __DIR__ . '/../credentials/db_credentials.php';
 *     $skulliance_conn = new mysqli($servername, $username, $password, $dbname);
 *
 * THE TRAP THAT LIVES IN THOSE FOUR LINES: both credentials files use the
 * SAME FOUR VARIABLE NAMES. Drop Ship's own is included at the top of db.php
 * and those names are what built $conn, the Drop Ship connection. Including
 * Skulliance's overwrites all four.
 *
 * It has been safe only because of two accidents of placement: $conn is
 * already a fully-built mysqli object by then, so overwriting the variables
 * that built it does not reach back into it, and every one of those includes
 * sits INSIDE a function, so the overwrite is function-scoped.
 *
 * Neither is enforced by anything. The day somebody includes that credentials
 * file at the top of a page instead of inside a function -- or adds a fifth
 * call site and puts it at file scope -- $servername and friends change
 * underneath db.php, and the next thing to build a connection from them
 * silently points at the wrong database. That is a data-corruption bug that
 * would read as "Drop Ship is acting strangely".
 *
 * So the include happens exactly once, here, inside a function, and the four
 * names never exist anywhere a caller can see them.
 *
 * THE CALLER OWNS THE HANDLE and must close it, which is deliberately the
 * same contract the four call sites already had. Returning a cached shared
 * connection would be fewer connections per request, but the existing sites
 * each call close() when they are done, and a shared handle closed by the
 * first caller is a use-after-close for the second. Changing that is a
 * separate job from removing the trap.
 */

/**
 * A new mysqli connection to the Skulliance database, or null.
 *
 * Fails quiet, because every caller is an enhancement to a Drop Ship page
 * that has to keep working when the platform database is unreachable.
 */
function dropshipSkullianceDb() {
	$cred = __DIR__ . '/../credentials/db_credentials.php';
	if (!is_file($cred)) {
		error_log('dropship: Skulliance credentials not found at ' . $cred);
		return null;
	}

	/*
	 * Included INSIDE this function on purpose. $servername, $username,
	 * $password and $dbname are function-locals here and go out of scope on
	 * return, so nothing outside can be overwritten by them -- which is the
	 * entire reason this file exists.
	 */
	include $cred;

	if (!isset($servername, $username, $password, $dbname)) {
		error_log('dropship: Skulliance credentials file did not define a connection');
		return null;
	}
	$sk = @new mysqli($servername, $username, $password, $dbname);
	if ($sk->connect_error) {
		error_log('dropship: Skulliance database unreachable -- ' . $sk->connect_error);
		return null;
	}
	return $sk;
}
