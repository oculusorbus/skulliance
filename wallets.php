<?php
include 'db.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
include 'header.php';

// Set primary wallet
if(isset($_POST['wallet_id'])){
	setPrimaryWallet($conn, $_POST['wallet_id']);
}

/*
 * DISCONNECTING X IS A POST, NOT A LINK. A GET would let an <img src>
 * in a Discord message unlink somebody's X account without them
 * touching anything.
 *
 * Nothing else is cleared: x_linked_at stays as a record that it once
 * happened, and the row keeps every other thing about the member.
 */
$wx_note = isset($_GET['x']) ? (string)$_GET['x'] : '';
if (isset($_POST['x_disconnect']) && !empty($_SESSION['userData']['user_id'])) {
	$wx_uid = (int)$_SESSION['userData']['user_id'];
	@$conn->query("UPDATE users SET x_id = NULL, x_username = NULL WHERE id = $wx_uid");
	unset($_SESSION['userData']['x_username']);
	$wx_note = 'disconnected';
}

/* What is attached right now, read fresh rather than trusted from the
   session -- this page is where somebody comes to check. */
$wx_handle = '';
if (!empty($_SESSION['userData']['user_id'])) {
	$wx_r = @$conn->query("SELECT x_username FROM users WHERE id = "
	                      . (int)$_SESSION['userData']['user_id'] . " LIMIT 1");
	if ($wx_r && ($wx_row = $wx_r->fetch_assoc())) $wx_handle = (string)$wx_row['x_username'];
}
$wx_said = array(
	'linked'      => 'Connected. You can sign in with X from now on.',
	'disconnected'=> 'Disconnected. Signing in with X will no longer find this account.',
	'taken'       => 'That X account is already connected to a different Skulliance account.',
	'cancelled'   => 'Cancelled at X. Nothing changed.',
	'failed'      => 'X did not complete the connection. Nothing changed.',
	'notoken'     => 'X would not complete the sign-in step, so nothing was connected. Setup problem our end.',
	'noprofile'   => 'X would not tell us who you are, so nothing was connected. Setup problem our end.',
	'expired'     => 'That took too long. Start again.',
	'unavailable' => 'X sign-in is not configured yet.',
);
?>
		<div class="row" id="row4">
			<div class="col1of3">
				<h2>Wallets</h2>
					<div class="content" id="player-stats">
						<?php 
						renderVisibility("wallets");
						renderWalletConnection("wallets"); ?>
						<li class="role">You can connect as many wallets as you want and set your primary address for staking store claims.</li>
						<?php
						/*
						 * X SITS HERE BECAUSE THIS IS THE CONNECTIONS PAGE --
						 * the one place you attach something external to your
						 * account. It is not a wallet and does not pretend to
						 * be: no staking, no holdings, nothing financial.
						 *
						 * CONNECTING IS THE ONLY WAY AN x_id IS EVER WRITTEN.
						 * X and Discord share no identifier, so this -- a
						 * member signed in with Discord, choosing -- is the
						 * single moment the two can be linked with certainty.
						 * Afterwards, signing in with X is a lookup that finds
						 * this row or finds nothing.
						 */
						require_once __DIR__ . '/lib/x-oauth.php';
						if (X_LOGIN_ENABLED && !empty($_SESSION['userData']['user_id'])):
						?>
						<li class="role" style="border-top:1px solid rgba(255,255,255,.08);margin-top:10px;padding-top:12px">
							<strong>X account</strong><br>
							<?php if ($wx_note !== '' && isset($wx_said[$wx_note])): ?>
								<span style="color:#00c8a0;font-size:.85rem"><?php echo htmlspecialchars($wx_said[$wx_note]); ?></span><br>
								<?php /* What X actually said, when it said anything. */
								$wx_why = isset($_GET['xw']) ? substr((string)$_GET['xw'], 0, 120) : '';
								if ($wx_why !== ''): ?>
								<span style="color:#b89a5e;font-size:.76rem;font-family:ui-monospace,Menlo,monospace">X said: <?php echo htmlspecialchars($wx_why); ?></span><br>
								<?php endif; ?>
							<?php endif; ?>
							<?php if ($wx_handle !== ''): ?>
								Connected as <strong>@<?php echo htmlspecialchars($wx_handle); ?></strong>.
								You can sign in with either X or Discord.
								<form action="wallets.php" method="post" style="display:inline">
									<input type="hidden" name="x_disconnect" value="1">
									<input class="small-button secondary" type="submit" value="Disconnect">
								</form>
							<?php else: ?>
								Not connected. Connecting lets you sign in with X as well as Discord,
								which is one tap from a post rather than a password, and lets your
								shared runs carry your handle.
								<a class="small-button" href="x-auth.php?mode=connect">Connect X</a>
							<?php endif; ?>
						</li>
						<?php endif; ?>
						<?php
						if(isset($_SESSION['userData']['user_id'])){ 
							$wallets = getWallets($conn);
							$wallet_counter = 1;
							foreach($wallets AS $id => $wallet){ 
								?>
								<li class="role">
									<?php
									echo $wallet_counter.".&nbsp;";
									if($wallet["main"] == "0"){
										echo "<form id='walletForm' action='wallets.php' method='post'>";
										echo "<input type='hidden' id='wallet_id' name='wallet_id' value='".$id."'>";
										echo "<input type='submit' value='Make Primary' class='small-button'>";
										echo "</form>";
									}else{
										echo "Primary Address: ";
									}
									echo "&nbsp;<a href='".accountExplorerUrl($wallet["address"], isset($wallet["blockchain_id"]) ? $wallet["blockchain_id"] : 1)."' target='_blank' rel='noopener'>".substr($wallet["address"], -20)."</a>&nbsp;";
									?>
								</li>
							<?php 
							$wallet_counter++;
							}
						} ?>
						</ul>
				</div>
			</div>
		</div>

		<!-- Footer -->
		<div class="footer">
		  <p>Skulliance<br>Copyright © <span id="year"></span>
		</div>
	</div>
  </div>
</body>
<?php
// Close DB Connection
$conn->close();
?>
<script type="module" src="wallet.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="skulliance.js"></script>
</html>