<?php
include 'credentials/process_oauth_credentials.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


print_r($payload);

$payload_string = http_build_query($payload);
$discord_token_url = "https://discordapp.com/api/oauth2/token";

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $discord_token_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload_string);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

$result = curl_exec($ch);

if(!$result){
    echo curl_error($ch);
}

$result = json_decode($result,true);
$access_token = $result['access_token'];

$discord_users_url = "https://discordapp.com/api/users/@me";
$header = array("Authorization: Bearer $access_token", "Content-Type: application/x-www-form-urlencoded");

$ch = curl_init();
curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
curl_setopt($ch, CURLOPT_URL, $discord_users_url);
curl_setopt($ch, CURLOPT_POST, false);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

$result = curl_exec($ch);

$result = json_decode($result, true);

/*
function addUserToGuild($discord_ID,$token,$guild_ID){
    $payload = [
        'access_token'=>$token,
    ];

    $discord_api_url = 'https://discordapp.com/api/guilds/'.$guild_ID.'/members/'.$discord_ID;

    $bot_token = "YOUR BOT TOKEN (SAME AS APPLICATION)";
    $header = array("Authorization: Bot $bot_token", "Content-Type: application/json");

    $ch = curl_init();
    //set the url, number of POST vars, POST data
    curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
    curl_setopt($ch,CURLOPT_URL, $discord_api_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); //must be put for this method..
    curl_setopt($ch,CURLOPT_POSTFIELDS, json_encode($payload)); //must be a json body
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

    $result = curl_exec($ch);
    
    if(!$result){
        echo curl_error($ch);
    }else{
        return true;
    }
}

function getUsersGuilds($auth_token){
    //url scheme /users/@me/guilds
    $discord_api_url = "https://discordapp.com/api";
    $header = array("Authorization: Bearer $auth_token","Content-Type: application/x-www-form-urlencoded");
    $ch = curl_init();
    //set the url, number of POST vars, POST data
    curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
    curl_setopt($ch,CURLOPT_URL, $discord_api_url.'/users/@me/guilds');
    curl_setopt($ch,CURLOPT_POST, false);
    //curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $result = curl_exec($ch);
    $result = json_decode($result,true);
    return $result;
}*/



// Get multiple guild roles
function getUsersGuildsRoles($discord_ID,$auth_token,$guild_IDs){
	$final_result = array();
	$final_result["roles"] = array();
	foreach($guild_IDs AS $index => $guild_ID){
	    //url scheme /users/@me/guilds
	    $discord_api_url = "https://discordapp.com/api/users/@me/guilds/".$guild_ID."/member";
	    $header = array("Authorization: Bearer $auth_token","Content-Type: application/x-www-form-urlencoded");
	    $ch = curl_init();
	    //set the url, number of POST vars, POST data

	    curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
	    curl_setopt($ch,CURLOPT_URL, $discord_api_url); // /guilds.$guild_ID.'/members/'.$discord_ID
	    curl_setopt($ch,CURLOPT_POST, false);
	    //curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
	    curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
	    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	    $result = curl_exec($ch);
		
		$result = json_decode($result,true);
		if(isset($result['roles'])){
	    	$final_result['roles'] = array_merge($result['roles'], $final_result['roles']);
		}
	}
    return $final_result['roles'];
}

// Get single guild role
function getUsersGuildRoles($discord_ID,$auth_token,$guild_ID){
    //url scheme /users/@me/guilds
    $discord_api_url = "https://discordapp.com/api/users/@me/guilds/".$guild_ID."/member";
    $header = array("Authorization: Bearer $auth_token","Content-Type: application/x-www-form-urlencoded");
    $ch = curl_init();
    //set the url, number of POST vars, POST data

    curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
    curl_setopt($ch,CURLOPT_URL, $discord_api_url); // /guilds.$guild_ID.'/members/'.$discord_ID
    curl_setopt($ch,CURLOPT_POST, false);
    //curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $result = curl_exec($ch);

    $result = json_decode($result,true);
    return $result['roles'];
}

// Change session timeout value to 1 month = ~2678400 seconds
ini_set('session.gc_maxlifetime', 2678400);

// Each client should remember their session id for EXACTLY 1 month
session_set_cookie_params(2678400);

session_start();

// db.php is included here (after session_start(), not before) so its own
// conditional session_start() -- triggered by isset($_COOKIE[...]) -- can
// never fire ahead of the gc_maxlifetime/cookie_params calls above and
// undo their effect for this exact login.
include_once 'db.php';

$guild_ID = '944002913443938306';
//$addUserToGuild = addUserToGuild($result['id'],$access_token,$guild_ID);

// Skulliance, Ritual Guild (still integrated -- role assignment for its own
// holders, per the user), Ohh Meed (Drop Ship's original guild), Oculus
// Lounge (a Drop Ship reskin, project_id 4 -- see dropship/db.php's
// evaluateText()).
$guild_IDs = array('944002913443938306', '1235869893664964608', '925610311183130644', '966397496978964500');

$_SESSION['logged_in'] = true;
$_SESSION['userData'] = [
    'name'=>$result['username'],
    'discord_id'=>$result['id'],
    'avatar'=>$result['avatar'],
	// Get single guild role
    //'roles'=>getUsersGuildRoles($result['id'],$access_token,$guild_ID)
	// Get multiple guild roles
	'roles'=>getUsersGuildsRoles($result['id'],$access_token,$guild_IDs)
/*	'guilds'=>getUsersGuilds($access_token)*/
];

// The actual root cause of "logged in but treated as a guest" on any page
// that doesn't happen to go through skulliance.php first (Crypt Crawl,
// deliberately, since it's guest-playable): $_SESSION['userData']['user_id']
// was never set here at all. Every other gated page backfills it via
// skulliance.php's own checkUser($conn) call, which normally runs before
// the visitor can reach anything -- but a page like cryptcrawl.php reads
// $_SESSION['userData']['user_id'] directly and has no reason to ever call
// checkUser() itself. A fresh login therefore had a fully valid, correctly
// logged-in session (logged_in => true, real discord_id/name/avatar/roles)
// that nonetheless read as user_id 0 -- guest -- for the very first page
// visited after logging in, until some other page happened to backfill it.
// Confirmed directly from a live session dump: userData had no user_id key
// at all immediately after login. Calling checkUser() here means user_id
// is present from the first request onward, same as every other page.
checkUser($conn);

header("location: profile.php");
exit();
