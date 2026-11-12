<?php
/*
 * docs-private-harness.php — the written record is not published.
 *
 * THE WHOLE REPO IS SERVED FROM /staking/. Every .md in the tree was
 * readable by anyone who guessed the name: skullpaper/MAINTENANCE.md,
 * which names code paths, caps and the exploits this platform has had,
 * and the design records at the root, which exist PRECISELY because the
 * Skull Paper is public and unbuilt work should not be. Both returned
 * 200 before 2026-11-12.
 *
 * Found while checking where a feature's notes could live without being
 * published, which is a question that only comes up if somebody asks --
 * so this exists to make the answer permanent.
 */
$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$ht = (string) @file_get_contents(__DIR__ . '/.htaccess');
ok($ht !== '', '.htaccess could not be read, so nothing below is checking anything');

echo "\nmarkdown is not served over HTTP\n";
preg_match_all('~<FilesMatch "([^"]+)">~', $ht, $m_fm);
$guards = isset($m_fm[1]) ? $m_fm[1] : array();
ok(count($guards) > 0, 'there is no FilesMatch block at all');
$mdGuarded = false;
foreach ($guards as $g) {
	/* The pattern is a regex; test it the way Apache will rather than
	   searching for the letters "md", which also appear in "cmd". */
	if (@preg_match('~' . $g . '~i', 'skullpaper/MAINTENANCE.md')) $mdGuarded = true;
}
ok($mdGuarded,
   'no FilesMatch matches a .md path, so every design record and MAINTENANCE.md is public');

/*
 * AND THE DENY HAS TO BE INSIDE IT. A FilesMatch that matches and then
 * permits nothing is a comment.
 */
$mdBlock = '';
if (preg_match('~<FilesMatch "[^"]*md[^"]*">(.*?)</FilesMatch>~s', $ht, $m_blk)) $mdBlock = $m_blk[1];
ok($mdBlock !== '', 'the .md FilesMatch block could not be lifted');
ok($mdBlock !== '' && strpos($mdBlock, 'Require all denied') !== false,
   'the .md block does not deny under mod_authz_core, which is what the live server runs');
ok($mdBlock !== '' && strpos($mdBlock, 'Deny from all') !== false,
   'the .md block has no pre-2.4 fallback, so an older Apache serves them anyway');

echo "\nand the Skull Paper still works, because it never fetched them\n";
/*
 * THE ONE THING THAT COULD HAVE BROKEN. skullpaper.php reads the page off
 * DISK -- if it ever fetched its own URL, denying .md would empty the
 * published documentation instead of protecting the private part of it.
 */
$sp = (string) file_get_contents(__DIR__ . '/skullpaper.php');
ok(strpos($sp, 'file_get_contents($mdFile)') !== false,
   'skullpaper.php no longer reads the page off disk, so denying .md may have emptied it');
ok(preg_match('~file_get_contents\(\s*[\'"]https?://~', $sp) !== 1,
   'skullpaper.php fetches over HTTP somewhere, which the .md deny would now block');

echo "\nthe private records are where they can stay private\n";
/*
 * A design record exists because the Skull Paper is public. Now that .md
 * is denied, the repo is a safe place for one -- but only while the deny
 * holds, which is what every check above is for.
 */
foreach (array('dhcportal-design.md', 'dhcbosses-rez-design.md') as $doc) {
	ok(is_file(__DIR__ . '/' . $doc), $doc . ' has gone; the design record for a shipped feature is not optional');
}
/* MAINTENANCE.md is NOT in the nav, so it was never a published page --
   only ever a raw file read. Both halves matter. */
ok(strpos($sp, "isset(\$sp_order[\$page])") !== false,
   'skullpaper.php no longer checks the slug against the nav, so any .md under skullpaper/ is a page');
ok(preg_match("~preg_match\('/\^\[a-z0-9-\]\+\\\$/', \\\$page\)~", $sp) === 1,
   'the slug pattern allows more than lowercase and dashes, so MAINTENANCE could be requested as a page');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
