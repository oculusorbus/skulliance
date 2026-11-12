<?php
/*
 * admin-xprobe.php -- what X will tell us about a comment, for free.
 *
 * WHY THIS EXISTS BEFORE ANY OF THE FEATURE DOES. Verifying that a member
 * commented on one of our posts needs four facts about a reply: who wrote
 * it, their immutable numeric id, what it says, and that it is a reply to
 * the post we named. If no free surface gives all four, the whole scheme
 * is dead and no amount of game design saves it. So this asks, before a
 * line of it gets built.
 *
 * NOT ONE PAID CALL. Every endpoint below is public and unauthenticated.
 * lib/x-oauth.php stays disabled: the OAuth dance is free but the one
 * call that says WHO signed in (GET /2/users/me) is a v2 data endpoint
 * and 403s without pay-per-use enrolment. That is the wall this page is
 * looking for a way around, not a thing to turn on.
 *
 * WHY A PAGE AND NOT A SCRIPT. The shell php on this box is a different
 * build from the one serving the site, so a CLI probe answers a question
 * nobody asked -- and the owner is usually holding a phone. Same reason
 * admin-defer.php is a page.
 *
 * READS ONLY. It writes nothing and stores nothing. POST rather than GET
 * because a probe fires seven outbound requests, and a side effect on
 * GET fires on every prefetch.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();

define('XP_TIMEOUT', 10);

/** One GET. Returns status, error, elapsed ms and body -- never throws. */
function xp_get($url) {
	$t0 = microtime(true);
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS      => 4,
		CURLOPT_TIMEOUT        => XP_TIMEOUT,
		CURLOPT_CONNECTTIMEOUT => 6,
		CURLOPT_HTTPHEADER     => array(
			/* A browser UA on purpose. Several of these surfaces answer a
			   bare curl differently from a browser, and the question is
			   what the feature can get, not what curl's default can. */
			'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) ' .
			'AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0 Safari/537.36',
			'Accept-Language: en-US,en;q=0.9',
		),
	));
	$body = curl_exec($ch);
	$out = array(
		'http' => (int) curl_getinfo($ch, CURLINFO_HTTP_CODE),
		'err'  => (string) curl_error($ch),
		'ms'   => (int) round((microtime(true) - $t0) * 1000),
		'body' => $body === false ? '' : (string) $body,
	);
	curl_close($ch);
	return $out;
}

/** The numeric status id out of anything the owner might paste. */
function xp_status_id($s) {
	$s = trim($s);
	if (preg_match('~/status(?:es)?/(\d{5,25})~', $s, $m)) return $m[1];
	if (preg_match('~^\d{5,25}$~', $s)) return $s;
	return '';
}

/** The @handle out of a status URL, when it carries one. */
function xp_url_handle($s) {
	if (preg_match('~(?:twitter|x)\.com/([A-Za-z0-9_]{1,15})/status~i', $s, $m)) return $m[1];
	return '';
}

/*
 * JS's Number.prototype.toString(36), near enough.
 *
 * The syndication endpoint wants a `token`, which the official embed
 * script computes in the browser as
 *   ((id / 1e15) * Math.PI).toString(36).replace(/(0+|\.)/g, '')
 * and PHP's base_convert() is integers only, so the fractional digits
 * have to be walked by hand.
 *
 * AND IT TURNS OUT NOT TO MATTER, which is why the probe asks anyway.
 * Measured 2026-11-12: `token=a` returns the full payload and an ABSENT
 * token returns `{}`. The parameter is required and its value is not
 * checked, so nothing rests on this matching JS bit for bit. Kept
 * because it is what a browser sends, and the day they start checking
 * is the day this stops being free.
 */
function xp_base36($num) {
	$digits = '0123456789abcdefghijklmnopqrstuvwxyz';
	$int    = floor($num);
	$frac   = $num - $int;
	$whole  = '';
	if ($int <= 0) {
		$whole = '0';
	} else {
		while ($int > 0) {
			$whole = $digits[(int) fmod($int, 36)] . $whole;
			$int   = floor($int / 36);
		}
	}
	$f = '';
	for ($i = 0; $i < 13 && $frac > 0; $i++) {
		$frac *= 36;
		$d     = (int) floor($frac);
		$f    .= $digits[$d];
		$frac -= $d;
	}
	return $f === '' ? $whole : $whole . '.' . $f;
}

function xp_token($id) {
	return preg_replace('/(0+|\.)/', '', xp_base36(((float) $id / 1e15) * M_PI));
}

