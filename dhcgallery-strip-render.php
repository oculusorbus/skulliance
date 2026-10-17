<?php
/**
 * dhcgallery-strip-render.php -- renders dhc-fighter-modal.php's inline script
 * so dhcgallery-strip-harness.js can run the SHIPPED code rather than a copy.
 *
 * The modal is a template, not a library: its JS is half PHP echoes. So the
 * only honest way to test the strip's behaviour is to render the template and
 * execute what comes out, which is what this does. No database -- the modal
 * reads constants and two axis tables and nothing else.
 *
 * Writes two files into the directory named as argv[1]:
 *   modal.js    the rendered script
 *   paxes.json  the axis table exactly as the page emits it
 */
$dir = isset($argv[1]) ? rtrim($argv[1], '/') : sys_get_temp_dir();
require_once __DIR__ . '/dhcfighters-config.php';   // dhcf_rank_axes(), DHCF_ONE_ARM_SPLIT
require_once __DIR__ . '/dhcarena-lib.php';         // dhca_proven_axes()

/* What the modal expects of its host page, and nothing more. */
$dhcm_deeplink = false;
$dhcm_user     = 0;
$dhcm_base     = 'web';

ob_start();
include __DIR__ . '/dhc-fighter-modal.php';
$html = ob_get_clean();

if (!preg_match_all('#<script[^>]*>(.*?)</script>#s', $html, $m) || !$m[1]) {
	fwrite(STDERR, "no inline script in the rendered modal\n");
	exit(2);
}
file_put_contents($dir . '/modal.js', implode("\n;\n", $m[1]));

$pax = array();
foreach (dhca_proven_axes() as $k => $meta) {
	$pax[] = array($k, $meta['label'], $meta['claim'], $meta['unit']);
}
file_put_contents($dir . '/paxes.json', json_encode($pax));

if (strpos($html, 'id="dhcg-proven"') === false) {
	fwrite(STDERR, "the rendered modal has no #dhcg-proven strip\n");
	exit(2);
}
echo "rendered " . count($m[1]) . " script block(s), " . count($pax) . " proven axes\n";
