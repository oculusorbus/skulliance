<?php
/**
 * join.php -- the rules quiz, on the website instead of in Discord.
 *
 * WHY THIS EXISTS. The same quiz runs as a Discord message with buttons
 * (discord-rules-post.php / discord-interactions.php). That flow works, but
 * every click has to be answered inside DISCORD'S 3000ms INTERACTION
 * DEADLINE, and when it is missed the member sees only "the application did
 * not respond in time" -- with no retry that reads as a retry, no way for
 * them to tell us what happened, and no way for us to tell whether the
 * request even arrived. REPORTED, repeatedly, by people arriving from
 * partner projects: they bounce at the front door and we never hear from
 * them again.
 *
 * A web page has no such deadline. It can take two seconds to talk to
 * Discord and nobody notices; it can say exactly what went wrong; it can be
 * reloaded; and a half-finished attempt survives in the session.
 *
 * WHAT IT DOES NOT CHANGE. The questions, the roles and the guild all come
 * from discord-rules-lib.php -- the SAME file the bot uses, so the two
 * cannot drift. This is a second front door, not a second rulebook.
 *
 * NO JAVASCRIPT, DELIBERATELY. Every step is a plain form POST. Most people
 * open this from a link inside Discord, which means Discord's in-app
 * browser, and that is the last place to depend on client-side anything.
 *
 * PUBLIC. It must not include skulliance.php or verify.php -- the people it
 * exists for do not have accounts yet. discord-rules-lib.php pulls in
 * webhooks.php for the bot token and role ids, which is exactly what
 * discord-interactions.php (also public) does.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/db.php';                 // session, if they have one
require_once __DIR__ . '/discord-rules-lib.php';  // $SK_CHAINS, roles, token
require_once __DIR__ . '/role.php';               // assignRole()
require_once __DIR__ . '/lib/asset-version.php';

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/*
 * A WRONG ANSWER DOES NOT KICK HERE, and that is a deliberate difference
 * from the bot (SK_KICK_ON_WRONG is true there, because that is what
 * BotGhost did). Kicking somebody out of a server from a web page they
 * opened in a browser tab is a baffling experience, and the whole reason
 * this page exists is that we are losing people at the door. The quiz is a
 * speed bump, not a security boundary -- anyone determined enough to guess
 * four options twice was never the problem. Flip this to true to match the
 * bot exactly.
 */
if (!defined('SKJ_KICK_ON_WRONG')) define('SKJ_KICK_ON_WRONG', false);

if (!defined('SKJ_INVITE')) define('SKJ_INVITE', 'https://discord.gg/JqqBZBrph2');

/* The site's login IS Discord OAuth, so "sign in" and "tell us who you are
   on Discord" are the same step. Copied from index.php; identify + guilds is
   all this needs. */
$skj_oauth = 'https://discord.com/api/oauth2/authorize'
  . '?client_id=1007909434141462588'
  . '&permissions=268437504'
  . '&redirect_uri=' . rawurlencode('https://skulliance.io/staking/process-oauth.php')
  . '&response_type=code&scope=identify%20guilds%20guilds.members.read';

$skj_discord_id = (string) ($_SESSION['userData']['discord_id'] ?? '');
$skj_name       = (string) ($_SESSION['userData']['name'] ?? ($_SESSION['userData']['username'] ?? ''));

/**
 * One GET against the guild member endpoint. Returns:
 *   array  -- the member object (they are in the server)
 *   false  -- a definite "not a member" (404)
 *   null   -- we could not tell (network, rate limit, bad token)
 *
 * The three are kept apart on purpose: telling somebody who IS in the
 * server to go and join it is exactly the kind of dead end this page is
 * meant to remove.
 */