/** Every key worth reporting if it turns up anywhere in a JSON reply. */
function xp_keys() {
	return array(
		'screen_name', 'author_name', 'author_url', 'name',
		'id_str', 'id', 'user_id_str',
		'in_reply_to_status_id_str', 'in_reply_to_status_id', 'in_reply_to_screen_name',
		'in_reply_to_user_id_str',
		'conversation_id_str', 'replying_to', 'replying_to_status',
		'text', 'full_text', 'html',
	);
}

/**
 * Every occurrence of an interesting key, at any depth, with its path.
 *
 * ALL occurrences, not the first. `id_str` appears on the reply, on its
 * author, and on the parent, and a probe that silently picked one of
 * those would be inventing the answer it was built to find out.
 */
function xp_dig($node, $keys, $path = '', &$out = null) {
	if ($out === null) $out = array();
	if (!is_array($node)) return $out;
	foreach ($node as $k => $v) {
		$p = $path === '' ? (string) $k : $path . '.' . $k;
		if (!is_array($v) && in_array((string) $k, $keys, true) && $v !== null && $v !== '') {
			$out[(string) $k][] = array('path' => $p, 'value' => (string) $v);
		}
		if (is_array($v)) xp_dig($v, $keys, $p, $out);
	}
	return $out;
}

/** The first occurrence of $key whose path avoids every word in $not. */
function xp_pick($dug, $key, $not = array()) {
	if (empty($dug[$key])) return null;
	foreach ($dug[$key] as $hit) {
		$bad = false;
		foreach ($not as $n) if (stripos($hit['path'], $n) !== false) $bad = true;
		if (!$bad) return $hit;
	}
	return null;
}

/* Paths that belong to somebody other than the reply's own author. */
function xp_other() { return array('parent', 'in_reply', 'quoted', 'retweet', 'mention'); }

/**
 * The four facts, read out of whatever came back.
 *
 * Each is returned with the PATH it was read from, so the page can show
 * its work rather than asserting a verdict nobody can check.
 */
function xp_facts($dug, $body) {
	$f = array('handle' => null, 'uid' => null, 'text' => null,
	           'parent' => null, 'ruid' => null, 'thread' => null);

	$f['handle'] = xp_pick($dug, 'screen_name', xp_other());
	if (!$f['handle']) {
		$a = xp_pick($dug, 'author_url', xp_other());
		if ($a && preg_match('~/([A-Za-z0-9_]{1,15})/?$~', $a['value'], $m)) {
			$f['handle'] = array('path' => $a['path'] . ' (parsed)', 'value' => $m[1]);
		}
	}

	/* The author's numeric id, which is the only part of an X identity
	   that cannot be renamed out from under us -- so an id_str on the
	   reply itself is the wrong one. It has to sit under a user or
	   author node. */
	foreach (array('id_str', 'user_id_str', 'id') as $k) {
		if (empty($dug[$k])) continue;
		foreach ($dug[$k] as $hit) {
			$p = $hit['path'];
			$under = preg_match('~(^|\.)(user|author)(\.|$)~i', $p);
			$theirs = false;
			foreach (xp_other() as $n) if (stripos($p, $n) !== false) $theirs = true;
			if ($under && !$theirs && preg_match('~^\d{1,25}$~', $hit['value'])) {
				$f['uid'] = $hit;
				break 2;
			}
		}
	}

	$f['text'] = xp_pick($dug, 'full_text', xp_other());
	if (!$f['text']) $f['text'] = xp_pick($dug, 'text', xp_other());
	if (!$f['text']) {
		/* oEmbed hands back rendered HTML rather than a text field. */
		$h = xp_pick($dug, 'html', array());
		if ($h) {
			$t = trim(preg_replace('/\s+/', ' ', strip_tags($h['value'])));
			if ($t !== '') $f['text'] = array('path' => $h['path'] . ' (tags stripped)', 'value' => $t);
		}
	}

	foreach (array('in_reply_to_status_id_str', 'in_reply_to_status_id') as $k) {
		$hit = xp_pick($dug, $k, array());
		if ($hit && preg_match('~^\d{5,25}$~', $hit['value'])) { $f['parent'] = $hit; break; }
	}
	if (!$f['parent'] && !empty($dug['parent'])) {
		/* fxtwitter nests the whole parent post under `parent`. */
		foreach ($dug['parent'] as $hit) {
			if (preg_match('~(^|\.)parent\.(id|id_str)$~', $hit['path'])) { $f['parent'] = $hit; break; }
		}
	}
	/* WHOSE post it replies to, by immutable id. Stronger than the parent
	   status id alone: it says the comment is under OUR account rather than
	   merely under some post, and a handle rename cannot break it. */
	$f['ruid']   = xp_pick($dug, 'in_reply_to_user_id_str', array());
	$f['thread'] = xp_pick($dug, 'conversation_id_str', array());

	return $f;
}

