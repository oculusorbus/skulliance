<?php
include_once 'db.php';
include 'message.php';
include 'verify.php';
include 'skulliance.php';
include 'header.php';
?>

<a name="points" id="points"></a>
<div class="row" id="row1">
  <div class="col1of2">
    <h2>Core Project Points</h2>
    <div class="content" id="player-stats">
      <?php renderWalletConnection("points"); ?>
      <?php if(isset($_SESSION['userData']['user_id'])){ renderCurrency($conn); } ?>
    </div>
    <?php if(isset($_SESSION['userData']['user_id'])){ ?>
    <h2>Daily Rewards</h2>
    <div class="content" id="daily-rewards">
      <?php renderDailyRewardsSection(); ?>
    </div>
    <h2>Crafting</h2>
    <div class="content" id="player-stats">
      <?php renderCrafting($conn, "points"); ?>
    </div>
    <?php } ?>
  </div>
  <div class="col1of2">
    <h2>Partner Points</h2>
    <div class="content" id="player-stats">
      <?php if(isset($_SESSION['userData']['user_id'])){ renderCurrency($conn, false); } ?>
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
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php
$conn->close();
?>
</html>
