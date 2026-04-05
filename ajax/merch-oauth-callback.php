<?php
/**
 * ajax/merch-oauth-callback.php
 * Printful OAuth 2.0 callback handler.
 * Exchanges the authorization code for access + refresh tokens,
 * stores them encrypted in merch_accounts, then redirects to merch.php.
 */
include '../db.php';

// ── Auth guard ──────────────────────────────────────────────
if (!isset($_SESSION['userData']['user_id'])) {
    header('Location: ../index.php');
    exit;
}
$user_id = intval($_SESSION['userData']['user_id']);

// ── Flash helper ─────────────────────────────────────────────
if (!isset($_SESSION['merch_flash'])) $_SESSION['merch_flash'] = [];
function cbFlash($msg, $type = 'info') {
    $_SESSION['merch_flash'][] = ['msg' => $msg, 'type' => $type];
}

// ── Validate state parameter ─────────────────────────────────
$state         = $_GET['state'] ?? '';
$session_state = $_SESSION['merch_oauth_state'] ?? '';
unset($_SESSION['merch_oauth_state']);

if (empty($state) || empty($session_state) || !hash_equals($session_state, $state)) {
    cbFlash('OAuth state mismatch. Please try connecting again.', 'error');
    header('Location: ../merch.php');
    exit;
}

// ── Check for errors from Printful ──────────────────────────
if (!empty($_GET['error'])) {
    $err_desc = $_GET['error_description'] ?? $_GET['error'];
    cbFlash('Printful authorization failed: ' . htmlspecialchars($err_desc), 'error');
    header('Location: ../merch.php');
    exit;
}

$code = $_GET['code'] ?? '';
if (empty($code)) {
    cbFlash('No authorization code received from Printful.', 'error');
    header('Location: ../merch.php');
    exit;
}

// ── Exchange code for tokens ─────────────────────────────────
$ch = curl_init('https://www.printful.com/oauth/token');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'client_id'     => PRINTFUL_CLIENT_ID,
    'client_secret' => PRINTFUL_CLIENT_SECRET,
    'redirect_uri'  => PRINTFUL_REDIRECT_URI,
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$token_resp = curl_exec($ch);
$token_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$token_data = json_decode($token_resp, true);

if ($token_http !== 200 || empty($token_data['access_token'])) {
    $err = $token_data['error_description'] ?? ($token_data['error'] ?? 'HTTP ' . $token_http);
    cbFlash('Failed to obtain access token from Printful: ' . htmlspecialchars($err), 'error');
    header('Location: ../merch.php');
    exit;
}

$access_token  = $token_data['access_token'];
$refresh_token = $token_data['refresh_token'] ?? '';
$expires_in    = intval($token_data['expires_in'] ?? 3600);
$expires_at    = date('Y-m-d H:i:s', time() + $expires_in);

// ── Fetch connected stores ───────────────────────────────────
$stores_ch = curl_init('https://api.printful.com/stores');
curl_setopt($stores_ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $access_token,
    'Content-Type: application/json',
]);
curl_setopt($stores_ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($stores_ch, CURLOPT_TIMEOUT, 10);
$stores_resp = curl_exec($stores_ch);
$stores_http = curl_getinfo($stores_ch, CURLINFO_HTTP_CODE);
curl_close($stores_ch);

$stores_data = json_decode($stores_resp, true);
$stores_json = ($stores_http === 200 && !empty($stores_data['result']))
    ? json_encode($stores_data['result'])
    : '[]';

// ── Encrypt tokens ───────────────────────────────────────────
$enc_access  = $conn->real_escape_string(merchEncrypt($access_token));
$enc_refresh = $conn->real_escape_string(merchEncrypt($refresh_token));
$stores_esc  = $conn->real_escape_string($stores_json);
$expires_esc = $conn->real_escape_string($expires_at);

// ── Upsert merch_accounts ────────────────────────────────────
$existing = $conn->query("SELECT id FROM merch_accounts WHERE user_id = $user_id LIMIT 1");
if ($existing && $existing->num_rows > 0) {
    $conn->query("
        UPDATE merch_accounts
        SET printful_access_token  = '$enc_access',
            printful_refresh_token = '$enc_refresh',
            token_expires_at       = '$expires_esc',
            connected_stores       = '$stores_esc',
            updated_at             = NOW()
        WHERE user_id = $user_id
        LIMIT 1
    ");
} else {
    $conn->query("
        INSERT INTO merch_accounts
            (user_id, printful_access_token, printful_refresh_token, token_expires_at, connected_stores)
        VALUES
            ($user_id, '$enc_access', '$enc_refresh', '$expires_esc', '$stores_esc')
    ");
}

cbFlash('Printful account connected!', 'success');
header('Location: ../merch.php');
$conn->close();
exit;
?>
