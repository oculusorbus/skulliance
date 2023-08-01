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
					<table style="background-color:black">
						<tr>
						<td width="33%" valign="top"><a href="https://twitter.com/cryptiesnft" target="_blank"><img width="100%" src="images/flyers/crypties.jpg" /></a></td>
						<td width="33%" valign="top"><a href="https://twitter.com/SinderSkullz" target="_blank"><img width="100%" src="images/flyers/sinder.jpg" /></a></td>
						<td width="33%" valign="top"><a href="https://twitter.com/Nft4R" target="_blank"><img width="100%" src="images/flyers/kimo.jpg" /></a></td>
					</table>
				</p>
				<p>
					<a href="http://discord.gg/JqqBZBrph2" target="_blank">Join Skulliance Discord</a> for a full list of qualifying policies, verification of NFTs, and claiming membership. The minimum total cost for all the required NFTs for membership is approx 40 ADA.
				</p>
				<p>
					Once the necessary NFTs are verified in Discord and you've claimed your membership, you will be able to stake with Skulliance, earn off-chain currency, and claim NFT/FT rewards.
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