function skj_member($discord_id) {
	global $bot_token, $discord_guild_id;
	if ($discord_id === '' || empty($bot_token)) return null;
	$api = defined('SKULL_DISCORD_API') ? SKULL_DISCORD_API : 'https://discord.com';
	$ch  = curl_init($api . '/api/v10/guilds/' . rawurlencode($discord_guild_id)
	                 . '/members/' . rawurlencode($discord_id));
	curl_setopt_array($ch, [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_HTTPHEADER     => ['Authorization: Bot ' . $bot_token],
		CURLOPT_TIMEOUT        => 10,   /* no 3s deadline here; this is the point */
		CURLOPT_CONNECTTIMEOUT => 5,
	]);
	$body = curl_exec($ch);
	$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($code === 404) return false;
	if ($code < 200 || $code > 299) {
		error_log('join.php: member lookup returned ' . $code . ' for ' . $discord_id);
		return null;
	}
	$j = json_decode((string) $body, true);
	return is_array($j) ? $j : null;
}

/* ------------------------------------------------------------------ state */
$skj_step   = 'intro';
$skj_error  = '';
$skj_member = null;

if ($skj_discord_id !== '') {
	$skj_member = skj_member($skj_discord_id);
}

$skj_missing = sk_config_missing();
if ($skj_missing) {
	/* Say so plainly rather than failing at the last step, after somebody
	   has answered three questions for nothing. */
	$skj_step  = 'broken';
	$skj_error = 'This page is not fully configured yet (' . htmlspecialchars(implode(', ', $skj_missing)) . ').';
	error_log('join.php: missing config: ' . implode(', ', $skj_missing));
}

$skj_action = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' ? (string) ($_POST['step'] ?? '') : '';

if ($skj_step !== 'broken') {
	if ($skj_discord_id === '') {
		$skj_step = 'signin';
	} elseif ($skj_member === null) {
		$skj_step  = 'unknown';
	} elseif ($skj_member === false) {
		$skj_step  = 'notmember';
	} elseif (in_array((string) $role_candidate, array_map('strval', (array) ($skj_member['roles'] ?? [])), true)) {
		$skj_step = 'already';
	} else {
		/* ---- the quiz itself ---- */
		$chain = (string) ($_POST['chain'] ?? $_SESSION['skj_chain'] ?? '');
		if (!isset($SK_CHAINS[$chain])) $chain = '';
		$_SESSION['skj_chain'] = $chain;

		if ($skj_action === 'answer' && $chain !== '') {
			$index  = (int) ($_POST['index'] ?? 0);
			$choice = (string) ($_POST['choice'] ?? '');
			if ($choice === '') {
				$skj_error = 'Pick one of the four.';
				$skj_step  = 'question';
				$skj_index = $index;
			} elseif (sk_is_correct($chain, $index, $choice)) {
				$skj_index = $index + 1;
				if ($skj_index >= count($SK_CHAINS[$chain]['questions'])) {
					/* Passed. Add first, then remove -- the same order the bot
					   uses, so a failure at the second call leaves them holding
					   both roles rather than none. */
					$granted = assignRole($skj_discord_id, $role_candidate, '', $discord_guild_id);
					if (!$granted) {
						$skj_step  = 'rolefail';
					} else {
						if (defined('SK_REQUIRE_VISITOR') && $role_visitor !== '') {
							assignRole($skj_discord_id, $role_visitor, 'delete', $discord_guild_id);
						}
						unset($_SESSION['skj_chain']);
						$skj_step = 'passed';
					}
				} else {
					$skj_step = 'question';
				}
			} else {
				if (SKJ_KICK_ON_WRONG) {
					$skj_step = 'wrong-final';
				} else {
					$skj_error = 'That is not right. Have another go.';
					$skj_step  = 'question';
					$skj_index = $index;
				}
			}
		} elseif ($chain !== '') {
			$skj_step  = 'question';
			$skj_index = 0;
		} else {
			$skj_step = 'chain';
		}
	}
}
if (!isset($skj_index)) $skj_index = 0;
$skj_chain = (string) ($_SESSION['skj_chain'] ?? '');

/* Coming back here after the OAuth round trip. process-oauth.php reads this
   and honours it against its own whitelist. */