/* ------------------------------------------------------------------ */

$xp_in     = isset($_POST['comment']) ? trim((string) $_POST['comment']) : '';
$xp_target = isset($_POST['target'])  ? trim((string) $_POST['target'])  : '';
$xp_id     = xp_status_id($xp_in);
$xp_tid    = xp_status_id($xp_target);
$xp_hand   = xp_url_handle($xp_in);
$xp_ran    = false;
$xp_rows   = array();
$xp_err    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!function_exists('curl_init')) {
		$xp_err = 'This PHP build has no curl, so nothing can be fetched.';
	} elseif ($xp_id === '') {
		$xp_err = 'No status id in that. Paste the full link to a comment, or just its numeric id.';
	} else {
		$xp_ran = true;
		$tok    = xp_token($xp_id);
		$syn    = 'https://cdn.syndication.twimg.com/tweet-result?id=' . $xp_id . '&lang=en';

		$probes = array(
			array('oEmbed', 'official',
				'https://publish.twitter.com/oembed?omit_script=1&dnt=1&url=' .
				urlencode('https://x.com/i/status/' . $xp_id)),
			array('Syndication, computed token', 'undocumented', $syn . '&token=' . urlencode($tok)),
			array('Syndication, junk token', 'undocumented', $syn . '&token=a'),
			array('Syndication, no token', 'undocumented', $syn),
			array('Syndication (legacy widget)', 'undocumented',
				'https://cdn.syndication.twimg.com/widgets/tweet?id=' . $xp_id . '&lang=en'),
			array('x.com page, fetched server side', 'scrape',
				'https://x.com/i/status/' . $xp_id),
			array('fxtwitter', 'third party',
				'https://api.fxtwitter.com/status/' . $xp_id),
			array('vxtwitter', 'third party',
				'https://api.vxtwitter.com/status/' . $xp_id),
		);

		foreach ($probes as $p) {
			$r    = xp_get($p[2]);
			$json = json_decode($r['body'], true);
			$dug  = is_array($json) ? xp_dig($json, xp_keys()) : array();
			$row  = array(
				'name' => $p[0], 'kind' => $p[1], 'url' => $p[2],
				'http' => $r['http'], 'ms' => $r['ms'], 'err' => $r['err'],
				'len'  => strlen($r['body']), 'json' => is_array($json),
				'dug'  => $dug, 'facts' => xp_facts($dug, $r['body']),
				'body' => $r['body'], 'html' => array(),
			);
			if (!is_array($json) && $r['body'] !== '') {
				/* An HTML reply is only interesting for whether anything
				   survived without JavaScript. */
				foreach (array('og:title', 'og:description') as $prop) {
					if (preg_match('~<meta[^>]+property=["\']' . preg_quote($prop, '~') .
					               '["\'][^>]+content=["\']([^"\']*)~i', $r['body'], $m)) {
						$row['html'][$prop] = $m[1];
					}
				}
				$row['html']['js wall'] =
					stripos($r['body'], 'JavaScript is not available') !== false ? 'yes' : 'no';
			}
			$xp_rows[] = $row;
		}
	}
}

/** Green when a fact arrived, red when it did not. */
function xp_dot($hit) {
	if (!$hit) return '<span class="bad-dot">no</span>';
	return '<span class="ok-dot">yes</span>';
}

/** Does this endpoint prove the comment sits under the named target? */
function xp_proves($f, $tid) {
	if ($tid === '') return '';
	if ($f['parent'] && $f['parent']['value'] === $tid) return 'direct';
	if ($f['thread'] && $f['thread']['value'] === $tid) return 'thread';
	if ($f['parent'] || $f['thread']) return 'mismatch';
	return 'nothing';
}

