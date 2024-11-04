<?php
include 'db.php';
include 'webhooks.php';
include 'skulliance.php';
include 'header.php';
?>
		<div class="row" id="row4">
			<div class="main col1of3">
				<h2><img style="max-width:300px;width:100%;" src="images/skulliancelogoweb.png"/></h2>
				<p>
					In order to claim items in the Skulliance staking store, you will need to become a member.
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
					<a href="http://discord.gg/JqqBZBrph2" target="_blank">Join Skulliance Discord</a> for a full list of qualifying policies or visit <a href="collections.php">collections</a>. The minimum total cost for all the required NFTs for membership is approx 20-40 ADA.
				</p>
				<p>
					The staking site will automatically assign roles and membership. You may have to hit the refresh button next to the wallet dropdown or log out and back in to the staking platform.
				</p>
				<p>
					Once the necessary NFTs are verified on the staking platform and you're automatically assigned membership, you will be able to claim NFT/FT rewards from the staking store. 
				</p>
				<!--
				<video width="100%" style="background-color:black" poster="images/og.jpg" controls>
				  <source src="images/staking.mp4" type="video/mp4">
				Your browser does not support the video tag.
				</video>-->
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