if ($skj_step === 'signin') $_SESSION['skl_after_login'] = 'join.php';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Join the Skulliance</title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="dist/flexbox.css?v=<?php echo skl_asset_v(); ?>">
<style>
/* Self-contained: this page is for people who do not have an account yet,
   so it cannot use header.php (login gate) and should not depend on it. */
body { background:#07111d; color:#e8eaed; margin:0;
       font-family:Arial, Helvetica, sans-serif; }
.j-wrap { max-width:620px; margin:0 auto; padding:28px 16px 64px;
          padding-bottom:calc(64px + env(safe-area-inset-bottom, 0px)); }
.j-card { background:#0d2230; border:1px solid rgba(0,200,160,.18);
          padding:22px; margin-bottom:18px; }
.j-card h1 { font-size:1.25rem; margin:0 0 6px; color:#00c8a0;
             letter-spacing:.03em; text-transform:uppercase; }
.j-card h2 { font-size:1.05rem; margin:0 0 14px; color:#e8eaed; }
.j-card p  { line-height:1.5; margin:0 0 14px; }
.j-muted   { color:rgba(232,234,237,.72); font-size:.9rem; }
.j-err     { background:#3a1620; border:1px solid #ff5c5c; color:#ffd7d7;
             padding:10px 12px; margin:0 0 16px; font-size:.92rem; }
.j-step    { color:rgba(232,234,237,.55); font-size:.78rem;
             letter-spacing:.09em; text-transform:uppercase; margin:0 0 10px; }
.j-btn { display:block; width:100%; box-sizing:border-box; text-align:left;
         background:#123349; color:#e8eaed; border:1px solid rgba(0,200,160,.28);
         padding:14px 16px; margin:0 0 10px; font-size:1rem; font-weight:bold;
         cursor:pointer; font-family:inherit; }
.j-btn:hover, .j-btn:focus-visible { background:#1a4663; border-color:#00c8a0; }
.j-go { background:#00c8a0; color:#07111d; border:1px solid #2ee3bf;
        text-align:center; text-decoration:none; }
.j-go:hover, .j-go:focus-visible { background:#00e6b8; color:#07111d; }
form { margin:0; }
</style>
</head>
<body>
<div class="j-wrap">

<?php if ($skj_step === 'broken'): ?>
  <div class="j-card">
    <h1>Not available</h1>
    <p>Verification is temporarily unavailable. Nothing you did caused this.</p>
    <p class="j-muted">Please come back shortly, or ask in the server and a moderator will let you in by hand.</p>
  </div>

<?php elseif ($skj_step === 'signin'): ?>
  <div class="j-card">
    <h1>Join the Skulliance</h1>
    <p>Two quick questions about the chain you know best, and you are in.</p>
    <p class="j-muted">First, tell us who you are on Discord. This is the same
       sign-in the rest of the site uses, and it is how we know which account
       to give the role to.</p>
    <a class="j-btn j-go" href="<?php echo htmlspecialchars($skj_oauth); ?>">Continue with Discord</a>
  </div>

<?php elseif ($skj_step === 'unknown'): ?>
  <div class="j-card">
    <h1>Could not check</h1>
    <p>We could not reach Discord just now to look up your membership.</p>
    <p class="j-muted">This is us, not you. Try again in a moment.</p>
    <form method="post"><button class="j-btn j-go" type="submit" name="step" value="recheck">Try again</button></form>
  </div>

<?php elseif ($skj_step === 'notmember'): ?>
  <div class="j-card">
    <h1>Join the server first</h1>
    <p>You are signed in as <strong><?php echo htmlspecialchars($skj_name !== '' ? $skj_name : 'your Discord account'); ?></strong>,
       but you are not in the Skulliance server yet.</p>
    <a class="j-btn j-go" href="<?php echo htmlspecialchars(SKJ_INVITE); ?>" target="_blank" rel="noopener">Open the invite</a>
    <form method="post"><button class="j-btn" type="submit" name="step" value="recheck">I have joined - check again</button></form>
  </div>

<?php elseif ($skj_step === 'already'): ?>
  <div class="j-card">
    <h1>You are already in</h1>
    <p><?php echo htmlspecialchars($SK_WELCOME); ?></p>
    <a class="j-btn j-go" href="launchpad.php">Go to the Launchpad</a>
  </div>

<?php elseif ($skj_step === 'rolefail'): ?>
  <div class="j-card">
    <h1>Nearly</h1>
    <p>You answered correctly, but we could not set your role just now.</p>
    <p class="j-muted">A moderator has been notified. Your answers are kept -
       pressing the button again is all it takes once it is fixed.</p>
    <form method="post">
      <input type="hidden" name="chain" value="<?php echo htmlspecialchars($skj_chain); ?>">
      <input type="hidden" name="index" value="<?php echo (int) (count($SK_CHAINS[$skj_chain]['questions'] ?? []) - 1); ?>">
      <button class="j-btn j-go" type="submit" name="step" value="recheck">Try again</button>
    </form>
  </div>

<?php elseif ($skj_step === 'wrong-final'): ?>
  <div class="j-card">
    <h1>Not quite</h1>
    <p>That was not the right answer.</p>
    <p class="j-muted">Ask in the server and a moderator will help you in.</p>
  </div>

<?php elseif ($skj_step === 'passed'): ?>
  <div class="j-card">
    <h1>You are in</h1>
    <p><?php echo htmlspecialchars($SK_WELCOME); ?></p>
    <a class="j-btn j-go" href="<?php echo htmlspecialchars(SKJ_INVITE); ?>">Back to Discord</a>
    <p class="j-muted" style="margin-top:14px">Your role is set. If Discord still
       shows you as a visitor, close and reopen the app.</p>
  </div>

<?php elseif ($skj_step === 'chain'): ?>
  <div class="j-card">
    <p class="j-step">Step 1 of 3</p>
    <h2>Which chain do you know best?</h2>
    <p class="j-muted">There is no wrong answer here. The next two questions
       will be about whichever one you pick.</p>
    <form method="post">
      <?php foreach ($SK_CHAINS as $key => $c): ?>
        <button class="j-btn" type="submit" name="chain" value="<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars($c['label']); ?></button>
      <?php endforeach; ?>
    </form>
  </div>

<?php else:
  $q = $SK_CHAINS[$skj_chain]['questions'][$skj_index] ?? null;
  if ($q === null) { $q = ['prompt' => '', 'options' => []]; }
?>
  <div class="j-card">
    <p class="j-step">Step <?php echo (int) ($skj_index + 2); ?> of 3 &middot; <?php echo htmlspecialchars($SK_CHAINS[$skj_chain]['label'] ?? ''); ?></p>
    <?php if ($skj_error !== ''): ?><p class="j-err"><?php echo htmlspecialchars($skj_error); ?></p><?php endif; ?>
    <h2><?php echo htmlspecialchars($q['prompt']); ?></h2>
    <form method="post">
      <input type="hidden" name="step"  value="answer">
      <input type="hidden" name="chain" value="<?php echo htmlspecialchars($skj_chain); ?>">
      <input type="hidden" name="index" value="<?php echo (int) $skj_index; ?>">
      <?php
      /* SHUFFLED, like sk_question_payload() does for the bot. The options
         are authored correct-answer-first in discord-rules-lib.php, so a
         fixed order would put the right answer at the top of every single
         question and the quiz would test nothing. */
      $opt_keys = array_keys($q['options']);
      shuffle($opt_keys);
      foreach ($opt_keys as $k): ?>
        <button class="j-btn" type="submit" name="choice" value="<?php echo htmlspecialchars($k); ?>"><?php echo htmlspecialchars($q['options'][$k]); ?></button>
      <?php endforeach; ?>
    </form>
  </div>
<?php endif; ?>

</div>
</body>
</html>