include 'header.php';
admin_chrome('xprobe');
?>
<?php include 'admin-css.php'; ?>
<div class="row"><div class="adm">
	<h3>X comment probe</h3>
	<small>
		Can we verify, without paying X anything, that a given member left a given
		comment under a given post? That needs four facts about a reply: the author's
		handle, the author's numeric id, the comment text, and proof it replies to the
		post we named. This fetches a reply from every free surface there is and reports
		which of the four each one hands over.
		<br><br>
		Every endpoint here is public and unauthenticated. Nothing on this page uses the
		paid API, and nothing here turns on X sign-in.
		<br><br>
		<b>Paste the link to a comment, not a top level post.</b> The parent id is the
		fact in doubt, and a post with no parent cannot tell us whether it would have
		been reported.
	</small>

	<form method="post" class="adm-form">
		<label class="adm-wide">Comment link
			<input type="text" name="comment" class="adm-wide" autocomplete="off"
			       placeholder="https://x.com/someone/status/1234567890123456789"
			       value="<?php echo htmlspecialchars($xp_in); ?>">
			<small>The reply itself. A bare numeric id works too.</small>
		</label>
		<label class="adm-wide">Target post link <small style="text-transform:none">(optional)</small>
			<input type="text" name="target" class="adm-wide" autocomplete="off"
			       placeholder="https://x.com/skulliance/status/1234567890123456789"
			       value="<?php echo htmlspecialchars($xp_target); ?>">
			<small>The post the comment should be under. Give this and the probe checks
			       the match instead of just reporting an id.</small>
		</label>
		<button type="submit">Probe</button>
	</form>

	<?php if ($xp_err !== ''): ?>
		<p class="adm-msg bad"><?php echo htmlspecialchars($xp_err); ?></p>
	<?php endif; ?>

	<?php if ($xp_ran): ?>
		<h4>Asking about</h4>
		<p class="adm-note">
			Status <code><?php echo htmlspecialchars($xp_id); ?></code>
			<?php if ($xp_hand !== ''): ?>
				, which the link claims belongs to <code>@<?php echo htmlspecialchars($xp_hand); ?></code>
				<small>(claimed by the URL, which is why we are checking)</small>
			<?php endif; ?>
			<?php if ($xp_tid !== ''): ?>
				<br>Target <code><?php echo htmlspecialchars($xp_tid); ?></code>
			<?php else: ?>
				<br><span class="warn">No target given, so the parent id is reported but not matched.</span>
			<?php endif; ?>
		</p>

		<h4>What each surface gave up</h4>
		<div class="adm-tablewrap">
		<table class="adm-table">
			<tr><th>Surface</th><th>Kind</th><th>HTTP</th><th>ms</th><th>Handle</th>
			    <th>User id</th><th>Text</th><th>Parent</th><th>Proves it</th></tr>
			<?php foreach ($xp_rows as $r): $f = $r['facts']; $pv = xp_proves($f, $xp_tid); ?>
			<tr>
				<td><?php echo htmlspecialchars($r['name']); ?></td>
				<td><small><?php echo htmlspecialchars($r['kind']); ?></small></td>
				<td class="mono"><?php echo $r['http'] >= 200 && $r['http'] < 300
					? '<span class="ok-dot">' . $r['http'] . '</span>'
					: '<span class="bad-dot">' . ($r['http'] ?: 'err') . '</span>'; ?></td>
				<td class="mono"><?php echo (int) $r['ms']; ?></td>
				<td><?php echo xp_dot($f['handle']); ?></td>
				<td><?php echo xp_dot($f['uid']); ?></td>
				<td><?php echo xp_dot($f['text']); ?></td>
				<td><?php echo xp_dot($f['parent']); ?></td>
				<td><?php
					if ($pv === 'direct')   echo '<span class="ok-dot">yes, parent id</span>';
					elseif ($pv === 'thread') echo '<span class="ok-dot">thread only</span>';
					elseif ($pv === 'mismatch') echo '<span class="bad-dot">wrong post</span>';
					elseif ($pv === 'nothing')  echo '<span class="bad-dot">no</span>';
					else echo '<small>no target given</small>';
				?></td>
			</tr>
			<?php endforeach; ?>
		</table>
		</div>

		<h4>Verdict</h4>
		<?php
		$xp_win = array();
		foreach ($xp_rows as $r) {
			$f = $r['facts'];
			if ($f['handle'] && $f['uid'] && $f['text'] && ($f['parent'] || $f['thread'])) {
				$xp_win[] = $r;
			}
		}
		$xp_free = array();
		foreach ($xp_win as $r) if ($r['kind'] !== 'third party') $xp_free[] = $r;
		?>
		<?php if (count($xp_free)): ?>
			<p class="adm-msg good">
				<b>All four facts are available without paying for anything.</b>
				<?php foreach ($xp_free as $r): ?>
					<br><?php echo htmlspecialchars($r['name']); ?>
				<?php endforeach; ?>
			</p>
			<p class="adm-note">An undocumented surface can change without notice, so whatever
			   gets built on one needs to fail visibly rather than silently pass everybody.</p>
		<?php elseif (count($xp_win)): ?>
			<p class="adm-msg warn">
				<b>Only a third party relay gave all four.</b> That means a middleman sees every
				verification and can go away or start charging at any time. Workable, not
				something to depend on.
			</p>
		<?php else: ?>
			<p class="adm-msg bad">
				<b>No single surface gave all four facts.</b> Read the rows below before
				concluding anything is impossible: two surfaces between them may still cover it,
				since the handle and the parent id do not have to arrive from the same place.
			</p>
		<?php endif; ?>

		<h4>Everything, surface by surface</h4>
		<?php foreach ($xp_rows as $r): $f = $r['facts']; ?>
			<div class="adm-card">
				<strong><?php echo htmlspecialchars($r['name']); ?></strong>
				<small style="text-transform:none"> &nbsp;<?php echo htmlspecialchars($r['kind']); ?>
					&nbsp;HTTP <?php echo (int) $r['http']; ?>
					&nbsp;<?php echo (int) $r['ms']; ?>ms
					&nbsp;<?php echo (int) $r['len']; ?> bytes
					&nbsp;<?php echo $r['json'] ? 'JSON' : 'not JSON'; ?></small>
				<?php if ($r['err'] !== ''): ?>
					<p class="adm-msg bad">curl: <?php echo htmlspecialchars($r['err']); ?></p>
				<?php endif; ?>

				<div class="adm-tablewrap">
				<table class="adm-table" style="min-width:0">
					<?php foreach (array('handle' => 'Author handle', 'uid' => 'Author numeric id',
					                     'text' => 'Comment text', 'parent' => 'Replies to post',
					                     'ruid' => 'Replies to account', 'thread' => 'Conversation id')
					                     as $k => $lab): ?>
					<tr>
						<td style="width:160px"><small><?php echo $lab; ?></small></td>
						<td class="mono"><?php
							if (!$f[$k]) { echo '<span class="bad-dot">absent</span>'; }
							else {
								$v = $f[$k]['value'];
								if (strlen($v) > 300) $v = substr($v, 0, 300) . '...';
								echo '<span class="ok-dot">' . htmlspecialchars($v) . '</span>';
								echo '<br><small>from <code>' . htmlspecialchars($f[$k]['path']) . '</code></small>';
							}
						?></td>
					</tr>
					<?php endforeach; ?>
				</table>
				</div>

				<?php if (!empty($r['html'])): ?>
					<p class="adm-note">Served HTML, so there is no JSON to read. What survived
					   without a browser:</p>
					<ul class="adm-note">
					<?php foreach ($r['html'] as $k => $v): ?>
						<li><code><?php echo htmlspecialchars($k); ?></code>:
							<?php echo htmlspecialchars(substr($v, 0, 240)); ?></li>
					<?php endforeach; ?>
					</ul>
				<?php endif; ?>

				<?php if (!empty($r['dug'])): ?>
					<details>
						<summary><small>Every interesting key it returned
							(<?php echo count($r['dug']); ?>)</small></summary>
						<div class="adm-tablewrap">
						<table class="adm-table" style="min-width:0">
						<?php foreach ($r['dug'] as $k => $hits): foreach ($hits as $h): ?>
							<tr>
								<td class="mono"><small><?php echo htmlspecialchars($h['path']); ?></small></td>
								<td class="mono"><small><?php
									$v = $h['value'];
									if (strlen($v) > 200) $v = substr($v, 0, 200) . '...';
									echo htmlspecialchars($v);
								?></small></td>
							</tr>
						<?php endforeach; endforeach; ?>
						</table>
						</div>
					</details>
				<?php endif; ?>

				<details>
					<summary><small>Raw reply, first 3000 bytes</small></summary>
					<pre style="white-space:pre-wrap;word-break:break-all;font-size:.74rem;
					            color:#5a7888;max-height:320px;overflow:auto"><?php
						echo htmlspecialchars(substr($r['body'], 0, 3000));
					?></pre>
				</details>
				<p class="adm-note"><small>Asked: <code><?php
					echo htmlspecialchars($r['url']); ?></code></small></p>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div></div>
</html>
