<?php
header('Content-Type: application/json'); // Set headers first

include '../db.php';
include '../webhooks.php';
include '../skulliance.php';

// Process JSON POST data
$data = json_decode(file_get_contents('php://input'), true);

if (isset($_SESSION['userData']['user_id']) && isset($data['score']) && isset($data['level'])) {
  $user_id = intval($_SESSION['userData']['user_id']); // Sanitize user_id
  $outcome = isset($data['outcome']) ? $data['outcome'] : 'win';

  // Only save the score for victories (not defeat notifications)
  if ($outcome !== 'loss') {
    $result = saveMonstrocityScore($conn, $user_id, $data['score'], $data['level']);
    echo json_encode($result);
  } else {
    echo json_encode(['status' => 'defeat_noted']);
  }

  // Discord webhook — Monstrocity campaign outcome
  $mn_opponent = isset($data['opponentName']) ? strip_tags($data['opponentName']) : '';
  $mn_theme    = isset($data['theme']) ? preg_replace('/[^a-z0-9\-]/', '', strtolower($data['theme'])) : 'monstrocity';
  $mn_level    = (int)$data['level'];
  $mn_score    = round((float)$data['score']);

  if ($mn_opponent !== '') {
    $mn_username   = !empty($_SESSION['userData']['username']) ? $_SESSION['userData']['username'] : (!empty($_SESSION['userData']['name']) ? $_SESSION['userData']['name'] : 'Unknown');
    $mn_discord    = isset($_SESSION['userData']['discord_id']) ? $_SESSION['userData']['discord_id'] : '';
    $mn_avatar     = isset($_SESSION['userData']['avatar']) ? $_SESSION['userData']['avatar'] : '';
    $mn_avatar_url = ($mn_discord && $mn_avatar) ? "https://cdn.discordapp.com/avatars/".$mn_discord."/".$mn_avatar.".png" : "";
    $mn_profile    = "https://skulliance.io/staking/profile.php?username=".urlencode($mn_username);
    $mn_mention    = $mn_discord ? "<@".$mn_discord.">" : $mn_username;
    $mn_opp_slug   = strtolower(str_replace(' ', '-', $mn_opponent));
    $mn_image_url  = "https://skulliance.io/staking/images/monstrocity/".$mn_theme."/".$mn_opp_slug.".png";
    // Player's selected character image (passed from game client)
    $mn_char_url = isset($data['characterImageUrl']) ? filter_var(trim($data['characterImageUrl']), FILTER_VALIDATE_URL) : false;
    $mn_icon     = $mn_char_url ?: $mn_avatar_url;
    $mn_author = array("name" => $mn_username, "icon_url" => $mn_icon, "url" => $mn_profile);

    if ($outcome !== 'loss') {
      $mn_desc  = $mn_mention." vanquished **".$mn_opponent."** on Level ".$mn_level."!\n\n";
      $mn_desc .= "🏆 **Score:** ".number_format($mn_score)."\n";
      $mn_desc .= "📊 **Level:** ".$mn_level." of 28";
      if ($mn_level === 28) $mn_desc .= "\n🎉 **Campaign Complete!**";
      discordmsg("⚔️ Level ".$mn_level." Victory", $mn_desc, $mn_image_url, "https://skulliance.io/staking/monstrocity.php", "monstrocity", $mn_icon, "00C8A0", $mn_author);
    } else {
      $mn_desc  = $mn_mention." was defeated by **".$mn_opponent."** on Level ".$mn_level."!\n\n";
      $mn_desc .= "📊 **Level Reached:** ".$mn_level." of 28\n";
      $mn_desc .= "🏆 **Score:** ".number_format($mn_score);
      discordmsg("💀 Level ".$mn_level." Defeat", $mn_desc, $mn_image_url, "https://skulliance.io/staking/monstrocity.php", "monstrocity", $mn_icon, "FF4444", $mn_author);
    }
  }
} else {
  echo json_encode(['status' => 'error', 'message' => 'User not logged in or missing score/level data']);
}

// Close DB Connection
$conn->close();
?>
