<?php
include 'db.php';
include 'webhooks.php';
include 'dropship.php';
include 'header.php';
?>
		<!-- Page-local styling only -- icon-led rows and card-style section
		     grouping so this reads as scannable sections instead of one long
		     block of prose. Reuses the site's own established teal-hairline
		     card look (same border/background/radius as .inventory-item in
		     dist/flexbox.css) rather than inventing a new visual language. -->
		<style>
		.instr-section { background-color: #0a1929; border: 1px solid rgba(0,200,160,0.15); border-radius: 0.5rem; padding: 15px 20px; margin-bottom: 18px; }
		.instr-section h3 { margin-top: 0; }
		.instr-row { display: flex; align-items: center; gap: 12px; margin: 12px 0; }
		.instr-icon { width: 34px; height: 34px; flex-shrink: 0; }
		.instr-icons { display: flex; gap: 3px; flex-shrink: 0; }
		</style>
		<div class="row" id="row-instructions">
			<div class="main">
				<h2>How to Play</h2>
				<div class="content" style="text-align:left;">

					<p><?php echo evaluateText("Drop Ship");?> is a battlefield-style gamified raffle system. It requires that you stake your <?php echo evaluateText("Drop Ship");?> NFTs to receive the necessary Discord roles in order to participate in regular game sessions for coveted Shorty Verse NFT prizes.</p>

					<div class="instr-section">
						<h3>Game Mechanics</h3>
						<p>Your squad's combined armor determines your odds of dying each round &mdash; the more distinct armor types you stack together, the safer you are:</p>

						<div class="instr-row">
							<span class="instr-icons">
								<img class="instr-icon" src="icons/<?php echo evaluateText("heavy-armor");?>.png" alt=""/>
								<img class="instr-icon" src="icons/<?php echo evaluateText("medium-armor");?>.png" alt=""/>
								<img class="instr-icon" src="icons/<?php echo evaluateText("light-armor");?>.png" alt=""/>
								<img class="instr-icon" src="icons/<?php echo evaluateText("base-armor");?>.png" alt=""/>
							</span>
							<span><strong>Full Set</strong> (all four types) &mdash; 20% chance of dying (1 in 5)</span>
						</div>
						<div class="instr-row">
							<span class="instr-icons">
								<img class="instr-icon" src="icons/<?php echo evaluateText("heavy-armor");?>.png" alt=""/>
								<img class="instr-icon" src="icons/<?php echo evaluateText("base-armor");?>.png" alt=""/>
							</span>
							<span>&mdash; or &mdash;</span>
							<span class="instr-icons">
								<img class="instr-icon" src="icons/<?php echo evaluateText("medium-armor");?>.png" alt=""/>
								<img class="instr-icon" src="icons/<?php echo evaluateText("light-armor");?>.png" alt=""/>
								<img class="instr-icon" src="icons/<?php echo evaluateText("base-armor");?>.png" alt=""/>
							</span>
							<span>25% chance of dying (1 in 4)</span>
						</div>
						<div class="instr-row">
							<span class="instr-icons">
								<img class="instr-icon" src="icons/<?php echo evaluateText("light-armor");?>.png" alt=""/>
								<img class="instr-icon" src="icons/<?php echo evaluateText("base-armor");?>.png" alt=""/>
							</span>
							<span>33.33% chance of dying (1 in 3)</span>
						</div>
						<div class="instr-row">
							<span class="instr-icons">
								<img class="instr-icon" src="icons/<?php echo evaluateText("base-armor");?>.png" alt=""/>
							</span>
							<span><?php echo evaluateText("Base");?> Armor alone &mdash; 50% chance of dying (1 in 2)</span>
						</div>
						<p>There's more than one path to the same odds, as shown above &mdash; what matters is the total, not any one specific combination.</p>
					</div>

					<div class="instr-section">
						<h3>Items</h3>
						<p>Items shift the odds further, on top of your armor:</p>
						<div class="instr-row">
							<img class="instr-icon" src="icons/<?php echo evaluateText("melee");?>.png" alt=""/>
							<span><strong><?php echo evaluateText("Melee");?></strong> &mdash; delay death by 1 turn</span>
						</div>
						<div class="instr-row">
							<img class="instr-icon" src="icons/<?php echo evaluateText("demolition");?>.png" alt=""/>
							<span><strong><?php echo evaluateText("Demolition");?></strong> &mdash; delay death by 2 turns</span>
						</div>
						<div class="instr-row">
							<img class="instr-icon" src="icons/<?php echo evaluateText("medkit");?>.png" alt=""/>
							<span><strong><?php echo evaluateText("Medkit");?></strong> &mdash; 1 extra life (doesn't stack, max 1 per player)</span>
						</div>
						<p>Distinct traits stack from across your whole NFT squad. The ultimate setup is a</p>
						<div class="instr-row">
							<img class="instr-icon" src="icons/<?php echo evaluateText("supersoldier");?>.png" alt=""/>
							<span><strong><?php echo evaluateText("Super Soldier");?> Squad</strong> &mdash; at least one of every armor type, plus a <?php echo evaluateText("Medkit");?>, <?php echo evaluateText("Melee");?>, and <?php echo evaluateText("Demolition");?>, all held collectively.</span>
						</div>
						<p><em>The game is luck-based &mdash; items only influence the outcome, they don't control it. Less powerful soldiers can still get a lucky run, and powerful soldiers can have a bad stroke of luck.</em></p>
					</div>

					<div class="instr-section">
						<h3>Play for Free</h3>
						<div class="instr-row">
							<img class="instr-icon" src="icons/<?php echo evaluateText("supersoldier");?>.png" alt=""/>
							<span>Every player gets <strong>4 free <?php echo evaluateText("Grunt");?> soldiers</strong> automatically &mdash; no NFT required, and each one carries full <?php echo evaluateText("Super Soldier");?> Squad power regardless of traits.</span>
						</div>
						<p>Send them in one at a time for full effect &mdash; that's 4 separate chances at winning, no purchase necessary. If you already hold <?php echo evaluateText("Drop Ship");?> NFTs, you'll still want to form your own <?php echo evaluateText("Super Soldier");?> Squad from them for the full item benefits above.</p>
					</div>

					<div class="instr-section">
						<h3>Scoring</h3>
						<div class="instr-row">
							<img class="instr-icon" src="icons/scoreboard.png" alt=""/>
							<span>The goal is to survive as long as you can &mdash; everyone dies eventually.</span>
						</div>
						<p>If you run multiple times during a round, the game replaces your previous score with your new one. If you already have a good score, it may be in your best interest to keep it and earn the associated <?php echo evaluateText("SCRIP");?> rather than risking a lower score (and less <?php echo evaluateText("SCRIP");?>) on another attempt.</p>
						<p>Ohh Meed determines the number of top players who win a prize for any given match.</p>
					</div>

					<div class="instr-section">
						<h3>Weekly Prizes</h3>
						<p>Every Thursday at 4pm CST, the top 3 players for the week are locked in.</p>
						<?php if($_SESSION['userData']['dropship_project_id'] == 1 || $_SESSION['userData']['dropship_project_id'] == 4){
							// Real currency conversion into the player's Skulliance
							// balance -- see logBalances() in db.php, which posts to
							// Skulliance's own db.php and credits project_id 2
							// (DREAD) for Drop Ship or 21 (MOON) for Oculus Lounge.
							// Only these two projects are wired to a real Skulliance
							// currency; Dread City still runs on its own in-game
							// evaluateText("SCRIP") economy below.
							$payout_currency = ($_SESSION['userData']['dropship_project_id'] == 1) ? "DREAD" : "MOON";
							$payout_icon = ($_SESSION['userData']['dropship_project_id'] == 1) ? "/staking/icons/dread.png" : "/staking/icons/moon.png";
						?>
						<div class="instr-row">
							<img class="instr-icon" src="<?php echo $payout_icon;?>" alt=""/>
							<span><strong>1st Place</strong> &mdash; 1,000 <?php echo $payout_currency;?></span>
						</div>
						<div class="instr-row">
							<img class="instr-icon" src="<?php echo $payout_icon;?>" alt=""/>
							<span><strong>2nd Place</strong> &mdash; 500 <?php echo $payout_currency;?></span>
						</div>
						<div class="instr-row">
							<img class="instr-icon" src="<?php echo $payout_icon;?>" alt=""/>
							<span><strong>3rd Place</strong> &mdash; 250 <?php echo $payout_currency;?></span>
						</div>
						<p>This isn't <?php echo evaluateText("SCRIP");?> &mdash; it's a direct <strong><?php echo $payout_currency;?></strong> credit to your real Skulliance balance, converted automatically the moment the week closes. Landing in the top 3 trades that round's <?php echo evaluateText("SCRIP");?> for the bigger, platform-wide payout instead.</p>
						<?php }else{ ?>
						<div class="instr-row">
							<img class="instr-icon" src="icons/trophy.png" alt=""/>
							<span><strong>1st Place</strong> &mdash; 1,000 <?php echo evaluateText("SCRIP");?></span>
						</div>
						<div class="instr-row">
							<img class="instr-icon" src="icons/scrip.png" alt=""/>
							<span><strong>2nd Place</strong> &mdash; 500 <?php echo evaluateText("SCRIP");?></span>
						</div>
						<div class="instr-row">
							<img class="instr-icon" src="icons/scrip.png" alt=""/>
							<span><strong>3rd Place</strong> &mdash; 250 <?php echo evaluateText("SCRIP");?></span>
						</div>
						<?php } ?>
						<p><em>The mechanics above are subject to change if the game needs further balancing or enhancements.</em></p>
					</div>

					<h3>Where to Buy <?php echo evaluateText("Drop Ship");?> NFTs</h3>
					<?php
					// Oculus Lounge plays on the Disco Solaris NFT policy, a
					// different collection entirely from Drop Ship's own -- see
					// the same policy id used in dropshipSyncOculusLounge() in
					// db.php. Every other project still points at Drop Ship's
					// own wayup.io collection.
					$nft_marketplace_url = ($_SESSION['userData']['dropship_project_id'] == 4)
						? "https://www.wayup.io/collection/d0112837f8f856b2ca14f69b375bc394e73d146fdadcc993bb993779"
						: "https://www.wayup.io/collection/4478c708183e95340d0582419a2d6bc93d57657895c19802546d396c";
					?>
					<p><a href="<?php echo $nft_marketplace_url;?>" target="_blank">wayup.io</a></p>

					<p style="text-align:center;margin-top:20px;"><strong>Let the games begin. And remember, no one survives <?php echo evaluateText("Drop Ship");?>.</strong></p>

				</div>
			</div>
		</div>

		<!-- Footer -->
		<div class="footer">
		  <p>Drop Ship | Ohh Meed's Shorty Verse<br>Copyright © <span id="year"></span>
		</div>
	</div>
  </div>
</body>
<?php
// Close DB Connection
$conn->close();
?>
<script type="text/javascript" src="dropship.js"></script>
</html>
