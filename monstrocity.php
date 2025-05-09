<?php
session_start();

// Initialize default variables
$member = false;
$elite = false;
$innercircle = false;
$dev = false;

// Restore session from cookie if logged out
if (!isset($_SESSION['logged_in'])) {
    if (isset($_COOKIE['SessionCookie'])) {
        $cookie = $_COOKIE['SessionCookie'];
        $cookieData = json_decode($cookie, true);
        if (is_array($cookieData)) {
            $_SESSION = $cookieData;
        }
    }
}

$user_id = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;

// Process user data only if valid session exists
if (isset($_SESSION['userData']) && is_array($_SESSION['userData'])) {
    extract($_SESSION['userData']);
    if (isset($roles) && is_array($roles) && !empty($roles)) {
        foreach ($roles as $key => $roleData) {
            switch ($roleData) {
                case "949930195584954378":
                    $member = true;
                    break;
                case "949930360681140274":
                    $elite = true;
                    break;
                case "949930529841635348":
                    $innercircle = true;
                    break;
                default:
                    break;
            }
        }
    }
	if($_SESSION['userData']['discord_id'] == "772831523899965440"){
		$dev = true;
	}
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Monstrocity Match-3</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      background-color: #121212;
      color: #fff;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      margin: 0;
      background-size: cover;
      background-position: center;
  	  background-attachment: fixed;
    }
	
	#theme-select {
	  padding: 5px;
	  margin: 10px 0;
	  background-color: #165777;
	  color: #fff;
	  border: 1px solid black;
	  border-radius: 5px;
	  font-size: 16px;
	  max-width: 265px;
	}

    h2 {
      margin-top: 0px;
      margin-bottom: 10px;
      font-weight: bold;
	  white-space: nowrap;
    }
	
	a {
		color: white;
		font-weight: bold;
		text-decoration: none;
	}

	.game-container {
	  text-align: center;
	  padding: 20px;
	  background-color: #002f44;
	  box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
	  width: 100%;
	  height: 100%;
	  min-width: 910px;
	  max-width: 1024px;
	  box-sizing: border-box;
	  border-left: 3px solid black;
	  border-right: 3px solid black;
	  display: none; /* Initially hidden */
	  height: 100%;
	  max-height: 2050px;
	}

    .game-logo {
      max-width: 300px;
      height: auto;
      margin: 0 auto 10px;
      display: block;
      -webkit-filter: drop-shadow(2px 5px 10px #000);
      filter: drop-shadow(2px 5px 10px #000);
    }

    .turn-indicator {
      font-size: 1.2em;
      margin-bottom: 20px;
      color: #fff;
      position: relative;
      top: 10px;
      font-weight: bold;
    }

    .battlefield {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 20px;
      margin-bottom: 20px;
    }

    .character {
      width: 265px;
      padding: 15px;
      background-color: #165777;
      border-radius: 5px;
      text-align: center;
      flex-shrink: 0;
      position: relative;
      top: -225px;
      min-height: 552px;
      border: 1px solid black;
    }

	.character img, .character video {
		width: 100%;
	    height: auto;
	    margin-bottom: 10px;
	    border-radius: 5px;
	    transition: transform 0.1slinear, filter 0.5sease;
	    -webkit-filter: drop-shadow(2px 5px 10px #000);
	    filter: drop-shadow(2px 5px 10px #000);
	    min-height: 265px;
	}
    
    .character p{
      font-weight: bold;
    }
    
    .character table {
      width: 100%;
      text-align: left;
	  margin-top: 10px;
    }
    
    .character td{
      padding-top: 5px;
      width: 58%;
      text-align: right;
    }
    
    .character .attribute-label {
      font-weight: bold;
      width: 42%;
      text-align: left;
    }
    
    .character .attribute {
      font-weight: normal;
    }
	
	#player1 img, #player2 img {
	  display: none;
	}
	
	#p1-image:hover {
	  cursor: pointer;
	}
    
    .health-bar {
      width: 100%;
      height: 20px;
      background-color: #525F65;
      border-radius: 5px;
      overflow: hidden;
      margin: 5px 0;
	  -webkit-filter: drop-shadow(2px 5px 10px #000);
      filter: drop-shadow(2px 5px 10px #000);
	  border: 1px solid black;
    }

    .health {
      height: 100%;
      background-color: #4CAF50;
      transition: width 0.3s ease;
    }

    #game-board {
      box-sizing: border-box;		
      display: grid;
      gap: 0.5vh;
      background: #165777;
      padding: 1vh;
      box-sizing: border-box;
      user-select: none;
      position: relative;
      touch-action: none;
      width: 342px;
      height: 342px;
      grid-template-columns: repeat(5, 1fr);
      flex-shrink: 0;
      margin-top: 17px;
      border-radius: 5px;
      border: 1px solid black;
    }

    .tile {
      box-sizing: border-box;
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5vh;
      cursor: pointer;
      transition: transform 0.2s ease;
      position: relative;
      background: #444;
      box-sizing: border-box;
      z-index: 1;
      border: 1px solid black;
      box-shadow: 0px 2px 5px black;
    }
	
	.tile:hover {
		border: 1px solid white;
	}
    
    .tile img {
      width: 80%;
      height: 80%;
      object-fit: contain;
    }
    
    .legend-tile img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }

    .tile.game-over {
      filter: grayscale(100%);
    }

    .tile.first-attack { background-color: #4CAF50; }
    .tile.second-attack { background-color: #2196F3; }
    .tile.special-attack { background-color: #FFC107; }
    .tile.power-up { background-color: #9C27B0; }
    .tile.last-stand { background-color: #F44336; }

    .selected {
      transform: scale(1.05);
      outline: 0.25vh solid white;
      outline-offset: -0.25vh;
      z-index: 10;
      pointer-events: none;
    }

    .matched {
      animation: matchAnimation 0.3s ease forwards;
    }

    .falling {
      transition: transform 0.3s ease-out;
    }

	#game-over-container {
	    position: absolute;
	    top: 430px;
	    left: 50%;
	    width: 260px;
	    max-width: 260px;
	    transform: translate(-50%, -50%);
	    text-align: center;
	    z-index: 30;
	    display: none;
	    background: rgba(0, 0, 0, 0.8);
	    padding: 20px;
	    border-radius: 10px;
	}

    #game-over {
      font-size: 48px;
      font-family: Arial;
      color: #ffffff;
      text-shadow: 2px 2px 4px #000;
      margin: 0 0 20px 0;
      animation: gameOverPulse 1s ease-in-out infinite;
    }

    #try-again {
      font-size: 24px;
      font-family: Arial;
      font-weight: bold;
      color: #ffffff;
      background-color: #444;
      border: 2px solid #fff;
      padding: 10px 20px;
      margin: 10px 0;
      cursor: pointer;
      transition: background-color 0.3s ease, transform 0.2s ease;
      width: 250px;
      box-sizing: border-box;
      text-align: center;
    }

    #try-again:hover {
      background-color: #666;
      transform: scale(1.05);
    }
	
    #leaderboard {
      font-size: 24px;
      font-family: Arial;
      font-weight: bold;
      color: #ffffff;
      background-color: #444;
      border: 2px solid #fff;
      padding: 10px 20px;
      margin: 10px 0;
      cursor: pointer;
      transition: background-color 0.3s ease, transform 0.2s ease;
      width: 250px;
      box-sizing: border-box;
      text-align: center;
    }

    #leaderboard:hover {
      background-color: #666;
      transform: scale(1.05);
    }

    @keyframes matchAnimation {
      0% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.2); opacity: 0.8; }
      100% { transform: scale(0); opacity: 0; }
    }

    @keyframes gameOverPulse {
      0% { transform: scale(1); opacity: 0.8; }
      50% { transform: scale(1.1); opacity: 1; }
      100% { transform: scale(1); opacity: 0.8; }
    }

    .log {
      margin-top: 20px;
      text-align: left;
      background-color: #165777;
      padding: 10px;
      border-radius: 5px;
      max-height: 150px;
      min-height: 150px;
      overflow-y: auto;
      position: relative;
      top: -225px;
      border: 1px solid black;
    }
    
    .log h3 {
      margin: 0 0 10px;
    }

    #battle-log { list-style: none; padding: 0; }
    #battle-log li { margin: 5px 0; opacity: 0; animation: fadeIn 0.5s forwards; }
    @keyframes fadeIn { to { opacity: 1; } }

	button {
	    padding: 10px 20px;
	    background-color: #49BBE3;
	    border: none;
	    border-radius: 5px;
	    cursor: pointer;
	    font-weight: bold;
	    margin-bottom: 20px;
	    min-width: 150px;
	    font-size: 13px;
	}
	
	button:hover {
	    background-color: #54d4ff;
	}

	.legend {
	  margin-top: 20px;
	  text-align: left;
	  background-color: #165777;
	  padding: 10px;
	  border-radius: 5px;
	  position: relative;
	  top: -225px;
	  border: 1px solid black;
	}

	.legend h3 {
	  margin: 0 0 10px;
	  color: #fff;
	}

	.legend ul {
	  list-style: none;
	  padding: 0;
	  margin: 0;
	}

	.legend li {
	  display: flex; /* Main flex container for the list item */
	  align-items: flex-start; /* Align items at the top */
	  margin: 5px 0;
	  font-size: 0.9em;
	  line-height: 1.4; /* Improve readability with better line spacing */
	}

	.legend-tile {
	  width: 20px;
	  height: 20px;
	  flex-shrink: 0; /* Prevent the tile from shrinking */
	  margin-right: 10px; /* Space between tile and text */
	  display: inline-block;
	  border: 1px solid black;
	  padding: 3px;
	  margin-top: 3px;
	  box-shadow: 0px 2px 5px black;
	}

	/* Nested flex container for the text content */
	.legend li .text-content {
	  display: flex;
	  flex-direction: column; /* Stack the strong and span vertically */
	  flex: 1; /* Take up the remaining space */
	}

	/* Ensure the description text wraps naturally */
	.legend li .text-content span {
	  display: inline; /* Allow natural text wrapping */
	}

    .legend-tile.first-attack { background-color: #4CAF50; }
    .legend-tile.second-attack { background-color: #2196F3; }
    .legend-tile.special-attack { background-color: #FFC107; }
    .legend-tile.power-up { background-color: #9C27B0; }
    .legend-tile.last-stand { background-color: #F44336; }

    .glow-first-attack { box-shadow: 0 0 10px 5px #4CAF50; }
    .glow-second-attack { box-shadow: 0 0 10px 5px #2196F3; }
    .glow-special-attack { box-shadow: 0 0 10px 5px #FFC107; }
    .glow-last-stand { box-shadow: 0 0 10px 5px #F44336; }
    .glow-power-up { box-shadow: 0 0 10px 5px #9C27B0; }
    .glow-recoil { box-shadow: 0 0 10px 5px #FF0000; }
    .winner { animation: bounce 1.5s infinite, pulseGlow 1.5s infinite; }
    .loser { animation: pulseRedGlow 1.5s infinite; }
    @keyframes bounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }
    @keyframes pulseGlow {
      0% { box-shadow: 0 0 10px 5px #FFD700; }
      50% { box-shadow: 0 0 20px 10px #FFD700; }
      100% { box-shadow: 0 0 10px 5px #FFD700; }
    }
    @keyframes pulseRedGlow {
      0% { box-shadow: 0 0 10px 5px #FF0000; }
      50% { box-shadow: 0 0 20px 10px #FF0000; }
      100% { box-shadow: 0 0 10px 5px #FF0000; }
    }

	#character-select-container {
	  position: fixed;
	  top: 50%;
	  left: 50%;
	  transform: translate(-50%, -50%);
	  background: #002f44;
	  padding: 20px;
	  z-index: 100;
	  width: 100%;
	  height: 100%;
	  overflow-y: auto;
	  display: block; /* Initially visible */
	  border: 3px solid black;
	  text-align: center;
	}

    #character-select-container h2 {
      text-align: center;
      margin-bottom: 20px;
	  margin-top: 20px;
    }

    .character-option {
      display: inline-block;
      width: 200px;
      margin: 10px;
      padding: 10px;
      background: #165777;
      border-radius: 5px;
      cursor: pointer;
      transition: transform 0.2s ease, background 0.2s ease;
	  border: 1px solid black;
    }

    .character-option:hover {
      transform: scale(1.05);
      background: #2080ad;
    }

	.character-option img, .character-option video {
	    width: 100%;
	    height: auto;
	    border-radius: 5px;
	    -webkit-filter: drop-shadow(2px 5px 10px #000);
	    filter: drop-shadow(2px 5px 10px #000);
	}

    .character-option p {
      margin: 5px 0;
      font-size: 0.9em;
    }
	
	/* Use more specific class names to avoid conflicts */
	.progress-modal {
	  position: fixed;
	  top: 0;
	  left: 0;
	  width: 100%;
	  height: 100%;
	  background-color: rgba(0, 0, 0, 0.5);
	  display: flex;
	  justify-content: center;
	  align-items: center;
	  z-index: 1000;
	  margin: 0;
	  padding: 0;
	  box-sizing: border-box;
	}

	.progress-modal-content {
	  background-color: #1a1a1a;
	  padding: 20px;
	  border-radius: 10px;
	  text-align: center;
	  color: #fff;
	  font-family: Arial, sans-serif;
	  box-shadow: 0 0 10px rgba(0, 0, 0, 0.8);
	  max-width: 400px;
	  width: 90%;
	  margin: 0;
	  box-sizing: border-box;
	}

	.progress-modal-content p {
	  margin: 0 0 20px 0;
	  font-size: 18px;
	}

	.progress-modal-buttons {
	  display: flex;
	  justify-content: center;
	  gap: 10px;
	}

	.progress-modal-buttons button {
	  padding: 10px 20px;
	  font-size: 16px;
	  cursor: pointer;
	  border: none;
	  border-radius: 5px;
	  transition: background-color 0.3s;
	}

	#progress-resume {
	  background-color: #4CAF50;
	  color: white;
	}

	#progress-resume:hover {
	  background-color: #45a049;
	}

	#progress-start-fresh {
	  background-color: #f44336;
	  color: white;
	}

	#progress-start-fresh:hover {
	  background-color: #da190b;
	}
	
    #flip-p1, #flip-p2 {
		margin-top: 10px;
		margin-bottom: 0px;
		-webkit-filter: drop-shadow(2px 5px 10px #000);
		filter: drop-shadow(2px 5px 10px #000);
	}
	
	#theme-select-button, #select-boss-button {
	  padding: 10px 20px;
	  background-color: #49BBE3;
	  border: none;
	  border-radius: 5px;
	  cursor: pointer;
	  font-weight: bold;
	  font-size: 16px;
	  margin: 10px 0;
	  min-width: 150px;
	  color: black !important;
      -webkit-appearance: none; /* Remove Safari’s default button styling */
      -webkit-text-fill-color: black; /* Override Safari’s default text fill */
	}

	#theme-select-button:hover, #select-boss-button:hover {
	  background-color: #54d4ff;
	}

	#theme-select-container {
	  position: fixed;
	  top: 50%;
	  left: 50%;
	  transform: translate(-50%, -50%);
	  background: #002f44;
	  padding: 20px;
	  z-index: 101; /* Above character-select-container */
	  width: 100%;
	  height: 100%;
	  overflow-y: auto;
	  border: 3px solid black;
	  text-align: center;
	  display: none;
	}
	
	#theme-select-container h2{
		margin-top: 30px;
	}

	#theme-close-button {
	  position: absolute;
	  top: 20px;
	  right: 20px;
	  padding: 10px 20px;
	  background-color: #f44336;
	  border: none;
	  border-radius: 5px;
	  cursor: pointer;
	  font-weight: bold;
	  font-size: 16px;
	  color: #fff;
	}

	#theme-close-button:hover {
	  background-color: #da190b;
	}

	.theme-group {
	  margin: 20px 0;
	  display: flex;
	  flex-wrap: wrap;
	  justify-content: center; /* Center themes horizontally */
	  text-align: center;
	}

	.theme-group h3 {
	  color: #fff;
	  font-size: 1.5em;
	  margin: 10px 0;
	  width: 100%; /* Ensure title spans container */
	  background-color: #165777;
	  padding: 10px;
	  border-top: 1px solid black;
	  border-bottom: 1px solid black;
	  margin-bottom: 30px;
	}

	.theme-option {
	  display: inline-block;
	  width: 350px;
	  height: 275px;
	  margin: 10px;
	  padding: 10px;
	  background-color: #121212; /* Fallback color */
	  background-size: cover;
	  background-position: center;
	  border-radius: 5px;
	  cursor: pointer;
	  transition: transform 0.2s ease, background 0.2s ease;
	  border: 1px solid black;
	  position: relative;
	  overflow: hidden;
	  text-align: center;
	}

	.theme-option:hover {
	  transform: scale(1.05);
	  background-color: rgba(32, 128, 173, 0.2); /* Blend with fallback on hover */
	}

	.theme-option img {
	  max-width: 80%;
	  max-height: 120px;
	  margin: 20px auto;
	  display: block;
	  -webkit-filter: drop-shadow(2px 5px 10px #000);
	  filter: drop-shadow(2px 5px 10px #000);
	}

	.theme-option img:hover::after {
	  content: attr(data-project);
	  position: absolute;
	  background: rgba(0, 0, 0, 0.8);
	  color: #fff;
	  padding: 5px 10px;
	  border-radius: 5px;
	  font-size: 0.8em;
	  bottom: 140px; /* Above title bar */
	  left: 50%;
	  transform: translateX(-50%);
	  z-index: 10;
	}

	.theme-option p {
	  margin: 0;
	  font-size: 0.9em;
	  color: #fff;
	  background: rgba(0, 0, 0, 0.7);
	  padding: 10px;
	  position: absolute;
	  bottom: 0;
	  left: 0;
	  right: 0;
	  text-align: center;
	  box-sizing: border-box;
	}
	
	#boss-battles-button-container {
	  text-align: center;
	  margin: 10px 0 20px 0; /* Reduced top margin, increased bottom margin */
	}
	
	#boss-select-container {
	  position: fixed;
	  top: 50%;
	  left: 50%;
	  transform: translate(-50%, -50%);
	  background: #002f44;
	  padding: 20px;
	  z-index: 102; /* Above theme-select-container (101) */
	  width: 100%;
	  height: 100%;
	  overflow-y: auto;
	  border: 3px solid black;
	  text-align: center;
	  display: none;
	}

	#boss-select-container h2 {
	  text-align: center;
	  margin-bottom: 10px;
	  margin-top: 30px;
	}

	/* Reuse character-option for boss-option */
	.boss-option {
	  display: inline-block;
	  width: 200px;
	  margin: 10px;
	  padding: 10px;
	  background: #165777;
	  border-radius: 5px;
	  cursor: pointer;
	  transition: transform 0.2s ease, background 0.2s ease;
	  border: 1px solid black;
	}

	.boss-option:hover {
	  transform: scale(1.05);
	  background: #2080ad;
	}
	
	.boss-option div {
	  max-height: 200px;
	  border-radius: 5px;
	  overflow: hidden;
	  -webkit-filter: drop-shadow(2px 5px 10px #000);
	  filter: drop-shadow(2px 5px 10px #000);
	}

	.boss-option img {
	  width: 100%;
	  height: auto;
	  border-radius: 5px;
	  -webkit-filter: drop-shadow(2px 5px 10px #000);
	  filter: drop-shadow(2px 5px 10px #000);
	}

	.boss-option p {
	  margin: 5px 0;
	  font-size: 0.9em;
	}

	/* Style for non-fightable bosses */
	.boss-option.disabled {
	  opacity: 0.5;
	  cursor: not-allowed;
	}

	.boss-option.disabled:hover {
	  transform: none;
	  background: #165777;
	}
	
	.boss-option table {
	  width: 100%;
	  margin: 5px 0;
	  border-collapse: collapse;
	  font-size: 0.75em;
	  color: #fff;
	}

	.boss-option table td {
	  padding: 2px 5px;
	  text-align: left;
	}

	.boss-option table td:first-child {
	  font-weight: bold;
	  width: 50%;
	}

	.boss-option table td:last-child {
	  text-align: right;
	}

	@media (max-width: 1025px) {
	  .theme-option {
	    width: 250px;
	    height: 200px;
	  }

	  .theme-option img {
	    max-height: 80px;
	    margin: 10px auto;
	  }

	  .theme-option p {
	    font-size: 0.8em;
	    padding: 8px;
	  }
	  #boss-battles-button-container {
	    margin: 5px 0 15px 0; /* Adjusted for mobile */
	  }
	  .theme-select-button {
	    font-size: 14px;
	    padding: 8px 16px;
	    min-width: 120px;
	  }
	  #boss-select-container {
	    width: 90%;
	    padding: 10px;
	  }
	  .boss-option {
	    width: 140px;
	    margin: 5px;
	  }
	  .boss-option p {
	    font-size: 0.8em;
	  }
	  .boss-option table {
	    font-size: 0.75em;
	  }
	}

    @media (max-width: 1025px) {
      .game-container {
        width: 100%;
        min-width: 320px;
        max-width: 100%;
        padding: 10px;
        max-height: none;
        margin-top: 0px;
      }
      
      .character {
        min-height: 100px;
        text-align: right;
        width: 100%;
        max-width: 330px;
        padding: 5px;
        padding-left: 10px;
        padding-right: 10px;
      }
      
      .character h2 {
        text-align: center;
        display: none;
      }
      
      .character img, .character video {
		  width: 85px;
		  height: auto;
          float: left;
          position: absolute;
          left: 15px;
          top: 55px;
		  min-height: 40px;
      }
      
      .character table{
        width: 70%;
        float: right;
		font-size: 13px;
		width: 65%;
      }
      
      .character td {
        padding-top: 0px;
      }
      
      .game-logo {
        opacity: 0;
      }
      
      #restart, #change-character {
        opacity: 0;
      }

      .battlefield {
        flex-direction: column;
        align-items: center;
        gap: 0px;
      }
      
      .turn-indicator {
        opacity: 0;
      }

      #game-board {
        width: 250px;
        height: 250px;
        position: relative;
        top: -225px;
        margin-top: 0px;
        background: none;
        border: none;
      }

	  .legend {
	    font-size: 0.8em;
	  }

	  .legend-tile {
	    width: 15px;
	    height: 15px;
	  }

	  .legend li {
	    line-height: 1.3; /* Adjust line spacing for smaller screens */
	  }
      
      #game-over-container {
        top: 317px;
      }

      #character-select-container {
        width: 90%;
        padding: 10px;
      }

      .character-option {
        width: 140px;
        margin: 5px;
      }
	  
	  #flip-p1, #flip-p2 {
		  display: none;
	  }
    }
  </style>
  <script type="text/javascript">
    // Pass login status to JavaScript
    window.isLoggedIn = <?php echo json_encode(isset($_SESSION['userData']['user_id']) && !empty($_SESSION['userData']['user_id'])); ?>;
	window.userId = <?php echo json_encode($user_id); ?>;
  </script>
</head>
<body>
  <div class="game-container">
    <div id="game-over-container">
      <div id="game-over"></div>
      <div id="game-over-buttons">
        <button id="try-again"></button>
		<div id="leaderboard-button"></div> <!-- Placeholder for leaderboard form -->
      </div>
    </div>
    <img src="https://www.skulliance.io/staking/images/monstrocity/logo.png" alt="Monstrocity Logo" class="game-logo">
    <button id="restart" class="game-button">Restart Level</button>
    <button id="refresh-board" class="game-button" style="display: none;">Refresh Board</button>
    <button id="change-character" class="game-button" style="display: none;">Switch Character</button>
    <div class="turn-indicator" id="turn-indicator">Player 1's Turn</div>

    <div class="battlefield">
	  <div class="character" id="player1">
	      <h2><span id="p1-name"></span></h2>
	      <img id="p1-image" src="" alt="Player 1 Image">
	      <div class="health-bar"><div class="health" id="p1-health"></div></div>
  	      <button id="flip-p1">Flip Player 1 Image</button>
	      <table>
	          <tr><td class="attribute-label">Health:</td><td class="attribute"><span id="p1-hp"></span></td></tr>
	          <tr><td class="attribute-label">Strength:</td><td class="attribute"><span id="p1-strength"></span></td></tr>
	          <tr><td class="attribute-label">Speed:</td><td class="attribute"><span id="p1-speed"></span></td></tr>
	          <tr><td class="attribute-label">Tactics:</td><td class="attribute"><span id="p1-tactics"></span></td></tr>
	          <tr><td class="attribute-label">Size:</td><td class="attribute"><span id="p1-size"></span></td></tr>
	          <tr><td class="attribute-label">Power-Up:</td><td class="attribute"><span id="p1-powerup"></span></td></tr>
	          <tr><td class="attribute-label">Type:</td><td class="attribute"><span id="p1-type"></span></td></tr>
	      </table>
	  </div>
      <div id="game-board"></div>
	  <div class="character" id="player2">
	      <h2><span id="p2-name"></span></h2>
	      <img id="p2-image" src="" alt="Player 2 Image">
	      <div class="health-bar"><div class="health" id="p2-health"></div></div>
  	      <button id="flip-p2">Flip Opponent Image</button>
	      <table>
	          <tr><td class="attribute-label">Health:</td><td class="attribute"><span id="p2-hp"></span></td></tr>
	          <tr><td class="attribute-label">Strength:</td><td class="attribute"><span id="p2-strength"></span></td></tr>
	          <tr><td class="attribute-label">Speed:</td><td class="attribute"><span id="p2-speed"></span></td></tr>
	          <tr><td class="attribute-label">Tactics:</td><td class="attribute"><span id="p2-tactics"></span></td></tr>
	          <tr><td class="attribute-label">Size:</td><td class="attribute"><span id="p2-size"></span></td></tr>
	          <tr><td class="attribute-label">Power-Up:</td><td class="attribute"><span id="p2-powerup"></span></td></tr>
	          <tr><td class="attribute-label">Type:</td><td class="attribute"><span id="p2-type"></span></td></tr>
	      </table>
	  </div>
    </div>
    <div class="log">
      <h3>Battle Log</h3>
      <ul id="battle-log"></ul>
    </div>
	<div class="legend">
	  <h3>Legend</h3>
	  <ul>
	    <li>
	      <span class="legend-tile first-attack"><img src="https://www.skulliance.io/staking/icons/first-attack.png" alt="First Attack"></span>
	      <div class="text-content">
	        <strong>First Attack (Slash): </strong>
	        <span>Deals damage (Strength × 2/3/4 for 3/4/5 tiles)</span>
	      </div>
	    </li>
	    <li>
	      <span class="legend-tile second-attack"><img src="https://www.skulliance.io/staking/icons/second-attack.png" alt="Second Attack"></span>
	      <div class="text-content">
	        <strong>Second Attack (Bite): </strong>
	        <span>Deals damage (Strength × 2/3/4 for 3/4/5 tiles)</span>
	      </div>
	    </li>
	    <li>
	      <span class="legend-tile special-attack"><img src="https://www.skulliance.io/staking/icons/special-attack.png" alt="Special Attack"></span>
	      <div class="text-content">
	        <strong>Special Attack (Shadow Strike): </strong>
	        <span>Deals 1.2× damage (Strength × 2/3/4 for 3/4/5 tiles)</span>
	      </div>
	    </li>
	    <li>
	      <span class="legend-tile power-up"><img src="https://www.skulliance.io/staking/icons/power-up.png" alt="Power Up"></span>
	      <div class="text-content">
	        <strong>Power-Up: </strong>
	        <span>Activates a random powerup (see below)</span>
	      </div>
	    </li>
	    <li>
	      <span class="legend-tile last-stand"><img src="https://www.skulliance.io/staking/icons/last-stand.png" alt="Last Stand"></span>
	      <div class="text-content">
	        <strong>Last Stand: </strong>
	        <span>Deals damage and mitigates 5 damage on the next attack received</span>
	      </div>
	    </li>
	  </ul>
	  <br>
	  <h3>Power-Up Effects</h3>
	  <ul>
	    <li>
	      <div class="text-content">
	        <strong>Heal (Bloody): </strong>
	        <span>Restores 10 HP (reduced by enemy tactics)</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Boost Attack (Cardano): </strong>
	        <span>Adds +10 damage to the next attack (reduced by enemy tactics)</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Regenerate (ADA): </strong>
	        <span>Restores 7 HP (reduced by enemy tactics)</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Minor Regen (None): </strong>
	        <span>Restores 5 HP (reduced by enemy tactics)</span>
	      </div>
	    </li>
	  </ul>
	  <span>Note: Power-up effects are boosted by 50% for a match-4 and 100% for a match-5+.</span>
	  <br>
	  <br>
	  <h3>Combo Bonuses</h3>
	  <ul>
	    <li>
	      <div class="text-content">
	        <strong>Match-4 Bonus: </strong>
	        <span>50% bonus to damage and score for a single match of 4 tiles</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Match-5+ Bonus: </strong>
	        <span>100% bonus to damage and score for a single match of 5 or more tiles</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Multi-Match (6–8 tiles): </strong>
	        <span>20% bonus to score for matching 6–8 tiles across multiple matches in a single move (does not apply to cascades)</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Mega Multi-Match (9+ tiles): </strong>
	        <span>200% bonus to score for matching 9 or more tiles across multiple matches in a single move (does not apply to cascades)</span>
	      </div>
	    </li>
	  </ul>
	  <br>
	  <h3>Character Traits</h3>
	  <ul>
	    <li>
	      <div class="text-content">
	        <strong>Strength: </strong>
	        <span>Determines base damage for attacks (Strength × 2/3/4 for 3/4/5+ tiles)</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Speed: </strong>
	        <span>Determines turn order at the start of the level (higher Speed goes first; ties broken by Strength)</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Tactics: </strong>
	        <span>Gives a (Tactics × 10)% chance to halve incoming damage and reduces enemy power-up effects by (Tactics × 5)%</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Size: </strong>
	        <span>Large: +20% health, -2 Tactics (if Tactics > 1); Medium: No effect; Small: -20% health, +2 Tactics (max 7)</span>
	      </div>
	    </li>
	    <li>
	      <div class="text-content">
	        <strong>Type: </strong>
	        <span>Base: 85 health; Leader: 100 health; Battle Damaged: 70 health</span>
	      </div>
	    </li>
	  </ul>
	</div>

  </div>
    <div id="character-select-container">
	  <h2>Staking Info</h2>
  	  <!--<p><a href="https://www.jpg.store/collection/monstrocity" target="_blank">Purchase Monstrocity NFTs</a> to Add More Characters</p>-->
  	  <p><a href="https://www.skulliance.io/staking" target="_blank">Visit Skulliance Staking</a> to Connect Wallet(s) and Load in Qualifying NFTs</p>
  	  <p>Leaderboards, Game Saves, and Rewards are Available to Skulliance Stakers</p>
      <button id="theme-select-button">Select Theme</button>
	  <button id="select-boss-button" style="display: none;">Select Boss</button>
	  <h2>Select Character</h2>
      <div id="character-options"></div>
    </div>
	<!-- New Theme Select Modal -->
	<!-- Theme Select Template (initially empty, built by JS) -->
	<div id="theme-select-container" style="display: none;"></div>
    <div id="boss-select-container" style="display: none;"></div>
  <script>
	  let updatePending = false;
	  // Theme data extracted from original <select>
	  const themes = [
	    {
	      group: "Partner Project Themes",
	      items: [
	        {
	          value: "monstrocity",
	          project: "Monstrocity",
	          title: "Default Game",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "apprentices",
	          project: "Apprentices",
	          title: "Apprentices",
	          policyIds: "93ff51e7dfdf32314fd2f99ff222aa9c92f486b7d2cc0d46b64a9785",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "discosolaris",
	          project: "Disco Solaris",
	          title: "Moebius Pioneers",
	          policyIds: "9874142fc1a8687d0fa4c34140b4c8678e820c91c185cc3c099acb99",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "oculuslounge",
	          project: "Disco Solaris",
	          title: "Oculus Lounge",
	          policyIds: "d0112837f8f856b2ca14f69b375bc394e73d146fdadcc993bb993779",
	          orientations: "Left",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "havocworlds",
	          project: "Havoc Worlds",
	          title: "Season 1",
	          policyIds: "1088b361c41f49906645cedeeb7a9ef0e0b793b1a2d24f623ea74876",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "muses",
	          project: "Josh Howard",
	          title: "Muses of the Multiverse",
	          policyIds: "7f95b5948e3efed1171523757b472f24aecfab8303612cfa1b6fec55",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "fauna",
	          project: "Nemonium",
	          title: "Fauna x Nemonium",
	          policyIds: "7cd357f96d7a7325ff3038e78e004840706887790d8b513913b45c27",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "sh4pes",
	          project: "Nemonium",
	          title: "Sh4pes x Nemonium",
	          policyIds: "2d868badf3dc317234fe253859621fedf661bf9eba275faea80a8bfe",
	          orientations: "Left",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        }
	      ]
	    },
	    {
	      group: "Partner Artist Themes",
	      items: [
	        {
	          value: "bungking",
	          project: "Bungking",
	          title: "Yume",
	          policyIds: "f5a4009f12b9ee53b15edf338d1b7001641630be8308409b1477753b",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "cardanocamera",
	          project: "Cardano Camera",
	          title: "Galaxy of Sons",
	          policyIds: "647535c1befd741bfa1ace4a5508e93fe03ff7590c26d372c8a812cb",
	          orientations: "Left",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "crypties2",
	          project: "Crypties",
	          title: "Crypties S2",
	          policyIds: "e77fe5101469bdbd2d596f69abbd8ea6311008f5687dec3d950bb17a",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "darkula",
	          project: "Darkula",
	          title: "Island of the Uncanny Neighbors",
	          policyIds: "b0b93618e3f594ae0b56e4636bbd7e47d537f0642203d80e88a631e0",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "darkula2",
	          project: "Darkula",
	          title: "Island of the Violent Neighbors",
	          policyIds: "b0b93618e3f594ae0b56e4636bbd7e47d537f0642203d80e88a631e0",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "galactico",
	          project: "Galactico",
	          title: "Galactico",
	          policyIds: "bedb9383c1ad91247ec2a81fc3841934bbb298530eb99a55e77a0fb8,89c01dc68d57169d9c02c3f35562d219884bbafa9d9f868c2f39ce67",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "gif" // Applies only to character images
	        },
	        {
	          value: "happypeople",
	          project: "Netanel Cohen",
	          title: "Happy People",
	          policyIds: "ac9067e22a857ee2e4ea20f77c6c047f78a11511f285d30d81857196",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "maxi",
	          project: "Maxingo",
	          title: "Digital Hell Citizens 2: Fighters",
	          policyIds: "b31a34ca2b08bfc905d2b630c9317d148554303fa7f0d605fd651cb5",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "shortyverse",
	          project: "Ohh Meed",
	          title: "Shorty Verse",
	          policyIds: "0d7c69f8e7d1e80f4380446a74737eebb6e89c56440f3f167e4e231c",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "shortyverse2",
	          project: "Ohh Meed",
	          title: "Shorty Verse Engaged",
	          policyIds: "0d7c69f8e7d1e80f4380446a74737eebb6e89c56440f3f167e4e231c",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "dropship",
	          project: "Ohh Meed",
	          title: "Drop Ship",
	          policyIds: "4478c708183e95340d0582419a2d6bc93d57657895c19802546d396c",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "beelzebub",
	          project: "Ritual",
	          title: "Beelzebub",
	          policyIds: "cd3a338f248a8ccb484032327b499e90b0303c499beea47e37039bf2",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "bogeyman",
	          project: "Ritual",
	          title: "Bogeyman",
	          policyIds: "bca7c472792b859fb18920477f917c94b76c9c9705e039bf08af0b63",
	          orientations: "Random",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "ritual",
	          project: "Ritual",
	          title: "John Doe",
	          policyIds: "16b10d60f428b03fa5bafa631c848b2243f31cbf93cce1a65779e5f5",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "skada",
	          project: "Ritual",
	          title: "Skada",
	          policyIds: "2eacad9ddcb9edd7721af49f682bd356e8e28194bafa4bbc2f559bb7",
	          orientations: "Left",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "sinderskullz",
	          project: "Sinder Skullz",
	          title: "Sinder Skullz",
	          policyIds: "83732ff37818e7e520592fcd3e5257e429307d40a9f5437240e926de",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "skowl",
	          project: "Skowl",
	          title: "Derivative Heroes",
	          policyIds: "d38910b4b5bd3e634138dc027b507b52406acf687889e3719aa4f7cf",
	          orientations: "Left",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "ug",
	          project: "Squashua",
	          title: "Ug Vs Donuts",
	          policyIds: "8972aab912aed2cf44b65916e206324c6bdcb6fbd3dc4eb634fdbd28",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "wave",
	          project: "Squashua",
	          title: "Wavy Ape Vibe Empire",
	          policyIds: "d458857ae7d121bfb5af64d78c0454a519133248e030a0225734452b",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        }
	      ]
	    },
	    {
	      group: "Bonus Themes",
	      items: [
	        {
	          value: "adapunks",
	          project: "ADA Punks",
	          title: "ADA Punks",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "cardanians",
	          project: "Cardanians",
	          title: "Cardanian Snow Globes (GIF)",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "gif" // Applies only to character images
	        },
	        {
	          value: "cardanians2",
	          project: "Cardanians",
	          title: "Cardanian Snow Globes (MOV)",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "mov" // Applies only to character images
	        },
	        {
	          value: "handies",
	          project: "Handies",
	          title: "Handies",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "animeorigins",
	          project: "Nel",
	          title: "Anime Origins",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "png" // Applies only to character images
	        }
	      ]
	    }
	    <?php if($innercircle) { ?>
	    ,
	    {
	      group: "Inner Circle Top Secret Themes",
	      items: [
	        {
	          value: "occultarchives",
	          project: "Billy Martin",
	          title: "Occult Archives",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "rubberrebels",
	          project: "Classic Cardtoons",
	          title: "Rubber Rebels",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "danketsu",
	          project: "Danketsu",
	          title: "Legends",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "danketsu2",
	          project: "Danketsu",
	          title: "The Fourth",
	          policyIds: "a4b7f3bbb16b028739efc983967f1e631883f63a2671d508023b5dfb",
	          orientations: "Left",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "deadpophell",
	          project: "Dead Pop Hell",
	          title: "NSFW",
	          policyIds: "6710d32c862a616ba81ef00294e60fe56969949e0225452c48b5f0ed",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "moebiuspioneers",
	          project: "Disco Solaris",
	          title: "Legends",
	          policyIds: "9874142fc1a8687d0fa4c34140b4c8678e820c91c185cc3c099acb99",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
			 <?php if($dev) { ?>
	        {
	          value: "j2",
	          project: "J2",
	          title: "J2",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
			<?php } ?>
	        {
	          value: "jetchicken",
	          project: "Jet Chicken",
	          title: "Jet Chicken",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "karranka",
	          project: "Karranka",
	          title: "Badass Heroes",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "karranka2",
	          project: "Karranka",
	          title: "Japanese Ghosts: Legendary Warriors",
	          policyIds: "",
	          orientations: "",
	          ipfsPrefixes: "",
			  background: true,
			  extension: "png" // Applies only to character images
	        },
	        {
	          value: "omen",
	          project: "Nemonium",
	          title: "Omen Legends",
	          policyIds: "da286f15e0de865e3d50fec6fa0484d7e2309671dc4ba8ce6bdd122b",
	          orientations: "Right",
	          ipfsPrefixes: "https://ipfs5.jpgstoreapis.com/ipfs/",
			  background: true,
			  extension: "png" // Applies only to character images
	        }
	      ]
	    }
	    <?php } ?>
	  ];
	  
const _0x12575b=_0x372b;function _0x372b(_0x466071,_0x2dca96){const _0x424a8c=_0x424a();return _0x372b=function(_0x372bc1,_0x36fd52){_0x372bc1=_0x372bc1-0x1bd;let _0x4554a4=_0x424a8c[_0x372bc1];return _0x4554a4;},_0x372b(_0x466071,_0x2dca96);}(function(_0x36ff6d,_0x1d0eda){const _0x1e0ca9=_0x372b,_0x483e61=_0x36ff6d();while(!![]){try{const _0x18378a=parseInt(_0x1e0ca9(0x452))/0x1+parseInt(_0x1e0ca9(0x418))/0x2+-parseInt(_0x1e0ca9(0x2ba))/0x3*(parseInt(_0x1e0ca9(0x276))/0x4)+parseInt(_0x1e0ca9(0x4a5))/0x5+parseInt(_0x1e0ca9(0x3b3))/0x6+-parseInt(_0x1e0ca9(0x361))/0x7*(-parseInt(_0x1e0ca9(0x474))/0x8)+-parseInt(_0x1e0ca9(0x1c8))/0x9*(parseInt(_0x1e0ca9(0x3ea))/0xa);if(_0x18378a===_0x1d0eda)break;else _0x483e61['push'](_0x483e61['shift']());}catch(_0x445f6a){_0x483e61['push'](_0x483e61['shift']());}}}(_0x424a,0x1aed8));function showThemeSelect(_0x3d6e47){const _0x193833=_0x372b;console[_0x193833(0x2b6)](_0x193833(0x395));let _0x2c7b1e=document[_0x193833(0x43c)]('theme-select-container');const _0x1c2621=document[_0x193833(0x43c)](_0x193833(0x3a2));_0x3d6e47[_0x193833(0x2e9)]=null,_0x3d6e47[_0x193833(0x292)]=null,_0x3d6e47[_0x193833(0x341)](),console[_0x193833(0x253)](_0x193833(0x387)),_0x2c7b1e[_0x193833(0x20b)]=_0x193833(0x1d8)+(window[_0x193833(0x306)]?_0x193833(0x3df):_0x193833(0x396))+_0x193833(0x1e1);const _0xf989df=document[_0x193833(0x43c)](_0x193833(0x330));_0x2c7b1e[_0x193833(0x3ff)][_0x193833(0x2c6)]=_0x193833(0x3df),_0x1c2621[_0x193833(0x3ff)][_0x193833(0x2c6)]=_0x193833(0x396),_0x3d6e47[_0x193833(0x273)](![]),themes[_0x193833(0x439)](_0x224bb9=>{const _0x7a2b67=_0x193833,_0x4fb367=document['createElement'](_0x7a2b67(0x3d0));_0x4fb367[_0x7a2b67(0x43b)]=_0x7a2b67(0x479);const _0x3f1f6c=document[_0x7a2b67(0x3a8)]('h3');_0x3f1f6c[_0x7a2b67(0x1c2)]=_0x224bb9['group'],_0x4fb367[_0x7a2b67(0x4b7)](_0x3f1f6c),_0x224bb9[_0x7a2b67(0x296)][_0x7a2b67(0x439)](_0x555fce=>{const _0x151eaf=_0x7a2b67,_0x4d9232=document[_0x151eaf(0x3a8)](_0x151eaf(0x3d0));_0x4d9232[_0x151eaf(0x43b)]='theme-option';if(_0x555fce[_0x151eaf(0x272)]){const _0x4b4d48='https://www.skulliance.io/staking/images/monstrocity/'+_0x555fce[_0x151eaf(0x27d)]+_0x151eaf(0x2e6);_0x4d9232[_0x151eaf(0x3ff)][_0x151eaf(0x340)]=_0x151eaf(0x34b)+_0x4b4d48+')';}const _0x44cee3=_0x151eaf(0x369)+_0x555fce['value']+_0x151eaf(0x42c);_0x4d9232[_0x151eaf(0x20b)]=_0x151eaf(0x307)+_0x44cee3+_0x151eaf(0x4bd)+_0x555fce['title']+_0x151eaf(0x45a)+_0x555fce[_0x151eaf(0x460)]+_0x151eaf(0x379)+_0x555fce[_0x151eaf(0x309)]+_0x151eaf(0x20d),_0x4d9232[_0x151eaf(0x27c)]('click',()=>{const _0x82b889=_0x151eaf,_0x42bbf3=document[_0x82b889(0x43c)](_0x82b889(0x26a));_0x42bbf3&&(_0x42bbf3[_0x82b889(0x20b)]=_0x82b889(0x28a)),_0x2c7b1e[_0x82b889(0x20b)]='',_0x2c7b1e[_0x82b889(0x3ff)][_0x82b889(0x2c6)]=_0x82b889(0x396),_0x1c2621[_0x82b889(0x3ff)][_0x82b889(0x2c6)]=_0x82b889(0x3df),_0x3d6e47[_0x82b889(0x399)](_0x555fce[_0x82b889(0x27d)]);}),_0x4fb367[_0x151eaf(0x4b7)](_0x4d9232);}),_0xf989df[_0x7a2b67(0x4b7)](_0x4fb367);});const _0x44d137=document[_0x193833(0x43c)](_0x193833(0x2e2));_0x44d137&&_0x44d137[_0x193833(0x27c)]('click',()=>{const _0x13c2f9=_0x193833;console[_0x13c2f9(0x253)](_0x13c2f9(0x3bb)),showBossSelect(_0x3d6e47);}),console[_0x193833(0x1d4)](_0x193833(0x395));}function showBossSelect(_0x3babcf){const _0xd72aee=_0x372b;console[_0xd72aee(0x2b6)](_0xd72aee(0x45e));const _0x387d50=document[_0xd72aee(0x43c)](_0xd72aee(0x2f4)),_0x482bdd=document[_0xd72aee(0x43c)](_0xd72aee(0x226)),_0x536fd9=document['getElementById'](_0xd72aee(0x3a2));console[_0xd72aee(0x253)]('showBossSelect:\x20Entering\x20boss\x20mode'),_0x387d50[_0xd72aee(0x20b)]=_0xd72aee(0x441);const _0x41b057=document[_0xd72aee(0x43c)](_0xd72aee(0x1fb));_0x387d50['style']['display']=_0xd72aee(0x3df),_0x482bdd['style'][_0xd72aee(0x2c6)]=_0xd72aee(0x396),_0x536fd9[_0xd72aee(0x3ff)][_0xd72aee(0x2c6)]='none';const _0x42e4f5=document[_0xd72aee(0x43c)](_0xd72aee(0x393));_0x42e4f5[_0xd72aee(0x27c)]('click',()=>{const _0x3b40a4=_0xd72aee;_0x3babcf[_0x3b40a4(0x2e9)]=null,_0x3babcf[_0x3b40a4(0x292)]=null,_0x3babcf[_0x3b40a4(0x341)](),console[_0x3b40a4(0x253)](_0x3b40a4(0x1d6)),_0x387d50[_0x3b40a4(0x3ff)][_0x3b40a4(0x2c6)]=_0x3b40a4(0x396),_0x482bdd[_0x3b40a4(0x3ff)]['display']='block',showThemeSelect(_0x3babcf);}),fetch(_0xd72aee(0x3f8),{'method':'GET','headers':{'Content-Type':_0xd72aee(0x36c)}})['then'](_0x467fa2=>{const _0x41845d=_0xd72aee;if(!_0x467fa2['ok'])throw new Error(_0x41845d(0x48f)+_0x467fa2[_0x41845d(0x2ac)]);return _0x467fa2['json']();})['then'](_0x1fafbf=>{const _0xcd6dab=_0xd72aee;if(!Array['isArray'](_0x1fafbf)||_0x1fafbf['length']===0x0){_0x41b057[_0xcd6dab(0x20b)]=_0xcd6dab(0x26c),console[_0xcd6dab(0x21f)](_0xcd6dab(0x2b0));return;}const _0x15962d=document[_0xcd6dab(0x492)]();_0x1fafbf[_0xcd6dab(0x439)](_0x46b8c3=>{const _0xf7cd62=_0xcd6dab;console[_0xf7cd62(0x253)](_0xf7cd62(0x1be)+_0x46b8c3[_0xf7cd62(0x266)]+':\x20playerHealth='+_0x46b8c3['playerHealth']+_0xf7cd62(0x1db)+typeof _0x46b8c3[_0xf7cd62(0x47c)]+')');const _0x3fbacd=document[_0xf7cd62(0x3a8)]('div');_0x3fbacd['className']='boss-option';const _0x4ee2ac=_0x46b8c3[_0xf7cd62(0x47c)]===0x0,_0x27ea50=_0x46b8c3[_0xf7cd62(0x39e)]<=0x0;if(_0x4ee2ac||_0x27ea50){_0x3fbacd[_0xf7cd62(0x3ff)]['pointerEvents']=_0xf7cd62(0x396);if(_0x27ea50)_0x3fbacd[_0xf7cd62(0x3ff)][_0xf7cd62(0x3cd)]=_0xf7cd62(0x206),_0x3fbacd[_0xf7cd62(0x3ff)]['opacity']=_0xf7cd62(0x3cf);else _0x4ee2ac&&(_0x3fbacd['style']['filter']='grayscale(100%)',_0x3fbacd['style']['opacity']=_0xf7cd62(0x3cf));}const _0x2baee1=_0x46b8c3[_0xf7cd62(0x431)][_0xf7cd62(0x2a9)]('/')?_0x46b8c3['imageUrl'][_0xf7cd62(0x303)](0x1):_0x46b8c3[_0xf7cd62(0x431)],_0x4ffb16=(_0x46b8c3[_0xf7cd62(0x39e)]||0x0)/(_0x46b8c3[_0xf7cd62(0x1da)]||0x64)*0x64;let _0x1c8433;if(_0x4ffb16>0x4b)_0x1c8433='#4CAF50';else{if(_0x4ffb16>0x32)_0x1c8433=_0xf7cd62(0x2a1);else _0x4ffb16>0x19?_0x1c8433=_0xf7cd62(0x33b):_0x1c8433=_0xf7cd62(0x203);}_0x3fbacd['innerHTML']=_0xf7cd62(0x2f7)+_0x46b8c3[_0xf7cd62(0x266)]+_0xf7cd62(0x2cb)+_0x2baee1+_0xf7cd62(0x4bd)+_0x46b8c3['name']+_0xf7cd62(0x46c)+_0x4ffb16+_0xf7cd62(0x1e9)+_0x1c8433+_0xf7cd62(0x1e3)+_0x46b8c3[_0xf7cd62(0x39e)]+'/'+_0x46b8c3['maxHealth']+_0xf7cd62(0x426)+_0x46b8c3[_0xf7cd62(0x3a4)]+_0xf7cd62(0x3e4)+_0x46b8c3['speed']+_0xf7cd62(0x27a)+_0x46b8c3[_0xf7cd62(0x232)]+_0xf7cd62(0x35f)+_0x46b8c3[_0xf7cd62(0x3ee)]+_0xf7cd62(0x3ab)+_0x46b8c3[_0xf7cd62(0x3ef)]+'</td></tr>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<tr><td>Players:</td><td>'+_0x46b8c3[_0xf7cd62(0x41b)]+'</td></tr>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<tr><td>Bounty:</td><td>'+_0x46b8c3[_0xf7cd62(0x2e5)]+'\x20'+_0x46b8c3['currency']+_0xf7cd62(0x231),!_0x4ee2ac&&!_0x27ea50&&_0x3fbacd[_0xf7cd62(0x27c)]('click',()=>{const _0x40f1f6=_0xf7cd62;console[_0x40f1f6(0x253)]('Boss\x20selected:\x20'+_0x46b8c3[_0x40f1f6(0x266)]+_0x40f1f6(0x308)+_0x46b8c3['id']+')'),console[_0x40f1f6(0x253)](_0x40f1f6(0x1d5)+_0x46b8c3[_0x40f1f6(0x20f)]),_0x387d50[_0x40f1f6(0x3ff)]['display']=_0x40f1f6(0x396),_0x3babcf[_0x40f1f6(0x46d)]=[],console[_0x40f1f6(0x253)](_0x40f1f6(0x1d9)),fetch('ajax/get-nft-assets.php',{'method':'POST','headers':{'Content-Type':'application/json'},'body':JSON['stringify']({'policyIds':[_0x46b8c3['policy']],'theme':_0x3babcf[_0x40f1f6(0x404)]})})['then'](_0x1b42cd=>{const _0x7f70c6=_0x40f1f6;if(!_0x1b42cd['ok'])throw new Error(_0x7f70c6(0x48f)+_0x1b42cd[_0x7f70c6(0x2ac)]);return _0x1b42cd[_0x7f70c6(0x32b)]();})['then'](_0x16ee09=>{const _0xa5a2d2=_0x40f1f6;console[_0xa5a2d2(0x253)]('NFT\x20characters\x20response:',_0x16ee09);if(_0x16ee09===![]||!Array[_0xa5a2d2(0x208)](_0x16ee09)||_0x16ee09[_0xa5a2d2(0x3eb)]===0x0){console[_0xa5a2d2(0x21f)](_0xa5a2d2(0x2a5)),alert(_0xa5a2d2(0x23f)),_0x387d50['style'][_0xa5a2d2(0x2c6)]=_0xa5a2d2(0x3df),_0x536fd9[_0xa5a2d2(0x3ff)][_0xa5a2d2(0x2c6)]=_0xa5a2d2(0x396);return;}_0x3babcf['playerCharacters']=_0x16ee09[_0xa5a2d2(0x377)](_0x535d57=>_0x3babcf[_0xa5a2d2(0x26b)](_0x535d57)),console['log'](_0xa5a2d2(0x390)+_0x3babcf[_0xa5a2d2(0x46d)][_0xa5a2d2(0x3eb)]+_0xa5a2d2(0x360)),_0x3babcf[_0xa5a2d2(0x263)](_0x46b8c3),_0x536fd9[_0xa5a2d2(0x3ff)][_0xa5a2d2(0x2c6)]=_0xa5a2d2(0x3df),_0x3babcf[_0xa5a2d2(0x2ae)](!![]);})[_0x40f1f6(0x2b4)](_0x3405c5=>{const _0x58d89c=_0x40f1f6;console[_0x58d89c(0x49f)](_0x58d89c(0x3ed),_0x3405c5),alert('Error\x20loading\x20NFT\x20characters.\x20Please\x20try\x20again.'),_0x387d50[_0x58d89c(0x3ff)][_0x58d89c(0x2c6)]=_0x58d89c(0x3df),_0x536fd9[_0x58d89c(0x3ff)][_0x58d89c(0x2c6)]=_0x58d89c(0x396);});}),_0x15962d['appendChild'](_0x3fbacd);}),_0x41b057[_0xcd6dab(0x4b7)](_0x15962d),console[_0xcd6dab(0x253)](_0xcd6dab(0x322)+_0x1fafbf['length']+'\x20bosses');})[_0xd72aee(0x2b4)](_0x3e1cae=>{const _0x2aebac=_0xd72aee;console[_0x2aebac(0x49f)](_0x2aebac(0x45c),_0x3e1cae),_0x41b057['innerHTML']=_0x2aebac(0x44f);}),console[_0xd72aee(0x1d4)](_0xd72aee(0x45e));}const opponentsConfig=[{'name':'Craig','strength':0x1,'speed':0x1,'tactics':0x1,'size':'Medium','type':'Base','powerup':_0x12575b(0x35a),'theme':_0x12575b(0x38d)},{'name':_0x12575b(0x45d),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x12575b(0x2d7),'type':_0x12575b(0x268),'powerup':_0x12575b(0x35a),'theme':_0x12575b(0x38d)},{'name':_0x12575b(0x44c),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x12575b(0x205),'type':_0x12575b(0x268),'powerup':_0x12575b(0x35a),'theme':_0x12575b(0x38d)},{'name':_0x12575b(0x343),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x12575b(0x41d),'type':'Base','powerup':_0x12575b(0x35a),'theme':_0x12575b(0x38d)},{'name':'Mandiblus','strength':0x3,'speed':0x3,'tactics':0x3,'size':'Medium','type':_0x12575b(0x268),'powerup':_0x12575b(0x446),'theme':'monstrocity'},{'name':_0x12575b(0x221),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x12575b(0x41d),'type':'Base','powerup':_0x12575b(0x446),'theme':'monstrocity'},{'name':_0x12575b(0x3d7),'strength':0x4,'speed':0x4,'tactics':0x4,'size':'Small','type':_0x12575b(0x268),'powerup':_0x12575b(0x446),'theme':'monstrocity'},{'name':_0x12575b(0x344),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x12575b(0x41d),'type':_0x12575b(0x268),'powerup':_0x12575b(0x446),'theme':'monstrocity'},{'name':_0x12575b(0x257),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x12575b(0x41d),'type':_0x12575b(0x268),'powerup':_0x12575b(0x2d8),'theme':'monstrocity'},{'name':_0x12575b(0x488),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x12575b(0x41d),'type':_0x12575b(0x268),'powerup':_0x12575b(0x2d8),'theme':_0x12575b(0x38d)},{'name':_0x12575b(0x467),'strength':0x6,'speed':0x6,'tactics':0x6,'size':_0x12575b(0x205),'type':_0x12575b(0x268),'powerup':'Heal','theme':_0x12575b(0x38d)},{'name':_0x12575b(0x3b4),'strength':0x7,'speed':0x7,'tactics':0x7,'size':'Large','type':_0x12575b(0x268),'powerup':_0x12575b(0x476),'theme':'monstrocity'},{'name':'Ouchie','strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x12575b(0x41d),'type':_0x12575b(0x268),'powerup':_0x12575b(0x476),'theme':_0x12575b(0x38d)},{'name':_0x12575b(0x1bd),'strength':0x8,'speed':0x7,'tactics':0x7,'size':_0x12575b(0x41d),'type':'Base','powerup':_0x12575b(0x476),'theme':_0x12575b(0x38d)},{'name':_0x12575b(0x241),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x12575b(0x41d),'type':_0x12575b(0x44e),'powerup':'Minor\x20Regen','theme':_0x12575b(0x38d)},{'name':_0x12575b(0x45d),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x12575b(0x2d7),'type':_0x12575b(0x44e),'powerup':'Minor\x20Regen','theme':_0x12575b(0x38d)},{'name':_0x12575b(0x44c),'strength':0x2,'speed':0x2,'tactics':0x2,'size':'Small','type':_0x12575b(0x44e),'powerup':_0x12575b(0x35a),'theme':'monstrocity'},{'name':'Texby','strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x12575b(0x41d),'type':_0x12575b(0x44e),'powerup':_0x12575b(0x317),'theme':_0x12575b(0x38d)},{'name':_0x12575b(0x345),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x12575b(0x41d),'type':_0x12575b(0x44e),'powerup':_0x12575b(0x446),'theme':_0x12575b(0x38d)},{'name':_0x12575b(0x221),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x12575b(0x41d),'type':_0x12575b(0x44e),'powerup':_0x12575b(0x446),'theme':'monstrocity'},{'name':_0x12575b(0x3d7),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x12575b(0x205),'type':_0x12575b(0x44e),'powerup':'Regenerate','theme':_0x12575b(0x38d)},{'name':_0x12575b(0x344),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x12575b(0x41d),'type':'Leader','powerup':'Regenerate','theme':_0x12575b(0x38d)},{'name':_0x12575b(0x257),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x12575b(0x41d),'type':'Leader','powerup':'Boost\x20Attack','theme':_0x12575b(0x38d)},{'name':_0x12575b(0x488),'strength':0x5,'speed':0x5,'tactics':0x5,'size':'Medium','type':_0x12575b(0x44e),'powerup':_0x12575b(0x2d8),'theme':_0x12575b(0x38d)},{'name':_0x12575b(0x467),'strength':0x6,'speed':0x6,'tactics':0x6,'size':'Small','type':_0x12575b(0x44e),'powerup':_0x12575b(0x476),'theme':_0x12575b(0x38d)},{'name':_0x12575b(0x3b4),'strength':0x7,'speed':0x7,'tactics':0x7,'size':'Large','type':_0x12575b(0x44e),'powerup':_0x12575b(0x476),'theme':_0x12575b(0x38d)},{'name':_0x12575b(0x41e),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x12575b(0x41d),'type':_0x12575b(0x44e),'powerup':_0x12575b(0x476),'theme':_0x12575b(0x38d)},{'name':_0x12575b(0x1bd),'strength':0x8,'speed':0x7,'tactics':0x7,'size':_0x12575b(0x41d),'type':_0x12575b(0x44e),'powerup':_0x12575b(0x476),'theme':_0x12575b(0x38d)}],characterDirections={'Billandar\x20and\x20Ted':'Left','Craig':_0x12575b(0x3e2),'Dankle':_0x12575b(0x3e2),'Drake':_0x12575b(0x34d),'Goblin\x20Ganger':_0x12575b(0x3e2),'Jarhead':'Right','Katastrophy':'Right','Koipon':'Left','Mandiblus':'Left','Merdock':_0x12575b(0x3e2),'Ouchie':_0x12575b(0x3e2),'Slime\x20Mind':_0x12575b(0x34d),'Spydrax':'Right','Texby':_0x12575b(0x3e2)};class MonstrocityMatch3{constructor(_0x1e8da1,_0x121a77){const _0x515400=_0x12575b;this[_0x515400(0x471)]='ontouchstart'in window||navigator['maxTouchPoints']>0x0||navigator['msMaxTouchPoints']>0x0,this[_0x515400(0x473)]=0x5,this[_0x515400(0x2ca)]=0x5,this['board']=[],this[_0x515400(0x37b)]=null,this[_0x515400(0x254)]=![],this[_0x515400(0x31c)]=null,this['player1']=null,this[_0x515400(0x385)]=null,this[_0x515400(0x499)]='initializing',this[_0x515400(0x4b4)]=![],this[_0x515400(0x1f9)]=null,this[_0x515400(0x31a)]=null,this['offsetX']=0x0,this[_0x515400(0x327)]=0x0,this[_0x515400(0x240)]=0x1,this['playerCharactersConfig']=_0x1e8da1,this[_0x515400(0x46d)]=[],this[_0x515400(0x30c)]=![],this[_0x515400(0x478)]=[_0x515400(0x219),'second-attack','special-attack',_0x515400(0x338),'last-stand'],this[_0x515400(0x293)]=[],this[_0x515400(0x421)]=0x0,this[_0x515400(0x2e9)]=null,this[_0x515400(0x292)]=null;const _0x3cc1f6=themes[_0x515400(0x3a1)](_0x2b4b88=>_0x2b4b88[_0x515400(0x296)])[_0x515400(0x377)](_0x66df44=>_0x66df44[_0x515400(0x27d)]),_0x56605b=localStorage[_0x515400(0x2f3)](_0x515400(0x2d0));this[_0x515400(0x404)]=_0x56605b&&_0x3cc1f6[_0x515400(0x3d1)](_0x56605b)?_0x56605b:_0x121a77&&_0x3cc1f6[_0x515400(0x3d1)](_0x121a77)?_0x121a77:_0x515400(0x38d),console[_0x515400(0x253)](_0x515400(0x3da)+_0x121a77+_0x515400(0x497)+_0x56605b+_0x515400(0x33a)+this[_0x515400(0x404)]),this[_0x515400(0x468)]='images/monstrocity/'+this[_0x515400(0x404)]+'/',this[_0x515400(0x3c3)]={'match':new Audio(_0x515400(0x477)),'cascade':new Audio('https://www.skulliance.io/staking/sounds/select.ogg'),'badMove':new Audio(_0x515400(0x252)),'gameOver':new Audio('https://www.skulliance.io/staking/sounds/voice_gameover.ogg'),'reset':new Audio('https://www.skulliance.io/staking/sounds/voice_go.ogg'),'loss':new Audio(_0x515400(0x353)),'win':new Audio('https://www.skulliance.io/staking/sounds/voice_levelcomplete.ogg'),'finalWin':new Audio(_0x515400(0x323)),'powerGem':new Audio(_0x515400(0x2ab)),'hyperCube':new Audio(_0x515400(0x27f)),'multiMatch':new Audio('https://www.skulliance.io/staking/sounds/speedmatch1.ogg')},this['updateTileSizeWithGap'](),this[_0x515400(0x339)]();}[_0x12575b(0x263)](_0x3a6849){const _0x25a697=_0x12575b;console[_0x25a697(0x253)](_0x25a697(0x461)),console['log']('\x20\x20Name:',_0x3a6849['name']),console[_0x25a697(0x253)](_0x25a697(0x326),_0x3a6849['health'],'/',_0x3a6849[_0x25a697(0x1da)]),console[_0x25a697(0x253)](_0x25a697(0x3b0),_0x3a6849[_0x25a697(0x3a4)]),this[_0x25a697(0x2e9)]=_0x3a6849,console[_0x25a697(0x253)](_0x25a697(0x35c)+_0x3a6849['name']);}[_0x12575b(0x1cc)](_0x5613f3){const _0x3db444=_0x12575b;this['selectedCharacter']=_0x5613f3,console['log'](_0x3db444(0x49b)+_0x5613f3[_0x3db444(0x266)]),this[_0x3db444(0x239)]();}async['startBossBattle'](){const _0x33cf5b=_0x12575b;console[_0x33cf5b(0x253)](_0x33cf5b(0x1cb)),console[_0x33cf5b(0x253)](_0x33cf5b(0x215),this['selectedCharacter'][_0x33cf5b(0x266)]),console[_0x33cf5b(0x253)](_0x33cf5b(0x443),this[_0x33cf5b(0x2e9)][_0x33cf5b(0x266)]),console[_0x33cf5b(0x253)](_0x33cf5b(0x335),this[_0x33cf5b(0x2e9)][_0x33cf5b(0x404)]);const _0x1a6e13=this[_0x33cf5b(0x2e9)][_0x33cf5b(0x1f8)]||_0x33cf5b(0x3e9),_0xabe3e9=this[_0x33cf5b(0x2e9)][_0x33cf5b(0x431)]||'images/monstrocity/bosses/'+this[_0x33cf5b(0x2e9)][_0x33cf5b(0x266)][_0x33cf5b(0x260)]()[_0x33cf5b(0x49a)](/ /g,'-')+'.'+_0x1a6e13,_0xa86593=this[_0x33cf5b(0x2e9)]['battleDamagedUrl']||_0x33cf5b(0x217)+this[_0x33cf5b(0x2e9)]['name'][_0x33cf5b(0x260)]()[_0x33cf5b(0x49a)](/ /g,'-')+'.'+_0x1a6e13,_0x2603da=[_0xabe3e9,_0xa86593,this[_0x33cf5b(0x292)][_0x33cf5b(0x431)]];_0x2603da[_0x33cf5b(0x439)](_0x224678=>{const _0x204ebf=_0x33cf5b,_0x11dd47=new Image();_0x11dd47[_0x204ebf(0x2ed)]=_0x224678,_0x11dd47[_0x204ebf(0x4b8)]=()=>console[_0x204ebf(0x253)]('Preloaded\x20image:\x20'+_0x224678),_0x11dd47[_0x204ebf(0x4a2)]=()=>console[_0x204ebf(0x253)](_0x204ebf(0x324)+_0x224678);});if(this[_0x33cf5b(0x404)]!==this[_0x33cf5b(0x2e9)][_0x33cf5b(0x404)]){console[_0x33cf5b(0x21f)](_0x33cf5b(0x4af)+this['theme']+_0x33cf5b(0x4a3)+this[_0x33cf5b(0x2e9)][_0x33cf5b(0x404)]+_0x33cf5b(0x490));const _0x5ef6e9=themes[_0x33cf5b(0x3a1)](_0x518109=>_0x518109['items'])[_0x33cf5b(0x377)](_0x1e8100=>_0x1e8100[_0x33cf5b(0x27d)]);this['selectedBoss']['theme']&&_0x5ef6e9['includes'](this['selectedBoss'][_0x33cf5b(0x404)])&&await this[_0x33cf5b(0x399)](this[_0x33cf5b(0x2e9)][_0x33cf5b(0x404)],!![]);}document[_0x33cf5b(0x4a4)](_0x33cf5b(0x407))[_0x33cf5b(0x2ed)]=_0x33cf5b(0x367)+this[_0x33cf5b(0x404)]+_0x33cf5b(0x42c);let _0x56c6aa=this['selectedBoss'][_0x33cf5b(0x2fd)]||_0x33cf5b(0x34d);_0x56c6aa===_0x33cf5b(0x3ba)&&(_0x56c6aa=Math[_0x33cf5b(0x47e)]()<0.5?_0x33cf5b(0x3e2):_0x33cf5b(0x34d),console[_0x33cf5b(0x253)]('Random\x20boss\x20orientation\x20resolved\x20to:\x20'+_0x56c6aa));let _0x105f23=this[_0x33cf5b(0x292)][_0x33cf5b(0x2fd)]||_0x33cf5b(0x3ba);_0x105f23===_0x33cf5b(0x3ba)&&(_0x105f23=Math[_0x33cf5b(0x47e)]()<0.5?'Left':'Right',console[_0x33cf5b(0x253)](_0x33cf5b(0x1f5)+_0x105f23));this[_0x33cf5b(0x1ca)]={...this[_0x33cf5b(0x292)],'orientation':_0x105f23},this[_0x33cf5b(0x385)]={'name':this[_0x33cf5b(0x2e9)][_0x33cf5b(0x266)],'strength':this[_0x33cf5b(0x2e9)][_0x33cf5b(0x3a4)]||0x4,'speed':this[_0x33cf5b(0x2e9)]['speed']||0x4,'tactics':this['selectedBoss'][_0x33cf5b(0x232)]||0x4,'size':this[_0x33cf5b(0x2e9)]['size']||_0x33cf5b(0x41d),'type':'Boss','powerup':this[_0x33cf5b(0x2e9)][_0x33cf5b(0x3ef)]||_0x33cf5b(0x35a),'theme':this[_0x33cf5b(0x2e9)][_0x33cf5b(0x404)]||this[_0x33cf5b(0x404)],'imageUrl':_0xabe3e9,'extension':_0x1a6e13,'battleDamagedUrl':_0xa86593,'fallbackUrl':_0x33cf5b(0x204),'orientation':_0x56c6aa,'health':this[_0x33cf5b(0x2e9)]['health'],'maxHealth':this[_0x33cf5b(0x2e9)][_0x33cf5b(0x1da)],'mediaType':this['selectedBoss'][_0x33cf5b(0x44d)]||_0x33cf5b(0x23a),'isBossBattle':!![]};const _0x46bf76=window[_0x33cf5b(0x3e8)]||null;if(_0x46bf76&&this[_0x33cf5b(0x2e9)]&&this[_0x33cf5b(0x2e9)]['id'])try{const _0x5189fb=await fetch(_0x33cf5b(0x235),{'method':_0x33cf5b(0x1f6),'headers':{'Content-Type':_0x33cf5b(0x328)},'body':'user_id='+encodeURIComponent(_0x46bf76)+_0x33cf5b(0x47a)+encodeURIComponent(this[_0x33cf5b(0x2e9)]['id'])}),_0x376be9=await _0x5189fb[_0x33cf5b(0x32b)]();_0x376be9[_0x33cf5b(0x4ad)]&&_0x376be9[_0x33cf5b(0x39e)]!==null?(this['player1']['health']=_0x376be9['health'],console[_0x33cf5b(0x253)](_0x33cf5b(0x375)+this['player1'][_0x33cf5b(0x39e)])):(this[_0x33cf5b(0x1ca)]['health']=this[_0x33cf5b(0x1ca)][_0x33cf5b(0x1da)],console['log'](_0x33cf5b(0x419)));}catch(_0xf9cd6b){console['error'](_0x33cf5b(0x238),_0xf9cd6b),this[_0x33cf5b(0x1ca)]['health']=this[_0x33cf5b(0x1ca)][_0x33cf5b(0x1da)];}else this[_0x33cf5b(0x1ca)][_0x33cf5b(0x39e)]=this[_0x33cf5b(0x1ca)]['maxHealth'],console[_0x33cf5b(0x253)](_0x33cf5b(0x2ad));this[_0x33cf5b(0x385)][_0x33cf5b(0x39e)]=this[_0x33cf5b(0x2e9)][_0x33cf5b(0x39e)];if(this[_0x33cf5b(0x1ca)]['health']<=0x0||this[_0x33cf5b(0x385)][_0x33cf5b(0x39e)]<=0x0){console[_0x33cf5b(0x253)](_0x33cf5b(0x289)+this[_0x33cf5b(0x1ca)][_0x33cf5b(0x39e)]+_0x33cf5b(0x4b3)+this[_0x33cf5b(0x385)][_0x33cf5b(0x39e)]),this[_0x33cf5b(0x254)]=!![],this[_0x33cf5b(0x499)]=_0x33cf5b(0x254);const _0xbdd052=document[_0x33cf5b(0x4a4)](_0x33cf5b(0x2c7)),_0x1de780=document['getElementById'](_0x33cf5b(0x383));_0xbdd052[_0x33cf5b(0x3ff)][_0x33cf5b(0x2c6)]=_0x33cf5b(0x3df),_0x1de780[_0x33cf5b(0x3ff)][_0x33cf5b(0x288)]=_0x33cf5b(0x242),this[_0x33cf5b(0x47b)](),this[_0x33cf5b(0x38f)](),this[_0x33cf5b(0x1d0)]();const _0xea82ae=document['getElementById'](_0x33cf5b(0x265)),_0x391f36=document['getElementById'](_0x33cf5b(0x381));_0xea82ae&&(_0xea82ae[_0x33cf5b(0x3ff)][_0x33cf5b(0x2a4)]=this['player1'][_0x33cf5b(0x2fd)]===_0x33cf5b(0x3e2)?'scaleX(-1)':'none',console[_0x33cf5b(0x253)](_0x33cf5b(0x41c)+this[_0x33cf5b(0x1ca)][_0x33cf5b(0x2fd)]+_0x33cf5b(0x374)+_0xea82ae[_0x33cf5b(0x3ff)][_0x33cf5b(0x2a4)]));_0x391f36&&(_0x391f36[_0x33cf5b(0x3ff)][_0x33cf5b(0x2a4)]=this[_0x33cf5b(0x385)][_0x33cf5b(0x2fd)]===_0x33cf5b(0x34d)?_0x33cf5b(0x491):_0x33cf5b(0x396),console[_0x33cf5b(0x253)]('startBossBattle:\x20player2\x20orientation\x20set\x20to\x20'+this[_0x33cf5b(0x385)][_0x33cf5b(0x2fd)]+_0x33cf5b(0x374)+_0x391f36[_0x33cf5b(0x3ff)][_0x33cf5b(0x2a4)]));this[_0x33cf5b(0x24a)](this[_0x33cf5b(0x1ca)]),this['updateHealth'](this[_0x33cf5b(0x385)]),battleLog[_0x33cf5b(0x20b)]='',gameOver[_0x33cf5b(0x1c2)]='',this['toggleGameButtons'](!![]);if(this[_0x33cf5b(0x1ca)][_0x33cf5b(0x39e)]<=0x0)gameOver['textContent']='You\x20Lose!',turnIndicator[_0x33cf5b(0x1c2)]=_0x33cf5b(0x314),log(this['player1'][_0x33cf5b(0x266)]+_0x33cf5b(0x1e2)),this['sounds'][_0x33cf5b(0x435)][_0x33cf5b(0x391)]();else{if(this[_0x33cf5b(0x385)]['health']<=0x0){gameOver['textContent']=_0x33cf5b(0x2d5),turnIndicator['textContent']='Game\x20Over',log(this['player2'][_0x33cf5b(0x266)]+_0x33cf5b(0x3e7)),this[_0x33cf5b(0x3c3)][_0x33cf5b(0x36b)][_0x33cf5b(0x391)]();let _0x1c9c27=this[_0x33cf5b(0x385)]['battleDamagedUrl']||_0x33cf5b(0x217)+this[_0x33cf5b(0x385)]['name'][_0x33cf5b(0x260)]()[_0x33cf5b(0x49a)](/ /g,'-')+'.'+(this[_0x33cf5b(0x385)][_0x33cf5b(0x1f8)]||_0x33cf5b(0x3e9));const _0x48fa6b=document[_0x33cf5b(0x43c)](_0x33cf5b(0x381));_0x48fa6b[_0x33cf5b(0x2af)]===_0x33cf5b(0x277)&&(_0x48fa6b[_0x33cf5b(0x2ed)]=_0x1c9c27,_0x48fa6b[_0x33cf5b(0x4a2)]=()=>{const _0x3e9e4f=_0x33cf5b;_0x48fa6b[_0x3e9e4f(0x2ed)]=this[_0x3e9e4f(0x385)]['fallbackUrl'];}),_0x48fa6b[_0x33cf5b(0x3fd)][_0x33cf5b(0x2b3)](_0x33cf5b(0x233)),_0xea82ae[_0x33cf5b(0x3fd)][_0x33cf5b(0x2b3)](_0x33cf5b(0x2b5));}}const _0x12c072=document[_0x33cf5b(0x43c)](_0x33cf5b(0x285));_0x12c072[_0x33cf5b(0x1c2)]=_0x33cf5b(0x3d3);const _0xc65757=_0x12c072[_0x33cf5b(0x2a6)](!![]);_0x12c072[_0x33cf5b(0x25d)][_0x33cf5b(0x2c4)](_0xc65757,_0x12c072),_0xc65757[_0x33cf5b(0x27c)]('click',()=>this[_0x33cf5b(0x415)]()),document[_0x33cf5b(0x43c)](_0x33cf5b(0x23b))[_0x33cf5b(0x3ff)][_0x33cf5b(0x2c6)]=_0x33cf5b(0x3df),this[_0x33cf5b(0x2f6)]();return;}console[_0x33cf5b(0x253)](_0x33cf5b(0x3be),{'Name':this['player1'][_0x33cf5b(0x266)],'Health':this['player1'][_0x33cf5b(0x39e)]+'/'+this[_0x33cf5b(0x1ca)][_0x33cf5b(0x1da)],'Strength':this[_0x33cf5b(0x1ca)]['strength'],'Speed':this[_0x33cf5b(0x1ca)][_0x33cf5b(0x3e3)],'Tactics':this[_0x33cf5b(0x1ca)][_0x33cf5b(0x232)],'Size':this[_0x33cf5b(0x1ca)][_0x33cf5b(0x3ee)],'Type':this['player1'][_0x33cf5b(0x386)],'Powerup':this[_0x33cf5b(0x1ca)]['powerup'],'Theme':this[_0x33cf5b(0x1ca)]['theme'],'Orientation':this['player1']['orientation']}),console['log'](_0x33cf5b(0x2c2),{'Name':this[_0x33cf5b(0x385)][_0x33cf5b(0x266)],'Health':this['player2'][_0x33cf5b(0x39e)]+'/'+this[_0x33cf5b(0x385)]['maxHealth'],'Strength':this[_0x33cf5b(0x385)]['strength'],'Speed':this['player2'][_0x33cf5b(0x3e3)],'Tactics':this['player2'][_0x33cf5b(0x232)],'Size':this[_0x33cf5b(0x385)][_0x33cf5b(0x3ee)],'Type':this['player2'][_0x33cf5b(0x386)],'Powerup':this['player2'][_0x33cf5b(0x3ef)],'Theme':this[_0x33cf5b(0x385)]['theme'],'ImageUrl':this[_0x33cf5b(0x385)][_0x33cf5b(0x431)],'BattleDamagedUrl':this[_0x33cf5b(0x385)][_0x33cf5b(0x444)],'Extension':this['player2']['extension'],'Orientation':this[_0x33cf5b(0x385)][_0x33cf5b(0x2fd)],'IsBossBattle':this[_0x33cf5b(0x385)][_0x33cf5b(0x3c7)]});const _0x255c4a=document[_0x33cf5b(0x4a4)](_0x33cf5b(0x2c7)),_0x70d04c=document[_0x33cf5b(0x43c)](_0x33cf5b(0x383));_0x255c4a[_0x33cf5b(0x3ff)][_0x33cf5b(0x2c6)]=_0x33cf5b(0x3df),_0x70d04c[_0x33cf5b(0x3ff)][_0x33cf5b(0x288)]='visible',this['setBackground'](),this[_0x33cf5b(0x3c3)][_0x33cf5b(0x1ea)]['play'](),log('Starting\x20Boss\x20Battle...'),this[_0x33cf5b(0x31c)]=this['player1'][_0x33cf5b(0x3e3)]>this[_0x33cf5b(0x385)]['speed']?this[_0x33cf5b(0x1ca)]:this[_0x33cf5b(0x385)]['speed']>this['player1'][_0x33cf5b(0x3e3)]?this[_0x33cf5b(0x385)]:this['player1']['strength']>=this[_0x33cf5b(0x385)][_0x33cf5b(0x3a4)]?this[_0x33cf5b(0x1ca)]:this[_0x33cf5b(0x385)],this[_0x33cf5b(0x499)]='initializing',this['gameOver']=![],this['roundStats']=[];const _0x1b34d6=document[_0x33cf5b(0x43c)](_0x33cf5b(0x265)),_0x392a9b=document[_0x33cf5b(0x43c)](_0x33cf5b(0x381));if(_0x1b34d6)_0x1b34d6[_0x33cf5b(0x3fd)][_0x33cf5b(0x37a)](_0x33cf5b(0x2b5),_0x33cf5b(0x233));if(_0x392a9b)_0x392a9b['classList']['remove'](_0x33cf5b(0x2b5),_0x33cf5b(0x233));this[_0x33cf5b(0x38f)](),this[_0x33cf5b(0x1d0)](),_0x1b34d6&&(_0x1b34d6['style']['transform']=this[_0x33cf5b(0x1ca)][_0x33cf5b(0x2fd)]===_0x33cf5b(0x3e2)?_0x33cf5b(0x491):'none',console[_0x33cf5b(0x253)]('startBossBattle:\x20player1\x20orientation\x20set\x20to\x20'+this[_0x33cf5b(0x1ca)]['orientation']+_0x33cf5b(0x374)+_0x1b34d6[_0x33cf5b(0x3ff)][_0x33cf5b(0x2a4)])),_0x392a9b&&(_0x392a9b[_0x33cf5b(0x3ff)][_0x33cf5b(0x2a4)]=this['player2']['orientation']===_0x33cf5b(0x34d)?_0x33cf5b(0x491):'none',console[_0x33cf5b(0x253)](_0x33cf5b(0x3fe)+this[_0x33cf5b(0x385)]['orientation']+_0x33cf5b(0x374)+_0x392a9b['style']['transform'])),this[_0x33cf5b(0x24a)](this[_0x33cf5b(0x1ca)]),this['updateHealth'](this['player2']),battleLog[_0x33cf5b(0x20b)]='',gameOver[_0x33cf5b(0x1c2)]='',this[_0x33cf5b(0x273)](!![]),this[_0x33cf5b(0x1ca)][_0x33cf5b(0x3ee)]!=='Medium'&&log(this[_0x33cf5b(0x1ca)]['name']+_0x33cf5b(0x37d)+this[_0x33cf5b(0x1ca)][_0x33cf5b(0x3ee)]+_0x33cf5b(0x3de)+(this[_0x33cf5b(0x1ca)]['size']==='Large'?_0x33cf5b(0x41a)+this[_0x33cf5b(0x1ca)][_0x33cf5b(0x1da)]+'\x20but\x20dulls\x20tactics\x20to\x20'+this[_0x33cf5b(0x1ca)]['tactics']:_0x33cf5b(0x32f)+this[_0x33cf5b(0x1ca)]['maxHealth']+_0x33cf5b(0x1f0)+this[_0x33cf5b(0x1ca)][_0x33cf5b(0x232)])+'!'),this[_0x33cf5b(0x385)]['size']!=='Medium'&&log(this['player2'][_0x33cf5b(0x266)]+_0x33cf5b(0x37d)+this[_0x33cf5b(0x385)][_0x33cf5b(0x3ee)]+_0x33cf5b(0x3de)+(this[_0x33cf5b(0x385)][_0x33cf5b(0x3ee)]===_0x33cf5b(0x2d7)?_0x33cf5b(0x41a)+this[_0x33cf5b(0x385)][_0x33cf5b(0x1da)]+_0x33cf5b(0x405)+this[_0x33cf5b(0x385)][_0x33cf5b(0x232)]:_0x33cf5b(0x32f)+this['player2'][_0x33cf5b(0x1da)]+_0x33cf5b(0x1f0)+this[_0x33cf5b(0x385)]['tactics'])+'!'),log(this[_0x33cf5b(0x1ca)][_0x33cf5b(0x266)]+_0x33cf5b(0x3b6)+this[_0x33cf5b(0x1ca)][_0x33cf5b(0x39e)]+'/'+this[_0x33cf5b(0x1ca)][_0x33cf5b(0x1da)]+_0x33cf5b(0x284)),log(this[_0x33cf5b(0x31c)][_0x33cf5b(0x266)]+_0x33cf5b(0x1ff)),this[_0x33cf5b(0x3c6)](),this['gameState']=this[_0x33cf5b(0x31c)]===this[_0x33cf5b(0x1ca)]?_0x33cf5b(0x2fb):_0x33cf5b(0x23d),turnIndicator[_0x33cf5b(0x1c2)]=_0x33cf5b(0x251)+(this[_0x33cf5b(0x31c)]===this['player1']?_0x33cf5b(0x403):_0x33cf5b(0x366))+_0x33cf5b(0x2f2),this[_0x33cf5b(0x31c)]===this[_0x33cf5b(0x385)]&&setTimeout(()=>this['aiTurn'](),0x3e8),log(_0x33cf5b(0x1dc)+this[_0x33cf5b(0x1ca)][_0x33cf5b(0x266)]+_0x33cf5b(0x243)+this[_0x33cf5b(0x385)][_0x33cf5b(0x266)]+'!');}[_0x12575b(0x415)](){const _0x177a94=_0x12575b;console[_0x177a94(0x253)](_0x177a94(0x2eb));const _0xf9cc9a=document['querySelector'](_0x177a94(0x2c7)),_0x253bd8=document[_0x177a94(0x43c)](_0x177a94(0x23b));_0xf9cc9a[_0x177a94(0x3ff)][_0x177a94(0x2c6)]='none',_0x253bd8[_0x177a94(0x3ff)][_0x177a94(0x2c6)]=_0x177a94(0x396);const _0x4071c1=document[_0x177a94(0x43c)]('boss-select-container');_0x4071c1?(_0x4071c1[_0x177a94(0x3ff)][_0x177a94(0x2c6)]='block',typeof showBossSelect===_0x177a94(0x25f)?(showBossSelect(this),console[_0x177a94(0x253)]('showBossSelectionScreen:\x20Called\x20global\x20showBossSelect')):(console['error']('showBossSelectionScreen:\x20showBossSelect\x20function\x20not\x20found'),_0x4071c1[_0x177a94(0x20b)]='<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>Error:\x20Boss\x20selection\x20UI\x20unavailable.</p>')):console[_0x177a94(0x49f)]('showBossSelectionScreen:\x20Boss\x20select\x20container\x20(#boss-select-container)\x20not\x20found'),this['selectedBoss']=null,this[_0x177a94(0x292)]=null,this[_0x177a94(0x499)]=_0x177a94(0x2f0),this[_0x177a94(0x254)]=![],battleLog[_0x177a94(0x20b)]='',gameOver[_0x177a94(0x1c2)]='';}[_0x12575b(0x21c)](){const _0x26de9b=_0x12575b;if(!this[_0x26de9b(0x2e9)]){console[_0x26de9b(0x253)](_0x26de9b(0x2a3));return;}const _0x3ec0f3=window['userId']||null;if(!_0x3ec0f3){console[_0x26de9b(0x21f)]('savePlayerHealth:\x20userId\x20not\x20defined.\x20Ensure\x20window.userId\x20is\x20set\x20in\x20PHP.'),log('Cannot\x20save\x20health:\x20User\x20not\x20logged\x20in\x20or\x20userId\x20missing.');return;}const _0x3e223a=this[_0x26de9b(0x2e9)]['id'],_0x53fc47=this[_0x26de9b(0x1ca)][_0x26de9b(0x39e)];console[_0x26de9b(0x253)](_0x26de9b(0x336)+_0x3ec0f3+_0x26de9b(0x24f)+_0x3e223a+_0x26de9b(0x213)+_0x53fc47),fetch(_0x26de9b(0x376),{'method':_0x26de9b(0x1f6),'headers':{'Content-Type':_0x26de9b(0x328)},'body':_0x26de9b(0x304)+encodeURIComponent(_0x3ec0f3)+_0x26de9b(0x47a)+encodeURIComponent(_0x3e223a)+'&health='+encodeURIComponent(_0x53fc47)})[_0x26de9b(0x3b8)](_0x212c1e=>{const _0x157eb0=_0x26de9b;console['log'](_0x157eb0(0x318),_0x212c1e[_0x157eb0(0x2ac)]);if(!_0x212c1e['ok'])throw new Error('HTTP\x20error!\x20Status:\x20'+_0x212c1e['status']);return _0x212c1e[_0x157eb0(0x32b)]();})[_0x26de9b(0x3b8)](_0x29b785=>{const _0x38dfca=_0x26de9b;_0x29b785[_0x38dfca(0x4ad)]?(console['log'](_0x38dfca(0x248)),log(_0x38dfca(0x414)+_0x53fc47+_0x38dfca(0x34c)+this[_0x38dfca(0x1ca)][_0x38dfca(0x266)]+'\x20vs\x20'+this['selectedBoss']['name'])):(console[_0x38dfca(0x49f)](_0x38dfca(0x1f3),_0x29b785[_0x38dfca(0x49f)]||_0x38dfca(0x42e)),log('Failed\x20to\x20save\x20health:\x20'+(_0x29b785['error']||_0x38dfca(0x25c))));})[_0x26de9b(0x2b4)](_0x3f386e=>{const _0x14204e=_0x26de9b;console['error'](_0x14204e(0x416),_0x3f386e),log(_0x14204e(0x315)+_0x3f386e[_0x14204e(0x291)]);});}[_0x12575b(0x371)](){const _0x5a2cdb=_0x12575b;if(!this[_0x5a2cdb(0x2e9)]){console[_0x5a2cdb(0x253)](_0x5a2cdb(0x220));return;}const _0x389320=this[_0x5a2cdb(0x2e9)]['id'],_0x2272e6=this['player2'][_0x5a2cdb(0x39e)];console['log']('saveBossHealth:\x20Saving\x20-\x20bossId='+_0x389320+',\x20health='+_0x2272e6),fetch(_0x5a2cdb(0x249),{'method':_0x5a2cdb(0x1f6),'headers':{'Content-Type':_0x5a2cdb(0x328)},'body':'user_id='+encodeURIComponent(userId)+'&boss_id='+encodeURIComponent(_0x389320)+_0x5a2cdb(0x32c)+encodeURIComponent(_0x2272e6)})[_0x5a2cdb(0x3b8)](_0x35f272=>{const _0x43251f=_0x5a2cdb;console['log'](_0x43251f(0x299),_0x35f272[_0x43251f(0x2ac)]);if(!_0x35f272['ok'])throw new Error(_0x43251f(0x48f)+_0x35f272[_0x43251f(0x2ac)]);return _0x35f272[_0x43251f(0x32b)]();})[_0x5a2cdb(0x3b8)](_0x28f25f=>{const _0x3538e4=_0x5a2cdb;_0x28f25f[_0x3538e4(0x4ad)]?(console[_0x3538e4(0x253)](_0x3538e4(0x21e)),log(_0x3538e4(0x295)+_0x2272e6+_0x3538e4(0x34c)+this[_0x3538e4(0x2e9)][_0x3538e4(0x266)])):(console[_0x3538e4(0x49f)](_0x3538e4(0x225),_0x28f25f[_0x3538e4(0x49f)]||_0x3538e4(0x42e)),log(_0x3538e4(0x22a)+(_0x28f25f[_0x3538e4(0x49f)]||_0x3538e4(0x25c))));})['catch'](_0x5d4b6e=>{const _0xa70e84=_0x5a2cdb;console[_0xa70e84(0x49f)](_0xa70e84(0x2a2),_0x5d4b6e),log(_0xa70e84(0x321)+_0x5d4b6e[_0xa70e84(0x291)]);});}[_0x12575b(0x22c)](){const _0x508ec3=_0x12575b;if(!this[_0x508ec3(0x2e9)]){console[_0x508ec3(0x253)](_0x508ec3(0x49e));return;}const _0x3facbb=window[_0x508ec3(0x3e8)]||_0x508ec3(0x310);!_0x3facbb&&console[_0x508ec3(0x21f)](_0x508ec3(0x3d6));const _0x466827=this[_0x508ec3(0x2e9)]['id'],_0x59dd0a=_0x508ec3(0x218)+_0x3facbb+'_'+_0x466827,_0x3faf36=this[_0x508ec3(0x2d1)]['map'](_0x5afe37=>_0x5afe37[_0x508ec3(0x377)](_0x4144ca=>({'type':_0x4144ca[_0x508ec3(0x386)]})));console['log'](_0x508ec3(0x270)+_0x59dd0a+_0x508ec3(0x1d7),_0x3faf36);try{localStorage['setItem'](_0x59dd0a,JSON[_0x508ec3(0x24e)](_0x3faf36)),console[_0x508ec3(0x253)](_0x508ec3(0x216)+this[_0x508ec3(0x2e9)][_0x508ec3(0x266)]),log(_0x508ec3(0x359)+this[_0x508ec3(0x2e9)][_0x508ec3(0x266)]);}catch(_0x15dfa1){console['error'](_0x508ec3(0x41f),_0x15dfa1),log('Error\x20saving\x20board\x20state:\x20'+_0x15dfa1[_0x508ec3(0x291)]);}}[_0x12575b(0x262)](){const _0x394179=_0x12575b;if(!this[_0x394179(0x2e9)])return console[_0x394179(0x253)](_0x394179(0x2ef)),null;const _0x33cc32=window['userId']||_0x394179(0x310);!_0x33cc32&&console['warn'](_0x394179(0x33e));const _0x5d32a1=this[_0x394179(0x2e9)]['id'],_0x23d1bb=_0x394179(0x218)+_0x33cc32+'_'+_0x5d32a1;console[_0x394179(0x253)](_0x394179(0x1f7)+_0x23d1bb);try{const _0x431fa4=localStorage[_0x394179(0x2f3)](_0x23d1bb);if(_0x431fa4){const _0xcebbb3=JSON[_0x394179(0x484)](_0x431fa4);return console[_0x394179(0x253)](_0x394179(0x45b),_0xcebbb3),_0xcebbb3[_0x394179(0x377)](_0x5bb525=>_0x5bb525['map'](_0x5838e1=>({'type':_0x5838e1[_0x394179(0x386)],'element':null})));}else return console['log'](_0x394179(0x337)),null;}catch(_0x270fe8){return console[_0x394179(0x49f)](_0x394179(0x21b),_0x270fe8),log(_0x394179(0x271)+_0x270fe8[_0x394179(0x291)]),null;}}[_0x12575b(0x341)](){const _0x4e405c=_0x12575b;if(!this[_0x4e405c(0x2e9)]){console[_0x4e405c(0x253)](_0x4e405c(0x351));return;}const _0x3f4c34=window[_0x4e405c(0x3e8)]||_0x4e405c(0x310);!_0x3f4c34&&console[_0x4e405c(0x21f)](_0x4e405c(0x224));const _0x599a0f=this[_0x4e405c(0x2e9)]['id'],_0x8d5ba=_0x4e405c(0x218)+_0x3f4c34+'_'+_0x599a0f;console[_0x4e405c(0x253)](_0x4e405c(0x3f2)+_0x8d5ba);try{localStorage[_0x4e405c(0x2cd)](_0x8d5ba),console[_0x4e405c(0x253)](_0x4e405c(0x4be)+this[_0x4e405c(0x2e9)][_0x4e405c(0x266)]),log(_0x4e405c(0x26e)+this['selectedBoss'][_0x4e405c(0x266)]);}catch(_0x365592){console['error'](_0x4e405c(0x201),_0x365592),log(_0x4e405c(0x2e1)+_0x365592[_0x4e405c(0x291)]);}}[_0x12575b(0x36d)](){const _0x20213f=_0x12575b;console[_0x20213f(0x253)](_0x20213f(0x40c)),this[_0x20213f(0x4b4)]=![],this[_0x20213f(0x37b)]=null,this[_0x20213f(0x1f9)]=null;const _0x562452=document[_0x20213f(0x2b2)](_0x20213f(0x264));_0x562452[_0x20213f(0x439)](_0xf3c521=>{const _0x18bbdb=_0x20213f;_0xf3c521['removeEventListener'](_0x18bbdb(0x3ad),this[_0x18bbdb(0x325)]),_0xf3c521[_0x18bbdb(0x2dc)]('touchstart',this[_0x18bbdb(0x494)]);}),this[_0x20213f(0x2f6)](),this[_0x20213f(0x499)]=this['currentTurn']===this[_0x20213f(0x1ca)]?_0x20213f(0x2fb):'aiTurn',turnIndicator['textContent']=_0x20213f(0x251)+(this['currentTurn']===this['player1']?'Player':_0x20213f(0x366))+'\x27s\x20Turn',console[_0x20213f(0x253)](_0x20213f(0x329),{'player1':this[_0x20213f(0x1ca)]['name'],'player2':this[_0x20213f(0x385)][_0x20213f(0x266)],'currentTurn':this['currentTurn'][_0x20213f(0x266)],'gameState':this[_0x20213f(0x499)]}),log('Board\x20unstuck.\x20Continue\x20the\x20battle!'),this[_0x20213f(0x31c)]===this[_0x20213f(0x385)]&&setTimeout(()=>this['aiTurn'](),0x3e8);}['toggleGameButtons'](_0x19047f){const _0x37c573=_0x12575b;console[_0x37c573(0x253)](_0x37c573(0x480)+(_0x19047f?_0x37c573(0x1d3):_0x37c573(0x429)));const _0x523355=document[_0x37c573(0x4a4)](_0x37c573(0x2c7));if(!_0x523355){console[_0x37c573(0x49f)](_0x37c573(0x21d));return;}const _0x96b29b=document[_0x37c573(0x43c)](_0x37c573(0x469)),_0x510d6e=document[_0x37c573(0x43c)]('refresh-board'),_0x4f53f0=document[_0x37c573(0x43c)]('change-character');if(!_0x96b29b||!_0x510d6e||!_0x4f53f0){console[_0x37c573(0x21f)](_0x37c573(0x256),{'restart':!!_0x96b29b,'refresh':!!_0x510d6e,'changeCharacter':!!_0x4f53f0});return;}_0x96b29b[_0x37c573(0x3ff)][_0x37c573(0x2c6)]=_0x19047f?_0x37c573(0x396):'inline-block',_0x510d6e[_0x37c573(0x3ff)][_0x37c573(0x2c6)]=_0x19047f?_0x37c573(0x46a):_0x37c573(0x396),_0x4f53f0[_0x37c573(0x3ff)][_0x37c573(0x2c6)]=this[_0x37c573(0x46d)][_0x37c573(0x3eb)]>0x1?_0x37c573(0x46a):_0x37c573(0x396),console[_0x37c573(0x253)]('toggleGameButtons:\x20Buttons\x20set\x20-\x20restart:\x20'+_0x96b29b['style']['display']+_0x37c573(0x298)+_0x510d6e['style']['display']+',\x20change:\x20'+_0x4f53f0[_0x37c573(0x3ff)][_0x37c573(0x2c6)]);}['createBoardHash'](_0x5cc0d4){const _0x1d6cde=_0x12575b;let _0xe23ad7=0x0;const _0x5d9201=JSON['stringify'](_0x5cc0d4[_0x1d6cde(0x377)](_0x29e358=>_0x29e358[_0x1d6cde(0x377)](_0x1907b1=>_0x1907b1[_0x1d6cde(0x386)])));for(let _0x51f024=0x0;_0x51f024<_0x5d9201['length'];_0x51f024++){_0xe23ad7=(_0xe23ad7<<0x5)-_0xe23ad7+_0x5d9201[_0x1d6cde(0x384)](_0x51f024),_0xe23ad7|=0x0;}return _0xe23ad7[_0x1d6cde(0x40e)]();}['saveBoard'](){const _0xcfad2c=_0x12575b,_0x5b8a30={'level':this['currentLevel'],'board':this[_0xcfad2c(0x2d1)][_0xcfad2c(0x377)](_0x256112=>_0x256112[_0xcfad2c(0x377)](_0x30ff86=>({'type':_0x30ff86[_0xcfad2c(0x386)]}))),'hash':this['createBoardHash'](this[_0xcfad2c(0x2d1)])};localStorage[_0xcfad2c(0x258)](_0xcfad2c(0x229)+this[_0xcfad2c(0x240)],JSON[_0xcfad2c(0x24e)](_0x5b8a30));}[_0x12575b(0x33d)](){const _0x529b71=_0x12575b,_0x41dad1=_0x529b71(0x229)+this[_0x529b71(0x240)],_0x4b9773=JSON[_0x529b71(0x484)](localStorage['getItem'](_0x41dad1));if(_0x4b9773&&_0x4b9773['level']===this[_0x529b71(0x240)]){const _0x15ccac=this[_0x529b71(0x454)](_0x4b9773[_0x529b71(0x2d1)]);if(_0x4b9773[_0x529b71(0x3e6)]===_0x15ccac)return _0x4b9773[_0x529b71(0x2d1)]['map'](_0x284156=>_0x284156[_0x529b71(0x377)](_0x5cd3f6=>({'type':_0x5cd3f6[_0x529b71(0x386)],'element':null})));else console['warn'](_0x529b71(0x447)+this['currentLevel']+_0x529b71(0x2ce));}return null;}[_0x12575b(0x35b)](){const _0x340e2a=_0x12575b;localStorage['removeItem'](_0x340e2a(0x229)+this[_0x340e2a(0x240)]);}async[_0x12575b(0x1ef)](){const _0xd44c7c=_0x12575b;console['log'](_0xd44c7c(0x30e)),this[_0xd44c7c(0x46d)]=this['playerCharactersConfig'][_0xd44c7c(0x377)](_0x1e95cd=>this[_0xd44c7c(0x26b)](_0x1e95cd)),await this['showCharacterSelect'](!![]);const _0x4704f5=await this['loadProgress'](),{loadedLevel:_0xe82a0,loadedScore:_0x14a2a9,hasProgress:_0xc76003}=_0x4704f5;if(_0xc76003){console[_0xd44c7c(0x253)](_0xd44c7c(0x210)+_0xe82a0+_0xd44c7c(0x2cf)+_0x14a2a9);const _0x1afb04=await this[_0xd44c7c(0x24c)](_0xe82a0,_0x14a2a9);_0x1afb04?(this[_0xd44c7c(0x240)]=_0xe82a0,this['grandTotalScore']=_0x14a2a9,log(_0xd44c7c(0x2fa)+this[_0xd44c7c(0x240)]+_0xd44c7c(0x4ab)+this[_0xd44c7c(0x421)])):(this[_0xd44c7c(0x240)]=0x1,this[_0xd44c7c(0x421)]=0x0,await this[_0xd44c7c(0x28e)](),log(_0xd44c7c(0x3c1)));}else this['currentLevel']=0x1,this['grandTotalScore']=0x0,log(_0xd44c7c(0x487));console[_0xd44c7c(0x253)]('init:\x20Async\x20initialization\x20completed');}['setBackground'](){const _0x32f5c7=_0x12575b;console[_0x32f5c7(0x253)](_0x32f5c7(0x26d)+this[_0x32f5c7(0x404)]);const _0x304842=themes[_0x32f5c7(0x3a1)](_0x1e4760=>_0x1e4760['items'])['find'](_0x47e7b1=>_0x47e7b1[_0x32f5c7(0x27d)]===this[_0x32f5c7(0x404)]);console['log']('setBackground:\x20themeData=',_0x304842);const _0x554fa4='images/monstrocity/'+this['theme']+_0x32f5c7(0x2e6);console[_0x32f5c7(0x253)]('setBackground:\x20Setting\x20background\x20to\x20'+_0x554fa4),_0x304842&&_0x304842[_0x32f5c7(0x272)]?(document[_0x32f5c7(0x245)][_0x32f5c7(0x3ff)][_0x32f5c7(0x340)]=_0x32f5c7(0x34b)+_0x554fa4+')',document['body'][_0x32f5c7(0x3ff)][_0x32f5c7(0x3dc)]='cover',document[_0x32f5c7(0x245)]['style'][_0x32f5c7(0x30b)]=_0x32f5c7(0x48d)):document['body'][_0x32f5c7(0x3ff)]['backgroundImage']=_0x32f5c7(0x396);}[_0x12575b(0x399)](_0x2381de,_0x180a74=![]){const _0x1f2a9d=_0x12575b;if(updatePending)return console[_0x1f2a9d(0x253)](_0x1f2a9d(0x25e)),Promise[_0x1f2a9d(0x346)]();updatePending=!![],console[_0x1f2a9d(0x2b6)](_0x1f2a9d(0x394)+_0x2381de);const _0x57bcb3=this;this[_0x1f2a9d(0x404)]=_0x2381de,this[_0x1f2a9d(0x468)]=_0x1f2a9d(0x367)+this[_0x1f2a9d(0x404)]+'/',localStorage[_0x1f2a9d(0x258)](_0x1f2a9d(0x2d0),this['theme']),this[_0x1f2a9d(0x47b)]();!_0x180a74?(console[_0x1f2a9d(0x253)](_0x1f2a9d(0x489)),this['selectedBoss']=null,this[_0x1f2a9d(0x292)]=null):console['log'](_0x1f2a9d(0x43e));document[_0x1f2a9d(0x4a4)](_0x1f2a9d(0x407))[_0x1f2a9d(0x2ed)]=this['baseImagePath']+_0x1f2a9d(0x411);if(!_0x180a74){const _0x3f745d=document[_0x1f2a9d(0x43c)](_0x1f2a9d(0x26a));_0x3f745d&&(_0x3f745d[_0x1f2a9d(0x20b)]=_0x1f2a9d(0x28a));}return getAssets(this['theme'])[_0x1f2a9d(0x3b8)](function(_0xa081af){const _0x39e75e=_0x1f2a9d;console[_0x39e75e(0x2b6)](_0x39e75e(0x2bd)+_0x2381de),_0x57bcb3['playerCharactersConfig']=_0xa081af,_0x57bcb3[_0x39e75e(0x46d)]=[],_0xa081af[_0x39e75e(0x439)](_0x439cf7=>{const _0x5aff42=_0x39e75e,_0x5a016d=_0x57bcb3[_0x5aff42(0x26b)](_0x439cf7);if(_0x5a016d[_0x5aff42(0x44d)]===_0x5aff42(0x23a)){const _0x1b73b4=new Image();_0x1b73b4[_0x5aff42(0x2ed)]=_0x5a016d['imageUrl'],_0x1b73b4[_0x5aff42(0x4b8)]=()=>console[_0x5aff42(0x253)]('Preloaded:\x20'+_0x5a016d[_0x5aff42(0x431)]),_0x1b73b4[_0x5aff42(0x4a2)]=()=>console['log']('Failed\x20to\x20preload:\x20'+_0x5a016d['imageUrl']);}_0x57bcb3[_0x5aff42(0x46d)][_0x5aff42(0x4bc)](_0x5a016d);});if(_0x57bcb3[_0x39e75e(0x1ca)]&&!_0x180a74){const _0x1baaaf=_0x57bcb3[_0x39e75e(0x43d)]['find'](_0x29da8c=>_0x29da8c[_0x39e75e(0x266)]===_0x57bcb3[_0x39e75e(0x1ca)][_0x39e75e(0x266)])||_0x57bcb3[_0x39e75e(0x43d)][0x0];_0x57bcb3[_0x39e75e(0x1ca)]=_0x57bcb3[_0x39e75e(0x26b)](_0x1baaaf),_0x57bcb3[_0x39e75e(0x38f)]();}_0x57bcb3[_0x39e75e(0x1ca)]&&!_0x180a74&&(_0x57bcb3[_0x39e75e(0x385)]=_0x57bcb3[_0x39e75e(0x26b)](opponentsConfig[_0x57bcb3[_0x39e75e(0x240)]-0x1]),_0x57bcb3['updateOpponentDisplay'](),console[_0x39e75e(0x253)](_0x39e75e(0x436)+_0x57bcb3[_0x39e75e(0x385)][_0x39e75e(0x266)]));if(_0x57bcb3['player1']&&_0x57bcb3[_0x39e75e(0x499)]!==_0x39e75e(0x2f0)&&!_0x180a74){const _0x4bedb8=document['querySelectorAll'](_0x39e75e(0x264));_0x4bedb8['forEach'](_0x4b4c21=>{const _0x53da4d=_0x39e75e;_0x4b4c21[_0x53da4d(0x2dc)]('mousedown',_0x57bcb3[_0x53da4d(0x325)]),_0x4b4c21[_0x53da4d(0x2dc)](_0x53da4d(0x4bb),_0x57bcb3['handleTouchStart']);}),_0x57bcb3[_0x39e75e(0x2f6)](),console[_0x39e75e(0x253)](_0x39e75e(0x2c9));}else console[_0x39e75e(0x253)](_0x39e75e(0x332));_0x57bcb3['player1']&&!_0x180a74&&(_0x57bcb3[_0x39e75e(0x4b4)]=![],_0x57bcb3[_0x39e75e(0x37b)]=null,_0x57bcb3[_0x39e75e(0x1f9)]=null,_0x57bcb3[_0x39e75e(0x499)]=_0x57bcb3[_0x39e75e(0x31c)]===_0x57bcb3[_0x39e75e(0x1ca)]?_0x39e75e(0x2fb):_0x39e75e(0x23d));if(!_0x180a74){const _0x3772fb=document[_0x39e75e(0x43c)](_0x39e75e(0x3a2));_0x3772fb['style'][_0x39e75e(0x2c6)]=_0x39e75e(0x3df),_0x57bcb3[_0x39e75e(0x2ae)](_0x57bcb3['player1']===null);}console[_0x39e75e(0x1d4)](_0x39e75e(0x2bd)+_0x2381de),console[_0x39e75e(0x1d4)](_0x39e75e(0x394)+_0x2381de),updatePending=![];})[_0x1f2a9d(0x2b4)](function(_0xa08317){const _0x5da2e1=_0x1f2a9d;console[_0x5da2e1(0x49f)](_0x5da2e1(0x25a),_0xa08317),_0x57bcb3['playerCharactersConfig']=[{'name':'Craig','strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x5da2e1(0x41d),'type':_0x5da2e1(0x268),'powerup':_0x5da2e1(0x446),'theme':'monstrocity'},{'name':_0x5da2e1(0x257),'strength':0x3,'speed':0x5,'tactics':0x3,'size':'Small','type':_0x5da2e1(0x268),'powerup':_0x5da2e1(0x476),'theme':_0x5da2e1(0x38d)}],_0x57bcb3[_0x5da2e1(0x46d)]=_0x57bcb3[_0x5da2e1(0x43d)][_0x5da2e1(0x377)](_0x4fc434=>_0x57bcb3['createCharacter'](_0x4fc434));!_0x180a74&&(_0x57bcb3[_0x5da2e1(0x2e9)]=null,_0x57bcb3['selectedCharacter']=null);if(!_0x180a74){const _0x360d3c=document['getElementById'](_0x5da2e1(0x3a2));_0x360d3c[_0x5da2e1(0x3ff)]['display']=_0x5da2e1(0x3df),_0x57bcb3[_0x5da2e1(0x2ae)](_0x57bcb3['player1']===null);}console['timeEnd']('updateTheme_'+_0x2381de),updatePending=![];});}async[_0x12575b(0x1fc)](){const _0x1bab3c=_0x12575b,_0x406e6f={'currentLevel':this[_0x1bab3c(0x240)],'grandTotalScore':this['grandTotalScore']};console[_0x1bab3c(0x253)](_0x1bab3c(0x402),_0x406e6f);try{const _0x182d3b=await fetch(_0x1bab3c(0x20c),{'method':'POST','headers':{'Content-Type':_0x1bab3c(0x36c)},'body':JSON[_0x1bab3c(0x24e)](_0x406e6f)});console[_0x1bab3c(0x253)](_0x1bab3c(0x481),_0x182d3b[_0x1bab3c(0x2ac)]);const _0x3c0761=await _0x182d3b['text']();console['log'](_0x1bab3c(0x3a6),_0x3c0761);if(!_0x182d3b['ok'])throw new Error(_0x1bab3c(0x48f)+_0x182d3b[_0x1bab3c(0x2ac)]);const _0x1ad812=JSON[_0x1bab3c(0x484)](_0x3c0761);console[_0x1bab3c(0x253)]('Parsed\x20response:',_0x1ad812),_0x1ad812[_0x1bab3c(0x2ac)]===_0x1bab3c(0x4ad)?log(_0x1bab3c(0x1eb)+this[_0x1bab3c(0x240)]):console[_0x1bab3c(0x49f)](_0x1bab3c(0x4a0),_0x1ad812[_0x1bab3c(0x291)]);}catch(_0x326f39){console[_0x1bab3c(0x49f)](_0x1bab3c(0x35d),_0x326f39);}}async['loadProgress'](){const _0x35c881=_0x12575b;try{console[_0x35c881(0x253)]('Fetching\x20progress\x20from\x20ajax/load-monstrocity-progress.php');const _0x55c054=await fetch('ajax/load-monstrocity-progress.php',{'method':_0x35c881(0x211),'headers':{'Content-Type':'application/json'}});console[_0x35c881(0x253)](_0x35c881(0x481),_0x55c054['status']);if(!_0x55c054['ok'])throw new Error('HTTP\x20error!\x20Status:\x20'+_0x55c054[_0x35c881(0x2ac)]);const _0x4dc24b=await _0x55c054['json']();console[_0x35c881(0x253)](_0x35c881(0x3a3),_0x4dc24b);if(_0x4dc24b[_0x35c881(0x2ac)]==='success'&&_0x4dc24b[_0x35c881(0x261)]){const _0x3e098d=_0x4dc24b[_0x35c881(0x261)];return{'loadedLevel':_0x3e098d[_0x35c881(0x240)]||0x1,'loadedScore':_0x3e098d[_0x35c881(0x421)]||0x0,'hasProgress':!![]};}else return console[_0x35c881(0x253)]('No\x20progress\x20found\x20or\x20status\x20not\x20success:',_0x4dc24b),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}catch(_0x57c782){return console[_0x35c881(0x49f)](_0x35c881(0x4b5),_0x57c782),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}}async[_0x12575b(0x28e)](){const _0x12ae74=_0x12575b;try{const _0x5be8e3=await fetch(_0x12ae74(0x30f),{'method':_0x12ae74(0x1f6),'headers':{'Content-Type':_0x12ae74(0x36c)}});if(!_0x5be8e3['ok'])throw new Error(_0x12ae74(0x48f)+_0x5be8e3[_0x12ae74(0x2ac)]);const _0x150e46=await _0x5be8e3[_0x12ae74(0x32b)]();_0x150e46[_0x12ae74(0x2ac)]==='success'&&(this[_0x12ae74(0x240)]=0x1,this[_0x12ae74(0x421)]=0x0,this[_0x12ae74(0x35b)](),log(_0x12ae74(0x428)));}catch(_0x3ac9cd){console[_0x12ae74(0x49f)](_0x12ae74(0x2f5),_0x3ac9cd);}}[_0x12575b(0x33c)](){const _0x3daa61=_0x12575b,_0x58569e=document[_0x3daa61(0x43c)](_0x3daa61(0x383)),_0x336d73=_0x58569e[_0x3daa61(0x30d)]||0x12c;this['tileSizeWithGap']=(_0x336d73-0.5*(this['width']-0x1))/this[_0x3daa61(0x473)];}[_0x12575b(0x26b)](_0x2fdfba){const _0x118992=_0x12575b;console[_0x118992(0x253)](_0x118992(0x1c1),_0x2fdfba);var _0x98d0c0,_0x37d5d7,_0x4dc2ca,_0x39b554=_0x118992(0x3e2),_0x59014b=![],_0x73f6d2='image';console['log'](_0x118992(0x422),_0x2fdfba[_0x118992(0x431)]);const _0x41c4a9=themes[_0x118992(0x3a1)](_0x4cf389=>_0x4cf389[_0x118992(0x296)])['find'](_0x3dafd0=>_0x3dafd0['value']===this[_0x118992(0x404)]),_0x30af5e=_0x41c4a9?.[_0x118992(0x1f8)]||_0x118992(0x3e9),_0x4dea57=['mov','mp4'],_0x54a336=_0x118992(0x1c6);if(_0x2fdfba[_0x118992(0x431)])_0x37d5d7=_0x2fdfba[_0x118992(0x431)],_0x4dc2ca=_0x2fdfba['fallbackUrl']||_0x118992(0x204),_0x39b554=_0x2fdfba[_0x118992(0x2fd)]||_0x118992(0x34d);else{if(_0x2fdfba[_0x118992(0x305)]&&_0x2fdfba[_0x118992(0x355)]){_0x59014b=!![];var _0x57606a={'orientation':'Right','ipfsPrefix':_0x41c4a9?.[_0x118992(0x450)]||_0x54a336};if(_0x41c4a9&&_0x41c4a9[_0x118992(0x1f1)]){var _0x3a4d92=_0x41c4a9[_0x118992(0x1f1)][_0x118992(0x362)](',')[_0x118992(0x3cd)](_0x5a8dd0=>_0x5a8dd0[_0x118992(0x352)]()),_0x4b5475=_0x41c4a9[_0x118992(0x212)]?_0x41c4a9[_0x118992(0x212)][_0x118992(0x362)](',')[_0x118992(0x3cd)](_0x10b2d4=>_0x10b2d4['trim']()):[],_0x51363f=_0x41c4a9[_0x118992(0x450)]?_0x41c4a9['ipfsPrefixes'][_0x118992(0x362)](',')[_0x118992(0x3cd)](_0x415d1a=>_0x415d1a[_0x118992(0x352)]()):[_0x54a336],_0x5ea8f4=_0x3a4d92['indexOf'](_0x2fdfba[_0x118992(0x355)]);_0x5ea8f4!==-0x1&&(_0x57606a={'orientation':_0x4b5475[_0x118992(0x3eb)]===0x1?_0x4b5475[0x0]:_0x4b5475[_0x5ea8f4]||_0x118992(0x34d),'ipfsPrefix':_0x51363f[_0x118992(0x3eb)]===0x1?_0x51363f[0x0]:_0x51363f[_0x5ea8f4]||_0x54a336});}_0x57606a[_0x118992(0x2fd)]==='Random'?_0x39b554=Math[_0x118992(0x47e)]()<0.5?_0x118992(0x3e2):'Right':_0x39b554=_0x57606a[_0x118992(0x2fd)];_0x37d5d7=_0x57606a[_0x118992(0x3b2)]+_0x2fdfba[_0x118992(0x305)],_0x4dc2ca=_0x54a336+_0x2fdfba[_0x118992(0x305)];const _0x984a3a=_0x37d5d7[_0x118992(0x362)]('.')[_0x118992(0x3bd)]()[_0x118992(0x260)]();_0x4dea57[_0x118992(0x3d1)](_0x984a3a)&&(_0x73f6d2=_0x118992(0x22f));}else{switch(_0x2fdfba[_0x118992(0x386)]){case'Base':_0x98d0c0=_0x118992(0x1dd);break;case _0x118992(0x44e):_0x98d0c0=_0x118992(0x3fa);break;case _0x118992(0x3cc):_0x98d0c0=_0x118992(0x3f0);break;default:_0x98d0c0=_0x118992(0x1dd);}_0x37d5d7=this['baseImagePath']+_0x98d0c0+'/'+_0x2fdfba['name'][_0x118992(0x260)]()['replace'](/ /g,'-')+'.'+_0x30af5e,_0x4dc2ca='icons/skull.png',_0x39b554=characterDirections[_0x2fdfba[_0x118992(0x266)]]||_0x118992(0x3e2),_0x4dea57['includes'](_0x30af5e['toLowerCase']())&&(_0x73f6d2=_0x118992(0x22f));}}var _0x52c5c6;switch(_0x2fdfba['type']){case _0x118992(0x44e):_0x52c5c6=0x64;break;case _0x118992(0x3cc):_0x52c5c6=0x46;break;case _0x118992(0x268):default:_0x52c5c6=0x55;}var _0x5f1380=0x1,_0x3aa0ef=0x0;switch(_0x2fdfba['size']){case'Large':_0x5f1380=1.2,_0x3aa0ef=_0x2fdfba[_0x118992(0x232)]>0x1?-0x2:0x0;break;case _0x118992(0x205):_0x5f1380=0.8,_0x3aa0ef=_0x2fdfba[_0x118992(0x232)]<0x6?0x2:0x7-_0x2fdfba[_0x118992(0x232)];break;case _0x118992(0x41d):_0x5f1380=0x1,_0x3aa0ef=0x0;break;}var _0xf49548=Math[_0x118992(0x319)](_0x52c5c6*_0x5f1380),_0x408a75=Math['max'](0x1,Math['min'](0x7,_0x2fdfba[_0x118992(0x232)]+_0x3aa0ef));return{'name':_0x2fdfba[_0x118992(0x266)],'type':_0x2fdfba['type'],'strength':_0x2fdfba['strength'],'speed':_0x2fdfba['speed'],'tactics':_0x408a75,'size':_0x2fdfba[_0x118992(0x3ee)],'powerup':_0x2fdfba[_0x118992(0x3ef)],'health':_0xf49548,'maxHealth':_0xf49548,'boostActive':![],'boostValue':0x0,'lastStandActive':![],'imageUrl':_0x37d5d7,'fallbackUrl':_0x4dc2ca,'orientation':_0x39b554,'isNFT':_0x59014b,'mediaType':_0x73f6d2};}['flipCharacter'](_0x9998bf,_0x35f9d0,_0x519819=![]){const _0x2deeac=_0x12575b;_0x9998bf[_0x2deeac(0x2fd)]==='Left'?(_0x9998bf['orientation']='Right',_0x35f9d0[_0x2deeac(0x3ff)][_0x2deeac(0x2a4)]=_0x519819?_0x2deeac(0x491):'none'):(_0x9998bf[_0x2deeac(0x2fd)]=_0x2deeac(0x3e2),_0x35f9d0[_0x2deeac(0x3ff)]['transform']=_0x519819?_0x2deeac(0x396):_0x2deeac(0x491)),log(_0x9998bf[_0x2deeac(0x266)]+'\x27s\x20orientation\x20flipped\x20to\x20'+_0x9998bf[_0x2deeac(0x2fd)]+'!');}[_0x12575b(0x2ae)](_0x500b38){const _0x1737aa=_0x12575b;console['time'](_0x1737aa(0x2ae));const _0x5dfb2b=document['getElementById'](_0x1737aa(0x3a2)),_0x3a0b2e=document[_0x1737aa(0x43c)](_0x1737aa(0x26a));_0x3a0b2e[_0x1737aa(0x20b)]='',_0x5dfb2b[_0x1737aa(0x3ff)][_0x1737aa(0x2c6)]='block';const _0x37944e=document['getElementById'](_0x1737aa(0x464));this[_0x1737aa(0x2e9)]&&window[_0x1737aa(0x306)]?(_0x37944e[_0x1737aa(0x3ff)][_0x1737aa(0x2c6)]='inline-block',_0x37944e['onclick']=()=>{const _0x1138b7=_0x1737aa;_0x5dfb2b['style'][_0x1138b7(0x2c6)]=_0x1138b7(0x396),showBossSelect(this);}):_0x37944e[_0x1737aa(0x3ff)][_0x1737aa(0x2c6)]='none';if(!this[_0x1737aa(0x46d)]||this[_0x1737aa(0x46d)][_0x1737aa(0x3eb)]===0x0){console[_0x1737aa(0x21f)](_0x1737aa(0x2d3)),_0x3a0b2e[_0x1737aa(0x20b)]=_0x1737aa(0x2db),console[_0x1737aa(0x1d4)](_0x1737aa(0x2ae));return;}document[_0x1737aa(0x43c)](_0x1737aa(0x301))[_0x1737aa(0x34e)]=()=>{showThemeSelect(this);};const _0x163c6a=document['createDocumentFragment']();this[_0x1737aa(0x46d)]['forEach'](_0x2384bf=>{const _0x2fa0f4=_0x1737aa,_0x4fe929=document[_0x2fa0f4(0x3a8)](_0x2fa0f4(0x3d0));_0x4fe929['className']='character-option',_0x4fe929[_0x2fa0f4(0x20b)]=_0x2384bf['mediaType']==='video'?_0x2fa0f4(0x368)+_0x2384bf[_0x2fa0f4(0x431)]+_0x2fa0f4(0x37f)+_0x2384bf[_0x2fa0f4(0x266)]+'\x22\x20onerror=\x22this.src=\x27'+_0x2384bf['fallbackUrl']+_0x2fa0f4(0x23c)+('<p><strong>'+_0x2384bf[_0x2fa0f4(0x266)]+_0x2fa0f4(0x28b))+(_0x2fa0f4(0x3ae)+_0x2384bf[_0x2fa0f4(0x386)]+_0x2fa0f4(0x3e5))+(_0x2fa0f4(0x3b7)+_0x2384bf[_0x2fa0f4(0x1da)]+_0x2fa0f4(0x3e5))+('<p>Strength:\x20'+_0x2384bf['strength']+_0x2fa0f4(0x3e5))+('<p>Speed:\x20'+_0x2384bf[_0x2fa0f4(0x3e3)]+_0x2fa0f4(0x3e5))+(_0x2fa0f4(0x1ce)+_0x2384bf[_0x2fa0f4(0x232)]+'</p>')+(_0x2fa0f4(0x498)+_0x2384bf[_0x2fa0f4(0x3ee)]+_0x2fa0f4(0x3e5))+(_0x2fa0f4(0x21a)+_0x2384bf[_0x2fa0f4(0x3ef)]+_0x2fa0f4(0x3e5)):_0x2fa0f4(0x312)+_0x2384bf['imageUrl']+'\x22\x20alt=\x22'+_0x2384bf[_0x2fa0f4(0x266)]+_0x2fa0f4(0x2b8)+_0x2384bf['fallbackUrl']+'\x27\x22>'+(_0x2fa0f4(0x48e)+_0x2384bf[_0x2fa0f4(0x266)]+'</strong></p>')+('<p>Type:\x20'+_0x2384bf[_0x2fa0f4(0x386)]+_0x2fa0f4(0x3e5))+(_0x2fa0f4(0x3b7)+_0x2384bf[_0x2fa0f4(0x1da)]+_0x2fa0f4(0x3e5))+('<p>Strength:\x20'+_0x2384bf[_0x2fa0f4(0x3a4)]+_0x2fa0f4(0x3e5))+(_0x2fa0f4(0x3c5)+_0x2384bf['speed']+_0x2fa0f4(0x3e5))+(_0x2fa0f4(0x1ce)+_0x2384bf['tactics']+_0x2fa0f4(0x3e5))+(_0x2fa0f4(0x498)+_0x2384bf[_0x2fa0f4(0x3ee)]+_0x2fa0f4(0x3e5))+(_0x2fa0f4(0x21a)+_0x2384bf[_0x2fa0f4(0x3ef)]+_0x2fa0f4(0x3e5)),_0x4fe929[_0x2fa0f4(0x27c)](_0x2fa0f4(0x382),()=>{const _0x9f41a4=_0x2fa0f4;console[_0x9f41a4(0x253)](_0x9f41a4(0x1e8)+_0x2384bf['name']),console[_0x9f41a4(0x253)](_0x9f41a4(0x320),this[_0x9f41a4(0x2e9)]),_0x5dfb2b[_0x9f41a4(0x3ff)][_0x9f41a4(0x2c6)]=_0x9f41a4(0x396),_0x500b38?(console[_0x9f41a4(0x253)](_0x9f41a4(0x3b1)),this['selectedBoss']?(console[_0x9f41a4(0x253)]('showCharacterSelect:\x20Boss\x20battle\x20initial\x20-\x20calling\x20setSelectedCharacter'),this[_0x9f41a4(0x1cc)](_0x2384bf)):(console[_0x9f41a4(0x253)](_0x9f41a4(0x389)),this[_0x9f41a4(0x1ca)]={..._0x2384bf},console[_0x9f41a4(0x253)](_0x9f41a4(0x2da)+this[_0x9f41a4(0x1ca)]['name']),this[_0x9f41a4(0x283)]())):(console['log'](_0x9f41a4(0x3c8)),this[_0x9f41a4(0x22d)](_0x2384bf));}),_0x163c6a[_0x2fa0f4(0x4b7)](_0x4fe929);}),_0x3a0b2e[_0x1737aa(0x4b7)](_0x163c6a),console['log']('showCharacterSelect:\x20Rendered\x20'+this['playerCharacters'][_0x1737aa(0x3eb)]+_0x1737aa(0x29e)),console['timeEnd'](_0x1737aa(0x2ae));}['swapPlayerCharacter'](_0x344a61){const _0x504770=_0x12575b,_0x29ba20=this['player1']['health'],_0x27113c=this['player1'][_0x504770(0x1da)],_0x9bf96a={..._0x344a61},_0x4af4fd=Math[_0x504770(0x278)](0x1,_0x29ba20/_0x27113c);_0x9bf96a[_0x504770(0x39e)]=Math[_0x504770(0x319)](_0x9bf96a['maxHealth']*_0x4af4fd),_0x9bf96a['health']=Math[_0x504770(0x42d)](0x0,Math['min'](_0x9bf96a['maxHealth'],_0x9bf96a[_0x504770(0x39e)])),_0x9bf96a[_0x504770(0x209)]=![],_0x9bf96a[_0x504770(0x49c)]=0x0,_0x9bf96a['lastStandActive']=![],this[_0x504770(0x1ca)]=_0x9bf96a,this[_0x504770(0x38f)](),this[_0x504770(0x24a)](this[_0x504770(0x1ca)]),log(this[_0x504770(0x1ca)]['name']+_0x504770(0x493)+this[_0x504770(0x1ca)][_0x504770(0x39e)]+'/'+this[_0x504770(0x1ca)][_0x504770(0x1da)]+_0x504770(0x284)),this[_0x504770(0x31c)]=this[_0x504770(0x1ca)][_0x504770(0x3e3)]>this[_0x504770(0x385)][_0x504770(0x3e3)]?this[_0x504770(0x1ca)]:this[_0x504770(0x385)]['speed']>this[_0x504770(0x1ca)][_0x504770(0x3e3)]?this['player2']:this[_0x504770(0x1ca)][_0x504770(0x3a4)]>=this[_0x504770(0x385)][_0x504770(0x3a4)]?this['player1']:this[_0x504770(0x385)],turnIndicator[_0x504770(0x1c2)]=_0x504770(0x2fc)+this[_0x504770(0x240)]+_0x504770(0x427)+(this[_0x504770(0x31c)]===this[_0x504770(0x1ca)]?_0x504770(0x403):_0x504770(0x39c))+_0x504770(0x2f2),this[_0x504770(0x31c)]===this['player2']&&this[_0x504770(0x499)]!=='gameOver'&&setTimeout(()=>this['aiTurn'](),0x3e8);}[_0x12575b(0x24c)](_0x39aba9,_0x5374b6){const _0x57115b=_0x12575b;return console[_0x57115b(0x253)](_0x57115b(0x363)+_0x39aba9+',\x20score='+_0x5374b6),new Promise(_0x215b63=>{const _0x58fbc0=_0x57115b,_0x8ac082=document[_0x58fbc0(0x3a8)](_0x58fbc0(0x3d0));_0x8ac082['id']=_0x58fbc0(0x3ac),_0x8ac082['className']=_0x58fbc0(0x3ac);const _0x5eb428=document[_0x58fbc0(0x3a8)](_0x58fbc0(0x3d0));_0x5eb428[_0x58fbc0(0x43b)]=_0x58fbc0(0x39a);const _0x440ff6=document[_0x58fbc0(0x3a8)]('p');_0x440ff6['id']=_0x58fbc0(0x313),_0x440ff6['textContent']='Resume\x20from\x20Level\x20'+_0x39aba9+_0x58fbc0(0x3c4)+_0x5374b6+'?',_0x5eb428[_0x58fbc0(0x4b7)](_0x440ff6);const _0x3de250=document['createElement'](_0x58fbc0(0x3d0));_0x3de250[_0x58fbc0(0x43b)]=_0x58fbc0(0x294);const _0x3567e1=document[_0x58fbc0(0x3a8)]('button');_0x3567e1['id']=_0x58fbc0(0x2e7),_0x3567e1[_0x58fbc0(0x1c2)]=_0x58fbc0(0x250),_0x3de250['appendChild'](_0x3567e1);const _0x39e464=document[_0x58fbc0(0x3a8)](_0x58fbc0(0x2c3));_0x39e464['id']=_0x58fbc0(0x425),_0x39e464[_0x58fbc0(0x1c2)]=_0x58fbc0(0x463),_0x3de250['appendChild'](_0x39e464),_0x5eb428['appendChild'](_0x3de250),_0x8ac082[_0x58fbc0(0x4b7)](_0x5eb428),document[_0x58fbc0(0x245)][_0x58fbc0(0x4b7)](_0x8ac082),_0x8ac082[_0x58fbc0(0x3ff)][_0x58fbc0(0x2c6)]=_0x58fbc0(0x3a7);const _0x57f6b1=()=>{const _0x4d966d=_0x58fbc0;console[_0x4d966d(0x253)](_0x4d966d(0x37e)),_0x8ac082[_0x4d966d(0x3ff)][_0x4d966d(0x2c6)]=_0x4d966d(0x396),document[_0x4d966d(0x245)][_0x4d966d(0x331)](_0x8ac082),_0x3567e1[_0x4d966d(0x2dc)]('click',_0x57f6b1),_0x39e464[_0x4d966d(0x2dc)](_0x4d966d(0x382),_0x1ba726),_0x215b63(!![]);},_0x1ba726=()=>{const _0x5370e3=_0x58fbc0;console['log'](_0x5370e3(0x287)),_0x8ac082[_0x5370e3(0x3ff)][_0x5370e3(0x2c6)]=_0x5370e3(0x396),document[_0x5370e3(0x245)][_0x5370e3(0x331)](_0x8ac082),_0x3567e1[_0x5370e3(0x2dc)](_0x5370e3(0x382),_0x57f6b1),_0x39e464['removeEventListener']('click',_0x1ba726),_0x215b63(![]);};_0x3567e1['addEventListener']('click',_0x57f6b1),_0x39e464[_0x58fbc0(0x27c)](_0x58fbc0(0x382),_0x1ba726);});}[_0x12575b(0x283)](){const _0x483136=_0x12575b;var _0x2447fe=this;console['log'](_0x483136(0x34f)+this['currentLevel']),this[_0x483136(0x2e9)]=null,this[_0x483136(0x292)]=null,console[_0x483136(0x253)](_0x483136(0x311));var _0x5b1386=document[_0x483136(0x4a4)](_0x483136(0x2c7)),_0x584db6=document[_0x483136(0x43c)](_0x483136(0x383));_0x5b1386[_0x483136(0x3ff)][_0x483136(0x2c6)]=_0x483136(0x3df),_0x584db6[_0x483136(0x3ff)][_0x483136(0x288)]='visible',this[_0x483136(0x47b)](),this[_0x483136(0x3c3)][_0x483136(0x1ea)]['play'](),log(_0x483136(0x456)+this[_0x483136(0x240)]+_0x483136(0x466)),this[_0x483136(0x385)]=this[_0x483136(0x26b)](opponentsConfig[this[_0x483136(0x240)]-0x1]),console[_0x483136(0x253)](_0x483136(0x354)+this[_0x483136(0x240)]+':\x20'+this[_0x483136(0x385)][_0x483136(0x266)]+_0x483136(0x40b)+(this[_0x483136(0x240)]-0x1)+'])'),this[_0x483136(0x1ca)][_0x483136(0x39e)]=this[_0x483136(0x1ca)]['maxHealth'],this['currentTurn']=this['player1']['speed']>this[_0x483136(0x385)][_0x483136(0x3e3)]?this[_0x483136(0x1ca)]:this[_0x483136(0x385)][_0x483136(0x3e3)]>this['player1'][_0x483136(0x3e3)]?this[_0x483136(0x385)]:this[_0x483136(0x1ca)][_0x483136(0x3a4)]>=this[_0x483136(0x385)][_0x483136(0x3a4)]?this[_0x483136(0x1ca)]:this[_0x483136(0x385)],this[_0x483136(0x499)]=_0x483136(0x2f0),this[_0x483136(0x254)]=![],this['roundStats']=[];const _0x1c0b7b=document['getElementById'](_0x483136(0x265)),_0x58c699=document[_0x483136(0x43c)](_0x483136(0x381));if(_0x1c0b7b)_0x1c0b7b['classList'][_0x483136(0x37a)]('winner',_0x483136(0x233));if(_0x58c699)_0x58c699[_0x483136(0x3fd)][_0x483136(0x37a)](_0x483136(0x2b5),_0x483136(0x233));this[_0x483136(0x38f)](),this[_0x483136(0x1d0)]();if(_0x1c0b7b)_0x1c0b7b['style'][_0x483136(0x2a4)]=this['player1'][_0x483136(0x2fd)]===_0x483136(0x3e2)?'scaleX(-1)':_0x483136(0x396);if(_0x58c699)_0x58c699[_0x483136(0x3ff)][_0x483136(0x2a4)]=this['player2'][_0x483136(0x2fd)]===_0x483136(0x34d)?_0x483136(0x491):_0x483136(0x396);this['updateHealth'](this[_0x483136(0x1ca)]),this['updateHealth'](this['player2']),battleLog[_0x483136(0x20b)]='',gameOver[_0x483136(0x1c2)]='',this[_0x483136(0x273)](![]),this[_0x483136(0x1ca)][_0x483136(0x3ee)]!=='Medium'&&log(this[_0x483136(0x1ca)][_0x483136(0x266)]+_0x483136(0x37d)+this['player1']['size']+_0x483136(0x3de)+(this[_0x483136(0x1ca)][_0x483136(0x3ee)]===_0x483136(0x2d7)?_0x483136(0x41a)+this[_0x483136(0x1ca)]['maxHealth']+'\x20but\x20dulls\x20tactics\x20to\x20'+this['player1']['tactics']:'drops\x20health\x20to\x20'+this['player1']['maxHealth']+_0x483136(0x1f0)+this[_0x483136(0x1ca)][_0x483136(0x232)])+'!'),this[_0x483136(0x385)]['size']!==_0x483136(0x41d)&&log(this[_0x483136(0x385)][_0x483136(0x266)]+'\x27s\x20'+this['player2'][_0x483136(0x3ee)]+_0x483136(0x3de)+(this[_0x483136(0x385)][_0x483136(0x3ee)]===_0x483136(0x2d7)?_0x483136(0x41a)+this[_0x483136(0x385)][_0x483136(0x1da)]+_0x483136(0x405)+this[_0x483136(0x385)][_0x483136(0x232)]:_0x483136(0x32f)+this[_0x483136(0x385)][_0x483136(0x1da)]+'\x20but\x20sharpens\x20tactics\x20to\x20'+this[_0x483136(0x385)][_0x483136(0x232)])+'!'),log(this[_0x483136(0x1ca)][_0x483136(0x266)]+_0x483136(0x2b9)+this[_0x483136(0x1ca)]['health']+'/'+this[_0x483136(0x1ca)]['maxHealth']+_0x483136(0x284)),log(this[_0x483136(0x31c)]['name']+_0x483136(0x1ff)),this[_0x483136(0x3c6)](),this['gameState']=this[_0x483136(0x31c)]===this[_0x483136(0x1ca)]?_0x483136(0x2fb):_0x483136(0x23d),turnIndicator['textContent']=_0x483136(0x2fc)+this[_0x483136(0x240)]+_0x483136(0x427)+(this[_0x483136(0x31c)]===this['player1']?_0x483136(0x403):'Opponent')+_0x483136(0x2f2),this[_0x483136(0x31c)]===this[_0x483136(0x385)]&&setTimeout(function(){const _0x42c517=_0x483136;_0x2447fe[_0x42c517(0x23d)]();},0x3e8);}[_0x12575b(0x38f)](){const _0x59bba6=_0x12575b;p1Name['textContent']=this['player1'][_0x59bba6(0x3d2)]||this[_0x59bba6(0x404)]==='monstrocity'?this['player1'][_0x59bba6(0x266)]:_0x59bba6(0x2a7),p1Type[_0x59bba6(0x1c2)]=this[_0x59bba6(0x1ca)][_0x59bba6(0x386)],p1Strength[_0x59bba6(0x1c2)]=this[_0x59bba6(0x1ca)][_0x59bba6(0x3a4)],p1Speed[_0x59bba6(0x1c2)]=this['player1'][_0x59bba6(0x3e3)],p1Tactics[_0x59bba6(0x1c2)]=this[_0x59bba6(0x1ca)]['tactics'],p1Size['textContent']=this['player1']['size'],p1Powerup[_0x59bba6(0x1c2)]=this[_0x59bba6(0x1ca)][_0x59bba6(0x3ef)];const _0x2c37ad=document['getElementById'](_0x59bba6(0x265)),_0x3e7615=_0x2c37ad['parentNode'];if(this['player1'][_0x59bba6(0x44d)]===_0x59bba6(0x22f)){if(_0x2c37ad[_0x59bba6(0x2af)]!=='VIDEO'){const _0x588910=document[_0x59bba6(0x3a8)](_0x59bba6(0x22f));_0x588910['id']=_0x59bba6(0x265),_0x588910['src']=this[_0x59bba6(0x1ca)][_0x59bba6(0x431)],_0x588910['autoplay']=!![],_0x588910[_0x59bba6(0x472)]=!![],_0x588910[_0x59bba6(0x2be)]=!![],_0x588910[_0x59bba6(0x437)]=this[_0x59bba6(0x1ca)][_0x59bba6(0x266)],_0x588910[_0x59bba6(0x4a2)]=()=>{const _0x13b10c=_0x59bba6;_0x588910['src']=this[_0x13b10c(0x1ca)][_0x13b10c(0x48b)];},_0x3e7615[_0x59bba6(0x2c4)](_0x588910,_0x2c37ad);}else _0x2c37ad[_0x59bba6(0x2ed)]=this[_0x59bba6(0x1ca)]['imageUrl'],_0x2c37ad[_0x59bba6(0x4a2)]=()=>{const _0xbfeb9=_0x59bba6;_0x2c37ad[_0xbfeb9(0x2ed)]=this[_0xbfeb9(0x1ca)][_0xbfeb9(0x48b)];};}else{if(_0x2c37ad['tagName']!==_0x59bba6(0x277)){const _0x26713c=document[_0x59bba6(0x3a8)](_0x59bba6(0x4b6));_0x26713c['id']=_0x59bba6(0x265),_0x26713c[_0x59bba6(0x2ed)]=this[_0x59bba6(0x1ca)][_0x59bba6(0x431)],_0x26713c[_0x59bba6(0x437)]=this[_0x59bba6(0x1ca)]['name'],_0x26713c[_0x59bba6(0x4a2)]=()=>{const _0x1c997d=_0x59bba6;_0x26713c[_0x1c997d(0x2ed)]=this['player1'][_0x1c997d(0x48b)];},_0x3e7615[_0x59bba6(0x2c4)](_0x26713c,_0x2c37ad);}else _0x2c37ad[_0x59bba6(0x2ed)]=this[_0x59bba6(0x1ca)][_0x59bba6(0x431)],_0x2c37ad[_0x59bba6(0x4a2)]=()=>{const _0x5d6591=_0x59bba6;_0x2c37ad['src']=this[_0x5d6591(0x1ca)]['fallbackUrl'];};}const _0x5b2022=document[_0x59bba6(0x43c)](_0x59bba6(0x265));_0x5b2022[_0x59bba6(0x3ff)][_0x59bba6(0x2a4)]=this[_0x59bba6(0x1ca)][_0x59bba6(0x2fd)]===_0x59bba6(0x3e2)?_0x59bba6(0x491):_0x59bba6(0x396),_0x5b2022[_0x59bba6(0x2af)]===_0x59bba6(0x277)?_0x5b2022['onload']=function(){const _0x1ea303=_0x59bba6;_0x5b2022[_0x1ea303(0x3ff)][_0x1ea303(0x2c6)]=_0x1ea303(0x3df);}:_0x5b2022[_0x59bba6(0x3ff)][_0x59bba6(0x2c6)]=_0x59bba6(0x3df),p1Hp[_0x59bba6(0x1c2)]=this['player1']['health']+'/'+this[_0x59bba6(0x1ca)][_0x59bba6(0x1da)],_0x5b2022[_0x59bba6(0x34e)]=()=>{const _0x1d516e=_0x59bba6;console[_0x1d516e(0x253)](_0x1d516e(0x269)),this[_0x1d516e(0x2ae)](![]);};}[_0x12575b(0x1d0)](){const _0x5282f2=_0x12575b;p2Name[_0x5282f2(0x1c2)]=this[_0x5282f2(0x385)][_0x5282f2(0x3c7)]?this[_0x5282f2(0x385)][_0x5282f2(0x266)]:this['theme']===_0x5282f2(0x38d)?this[_0x5282f2(0x385)][_0x5282f2(0x266)]:_0x5282f2(0x228),p2Type['textContent']=this[_0x5282f2(0x385)]['type'],p2Strength['textContent']=this[_0x5282f2(0x385)][_0x5282f2(0x3a4)],p2Speed['textContent']=this[_0x5282f2(0x385)][_0x5282f2(0x3e3)],p2Tactics[_0x5282f2(0x1c2)]=this[_0x5282f2(0x385)][_0x5282f2(0x232)],p2Size[_0x5282f2(0x1c2)]=this[_0x5282f2(0x385)][_0x5282f2(0x3ee)],p2Powerup[_0x5282f2(0x1c2)]=this[_0x5282f2(0x385)][_0x5282f2(0x3ef)];const _0x1ef2e6=document['getElementById'](_0x5282f2(0x381)),_0x570b83=_0x1ef2e6['parentNode'];if(this['player2'][_0x5282f2(0x44d)]===_0x5282f2(0x22f)){if(_0x1ef2e6[_0x5282f2(0x2af)]!==_0x5282f2(0x465)){const _0x34299c=document[_0x5282f2(0x3a8)](_0x5282f2(0x22f));_0x34299c['id']=_0x5282f2(0x381),_0x34299c[_0x5282f2(0x2ed)]=this['player2'][_0x5282f2(0x431)],_0x34299c[_0x5282f2(0x30a)]=!![],_0x34299c[_0x5282f2(0x472)]=!![],_0x34299c['muted']=!![],_0x34299c[_0x5282f2(0x437)]=this[_0x5282f2(0x385)][_0x5282f2(0x266)],_0x570b83[_0x5282f2(0x2c4)](_0x34299c,_0x1ef2e6);}else _0x1ef2e6[_0x5282f2(0x2ed)]=this[_0x5282f2(0x385)][_0x5282f2(0x431)];}else{if(_0x1ef2e6[_0x5282f2(0x2af)]!==_0x5282f2(0x277)){const _0x57e5b5=document['createElement'](_0x5282f2(0x4b6));_0x57e5b5['id']='p2-image',_0x57e5b5[_0x5282f2(0x2ed)]=this['player2'][_0x5282f2(0x431)],_0x57e5b5[_0x5282f2(0x437)]=this[_0x5282f2(0x385)][_0x5282f2(0x266)],_0x570b83[_0x5282f2(0x2c4)](_0x57e5b5,_0x1ef2e6);}else _0x1ef2e6[_0x5282f2(0x2ed)]=this[_0x5282f2(0x385)][_0x5282f2(0x431)];}const _0x607f74=document[_0x5282f2(0x43c)]('p2-image');_0x607f74[_0x5282f2(0x3ff)][_0x5282f2(0x2a4)]=this[_0x5282f2(0x385)][_0x5282f2(0x2fd)]==='Right'?_0x5282f2(0x491):_0x5282f2(0x396),_0x607f74[_0x5282f2(0x2af)]==='IMG'?_0x607f74[_0x5282f2(0x4b8)]=function(){const _0x383e0e=_0x5282f2;_0x607f74[_0x383e0e(0x3ff)][_0x383e0e(0x2c6)]='block';}:_0x607f74[_0x5282f2(0x3ff)][_0x5282f2(0x2c6)]=_0x5282f2(0x3df),p2Hp[_0x5282f2(0x1c2)]=this[_0x5282f2(0x385)][_0x5282f2(0x39e)]+'/'+this['player2'][_0x5282f2(0x1da)],_0x607f74[_0x5282f2(0x3fd)][_0x5282f2(0x37a)]('winner',_0x5282f2(0x233));}[_0x12575b(0x3c6)](){const _0x1e5b8a=_0x12575b;if(this[_0x1e5b8a(0x2e9)]){console['log']('initBoard:\x20Attempting\x20to\x20load\x20board\x20state\x20for\x20boss\x20battle');const _0x2cba5f=this[_0x1e5b8a(0x262)]();if(_0x2cba5f)this[_0x1e5b8a(0x2d1)]=_0x2cba5f,console[_0x1e5b8a(0x253)](_0x1e5b8a(0x23e));else{this[_0x1e5b8a(0x2d1)]=[];for(let _0x1cd30c=0x0;_0x1cd30c<this[_0x1e5b8a(0x2ca)];_0x1cd30c++){this['board'][_0x1cd30c]=[];for(let _0x39cf32=0x0;_0x39cf32<this[_0x1e5b8a(0x473)];_0x39cf32++){let _0x4e289f;do{_0x4e289f=this['createRandomTile']();}while(_0x39cf32>=0x2&&this[_0x1e5b8a(0x2d1)][_0x1cd30c][_0x39cf32-0x1]?.[_0x1e5b8a(0x386)]===_0x4e289f[_0x1e5b8a(0x386)]&&this['board'][_0x1cd30c][_0x39cf32-0x2]?.[_0x1e5b8a(0x386)]===_0x4e289f['type']||_0x1cd30c>=0x2&&this[_0x1e5b8a(0x2d1)][_0x1cd30c-0x1]?.[_0x39cf32]?.[_0x1e5b8a(0x386)]===_0x4e289f['type']&&this[_0x1e5b8a(0x2d1)][_0x1cd30c-0x2]?.[_0x39cf32]?.[_0x1e5b8a(0x386)]===_0x4e289f['type']);this[_0x1e5b8a(0x2d1)][_0x1cd30c][_0x39cf32]=_0x4e289f;}}console[_0x1e5b8a(0x253)]('initBoard:\x20Generated\x20new\x20board\x20for\x20boss\x20battle');}this[_0x1e5b8a(0x2f6)]();}else{const _0x37fbba=this['loadBoard']();if(_0x37fbba)this[_0x1e5b8a(0x2d1)]=_0x37fbba,console[_0x1e5b8a(0x253)](_0x1e5b8a(0x365),this['currentLevel']);else{this['board']=[];for(let _0x5c5306=0x0;_0x5c5306<this[_0x1e5b8a(0x2ca)];_0x5c5306++){this[_0x1e5b8a(0x2d1)][_0x5c5306]=[];for(let _0x3c65de=0x0;_0x3c65de<this[_0x1e5b8a(0x473)];_0x3c65de++){let _0x36d330;do{_0x36d330=this[_0x1e5b8a(0x39b)]();}while(_0x3c65de>=0x2&&this[_0x1e5b8a(0x2d1)][_0x5c5306][_0x3c65de-0x1]?.[_0x1e5b8a(0x386)]===_0x36d330[_0x1e5b8a(0x386)]&&this[_0x1e5b8a(0x2d1)][_0x5c5306][_0x3c65de-0x2]?.[_0x1e5b8a(0x386)]===_0x36d330[_0x1e5b8a(0x386)]||_0x5c5306>=0x2&&this['board'][_0x5c5306-0x1]?.[_0x3c65de]?.['type']===_0x36d330['type']&&this['board'][_0x5c5306-0x2]?.[_0x3c65de]?.['type']===_0x36d330[_0x1e5b8a(0x386)]);this[_0x1e5b8a(0x2d1)][_0x5c5306][_0x3c65de]=_0x36d330;}}this[_0x1e5b8a(0x2e8)](),console[_0x1e5b8a(0x253)](_0x1e5b8a(0x380),this['currentLevel']);}this['renderBoard']();}}[_0x12575b(0x39b)](){return{'type':randomChoice(this['tileTypes']),'element':null};}[_0x12575b(0x2f6)](){const _0x1443b3=_0x12575b;this['updateTileSizeWithGap']();const _0x2e6b04=document[_0x1443b3(0x43c)](_0x1443b3(0x383));_0x2e6b04[_0x1443b3(0x20b)]='';if(!this[_0x1443b3(0x2d1)]||!Array[_0x1443b3(0x208)](this[_0x1443b3(0x2d1)])||this[_0x1443b3(0x2d1)]['length']!==this['height']){console['warn']('renderBoard:\x20Board\x20not\x20initialized,\x20skipping\x20render');return;}for(let _0x212d9a=0x0;_0x212d9a<this[_0x1443b3(0x2ca)];_0x212d9a++){if(!Array['isArray'](this[_0x1443b3(0x2d1)][_0x212d9a])){console[_0x1443b3(0x21f)](_0x1443b3(0x40d)+_0x212d9a+'\x20is\x20not\x20an\x20array,\x20skipping');continue;}for(let _0x27822c=0x0;_0x27822c<this[_0x1443b3(0x473)];_0x27822c++){const _0x11494d=this[_0x1443b3(0x2d1)][_0x212d9a][_0x27822c];if(!_0x11494d||_0x11494d[_0x1443b3(0x386)]===null)continue;const _0x358dd0=document[_0x1443b3(0x3a8)]('div');_0x358dd0['className']='tile\x20'+_0x11494d[_0x1443b3(0x386)];if(this['gameOver'])_0x358dd0['classList'][_0x1443b3(0x2b3)](_0x1443b3(0x200));const _0xd26f60=document[_0x1443b3(0x3a8)]('img');_0xd26f60['src']=_0x1443b3(0x1c7)+_0x11494d['type']+'.png',_0xd26f60[_0x1443b3(0x437)]=_0x11494d[_0x1443b3(0x386)],_0x358dd0[_0x1443b3(0x4b7)](_0xd26f60),_0x358dd0[_0x1443b3(0x451)]['x']=_0x27822c,_0x358dd0['dataset']['y']=_0x212d9a,_0x2e6b04[_0x1443b3(0x4b7)](_0x358dd0),_0x11494d['element']=_0x358dd0,(!this[_0x1443b3(0x4b4)]||this[_0x1443b3(0x37b)]&&(this[_0x1443b3(0x37b)]['x']!==_0x27822c||this[_0x1443b3(0x37b)]['y']!==_0x212d9a))&&(_0x358dd0[_0x1443b3(0x3ff)][_0x1443b3(0x2a4)]='translate(0,\x200)'),this[_0x1443b3(0x471)]?_0x358dd0['addEventListener']('touchstart',_0x5e5995=>this['handleTouchStart'](_0x5e5995)):_0x358dd0[_0x1443b3(0x27c)](_0x1443b3(0x3ad),_0x43064d=>this['handleMouseDown'](_0x43064d));}}document[_0x1443b3(0x43c)](_0x1443b3(0x23b))['style'][_0x1443b3(0x2c6)]=this['gameOver']?'block':'none',console[_0x1443b3(0x253)](_0x1443b3(0x44a));}[_0x12575b(0x339)](){const _0x2902bf=_0x12575b,_0x5f2c33=document[_0x2902bf(0x43c)](_0x2902bf(0x383));this[_0x2902bf(0x471)]?(_0x5f2c33[_0x2902bf(0x27c)]('touchstart',_0x3c0685=>this[_0x2902bf(0x494)](_0x3c0685)),_0x5f2c33[_0x2902bf(0x27c)]('touchmove',_0x59ae29=>this[_0x2902bf(0x25b)](_0x59ae29)),_0x5f2c33['addEventListener'](_0x2902bf(0x32e),_0x5e3895=>this[_0x2902bf(0x406)](_0x5e3895))):(_0x5f2c33[_0x2902bf(0x27c)](_0x2902bf(0x3ad),_0x21b2a5=>this[_0x2902bf(0x325)](_0x21b2a5)),_0x5f2c33[_0x2902bf(0x27c)](_0x2902bf(0x3e0),_0x34af1b=>this[_0x2902bf(0x45f)](_0x34af1b)),_0x5f2c33[_0x2902bf(0x27c)](_0x2902bf(0x1cf),_0x3df04a=>this['handleMouseUp'](_0x3df04a)));document[_0x2902bf(0x43c)](_0x2902bf(0x285))[_0x2902bf(0x27c)]('click',()=>this[_0x2902bf(0x2d2)]()),document['getElementById'](_0x2902bf(0x469))[_0x2902bf(0x27c)](_0x2902bf(0x382),()=>{this['initGame']();});const _0x17cc1d=document[_0x2902bf(0x43c)]('refresh-board');_0x17cc1d[_0x2902bf(0x27c)](_0x2902bf(0x382),()=>{const _0x31f75e=_0x2902bf;console[_0x31f75e(0x253)](_0x31f75e(0x40a)),this[_0x31f75e(0x36d)]();});const _0x5b8ad2=document[_0x2902bf(0x43c)](_0x2902bf(0x48a)),_0x5c5bda=document[_0x2902bf(0x43c)](_0x2902bf(0x265)),_0xea74cd=document[_0x2902bf(0x43c)]('p2-image');_0x5b8ad2[_0x2902bf(0x27c)](_0x2902bf(0x382),()=>{const _0x1ea5f8=_0x2902bf;console[_0x1ea5f8(0x253)](_0x1ea5f8(0x2b7)),this[_0x1ea5f8(0x2ae)](![]);}),_0x5c5bda[_0x2902bf(0x27c)](_0x2902bf(0x382),()=>{const _0x4f1d8a=_0x2902bf;console[_0x4f1d8a(0x253)]('addEventListeners:\x20Player\x201\x20media\x20clicked'),this[_0x4f1d8a(0x2ae)](![]);}),document[_0x2902bf(0x43c)]('flip-p1')['addEventListener'](_0x2902bf(0x382),()=>this[_0x2902bf(0x38e)](this[_0x2902bf(0x1ca)],document[_0x2902bf(0x43c)](_0x2902bf(0x265)),![])),document[_0x2902bf(0x43c)](_0x2902bf(0x3f1))[_0x2902bf(0x27c)](_0x2902bf(0x382),()=>this[_0x2902bf(0x38e)](this[_0x2902bf(0x385)],document['getElementById'](_0x2902bf(0x381)),!![]));}[_0x12575b(0x2d2)](){const _0x2dfaef=_0x12575b;console[_0x2dfaef(0x253)]('handleGameOverButton\x20started:\x20currentLevel='+this['currentLevel']+',\x20player2.health='+this['player2'][_0x2dfaef(0x39e)]),this[_0x2dfaef(0x385)]['health']<=0x0&&this[_0x2dfaef(0x240)]>opponentsConfig[_0x2dfaef(0x3eb)]&&(this['currentLevel']=0x1,console[_0x2dfaef(0x253)](_0x2dfaef(0x3b9)+this['currentLevel'])),this[_0x2dfaef(0x283)](),console[_0x2dfaef(0x253)](_0x2dfaef(0x2e0)+this[_0x2dfaef(0x240)]);}[_0x12575b(0x325)](_0x5bbc06){const _0x24e9c7=_0x12575b;if(this[_0x24e9c7(0x254)]||this[_0x24e9c7(0x499)]!==_0x24e9c7(0x2fb)||this[_0x24e9c7(0x31c)]!==this['player1'])return;_0x5bbc06[_0x24e9c7(0x29a)]();const _0x124192=this[_0x24e9c7(0x2ee)](_0x5bbc06);if(!_0x124192||!_0x124192[_0x24e9c7(0x2cc)])return;this['isDragging']=!![],this[_0x24e9c7(0x37b)]={'x':_0x124192['x'],'y':_0x124192['y']},_0x124192[_0x24e9c7(0x2cc)]['classList']['add'](_0x24e9c7(0x3f6));const _0x4a502d=document[_0x24e9c7(0x43c)](_0x24e9c7(0x383))[_0x24e9c7(0x1de)]();this[_0x24e9c7(0x420)]=_0x5bbc06[_0x24e9c7(0x3d4)]-(_0x4a502d[_0x24e9c7(0x4b1)]+this['selectedTile']['x']*this[_0x24e9c7(0x222)]),this[_0x24e9c7(0x327)]=_0x5bbc06[_0x24e9c7(0x49d)]-(_0x4a502d[_0x24e9c7(0x40f)]+this[_0x24e9c7(0x37b)]['y']*this[_0x24e9c7(0x222)]);}[_0x12575b(0x45f)](_0x2f1592){const _0x40bf70=_0x12575b;if(!this[_0x40bf70(0x4b4)]||!this[_0x40bf70(0x37b)]||this[_0x40bf70(0x254)]||this[_0x40bf70(0x499)]!==_0x40bf70(0x2fb))return;_0x2f1592['preventDefault']();const _0xa545c8=document[_0x40bf70(0x43c)]('game-board')[_0x40bf70(0x1de)](),_0x4fb535=_0x2f1592[_0x40bf70(0x3d4)]-_0xa545c8[_0x40bf70(0x4b1)]-this['offsetX'],_0x4d2748=_0x2f1592[_0x40bf70(0x49d)]-_0xa545c8[_0x40bf70(0x40f)]-this[_0x40bf70(0x327)],_0x31ed6=this[_0x40bf70(0x2d1)][this[_0x40bf70(0x37b)]['y']][this[_0x40bf70(0x37b)]['x']]['element'];_0x31ed6[_0x40bf70(0x3ff)][_0x40bf70(0x442)]='';if(!this[_0x40bf70(0x31a)]){const _0x10dbc9=Math[_0x40bf70(0x348)](_0x4fb535-this[_0x40bf70(0x37b)]['x']*this[_0x40bf70(0x222)]),_0x517907=Math[_0x40bf70(0x348)](_0x4d2748-this['selectedTile']['y']*this['tileSizeWithGap']);if(_0x10dbc9>_0x517907&&_0x10dbc9>0x5)this['dragDirection']='row';else{if(_0x517907>_0x10dbc9&&_0x517907>0x5)this['dragDirection']=_0x40bf70(0x4a1);}}if(!this[_0x40bf70(0x31a)])return;if(this[_0x40bf70(0x31a)]===_0x40bf70(0x31d)){const _0x1b3133=Math[_0x40bf70(0x42d)](0x0,Math['min']((this[_0x40bf70(0x473)]-0x1)*this[_0x40bf70(0x222)],_0x4fb535));_0x31ed6['style'][_0x40bf70(0x2a4)]=_0x40bf70(0x424)+(_0x1b3133-this[_0x40bf70(0x37b)]['x']*this[_0x40bf70(0x222)])+_0x40bf70(0x2aa),this[_0x40bf70(0x1f9)]={'x':Math[_0x40bf70(0x319)](_0x1b3133/this[_0x40bf70(0x222)]),'y':this['selectedTile']['y']};}else{if(this[_0x40bf70(0x31a)]===_0x40bf70(0x4a1)){const _0xc54c=Math[_0x40bf70(0x42d)](0x0,Math[_0x40bf70(0x278)]((this[_0x40bf70(0x2ca)]-0x1)*this[_0x40bf70(0x222)],_0x4d2748));_0x31ed6[_0x40bf70(0x3ff)]['transform']='translate(0,\x20'+(_0xc54c-this[_0x40bf70(0x37b)]['y']*this[_0x40bf70(0x222)])+_0x40bf70(0x2d6),this[_0x40bf70(0x1f9)]={'x':this['selectedTile']['x'],'y':Math['round'](_0xc54c/this['tileSizeWithGap'])};}}}[_0x12575b(0x4a9)](_0x17134e){const _0x423158=_0x12575b;if(!this['isDragging']||!this[_0x423158(0x37b)]||!this[_0x423158(0x1f9)]||this[_0x423158(0x254)]||this[_0x423158(0x499)]!==_0x423158(0x2fb)){if(this[_0x423158(0x37b)]){const _0x282829=this[_0x423158(0x2d1)][this['selectedTile']['y']][this[_0x423158(0x37b)]['x']];if(_0x282829[_0x423158(0x2cc)])_0x282829[_0x423158(0x2cc)][_0x423158(0x3fd)][_0x423158(0x37a)]('selected');}this['isDragging']=![],this[_0x423158(0x37b)]=null,this[_0x423158(0x1f9)]=null,this[_0x423158(0x31a)]=null,this[_0x423158(0x2f6)]();return;}const _0x3b046d=this[_0x423158(0x2d1)][this[_0x423158(0x37b)]['y']][this['selectedTile']['x']];if(_0x3b046d[_0x423158(0x2cc)])_0x3b046d['element'][_0x423158(0x3fd)][_0x423158(0x37a)](_0x423158(0x3f6));this['slideTiles'](this[_0x423158(0x37b)]['x'],this[_0x423158(0x37b)]['y'],this[_0x423158(0x1f9)]['x'],this[_0x423158(0x1f9)]['y']),this[_0x423158(0x4b4)]=![],this[_0x423158(0x37b)]=null,this['targetTile']=null,this[_0x423158(0x31a)]=null;}[_0x12575b(0x494)](_0x196c31){const _0x2ea692=_0x12575b;if(this[_0x2ea692(0x254)]||this[_0x2ea692(0x499)]!==_0x2ea692(0x2fb)||this[_0x2ea692(0x31c)]!==this[_0x2ea692(0x1ca)])return;_0x196c31['preventDefault']();const _0x49ab76=this[_0x2ea692(0x2ee)](_0x196c31[_0x2ea692(0x43a)][0x0]);if(!_0x49ab76||!_0x49ab76[_0x2ea692(0x2cc)])return;this[_0x2ea692(0x4b4)]=!![],this['selectedTile']={'x':_0x49ab76['x'],'y':_0x49ab76['y']},_0x49ab76[_0x2ea692(0x2cc)][_0x2ea692(0x3fd)][_0x2ea692(0x2b3)](_0x2ea692(0x3f6));const _0x549b23=document[_0x2ea692(0x43c)](_0x2ea692(0x383))['getBoundingClientRect']();this[_0x2ea692(0x420)]=_0x196c31['touches'][0x0][_0x2ea692(0x3d4)]-(_0x549b23[_0x2ea692(0x4b1)]+this['selectedTile']['x']*this[_0x2ea692(0x222)]),this[_0x2ea692(0x327)]=_0x196c31[_0x2ea692(0x43a)][0x0]['clientY']-(_0x549b23[_0x2ea692(0x40f)]+this[_0x2ea692(0x37b)]['y']*this[_0x2ea692(0x222)]);}[_0x12575b(0x25b)](_0x269301){const _0x4acd66=_0x12575b;if(!this['isDragging']||!this['selectedTile']||this[_0x4acd66(0x254)]||this['gameState']!==_0x4acd66(0x2fb))return;_0x269301['preventDefault']();const _0x31221b=document['getElementById']('game-board')[_0x4acd66(0x1de)](),_0x42db71=_0x269301['touches'][0x0][_0x4acd66(0x3d4)]-_0x31221b['left']-this[_0x4acd66(0x420)],_0x59901d=_0x269301[_0x4acd66(0x43a)][0x0][_0x4acd66(0x49d)]-_0x31221b[_0x4acd66(0x40f)]-this[_0x4acd66(0x327)],_0x540eb6=this[_0x4acd66(0x2d1)][this[_0x4acd66(0x37b)]['y']][this['selectedTile']['x']][_0x4acd66(0x2cc)];requestAnimationFrame(()=>{const _0x9a3217=_0x4acd66;if(!this['dragDirection']){const _0x1a0675=Math[_0x9a3217(0x348)](_0x42db71-this[_0x9a3217(0x37b)]['x']*this[_0x9a3217(0x222)]),_0x3debcb=Math[_0x9a3217(0x348)](_0x59901d-this[_0x9a3217(0x37b)]['y']*this[_0x9a3217(0x222)]);if(_0x1a0675>_0x3debcb&&_0x1a0675>0x7)this[_0x9a3217(0x31a)]=_0x9a3217(0x31d);else{if(_0x3debcb>_0x1a0675&&_0x3debcb>0x7)this[_0x9a3217(0x31a)]=_0x9a3217(0x4a1);}}_0x540eb6[_0x9a3217(0x3ff)][_0x9a3217(0x442)]='';if(this[_0x9a3217(0x31a)]==='row'){const _0x49d98e=Math[_0x9a3217(0x42d)](0x0,Math[_0x9a3217(0x278)]((this[_0x9a3217(0x473)]-0x1)*this['tileSizeWithGap'],_0x42db71));_0x540eb6['style'][_0x9a3217(0x2a4)]=_0x9a3217(0x424)+(_0x49d98e-this['selectedTile']['x']*this['tileSizeWithGap'])+_0x9a3217(0x2aa),this['targetTile']={'x':Math[_0x9a3217(0x319)](_0x49d98e/this[_0x9a3217(0x222)]),'y':this[_0x9a3217(0x37b)]['y']};}else{if(this[_0x9a3217(0x31a)]===_0x9a3217(0x4a1)){const _0x1ad068=Math[_0x9a3217(0x42d)](0x0,Math[_0x9a3217(0x278)]((this['height']-0x1)*this[_0x9a3217(0x222)],_0x59901d));_0x540eb6['style'][_0x9a3217(0x2a4)]='translate(0,\x20'+(_0x1ad068-this['selectedTile']['y']*this[_0x9a3217(0x222)])+_0x9a3217(0x2d6),this[_0x9a3217(0x1f9)]={'x':this['selectedTile']['x'],'y':Math[_0x9a3217(0x319)](_0x1ad068/this[_0x9a3217(0x222)])};}}});}['handleTouchEnd'](_0x21da6f){const _0xd2c632=_0x12575b;if(!this[_0xd2c632(0x4b4)]||!this['selectedTile']||!this['targetTile']||this['gameOver']||this[_0xd2c632(0x499)]!=='playerTurn'){if(this[_0xd2c632(0x37b)]){const _0x5af41e=this[_0xd2c632(0x2d1)][this[_0xd2c632(0x37b)]['y']][this[_0xd2c632(0x37b)]['x']];if(_0x5af41e['element'])_0x5af41e['element'][_0xd2c632(0x3fd)]['remove']('selected');}this['isDragging']=![],this[_0xd2c632(0x37b)]=null,this['targetTile']=null,this['dragDirection']=null,this['renderBoard']();return;}const _0x8d6810=this['board'][this[_0xd2c632(0x37b)]['y']][this['selectedTile']['x']];if(_0x8d6810[_0xd2c632(0x2cc)])_0x8d6810[_0xd2c632(0x2cc)][_0xd2c632(0x3fd)][_0xd2c632(0x37a)](_0xd2c632(0x3f6));this[_0xd2c632(0x1c9)](this[_0xd2c632(0x37b)]['x'],this[_0xd2c632(0x37b)]['y'],this[_0xd2c632(0x1f9)]['x'],this[_0xd2c632(0x1f9)]['y']),this[_0xd2c632(0x4b4)]=![],this[_0xd2c632(0x37b)]=null,this[_0xd2c632(0x1f9)]=null,this['dragDirection']=null;}[_0x12575b(0x2ee)](_0x3dc8fb){const _0x20b2ba=_0x12575b,_0x50fc25=document['getElementById'](_0x20b2ba(0x383))[_0x20b2ba(0x1de)](),_0x2d8e72=Math[_0x20b2ba(0x1e7)]((_0x3dc8fb['clientX']-_0x50fc25[_0x20b2ba(0x4b1)])/this[_0x20b2ba(0x222)]),_0x1e6db9=Math[_0x20b2ba(0x1e7)]((_0x3dc8fb[_0x20b2ba(0x49d)]-_0x50fc25[_0x20b2ba(0x40f)])/this[_0x20b2ba(0x222)]);if(_0x2d8e72>=0x0&&_0x2d8e72<this[_0x20b2ba(0x473)]&&_0x1e6db9>=0x0&&_0x1e6db9<this[_0x20b2ba(0x2ca)])return{'x':_0x2d8e72,'y':_0x1e6db9,'element':this['board'][_0x1e6db9][_0x2d8e72][_0x20b2ba(0x2cc)]};return null;}[_0x12575b(0x1c9)](_0x59763f,_0x4cd8aa,_0x599899,_0x59bc02,_0x5d1c7f=()=>this[_0x12575b(0x2d4)]()){const _0x122102=_0x12575b,_0x701407=this[_0x122102(0x222)];let _0x506799;const _0x3044b4=[],_0x37c8c1=[];if(_0x4cd8aa===_0x59bc02){_0x506799=_0x59763f<_0x599899?0x1:-0x1;const _0x1e6b61=Math['min'](_0x59763f,_0x599899),_0x183082=Math['max'](_0x59763f,_0x599899);for(let _0x959905=_0x1e6b61;_0x959905<=_0x183082;_0x959905++){_0x3044b4[_0x122102(0x4bc)]({...this['board'][_0x4cd8aa][_0x959905]}),_0x37c8c1['push'](this[_0x122102(0x2d1)][_0x4cd8aa][_0x959905][_0x122102(0x2cc)]);}}else{if(_0x59763f===_0x599899){_0x506799=_0x4cd8aa<_0x59bc02?0x1:-0x1;const _0x594641=Math[_0x122102(0x278)](_0x4cd8aa,_0x59bc02),_0x47b5ac=Math[_0x122102(0x42d)](_0x4cd8aa,_0x59bc02);for(let _0x3fc7fd=_0x594641;_0x3fc7fd<=_0x47b5ac;_0x3fc7fd++){_0x3044b4[_0x122102(0x4bc)]({...this[_0x122102(0x2d1)][_0x3fc7fd][_0x59763f]}),_0x37c8c1[_0x122102(0x4bc)](this[_0x122102(0x2d1)][_0x3fc7fd][_0x59763f]['element']);}}}const _0x176249=this['board'][_0x4cd8aa][_0x59763f]['element'],_0x2bef3e=(_0x599899-_0x59763f)*_0x701407,_0x2cb016=(_0x59bc02-_0x4cd8aa)*_0x701407;_0x176249[_0x122102(0x3ff)][_0x122102(0x442)]='transform\x200.2s\x20ease',_0x176249[_0x122102(0x3ff)][_0x122102(0x2a4)]=_0x122102(0x424)+_0x2bef3e+_0x122102(0x32d)+_0x2cb016+_0x122102(0x3a9);let _0x26f98e=0x0;if(_0x4cd8aa===_0x59bc02)for(let _0x45469a=Math[_0x122102(0x278)](_0x59763f,_0x599899);_0x45469a<=Math[_0x122102(0x42d)](_0x59763f,_0x599899);_0x45469a++){if(_0x45469a===_0x59763f)continue;const _0x4bed8c=_0x506799*-_0x701407*(_0x45469a-_0x59763f)/Math[_0x122102(0x348)](_0x599899-_0x59763f);_0x37c8c1[_0x26f98e][_0x122102(0x3ff)][_0x122102(0x442)]=_0x122102(0x227),_0x37c8c1[_0x26f98e][_0x122102(0x3ff)]['transform']=_0x122102(0x424)+_0x4bed8c+_0x122102(0x2c5),_0x26f98e++;}else for(let _0x4ed6cb=Math[_0x122102(0x278)](_0x4cd8aa,_0x59bc02);_0x4ed6cb<=Math[_0x122102(0x42d)](_0x4cd8aa,_0x59bc02);_0x4ed6cb++){if(_0x4ed6cb===_0x4cd8aa)continue;const _0xc42711=_0x506799*-_0x701407*(_0x4ed6cb-_0x4cd8aa)/Math[_0x122102(0x348)](_0x59bc02-_0x4cd8aa);_0x37c8c1[_0x26f98e][_0x122102(0x3ff)][_0x122102(0x442)]=_0x122102(0x227),_0x37c8c1[_0x26f98e][_0x122102(0x3ff)][_0x122102(0x2a4)]=_0x122102(0x3ce)+_0xc42711+_0x122102(0x3a9),_0x26f98e++;}setTimeout(()=>{const _0x1deaa8=_0x122102;if(_0x4cd8aa===_0x59bc02){const _0x2a1763=this[_0x1deaa8(0x2d1)][_0x4cd8aa],_0x3399a8=[..._0x2a1763];if(_0x59763f<_0x599899){for(let _0x816f29=_0x59763f;_0x816f29<_0x599899;_0x816f29++)_0x2a1763[_0x816f29]=_0x3399a8[_0x816f29+0x1];}else{for(let _0x20a2df=_0x59763f;_0x20a2df>_0x599899;_0x20a2df--)_0x2a1763[_0x20a2df]=_0x3399a8[_0x20a2df-0x1];}_0x2a1763[_0x599899]=_0x3399a8[_0x59763f];}else{const _0x3b88fc=[];for(let _0x136df9=0x0;_0x136df9<this[_0x1deaa8(0x2ca)];_0x136df9++)_0x3b88fc[_0x136df9]={...this[_0x1deaa8(0x2d1)][_0x136df9][_0x59763f]};if(_0x4cd8aa<_0x59bc02){for(let _0x22acde=_0x4cd8aa;_0x22acde<_0x59bc02;_0x22acde++)this[_0x1deaa8(0x2d1)][_0x22acde][_0x59763f]=_0x3b88fc[_0x22acde+0x1];}else{for(let _0x5e6d82=_0x4cd8aa;_0x5e6d82>_0x59bc02;_0x5e6d82--)this[_0x1deaa8(0x2d1)][_0x5e6d82][_0x59763f]=_0x3b88fc[_0x5e6d82-0x1];}this['board'][_0x59bc02][_0x599899]=_0x3b88fc[_0x4cd8aa];}this[_0x1deaa8(0x2f6)]();const _0x1defab=this[_0x1deaa8(0x46b)](_0x599899,_0x59bc02);_0x1defab?this['gameState']=_0x1deaa8(0x4a7):(log('No\x20match,\x20reverting\x20tiles...'),this[_0x1deaa8(0x3c3)][_0x1deaa8(0x37c)][_0x1deaa8(0x391)](),_0x176249['style'][_0x1deaa8(0x442)]='transform\x200.2s\x20ease',_0x176249[_0x1deaa8(0x3ff)][_0x1deaa8(0x2a4)]=_0x1deaa8(0x400),_0x37c8c1[_0x1deaa8(0x439)](_0x2ead06=>{const _0x2b6a27=_0x1deaa8;_0x2ead06[_0x2b6a27(0x3ff)]['transition']=_0x2b6a27(0x227),_0x2ead06[_0x2b6a27(0x3ff)][_0x2b6a27(0x2a4)]=_0x2b6a27(0x400);}),setTimeout(()=>{const _0x170ce3=_0x1deaa8;if(_0x4cd8aa===_0x59bc02){const _0x2e9340=Math[_0x170ce3(0x278)](_0x59763f,_0x599899);for(let _0x11cfc1=0x0;_0x11cfc1<_0x3044b4[_0x170ce3(0x3eb)];_0x11cfc1++){this[_0x170ce3(0x2d1)][_0x4cd8aa][_0x2e9340+_0x11cfc1]={..._0x3044b4[_0x11cfc1],'element':_0x37c8c1[_0x11cfc1]};}}else{const _0x13e4b7=Math[_0x170ce3(0x278)](_0x4cd8aa,_0x59bc02);for(let _0x3871b2=0x0;_0x3871b2<_0x3044b4[_0x170ce3(0x3eb)];_0x3871b2++){this['board'][_0x13e4b7+_0x3871b2][_0x59763f]={..._0x3044b4[_0x3871b2],'element':_0x37c8c1[_0x3871b2]};}}this[_0x170ce3(0x2f6)](),this[_0x170ce3(0x499)]=this[_0x170ce3(0x31c)]===this[_0x170ce3(0x1ca)]?_0x170ce3(0x2fb):_0x170ce3(0x23d);},0xc8));},0xc8);}[_0x12575b(0x46b)](_0xe1b271=null,_0x24f0d1=null){const _0x2cd6dc=_0x12575b;console[_0x2cd6dc(0x253)](_0x2cd6dc(0x26f),this[_0x2cd6dc(0x254)]);if(this[_0x2cd6dc(0x254)])return console[_0x2cd6dc(0x253)]('Game\x20over,\x20exiting\x20resolveMatches'),![];const _0x4393e4=_0xe1b271!==null&&_0x24f0d1!==null;console[_0x2cd6dc(0x253)](_0x2cd6dc(0x364)+_0x4393e4);const _0x514a32=this['checkMatches']();console[_0x2cd6dc(0x253)]('Found\x20'+_0x514a32[_0x2cd6dc(0x3eb)]+_0x2cd6dc(0x1c0),_0x514a32);let _0x37fb69=0x1,_0x571e8f='';if(_0x4393e4&&_0x514a32[_0x2cd6dc(0x3eb)]>0x1){const _0x15b27d=_0x514a32[_0x2cd6dc(0x417)]((_0x166afa,_0x687c44)=>_0x166afa+_0x687c44[_0x2cd6dc(0x279)],0x0);console['log'](_0x2cd6dc(0x1fd)+_0x15b27d);if(_0x15b27d>=0x6&&_0x15b27d<=0x8)_0x37fb69=1.2,_0x571e8f=_0x2cd6dc(0x28c)+_0x15b27d+'\x20tiles\x20matched\x20for\x20a\x2020%\x20bonus!',this[_0x2cd6dc(0x3c3)][_0x2cd6dc(0x2f1)][_0x2cd6dc(0x391)]();else _0x15b27d>=0x9&&(_0x37fb69=0x3,_0x571e8f='Mega\x20Multi-Match!\x20'+_0x15b27d+'\x20tiles\x20matched\x20for\x20a\x20200%\x20bonus!',this['sounds'][_0x2cd6dc(0x2f1)][_0x2cd6dc(0x391)]());}if(_0x514a32[_0x2cd6dc(0x3eb)]>0x0){const _0x310091=new Set();let _0x8ce962=0x0;const _0x5ae635=this[_0x2cd6dc(0x31c)],_0x2d83f8=this[_0x2cd6dc(0x31c)]===this['player1']?this['player2']:this[_0x2cd6dc(0x1ca)];try{_0x514a32[_0x2cd6dc(0x439)](_0x14f6d2=>{const _0x30d4cb=_0x2cd6dc;console[_0x30d4cb(0x253)](_0x30d4cb(0x27b),_0x14f6d2),_0x14f6d2['coordinates'][_0x30d4cb(0x439)](_0x2b8b79=>_0x310091[_0x30d4cb(0x2b3)](_0x2b8b79));const _0x965f4d=this[_0x30d4cb(0x2dd)](_0x14f6d2,_0x4393e4);console[_0x30d4cb(0x253)](_0x30d4cb(0x482)+_0x965f4d);if(this[_0x30d4cb(0x254)]){console[_0x30d4cb(0x253)](_0x30d4cb(0x2ec));return;}if(_0x965f4d>0x0)_0x8ce962+=_0x965f4d;});if(this[_0x2cd6dc(0x254)])return console[_0x2cd6dc(0x253)](_0x2cd6dc(0x458)),!![];return console[_0x2cd6dc(0x253)]('Total\x20damage\x20dealt:\x20'+_0x8ce962+_0x2cd6dc(0x259),[..._0x310091]),_0x8ce962>0x0&&!this[_0x2cd6dc(0x254)]&&setTimeout(()=>{const _0x53c8ad=_0x2cd6dc;if(this[_0x53c8ad(0x254)]){console[_0x53c8ad(0x253)](_0x53c8ad(0x1e5));return;}console[_0x53c8ad(0x253)](_0x53c8ad(0x48c),_0x2d83f8[_0x53c8ad(0x266)]),this[_0x53c8ad(0x3db)](_0x2d83f8,_0x8ce962);},0x64),setTimeout(()=>{const _0x3f32a1=_0x2cd6dc;if(this[_0x3f32a1(0x254)]){console[_0x3f32a1(0x253)]('Game\x20over,\x20skipping\x20match\x20animation\x20and\x20cascading');return;}console[_0x3f32a1(0x253)](_0x3f32a1(0x2fe),[..._0x310091]),_0x310091[_0x3f32a1(0x439)](_0xbe01c2=>{const _0x3c2486=_0x3f32a1,[_0xea18b1,_0x48c937]=_0xbe01c2[_0x3c2486(0x362)](',')['map'](Number);this[_0x3c2486(0x2d1)][_0x48c937][_0xea18b1]?.[_0x3c2486(0x2cc)]?this[_0x3c2486(0x2d1)][_0x48c937][_0xea18b1][_0x3c2486(0x2cc)][_0x3c2486(0x3fd)][_0x3c2486(0x2b3)](_0x3c2486(0x486)):console[_0x3c2486(0x21f)](_0x3c2486(0x230)+_0xea18b1+','+_0x48c937+_0x3c2486(0x1ee));}),setTimeout(()=>{const _0x4fd548=_0x3f32a1;if(this[_0x4fd548(0x254)]){console['log'](_0x4fd548(0x457));return;}console[_0x4fd548(0x253)]('Clearing\x20matched\x20tiles:',[..._0x310091]),_0x310091[_0x4fd548(0x439)](_0x2391fc=>{const _0x43620a=_0x4fd548,[_0x42db22,_0x188d67]=_0x2391fc[_0x43620a(0x362)](',')[_0x43620a(0x377)](Number);this[_0x43620a(0x2d1)][_0x188d67][_0x42db22]&&(this[_0x43620a(0x2d1)][_0x188d67][_0x42db22][_0x43620a(0x386)]=null,this['board'][_0x188d67][_0x42db22][_0x43620a(0x2cc)]=null);}),this[_0x4fd548(0x3c3)]['match'][_0x4fd548(0x391)](),console[_0x4fd548(0x253)](_0x4fd548(0x1fa));if(_0x37fb69>0x1&&this['roundStats']['length']>0x0){const _0x2ade53=this[_0x4fd548(0x293)][this['roundStats'][_0x4fd548(0x3eb)]-0x1],_0xe7521d=_0x2ade53[_0x4fd548(0x22b)];_0x2ade53[_0x4fd548(0x22b)]=Math[_0x4fd548(0x319)](_0x2ade53[_0x4fd548(0x22b)]*_0x37fb69),_0x571e8f&&(log(_0x571e8f),log(_0x4fd548(0x388)+_0xe7521d+'\x20to\x20'+_0x2ade53['points']+_0x4fd548(0x1e6)));}this[_0x4fd548(0x20a)](()=>{const _0x608de2=_0x4fd548;if(this[_0x608de2(0x254)]){console[_0x608de2(0x253)]('Game\x20over,\x20skipping\x20endTurn');return;}console[_0x608de2(0x253)]('Cascade\x20complete,\x20ending\x20turn'),this[_0x608de2(0x2d4)]();});},0x12c);},0xc8),!![];}catch(_0x2d84bc){return console[_0x2cd6dc(0x49f)]('Error\x20in\x20resolveMatches:',_0x2d84bc),this[_0x2cd6dc(0x499)]=this[_0x2cd6dc(0x31c)]===this[_0x2cd6dc(0x1ca)]?'playerTurn':'aiTurn',![];}}return console[_0x2cd6dc(0x253)](_0x2cd6dc(0x356)),![];}[_0x12575b(0x2de)](){const _0x878241=_0x12575b;console[_0x878241(0x253)](_0x878241(0x4b9));const _0x136315=[];try{const _0x2d51d7=[];for(let _0x20db57=0x0;_0x20db57<this[_0x878241(0x2ca)];_0x20db57++){let _0x5cf1cb=0x0;for(let _0x24ad55=0x0;_0x24ad55<=this[_0x878241(0x473)];_0x24ad55++){const _0x4c7f30=_0x24ad55<this[_0x878241(0x473)]?this[_0x878241(0x2d1)][_0x20db57][_0x24ad55]?.[_0x878241(0x386)]:null;if(_0x4c7f30!==this[_0x878241(0x2d1)][_0x20db57][_0x5cf1cb]?.[_0x878241(0x386)]||_0x24ad55===this[_0x878241(0x473)]){const _0x563cc5=_0x24ad55-_0x5cf1cb;if(_0x563cc5>=0x3){const _0x170bfd=new Set();for(let _0x41783f=_0x5cf1cb;_0x41783f<_0x24ad55;_0x41783f++){_0x170bfd[_0x878241(0x2b3)](_0x41783f+','+_0x20db57);}_0x2d51d7[_0x878241(0x4bc)]({'type':this['board'][_0x20db57][_0x5cf1cb][_0x878241(0x386)],'coordinates':_0x170bfd}),console[_0x878241(0x253)](_0x878241(0x3d9)+_0x20db57+_0x878241(0x475)+_0x5cf1cb+'-'+(_0x24ad55-0x1)+':',[..._0x170bfd]);}_0x5cf1cb=_0x24ad55;}}}for(let _0x4bb36f=0x0;_0x4bb36f<this[_0x878241(0x473)];_0x4bb36f++){let _0x7a074b=0x0;for(let _0x88f6e8=0x0;_0x88f6e8<=this['height'];_0x88f6e8++){const _0x29d1ba=_0x88f6e8<this['height']?this[_0x878241(0x2d1)][_0x88f6e8][_0x4bb36f]?.['type']:null;if(_0x29d1ba!==this['board'][_0x7a074b][_0x4bb36f]?.[_0x878241(0x386)]||_0x88f6e8===this[_0x878241(0x2ca)]){const _0x186071=_0x88f6e8-_0x7a074b;if(_0x186071>=0x3){const _0x547252=new Set();for(let _0x2f17ab=_0x7a074b;_0x2f17ab<_0x88f6e8;_0x2f17ab++){_0x547252['add'](_0x4bb36f+','+_0x2f17ab);}_0x2d51d7[_0x878241(0x4bc)]({'type':this[_0x878241(0x2d1)][_0x7a074b][_0x4bb36f][_0x878241(0x386)],'coordinates':_0x547252}),console[_0x878241(0x253)]('Vertical\x20match\x20found\x20at\x20col\x20'+_0x4bb36f+_0x878241(0x347)+_0x7a074b+'-'+(_0x88f6e8-0x1)+':',[..._0x547252]);}_0x7a074b=_0x88f6e8;}}}const _0x35c809=[],_0x3a8a20=new Set();return _0x2d51d7['forEach']((_0x2ce596,_0x47472d)=>{const _0x3914fb=_0x878241;if(_0x3a8a20['has'](_0x47472d))return;const _0x50ad9c={'type':_0x2ce596['type'],'coordinates':new Set(_0x2ce596[_0x3914fb(0x223)])};_0x3a8a20[_0x3914fb(0x2b3)](_0x47472d);for(let _0x197877=0x0;_0x197877<_0x2d51d7[_0x3914fb(0x3eb)];_0x197877++){if(_0x3a8a20[_0x3914fb(0x3bc)](_0x197877))continue;const _0x1aad3e=_0x2d51d7[_0x197877];if(_0x1aad3e[_0x3914fb(0x386)]===_0x50ad9c['type']){const _0x1b8284=[..._0x1aad3e[_0x3914fb(0x223)]][_0x3914fb(0x36f)](_0x104213=>_0x50ad9c[_0x3914fb(0x223)][_0x3914fb(0x3bc)](_0x104213));_0x1b8284&&(_0x1aad3e['coordinates'][_0x3914fb(0x439)](_0x325d7f=>_0x50ad9c[_0x3914fb(0x223)]['add'](_0x325d7f)),_0x3a8a20[_0x3914fb(0x2b3)](_0x197877));}}_0x35c809[_0x3914fb(0x4bc)]({'type':_0x50ad9c[_0x3914fb(0x386)],'coordinates':_0x50ad9c[_0x3914fb(0x223)],'totalTiles':_0x50ad9c[_0x3914fb(0x223)][_0x3914fb(0x3ee)]});}),_0x136315[_0x878241(0x4bc)](..._0x35c809),console[_0x878241(0x253)]('checkMatches\x20completed,\x20returning\x20matches:',_0x136315),_0x136315;}catch(_0x12c863){return console[_0x878241(0x49f)](_0x878241(0x2f8),_0x12c863),[];}}['handleMatch'](_0xa77a1b,_0xff23c5=!![]){const _0x1e3f3a=_0x12575b;console[_0x1e3f3a(0x253)]('handleMatch\x20started,\x20match:',_0xa77a1b,_0x1e3f3a(0x413),_0xff23c5);const _0x5a9716=this[_0x1e3f3a(0x31c)],_0x576cb4=this[_0x1e3f3a(0x31c)]===this[_0x1e3f3a(0x1ca)]?this['player2']:this['player1'],_0x8ffa41=_0xa77a1b[_0x1e3f3a(0x386)],_0x5b858e=_0xa77a1b[_0x1e3f3a(0x279)];let _0x3d4115=0x0,_0x1716d8=0x0;console['log'](_0x576cb4[_0x1e3f3a(0x266)]+_0x1e3f3a(0x3c0)+_0x576cb4[_0x1e3f3a(0x39e)]);_0x5b858e==0x4&&(this[_0x1e3f3a(0x3c3)][_0x1e3f3a(0x438)]['play'](),log(_0x5a9716[_0x1e3f3a(0x266)]+'\x20created\x20a\x20match\x20of\x20'+_0x5b858e+_0x1e3f3a(0x462)));_0x5b858e>=0x5&&(this[_0x1e3f3a(0x3c3)][_0x1e3f3a(0x4a6)]['play'](),log(_0x5a9716[_0x1e3f3a(0x266)]+_0x1e3f3a(0x4ba)+_0x5b858e+_0x1e3f3a(0x462)));if(_0x8ffa41===_0x1e3f3a(0x219)||_0x8ffa41===_0x1e3f3a(0x3f7)||_0x8ffa41==='special-attack'||_0x8ffa41===_0x1e3f3a(0x4aa)){_0x3d4115=Math[_0x1e3f3a(0x319)](_0x5a9716[_0x1e3f3a(0x3a4)]*(_0x5b858e===0x3?0x2:_0x5b858e===0x4?0x3:0x4));let _0x4991f7=0x1;if(_0x5b858e===0x4)_0x4991f7=1.5;else _0x5b858e>=0x5&&(_0x4991f7=0x2);_0x3d4115=Math['round'](_0x3d4115*_0x4991f7),console[_0x1e3f3a(0x253)](_0x1e3f3a(0x1d1)+_0x5a9716[_0x1e3f3a(0x3a4)]*(_0x5b858e===0x3?0x2:_0x5b858e===0x4?0x3:0x4)+_0x1e3f3a(0x3f3)+_0x4991f7+_0x1e3f3a(0x372)+_0x3d4115);_0x8ffa41==='special-attack'&&(_0x3d4115=Math[_0x1e3f3a(0x319)](_0x3d4115*1.2),console[_0x1e3f3a(0x253)](_0x1e3f3a(0x3e1)+_0x3d4115));_0x5a9716[_0x1e3f3a(0x209)]&&(_0x3d4115+=_0x5a9716[_0x1e3f3a(0x49c)]||0xa,_0x5a9716[_0x1e3f3a(0x209)]=![],log(_0x5a9716['name']+_0x1e3f3a(0x2e4)),console[_0x1e3f3a(0x253)](_0x1e3f3a(0x357)+_0x3d4115));_0x1716d8=_0x3d4115;const _0x47ef17=_0x576cb4[_0x1e3f3a(0x232)]*0xa;Math[_0x1e3f3a(0x47e)]()*0x64<_0x47ef17&&(_0x3d4115=Math[_0x1e3f3a(0x1e7)](_0x3d4115/0x2),log(_0x576cb4['name']+_0x1e3f3a(0x3a0)+_0x3d4115+_0x1e3f3a(0x433)),console[_0x1e3f3a(0x253)](_0x1e3f3a(0x3aa)+_0x3d4115));let _0x37a014=0x0;_0x576cb4['lastStandActive']&&(_0x37a014=Math[_0x1e3f3a(0x278)](_0x3d4115,0x5),_0x3d4115=Math[_0x1e3f3a(0x42d)](0x0,_0x3d4115-_0x37a014),_0x576cb4[_0x1e3f3a(0x334)]=![],console[_0x1e3f3a(0x253)](_0x1e3f3a(0x496)+_0x37a014+_0x1e3f3a(0x39d)+_0x3d4115));const _0x47d575=_0x8ffa41==='first-attack'?_0x1e3f3a(0x2df):_0x8ffa41===_0x1e3f3a(0x3f7)?_0x1e3f3a(0x2ea):'Shadow\x20Strike';let _0x39abe8;if(_0x37a014>0x0)_0x39abe8=_0x5a9716[_0x1e3f3a(0x266)]+_0x1e3f3a(0x342)+_0x47d575+'\x20on\x20'+_0x576cb4[_0x1e3f3a(0x266)]+_0x1e3f3a(0x495)+_0x1716d8+_0x1e3f3a(0x440)+_0x576cb4[_0x1e3f3a(0x266)]+_0x1e3f3a(0x2b1)+_0x37a014+'\x20damage,\x20resulting\x20in\x20'+_0x3d4115+'\x20damage!';else _0x8ffa41==='last-stand'?_0x39abe8=_0x5a9716[_0x1e3f3a(0x266)]+_0x1e3f3a(0x275)+_0x3d4115+_0x1e3f3a(0x43f)+_0x576cb4['name']+'\x20and\x20preparing\x20to\x20mitigate\x205\x20damage\x20on\x20the\x20next\x20attack!':_0x39abe8=_0x5a9716[_0x1e3f3a(0x266)]+_0x1e3f3a(0x342)+_0x47d575+_0x1e3f3a(0x1df)+_0x576cb4['name']+_0x1e3f3a(0x495)+_0x3d4115+_0x1e3f3a(0x433);_0xff23c5?log(_0x39abe8):log('Cascade:\x20'+_0x39abe8),_0x576cb4[_0x1e3f3a(0x39e)]=Math[_0x1e3f3a(0x42d)](0x0,_0x576cb4[_0x1e3f3a(0x39e)]-_0x3d4115),console[_0x1e3f3a(0x253)](_0x576cb4[_0x1e3f3a(0x266)]+'\x20health\x20after\x20damage:\x20'+_0x576cb4[_0x1e3f3a(0x39e)]),this[_0x1e3f3a(0x24a)](_0x576cb4),console[_0x1e3f3a(0x253)](_0x1e3f3a(0x358)),this[_0x1e3f3a(0x373)](),!this[_0x1e3f3a(0x254)]&&(console[_0x1e3f3a(0x253)]('Game\x20not\x20over,\x20animating\x20attack'),this['animateAttack'](_0x5a9716,_0x3d4115,_0x8ffa41));}else _0x8ffa41===_0x1e3f3a(0x338)&&(this[_0x1e3f3a(0x20e)](_0x5a9716,_0x576cb4,_0x5b858e),!this[_0x1e3f3a(0x254)]&&(console[_0x1e3f3a(0x253)]('Animating\x20powerup'),this[_0x1e3f3a(0x3f5)](_0x5a9716)));(!this[_0x1e3f3a(0x293)][this['roundStats'][_0x1e3f3a(0x3eb)]-0x1]||this[_0x1e3f3a(0x293)][this[_0x1e3f3a(0x293)][_0x1e3f3a(0x3eb)]-0x1]['completed'])&&this[_0x1e3f3a(0x293)][_0x1e3f3a(0x4bc)]({'points':0x0,'matches':0x0,'healthPercentage':0x0,'completed':![]});const _0x42f942=this[_0x1e3f3a(0x293)][this['roundStats']['length']-0x1];return _0x42f942[_0x1e3f3a(0x22b)]+=_0x3d4115,_0x42f942[_0x1e3f3a(0x255)]+=0x1,console[_0x1e3f3a(0x253)](_0x1e3f3a(0x42a)+_0x3d4115),_0x3d4115;}[_0x12575b(0x20a)](_0x19a194){const _0x1cc7d4=_0x12575b;if(this['gameOver']){console['log'](_0x1cc7d4(0x2bf));return;}const _0x46d386=this['cascadeTilesWithoutRender'](),_0x1abc69=_0x1cc7d4(0x410);for(let _0x539f58=0x0;_0x539f58<this[_0x1cc7d4(0x473)];_0x539f58++){for(let _0x244ac7=0x0;_0x244ac7<this[_0x1cc7d4(0x2ca)];_0x244ac7++){const _0xccad9c=this[_0x1cc7d4(0x2d1)][_0x244ac7][_0x539f58];if(_0xccad9c[_0x1cc7d4(0x2cc)]&&_0xccad9c[_0x1cc7d4(0x2cc)]['style'][_0x1cc7d4(0x2a4)]===_0x1cc7d4(0x236)){const _0xf6d65d=this['countEmptyBelow'](_0x539f58,_0x244ac7);_0xf6d65d>0x0&&(_0xccad9c['element'][_0x1cc7d4(0x3fd)][_0x1cc7d4(0x2b3)](_0x1abc69),_0xccad9c['element'][_0x1cc7d4(0x3ff)][_0x1cc7d4(0x2a4)]=_0x1cc7d4(0x3ce)+_0xf6d65d*this[_0x1cc7d4(0x222)]+'px)');}}}this[_0x1cc7d4(0x2f6)](),_0x46d386?setTimeout(()=>{const _0x178e8a=_0x1cc7d4;if(this[_0x178e8a(0x254)]){console[_0x178e8a(0x253)]('Game\x20over,\x20skipping\x20cascade\x20resolution');return;}this[_0x178e8a(0x3c3)]['cascade']['play']();const _0xb717b9=this['resolveMatches'](),_0x27c6c8=document['querySelectorAll']('.'+_0x1abc69);_0x27c6c8[_0x178e8a(0x439)](_0x1e5b65=>{const _0x4d827f=_0x178e8a;_0x1e5b65[_0x4d827f(0x3fd)][_0x4d827f(0x37a)](_0x1abc69),_0x1e5b65['style'][_0x4d827f(0x2a4)]=_0x4d827f(0x400);}),!_0xb717b9&&_0x19a194();},0x12c):_0x19a194();}[_0x12575b(0x207)](){const _0x3b40a6=_0x12575b;let _0x4e38a3=![];for(let _0x2c3319=0x0;_0x2c3319<this['width'];_0x2c3319++){let _0x4a806e=0x0;for(let _0x56a8c2=this['height']-0x1;_0x56a8c2>=0x0;_0x56a8c2--){if(!this[_0x3b40a6(0x2d1)][_0x56a8c2][_0x2c3319][_0x3b40a6(0x386)])_0x4a806e++;else _0x4a806e>0x0&&(this[_0x3b40a6(0x2d1)][_0x56a8c2+_0x4a806e][_0x2c3319]=this[_0x3b40a6(0x2d1)][_0x56a8c2][_0x2c3319],this[_0x3b40a6(0x2d1)][_0x56a8c2][_0x2c3319]={'type':null,'element':null},_0x4e38a3=!![]);}for(let _0x3f9868=0x0;_0x3f9868<_0x4a806e;_0x3f9868++){this[_0x3b40a6(0x2d1)][_0x3f9868][_0x2c3319]=this[_0x3b40a6(0x39b)](),_0x4e38a3=!![];}}return _0x4e38a3;}[_0x12575b(0x3c2)](_0x37322c,_0x2980a3){const _0x451e0c=_0x12575b;let _0x34eae3=0x0;for(let _0x4f0833=_0x2980a3+0x1;_0x4f0833<this[_0x451e0c(0x2ca)];_0x4f0833++){if(!this[_0x451e0c(0x2d1)][_0x4f0833][_0x37322c][_0x451e0c(0x386)])_0x34eae3++;else break;}return _0x34eae3;}[_0x12575b(0x20e)](_0x2bbc8e,_0x311cbb,_0x26a17f){const _0x3789b1=_0x12575b,_0x40941f=0x1-_0x311cbb[_0x3789b1(0x232)]*0.05;let _0x3a099b,_0x41804d,_0xf33b5d,_0x1feb69=0x1,_0x1794f8='';if(_0x26a17f===0x4)_0x1feb69=1.5,_0x1794f8=_0x3789b1(0x2c0);else _0x26a17f>=0x5&&(_0x1feb69=0x2,_0x1794f8=_0x3789b1(0x316));if(_0x2bbc8e['powerup']===_0x3789b1(0x476))_0x41804d=0xa*_0x1feb69,_0x3a099b=Math[_0x3789b1(0x1e7)](_0x41804d*_0x40941f),_0xf33b5d=_0x41804d-_0x3a099b,_0x2bbc8e['health']=Math['min'](_0x2bbc8e[_0x3789b1(0x1da)],_0x2bbc8e[_0x3789b1(0x39e)]+_0x3a099b),log(_0x2bbc8e[_0x3789b1(0x266)]+_0x3789b1(0x1e0)+_0x3a099b+_0x3789b1(0x401)+_0x1794f8+(_0x311cbb[_0x3789b1(0x232)]>0x0?_0x3789b1(0x39f)+_0x41804d+',\x20reduced\x20by\x20'+_0xf33b5d+_0x3789b1(0x4bf)+_0x311cbb[_0x3789b1(0x266)]+'\x27s\x20tactics)':'')+'!');else{if(_0x2bbc8e[_0x3789b1(0x3ef)]===_0x3789b1(0x2d8))_0x41804d=0xa*_0x1feb69,_0x3a099b=Math[_0x3789b1(0x1e7)](_0x41804d*_0x40941f),_0xf33b5d=_0x41804d-_0x3a099b,_0x2bbc8e[_0x3789b1(0x209)]=!![],_0x2bbc8e[_0x3789b1(0x49c)]=_0x3a099b,log(_0x2bbc8e[_0x3789b1(0x266)]+_0x3789b1(0x31f)+_0x3a099b+'\x20damage'+_0x1794f8+(_0x311cbb[_0x3789b1(0x232)]>0x0?'\x20(originally\x20'+_0x41804d+',\x20reduced\x20by\x20'+_0xf33b5d+'\x20due\x20to\x20'+_0x311cbb[_0x3789b1(0x266)]+'\x27s\x20tactics)':'')+'!');else{if(_0x2bbc8e[_0x3789b1(0x3ef)]===_0x3789b1(0x446))_0x41804d=0x7*_0x1feb69,_0x3a099b=Math[_0x3789b1(0x1e7)](_0x41804d*_0x40941f),_0xf33b5d=_0x41804d-_0x3a099b,_0x2bbc8e[_0x3789b1(0x39e)]=Math[_0x3789b1(0x278)](_0x2bbc8e[_0x3789b1(0x1da)],_0x2bbc8e[_0x3789b1(0x39e)]+_0x3a099b),log(_0x2bbc8e[_0x3789b1(0x266)]+_0x3789b1(0x2ff)+_0x3a099b+'\x20HP'+_0x1794f8+(_0x311cbb['tactics']>0x0?_0x3789b1(0x39f)+_0x41804d+_0x3789b1(0x31e)+_0xf33b5d+_0x3789b1(0x4bf)+_0x311cbb[_0x3789b1(0x266)]+_0x3789b1(0x4b2):'')+'!');else _0x2bbc8e[_0x3789b1(0x3ef)]===_0x3789b1(0x35a)&&(_0x41804d=0x5*_0x1feb69,_0x3a099b=Math[_0x3789b1(0x1e7)](_0x41804d*_0x40941f),_0xf33b5d=_0x41804d-_0x3a099b,_0x2bbc8e[_0x3789b1(0x39e)]=Math[_0x3789b1(0x278)](_0x2bbc8e[_0x3789b1(0x1da)],_0x2bbc8e[_0x3789b1(0x39e)]+_0x3a099b),log(_0x2bbc8e[_0x3789b1(0x266)]+_0x3789b1(0x28d)+_0x3a099b+_0x3789b1(0x401)+_0x1794f8+(_0x311cbb[_0x3789b1(0x232)]>0x0?_0x3789b1(0x39f)+_0x41804d+_0x3789b1(0x31e)+_0xf33b5d+'\x20due\x20to\x20'+_0x311cbb['name']+_0x3789b1(0x4b2):'')+'!'));}}this['updateHealth'](_0x2bbc8e);}['updateHealth'](_0x266359){const _0x3bc2d3=_0x12575b,_0xeb3bf4=_0x266359===this[_0x3bc2d3(0x1ca)]?p1Health:p2Health,_0x398d20=_0x266359===this[_0x3bc2d3(0x1ca)]?p1Hp:p2Hp,_0x495c58=_0x266359[_0x3bc2d3(0x39e)]/_0x266359[_0x3bc2d3(0x1da)]*0x64;_0xeb3bf4[_0x3bc2d3(0x3ff)][_0x3bc2d3(0x473)]=_0x495c58+'%';let _0x5b5f34;if(_0x495c58>0x4b)_0x5b5f34=_0x3bc2d3(0x29f);else{if(_0x495c58>0x32)_0x5b5f34=_0x3bc2d3(0x2a1);else _0x495c58>0x19?_0x5b5f34='#FFA500':_0x5b5f34=_0x3bc2d3(0x203);}_0xeb3bf4[_0x3bc2d3(0x3ff)][_0x3bc2d3(0x1ed)]=_0x5b5f34,_0x398d20[_0x3bc2d3(0x1c2)]=_0x266359[_0x3bc2d3(0x39e)]+'/'+_0x266359[_0x3bc2d3(0x1da)];}[_0x12575b(0x2d4)](){const _0x32fea0=_0x12575b;if(this[_0x32fea0(0x499)]==='gameOver'||this[_0x32fea0(0x254)]){console[_0x32fea0(0x253)](_0x32fea0(0x1d2));return;}if(this['selectedBoss']){if(this['currentTurn']===this[_0x32fea0(0x1ca)])console[_0x32fea0(0x253)]('endTurn:\x20Player\x20turn\x20ending,\x20saving\x20boss\x20health'),this[_0x32fea0(0x371)](),console[_0x32fea0(0x253)](_0x32fea0(0x392)),this[_0x32fea0(0x22c)]();else this[_0x32fea0(0x31c)]===this[_0x32fea0(0x385)]&&(console['log'](_0x32fea0(0x3ca)),this[_0x32fea0(0x21c)](),console[_0x32fea0(0x253)]('endTurn:\x20Boss\x20turn\x20ending,\x20saving\x20board\x20state'),this['saveBoardState']());}this[_0x32fea0(0x31c)]=this[_0x32fea0(0x31c)]===this[_0x32fea0(0x1ca)]?this['player2']:this[_0x32fea0(0x1ca)],this[_0x32fea0(0x499)]=this[_0x32fea0(0x31c)]===this[_0x32fea0(0x1ca)]?_0x32fea0(0x2fb):_0x32fea0(0x23d),turnIndicator['textContent']=this[_0x32fea0(0x2e9)]?_0x32fea0(0x251)+(this[_0x32fea0(0x31c)]===this[_0x32fea0(0x1ca)]?_0x32fea0(0x403):_0x32fea0(0x366))+_0x32fea0(0x2f2):_0x32fea0(0x2fc)+this[_0x32fea0(0x240)]+'\x20-\x20'+(this[_0x32fea0(0x31c)]===this['player1']?_0x32fea0(0x403):_0x32fea0(0x39c))+_0x32fea0(0x2f2),log(_0x32fea0(0x29b)+(this[_0x32fea0(0x31c)]===this['player1']?'Player':this[_0x32fea0(0x2e9)]?'Boss':_0x32fea0(0x39c))),this[_0x32fea0(0x31c)]===this[_0x32fea0(0x385)]&&!this['gameOver']&&setTimeout(()=>this[_0x32fea0(0x23d)](),0x3e8);}[_0x12575b(0x23d)](){const _0x1af772=_0x12575b;if(this[_0x1af772(0x499)]!==_0x1af772(0x23d)||this[_0x1af772(0x31c)]!==this[_0x1af772(0x385)])return;this['gameState']='animating';const _0x2f1b25=this[_0x1af772(0x434)]();if(_0x2f1b25){log(this[_0x1af772(0x385)][_0x1af772(0x266)]+_0x1af772(0x3f4)+_0x2f1b25['x1']+',\x20'+_0x2f1b25['y1']+_0x1af772(0x34a)+_0x2f1b25['x2']+',\x20'+_0x2f1b25['y2']+')');const _0x40b818=()=>{const _0x454613=_0x1af772;this[_0x454613(0x2e9)]&&this[_0x454613(0x21c)](),this['endTurn']();};this[_0x1af772(0x1c9)](_0x2f1b25['x1'],_0x2f1b25['y1'],_0x2f1b25['x2'],_0x2f1b25['y2'],_0x40b818);}else log(this[_0x1af772(0x385)][_0x1af772(0x266)]+_0x1af772(0x3d8)),this['selectedBoss']&&this[_0x1af772(0x21c)](),this[_0x1af772(0x2d4)]();}[_0x12575b(0x434)](){const _0x1036da=_0x12575b;for(let _0x121f51=0x0;_0x121f51<this[_0x1036da(0x2ca)];_0x121f51++){for(let _0x1f8e7c=0x0;_0x1f8e7c<this[_0x1036da(0x473)];_0x1f8e7c++){if(_0x1f8e7c<this[_0x1036da(0x473)]-0x1&&this[_0x1036da(0x42f)](_0x1f8e7c,_0x121f51,_0x1f8e7c+0x1,_0x121f51))return{'x1':_0x1f8e7c,'y1':_0x121f51,'x2':_0x1f8e7c+0x1,'y2':_0x121f51};if(_0x121f51<this[_0x1036da(0x2ca)]-0x1&&this[_0x1036da(0x42f)](_0x1f8e7c,_0x121f51,_0x1f8e7c,_0x121f51+0x1))return{'x1':_0x1f8e7c,'y1':_0x121f51,'x2':_0x1f8e7c,'y2':_0x121f51+0x1};}}return null;}[_0x12575b(0x42f)](_0x4599c2,_0x42efbb,_0xc200e,_0x2f3130){const _0x3d6105=_0x12575b,_0x2ad6f4={...this[_0x3d6105(0x2d1)][_0x42efbb][_0x4599c2]},_0x523862={...this[_0x3d6105(0x2d1)][_0x2f3130][_0xc200e]};this[_0x3d6105(0x2d1)][_0x42efbb][_0x4599c2]=_0x523862,this['board'][_0x2f3130][_0xc200e]=_0x2ad6f4;const _0x431caa=this[_0x3d6105(0x2de)]()[_0x3d6105(0x3eb)]>0x0;return this[_0x3d6105(0x2d1)][_0x42efbb][_0x4599c2]=_0x2ad6f4,this[_0x3d6105(0x2d1)][_0x2f3130][_0xc200e]=_0x523862,_0x431caa;}async['checkGameOver'](){const _0x478ece=_0x12575b;if(this[_0x478ece(0x254)]||this[_0x478ece(0x30c)]){console[_0x478ece(0x253)](_0x478ece(0x286)+this['gameOver']+_0x478ece(0x46e)+this['isCheckingGameOver']+_0x478ece(0x24b)+this['currentLevel']);return;}this['isCheckingGameOver']=!![],console['log'](_0x478ece(0x33f)+this[_0x478ece(0x240)]+_0x478ece(0x3fc)+this['player1'][_0x478ece(0x39e)]+_0x478ece(0x4b3)+this[_0x478ece(0x385)][_0x478ece(0x39e)]+',\x20selectedBoss='+(this['selectedBoss']?this['selectedBoss'][_0x478ece(0x266)]:'none'));const _0x373ffa=document['getElementById']('try-again'),_0xbc734c=document[_0x478ece(0x43c)]('leaderboard-button');_0xbc734c[_0x478ece(0x20b)]='';let _0x2f12da;this[_0x478ece(0x2e9)]?_0x2f12da=_0x478ece(0x237):_0x2f12da=_0x478ece(0x449);_0xbc734c[_0x478ece(0x20b)]=_0x2f12da;if(this[_0x478ece(0x1ca)][_0x478ece(0x39e)]<=0x0){console['log'](_0x478ece(0x32a)+!!this[_0x478ece(0x2e9)]);this[_0x478ece(0x2e9)]&&(this[_0x478ece(0x1ca)]['health']=0x0,await this['savePlayerHealth'](),console[_0x478ece(0x253)](_0x478ece(0x38b)),this[_0x478ece(0x341)]());this[_0x478ece(0x254)]=!![],this[_0x478ece(0x499)]=_0x478ece(0x254),gameOver[_0x478ece(0x1c2)]=_0x478ece(0x1cd),turnIndicator[_0x478ece(0x1c2)]=_0x478ece(0x314);const _0x34775d=document['createElement'](_0x478ece(0x2c3));_0x34775d['id']=_0x478ece(0x285),_0x34775d['textContent']=this[_0x478ece(0x2e9)]?_0x478ece(0x3d3):_0x478ece(0x1bf),_0x373ffa[_0x478ece(0x25d)][_0x478ece(0x2c4)](_0x34775d,_0x373ffa),_0x34775d['addEventListener']('click',()=>{const _0x3e8135=_0x478ece;console[_0x3e8135(0x253)](_0x3e8135(0x47d)+_0x34775d[_0x3e8135(0x1c2)]+_0x3e8135(0x2e3)+(this[_0x3e8135(0x2e9)]?this[_0x3e8135(0x2e9)][_0x3e8135(0x266)]:'none')),document[_0x3e8135(0x43c)](_0x3e8135(0x23b))[_0x3e8135(0x3ff)][_0x3e8135(0x2c6)]=_0x3e8135(0x396),this[_0x3e8135(0x2e9)]?this[_0x3e8135(0x415)]():this['handleGameOverButton']();}),document['getElementById']('game-over-container')[_0x478ece(0x3ff)][_0x478ece(0x2c6)]=_0x478ece(0x3df);try{this[_0x478ece(0x3c3)][_0x478ece(0x435)][_0x478ece(0x391)]();}catch(_0x1c47aa){console['error'](_0x478ece(0x3cb),_0x1c47aa);}}else{if(this['player2'][_0x478ece(0x39e)]<=0x0){console['log'](_0x478ece(0x370));this[_0x478ece(0x2e9)]&&(this[_0x478ece(0x385)]['health']=0x0,await this[_0x478ece(0x371)](),console['log'](_0x478ece(0x22e)),this[_0x478ece(0x341)]());this['gameOver']=!![],this[_0x478ece(0x499)]=_0x478ece(0x254),gameOver[_0x478ece(0x1c2)]=_0x478ece(0x2d5),turnIndicator[_0x478ece(0x1c2)]=_0x478ece(0x314);const _0x579962=document[_0x478ece(0x3a8)](_0x478ece(0x2c3));_0x579962['id']='try-again',_0x579962[_0x478ece(0x1c2)]=this[_0x478ece(0x2e9)]?_0x478ece(0x3d3):this['currentLevel']===opponentsConfig['length']?_0x478ece(0x31b):_0x478ece(0x1c5),_0x373ffa[_0x478ece(0x25d)][_0x478ece(0x2c4)](_0x579962,_0x373ffa),_0x579962[_0x478ece(0x27c)](_0x478ece(0x382),()=>{const _0x175d92=_0x478ece;console['log']('checkGameOver:\x20Button\x20clicked,\x20text='+_0x579962[_0x175d92(0x1c2)]+',\x20selectedBoss='+(this['selectedBoss']?this[_0x175d92(0x2e9)]['name']:_0x175d92(0x396))),document[_0x175d92(0x43c)](_0x175d92(0x23b))[_0x175d92(0x3ff)]['display']=_0x175d92(0x396),this[_0x175d92(0x2e9)]?this[_0x175d92(0x415)]():this[_0x175d92(0x2d2)]();}),document[_0x478ece(0x43c)](_0x478ece(0x23b))['style'][_0x478ece(0x2c6)]='block';if(!this[_0x478ece(0x2e9)]){if(this[_0x478ece(0x31c)]===this[_0x478ece(0x1ca)]){const _0x59f8ce=this[_0x478ece(0x293)][this[_0x478ece(0x293)][_0x478ece(0x3eb)]-0x1];if(_0x59f8ce&&!_0x59f8ce[_0x478ece(0x1c4)]){_0x59f8ce[_0x478ece(0x28f)]=this[_0x478ece(0x1ca)][_0x478ece(0x39e)]/this[_0x478ece(0x1ca)][_0x478ece(0x1da)]*0x64,_0x59f8ce['completed']=!![];const _0x3e9e25=_0x59f8ce[_0x478ece(0x255)]>0x0?_0x59f8ce[_0x478ece(0x22b)]/_0x59f8ce[_0x478ece(0x255)]/0x64*(_0x59f8ce['healthPercentage']+0x14)*(0x1+this[_0x478ece(0x240)]/0x38):0x0;log(_0x478ece(0x2bb)+_0x59f8ce[_0x478ece(0x22b)]+_0x478ece(0x3b5)+_0x59f8ce['matches']+_0x478ece(0x2a8)+_0x59f8ce[_0x478ece(0x28f)][_0x478ece(0x46f)](0x2)+',\x20level='+this[_0x478ece(0x240)]),log(_0x478ece(0x378)+_0x59f8ce[_0x478ece(0x22b)]+'\x20/\x20'+_0x59f8ce[_0x478ece(0x255)]+')\x20/\x20100)\x20*\x20('+_0x59f8ce[_0x478ece(0x28f)]+_0x478ece(0x38a)+this[_0x478ece(0x240)]+'\x20/\x2056)\x20=\x20'+_0x3e9e25),this['grandTotalScore']+=_0x3e9e25,log(_0x478ece(0x4a8)+_0x59f8ce[_0x478ece(0x22b)]+_0x478ece(0x4ac)+_0x59f8ce[_0x478ece(0x255)]+',\x20Health\x20Left:\x20'+_0x59f8ce[_0x478ece(0x28f)][_0x478ece(0x46f)](0x2)+'%'),log(_0x478ece(0x408)+_0x3e9e25+_0x478ece(0x234)+this[_0x478ece(0x421)]);}}await this['saveScoreToDatabase'](this[_0x478ece(0x240)]);if(this[_0x478ece(0x240)]===opponentsConfig['length']){try{this[_0x478ece(0x3c3)][_0x478ece(0x29c)][_0x478ece(0x391)]();}catch(_0x39cb7c){console[_0x478ece(0x49f)]('Error\x20playing\x20finalWin\x20sound:',_0x39cb7c);}log(_0x478ece(0x349)+this[_0x478ece(0x421)]),this['grandTotalScore']=0x0,await this[_0x478ece(0x28e)](),log(_0x478ece(0x47f));}else this['clearBoard'](),this[_0x478ece(0x240)]+=0x1,await this[_0x478ece(0x1fc)](),console['log'](_0x478ece(0x29d)+this[_0x478ece(0x240)]),this[_0x478ece(0x3c3)][_0x478ece(0x36b)][_0x478ece(0x391)]();}let _0xc26705,_0x2deba8=this['player2']['fallbackUrl']||_0x478ece(0x204);const _0x223e3b=themes[_0x478ece(0x3a1)](_0x49fe47=>_0x49fe47[_0x478ece(0x296)])[_0x478ece(0x483)](_0xa879da=>_0xa879da['value']===this[_0x478ece(0x404)]),_0x5233f2=_0x223e3b?.[_0x478ece(0x1f8)]||_0x478ece(0x3e9);this[_0x478ece(0x2e9)]?_0xc26705=this[_0x478ece(0x385)][_0x478ece(0x444)]||_0x478ece(0x217)+this['player2'][_0x478ece(0x266)][_0x478ece(0x260)]()[_0x478ece(0x49a)](/ /g,'-')+'.'+(this[_0x478ece(0x385)]['extension']||_0x478ece(0x3e9)):_0xc26705=this['baseImagePath']+_0x478ece(0x1e4)+this[_0x478ece(0x385)][_0x478ece(0x266)]['toLowerCase']()[_0x478ece(0x49a)](/ /g,'-')+'.'+_0x5233f2;const _0x48e131=document[_0x478ece(0x43c)]('p2-image'),_0x2ab29b=_0x48e131[_0x478ece(0x25d)];if(this[_0x478ece(0x385)]['mediaType']==='video'){if(_0x48e131['tagName']!==_0x478ece(0x465)){const _0x146221=document['createElement'](_0x478ece(0x22f));_0x146221['id']=_0x478ece(0x381),_0x146221[_0x478ece(0x2ed)]=_0xc26705,_0x146221['autoplay']=!![],_0x146221['loop']=!![],_0x146221[_0x478ece(0x2be)]=!![],_0x146221['alt']=this[_0x478ece(0x385)][_0x478ece(0x266)],_0x146221['onerror']=()=>{const _0x95da4e=_0x478ece;console['warn']('Failed\x20to\x20load\x20battle-damaged\x20video:\x20'+_0xc26705+_0x95da4e(0x4ae)),_0x146221[_0x95da4e(0x2ed)]=_0x2deba8;},_0x2ab29b[_0x478ece(0x2c4)](_0x146221,_0x48e131);}else _0x48e131[_0x478ece(0x2ed)]=_0xc26705,_0x48e131['onerror']=()=>{const _0x1746ca=_0x478ece;console['warn'](_0x1746ca(0x274)+_0xc26705+_0x1746ca(0x4ae)),_0x48e131[_0x1746ca(0x2ed)]=_0x2deba8;};}else{if(_0x48e131['tagName']!==_0x478ece(0x277)){const _0x3d41e5=document['createElement'](_0x478ece(0x4b6));_0x3d41e5['id']='p2-image',_0x3d41e5[_0x478ece(0x2ed)]=_0xc26705,_0x3d41e5['alt']=this[_0x478ece(0x385)][_0x478ece(0x266)],_0x3d41e5[_0x478ece(0x4a2)]=()=>{const _0x5b0381=_0x478ece;console[_0x5b0381(0x21f)](_0x5b0381(0x2d9)+_0xc26705+_0x5b0381(0x4ae)),_0x3d41e5['src']=_0x2deba8;},_0x2ab29b[_0x478ece(0x2c4)](_0x3d41e5,_0x48e131);}else _0x48e131[_0x478ece(0x2ed)]=_0xc26705,_0x48e131[_0x478ece(0x4a2)]=()=>{const _0x4abc86=_0x478ece;console[_0x4abc86(0x21f)]('Failed\x20to\x20load\x20battle-damaged\x20image:\x20'+_0xc26705+',\x20using\x20fallback'),_0x48e131['src']=_0x2deba8;};}const _0x3c34d8=document[_0x478ece(0x43c)](_0x478ece(0x381));_0x3c34d8[_0x478ece(0x3ff)][_0x478ece(0x2c6)]='block',_0x3c34d8[_0x478ece(0x3fd)][_0x478ece(0x2b3)](_0x478ece(0x233)),p1Image[_0x478ece(0x3fd)][_0x478ece(0x2b3)](_0x478ece(0x2b5)),this[_0x478ece(0x2f6)]();}}this[_0x478ece(0x30c)]=![],console[_0x478ece(0x253)](_0x478ece(0x38c)+this['gameOver']+_0x478ece(0x24d)+this[_0x478ece(0x499)]);}async[_0x12575b(0x1ec)](_0x2d798a){const _0x55ecc1=_0x12575b,_0x460806={'level':_0x2d798a,'score':this['grandTotalScore']};console[_0x55ecc1(0x253)](_0x55ecc1(0x1fe)+_0x460806[_0x55ecc1(0x2c1)]+_0x55ecc1(0x44b)+_0x460806[_0x55ecc1(0x202)]);try{const _0x14e8cc=await fetch(_0x55ecc1(0x214),{'method':_0x55ecc1(0x1f6),'headers':{'Content-Type':_0x55ecc1(0x36c)},'body':JSON[_0x55ecc1(0x24e)](_0x460806)});if(!_0x14e8cc['ok'])throw new Error('HTTP\x20error!\x20Status:\x20'+_0x14e8cc['status']);const _0x4f619f=await _0x14e8cc[_0x55ecc1(0x32b)]();console[_0x55ecc1(0x253)]('Save\x20response:',_0x4f619f),log(_0x55ecc1(0x2fc)+_0x4f619f['level']+_0x55ecc1(0x3af)+_0x4f619f[_0x55ecc1(0x202)][_0x55ecc1(0x46f)](0x2)),_0x4f619f[_0x55ecc1(0x2ac)]===_0x55ecc1(0x4ad)?log('Score\x20Saved:\x20Level\x20'+_0x4f619f[_0x55ecc1(0x2c1)]+',\x20Score\x20'+_0x4f619f['score'][_0x55ecc1(0x46f)](0x2)+_0x55ecc1(0x3f9)+_0x4f619f[_0x55ecc1(0x3a5)]):log(_0x55ecc1(0x485)+_0x4f619f[_0x55ecc1(0x291)]);}catch(_0x1958e9){console['error'](_0x55ecc1(0x246),_0x1958e9),log(_0x55ecc1(0x4b0)+_0x1958e9['message']);}}[_0x12575b(0x412)](_0x2a7dc9,_0x119333,_0x42a590,_0x467047){const _0x8cffe4=_0x12575b,_0x39ccf6=_0x2a7dc9[_0x8cffe4(0x3ff)]['transform']||'',_0x47eaa4=_0x39ccf6['includes'](_0x8cffe4(0x397))?_0x39ccf6[_0x8cffe4(0x455)](/scaleX\([^)]+\)/)[0x0]:'';_0x2a7dc9[_0x8cffe4(0x3ff)][_0x8cffe4(0x442)]=_0x8cffe4(0x448)+_0x467047/0x2/0x3e8+_0x8cffe4(0x27e),_0x2a7dc9[_0x8cffe4(0x3ff)][_0x8cffe4(0x2a4)]=_0x8cffe4(0x42b)+_0x119333+'px)\x20'+_0x47eaa4,_0x2a7dc9[_0x8cffe4(0x3fd)][_0x8cffe4(0x2b3)](_0x42a590),setTimeout(()=>{const _0x5e179a=_0x8cffe4;_0x2a7dc9[_0x5e179a(0x3ff)][_0x5e179a(0x2a4)]=_0x47eaa4,setTimeout(()=>{const _0x2d0952=_0x5e179a;_0x2a7dc9[_0x2d0952(0x3fd)]['remove'](_0x42a590);},_0x467047/0x2);},_0x467047/0x2);}['animateAttack'](_0x38c071,_0x96874f,_0x1e4db2){const _0x8c7e1f=_0x12575b,_0x59804e=_0x38c071===this[_0x8c7e1f(0x1ca)]?p1Image:p2Image,_0x12cd01=_0x38c071===this[_0x8c7e1f(0x1ca)]?0x1:-0x1,_0x5191a9=Math[_0x8c7e1f(0x278)](0xa,0x2+_0x96874f*0.4),_0x19c28d=_0x12cd01*_0x5191a9,_0x3b1d8c=_0x8c7e1f(0x2c8)+_0x1e4db2;this[_0x8c7e1f(0x412)](_0x59804e,_0x19c28d,_0x3b1d8c,0xc8);}[_0x12575b(0x3f5)](_0x5a44e8){const _0x140480=_0x12575b,_0xaa0d96=_0x5a44e8===this[_0x140480(0x1ca)]?p1Image:p2Image;this['applyAnimation'](_0xaa0d96,0x0,_0x140480(0x280),0xc8);}[_0x12575b(0x3db)](_0x1f76d4,_0x161409){const _0x9196ce=_0x12575b,_0x2dc45a=_0x1f76d4===this[_0x9196ce(0x1ca)]?p1Image:p2Image,_0x485eaa=_0x1f76d4===this[_0x9196ce(0x1ca)]?-0x1:0x1,_0x1c3f51=Math[_0x9196ce(0x278)](0xa,0x2+_0x161409*0.4),_0x482cb5=_0x485eaa*_0x1c3f51;this[_0x9196ce(0x412)](_0x2dc45a,_0x482cb5,_0x9196ce(0x459),0xc8);}}function randomChoice(_0x261ff0){const _0x4be7ce=_0x12575b;return _0x261ff0[Math['floor'](Math[_0x4be7ce(0x47e)]()*_0x261ff0[_0x4be7ce(0x3eb)])];}function log(_0x362779){const _0x5e5cf1=_0x12575b,_0x2e242c=document[_0x5e5cf1(0x43c)](_0x5e5cf1(0x281)),_0x20d6aa=document[_0x5e5cf1(0x3a8)]('li');_0x20d6aa[_0x5e5cf1(0x1c2)]=_0x362779,_0x2e242c['insertBefore'](_0x20d6aa,_0x2e242c[_0x5e5cf1(0x2bc)]),_0x2e242c[_0x5e5cf1(0x290)][_0x5e5cf1(0x3eb)]>0x32&&_0x2e242c[_0x5e5cf1(0x331)](_0x2e242c[_0x5e5cf1(0x2f9)]),_0x2e242c[_0x5e5cf1(0x3ec)]=0x0;}const turnIndicator=document['getElementById'](_0x12575b(0x350)),p1Name=document['getElementById'](_0x12575b(0x2a0)),p1Image=document['getElementById'](_0x12575b(0x265)),p1Health=document['getElementById'](_0x12575b(0x3bf)),p1Hp=document['getElementById'](_0x12575b(0x3c9)),p1Strength=document[_0x12575b(0x43c)]('p1-strength'),p1Speed=document[_0x12575b(0x43c)]('p1-speed'),p1Tactics=document[_0x12575b(0x43c)](_0x12575b(0x453)),p1Size=document['getElementById'](_0x12575b(0x3fb)),p1Powerup=document['getElementById'](_0x12575b(0x1f2)),p1Type=document[_0x12575b(0x43c)](_0x12575b(0x247)),p2Name=document[_0x12575b(0x43c)](_0x12575b(0x3d5)),p2Image=document[_0x12575b(0x43c)]('p2-image'),p2Health=document['getElementById'](_0x12575b(0x36e)),p2Hp=document['getElementById']('p2-hp'),p2Strength=document[_0x12575b(0x43c)](_0x12575b(0x1f4)),p2Speed=document[_0x12575b(0x43c)](_0x12575b(0x398)),p2Tactics=document['getElementById']('p2-tactics'),p2Size=document[_0x12575b(0x43c)]('p2-size'),p2Powerup=document[_0x12575b(0x43c)](_0x12575b(0x445)),p2Type=document[_0x12575b(0x43c)]('p2-type'),battleLog=document[_0x12575b(0x43c)](_0x12575b(0x281)),gameOver=document[_0x12575b(0x43c)](_0x12575b(0x200)),assetCache={};async function getAssets(_0x646f9c){const _0x8dca30=_0x12575b;if(assetCache[_0x646f9c])return console[_0x8dca30(0x253)]('getAssets:\x20Cache\x20hit\x20for\x20'+_0x646f9c),assetCache[_0x646f9c];console['time'](_0x8dca30(0x333)+_0x646f9c);let _0x3d43e8=[];try{console[_0x8dca30(0x253)](_0x8dca30(0x432));const _0x4024d8=await Promise[_0x8dca30(0x36a)]([fetch(_0x8dca30(0x282),{'method':'POST','headers':{'Content-Type':'application/json'},'body':JSON['stringify']({'theme':_0x8dca30(0x38d)})}),new Promise((_0x4e2b85,_0x12f034)=>setTimeout(()=>_0x12f034(new Error(_0x8dca30(0x267))),0x1388))]);if(!_0x4024d8['ok'])throw new Error(_0x8dca30(0x302)+_0x4024d8[_0x8dca30(0x2ac)]);_0x3d43e8=await _0x4024d8[_0x8dca30(0x32b)](),!Array['isArray'](_0x3d43e8)&&(_0x3d43e8=[_0x3d43e8]),_0x3d43e8=_0x3d43e8['map']((_0x13fbed,_0x28fc40)=>({..._0x13fbed,'theme':_0x8dca30(0x38d),'name':_0x13fbed[_0x8dca30(0x266)]||'Monstrocity_Unknown_'+_0x28fc40,'strength':_0x13fbed[_0x8dca30(0x3a4)]||0x4,'speed':_0x13fbed[_0x8dca30(0x3e3)]||0x4,'tactics':_0x13fbed[_0x8dca30(0x232)]||0x4,'size':_0x13fbed[_0x8dca30(0x3ee)]||_0x8dca30(0x41d),'type':_0x13fbed[_0x8dca30(0x386)]||'Base','powerup':_0x13fbed[_0x8dca30(0x3ef)]||'Regenerate'}));}catch(_0x564f43){console[_0x8dca30(0x49f)]('getAssets:\x20Monstrocity\x20fetch\x20error:',_0x564f43),_0x3d43e8=[{'name':_0x8dca30(0x241),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x8dca30(0x41d),'type':_0x8dca30(0x268),'powerup':'Regenerate','theme':'monstrocity'},{'name':_0x8dca30(0x257),'strength':0x3,'speed':0x5,'tactics':0x3,'size':'Small','type':'Base','powerup':_0x8dca30(0x476),'theme':_0x8dca30(0x38d)}];}if(_0x646f9c===_0x8dca30(0x38d))return assetCache[_0x646f9c]=_0x3d43e8,console['timeEnd'](_0x8dca30(0x333)+_0x646f9c),_0x3d43e8;const _0x3a244e=themes[_0x8dca30(0x3a1)](_0x15d5ba=>_0x15d5ba['items'])[_0x8dca30(0x483)](_0x38cbea=>_0x38cbea[_0x8dca30(0x27d)]===_0x646f9c);if(!_0x3a244e)return console[_0x8dca30(0x21f)](_0x8dca30(0x244)+_0x646f9c),assetCache[_0x646f9c]=_0x3d43e8,console[_0x8dca30(0x1d4)](_0x8dca30(0x333)+_0x646f9c),_0x3d43e8;const _0x55b5f7=_0x3a244e[_0x8dca30(0x1f1)]?_0x3a244e[_0x8dca30(0x1f1)][_0x8dca30(0x362)](',')[_0x8dca30(0x3cd)](_0x422816=>_0x422816['trim']()):[];if(!_0x55b5f7[_0x8dca30(0x3eb)])return assetCache[_0x646f9c]=_0x3d43e8,console[_0x8dca30(0x1d4)](_0x8dca30(0x333)+_0x646f9c),_0x3d43e8;let _0x5e44fe=[];try{const _0x179ba6=_0x55b5f7[_0x8dca30(0x377)]((_0x28c86b,_0x473311)=>({'policyId':_0x28c86b,'orientation':_0x3a244e[_0x8dca30(0x212)]?.[_0x8dca30(0x362)](',')[_0x473311]||_0x8dca30(0x34d),'ipfsPrefix':_0x3a244e[_0x8dca30(0x450)]?.[_0x8dca30(0x362)](',')[_0x473311]||_0x8dca30(0x297)})),_0x509720=await Promise['race']([fetch('ajax/get-nft-assets.php',{'method':'POST','headers':{'Content-Type':_0x8dca30(0x36c)},'body':JSON[_0x8dca30(0x24e)]({'policyIds':_0x179ba6[_0x8dca30(0x377)](_0x397790=>_0x397790[_0x8dca30(0x355)]),'theme':_0x646f9c})}),new Promise((_0x30ebc7,_0x288811)=>setTimeout(()=>_0x288811(new Error('NFT\x20timeout')),0x2710))]);if(!_0x509720['ok'])throw new Error(_0x8dca30(0x409)+_0x509720[_0x8dca30(0x2ac)]);const _0x4e1bd3=await _0x509720['json']();_0x5e44fe=Array[_0x8dca30(0x208)](_0x4e1bd3)?_0x4e1bd3:[_0x4e1bd3],_0x5e44fe=_0x5e44fe['filter'](_0x49f312=>_0x49f312&&_0x49f312[_0x8dca30(0x266)]&&_0x49f312[_0x8dca30(0x305)])[_0x8dca30(0x377)]((_0x5d8e33,_0x439282)=>({..._0x5d8e33,'theme':_0x646f9c,'name':_0x5d8e33['name']||_0x8dca30(0x35e)+_0x439282,'strength':_0x5d8e33[_0x8dca30(0x3a4)]||0x4,'speed':_0x5d8e33[_0x8dca30(0x3e3)]||0x4,'tactics':_0x5d8e33[_0x8dca30(0x232)]||0x4,'size':_0x5d8e33[_0x8dca30(0x3ee)]||_0x8dca30(0x41d),'type':_0x5d8e33[_0x8dca30(0x386)]||_0x8dca30(0x268),'powerup':_0x5d8e33[_0x8dca30(0x3ef)]||'Regenerate','policyId':_0x5d8e33[_0x8dca30(0x355)]||_0x179ba6[0x0][_0x8dca30(0x355)],'ipfs':_0x5d8e33[_0x8dca30(0x305)]||''}));}catch(_0x4b8830){console[_0x8dca30(0x49f)](_0x8dca30(0x1c3)+_0x646f9c+':',_0x4b8830);}const _0x1d7e64=[..._0x3d43e8,..._0x5e44fe];return assetCache[_0x646f9c]=_0x1d7e64,console[_0x8dca30(0x1d4)](_0x8dca30(0x333)+_0x646f9c),_0x1d7e64;}document[_0x12575b(0x27c)](_0x12575b(0x300),function(){var _0xc1ec76=function(){const _0x54bfa7=_0x372b;var _0x3ba7cb=localStorage['getItem']('gameTheme')||_0x54bfa7(0x38d);getAssets(_0x3ba7cb)[_0x54bfa7(0x3b8)](function(_0x14e6c2){const _0x5c9887=_0x54bfa7;console['log'](_0x5c9887(0x430),_0x14e6c2);var _0x2e6c0d=new MonstrocityMatch3(_0x14e6c2,_0x3ba7cb);console[_0x5c9887(0x253)](_0x5c9887(0x3dd)),_0x2e6c0d[_0x5c9887(0x1ef)]()[_0x5c9887(0x3b8)](function(){const _0x16d0ee=_0x5c9887;console['log'](_0x16d0ee(0x470)),document[_0x16d0ee(0x4a4)](_0x16d0ee(0x407))[_0x16d0ee(0x2ed)]=_0x2e6c0d[_0x16d0ee(0x468)]+'logo.png';});})[_0x54bfa7(0x2b4)](function(_0x4bf214){const _0x7c7daf=_0x54bfa7;console[_0x7c7daf(0x49f)](_0x7c7daf(0x423),_0x4bf214);});};_0xc1ec76();});function _0x424a(){const _0x198092=['translate(0,\x200)','\x20HP','Sending\x20saveProgress\x20request\x20with\x20data:','Player','theme','\x20but\x20dulls\x20tactics\x20to\x20','handleTouchEnd','.game-logo','Round\x20Score:\x20','NFT\x20HTTP\x20error!\x20Status:\x20','Refresh\x20Board\x20button\x20clicked','\x20(opponentsConfig[','refreshBoard:\x20Unsticking\x20game\x20board\x20for\x20boss\x20battle','renderBoard:\x20Row\x20','toString','top','falling','logo.png','applyAnimation','isInitialMove:','Health\x20saved:\x20','showBossSelectionScreen','savePlayerHealth:\x20Error\x20saving\x20health:','reduce','15820FnluwW','No\x20saved\x20health\x20found,\x20using\x20max\x20health','boosts\x20health\x20to\x20','playerCount','startBossBattle:\x20player1\x20orientation\x20set\x20to\x20','Medium','Ouchie','saveBoardState:\x20Error\x20saving\x20board\x20state:','offsetX','grandTotalScore','createCharacter:\x20config.imageUrl=','Main:\x20Error\x20initializing\x20game:','translate(','progress-start-fresh','</td></tr>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<tr><td>Strength:</td><td>','\x20-\x20','Progress\x20cleared','theme\x20game','handleMatch\x20completed,\x20damage\x20dealt:\x20','translateX(','/logo.png','max','Unknown\x20error','canMakeMatch','Main:\x20Player\x20characters\x20loaded:','imageUrl','getAssets:\x20Fetching\x20Monstrocity\x20assets','\x20damage!','findAIMove','loss','updateTheme:\x20Reset\x20player2\x20to\x20default\x20opponent:\x20','alt','powerGem','forEach','touches','className','getElementById','playerCharactersConfig','updateTheme:\x20Boss\x20battle\x20context,\x20preserving\x20selectedBoss\x20and\x20selectedCharacter','\x20damage\x20to\x20','\x20damage,\x20but\x20','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<h2>Select\x20Boss</h2>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<button\x20id=\x22boss-close-button\x22\x20class=\x22theme-select-button\x22\x20style=\x22margin-bottom:\x2010px;\x22>Back\x20to\x20Themes</button>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div\x20id=\x22boss-options\x22></div>\x0a\x09\x20\x20\x20\x20\x20\x20','transition','Selected\x20Boss:','battleDamagedUrl','p2-powerup','Regenerate','loadBoard:\x20Invalid\x20hash\x20for\x20level\x20','transform\x20','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<form\x20action=\x22leaderboards.php\x22\x20method=\x22post\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<input\x20type=\x22hidden\x22\x20name=\x22filterbystreak\x22\x20id=\x22filterbystreak\x22\x20value=\x22monthly-monstrocity\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<input\x20id=\x22leaderboard\x22\x20type=\x22submit\x22\x20value=\x22LEADERBOARD\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20</form>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20','renderBoard:\x20Board\x20rendered\x20successfully',',\x20score=','Goblin\x20Ganger','mediaType','Leader','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>Error\x20loading\x20bosses.\x20Please\x20try\x20again.</p>','ipfsPrefixes','dataset','92837kYOAWk','p1-tactics','createBoardHash','match','Starting\x20Level\x20','Game\x20over,\x20skipping\x20tile\x20clearing\x20and\x20cascading','Game\x20over\x20after\x20processing\x20matches,\x20exiting\x20resolveMatches','glow-recoil','\x22\x20data-project=\x22','loadBoardState:\x20Board\x20state\x20loaded\x20successfully:','showBossSelect:\x20Error\x20fetching\x20bosses:','Merdock','showBossSelect','handleMouseMove','project','Boss\x20selected:','\x20tiles!','Restart','select-boss-button','VIDEO','...','Spydrax','baseImagePath','restart','inline-block','resolveMatches','\x22\x20onerror=\x22this.src=\x27staking/icons/skull.png\x27\x22></div>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div\x20class=\x22health-bar\x22\x20style=\x22margin-bottom:\x2010px;\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div\x20class=\x22health\x22\x20style=\x22width:\x20','playerCharacters',',\x20isCheckingGameOver=','toFixed','Main:\x20Game\x20initialized\x20successfully','isTouchDevice','loop','width','186456gAHzPJ',',\x20cols\x20','Heal','https://www.skulliance.io/staking/sounds/select.ogg','tileTypes','theme-group','&boss_id=','setBackground','playerHealth','checkGameOver:\x20Button\x20clicked,\x20text=','random','Game\x20completed!\x20Grand\x20total\x20score\x20reset.','toggleGameButtons:\x20Setting\x20buttons\x20for\x20','Response\x20status:','Damage\x20from\x20match:\x20','find','parse','Score\x20Not\x20Saved:\x20','matched','No\x20saved\x20progress\x20found,\x20starting\x20at\x20Level\x201','Jarhead','updateTheme:\x20Not\x20a\x20boss\x20battle,\x20clearing\x20selectedBoss\x20and\x20selectedCharacter','change-character','fallbackUrl','Animating\x20recoil\x20for\x20defender:','center','<p><strong>','HTTP\x20error!\x20Status:\x20','),\x20updating\x20to\x20boss\x20theme','scaleX(-1)','createDocumentFragment','\x20steps\x20into\x20the\x20fray\x20with\x20','handleTouchStart','\x20for\x20','Last\x20Stand\x20applied,\x20mitigated\x20',',\x20storedTheme=','<p>Size:\x20','gameState','replace','MonstrocityMatch3:\x20Selected\x20character\x20set\x20to\x20','boostValue','clientY','saveBoardState:\x20Not\x20a\x20boss\x20battle,\x20skipping','error','Failed\x20to\x20save\x20progress:','column','onerror',',\x20boss:\x20','querySelector','61900VDOyUl','hyperCube','animating','Round\x20Won!\x20Points:\x20','handleMouseUp','last-stand',',\x20Score\x20',',\x20Matches:\x20','success',',\x20using\x20fallback','startBossBattle:\x20Theme\x20mismatch\x20(current:\x20','Error\x20saving\x20score:\x20','left','\x27s\x20tactics)',',\x20player2.health=','isDragging','Error\x20loading\x20progress:','img','appendChild','onload','checkMatches\x20started','\x20created\x20a\x20match\x20of\x20','touchstart','push','\x22\x20alt=\x22','clearBoardState:\x20Board\x20state\x20cleared\x20successfully\x20for\x20','\x20due\x20to\x20','Drake','Boss\x20','TRY\x20AGAIN','\x20matches:','createCharacter:\x20config=','textContent','getAssets:\x20NFT\x20fetch\x20error\x20for\x20theme\x20','completed','NEXT\x20LEVEL','https://ipfs.io/ipfs/','https://www.skulliance.io/staking/icons/','18aNKOAD','slideTiles','player1','Starting\x20boss\x20battle...','setSelectedCharacter','You\x20Lose!','<p>Tactics:\x20','mouseup','updateOpponentDisplay','Base\x20damage:\x20','Game\x20over,\x20skipping\x20endTurn','boss\x20battle','timeEnd','Fetching\x20NFT\x20characters\x20for\x20policy:\x20','showBossSelect:\x20Back\x20to\x20Themes\x20clicked,\x20cleared\x20boss\x20mode\x20state',',\x20boardState=','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<h2>Select\x20Theme</h2>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div\x20id=\x22boss-battles-button-container\x22\x20style=\x22display:\x20','showBossSelect:\x20Cleared\x20game.playerCharacters\x20before\x20fetching\x20NFTs','maxHealth','\x20(type:\x20','Boss\x20battle\x20begins:\x20','base','getBoundingClientRect','\x20on\x20','\x20uses\x20Heal,\x20restoring\x20',';\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<button\x20id=\x22boss-battles-button\x22\x20class=\x22theme-select-button\x22>Boss\x20Battles</button>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20</div>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div\x20id=\x22theme-options\x22></div>\x0a\x09\x20\x20\x20\x20\x20\x20','\x20has\x20no\x20health\x20left\x20and\x20cannot\x20fight!',';\x20filter:\x20none;\x20border-radius:\x205px\x200\x200\x205px;\x22></div>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20</div>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<table>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<tr><td>Health:</td><td>','battle-damaged/','Game\x20over,\x20skipping\x20recoil\x20animation','\x20after\x20multi-match\x20bonus!','floor','showCharacterSelect:\x20Character\x20selected:\x20','%;\x20background-color:\x20','reset','Progress\x20saved:\x20Level\x20','saveScoreToDatabase','backgroundColor',')\x20has\x20no\x20element\x20to\x20animate','init','\x20but\x20sharpens\x20tactics\x20to\x20','policyIds','p1-powerup','savePlayerHealth:\x20Failed\x20to\x20save\x20health:','p2-strength','Random\x20player\x20orientation\x20resolved\x20to:\x20','POST','loadBoardState:\x20Loading\x20-\x20key=','extension','targetTile','Cascading\x20tiles','boss-options','saveProgress','Total\x20tiles\x20matched\x20from\x20player\x20move:\x20','Saving\x20score:\x20level=','\x20goes\x20first!','game-over','clearBoardState:\x20Error\x20clearing\x20board\x20state:','score','#F44336','icons/skull.png','Small','grayscale(100%)\x20sepia(100%)\x20hue-rotate(0deg)\x20saturate(500%)','cascadeTilesWithoutRender','isArray','boostActive','cascadeTiles','innerHTML','ajax/save-monstrocity-progress.php','</p>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20','usePowerup','policy','init:\x20Prompting\x20with\x20loadedLevel=','GET','orientations',',\x20health=','ajax/save-monstrocity-score.php','Selected\x20Character:','saveBoardState:\x20Board\x20state\x20saved\x20successfully\x20for\x20','images/monstrocity/bosses/battle-damaged/','boss_board_','first-attack','<p>Power-Up:\x20','loadBoardState:\x20Error\x20loading\x20board\x20state:','savePlayerHealth','toggleGameButtons:\x20game-container\x20not\x20found','saveBossHealth:\x20Boss\x20health\x20saved\x20successfully','warn','saveBossHealth:\x20Not\x20a\x20boss\x20battle,\x20skipping','Koipon','tileSizeWithGap','coordinates','clearBoardState:\x20userId\x20not\x20defined.\x20Using\x20\x22guest\x22\x20as\x20fallback.','saveBossHealth:\x20Failed\x20to\x20save\x20boss\x20health:','theme-select-container','transform\x200.2s\x20ease','AI\x20Opponent','board_level_','Failed\x20to\x20save\x20boss\x20health:\x20','points','saveBoardState','swapPlayerCharacter','Boss\x20health\x20saved\x20as\x200\x20for\x20boss\x20battle','video','Tile\x20at\x20(','</td></tr>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20</table>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20','tactics','loser',',\x20Grand\x20Total\x20Score:\x20','ajax/get-health.php','translate(0px,\x200px)','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<form\x20action=\x22leaderboards.php\x22\x20method=\x22post\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<input\x20type=\x22hidden\x22\x20name=\x22filterbybosses\x22\x20id=\x22filterbybosses\x22\x20value=\x22weekly-bosses\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<input\x20id=\x22leaderboard\x22\x20type=\x22submit\x22\x20value=\x22LEADERBOARD\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20</form>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20','Error\x20loading\x20health:','startBossBattle','image','game-over-container','\x27\x22></video>','aiTurn','initBoard:\x20Loaded\x20board\x20from\x20localStorage\x20for\x20boss\x20battle','No\x20qualifying\x20NFT\x20characters\x20available\x20for\x20this\x20boss.\x20Please\x20ensure\x20you\x20own\x20the\x20required\x20NFTs.','currentLevel','Craig','visible','\x20vs\x20','getAssets:\x20Theme\x20not\x20found:\x20','body','Error\x20saving\x20to\x20database:','p1-type','savePlayerHealth:\x20Health\x20saved\x20successfully','ajax/save-boss-health.php','updateHealth',',\x20currentLevel=','showProgressPopup',',\x20gameState=','stringify',',\x20bossId=','Resume','Boss\x20Battle\x20-\x20','https://www.skulliance.io/staking/sounds/badmove.ogg','log','gameOver','matches','toggleGameButtons:\x20One\x20or\x20more\x20buttons\x20not\x20found','Dankle','setItem',',\x20tiles\x20to\x20clear:','Error\x20updating\x20theme\x20assets:','handleTouchMove','Server\x20error','parentNode','updateTheme:\x20Skipped\x20due\x20to\x20pending\x20update','function','toLowerCase','progress','loadBoardState','setSelectedBoss','.tile','p1-image','name','Monstrocity\x20timeout','Base','Player\x201\x20media\x20clicked','character-options','createCharacter','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>No\x20bosses\x20available.</p>','setBackground:\x20Attempting\x20for\x20theme=','Board\x20state\x20cleared\x20for\x20','resolveMatches\x20started,\x20gameOver:','saveBoardState:\x20Saving\x20-\x20key=','Error\x20loading\x20board\x20state:\x20','background','toggleGameButtons','Failed\x20to\x20load\x20battle-damaged\x20video:\x20','\x20uses\x20Last\x20Stand,\x20dealing\x20','8SoDJqD','IMG','min','totalTiles','</td></tr>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<tr><td>Tactics:</td><td>','Processing\x20match:','addEventListener','value','s\x20linear','https://www.skulliance.io/staking/sounds/hypercube_create.ogg','glow-power-up','battle-log','ajax/get-monstrocity-assets.php','initGame','\x20HP!','try-again','checkGameOver\x20skipped:\x20gameOver=','showProgressPopup:\x20User\x20chose\x20Restart','visibility','startBossBattle:\x20Immediate\x20game\x20over\x20-\x20player1.health=','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>Loading\x20new\x20characters...</p>','</strong></p>','Multi-Match!\x20','\x20uses\x20Minor\x20Regen,\x20restoring\x20','clearProgress','healthPercentage','children','message','selectedCharacter','roundStats','progress-modal-buttons','Boss\x20health\x20saved:\x20','items','https://ipfs5.jpgstoreapis.com/ipfs/',',\x20refresh:\x20','saveBossHealth:\x20Response\x20status:','preventDefault','Turn\x20switched\x20to\x20','finalWin','Progress\x20saved:\x20currentLevel=','\x20characters','#4CAF50','p1-name','#FFC105','saveBossHealth:\x20Error\x20saving\x20boss\x20health:','savePlayerHealth:\x20Not\x20a\x20boss\x20battle,\x20skipping','transform','showBossSelect:\x20No\x20valid\x20NFT\x20characters\x20returned','cloneNode','Player\x201',',\x20healthPercentage=','startsWith','px,\x200)\x20scale(1.05)','https://www.skulliance.io/staking/sounds/powergem_created.ogg','status','No\x20userId\x20or\x20bossId,\x20using\x20max\x20health','showCharacterSelect','tagName','showBossSelect:\x20No\x20bosses\x20returned','\x27s\x20Last\x20Stand\x20mitigates\x20','querySelectorAll','add','catch','winner','time','addEventListeners:\x20Switch\x20Monster\x20button\x20clicked','\x22\x20onerror=\x22this.src=\x27','\x20starts\x20at\x20full\x20strength\x20with\x20','149553FtCCUo','Calculating\x20round\x20score:\x20points=','firstChild','updateCharacters_','muted','Game\x20over,\x20skipping\x20cascadeTiles','\x20(50%\x20bonus\x20for\x20match-4)','level','Player\x202\x20Details:','button','replaceChild','px,\x200)','display','.game-container','glow-','updateTheme:\x20Board\x20rendered\x20for\x20active\x20game','height','</strong></p>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div><img\x20src=\x22','element','removeItem',',\x20generating\x20new\x20board',',\x20loadedScore=','gameTheme','board','handleGameOverButton','showCharacterSelect:\x20No\x20characters\x20available,\x20using\x20fallback','endTurn','You\x20Win!','px)\x20scale(1.05)','Large','Boost\x20Attack','Failed\x20to\x20load\x20battle-damaged\x20image:\x20','showCharacterSelect:\x20this.player1\x20set:\x20','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>No\x20characters\x20available.\x20Please\x20try\x20another\x20theme.</p>','removeEventListener','handleMatch','checkMatches','Slash','handleGameOverButton\x20completed:\x20currentLevel=','Error\x20clearing\x20board\x20state:\x20','boss-battles-button',',\x20selectedBoss=','\x27s\x20Boost\x20fades.','bounty','/monstrocity.png','progress-resume','saveBoard','selectedBoss','Bite','Navigating\x20to\x20boss\x20selection\x20screen','Game\x20over\x20detected\x20during\x20match\x20processing,\x20stopping\x20further\x20processing','src','getTileFromEvent','loadBoardState:\x20Not\x20a\x20boss\x20battle,\x20skipping','initializing','multiMatch','\x27s\x20Turn','getItem','boss-select-container','Error\x20clearing\x20progress:','renderBoard','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p><strong>','Error\x20in\x20checkMatches:','lastChild','Resumed\x20at\x20Level\x20','playerTurn','Level\x20','orientation','Animating\x20matched\x20tiles,\x20allMatchedTiles:','\x20uses\x20Regen,\x20restoring\x20','DOMContentLoaded','theme-select-button','Monstrocity\x20HTTP\x20error!\x20Status:\x20','substring','user_id=','ipfs','isLoggedIn','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<img\x20src=\x22','\x20(ID:\x20','title','autoplay','backgroundPosition','isCheckingGameOver','offsetWidth','init:\x20Starting\x20async\x20initialization','ajax/clear-monstrocity-progress.php','guest','initGame:\x20Cleared\x20selectedBoss\x20and\x20selectedCharacter','<img\x20loading=\x22eager\x22\x20src=\x22','progress-message','Game\x20Over','Error\x20saving\x20health:\x20','\x20(100%\x20bonus\x20for\x20match-5+)','Minor\x20Régén','savePlayerHealth:\x20Response\x20status:','round','dragDirection','START\x20OVER','currentTurn','row',',\x20reduced\x20by\x20','\x20uses\x20Power\x20Surge,\x20next\x20attack\x20+','showCharacterSelect:\x20Checking\x20for\x20selectedBoss:','Error\x20saving\x20boss\x20health:\x20','showBossSelect:\x20Rendered\x20','https://www.skulliance.io/staking/sounds/badgeawarded.ogg','Failed\x20to\x20preload\x20image:\x20','handleMouseDown','\x20\x20Health:','offsetY','application/x-www-form-urlencoded','refreshBoard:\x20Board\x20state\x20preserved','Player\x201\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(loss),\x20boss\x20mode=','json','&health=','px,\x20','touchend','drops\x20health\x20to\x20','theme-options','removeChild','updateTheme:\x20Skipping\x20board\x20render,\x20no\x20active\x20game\x20or\x20boss\x20battle\x20in\x20progress','getAssets_','lastStandActive','Boss\x20Theme:','savePlayerHealth:\x20Saving\x20-\x20userId=','loadBoardState:\x20No\x20saved\x20board\x20state\x20found','power-up','addEventListeners',',\x20selected\x20theme=','#FFA500','updateTileSizeWithGap','loadBoard','loadBoardState:\x20userId\x20not\x20defined.\x20Using\x20\x22guest\x22\x20as\x20fallback.','checkGameOver\x20started:\x20currentLevel=','backgroundImage','clearBoardState','\x20uses\x20','Texby','Billandar\x20and\x20Ted','Mandiblus','resolve',',\x20rows\x20','abs','Final\x20level\x20completed!\x20Final\x20score:\x20',')\x20to\x20(','url(','\x20HP\x20for\x20','Right','onclick','initGame:\x20Started\x20with\x20this.currentLevel=','turn-indicator','clearBoardState:\x20Not\x20a\x20boss\x20battle,\x20skipping','trim','https://www.skulliance.io/staking/sounds/skullcoinlose.ogg','Loaded\x20opponent\x20for\x20level\x20','policyId','No\x20matches\x20found,\x20returning\x20false','Boost\x20applied,\x20damage:\x20','Calling\x20checkGameOver\x20from\x20handleMatch','Board\x20state\x20saved\x20for\x20','Minor\x20Regen','clearBoard','MonstrocityMatch3:\x20Selected\x20boss\x20set\x20to\x20','Error\x20saving\x20progress:','NFT_Unknown_','</td></tr>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<tr><td>Size:</td><td>','\x20NFT\x20characters','35VwBhAh','split','showProgressPopup:\x20Displaying\x20popup\x20for\x20level=','Is\x20initial\x20move:\x20','initBoard:\x20Loaded\x20board\x20from\x20localStorage\x20for\x20level','Boss','images/monstrocity/','<video\x20src=\x22','https://www.skulliance.io/staking/images/monstrocity/','race','win','application/json','refreshBoard','p2-health','some','Player\x202\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(win)','saveBossHealth',',\x20Total\x20damage:\x20','checkGameOver',',\x20transform:\x20','Loaded\x20saved\x20health:\x20','ajax/save-health.php','map','Round\x20Score\x20Formula:\x20(((','\x22\x20onerror=\x22this.src=\x27icons/skull.png\x27\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p>','remove','selectedTile','badMove','\x27s\x20','showProgressPopup:\x20User\x20chose\x20Resume','\x22\x20autoplay\x20loop\x20muted\x20alt=\x22','initBoard:\x20Generated\x20and\x20saved\x20new\x20board\x20for\x20level','p2-image','click','game-board','charCodeAt','player2','type','showThemeSelect:\x20Cleared\x20boss\x20mode\x20state\x20(selectedBoss,\x20selectedCharacter,\x20board\x20state)','Round\x20points\x20increased\x20from\x20','showCharacterSelect:\x20Theme\x20game\x20initial\x20-\x20setting\x20player1\x20and\x20starting\x20game','\x20+\x2020))\x20*\x20(1\x20+\x20','Health\x20saved\x20as\x200\x20for\x20boss\x20battle','checkGameOver\x20completed:\x20gameOver=','monstrocity','flipCharacter','updatePlayerDisplay','showBossSelect:\x20Loaded\x20','play','endTurn:\x20Player\x20turn\x20ending,\x20saving\x20board\x20state','boss-close-button','updateTheme_','showThemeSelect','none','scaleX','p2-speed','updateTheme','progress-modal-content','createRandomTile','Opponent',',\x20damage:\x20','health','\x20(originally\x20','\x27s\x20tactics\x20halve\x20the\x20blow,\x20taking\x20only\x20','flatMap','character-select-container','Parsed\x20response:','strength','attempts','Raw\x20response\x20text:','flex','createElement','px)','Tactics\x20applied,\x20damage\x20reduced\x20to:\x20','</td></tr>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<tr><td>Powerup:</td><td>','progress-modal','mousedown','<p>Type:\x20','\x20Score:\x20','\x20\x20Strength:','showCharacterSelect:\x20Initial\x20selection','ipfsPrefix','800748UdrZbd','Katastrophy',',\x20matches=','\x20starts\x20with\x20','<p>Health:\x20','then','Reset\x20to\x20Level\x201:\x20currentLevel=','Random','showThemeSelect:\x20Boss\x20Battles\x20button\x20clicked,\x20entering\x20boss\x20mode','has','pop','Player\x201\x20Details:','p1-health','\x20health\x20before\x20match:\x20','Starting\x20fresh\x20at\x20Level\x201','countEmptyBelow','sounds','\x20with\x20Score\x20of\x20','<p>Speed:\x20','initBoard','isBossBattle','showCharacterSelect:\x20Swapping\x20character\x20during\x20game','p1-hp','endTurn:\x20Boss\x20turn\x20ending,\x20saving\x20player\x20health','Error\x20playing\x20lose\x20sound:','Battle\x20Damaged','filter','translate(0,\x20','0.6','div','includes','isNFT','SELECT\x20BOSS','clientX','p2-name','saveBoardState:\x20userId\x20not\x20defined.\x20Using\x20\x22guest\x22\x20as\x20fallback.','Slime\x20Mind','\x20passes...','Horizontal\x20match\x20found\x20at\x20row\x20','constructor:\x20initialTheme=','animateRecoil','backgroundSize','Main:\x20Game\x20instance\x20created','\x20size\x20','block','mousemove','Special\x20attack\x20multiplier\x20applied,\x20damage:\x20','Left','speed','</td></tr>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<tr><td>Speed:</td><td>','</p>','hash','\x20has\x20been\x20defeated\x20before\x20the\x20battle\x20begins!','userId','png','765610WJcpbj','length','scrollTop','showBossSelect:\x20Error\x20fetching\x20NFT\x20characters:','size','powerup','battle-damaged','flip-p2','clearBoardState:\x20Clearing\x20-\x20key=',',\x20Match\x20bonus:\x20','\x20swaps\x20tiles\x20at\x20(','animatePowerup','selected','second-attack','ajax/get-bosses.php',',\x20Completions:\x20','leader','p1-size',',\x20player1.health=','classList','startBossBattle:\x20player2\x20orientation\x20set\x20to\x20','style'];_0x424a=function(){return _0x198092;};return _0x424a();}
  </script>
</body>
</html>