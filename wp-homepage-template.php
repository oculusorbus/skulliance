<?php
/**
 * Template name: Homepage
 *
 * COPY THIS FILE INTO THE WORDPRESS THEME. It is not used by /staking; it
 * lives here so the thing you paste into WordPress is version-controlled
 * alongside the page it pulls in.
 *
 * It replaces the old copy-pasted homepage. The real page lives at
 * /staking/homepage.php, so a push and a pull deploys it -- no copying, and
 * no chance of the two versions disagreeing.
 *
 * ABSPATH, NOT A HARDCODED PATH. WordPress defines ABSPATH as its own
 * installation directory with a trailing slash, so this resolves correctly
 * whatever the account's real docroot is. A guessed absolute path is what
 * produced a white screen on the first attempt: a template whose only
 * statement is an include that fails outputs NOTHING, and with
 * display_errors off in production that is a blank page with no clue in it.
 *
 * IT MUST NEVER RENDER NOTHING. If the file cannot be found or read, this
 * prints a plain page saying so rather than a white screen -- and, for an
 * administrator only, the exact path it looked at.
 */

$skulliance_home = ABSPATH . 'staking/homepage.php';

if (is_readable($skulliance_home)) {
	include $skulliance_home;
	return;
}

/* Fallback. Deliberately self-contained -- if the include is broken, the
   theme's own header/footer may be the thing that is broken. */
?><!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Skulliance</title>
  <meta name="robots" content="noindex">
  <style>
    body { margin:0; background:#07111d; color:#e8eaed; line-height:1.6;
           font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; }
    .w { max-width:640px; margin:0 auto; padding:80px 20px; text-align:center; }
    a { color:#00c8a0; }
    code { background:#0a1929; padding:2px 6px; border-radius:4px; font-size:.9em; }
  </style>
</head>
<body>
  <div class="w">
    <h1>Skulliance</h1>
    <p>The homepage is being updated. In the meantime, everything is running at
       <a href="/staking/">the staking platform</a>.</p>
    <?php if (function_exists('current_user_can') && current_user_can('manage_options')): ?>
    <p style="margin-top:40px;color:#7a9eb0;font-size:.85rem;">
      Admin only: the template looked for<br><code><?php echo htmlspecialchars($skulliance_home); ?></code><br>
      and could not read it.
    </p>
    <?php endif; ?>
  </div>
</body>
</html>
