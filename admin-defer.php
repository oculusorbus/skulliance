<?php
/*
 * admin-defer.php — what this server can do about blocking notifications.
 *
 * WHY THIS IS A PAGE AND NOT A SCRIPT. The shell `php` on this box is a
 * different build from the one serving the site: a CLI probe answers a
 * question nobody asked. Whether the web SAPI can hand the browser its
 * response and keep working is exactly the sort of thing that has to be
 * asked of the web SAPI, from a browser, which is also the only tool
 * the owner has to hand most of the time.
 *
 * It reads. It writes nothing, sends nothing, and changes no setting.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();

$ad_sapi = php_sapi_name();
$ad_ways = array(
	'fastcgi_finish_request'  => function_exists('fastcgi_finish_request'),
	'litespeed_finish_request' => function_exists('litespeed_finish_request'),
);
$ad_best = '';
foreach ($ad_ways as $ad_n => $ad_y) { if ($ad_y && $ad_best === '') $ad_best = $ad_n; }

/* The mod_php fallback needs all three of these to be true. */
$ad_fb = array(
	'output_buffering is on'      => (int) ini_get('output_buffering') > 0 || ob_get_level() > 0,
	'zlib.output_compression off' => !ini_get('zlib.output_compression'),
	'no handler is gzipping'      => !in_array('ob_gzhandler', ob_list_handlers(), true),
);
$ad_fb_ok = !in_array(false, $ad_fb, true);

include 'header.php';
?>
<div class="container"><div class="row"><div class="col1of3" style="margin:0 auto;flex:1 1 100%;max-width:none;">
<div class="adm">
  <h2>Deferred notifications</h2>
  <p>Discord posts block the request that triggers them: <code>CURLOPT_TIMEOUT</code> is
     8 seconds on an embed and 20 on an upload, and the player waits for every one. This
     page reports whether this server can answer the browser first and post afterwards.</p>

  <h4>This server</h4>
  <p>SAPI: <b><?php echo htmlspecialchars($ad_sapi); ?></b></p>
  <ul>
  <?php foreach ($ad_ways as $ad_n => $ad_y): ?>
    <li><code><?php echo $ad_n; ?></code>: <b style="color:<?php echo $ad_y ? '#00c8a0' : '#e2184a'; ?>">
      <?php echo $ad_y ? 'available' : 'not available'; ?></b></li>
  <?php endforeach; ?>
  </ul>

  <h4>Verdict</h4>
  <?php if ($ad_best !== ''): ?>
    <p style="color:#00c8a0"><b>Clean release available</b> via <code><?php echo $ad_best; ?>()</code>.
       The response can be closed and the notifications sent after it, with nothing else changed.</p>
  <?php else: ?>
    <p style="color:#f5a623"><b>No native release.</b> The fallback is to declare a
       <code>Content-Length</code> and close the connection by hand, which needs:</p>
    <ul>
    <?php foreach ($ad_fb as $ad_n => $ad_y): ?>
      <li><?php echo htmlspecialchars($ad_n); ?>:
        <b style="color:<?php echo $ad_y ? '#00c8a0' : '#e2184a'; ?>"><?php echo $ad_y ? 'yes' : 'no'; ?></b></li>
    <?php endforeach; ?>
    </ul>
    <p><?php echo $ad_fb_ok
        ? 'All three hold, so the fallback will work.'
        : 'At least one does not hold. A queued notification would still move off the critical path but the browser would not be released early.'; ?></p>
  <?php endif; ?>

  <h4>What is blocking today</h4>
  <p>173 <code>discordmsg()</code> calls across 55 files, and no caller uses the return
     value, so the sender can queue instead of post without touching any of them.</p>
</div>
</div></div></div>
<?php include 'footer.php'; ?>
