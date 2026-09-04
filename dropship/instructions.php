<?php
include 'db.php';
include 'webhooks.php';
include 'dropship.php';
include 'header.php';
?>
		<div class="row" id="row-instructions">
			<div class="main">
				<h2>How to Play</h2>
				<div class="content" style="text-align:left;">

					<p><?php echo evaluateText("Drop Ship");?> is a battlefield-style gamified raffle system. It requires that you stake your <?php echo evaluateText("Drop Ship");?> NFTs to receive the necessary Discord roles in order to participate in regular game sessions for coveted Shorty Verse NFT prizes.</p>

					<h3>Game Mechanics</h3>
					<p>Your squad's combined armor determines your odds of dying each round:</p>
					<ul>
						<li><?php echo evaluateText("Heavy")." + ".evaluateText("Medium")." + ".evaluateText("Light")." + ".evaluateText("Base")." Armor";?> &mdash; 20% chance of dying (1 in 5)</li>
						<li><?php echo evaluateText("Medium")." + ".evaluateText("Light")." + ".evaluateText("Base")." Armor";?> &mdash; 25% chance of dying (1 in 4)</li>
						<li><?php echo evaluateText("Light")." + ".evaluateText("Base")." Armor";?> &mdash; 33.33% chance of dying (1 in 3)</li>
						<li><?php echo evaluateText("Base")." Armor";?> &mdash; 50% chance of dying (1 in 2)</li>
					</ul>

					<p>Items shift the odds further:</p>
					<ul>
						<li><strong><?php echo evaluateText("Melee");?>:</strong> Delay death by 1 turn</li>
						<li><strong><?php echo evaluateText("Demolition");?>:</strong> Delay death by 2 turns</li>
						<li><strong><?php echo evaluateText("Medkit");?>:</strong> 1 extra life (multiple medkits don't stack &mdash; only 1 extra life max per player)</li>
					</ul>

					<p>Distinct traits are stacked from your NFTs. The ultimate setup is a "<?php echo evaluateText("Super Soldier");?> Squad" that collectively has at least one <?php echo evaluateText("Heavy");?> Armor, <?php echo evaluateText("Medium");?> Armor, <?php echo evaluateText("Light");?> Armor, <?php echo evaluateText("Base");?> Armor, <?php echo evaluateText("Medkit");?>, <?php echo evaluateText("Melee");?>, and <?php echo evaluateText("Demolition");?>.</p>

					<p>The game is luck-based, so these items only influence the outcome &mdash; they do not control it. Less powerful soldiers can still have a lucky run, and powerful soldiers can have a bad stroke of luck.</p>

					<h3>Play for Free</h3>
					<p>Every player gets 4 free <?php echo evaluateText("Grunt");?> soldiers automatically &mdash; no NFT required. Each <?php echo evaluateText("Grunt");?> carries the power of a full <?php echo evaluateText("Super Soldier");?> Squad (full armor, a <?php echo evaluateText("Medkit");?>, <?php echo evaluateText("Melee");?>, and <?php echo evaluateText("Demolition");?>), regardless of any traits. Send them in one at a time for full effect &mdash; that's 4 separate chances at winning, no purchase necessary.</p>

					<p>If you already hold <?php echo evaluateText("Drop Ship");?> NFTs, you'll still want to form your own <?php echo evaluateText("Super Soldier");?> Squad from them for the full item benefits above.</p>

					<h3>Scoring</h3>
					<p>The goal is to survive as long as you can &mdash; everyone dies eventually. If you run multiple times during a round, the game replaces your previous score with your new one. If you already have a good score, it may be in your best interest to keep it and earn the associated <?php echo evaluateText("SCRIP");?> rather than risking a lower score (and less <?php echo evaluateText("SCRIP");?>) on another attempt.</p>

					<p>Ohh Meed determines the number of top players who win a prize for any given match.</p>

					<h3>Weekly Prizes</h3>
					<p>Every Thursday at 4pm CST, the top 3 players for the week earn their share of <?php echo evaluateText("SCRIP");?>:</p>
					<ul>
						<li>1st Place: 1,000 <?php echo evaluateText("SCRIP");?></li>
						<li>2nd Place: 500 <?php echo evaluateText("SCRIP");?></li>
						<li>3rd Place: 250 <?php echo evaluateText("SCRIP");?></li>
					</ul>

					<p><em>The mechanics above are subject to change if the game needs further balancing or enhancements.</em></p>

					<h3>Reference: Armor Stacking</h3>
					<img src="<?php echo $prefix; ?>images/armor-stacking-calculations.png" alt="Armor stacking calculations chart" style="max-width:100%;border-radius:0.5rem;"/>

					<h3>Where to Buy <?php echo evaluateText("Drop Ship");?> NFTs</h3>
					<p><a href="https://www.wayup.io/collection/4478c708183e95340d0582419a2d6bc93d57657895c19802546d396c" target="_blank">wayup.io</a></p>

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
