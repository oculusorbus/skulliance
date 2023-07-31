<?php
include 'db.php';
include 'webhooks.php';
include 'header.php';
?>
		<div class="row" id="row4">
			<div class="main">
				<h2>Skulliance Staking</h2>
				<p>
					In order to stake with Skulliance, you will need to become a member.
				</p>
				<p>
					Membership requires owning at least 1 NFT from ALL of the following projects:
					<ul>
						<li>Crypties</li>
						<li>Kimosabe Art</li>
						<li>Sinder Skullz</li>
					</ul>
				</p>
				<p>
					<a href="http://discord.gg/JqqBZBrph2">Join Skulliance Discord</a> for a full list of qualifying policies, verification of NFTs, and claiming membership.
				</p>
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
<script type="text/javascript" src="skulliance.js"></script>
</html>