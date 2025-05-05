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
	  
const _0x1fe90e=_0x30ad;function _0x30ad(_0x3adbc8,_0x25ab1d){const _0x21266=_0x2126();return _0x30ad=function(_0x30adbb,_0xf7a524){_0x30adbb=_0x30adbb-0x75;let _0x7cf256=_0x21266[_0x30adbb];return _0x7cf256;},_0x30ad(_0x3adbc8,_0x25ab1d);}(function(_0x17a8c1,_0x24cf59){const _0x2ff029=_0x30ad,_0x3ba0fa=_0x17a8c1();while(!![]){try{const _0x5897f0=parseInt(_0x2ff029(0x21c))/0x1*(-parseInt(_0x2ff029(0x2a0))/0x2)+parseInt(_0x2ff029(0x1b7))/0x3+parseInt(_0x2ff029(0xb6))/0x4*(-parseInt(_0x2ff029(0x36e))/0x5)+parseInt(_0x2ff029(0x18f))/0x6*(-parseInt(_0x2ff029(0x227))/0x7)+parseInt(_0x2ff029(0xea))/0x8+-parseInt(_0x2ff029(0x9a))/0x9*(parseInt(_0x2ff029(0x27b))/0xa)+parseInt(_0x2ff029(0xb2))/0xb*(parseInt(_0x2ff029(0x2ae))/0xc);if(_0x5897f0===_0x24cf59)break;else _0x3ba0fa['push'](_0x3ba0fa['shift']());}catch(_0x699309){_0x3ba0fa['push'](_0x3ba0fa['shift']());}}}(_0x2126,0x40b52));function _0x2126(){const _0x194d63=['<img\x20loading=\x22eager\x22\x20src=\x22','Resumed\x20at\x20Level\x20','png',')\x20has\x20no\x20element\x20to\x20animate','parentNode','refreshBoard:\x20Unsticking\x20game\x20board\x20for\x20boss\x20battle','userId','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p><strong>','lastChild','2929730CbSFEZ','Progress\x20cleared','Random','Progress\x20saved:\x20Level\x20','getAssets:\x20Theme\x20not\x20found:\x20','message','&boss_id=','p1-image','progress-message','<p>Strength:\x20','url(','<video\x20src=\x22','size','power-up','transform','scrollTop','width','NFT_Unknown_','currentTurn','IMG','pop','Game\x20not\x20over,\x20animating\x20attack','matches','Boss\x20selected:','win','toggleGameButtons:\x20Setting\x20buttons\x20for\x20','\x20swaps\x20tiles\x20at\x20(','swapPlayerCharacter','application/json','Cannot\x20save\x20health:\x20User\x20not\x20logged\x20in\x20or\x20userId\x20missing.','ajax/get-monstrocity-assets.php','HTTP\x20error!\x20Status:\x20','VIDEO','\x20size\x20','includes','</strong></p>','Starting\x20fresh\x20at\x20Level\x201','1878NnkqGo','toFixed','Is\x20initial\x20move:\x20','\x27\x22>','gameState','items','getTileFromEvent','winner','p1-health','&health=','Error\x20clearing\x20progress:','boosts\x20health\x20to\x20','Minor\x20Regen','Dankle','10916364qVjdZZ','\x20\x20Health:','special-attack','mousedown','Error\x20saving\x20to\x20database:','Cascade\x20complete,\x20ending\x20turn','mov','Small','showThemeSelect:\x20Boss\x20Battles\x20button\x20clicked,\x20entering\x20boss\x20mode','\x22\x20onerror=\x22this.src=\x27staking/icons/skull.png\x27\x22></div>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div\x20class=\x22health-bar\x22\x20style=\x22margin-bottom:\x2010px;\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div\x20class=\x22health\x22\x20style=\x22width:\x20','Cascading\x20tiles','hash',',\x20player2.health=','showCharacterSelect:\x20this.player1\x20set:\x20','ajax/save-monstrocity-score.php','\x20/\x20','healthPercentage','fallbackUrl','last-stand','race','loadBoardState:\x20Loading\x20-\x20key=','Total\x20damage\x20dealt:\x20','hyperCube','handleMouseMove','Level\x20','renderBoard:\x20Board\x20not\x20initialized,\x20skipping\x20render','icons/skull.png','Error\x20in\x20checkMatches:','\x20steps\x20into\x20the\x20fray\x20with\x20','max','showProgressPopup:\x20Displaying\x20popup\x20for\x20level=','Tile\x20at\x20(','\x27s\x20Last\x20Stand\x20mitigates\x20','\x27s\x20tactics)','Minor\x20Régén','then','mousemove','Right','0.6','POST','NFT\x20HTTP\x20error!\x20Status:\x20',',\x20selectedBoss=','text','random','click','createElement','boss-close-button','showBossSelectionScreen:\x20Boss\x20select\x20container\x20(#boss-select-container)\x20not\x20found','saveBoardState:\x20Board\x20state\x20saved\x20successfully\x20for\x20',',\x20health=','tileSizeWithGap','progress-start-fresh','progress','Game\x20over,\x20skipping\x20cascade\x20resolution','init','showThemeSelect:\x20Cleared\x20boss\x20mode\x20state\x20(selectedBoss,\x20selectedCharacter,\x20board\x20state)','p1-name',',\x20storedTheme=','No\x20NFT\x20characters\x20available\x20for\x20this\x20boss.','selectedCharacter','https://ipfs5.jpgstoreapis.com/ipfs/','Game\x20over,\x20exiting\x20resolveMatches','Game\x20over,\x20skipping\x20endTurn','Game\x20over,\x20skipping\x20recoil\x20animation','No\x20saved\x20progress\x20found,\x20starting\x20at\x20Level\x201','\x27s\x20tactics\x20halve\x20the\x20blow,\x20taking\x20only\x20','\x27s\x20','findAIMove','Main:\x20Error\x20initializing\x20game:','image','loadBoardState:\x20Board\x20state\x20loaded\x20successfully:','handleMouseUp','transform\x20','showCharacterSelect:\x20Checking\x20for\x20selectedBoss:','Health\x20saved\x20as\x200\x20for\x20boss\x20battle','<p>Speed:\x20','status','Katastrophy','board_level_','Game\x20over\x20after\x20processing\x20matches,\x20exiting\x20resolveMatches','translate(','block','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>No\x20bosses\x20available.</p>','Error\x20updating\x20theme\x20assets:','Tactics\x20applied,\x20damage\x20reduced\x20to:\x20','split','clearBoardState:\x20Clearing\x20-\x20key=','handleGameOverButton',';\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<button\x20id=\x22boss-battles-button\x22\x20class=\x22theme-select-button\x22>Boss\x20Battles</button>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20</div>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div\x20id=\x22theme-options\x22></div>\x0a\x09\x20\x20\x20\x20\x20\x20','visible','\x20-\x20','catch','getItem','clientX','second-attack','battleDamagedUrl','loadProgress','ipfsPrefixes','boostValue','Slime\x20Mind','Error\x20saving\x20boss\x20health:\x20','createCharacter:\x20config=','logo.png','scaleX','playerCharacters','height','Monstrocity_Unknown_','Error\x20loading\x20progress:','substring','classList','Final\x20level\x20completed!\x20Final\x20score:\x20','Mega\x20Multi-Match!\x20','Error\x20playing\x20lose\x20sound:','p2-image','backgroundColor','https://www.skulliance.io/staking/sounds/voice_go.ogg','ajax/get-nft-assets.php','game-over-container','board','isLoggedIn','updateTheme:\x20Boss\x20battle\x20context,\x20preserving\x20selectedBoss\x20and\x20selectedCharacter','p2-strength','Boss\x20Battle\x20-\x20','cover','</td></tr>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<tr><td>Powerup:</td><td>','\x20(ID:\x20','https://ipfs.io/ipfs/',',\x20gameState=','initializing','matched','offsetY','https://www.skulliance.io/staking/sounds/skullcoinlose.ogg','toLowerCase','isArray','baseImagePath','<p>Size:\x20','filter','savePlayerHealth:\x20Health\x20saved\x20successfully','clearBoardState:\x20userId\x20not\x20defined.\x20Using\x20\x22guest\x22\x20as\x20fallback.','Boss\x20battle\x20begins:\x20','tileTypes','showBossSelectionScreen','Boost\x20applied,\x20damage:\x20','\x20(100%\x20bonus\x20for\x20match-5+)','getAssets_','transition','getBoundingClientRect',',\x20Matches:\x20','some','#4CAF50','boostActive','updateTheme:\x20Skipped\x20due\x20to\x20pending\x20update','Turn\x20switched\x20to\x20','Error\x20clearing\x20board\x20state:\x20','Boss','\x20goes\x20first!','checkGameOver\x20started:\x20currentLevel=','showBossSelect:\x20No\x20bosses\x20returned','first-attack','Main:\x20Game\x20instance\x20created','Player\x202\x20Details:','initGame:\x20Cleared\x20selectedBoss\x20and\x20selectedCharacter','images/monstrocity/','replaceChild','indexOf','\x22\x20data-project=\x22','updateTheme:\x20Reset\x20player2\x20to\x20default\x20opponent:\x20','...',',\x20boardState=','\x22\x20autoplay\x20loop\x20muted\x20alt=\x22','endTurn:\x20Boss\x20turn\x20ending,\x20saving\x20player\x20health','Navigating\x20to\x20boss\x20selection\x20screen','showCharacterSelect:\x20Theme\x20game\x20initial\x20-\x20setting\x20player1\x20and\x20starting\x20game','showBossSelect:\x20Error\x20fetching\x20bosses:','startBossBattle:\x20player1\x20orientation\x20set\x20to\x20','initBoard','loser','completed','isDragging','removeItem','Failed\x20to\x20save\x20boss\x20health:\x20','success','Processing\x20match:','Shadow\x20Strike',',\x20level=','showProgressPopup','resolveMatches\x20started,\x20gameOver:','<p>Type:\x20','powerup','showProgressPopup:\x20User\x20chose\x20Resume','Player\x201','ipfs','190415TlTjik','change-character',';\x20filter:\x20none;\x20border-radius:\x205px\x200\x200\x205px;\x22></div>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20</div>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<table>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<tr><td>Health:</td><td>','onclick','glow-','div','resolveMatches','showCharacterSelect:\x20Character\x20selected:\x20','showCharacterSelect:\x20Initial\x20selection','display','application/x-www-form-urlencoded','updatePlayerDisplay','\x27s\x20Boost\x20fades.','isInitialMove:','Goblin\x20Ganger','updateHealth','\x20uses\x20Regen,\x20restoring\x20','addEventListener',',\x20using\x20fallback','maxTouchPoints','Starting\x20boss\x20battle...',',\x20loadedScore=','Error\x20saving\x20score:\x20','Regenerate','img','getElementById','Error\x20loading\x20health:','currency','selected','backgroundSize','MonstrocityMatch3:\x20Selected\x20boss\x20set\x20to\x20','showBossSelect:\x20Entering\x20boss\x20mode','\x20\x20Name:','tagName','boss-select-container','boss-options','loop','targetTile','\x20tiles!','START\x20OVER','Mandiblus','init:\x20Async\x20initialization\x20completed','flex','firstChild','theme-option','9YjZPqG','ontouchstart','toggleGameButtons:\x20Buttons\x20set\x20-\x20restart:\x20','abs','dataset','constructor:\x20initialTheme=','showProgressPopup:\x20User\x20chose\x20Restart','playerCharactersConfig','\x20on\x20','Game\x20over,\x20skipping\x20cascadeTiles','updateTheme','clearBoardState','p2-size','insertBefore','guest','getAssets:\x20NFT\x20fetch\x20error\x20for\x20theme\x20','Preloaded\x20image:\x20','clientY','player2','Board\x20state\x20saved\x20for\x20','\x20damage!','game-board','saveScoreToDatabase','saveBoardState:\x20userId\x20not\x20defined.\x20Using\x20\x22guest\x22\x20as\x20fallback.','11PguLfs','saveBossHealth:\x20Failed\x20to\x20save\x20boss\x20health:','<p>Power-Up:\x20','level','44LBmaSN','\x20after\x20multi-match\x20bonus!','Game\x20Over','checkMatches\x20started','base','Horizontal\x20match\x20found\x20at\x20row\x20','stringify','Slash','\x20uses\x20Power\x20Surge,\x20next\x20attack\x20+','tile\x20','trim','charCodeAt','glow-power-up','\x20uses\x20Heal,\x20restoring\x20','\x20starts\x20with\x20','\x27s\x20Turn','has','imageUrl','createCharacter','ajax/save-monstrocity-progress.php','Monstrocity\x20timeout','ajax/clear-monstrocity-progress.php','animating','\x27s\x20orientation\x20flipped\x20to\x20','character-option','getAssets:\x20Fetching\x20Monstrocity\x20assets','</td></tr>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<tr><td>Strength:</td><td>','</p>','removeChild','refresh-board','images/monstrocity/bosses/battle-damaged/','tactics','init:\x20Starting\x20async\x20initialization','grayscale(100%)\x20sepia(100%)\x20hue-rotate(0deg)\x20saturate(500%)','\x20vs\x20','https://www.skulliance.io/staking/sounds/speedmatch1.ogg','bounty','\x22\x20alt=\x22','appendChild','handleTouchEnd','toString','finalWin','Game\x20over\x20detected\x20during\x20match\x20processing,\x20stopping\x20further\x20processing','badMove','Unknown\x20error','\x20damage,\x20but\x20','Health\x20saved:\x20','orientation','canMakeMatch','boss\x20battle','opacity','touches','1008424ctrBVA','Player\x201\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(loss),\x20boss\x20mode=','Starting\x20Level\x20','\x20has\x20been\x20defeated\x20before\x20the\x20battle\x20begins!','Clearing\x20matched\x20tiles:','createBoardHash','Failed\x20to\x20preload\x20image:\x20','pointerEvents','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>No\x20characters\x20available.\x20Please\x20try\x20another\x20theme.</p>','theme',',\x20Score\x20','ajax/save-health.php','loadBoardState:\x20Not\x20a\x20boss\x20battle,\x20skipping','\x20but\x20dulls\x20tactics\x20to\x20','parse','none','clearProgress','Starting\x20Boss\x20Battle...','\x20uses\x20Last\x20Stand,\x20dealing\x20','Resume',',\x20refresh:\x20','isTouchDevice','\x20uses\x20','NEXT\x20LEVEL','handleTouchMove','type','showBossSelect','column','showCharacterSelect','innerHTML','background','mediaType','length','character-options','Error\x20saving\x20progress:',',\x20cols\x20','Boss\x20','policyId','updateTheme_','Damage\x20from\x20match:\x20','Craig','p2-name','saveBossHealth','backgroundPosition','</td></tr>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<tr><td>Size:</td><td>','startBossBattle','Score\x20Not\x20Saved:\x20','timeEnd','animateAttack','px)\x20scale(1.05)',',\x20rows\x20','Jarhead','monstrocity','loadBoard','saveBoardState:\x20Saving\x20-\x20key=','playerHealth','\x20HP\x20for\x20','checkGameOver','Heal','boss_board_','Player','You\x20Win!','updateTheme:\x20Not\x20a\x20boss\x20battle,\x20clearing\x20selectedBoss\x20and\x20selectedCharacter','roundStats','gameTheme','<p>Health:\x20','savePlayerHealth:\x20Response\x20status:','theme-group','selectedBoss','Error\x20loading\x20NFT\x20characters.\x20Please\x20try\x20again.','loadBoard:\x20Invalid\x20hash\x20for\x20level\x20','Failed\x20to\x20load\x20battle-damaged\x20video:\x20','grandTotalScore','top','<p><strong>','Error\x20loading\x20board\x20state:\x20','Round\x20Score\x20Formula:\x20(((','Main:\x20Game\x20initialized\x20successfully','attempts','.game-logo','https://www.skulliance.io/staking/sounds/powergem_created.ogg','Battle\x20Damaged','https://www.skulliance.io/staking/sounds/badgeawarded.ogg','grayscale(100%)','flip-p1','initBoard:\x20Loaded\x20board\x20from\x20localStorage\x20for\x20level','getAssets:\x20Monstrocity\x20fetch\x20error:','Calculating\x20round\x20score:\x20points=','Found\x20','extension','toggleGameButtons:\x20game-container\x20not\x20found','touchmove','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>Error:\x20Boss\x20selection\x20UI\x20unavailable.</p>','createDocumentFragment','handleMouseDown','Animating\x20recoil\x20for\x20defender:','p1-speed','msMaxTouchPoints','Spydrax','Progress\x20saved:\x20currentLevel=','renderBoard:\x20Board\x20rendered\x20successfully','NFT\x20characters\x20response:','onerror','\x20passes...','maxHealth','min','coordinates','init:\x20Prompting\x20with\x20loadedLevel=','loadBoardState:\x20Error\x20loading\x20board\x20state:','initBoard:\x20Generated\x20new\x20board\x20for\x20boss\x20battle','user_id=','p1-strength',',\x20reduced\x20by\x20','\x20for\x20','Refresh\x20Board\x20button\x20clicked','</td></tr>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<tr><td>Speed:</td><td>','#FFC105','boss-battles-button','forEach','backgroundImage','error','style','drops\x20health\x20to\x20','toggleGameButtons:\x20One\x20or\x20more\x20buttons\x20not\x20found','initGame','Selected\x20Character:','onload','p2-powerup','savePlayerHealth:\x20Not\x20a\x20boss\x20battle,\x20skipping','countEmptyBelow','title','health','\x20has\x20no\x20health\x20left\x20and\x20cannot\x20fight!','play','Response\x20status:',',\x20score=','flatMap','saveProgress','translate(0,\x20','#F44336','Boost\x20Attack','https://www.skulliance.io/staking/icons/','player1','round','Drake','querySelector','p1-hp','policyIds','initBoard:\x20Attempting\x20to\x20load\x20board\x20state\x20for\x20boss\x20battle','dragDirection','reset','checkMatches','NFT\x20timeout','p1-powerup','clearBoard','cascadeTiles','https://www.skulliance.io/staking/sounds/badmove.ogg','select-boss-button','\x20and\x20preparing\x20to\x20mitigate\x205\x20damage\x20on\x20the\x20next\x20attack!','removeEventListener','floor','<p>Tactics:\x20','Multi-Match!\x20','\x20bosses','battle-damaged','65922PCbMLi','Medium','checkGameOver\x20completed:\x20gameOver=',',\x20currentLevel=','handleGameOverButton\x20started:\x20currentLevel=','cascade','savePlayerHealth','Game\x20over,\x20skipping\x20match\x20animation\x20and\x20cascading','endTurn:\x20Player\x20turn\x20ending,\x20saving\x20board\x20state','\x22\x20onerror=\x22this.src=\x27','You\x20Lose!','renderBoard','refreshBoard','boss-option','showCharacterSelect:\x20Boss\x20battle\x20initial\x20-\x20calling\x20setSelectedCharacter','\x20+\x2020))\x20*\x20(1\x20+\x20','Bite','Loaded\x20saved\x20health:\x20','clearBoardState:\x20Board\x20state\x20cleared\x20successfully\x20for\x20','Koipon','animatePowerup','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>Error\x20loading\x20bosses.\x20Please\x20try\x20again.</p>','\x20(originally\x20',',\x20Completions:\x20','Large','Failed\x20to\x20preload:\x20','loadBoardState','\x20HP!','No\x20progress\x20found\x20or\x20status\x20not\x20success:','No\x20NFT\x20characters\x20available\x20for\x20this\x20boss.\x20The\x20server\x20returned\x20an\x20invalid\x20response.','name','Total\x20tiles\x20matched\x20from\x20player\x20move:\x20','Sending\x20saveProgress\x20request\x20with\x20data:','showCharacterSelect:\x20Swapping\x20character\x20during\x20game','showBossSelectionScreen:\x20Called\x20global\x20showBossSelect','flip-p2','scaleX(-1)','\x20uses\x20Minor\x20Regen,\x20restoring\x20','initBoard:\x20Generated\x20and\x20saved\x20new\x20board\x20for\x20level','aiTurn','1029561mEGTYI',':\x20playerHealth=','setItem','totalTiles','Boss\x20selected:\x20','time','showBossSelectionScreen:\x20showBossSelect\x20function\x20not\x20found','Game\x20over,\x20skipping\x20tile\x20clearing\x20and\x20cascading','showCharacterSelect:\x20Rendered\x20','setSelectedBoss','Ouchie','https://www.skulliance.io/staking/sounds/voice_levelcomplete.ogg','strength','map','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>Loading\x20new\x20characters...</p>','ajax/load-monstrocity-progress.php',',\x20change:\x20','addEventListeners:\x20Player\x201\x20media\x20clicked','value','Failed\x20to\x20save\x20health:\x20','saveBoardState','warn','https://www.skulliance.io/staking/sounds/select.ogg','showBossSelect:\x20Rendered\x20',',\x20Health\x20Left:\x20','p2-type','querySelectorAll','replace','theme\x20game',',\x20isCheckingGameOver=','loss','visibility','inline-block','theme-options','points','px,\x200)','updateTheme:\x20Skipping\x20board\x20render,\x20no\x20active\x20game\x20or\x20boss\x20battle\x20in\x20progress','lastStandActive','resolve','saveBoard','preventDefault','usePowerup','\x20is\x20not\x20an\x20array,\x20skipping','match','try-again','/monstrocity.png','json','saveBoardState:\x20Not\x20a\x20boss\x20battle,\x20skipping','leader','applyAnimation','.game-container','ajax/get-health.php','Parsed\x20response:','</td></tr>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<tr><td>Tactics:</td><td>','startBossBattle:\x20Theme\x20mismatch\x20(current:\x20',',\x20Total\x20damage:\x20','p2-tactics','find','),\x20updating\x20to\x20boss\x20theme','.png','Texby','button','currentLevel','Server\x20error','savePlayerHealth:\x20userId\x20not\x20defined.\x20Ensure\x20window.userId\x20is\x20set\x20in\x20PHP.','px)','\x20due\x20to\x20','</td></tr>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<tr><td>Bounty:</td><td>','touchstart','\x20damage,\x20resulting\x20in\x20','body','animateRecoil','Monstrocity\x20HTTP\x20error!\x20Status:\x20','\x20Score:\x20','/logo.png','Animating\x20powerup','initGame:\x20Started\x20with\x20this.currentLevel=','progress-modal-content','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<h2>Select\x20Boss</h2>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<button\x20id=\x22boss-close-button\x22\x20class=\x22theme-select-button\x22\x20style=\x22margin-bottom:\x2010px;\x22>Back\x20to\x20Themes</button>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div\x20id=\x22boss-options\x22></div>\x0a\x09\x20\x20\x20\x20\x20\x20','className','handleGameOverButton\x20completed:\x20currentLevel=','endTurn:\x20Player\x20turn\x20ending,\x20saving\x20boss\x20health','handleTouchStart','\x20starts\x20at\x20full\x20strength\x20with\x20','p1-size','character-select-container','Boss\x20health\x20saved\x20as\x200\x20for\x20boss\x20battle','\x20tiles\x20matched\x20for\x20a\x2020%\x20bonus!','MonstrocityMatch3:\x20Selected\x20character\x20set\x20to\x20','battle-damaged/','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<h2>Select\x20Theme</h2>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div\x20id=\x22boss-battles-button-container\x22\x20style=\x22display:\x20','https://www.skulliance.io/staking/sounds/hypercube_create.ogg','Base','toggleGameButtons','createRandomTile','turn-indicator','Round\x20points\x20increased\x20from\x20','Selected\x20Boss:','SELECT\x20BOSS','GET','AI\x20Opponent','42VDxUeK','muted','Restart','updateTileSizeWithGap','updateOpponentDisplay','element',',\x20bossId=','playerTurn','isBossBattle',',\x20transform:\x20','slideTiles','231qINFkM','speed','touchend','left','score','isNFT','Preloaded:\x20','\x20/\x2056)\x20=\x20','No\x20userId\x20or\x20bossId,\x20using\x20max\x20health','#FFA500','checkGameOver:\x20Button\x20clicked,\x20text=','</strong></p>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div><img\x20src=\x22','add','handleMatch','Billandar\x20and\x20Ted','isCheckingGameOver',',\x20Grand\x20Total\x20Score:\x20','autoplay','children','restart','handleMatch\x20started,\x20match:','px,\x200)\x20scale(1.05)','push','Round\x20Score:\x20','saveBossHealth:\x20Error\x20saving\x20boss\x20health:','No\x20match,\x20reverting\x20tiles...','Fetching\x20NFT\x20characters\x20for\x20policy:\x20','theme-select-button','src','images/monstrocity/bosses/','translateX(','\x20HP','cloneNode','setBackground','mouseup','Failed\x20to\x20save\x20progress:','startsWith','DOMContentLoaded','Opponent','showBossSelect:\x20Back\x20to\x20Themes\x20clicked,\x20cleared\x20boss\x20mode\x20state','addEventListeners','progress-modal','checkMatches\x20completed,\x20returning\x20matches:','clearBoardState:\x20Error\x20clearing\x20board\x20state:','translate(0,\x200)','offsetX','startBossBattle:\x20player2\x20orientation\x20set\x20to\x20','alt','Random\x20player\x20orientation\x20resolved\x20to:\x20','\x20but\x20sharpens\x20tactics\x20to\x20','transform\x200.2s\x20ease','Special\x20attack\x20multiplier\x20applied,\x20damage:\x20','Fetching\x20progress\x20from\x20ajax/load-monstrocity-progress.php','video','Base\x20damage:\x20','cascadeTilesWithoutRender',')\x20/\x20100)\x20*\x20(','showThemeSelect','selectedTile','Left','multiMatch','orientations','sounds','log','remove','gameOver','savePlayerHealth:\x20Saving\x20-\x20userId=','project','refreshBoard:\x20Board\x20state\x20preserved','textContent','Merdock','flipCharacter','https://www.skulliance.io/staking/images/monstrocity/','Animating\x20matched\x20tiles,\x20allMatchedTiles:','Leader'];_0x2126=function(){return _0x194d63;};return _0x2126();}function showThemeSelect(_0x3abd21){const _0x559300=_0x30ad;console[_0x559300(0x1bc)](_0x559300(0x260));let _0x1a0ea7=document['getElementById']('theme-select-container');const _0x1804b3=document['getElementById'](_0x559300(0x20c));_0x3abd21[_0x559300(0x12e)]=null,_0x3abd21['selectedCharacter']=null,_0x3abd21[_0x559300(0xa5)](),console[_0x559300(0x266)](_0x559300(0x2e5)),_0x1a0ea7[_0x559300(0x107)]=_0x559300(0x211)+(window[_0x559300(0x325)]?'block':_0x559300(0xf9))+_0x559300(0x306);const _0x12e44a=document[_0x559300(0x86)](_0x559300(0x1d8));_0x1a0ea7[_0x559300(0x163)]['display']=_0x559300(0x2ff),_0x1804b3['style'][_0x559300(0x76)]=_0x559300(0xf9),_0x3abd21[_0x559300(0x214)](![]),themes['forEach'](_0x537ea5=>{const _0x4508da=_0x559300,_0x5b957f=document[_0x4508da(0x2db)](_0x4508da(0x373));_0x5b957f[_0x4508da(0x206)]=_0x4508da(0x12d);const _0xd8ecea=document[_0x4508da(0x2db)]('h3');_0xd8ecea[_0x4508da(0x26c)]=_0x537ea5['group'],_0x5b957f[_0x4508da(0xdc)](_0xd8ecea),_0x537ea5[_0x4508da(0x2a5)][_0x4508da(0x160)](_0x79b936=>{const _0x5a1c6a=_0x4508da,_0x34ed40=document[_0x5a1c6a(0x2db)](_0x5a1c6a(0x373));_0x34ed40['className']=_0x5a1c6a(0x99);if(_0x79b936['background']){const _0x50dc3f='https://www.skulliance.io/staking/images/monstrocity/'+_0x79b936[_0x5a1c6a(0x1c9)]+_0x5a1c6a(0x1e4);_0x34ed40[_0x5a1c6a(0x163)][_0x5a1c6a(0x161)]=_0x5a1c6a(0x285)+_0x50dc3f+')';}const _0x5616af=_0x5a1c6a(0x26f)+_0x79b936[_0x5a1c6a(0x1c9)]+_0x5a1c6a(0x201);_0x34ed40[_0x5a1c6a(0x107)]='\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<img\x20src=\x22'+_0x5616af+'\x22\x20alt=\x22'+_0x79b936[_0x5a1c6a(0x16c)]+_0x5a1c6a(0x353)+_0x79b936[_0x5a1c6a(0x26a)]+'\x22\x20onerror=\x22this.src=\x27icons/skull.png\x27\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p>'+_0x79b936['title']+'</p>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20',_0x34ed40['addEventListener'](_0x5a1c6a(0x2da),()=>{const _0x594bb4=_0x5a1c6a,_0x24560e=document['getElementById'](_0x594bb4(0x10b));_0x24560e&&(_0x24560e[_0x594bb4(0x107)]=_0x594bb4(0x1c5)),_0x1a0ea7[_0x594bb4(0x107)]='',_0x1a0ea7[_0x594bb4(0x163)]['display']='none',_0x1804b3[_0x594bb4(0x163)][_0x594bb4(0x76)]=_0x594bb4(0x2ff),_0x3abd21[_0x594bb4(0xa4)](_0x79b936[_0x594bb4(0x1c9)]);}),_0x5b957f[_0x5a1c6a(0xdc)](_0x34ed40);}),_0x12e44a[_0x4508da(0xdc)](_0x5b957f);});const _0x40fff5=document[_0x559300(0x86)](_0x559300(0x15f));_0x40fff5&&_0x40fff5[_0x559300(0x7e)](_0x559300(0x2da),()=>{const _0x4b3eb8=_0x559300;console['log'](_0x4b3eb8(0x2b6)),showBossSelect(_0x3abd21);}),console['timeEnd'](_0x559300(0x260));}function showBossSelect(_0xc68b54){const _0x568ba6=_0x30ad;console[_0x568ba6(0x1bc)]('showBossSelect');const _0x445b02=document[_0x568ba6(0x86)](_0x568ba6(0x8f)),_0x2269d3=document[_0x568ba6(0x86)]('theme-select-container'),_0x9a8e79=document[_0x568ba6(0x86)](_0x568ba6(0x20c));console['log'](_0x568ba6(0x8c)),_0x445b02[_0x568ba6(0x107)]=_0x568ba6(0x205);const _0x1b2b56=document[_0x568ba6(0x86)](_0x568ba6(0x90));_0x445b02['style']['display']=_0x568ba6(0x2ff),_0x2269d3[_0x568ba6(0x163)]['display']=_0x568ba6(0xf9),_0x9a8e79[_0x568ba6(0x163)][_0x568ba6(0x76)]=_0x568ba6(0xf9);const _0x25c3e9=document[_0x568ba6(0x86)](_0x568ba6(0x2dc));_0x25c3e9[_0x568ba6(0x7e)]('click',()=>{const _0x5d8a2c=_0x568ba6;_0xc68b54[_0x5d8a2c(0x12e)]=null,_0xc68b54[_0x5d8a2c(0x2e9)]=null,_0xc68b54[_0x5d8a2c(0xa5)](),console[_0x5d8a2c(0x266)](_0x5d8a2c(0x24e)),_0x445b02[_0x5d8a2c(0x163)]['display']=_0x5d8a2c(0xf9),_0x2269d3[_0x5d8a2c(0x163)][_0x5d8a2c(0x76)]=_0x5d8a2c(0x2ff),showThemeSelect(_0xc68b54);}),fetch('ajax/get-bosses.php',{'method':_0x568ba6(0x21a),'headers':{'Content-Type':_0x568ba6(0x297)}})[_0x568ba6(0x2d1)](_0xae309a=>{const _0x114315=_0x568ba6;if(!_0xae309a['ok'])throw new Error(_0x114315(0x29a)+_0xae309a['status']);return _0xae309a[_0x114315(0x1e5)]();})['then'](_0xfe9ecb=>{const _0x51264e=_0x568ba6;if(!Array[_0x51264e(0x333)](_0xfe9ecb)||_0xfe9ecb[_0x51264e(0x10a)]===0x0){_0x1b2b56[_0x51264e(0x107)]=_0x51264e(0x300),console[_0x51264e(0x1cc)](_0x51264e(0x34b));return;}const _0x66598e=document[_0x51264e(0x147)]();_0xfe9ecb[_0x51264e(0x160)](_0x3a22bd=>{const _0x43d0ae=_0x51264e;console[_0x43d0ae(0x266)](_0x43d0ae(0x10e)+_0x3a22bd[_0x43d0ae(0x1ad)]+_0x43d0ae(0x1b8)+_0x3a22bd[_0x43d0ae(0x121)]+'\x20(type:\x20'+typeof _0x3a22bd['playerHealth']+')');const _0xb16391=document[_0x43d0ae(0x2db)]('div');_0xb16391['className']=_0x43d0ae(0x19c);const _0x5184e9=_0x3a22bd[_0x43d0ae(0x121)]===0x0,_0x3c177b=_0x3a22bd['health']<=0x0;if(_0x5184e9||_0x3c177b){_0xb16391[_0x43d0ae(0x163)][_0x43d0ae(0xf1)]=_0x43d0ae(0xf9);if(_0x3c177b)_0xb16391[_0x43d0ae(0x163)][_0x43d0ae(0x336)]=_0x43d0ae(0xd7),_0xb16391[_0x43d0ae(0x163)][_0x43d0ae(0xe8)]='0.6';else _0x5184e9&&(_0xb16391[_0x43d0ae(0x163)][_0x43d0ae(0x336)]=_0x43d0ae(0x13d),_0xb16391[_0x43d0ae(0x163)][_0x43d0ae(0xe8)]=_0x43d0ae(0x2d4));}const _0x4dd13f=_0x3a22bd[_0x43d0ae(0xc7)][_0x43d0ae(0x24b)]('/')?_0x3a22bd['imageUrl'][_0x43d0ae(0x31a)](0x1):_0x3a22bd[_0x43d0ae(0xc7)],_0x324865=(_0x3a22bd[_0x43d0ae(0x16d)]||0x0)/(_0x3a22bd[_0x43d0ae(0x152)]||0x64)*0x64;let _0x370eff;if(_0x324865>0x4b)_0x370eff=_0x43d0ae(0x343);else{if(_0x324865>0x32)_0x370eff=_0x43d0ae(0x15e);else _0x324865>0x19?_0x370eff=_0x43d0ae(0x230):_0x370eff=_0x43d0ae(0x175);}_0xb16391[_0x43d0ae(0x107)]=_0x43d0ae(0x279)+_0x3a22bd[_0x43d0ae(0x1ad)]+_0x43d0ae(0x232)+_0x4dd13f+_0x43d0ae(0xdb)+_0x3a22bd[_0x43d0ae(0x1ad)]+_0x43d0ae(0x2b7)+_0x324865+'%;\x20background-color:\x20'+_0x370eff+_0x43d0ae(0x370)+_0x3a22bd[_0x43d0ae(0x16d)]+'/'+_0x3a22bd[_0x43d0ae(0x152)]+_0x43d0ae(0xd0)+_0x3a22bd[_0x43d0ae(0x1c3)]+_0x43d0ae(0x15d)+_0x3a22bd['speed']+_0x43d0ae(0x1ec)+_0x3a22bd[_0x43d0ae(0xd5)]+_0x43d0ae(0x116)+_0x3a22bd[_0x43d0ae(0x287)]+_0x43d0ae(0x32a)+_0x3a22bd[_0x43d0ae(0x36a)]+'</td></tr>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<tr><td>Players:</td><td>'+_0x3a22bd['playerCount']+_0x43d0ae(0x1fa)+_0x3a22bd[_0x43d0ae(0xda)]+'\x20'+_0x3a22bd[_0x43d0ae(0x88)]+'</td></tr>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20</table>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20',!_0x5184e9&&!_0x3c177b&&_0xb16391[_0x43d0ae(0x7e)](_0x43d0ae(0x2da),()=>{const _0x5636d9=_0x43d0ae;console['log'](_0x5636d9(0x1bb)+_0x3a22bd[_0x5636d9(0x1ad)]+_0x5636d9(0x32b)+_0x3a22bd['id']+')'),console[_0x5636d9(0x266)](_0x5636d9(0x241)+_0x3a22bd['policy']),_0x445b02[_0x5636d9(0x163)][_0x5636d9(0x76)]=_0x5636d9(0xf9),_0x9a8e79['style'][_0x5636d9(0x76)]=_0x5636d9(0x2ff),_0xc68b54[_0x5636d9(0x1c0)](_0x3a22bd),fetch(_0x5636d9(0x322),{'method':_0x5636d9(0x2d5),'headers':{'Content-Type':_0x5636d9(0x297)},'body':JSON[_0x5636d9(0xbc)]({'policyIds':[_0x3a22bd['policy']],'theme':_0xc68b54[_0x5636d9(0xf3)]})})['then'](_0x4b30a9=>{const _0x46f0a6=_0x5636d9;if(!_0x4b30a9['ok'])throw new Error(_0x46f0a6(0x29a)+_0x4b30a9[_0x46f0a6(0x2fa)]);return _0x4b30a9[_0x46f0a6(0x1e5)]();})[_0x5636d9(0x2d1)](_0x4b1c88=>{const _0x4ea4e7=_0x5636d9;console['log'](_0x4ea4e7(0x14f),_0x4b1c88);if(_0x4b1c88===![]){console[_0x4ea4e7(0x1cc)]('showBossSelect:\x20get-nft-assets.php\x20returned\x20false'),alert(_0x4ea4e7(0x1ac));return;}if(!Array[_0x4ea4e7(0x333)](_0x4b1c88)||_0x4b1c88['length']===0x0){alert(_0x4ea4e7(0x2e8)),console['warn']('showBossSelect:\x20No\x20NFT\x20characters\x20returned');return;}_0xc68b54[_0x4ea4e7(0x316)]=_0x4b1c88[_0x4ea4e7(0x1c4)](_0x5f2010=>_0xc68b54['createCharacter'](_0x5f2010)),_0xc68b54['showCharacterSelect'](!![]);})[_0x5636d9(0x309)](_0x39d755=>{const _0x5382dd=_0x5636d9;console[_0x5382dd(0x162)]('showBossSelect:\x20Error\x20fetching\x20NFT\x20characters:',_0x39d755),alert(_0x5382dd(0x12f)),_0x9a8e79[_0x5382dd(0x163)][_0x5382dd(0x76)]='none',_0x445b02['style'][_0x5382dd(0x76)]=_0x5382dd(0x2ff);});}),_0x66598e[_0x43d0ae(0xdc)](_0xb16391);}),_0x1b2b56['appendChild'](_0x66598e),console[_0x51264e(0x266)](_0x51264e(0x1ce)+_0xfe9ecb[_0x51264e(0x10a)]+_0x51264e(0x18d));})[_0x568ba6(0x309)](_0x264bab=>{const _0x5982b9=_0x568ba6;console[_0x5982b9(0x162)](_0x5982b9(0x35b),_0x264bab),_0x1b2b56[_0x5982b9(0x107)]=_0x5982b9(0x1a4);}),console[_0x568ba6(0x119)](_0x568ba6(0x104));}const opponentsConfig=[{'name':'Craig','strength':0x1,'speed':0x1,'tactics':0x1,'size':'Medium','type':'Base','powerup':_0x1fe90e(0x2ac),'theme':'monstrocity'},{'name':'Merdock','strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x1fe90e(0x1a7),'type':_0x1fe90e(0x213),'powerup':_0x1fe90e(0x2ac),'theme':'monstrocity'},{'name':_0x1fe90e(0x7b),'strength':0x2,'speed':0x2,'tactics':0x2,'size':'Small','type':_0x1fe90e(0x213),'powerup':_0x1fe90e(0x2ac),'theme':_0x1fe90e(0x11e)},{'name':_0x1fe90e(0x1f3),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x1fe90e(0x190),'type':_0x1fe90e(0x213),'powerup':_0x1fe90e(0x2ac),'theme':'monstrocity'},{'name':_0x1fe90e(0x95),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x1fe90e(0x190),'type':_0x1fe90e(0x213),'powerup':'Regenerate','theme':_0x1fe90e(0x11e)},{'name':'Koipon','strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x1fe90e(0x190),'type':_0x1fe90e(0x213),'powerup':_0x1fe90e(0x84),'theme':_0x1fe90e(0x11e)},{'name':_0x1fe90e(0x311),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x1fe90e(0x2b5),'type':'Base','powerup':_0x1fe90e(0x84),'theme':'monstrocity'},{'name':_0x1fe90e(0x235),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x1fe90e(0x190),'type':_0x1fe90e(0x213),'powerup':_0x1fe90e(0x84),'theme':_0x1fe90e(0x11e)},{'name':'Dankle','strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x1fe90e(0x190),'type':_0x1fe90e(0x213),'powerup':_0x1fe90e(0x176),'theme':_0x1fe90e(0x11e)},{'name':'Jarhead','strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x1fe90e(0x190),'type':'Base','powerup':_0x1fe90e(0x176),'theme':_0x1fe90e(0x11e)},{'name':_0x1fe90e(0x14c),'strength':0x6,'speed':0x6,'tactics':0x6,'size':_0x1fe90e(0x2b5),'type':'Base','powerup':_0x1fe90e(0x124),'theme':_0x1fe90e(0x11e)},{'name':'Katastrophy','strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x1fe90e(0x1a7),'type':'Base','powerup':_0x1fe90e(0x124),'theme':'monstrocity'},{'name':_0x1fe90e(0x1c1),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x1fe90e(0x190),'type':_0x1fe90e(0x213),'powerup':_0x1fe90e(0x124),'theme':_0x1fe90e(0x11e)},{'name':_0x1fe90e(0x17a),'strength':0x8,'speed':0x7,'tactics':0x7,'size':'Medium','type':_0x1fe90e(0x213),'powerup':_0x1fe90e(0x124),'theme':_0x1fe90e(0x11e)},{'name':_0x1fe90e(0x112),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x1fe90e(0x190),'type':_0x1fe90e(0x271),'powerup':'Minor\x20Regen','theme':_0x1fe90e(0x11e)},{'name':_0x1fe90e(0x26d),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0x1fe90e(0x1a7),'type':_0x1fe90e(0x271),'powerup':_0x1fe90e(0x2ac),'theme':_0x1fe90e(0x11e)},{'name':'Goblin\x20Ganger','strength':0x2,'speed':0x2,'tactics':0x2,'size':_0x1fe90e(0x2b5),'type':'Leader','powerup':_0x1fe90e(0x2ac),'theme':_0x1fe90e(0x11e)},{'name':_0x1fe90e(0x1f3),'strength':0x2,'speed':0x2,'tactics':0x2,'size':'Medium','type':_0x1fe90e(0x271),'powerup':_0x1fe90e(0x2d0),'theme':_0x1fe90e(0x11e)},{'name':_0x1fe90e(0x95),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x1fe90e(0x190),'type':_0x1fe90e(0x271),'powerup':_0x1fe90e(0x84),'theme':_0x1fe90e(0x11e)},{'name':_0x1fe90e(0x1a2),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0x1fe90e(0x190),'type':'Leader','powerup':_0x1fe90e(0x84),'theme':_0x1fe90e(0x11e)},{'name':'Slime\x20Mind','strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x1fe90e(0x2b5),'type':'Leader','powerup':'Regenerate','theme':'monstrocity'},{'name':'Billandar\x20and\x20Ted','strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x1fe90e(0x190),'type':_0x1fe90e(0x271),'powerup':_0x1fe90e(0x84),'theme':_0x1fe90e(0x11e)},{'name':_0x1fe90e(0x2ad),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x1fe90e(0x190),'type':_0x1fe90e(0x271),'powerup':_0x1fe90e(0x176),'theme':_0x1fe90e(0x11e)},{'name':_0x1fe90e(0x11d),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0x1fe90e(0x190),'type':_0x1fe90e(0x271),'powerup':_0x1fe90e(0x176),'theme':_0x1fe90e(0x11e)},{'name':_0x1fe90e(0x14c),'strength':0x6,'speed':0x6,'tactics':0x6,'size':'Small','type':_0x1fe90e(0x271),'powerup':_0x1fe90e(0x124),'theme':_0x1fe90e(0x11e)},{'name':_0x1fe90e(0x2fb),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x1fe90e(0x1a7),'type':'Leader','powerup':_0x1fe90e(0x124),'theme':'monstrocity'},{'name':_0x1fe90e(0x1c1),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0x1fe90e(0x190),'type':_0x1fe90e(0x271),'powerup':_0x1fe90e(0x124),'theme':_0x1fe90e(0x11e)},{'name':'Drake','strength':0x8,'speed':0x7,'tactics':0x7,'size':_0x1fe90e(0x190),'type':'Leader','powerup':_0x1fe90e(0x124),'theme':'monstrocity'}],characterDirections={'Billandar\x20and\x20Ted':_0x1fe90e(0x262),'Craig':_0x1fe90e(0x262),'Dankle':_0x1fe90e(0x262),'Drake':_0x1fe90e(0x2d3),'Goblin\x20Ganger':_0x1fe90e(0x262),'Jarhead':_0x1fe90e(0x2d3),'Katastrophy':'Right','Koipon':_0x1fe90e(0x262),'Mandiblus':_0x1fe90e(0x262),'Merdock':_0x1fe90e(0x262),'Ouchie':_0x1fe90e(0x262),'Slime\x20Mind':_0x1fe90e(0x2d3),'Spydrax':'Right','Texby':_0x1fe90e(0x262)};class MonstrocityMatch3{constructor(_0x9cb62e,_0x42fc8f){const _0x4c8d8b=_0x1fe90e;this[_0x4c8d8b(0xff)]=_0x4c8d8b(0x9b)in window||navigator[_0x4c8d8b(0x80)]>0x0||navigator[_0x4c8d8b(0x14b)]>0x0,this[_0x4c8d8b(0x28b)]=0x5,this[_0x4c8d8b(0x317)]=0x5,this[_0x4c8d8b(0x324)]=[],this[_0x4c8d8b(0x261)]=null,this['gameOver']=![],this[_0x4c8d8b(0x28d)]=null,this[_0x4c8d8b(0x178)]=null,this[_0x4c8d8b(0xac)]=null,this['gameState']=_0x4c8d8b(0x32e),this[_0x4c8d8b(0x360)]=![],this[_0x4c8d8b(0x92)]=null,this['dragDirection']=null,this['offsetX']=0x0,this[_0x4c8d8b(0x330)]=0x0,this[_0x4c8d8b(0x1f5)]=0x1,this['playerCharactersConfig']=_0x9cb62e,this['playerCharacters']=[],this[_0x4c8d8b(0x236)]=![],this[_0x4c8d8b(0x33a)]=[_0x4c8d8b(0x34c),'second-attack',_0x4c8d8b(0x2b0),_0x4c8d8b(0x288),_0x4c8d8b(0x2c0)],this[_0x4c8d8b(0x129)]=[],this[_0x4c8d8b(0x132)]=0x0,this['selectedBoss']=null,this[_0x4c8d8b(0x2e9)]=null;const _0x513a98=themes['flatMap'](_0x2f1d7f=>_0x2f1d7f[_0x4c8d8b(0x2a5)])['map'](_0x1526f9=>_0x1526f9['value']),_0x5b8a03=localStorage[_0x4c8d8b(0x30a)](_0x4c8d8b(0x12a));this[_0x4c8d8b(0xf3)]=_0x5b8a03&&_0x513a98[_0x4c8d8b(0x29d)](_0x5b8a03)?_0x5b8a03:_0x42fc8f&&_0x513a98['includes'](_0x42fc8f)?_0x42fc8f:_0x4c8d8b(0x11e),console['log'](_0x4c8d8b(0x9f)+_0x42fc8f+_0x4c8d8b(0x2e7)+_0x5b8a03+',\x20selected\x20theme='+this['theme']),this['baseImagePath']=_0x4c8d8b(0x350)+this[_0x4c8d8b(0xf3)]+'/',this[_0x4c8d8b(0x265)]={'match':new Audio(_0x4c8d8b(0x1cd)),'cascade':new Audio(_0x4c8d8b(0x1cd)),'badMove':new Audio(_0x4c8d8b(0x186)),'gameOver':new Audio('https://www.skulliance.io/staking/sounds/voice_gameover.ogg'),'reset':new Audio(_0x4c8d8b(0x321)),'loss':new Audio(_0x4c8d8b(0x331)),'win':new Audio(_0x4c8d8b(0x1c2)),'finalWin':new Audio(_0x4c8d8b(0x13c)),'powerGem':new Audio(_0x4c8d8b(0x13a)),'hyperCube':new Audio(_0x4c8d8b(0x212)),'multiMatch':new Audio(_0x4c8d8b(0xd9))},this[_0x4c8d8b(0x21f)](),this[_0x4c8d8b(0x24f)]();}[_0x1fe90e(0x1c0)](_0x59c6a5){const _0x100c41=_0x1fe90e;console[_0x100c41(0x266)](_0x100c41(0x292)),console['log'](_0x100c41(0x8d),_0x59c6a5[_0x100c41(0x1ad)]),console[_0x100c41(0x266)](_0x100c41(0x2af),_0x59c6a5[_0x100c41(0x16d)],'/',_0x59c6a5[_0x100c41(0x152)]),console[_0x100c41(0x266)]('\x20\x20Strength:',_0x59c6a5[_0x100c41(0x1c3)]),this['selectedBoss']=_0x59c6a5,console['log'](_0x100c41(0x8b)+_0x59c6a5[_0x100c41(0x1ad)]);}['setSelectedCharacter'](_0x3c935d){const _0x38a90a=_0x1fe90e;this['selectedCharacter']=_0x3c935d,console[_0x38a90a(0x266)](_0x38a90a(0x20f)+_0x3c935d[_0x38a90a(0x1ad)]),this[_0x38a90a(0x117)]();}async['startBossBattle'](){const _0x39f790=_0x1fe90e;console[_0x39f790(0x266)](_0x39f790(0x81)),console['log'](_0x39f790(0x167),this[_0x39f790(0x2e9)]['name']),console[_0x39f790(0x266)](_0x39f790(0x218),this['selectedBoss'][_0x39f790(0x1ad)]),console[_0x39f790(0x266)]('Boss\x20Theme:',this[_0x39f790(0x12e)][_0x39f790(0xf3)]);const _0x23c6e0=this[_0x39f790(0x12e)][_0x39f790(0x143)]||'png',_0x198eb0=this[_0x39f790(0x12e)][_0x39f790(0xc7)]||_0x39f790(0x244)+this[_0x39f790(0x12e)][_0x39f790(0x1ad)][_0x39f790(0x332)]()[_0x39f790(0x1d2)](/ /g,'-')+'.'+_0x23c6e0,_0x17dc30=this['selectedBoss'][_0x39f790(0x30d)]||_0x39f790(0xd4)+this[_0x39f790(0x12e)][_0x39f790(0x1ad)]['toLowerCase']()['replace'](/ /g,'-')+'.'+_0x23c6e0,_0x1ad9d8=[_0x198eb0,_0x17dc30,this[_0x39f790(0x2e9)][_0x39f790(0xc7)]];_0x1ad9d8[_0x39f790(0x160)](_0x50f988=>{const _0x25ae47=_0x39f790,_0x53104f=new Image();_0x53104f[_0x25ae47(0x243)]=_0x50f988,_0x53104f[_0x25ae47(0x168)]=()=>console['log'](_0x25ae47(0xaa)+_0x50f988),_0x53104f[_0x25ae47(0x150)]=()=>console['log'](_0x25ae47(0xf0)+_0x50f988);});if(this['theme']!==this[_0x39f790(0x12e)][_0x39f790(0xf3)]){console['warn'](_0x39f790(0x1ed)+this[_0x39f790(0xf3)]+',\x20boss:\x20'+this[_0x39f790(0x12e)][_0x39f790(0xf3)]+_0x39f790(0x1f1));const _0x2c8b5e=themes[_0x39f790(0x172)](_0x28d1f=>_0x28d1f['items'])[_0x39f790(0x1c4)](_0x3fcd5d=>_0x3fcd5d['value']);this[_0x39f790(0x12e)][_0x39f790(0xf3)]&&_0x2c8b5e[_0x39f790(0x29d)](this[_0x39f790(0x12e)][_0x39f790(0xf3)])&&await this['updateTheme'](this[_0x39f790(0x12e)]['theme'],!![]);}document[_0x39f790(0x17b)](_0x39f790(0x139))['src']='images/monstrocity/'+this[_0x39f790(0xf3)]+_0x39f790(0x201);let _0x39816d=this[_0x39f790(0x12e)][_0x39f790(0xe5)]||_0x39f790(0x2d3);_0x39816d==='Random'&&(_0x39816d=Math['random']()<0.5?'Left':_0x39f790(0x2d3),console[_0x39f790(0x266)]('Random\x20boss\x20orientation\x20resolved\x20to:\x20'+_0x39816d));let _0x2c43b7=this[_0x39f790(0x2e9)][_0x39f790(0xe5)]||_0x39f790(0x27d);_0x2c43b7===_0x39f790(0x27d)&&(_0x2c43b7=Math[_0x39f790(0x2d9)]()<0.5?_0x39f790(0x262):_0x39f790(0x2d3),console['log'](_0x39f790(0x257)+_0x2c43b7));this['player1']={...this[_0x39f790(0x2e9)],'orientation':_0x2c43b7},this[_0x39f790(0xac)]={'name':this[_0x39f790(0x12e)][_0x39f790(0x1ad)],'strength':this['selectedBoss'][_0x39f790(0x1c3)]||0x4,'speed':this[_0x39f790(0x12e)][_0x39f790(0x228)]||0x4,'tactics':this[_0x39f790(0x12e)][_0x39f790(0xd5)]||0x4,'size':this[_0x39f790(0x12e)][_0x39f790(0x287)]||_0x39f790(0x190),'type':_0x39f790(0x348),'powerup':this[_0x39f790(0x12e)][_0x39f790(0x36a)]||_0x39f790(0x2ac),'theme':this['selectedBoss'][_0x39f790(0xf3)]||this[_0x39f790(0xf3)],'imageUrl':_0x198eb0,'extension':_0x23c6e0,'battleDamagedUrl':_0x17dc30,'fallbackUrl':_0x39f790(0x2c8),'orientation':_0x39816d,'health':this['selectedBoss']['health'],'maxHealth':this[_0x39f790(0x12e)][_0x39f790(0x152)],'mediaType':this[_0x39f790(0x12e)][_0x39f790(0x109)]||_0x39f790(0x2f3),'isBossBattle':!![]};const _0x661fb2=window[_0x39f790(0x278)]||null;if(_0x661fb2&&this[_0x39f790(0x12e)]&&this['selectedBoss']['id'])try{const _0x2cbdb2=await fetch(_0x39f790(0x1ea),{'method':_0x39f790(0x2d5),'headers':{'Content-Type':_0x39f790(0x77)},'body':_0x39f790(0x158)+encodeURIComponent(_0x661fb2)+'&boss_id='+encodeURIComponent(this['selectedBoss']['id'])}),_0x4cfacc=await _0x2cbdb2[_0x39f790(0x1e5)]();_0x4cfacc[_0x39f790(0x363)]&&_0x4cfacc[_0x39f790(0x16d)]!==null?(this['player1'][_0x39f790(0x16d)]=_0x4cfacc[_0x39f790(0x16d)],console[_0x39f790(0x266)](_0x39f790(0x1a0)+this[_0x39f790(0x178)][_0x39f790(0x16d)])):(this[_0x39f790(0x178)][_0x39f790(0x16d)]=this[_0x39f790(0x178)]['maxHealth'],console[_0x39f790(0x266)]('No\x20saved\x20health\x20found,\x20using\x20max\x20health'));}catch(_0x4d3073){console[_0x39f790(0x162)](_0x39f790(0x87),_0x4d3073),this['player1'][_0x39f790(0x16d)]=this[_0x39f790(0x178)][_0x39f790(0x152)];}else this[_0x39f790(0x178)][_0x39f790(0x16d)]=this[_0x39f790(0x178)][_0x39f790(0x152)],console[_0x39f790(0x266)](_0x39f790(0x22f));this['player2']['health']=this[_0x39f790(0x12e)][_0x39f790(0x16d)];if(this[_0x39f790(0x178)][_0x39f790(0x16d)]<=0x0||this[_0x39f790(0xac)][_0x39f790(0x16d)]<=0x0){console[_0x39f790(0x266)]('startBossBattle:\x20Immediate\x20game\x20over\x20-\x20player1.health='+this[_0x39f790(0x178)][_0x39f790(0x16d)]+_0x39f790(0x2ba)+this['player2'][_0x39f790(0x16d)]),this[_0x39f790(0x268)]=!![],this[_0x39f790(0x2a4)]='gameOver';const _0x415c03=document[_0x39f790(0x17b)]('.game-container'),_0x57628c=document[_0x39f790(0x86)](_0x39f790(0xaf));_0x415c03['style'][_0x39f790(0x76)]=_0x39f790(0x2ff),_0x57628c['style']['visibility']=_0x39f790(0x307),this[_0x39f790(0x248)](),this[_0x39f790(0x78)](),this[_0x39f790(0x220)]();const _0x4e37b1=document[_0x39f790(0x86)]('p1-image'),_0x37050e=document[_0x39f790(0x86)](_0x39f790(0x31f));_0x4e37b1&&(_0x4e37b1[_0x39f790(0x163)]['transform']=this['player1'][_0x39f790(0xe5)]===_0x39f790(0x262)?_0x39f790(0x1b3):_0x39f790(0xf9),console['log'](_0x39f790(0x35c)+this[_0x39f790(0x178)]['orientation']+',\x20transform:\x20'+_0x4e37b1[_0x39f790(0x163)]['transform']));_0x37050e&&(_0x37050e['style'][_0x39f790(0x289)]=this[_0x39f790(0xac)][_0x39f790(0xe5)]===_0x39f790(0x2d3)?_0x39f790(0x1b3):_0x39f790(0xf9),console['log'](_0x39f790(0x255)+this[_0x39f790(0xac)][_0x39f790(0xe5)]+',\x20transform:\x20'+_0x37050e[_0x39f790(0x163)]['transform']));this[_0x39f790(0x7c)](this[_0x39f790(0x178)]),this[_0x39f790(0x7c)](this[_0x39f790(0xac)]),battleLog[_0x39f790(0x107)]='',gameOver[_0x39f790(0x26c)]='',this[_0x39f790(0x214)](!![]);if(this['player1'][_0x39f790(0x16d)]<=0x0)gameOver['textContent']=_0x39f790(0x199),turnIndicator[_0x39f790(0x26c)]=_0x39f790(0xb8),log(this[_0x39f790(0x178)][_0x39f790(0x1ad)]+_0x39f790(0x16e)),this['sounds']['loss'][_0x39f790(0x16f)]();else{if(this[_0x39f790(0xac)][_0x39f790(0x16d)]<=0x0){gameOver[_0x39f790(0x26c)]=_0x39f790(0x127),turnIndicator['textContent']='Game\x20Over',log(this[_0x39f790(0xac)]['name']+_0x39f790(0xed)),this[_0x39f790(0x265)]['win'][_0x39f790(0x16f)]();let _0x1d091d=this['player2'][_0x39f790(0x30d)]||_0x39f790(0xd4)+this[_0x39f790(0xac)]['name'][_0x39f790(0x332)]()['replace'](/ /g,'-')+'.'+(this[_0x39f790(0xac)]['extension']||_0x39f790(0x274));const _0x32534e=document['getElementById'](_0x39f790(0x31f));_0x32534e[_0x39f790(0x8e)]===_0x39f790(0x28e)&&(_0x32534e[_0x39f790(0x243)]=_0x1d091d,_0x32534e[_0x39f790(0x150)]=()=>{const _0x361800=_0x39f790;_0x32534e['src']=this['player2'][_0x361800(0x2bf)];}),_0x32534e[_0x39f790(0x31b)][_0x39f790(0x233)](_0x39f790(0x35e)),_0x4e37b1['classList'][_0x39f790(0x233)]('winner');}}const _0x16f8bb=document[_0x39f790(0x86)](_0x39f790(0x1e3));_0x16f8bb[_0x39f790(0x26c)]=_0x39f790(0x219);const _0x1cc176=_0x16f8bb[_0x39f790(0x247)](!![]);_0x16f8bb['parentNode'][_0x39f790(0x351)](_0x1cc176,_0x16f8bb),_0x1cc176[_0x39f790(0x7e)](_0x39f790(0x2da),()=>this['showBossSelectionScreen']()),document[_0x39f790(0x86)](_0x39f790(0x323))[_0x39f790(0x163)]['display']='block',this['renderBoard']();return;}console[_0x39f790(0x266)]('Player\x201\x20Details:',{'Name':this[_0x39f790(0x178)][_0x39f790(0x1ad)],'Health':this[_0x39f790(0x178)][_0x39f790(0x16d)]+'/'+this[_0x39f790(0x178)][_0x39f790(0x152)],'Strength':this[_0x39f790(0x178)][_0x39f790(0x1c3)],'Speed':this[_0x39f790(0x178)][_0x39f790(0x228)],'Tactics':this['player1'][_0x39f790(0xd5)],'Size':this[_0x39f790(0x178)][_0x39f790(0x287)],'Type':this[_0x39f790(0x178)]['type'],'Powerup':this['player1']['powerup'],'Theme':this[_0x39f790(0x178)]['theme'],'Orientation':this[_0x39f790(0x178)][_0x39f790(0xe5)]}),console[_0x39f790(0x266)](_0x39f790(0x34e),{'Name':this['player2'][_0x39f790(0x1ad)],'Health':this['player2'][_0x39f790(0x16d)]+'/'+this['player2'][_0x39f790(0x152)],'Strength':this[_0x39f790(0xac)]['strength'],'Speed':this[_0x39f790(0xac)][_0x39f790(0x228)],'Tactics':this[_0x39f790(0xac)]['tactics'],'Size':this[_0x39f790(0xac)]['size'],'Type':this[_0x39f790(0xac)][_0x39f790(0x103)],'Powerup':this[_0x39f790(0xac)]['powerup'],'Theme':this[_0x39f790(0xac)]['theme'],'ImageUrl':this['player2'][_0x39f790(0xc7)],'BattleDamagedUrl':this['player2'][_0x39f790(0x30d)],'Extension':this[_0x39f790(0xac)]['extension'],'Orientation':this['player2'][_0x39f790(0xe5)],'IsBossBattle':this['player2'][_0x39f790(0x224)]});const _0x30a901=document['querySelector'](_0x39f790(0x1e9)),_0x4f938d=document['getElementById'](_0x39f790(0xaf));_0x30a901['style'][_0x39f790(0x76)]='block',_0x4f938d['style'][_0x39f790(0x1d6)]='visible',this[_0x39f790(0x248)](),this['sounds'][_0x39f790(0x180)][_0x39f790(0x16f)](),log(_0x39f790(0xfb)),this[_0x39f790(0x28d)]=this[_0x39f790(0x178)][_0x39f790(0x228)]>this['player2'][_0x39f790(0x228)]?this[_0x39f790(0x178)]:this['player2'][_0x39f790(0x228)]>this[_0x39f790(0x178)]['speed']?this[_0x39f790(0xac)]:this[_0x39f790(0x178)][_0x39f790(0x1c3)]>=this[_0x39f790(0xac)][_0x39f790(0x1c3)]?this['player1']:this['player2'],this[_0x39f790(0x2a4)]=_0x39f790(0x32e),this[_0x39f790(0x268)]=![],this[_0x39f790(0x129)]=[];const _0x463254=document[_0x39f790(0x86)]('p1-image'),_0x32ad80=document[_0x39f790(0x86)](_0x39f790(0x31f));if(_0x463254)_0x463254[_0x39f790(0x31b)]['remove'](_0x39f790(0x2a7),'loser');if(_0x32ad80)_0x32ad80['classList'][_0x39f790(0x267)](_0x39f790(0x2a7),_0x39f790(0x35e));this[_0x39f790(0x78)](),this[_0x39f790(0x220)](),_0x463254&&(_0x463254[_0x39f790(0x163)][_0x39f790(0x289)]=this[_0x39f790(0x178)]['orientation']===_0x39f790(0x262)?_0x39f790(0x1b3):'none',console['log'](_0x39f790(0x35c)+this['player1'][_0x39f790(0xe5)]+',\x20transform:\x20'+_0x463254[_0x39f790(0x163)]['transform'])),_0x32ad80&&(_0x32ad80[_0x39f790(0x163)][_0x39f790(0x289)]=this['player2']['orientation']==='Right'?_0x39f790(0x1b3):_0x39f790(0xf9),console['log']('startBossBattle:\x20player2\x20orientation\x20set\x20to\x20'+this[_0x39f790(0xac)][_0x39f790(0xe5)]+_0x39f790(0x225)+_0x32ad80['style'][_0x39f790(0x289)])),this[_0x39f790(0x7c)](this[_0x39f790(0x178)]),this[_0x39f790(0x7c)](this[_0x39f790(0xac)]),battleLog['innerHTML']='',gameOver['textContent']='',this[_0x39f790(0x214)](!![]),this[_0x39f790(0x178)][_0x39f790(0x287)]!==_0x39f790(0x190)&&log(this[_0x39f790(0x178)][_0x39f790(0x1ad)]+'\x27s\x20'+this[_0x39f790(0x178)][_0x39f790(0x287)]+'\x20size\x20'+(this[_0x39f790(0x178)]['size']==='Large'?'boosts\x20health\x20to\x20'+this[_0x39f790(0x178)][_0x39f790(0x152)]+_0x39f790(0xf7)+this['player1'][_0x39f790(0xd5)]:_0x39f790(0x164)+this['player1']['maxHealth']+_0x39f790(0x258)+this[_0x39f790(0x178)]['tactics'])+'!'),this[_0x39f790(0xac)][_0x39f790(0x287)]!==_0x39f790(0x190)&&log(this['player2'][_0x39f790(0x1ad)]+'\x27s\x20'+this[_0x39f790(0xac)][_0x39f790(0x287)]+_0x39f790(0x29c)+(this[_0x39f790(0xac)][_0x39f790(0x287)]===_0x39f790(0x1a7)?_0x39f790(0x2ab)+this['player2'][_0x39f790(0x152)]+_0x39f790(0xf7)+this[_0x39f790(0xac)][_0x39f790(0xd5)]:'drops\x20health\x20to\x20'+this[_0x39f790(0xac)][_0x39f790(0x152)]+_0x39f790(0x258)+this[_0x39f790(0xac)][_0x39f790(0xd5)])+'!'),log(this['player1'][_0x39f790(0x1ad)]+_0x39f790(0xc4)+this['player1']['health']+'/'+this[_0x39f790(0x178)]['maxHealth']+_0x39f790(0x1aa)),log(this[_0x39f790(0x28d)]['name']+_0x39f790(0x349)),this[_0x39f790(0x35d)](),this[_0x39f790(0x2a4)]=this[_0x39f790(0x28d)]===this['player1']?_0x39f790(0x223):'aiTurn',turnIndicator[_0x39f790(0x26c)]=_0x39f790(0x328)+(this[_0x39f790(0x28d)]===this[_0x39f790(0x178)]?_0x39f790(0x126):_0x39f790(0x348))+_0x39f790(0xc5),this['currentTurn']===this[_0x39f790(0xac)]&&setTimeout(()=>this['aiTurn'](),0x3e8),log(_0x39f790(0x339)+this[_0x39f790(0x178)][_0x39f790(0x1ad)]+'\x20vs\x20'+this[_0x39f790(0xac)][_0x39f790(0x1ad)]+'!');}[_0x1fe90e(0x33b)](){const _0x40c7c4=_0x1fe90e;console['log'](_0x40c7c4(0x359));const _0x58fdba=document[_0x40c7c4(0x17b)](_0x40c7c4(0x1e9)),_0x3218ad=document[_0x40c7c4(0x86)](_0x40c7c4(0x323));_0x58fdba[_0x40c7c4(0x163)][_0x40c7c4(0x76)]='none',_0x3218ad[_0x40c7c4(0x163)][_0x40c7c4(0x76)]=_0x40c7c4(0xf9);const _0x13811f=document['getElementById'](_0x40c7c4(0x8f));_0x13811f?(_0x13811f[_0x40c7c4(0x163)][_0x40c7c4(0x76)]=_0x40c7c4(0x2ff),typeof showBossSelect==='function'?(showBossSelect(this),console[_0x40c7c4(0x266)](_0x40c7c4(0x1b1))):(console[_0x40c7c4(0x162)](_0x40c7c4(0x1bd)),_0x13811f['innerHTML']=_0x40c7c4(0x146))):console[_0x40c7c4(0x162)](_0x40c7c4(0x2dd)),this['selectedBoss']=null,this[_0x40c7c4(0x2e9)]=null,this['gameState']=_0x40c7c4(0x32e),this[_0x40c7c4(0x268)]=![],battleLog[_0x40c7c4(0x107)]='',gameOver[_0x40c7c4(0x26c)]='';}[_0x1fe90e(0x195)](){const _0x345d45=_0x1fe90e;if(!this[_0x345d45(0x12e)]){console[_0x345d45(0x266)](_0x345d45(0x16a));return;}const _0xf95521=window[_0x345d45(0x278)]||null;if(!_0xf95521){console['warn'](_0x345d45(0x1f7)),log(_0x345d45(0x298));return;}const _0x4b534c=this[_0x345d45(0x12e)]['id'],_0x38c624=this[_0x345d45(0x178)]['health'];console[_0x345d45(0x266)](_0x345d45(0x269)+_0xf95521+_0x345d45(0x222)+_0x4b534c+_0x345d45(0x2df)+_0x38c624),fetch(_0x345d45(0xf5),{'method':'POST','headers':{'Content-Type':_0x345d45(0x77)},'body':'user_id='+encodeURIComponent(_0xf95521)+'&boss_id='+encodeURIComponent(_0x4b534c)+'&health='+encodeURIComponent(_0x38c624)})[_0x345d45(0x2d1)](_0x4dbccf=>{const _0x1f8b3e=_0x345d45;console['log'](_0x1f8b3e(0x12c),_0x4dbccf['status']);if(!_0x4dbccf['ok'])throw new Error(_0x1f8b3e(0x29a)+_0x4dbccf[_0x1f8b3e(0x2fa)]);return _0x4dbccf['json']();})[_0x345d45(0x2d1)](_0x389f04=>{const _0x6ccfd2=_0x345d45;_0x389f04['success']?(console[_0x6ccfd2(0x266)](_0x6ccfd2(0x337)),log(_0x6ccfd2(0xe4)+_0x38c624+_0x6ccfd2(0x122)+this['player1'][_0x6ccfd2(0x1ad)]+_0x6ccfd2(0xd8)+this[_0x6ccfd2(0x12e)][_0x6ccfd2(0x1ad)])):(console[_0x6ccfd2(0x162)]('savePlayerHealth:\x20Failed\x20to\x20save\x20health:',_0x389f04['error']||_0x6ccfd2(0xe2)),log(_0x6ccfd2(0x1ca)+(_0x389f04[_0x6ccfd2(0x162)]||'Server\x20error')));})[_0x345d45(0x309)](_0x8cafbf=>{const _0x151acc=_0x345d45;console[_0x151acc(0x162)]('savePlayerHealth:\x20Error\x20saving\x20health:',_0x8cafbf),log('Error\x20saving\x20health:\x20'+_0x8cafbf['message']);});}[_0x1fe90e(0x114)](){const _0x4e3f42=_0x1fe90e;if(!this[_0x4e3f42(0x12e)]){console['log']('saveBossHealth:\x20Not\x20a\x20boss\x20battle,\x20skipping');return;}const _0x4a56d4=this[_0x4e3f42(0x12e)]['id'],_0x58ef1d=this['player2']['health'];console[_0x4e3f42(0x266)]('saveBossHealth:\x20Saving\x20-\x20bossId='+_0x4a56d4+_0x4e3f42(0x2df)+_0x58ef1d),fetch('ajax/save-boss-health.php',{'method':'POST','headers':{'Content-Type':_0x4e3f42(0x77)},'body':_0x4e3f42(0x158)+encodeURIComponent(userId)+_0x4e3f42(0x281)+encodeURIComponent(_0x4a56d4)+_0x4e3f42(0x2a9)+encodeURIComponent(_0x58ef1d)})[_0x4e3f42(0x2d1)](_0xc36384=>{const _0x226234=_0x4e3f42;console[_0x226234(0x266)]('saveBossHealth:\x20Response\x20status:',_0xc36384[_0x226234(0x2fa)]);if(!_0xc36384['ok'])throw new Error(_0x226234(0x29a)+_0xc36384['status']);return _0xc36384[_0x226234(0x1e5)]();})[_0x4e3f42(0x2d1)](_0x6324be=>{const _0x14e33a=_0x4e3f42;_0x6324be['success']?(console[_0x14e33a(0x266)]('saveBossHealth:\x20Boss\x20health\x20saved\x20successfully'),log('Boss\x20health\x20saved:\x20'+_0x58ef1d+_0x14e33a(0x122)+this['selectedBoss'][_0x14e33a(0x1ad)])):(console[_0x14e33a(0x162)](_0x14e33a(0xb3),_0x6324be[_0x14e33a(0x162)]||_0x14e33a(0xe2)),log(_0x14e33a(0x362)+(_0x6324be[_0x14e33a(0x162)]||_0x14e33a(0x1f6))));})[_0x4e3f42(0x309)](_0x2df463=>{const _0x2e941b=_0x4e3f42;console[_0x2e941b(0x162)](_0x2e941b(0x23f),_0x2df463),log(_0x2e941b(0x312)+_0x2df463[_0x2e941b(0x280)]);});}[_0x1fe90e(0x1cb)](){const _0x1c23d5=_0x1fe90e;if(!this[_0x1c23d5(0x12e)]){console[_0x1c23d5(0x266)](_0x1c23d5(0x1e6));return;}const _0x5b11fe=window[_0x1c23d5(0x278)]||_0x1c23d5(0xa8);!_0x5b11fe&&console[_0x1c23d5(0x1cc)](_0x1c23d5(0xb1));const _0xd0caf5=this[_0x1c23d5(0x12e)]['id'],_0x3355b1=_0x1c23d5(0x125)+_0x5b11fe+'_'+_0xd0caf5,_0x34de0b=this[_0x1c23d5(0x324)][_0x1c23d5(0x1c4)](_0x56d5fd=>_0x56d5fd[_0x1c23d5(0x1c4)](_0x5a187d=>({'type':_0x5a187d['type']})));console[_0x1c23d5(0x266)](_0x1c23d5(0x120)+_0x3355b1+_0x1c23d5(0x356),_0x34de0b);try{localStorage[_0x1c23d5(0x1b9)](_0x3355b1,JSON[_0x1c23d5(0xbc)](_0x34de0b)),console[_0x1c23d5(0x266)](_0x1c23d5(0x2de)+this[_0x1c23d5(0x12e)]['name']),log(_0x1c23d5(0xad)+this[_0x1c23d5(0x12e)][_0x1c23d5(0x1ad)]);}catch(_0x3cbdcd){console[_0x1c23d5(0x162)]('saveBoardState:\x20Error\x20saving\x20board\x20state:',_0x3cbdcd),log('Error\x20saving\x20board\x20state:\x20'+_0x3cbdcd[_0x1c23d5(0x280)]);}}[_0x1fe90e(0x1a9)](){const _0x24b2bc=_0x1fe90e;if(!this[_0x24b2bc(0x12e)])return console['log'](_0x24b2bc(0xf6)),null;const _0x5586d8=window[_0x24b2bc(0x278)]||'guest';!_0x5586d8&&console[_0x24b2bc(0x1cc)]('loadBoardState:\x20userId\x20not\x20defined.\x20Using\x20\x22guest\x22\x20as\x20fallback.');const _0x53f1de=this[_0x24b2bc(0x12e)]['id'],_0x263cf2='boss_board_'+_0x5586d8+'_'+_0x53f1de;console['log'](_0x24b2bc(0x2c2)+_0x263cf2);try{const _0x6286d2=localStorage['getItem'](_0x263cf2);if(_0x6286d2){const _0x206bcd=JSON[_0x24b2bc(0xf8)](_0x6286d2);return console['log'](_0x24b2bc(0x2f4),_0x206bcd),_0x206bcd[_0x24b2bc(0x1c4)](_0x19043d=>_0x19043d[_0x24b2bc(0x1c4)](_0xbe04c6=>({'type':_0xbe04c6[_0x24b2bc(0x103)],'element':null})));}else return console[_0x24b2bc(0x266)]('loadBoardState:\x20No\x20saved\x20board\x20state\x20found'),null;}catch(_0x569b74){return console[_0x24b2bc(0x162)](_0x24b2bc(0x156),_0x569b74),log(_0x24b2bc(0x135)+_0x569b74[_0x24b2bc(0x280)]),null;}}[_0x1fe90e(0xa5)](){const _0x13f425=_0x1fe90e;if(!this[_0x13f425(0x12e)]){console[_0x13f425(0x266)]('clearBoardState:\x20Not\x20a\x20boss\x20battle,\x20skipping');return;}const _0x254ec3=window[_0x13f425(0x278)]||'guest';!_0x254ec3&&console['warn'](_0x13f425(0x338));const _0x2dcc05=this[_0x13f425(0x12e)]['id'],_0x21844c=_0x13f425(0x125)+_0x254ec3+'_'+_0x2dcc05;console[_0x13f425(0x266)](_0x13f425(0x304)+_0x21844c);try{localStorage[_0x13f425(0x361)](_0x21844c),console[_0x13f425(0x266)](_0x13f425(0x1a1)+this['selectedBoss'][_0x13f425(0x1ad)]),log('Board\x20state\x20cleared\x20for\x20'+this[_0x13f425(0x12e)][_0x13f425(0x1ad)]);}catch(_0x202ad7){console['error'](_0x13f425(0x252),_0x202ad7),log(_0x13f425(0x347)+_0x202ad7[_0x13f425(0x280)]);}}['refreshBoard'](){const _0x172b75=_0x1fe90e;console[_0x172b75(0x266)](_0x172b75(0x277)),this[_0x172b75(0x360)]=![],this['selectedTile']=null,this[_0x172b75(0x92)]=null;const _0x39afaf=document[_0x172b75(0x1d1)]('.tile');_0x39afaf[_0x172b75(0x160)](_0xf6293e=>{const _0x21517b=_0x172b75;_0xf6293e['removeEventListener'](_0x21517b(0x2b1),this[_0x21517b(0x148)]),_0xf6293e[_0x21517b(0x189)](_0x21517b(0x1fb),this[_0x21517b(0x209)]);}),this[_0x172b75(0x19a)](),this[_0x172b75(0x2a4)]=this[_0x172b75(0x28d)]===this[_0x172b75(0x178)]?'playerTurn':_0x172b75(0x1b6),turnIndicator[_0x172b75(0x26c)]=_0x172b75(0x328)+(this[_0x172b75(0x28d)]===this[_0x172b75(0x178)]?_0x172b75(0x126):_0x172b75(0x348))+_0x172b75(0xc5),console['log'](_0x172b75(0x26b),{'player1':this['player1'][_0x172b75(0x1ad)],'player2':this['player2'][_0x172b75(0x1ad)],'currentTurn':this[_0x172b75(0x28d)][_0x172b75(0x1ad)],'gameState':this[_0x172b75(0x2a4)]}),log('Board\x20unstuck.\x20Continue\x20the\x20battle!'),this[_0x172b75(0x28d)]===this['player2']&&setTimeout(()=>this[_0x172b75(0x1b6)](),0x3e8);}[_0x1fe90e(0x214)](_0x4a5236){const _0xb6e174=_0x1fe90e;console[_0xb6e174(0x266)](_0xb6e174(0x294)+(_0x4a5236?_0xb6e174(0xe7):_0xb6e174(0x1d3)));const _0x789c26=document[_0xb6e174(0x17b)]('.game-container');if(!_0x789c26){console['error'](_0xb6e174(0x144));return;}const _0x44e183=document[_0xb6e174(0x86)](_0xb6e174(0x23a)),_0x3d69e0=document['getElementById'](_0xb6e174(0xd3)),_0x187197=document['getElementById'](_0xb6e174(0x36f));if(!_0x44e183||!_0x3d69e0||!_0x187197){console[_0xb6e174(0x1cc)](_0xb6e174(0x165),{'restart':!!_0x44e183,'refresh':!!_0x3d69e0,'changeCharacter':!!_0x187197});return;}_0x44e183[_0xb6e174(0x163)][_0xb6e174(0x76)]=_0x4a5236?_0xb6e174(0xf9):_0xb6e174(0x1d7),_0x3d69e0['style'][_0xb6e174(0x76)]=_0x4a5236?_0xb6e174(0x1d7):_0xb6e174(0xf9),_0x187197[_0xb6e174(0x163)][_0xb6e174(0x76)]=this['playerCharacters'][_0xb6e174(0x10a)]>0x1?_0xb6e174(0x1d7):_0xb6e174(0xf9),console[_0xb6e174(0x266)](_0xb6e174(0x9c)+_0x44e183[_0xb6e174(0x163)][_0xb6e174(0x76)]+_0xb6e174(0xfe)+_0x3d69e0['style']['display']+_0xb6e174(0x1c7)+_0x187197[_0xb6e174(0x163)]['display']);}[_0x1fe90e(0xef)](_0x496f99){const _0x1b93bd=_0x1fe90e;let _0x1b1269=0x0;const _0x48083d=JSON[_0x1b93bd(0xbc)](_0x496f99[_0x1b93bd(0x1c4)](_0x1b4474=>_0x1b4474[_0x1b93bd(0x1c4)](_0x6ad4ad=>_0x6ad4ad['type'])));for(let _0x1ac510=0x0;_0x1ac510<_0x48083d[_0x1b93bd(0x10a)];_0x1ac510++){_0x1b1269=(_0x1b1269<<0x5)-_0x1b1269+_0x48083d[_0x1b93bd(0xc1)](_0x1ac510),_0x1b1269|=0x0;}return _0x1b1269[_0x1b93bd(0xde)]();}[_0x1fe90e(0x1de)](){const _0x9a612f=_0x1fe90e,_0x3ba2fb={'level':this['currentLevel'],'board':this[_0x9a612f(0x324)]['map'](_0x16d304=>_0x16d304['map'](_0x12b675=>({'type':_0x12b675[_0x9a612f(0x103)]}))),'hash':this[_0x9a612f(0xef)](this[_0x9a612f(0x324)])};localStorage['setItem'](_0x9a612f(0x2fc)+this[_0x9a612f(0x1f5)],JSON[_0x9a612f(0xbc)](_0x3ba2fb));}['loadBoard'](){const _0x922061=_0x1fe90e,_0x3c54e8=_0x922061(0x2fc)+this['currentLevel'],_0x5c61d0=JSON[_0x922061(0xf8)](localStorage['getItem'](_0x3c54e8));if(_0x5c61d0&&_0x5c61d0[_0x922061(0xb5)]===this[_0x922061(0x1f5)]){const _0x20f256=this[_0x922061(0xef)](_0x5c61d0['board']);if(_0x5c61d0[_0x922061(0x2b9)]===_0x20f256)return _0x5c61d0[_0x922061(0x324)][_0x922061(0x1c4)](_0x439fe9=>_0x439fe9[_0x922061(0x1c4)](_0x31f354=>({'type':_0x31f354[_0x922061(0x103)],'element':null})));else console[_0x922061(0x1cc)](_0x922061(0x130)+this[_0x922061(0x1f5)]+',\x20generating\x20new\x20board');}return null;}['clearBoard'](){const _0x150deb=_0x1fe90e;localStorage[_0x150deb(0x361)]('board_level_'+this[_0x150deb(0x1f5)]);}async[_0x1fe90e(0x2e4)](){const _0x526d57=_0x1fe90e;console[_0x526d57(0x266)](_0x526d57(0xd6)),this[_0x526d57(0x316)]=this['playerCharactersConfig'][_0x526d57(0x1c4)](_0x296519=>this[_0x526d57(0xc8)](_0x296519)),await this[_0x526d57(0x106)](!![]);const _0x2fce12=await this['loadProgress'](),{loadedLevel:_0x53e634,loadedScore:_0x131a07,hasProgress:_0x239845}=_0x2fce12;if(_0x239845){console['log'](_0x526d57(0x155)+_0x53e634+_0x526d57(0x82)+_0x131a07);const _0x514c7a=await this[_0x526d57(0x367)](_0x53e634,_0x131a07);_0x514c7a?(this[_0x526d57(0x1f5)]=_0x53e634,this[_0x526d57(0x132)]=_0x131a07,log(_0x526d57(0x273)+this[_0x526d57(0x1f5)]+_0x526d57(0xf4)+this[_0x526d57(0x132)])):(this[_0x526d57(0x1f5)]=0x1,this[_0x526d57(0x132)]=0x0,await this['clearProgress'](),log(_0x526d57(0x29f)));}else this['currentLevel']=0x1,this[_0x526d57(0x132)]=0x0,log(_0x526d57(0x2ee));console[_0x526d57(0x266)](_0x526d57(0x96));}[_0x1fe90e(0x248)](){const _0x2505ae=_0x1fe90e;console[_0x2505ae(0x266)]('setBackground:\x20Attempting\x20for\x20theme='+this['theme']);const _0x314fe2=themes[_0x2505ae(0x172)](_0x24f6ed=>_0x24f6ed['items'])['find'](_0x4eb724=>_0x4eb724[_0x2505ae(0x1c9)]===this[_0x2505ae(0xf3)]);console[_0x2505ae(0x266)]('setBackground:\x20themeData=',_0x314fe2);const _0x3615b2=_0x2505ae(0x350)+this[_0x2505ae(0xf3)]+_0x2505ae(0x1e4);console[_0x2505ae(0x266)]('setBackground:\x20Setting\x20background\x20to\x20'+_0x3615b2),_0x314fe2&&_0x314fe2[_0x2505ae(0x108)]?(document[_0x2505ae(0x1fd)][_0x2505ae(0x163)][_0x2505ae(0x161)]=_0x2505ae(0x285)+_0x3615b2+')',document[_0x2505ae(0x1fd)][_0x2505ae(0x163)][_0x2505ae(0x8a)]=_0x2505ae(0x329),document[_0x2505ae(0x1fd)][_0x2505ae(0x163)][_0x2505ae(0x115)]='center'):document[_0x2505ae(0x1fd)][_0x2505ae(0x163)][_0x2505ae(0x161)]=_0x2505ae(0xf9);}[_0x1fe90e(0xa4)](_0x5ce3b0,_0x4e046f=![]){const _0x417c73=_0x1fe90e;if(updatePending)return console[_0x417c73(0x266)](_0x417c73(0x345)),Promise[_0x417c73(0x1dd)]();updatePending=!![],console[_0x417c73(0x1bc)](_0x417c73(0x110)+_0x5ce3b0);const _0x342f96=this;this['theme']=_0x5ce3b0,this[_0x417c73(0x334)]=_0x417c73(0x350)+this['theme']+'/',localStorage[_0x417c73(0x1b9)](_0x417c73(0x12a),this['theme']),this['setBackground']();!_0x4e046f?(console[_0x417c73(0x266)](_0x417c73(0x128)),this[_0x417c73(0x12e)]=null,this[_0x417c73(0x2e9)]=null):console['log'](_0x417c73(0x326));document[_0x417c73(0x17b)](_0x417c73(0x139))['src']=this[_0x417c73(0x334)]+_0x417c73(0x314);if(!_0x4e046f){const _0xe0ea5b=document[_0x417c73(0x86)]('character-options');_0xe0ea5b&&(_0xe0ea5b[_0x417c73(0x107)]='<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>Loading\x20new\x20characters...</p>');}return getAssets(this['theme'])[_0x417c73(0x2d1)](function(_0x3c0a05){const _0x17bb22=_0x417c73;console['time']('updateCharacters_'+_0x5ce3b0),_0x342f96[_0x17bb22(0xa1)]=_0x3c0a05,_0x342f96[_0x17bb22(0x316)]=[],_0x3c0a05[_0x17bb22(0x160)](_0x52b5fc=>{const _0xec6c39=_0x17bb22,_0x13d47a=_0x342f96[_0xec6c39(0xc8)](_0x52b5fc);if(_0x13d47a[_0xec6c39(0x109)]===_0xec6c39(0x2f3)){const _0x28f9fa=new Image();_0x28f9fa['src']=_0x13d47a['imageUrl'],_0x28f9fa['onload']=()=>console[_0xec6c39(0x266)](_0xec6c39(0x22d)+_0x13d47a[_0xec6c39(0xc7)]),_0x28f9fa[_0xec6c39(0x150)]=()=>console[_0xec6c39(0x266)](_0xec6c39(0x1a8)+_0x13d47a[_0xec6c39(0xc7)]);}_0x342f96['playerCharacters']['push'](_0x13d47a);});if(_0x342f96['player1']&&!_0x4e046f){const _0x296e1c=_0x342f96[_0x17bb22(0xa1)]['find'](_0x1e72bc=>_0x1e72bc['name']===_0x342f96[_0x17bb22(0x178)][_0x17bb22(0x1ad)])||_0x342f96[_0x17bb22(0xa1)][0x0];_0x342f96[_0x17bb22(0x178)]=_0x342f96['createCharacter'](_0x296e1c),_0x342f96[_0x17bb22(0x78)]();}_0x342f96[_0x17bb22(0x178)]&&!_0x4e046f&&(_0x342f96['player2']=_0x342f96[_0x17bb22(0xc8)](opponentsConfig[_0x342f96[_0x17bb22(0x1f5)]-0x1]),_0x342f96['updateOpponentDisplay'](),console[_0x17bb22(0x266)](_0x17bb22(0x354)+_0x342f96[_0x17bb22(0xac)][_0x17bb22(0x1ad)]));if(_0x342f96[_0x17bb22(0x178)]&&_0x342f96[_0x17bb22(0x2a4)]!=='initializing'&&!_0x4e046f){const _0x177b73=document[_0x17bb22(0x1d1)]('.tile');_0x177b73['forEach'](_0x4f9473=>{const _0x19d207=_0x17bb22;_0x4f9473[_0x19d207(0x189)](_0x19d207(0x2b1),_0x342f96[_0x19d207(0x148)]),_0x4f9473['removeEventListener'](_0x19d207(0x1fb),_0x342f96[_0x19d207(0x209)]);}),_0x342f96[_0x17bb22(0x19a)](),console[_0x17bb22(0x266)]('updateTheme:\x20Board\x20rendered\x20for\x20active\x20game');}else console[_0x17bb22(0x266)](_0x17bb22(0x1db));_0x342f96['player1']&&!_0x4e046f&&(_0x342f96[_0x17bb22(0x360)]=![],_0x342f96[_0x17bb22(0x261)]=null,_0x342f96[_0x17bb22(0x92)]=null,_0x342f96[_0x17bb22(0x2a4)]=_0x342f96[_0x17bb22(0x28d)]===_0x342f96[_0x17bb22(0x178)]?_0x17bb22(0x223):_0x17bb22(0x1b6));if(!_0x4e046f){const _0x595228=document['getElementById'](_0x17bb22(0x20c));_0x595228[_0x17bb22(0x163)][_0x17bb22(0x76)]=_0x17bb22(0x2ff),_0x342f96['showCharacterSelect'](_0x342f96[_0x17bb22(0x178)]===null);}console[_0x17bb22(0x119)]('updateCharacters_'+_0x5ce3b0),console[_0x17bb22(0x119)](_0x17bb22(0x110)+_0x5ce3b0),updatePending=![];})['catch'](function(_0x1aef42){const _0x2a52ec=_0x417c73;console[_0x2a52ec(0x162)](_0x2a52ec(0x301),_0x1aef42),_0x342f96[_0x2a52ec(0xa1)]=[{'name':_0x2a52ec(0x112),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x2a52ec(0x190),'type':_0x2a52ec(0x213),'powerup':_0x2a52ec(0x84),'theme':_0x2a52ec(0x11e)},{'name':_0x2a52ec(0x2ad),'strength':0x3,'speed':0x5,'tactics':0x3,'size':'Small','type':_0x2a52ec(0x213),'powerup':_0x2a52ec(0x124),'theme':'monstrocity'}],_0x342f96[_0x2a52ec(0x316)]=_0x342f96[_0x2a52ec(0xa1)][_0x2a52ec(0x1c4)](_0x752fc1=>_0x342f96[_0x2a52ec(0xc8)](_0x752fc1));!_0x4e046f&&(_0x342f96[_0x2a52ec(0x12e)]=null,_0x342f96[_0x2a52ec(0x2e9)]=null);if(!_0x4e046f){const _0x1c5aac=document[_0x2a52ec(0x86)]('character-select-container');_0x1c5aac[_0x2a52ec(0x163)]['display']=_0x2a52ec(0x2ff),_0x342f96[_0x2a52ec(0x106)](_0x342f96[_0x2a52ec(0x178)]===null);}console['timeEnd'](_0x2a52ec(0x110)+_0x5ce3b0),updatePending=![];});}async[_0x1fe90e(0x173)](){const _0x43c8dc=_0x1fe90e,_0x25a063={'currentLevel':this[_0x43c8dc(0x1f5)],'grandTotalScore':this['grandTotalScore']};console['log'](_0x43c8dc(0x1af),_0x25a063);try{const _0x1a0b0f=await fetch(_0x43c8dc(0xc9),{'method':'POST','headers':{'Content-Type':_0x43c8dc(0x297)},'body':JSON[_0x43c8dc(0xbc)](_0x25a063)});console[_0x43c8dc(0x266)](_0x43c8dc(0x170),_0x1a0b0f['status']);const _0x4d461b=await _0x1a0b0f[_0x43c8dc(0x2d8)]();console[_0x43c8dc(0x266)]('Raw\x20response\x20text:',_0x4d461b);if(!_0x1a0b0f['ok'])throw new Error(_0x43c8dc(0x29a)+_0x1a0b0f[_0x43c8dc(0x2fa)]);const _0x116a62=JSON[_0x43c8dc(0xf8)](_0x4d461b);console['log']('Parsed\x20response:',_0x116a62),_0x116a62[_0x43c8dc(0x2fa)]===_0x43c8dc(0x363)?log(_0x43c8dc(0x27e)+this[_0x43c8dc(0x1f5)]):console[_0x43c8dc(0x162)](_0x43c8dc(0x24a),_0x116a62[_0x43c8dc(0x280)]);}catch(_0x23f681){console['error'](_0x43c8dc(0x10c),_0x23f681);}}async[_0x1fe90e(0x30e)](){const _0x3f6216=_0x1fe90e;try{console[_0x3f6216(0x266)](_0x3f6216(0x25b));const _0x44a168=await fetch(_0x3f6216(0x1c6),{'method':_0x3f6216(0x21a),'headers':{'Content-Type':_0x3f6216(0x297)}});console[_0x3f6216(0x266)](_0x3f6216(0x170),_0x44a168[_0x3f6216(0x2fa)]);if(!_0x44a168['ok'])throw new Error(_0x3f6216(0x29a)+_0x44a168[_0x3f6216(0x2fa)]);const _0x1b28d5=await _0x44a168[_0x3f6216(0x1e5)]();console[_0x3f6216(0x266)](_0x3f6216(0x1eb),_0x1b28d5);if(_0x1b28d5['status']===_0x3f6216(0x363)&&_0x1b28d5[_0x3f6216(0x2e2)]){const _0x26368a=_0x1b28d5[_0x3f6216(0x2e2)];return{'loadedLevel':_0x26368a['currentLevel']||0x1,'loadedScore':_0x26368a['grandTotalScore']||0x0,'hasProgress':!![]};}else return console[_0x3f6216(0x266)](_0x3f6216(0x1ab),_0x1b28d5),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}catch(_0x288017){return console['error'](_0x3f6216(0x319),_0x288017),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}}async[_0x1fe90e(0xfa)](){const _0x42c530=_0x1fe90e;try{const _0x43dc18=await fetch(_0x42c530(0xcb),{'method':_0x42c530(0x2d5),'headers':{'Content-Type':_0x42c530(0x297)}});if(!_0x43dc18['ok'])throw new Error('HTTP\x20error!\x20Status:\x20'+_0x43dc18[_0x42c530(0x2fa)]);const _0x659696=await _0x43dc18['json']();_0x659696[_0x42c530(0x2fa)]===_0x42c530(0x363)&&(this[_0x42c530(0x1f5)]=0x1,this[_0x42c530(0x132)]=0x0,this[_0x42c530(0x184)](),log(_0x42c530(0x27c)));}catch(_0x115ca1){console[_0x42c530(0x162)](_0x42c530(0x2aa),_0x115ca1);}}[_0x1fe90e(0x21f)](){const _0x59e065=_0x1fe90e,_0x39d1ae=document[_0x59e065(0x86)]('game-board'),_0x58bdf5=_0x39d1ae['offsetWidth']||0x12c;this['tileSizeWithGap']=(_0x58bdf5-0.5*(this[_0x59e065(0x28b)]-0x1))/this[_0x59e065(0x28b)];}['createCharacter'](_0xcfd743){const _0xbf7974=_0x1fe90e;console[_0xbf7974(0x266)](_0xbf7974(0x313),_0xcfd743);var _0x19fd62,_0x525c03,_0x995e38,_0x5da53a=_0xbf7974(0x262),_0x55ca0f=![],_0x50fdec=_0xbf7974(0x2f3);console[_0xbf7974(0x266)]('createCharacter:\x20config.imageUrl=',_0xcfd743['imageUrl']);const _0x5c4e67=themes['flatMap'](_0x43651a=>_0x43651a[_0xbf7974(0x2a5)])[_0xbf7974(0x1f0)](_0x54620d=>_0x54620d['value']===this[_0xbf7974(0xf3)]),_0x463976=_0x5c4e67?.[_0xbf7974(0x143)]||'png',_0x4f84c1=[_0xbf7974(0x2b4),'mp4'],_0x2c216c=_0xbf7974(0x32c);if(_0xcfd743['imageUrl'])_0x525c03=_0xcfd743[_0xbf7974(0xc7)],_0x995e38=_0xcfd743['fallbackUrl']||'icons/skull.png',_0x5da53a=_0xcfd743[_0xbf7974(0xe5)]||_0xbf7974(0x2d3);else{if(_0xcfd743[_0xbf7974(0x36d)]&&_0xcfd743['policyId']){_0x55ca0f=!![];var _0x4ed552={'orientation':_0xbf7974(0x2d3),'ipfsPrefix':_0x5c4e67?.[_0xbf7974(0x30f)]||_0x2c216c};if(_0x5c4e67&&_0x5c4e67[_0xbf7974(0x17d)]){var _0x1f73e7=_0x5c4e67['policyIds']['split'](',')[_0xbf7974(0x336)](_0x11ffd1=>_0x11ffd1[_0xbf7974(0xc0)]()),_0x1bc884=_0x5c4e67[_0xbf7974(0x264)]?_0x5c4e67['orientations']['split'](',')[_0xbf7974(0x336)](_0x748417=>_0x748417[_0xbf7974(0xc0)]()):[],_0x18faab=_0x5c4e67[_0xbf7974(0x30f)]?_0x5c4e67[_0xbf7974(0x30f)][_0xbf7974(0x303)](',')['filter'](_0x16e025=>_0x16e025[_0xbf7974(0xc0)]()):[_0x2c216c],_0x2a732d=_0x1f73e7[_0xbf7974(0x352)](_0xcfd743[_0xbf7974(0x10f)]);_0x2a732d!==-0x1&&(_0x4ed552={'orientation':_0x1bc884[_0xbf7974(0x10a)]===0x1?_0x1bc884[0x0]:_0x1bc884[_0x2a732d]||'Right','ipfsPrefix':_0x18faab['length']===0x1?_0x18faab[0x0]:_0x18faab[_0x2a732d]||_0x2c216c});}_0x4ed552[_0xbf7974(0xe5)]===_0xbf7974(0x27d)?_0x5da53a=Math[_0xbf7974(0x2d9)]()<0.5?'Left':_0xbf7974(0x2d3):_0x5da53a=_0x4ed552[_0xbf7974(0xe5)];_0x525c03=_0x4ed552['ipfsPrefix']+_0xcfd743['ipfs'],_0x995e38=_0x2c216c+_0xcfd743[_0xbf7974(0x36d)];const _0x3110c9=_0x525c03['split']('.')[_0xbf7974(0x28f)]()[_0xbf7974(0x332)]();_0x4f84c1['includes'](_0x3110c9)&&(_0x50fdec=_0xbf7974(0x25c));}else{switch(_0xcfd743[_0xbf7974(0x103)]){case _0xbf7974(0x213):_0x19fd62='base';break;case _0xbf7974(0x271):_0x19fd62=_0xbf7974(0x1e7);break;case _0xbf7974(0x13b):_0x19fd62=_0xbf7974(0x18e);break;default:_0x19fd62=_0xbf7974(0xba);}_0x525c03=this[_0xbf7974(0x334)]+_0x19fd62+'/'+_0xcfd743[_0xbf7974(0x1ad)][_0xbf7974(0x332)]()['replace'](/ /g,'-')+'.'+_0x463976,_0x995e38=_0xbf7974(0x2c8),_0x5da53a=characterDirections[_0xcfd743[_0xbf7974(0x1ad)]]||_0xbf7974(0x262),_0x4f84c1['includes'](_0x463976[_0xbf7974(0x332)]())&&(_0x50fdec='video');}}var _0xffe92c;switch(_0xcfd743[_0xbf7974(0x103)]){case _0xbf7974(0x271):_0xffe92c=0x64;break;case _0xbf7974(0x13b):_0xffe92c=0x46;break;case'Base':default:_0xffe92c=0x55;}var _0x1b8e7f=0x1,_0x5e8be4=0x0;switch(_0xcfd743[_0xbf7974(0x287)]){case'Large':_0x1b8e7f=1.2,_0x5e8be4=_0xcfd743['tactics']>0x1?-0x2:0x0;break;case _0xbf7974(0x2b5):_0x1b8e7f=0.8,_0x5e8be4=_0xcfd743['tactics']<0x6?0x2:0x7-_0xcfd743[_0xbf7974(0xd5)];break;case _0xbf7974(0x190):_0x1b8e7f=0x1,_0x5e8be4=0x0;break;}var _0x36102e=Math[_0xbf7974(0x179)](_0xffe92c*_0x1b8e7f),_0x1ed4e6=Math['max'](0x1,Math[_0xbf7974(0x153)](0x7,_0xcfd743[_0xbf7974(0xd5)]+_0x5e8be4));return{'name':_0xcfd743[_0xbf7974(0x1ad)],'type':_0xcfd743[_0xbf7974(0x103)],'strength':_0xcfd743[_0xbf7974(0x1c3)],'speed':_0xcfd743['speed'],'tactics':_0x1ed4e6,'size':_0xcfd743[_0xbf7974(0x287)],'powerup':_0xcfd743[_0xbf7974(0x36a)],'health':_0x36102e,'maxHealth':_0x36102e,'boostActive':![],'boostValue':0x0,'lastStandActive':![],'imageUrl':_0x525c03,'fallbackUrl':_0x995e38,'orientation':_0x5da53a,'isNFT':_0x55ca0f,'mediaType':_0x50fdec};}[_0x1fe90e(0x26e)](_0x24281a,_0x27ae30,_0x233d76=![]){const _0x36d86d=_0x1fe90e;_0x24281a[_0x36d86d(0xe5)]==='Left'?(_0x24281a['orientation']=_0x36d86d(0x2d3),_0x27ae30[_0x36d86d(0x163)][_0x36d86d(0x289)]=_0x233d76?'scaleX(-1)':_0x36d86d(0xf9)):(_0x24281a[_0x36d86d(0xe5)]='Left',_0x27ae30[_0x36d86d(0x163)][_0x36d86d(0x289)]=_0x233d76?_0x36d86d(0xf9):'scaleX(-1)'),log(_0x24281a[_0x36d86d(0x1ad)]+_0x36d86d(0xcd)+_0x24281a['orientation']+'!');}[_0x1fe90e(0x106)](_0x228e57){const _0x6d8a18=_0x1fe90e;console[_0x6d8a18(0x1bc)](_0x6d8a18(0x106));const _0x1757a2=document[_0x6d8a18(0x86)](_0x6d8a18(0x20c)),_0x158b44=document['getElementById'](_0x6d8a18(0x10b));_0x158b44[_0x6d8a18(0x107)]='',_0x1757a2[_0x6d8a18(0x163)][_0x6d8a18(0x76)]='block';const _0x2d0c93=document[_0x6d8a18(0x86)](_0x6d8a18(0x187));this[_0x6d8a18(0x12e)]&&window[_0x6d8a18(0x325)]?(_0x2d0c93[_0x6d8a18(0x163)]['display']=_0x6d8a18(0x1d7),_0x2d0c93[_0x6d8a18(0x371)]=()=>{const _0x4429d6=_0x6d8a18;_0x1757a2[_0x4429d6(0x163)][_0x4429d6(0x76)]=_0x4429d6(0xf9),showBossSelect(this);}):_0x2d0c93[_0x6d8a18(0x163)][_0x6d8a18(0x76)]=_0x6d8a18(0xf9);if(!this[_0x6d8a18(0x316)]||this[_0x6d8a18(0x316)]['length']===0x0){console[_0x6d8a18(0x1cc)]('showCharacterSelect:\x20No\x20characters\x20available,\x20using\x20fallback'),_0x158b44[_0x6d8a18(0x107)]=_0x6d8a18(0xf2),console[_0x6d8a18(0x119)](_0x6d8a18(0x106));return;}document[_0x6d8a18(0x86)](_0x6d8a18(0x242))[_0x6d8a18(0x371)]=()=>{showThemeSelect(this);};const _0x15f33e=document['createDocumentFragment']();this[_0x6d8a18(0x316)]['forEach'](_0xe3c64=>{const _0x3f0a8c=_0x6d8a18,_0x186671=document[_0x3f0a8c(0x2db)](_0x3f0a8c(0x373));_0x186671['className']=_0x3f0a8c(0xce),_0x186671[_0x3f0a8c(0x107)]=_0xe3c64[_0x3f0a8c(0x109)]===_0x3f0a8c(0x25c)?_0x3f0a8c(0x286)+_0xe3c64[_0x3f0a8c(0xc7)]+_0x3f0a8c(0x357)+_0xe3c64['name']+_0x3f0a8c(0x198)+_0xe3c64['fallbackUrl']+'\x27\x22></video>'+(_0x3f0a8c(0x134)+_0xe3c64[_0x3f0a8c(0x1ad)]+'</strong></p>')+(_0x3f0a8c(0x369)+_0xe3c64[_0x3f0a8c(0x103)]+_0x3f0a8c(0xd1))+(_0x3f0a8c(0x12b)+_0xe3c64[_0x3f0a8c(0x152)]+'</p>')+(_0x3f0a8c(0x284)+_0xe3c64[_0x3f0a8c(0x1c3)]+_0x3f0a8c(0xd1))+(_0x3f0a8c(0x2f9)+_0xe3c64['speed']+_0x3f0a8c(0xd1))+(_0x3f0a8c(0x18b)+_0xe3c64['tactics']+'</p>')+(_0x3f0a8c(0x335)+_0xe3c64['size']+_0x3f0a8c(0xd1))+(_0x3f0a8c(0xb4)+_0xe3c64['powerup']+_0x3f0a8c(0xd1)):_0x3f0a8c(0x272)+_0xe3c64[_0x3f0a8c(0xc7)]+'\x22\x20alt=\x22'+_0xe3c64[_0x3f0a8c(0x1ad)]+_0x3f0a8c(0x198)+_0xe3c64[_0x3f0a8c(0x2bf)]+_0x3f0a8c(0x2a3)+('<p><strong>'+_0xe3c64[_0x3f0a8c(0x1ad)]+_0x3f0a8c(0x29e))+(_0x3f0a8c(0x369)+_0xe3c64[_0x3f0a8c(0x103)]+'</p>')+('<p>Health:\x20'+_0xe3c64['maxHealth']+_0x3f0a8c(0xd1))+(_0x3f0a8c(0x284)+_0xe3c64[_0x3f0a8c(0x1c3)]+_0x3f0a8c(0xd1))+(_0x3f0a8c(0x2f9)+_0xe3c64[_0x3f0a8c(0x228)]+_0x3f0a8c(0xd1))+('<p>Tactics:\x20'+_0xe3c64[_0x3f0a8c(0xd5)]+_0x3f0a8c(0xd1))+('<p>Size:\x20'+_0xe3c64[_0x3f0a8c(0x287)]+_0x3f0a8c(0xd1))+(_0x3f0a8c(0xb4)+_0xe3c64['powerup']+'</p>'),_0x186671[_0x3f0a8c(0x7e)](_0x3f0a8c(0x2da),()=>{const _0x2dbb41=_0x3f0a8c;console['log'](_0x2dbb41(0x375)+_0xe3c64[_0x2dbb41(0x1ad)]),console[_0x2dbb41(0x266)](_0x2dbb41(0x2f7),this[_0x2dbb41(0x12e)]),_0x1757a2['style'][_0x2dbb41(0x76)]=_0x2dbb41(0xf9),_0x228e57?(console['log'](_0x2dbb41(0x75)),this[_0x2dbb41(0x12e)]?(console[_0x2dbb41(0x266)](_0x2dbb41(0x19d)),this['setSelectedCharacter'](_0xe3c64)):(console[_0x2dbb41(0x266)](_0x2dbb41(0x35a)),this['player1']={..._0xe3c64},console[_0x2dbb41(0x266)](_0x2dbb41(0x2bb)+this[_0x2dbb41(0x178)][_0x2dbb41(0x1ad)]),this[_0x2dbb41(0x166)]())):(console[_0x2dbb41(0x266)](_0x2dbb41(0x1b0)),this[_0x2dbb41(0x296)](_0xe3c64));}),_0x15f33e[_0x3f0a8c(0xdc)](_0x186671);}),_0x158b44[_0x6d8a18(0xdc)](_0x15f33e),console['log'](_0x6d8a18(0x1bf)+this[_0x6d8a18(0x316)][_0x6d8a18(0x10a)]+'\x20characters'),console[_0x6d8a18(0x119)]('showCharacterSelect');}[_0x1fe90e(0x296)](_0x19e2be){const _0x512204=_0x1fe90e,_0x5c63b0=this[_0x512204(0x178)][_0x512204(0x16d)],_0x4ea302=this[_0x512204(0x178)][_0x512204(0x152)],_0x49b3d1={..._0x19e2be},_0x4b3ae2=Math[_0x512204(0x153)](0x1,_0x5c63b0/_0x4ea302);_0x49b3d1['health']=Math[_0x512204(0x179)](_0x49b3d1[_0x512204(0x152)]*_0x4b3ae2),_0x49b3d1[_0x512204(0x16d)]=Math[_0x512204(0x2cb)](0x0,Math['min'](_0x49b3d1[_0x512204(0x152)],_0x49b3d1[_0x512204(0x16d)])),_0x49b3d1['boostActive']=![],_0x49b3d1[_0x512204(0x310)]=0x0,_0x49b3d1['lastStandActive']=![],this['player1']=_0x49b3d1,this[_0x512204(0x78)](),this['updateHealth'](this[_0x512204(0x178)]),log(this[_0x512204(0x178)][_0x512204(0x1ad)]+_0x512204(0x2ca)+this['player1'][_0x512204(0x16d)]+'/'+this['player1'][_0x512204(0x152)]+'\x20HP!'),this['currentTurn']=this[_0x512204(0x178)]['speed']>this['player2'][_0x512204(0x228)]?this[_0x512204(0x178)]:this[_0x512204(0xac)][_0x512204(0x228)]>this[_0x512204(0x178)][_0x512204(0x228)]?this[_0x512204(0xac)]:this[_0x512204(0x178)][_0x512204(0x1c3)]>=this[_0x512204(0xac)][_0x512204(0x1c3)]?this[_0x512204(0x178)]:this['player2'],turnIndicator[_0x512204(0x26c)]=_0x512204(0x2c6)+this[_0x512204(0x1f5)]+_0x512204(0x308)+(this['currentTurn']===this[_0x512204(0x178)]?_0x512204(0x126):_0x512204(0x24d))+'\x27s\x20Turn',this['currentTurn']===this['player2']&&this[_0x512204(0x2a4)]!=='gameOver'&&setTimeout(()=>this['aiTurn'](),0x3e8);}[_0x1fe90e(0x367)](_0x44a9c8,_0x3d5fc4){const _0x23e582=_0x1fe90e;return console[_0x23e582(0x266)](_0x23e582(0x2cc)+_0x44a9c8+_0x23e582(0x171)+_0x3d5fc4),new Promise(_0x45fefc=>{const _0x598c33=_0x23e582,_0x3f3250=document['createElement'](_0x598c33(0x373));_0x3f3250['id']=_0x598c33(0x250),_0x3f3250[_0x598c33(0x206)]='progress-modal';const _0x125e90=document[_0x598c33(0x2db)]('div');_0x125e90[_0x598c33(0x206)]=_0x598c33(0x204);const _0x293b2e=document[_0x598c33(0x2db)]('p');_0x293b2e['id']=_0x598c33(0x283),_0x293b2e[_0x598c33(0x26c)]='Resume\x20from\x20Level\x20'+_0x44a9c8+'\x20with\x20Score\x20of\x20'+_0x3d5fc4+'?',_0x125e90[_0x598c33(0xdc)](_0x293b2e);const _0x4d193d=document['createElement']('div');_0x4d193d[_0x598c33(0x206)]='progress-modal-buttons';const _0x205d83=document[_0x598c33(0x2db)](_0x598c33(0x1f4));_0x205d83['id']='progress-resume',_0x205d83[_0x598c33(0x26c)]=_0x598c33(0xfd),_0x4d193d[_0x598c33(0xdc)](_0x205d83);const _0x5d501a=document[_0x598c33(0x2db)]('button');_0x5d501a['id']=_0x598c33(0x2e1),_0x5d501a['textContent']=_0x598c33(0x21e),_0x4d193d[_0x598c33(0xdc)](_0x5d501a),_0x125e90[_0x598c33(0xdc)](_0x4d193d),_0x3f3250[_0x598c33(0xdc)](_0x125e90),document['body'][_0x598c33(0xdc)](_0x3f3250),_0x3f3250['style'][_0x598c33(0x76)]=_0x598c33(0x97);const _0x32cab1=()=>{const _0x16b698=_0x598c33;console[_0x16b698(0x266)](_0x16b698(0x36b)),_0x3f3250[_0x16b698(0x163)][_0x16b698(0x76)]=_0x16b698(0xf9),document['body'][_0x16b698(0xd2)](_0x3f3250),_0x205d83[_0x16b698(0x189)](_0x16b698(0x2da),_0x32cab1),_0x5d501a['removeEventListener'](_0x16b698(0x2da),_0x311d01),_0x45fefc(!![]);},_0x311d01=()=>{const _0x5bac66=_0x598c33;console['log'](_0x5bac66(0xa0)),_0x3f3250[_0x5bac66(0x163)][_0x5bac66(0x76)]=_0x5bac66(0xf9),document['body']['removeChild'](_0x3f3250),_0x205d83[_0x5bac66(0x189)](_0x5bac66(0x2da),_0x32cab1),_0x5d501a[_0x5bac66(0x189)](_0x5bac66(0x2da),_0x311d01),_0x45fefc(![]);};_0x205d83[_0x598c33(0x7e)](_0x598c33(0x2da),_0x32cab1),_0x5d501a[_0x598c33(0x7e)](_0x598c33(0x2da),_0x311d01);});}['initGame'](){const _0xd76f15=_0x1fe90e;var _0x3f552f=this;console[_0xd76f15(0x266)](_0xd76f15(0x203)+this[_0xd76f15(0x1f5)]),this[_0xd76f15(0x12e)]=null,this[_0xd76f15(0x2e9)]=null,console[_0xd76f15(0x266)](_0xd76f15(0x34f));var _0x26efdf=document[_0xd76f15(0x17b)](_0xd76f15(0x1e9)),_0x2f5965=document[_0xd76f15(0x86)](_0xd76f15(0xaf));_0x26efdf[_0xd76f15(0x163)]['display']=_0xd76f15(0x2ff),_0x2f5965[_0xd76f15(0x163)][_0xd76f15(0x1d6)]=_0xd76f15(0x307),this[_0xd76f15(0x248)](),this[_0xd76f15(0x265)][_0xd76f15(0x180)]['play'](),log(_0xd76f15(0xec)+this[_0xd76f15(0x1f5)]+_0xd76f15(0x355)),this[_0xd76f15(0xac)]=this['createCharacter'](opponentsConfig[this[_0xd76f15(0x1f5)]-0x1]),console['log']('Loaded\x20opponent\x20for\x20level\x20'+this[_0xd76f15(0x1f5)]+':\x20'+this[_0xd76f15(0xac)]['name']+'\x20(opponentsConfig['+(this[_0xd76f15(0x1f5)]-0x1)+'])'),this[_0xd76f15(0x178)][_0xd76f15(0x16d)]=this[_0xd76f15(0x178)]['maxHealth'],this[_0xd76f15(0x28d)]=this['player1'][_0xd76f15(0x228)]>this[_0xd76f15(0xac)][_0xd76f15(0x228)]?this[_0xd76f15(0x178)]:this[_0xd76f15(0xac)][_0xd76f15(0x228)]>this[_0xd76f15(0x178)][_0xd76f15(0x228)]?this[_0xd76f15(0xac)]:this[_0xd76f15(0x178)][_0xd76f15(0x1c3)]>=this[_0xd76f15(0xac)][_0xd76f15(0x1c3)]?this['player1']:this[_0xd76f15(0xac)],this['gameState']=_0xd76f15(0x32e),this[_0xd76f15(0x268)]=![],this[_0xd76f15(0x129)]=[];const _0x1a63c4=document[_0xd76f15(0x86)](_0xd76f15(0x282)),_0x3075f4=document[_0xd76f15(0x86)](_0xd76f15(0x31f));if(_0x1a63c4)_0x1a63c4[_0xd76f15(0x31b)]['remove'](_0xd76f15(0x2a7),'loser');if(_0x3075f4)_0x3075f4['classList']['remove']('winner',_0xd76f15(0x35e));this[_0xd76f15(0x78)](),this[_0xd76f15(0x220)]();if(_0x1a63c4)_0x1a63c4[_0xd76f15(0x163)][_0xd76f15(0x289)]=this['player1'][_0xd76f15(0xe5)]===_0xd76f15(0x262)?_0xd76f15(0x1b3):_0xd76f15(0xf9);if(_0x3075f4)_0x3075f4[_0xd76f15(0x163)][_0xd76f15(0x289)]=this[_0xd76f15(0xac)]['orientation']===_0xd76f15(0x2d3)?_0xd76f15(0x1b3):_0xd76f15(0xf9);this[_0xd76f15(0x7c)](this[_0xd76f15(0x178)]),this[_0xd76f15(0x7c)](this[_0xd76f15(0xac)]),battleLog['innerHTML']='',gameOver['textContent']='',this[_0xd76f15(0x214)](![]),this[_0xd76f15(0x178)][_0xd76f15(0x287)]!=='Medium'&&log(this[_0xd76f15(0x178)][_0xd76f15(0x1ad)]+_0xd76f15(0x2f0)+this[_0xd76f15(0x178)][_0xd76f15(0x287)]+_0xd76f15(0x29c)+(this[_0xd76f15(0x178)][_0xd76f15(0x287)]==='Large'?_0xd76f15(0x2ab)+this['player1']['maxHealth']+'\x20but\x20dulls\x20tactics\x20to\x20'+this['player1'][_0xd76f15(0xd5)]:_0xd76f15(0x164)+this['player1']['maxHealth']+_0xd76f15(0x258)+this[_0xd76f15(0x178)][_0xd76f15(0xd5)])+'!'),this[_0xd76f15(0xac)][_0xd76f15(0x287)]!==_0xd76f15(0x190)&&log(this[_0xd76f15(0xac)][_0xd76f15(0x1ad)]+_0xd76f15(0x2f0)+this[_0xd76f15(0xac)][_0xd76f15(0x287)]+_0xd76f15(0x29c)+(this[_0xd76f15(0xac)][_0xd76f15(0x287)]===_0xd76f15(0x1a7)?'boosts\x20health\x20to\x20'+this[_0xd76f15(0xac)][_0xd76f15(0x152)]+_0xd76f15(0xf7)+this[_0xd76f15(0xac)][_0xd76f15(0xd5)]:'drops\x20health\x20to\x20'+this['player2'][_0xd76f15(0x152)]+_0xd76f15(0x258)+this[_0xd76f15(0xac)][_0xd76f15(0xd5)])+'!'),log(this[_0xd76f15(0x178)][_0xd76f15(0x1ad)]+_0xd76f15(0x20a)+this['player1'][_0xd76f15(0x16d)]+'/'+this[_0xd76f15(0x178)][_0xd76f15(0x152)]+_0xd76f15(0x1aa)),log(this[_0xd76f15(0x28d)]['name']+_0xd76f15(0x349)),this[_0xd76f15(0x35d)](),this[_0xd76f15(0x2a4)]=this[_0xd76f15(0x28d)]===this[_0xd76f15(0x178)]?_0xd76f15(0x223):_0xd76f15(0x1b6),turnIndicator[_0xd76f15(0x26c)]=_0xd76f15(0x2c6)+this[_0xd76f15(0x1f5)]+_0xd76f15(0x308)+(this[_0xd76f15(0x28d)]===this['player1']?_0xd76f15(0x126):_0xd76f15(0x24d))+_0xd76f15(0xc5),this['currentTurn']===this['player2']&&setTimeout(function(){const _0x56d4ff=_0xd76f15;_0x3f552f[_0x56d4ff(0x1b6)]();},0x3e8);}[_0x1fe90e(0x78)](){const _0x42358e=_0x1fe90e;p1Name[_0x42358e(0x26c)]=this[_0x42358e(0x178)][_0x42358e(0x22c)]||this[_0x42358e(0xf3)]===_0x42358e(0x11e)?this[_0x42358e(0x178)][_0x42358e(0x1ad)]:_0x42358e(0x36c),p1Type[_0x42358e(0x26c)]=this[_0x42358e(0x178)]['type'],p1Strength['textContent']=this['player1'][_0x42358e(0x1c3)],p1Speed[_0x42358e(0x26c)]=this[_0x42358e(0x178)]['speed'],p1Tactics['textContent']=this[_0x42358e(0x178)][_0x42358e(0xd5)],p1Size['textContent']=this[_0x42358e(0x178)][_0x42358e(0x287)],p1Powerup[_0x42358e(0x26c)]=this['player1'][_0x42358e(0x36a)];const _0x4158e0=document[_0x42358e(0x86)]('p1-image'),_0x3b0ec0=_0x4158e0[_0x42358e(0x276)];if(this[_0x42358e(0x178)][_0x42358e(0x109)]===_0x42358e(0x25c)){if(_0x4158e0[_0x42358e(0x8e)]!==_0x42358e(0x29b)){const _0x3386a1=document[_0x42358e(0x2db)](_0x42358e(0x25c));_0x3386a1['id']=_0x42358e(0x282),_0x3386a1['src']=this[_0x42358e(0x178)][_0x42358e(0xc7)],_0x3386a1[_0x42358e(0x238)]=!![],_0x3386a1['loop']=!![],_0x3386a1[_0x42358e(0x21d)]=!![],_0x3386a1[_0x42358e(0x256)]=this[_0x42358e(0x178)][_0x42358e(0x1ad)],_0x3386a1[_0x42358e(0x150)]=()=>{const _0x5d9134=_0x42358e;_0x3386a1[_0x5d9134(0x243)]=this['player1'][_0x5d9134(0x2bf)];},_0x3b0ec0[_0x42358e(0x351)](_0x3386a1,_0x4158e0);}else _0x4158e0[_0x42358e(0x243)]=this[_0x42358e(0x178)][_0x42358e(0xc7)],_0x4158e0['onerror']=()=>{const _0x1f0a8b=_0x42358e;_0x4158e0[_0x1f0a8b(0x243)]=this[_0x1f0a8b(0x178)][_0x1f0a8b(0x2bf)];};}else{if(_0x4158e0[_0x42358e(0x8e)]!==_0x42358e(0x28e)){const _0x5d181c=document[_0x42358e(0x2db)](_0x42358e(0x85));_0x5d181c['id']=_0x42358e(0x282),_0x5d181c[_0x42358e(0x243)]=this[_0x42358e(0x178)]['imageUrl'],_0x5d181c[_0x42358e(0x256)]=this[_0x42358e(0x178)][_0x42358e(0x1ad)],_0x5d181c[_0x42358e(0x150)]=()=>{const _0x4f8f4b=_0x42358e;_0x5d181c[_0x4f8f4b(0x243)]=this[_0x4f8f4b(0x178)][_0x4f8f4b(0x2bf)];},_0x3b0ec0[_0x42358e(0x351)](_0x5d181c,_0x4158e0);}else _0x4158e0[_0x42358e(0x243)]=this['player1'][_0x42358e(0xc7)],_0x4158e0[_0x42358e(0x150)]=()=>{const _0x1794ab=_0x42358e;_0x4158e0[_0x1794ab(0x243)]=this[_0x1794ab(0x178)]['fallbackUrl'];};}const _0xf723=document[_0x42358e(0x86)](_0x42358e(0x282));_0xf723[_0x42358e(0x163)][_0x42358e(0x289)]=this[_0x42358e(0x178)][_0x42358e(0xe5)]===_0x42358e(0x262)?'scaleX(-1)':_0x42358e(0xf9),_0xf723['tagName']==='IMG'?_0xf723[_0x42358e(0x168)]=function(){const _0x40cc5f=_0x42358e;_0xf723[_0x40cc5f(0x163)]['display']=_0x40cc5f(0x2ff);}:_0xf723['style']['display']=_0x42358e(0x2ff),p1Hp[_0x42358e(0x26c)]=this['player1'][_0x42358e(0x16d)]+'/'+this[_0x42358e(0x178)]['maxHealth'],_0xf723['onclick']=()=>{const _0x4373ca=_0x42358e;console['log']('Player\x201\x20media\x20clicked'),this[_0x4373ca(0x106)](![]);};}[_0x1fe90e(0x220)](){const _0x25b15f=_0x1fe90e;p2Name[_0x25b15f(0x26c)]=this[_0x25b15f(0xac)][_0x25b15f(0x224)]?this[_0x25b15f(0xac)]['name']:this[_0x25b15f(0xf3)]===_0x25b15f(0x11e)?this[_0x25b15f(0xac)]['name']:_0x25b15f(0x21b),p2Type[_0x25b15f(0x26c)]=this[_0x25b15f(0xac)]['type'],p2Strength[_0x25b15f(0x26c)]=this[_0x25b15f(0xac)][_0x25b15f(0x1c3)],p2Speed[_0x25b15f(0x26c)]=this[_0x25b15f(0xac)]['speed'],p2Tactics[_0x25b15f(0x26c)]=this[_0x25b15f(0xac)][_0x25b15f(0xd5)],p2Size[_0x25b15f(0x26c)]=this[_0x25b15f(0xac)][_0x25b15f(0x287)],p2Powerup['textContent']=this['player2'][_0x25b15f(0x36a)];const _0x31884f=document[_0x25b15f(0x86)](_0x25b15f(0x31f)),_0x1ceb5b=_0x31884f['parentNode'];if(this[_0x25b15f(0xac)]['mediaType']===_0x25b15f(0x25c)){if(_0x31884f[_0x25b15f(0x8e)]!=='VIDEO'){const _0x3d925b=document[_0x25b15f(0x2db)]('video');_0x3d925b['id']=_0x25b15f(0x31f),_0x3d925b['src']=this[_0x25b15f(0xac)][_0x25b15f(0xc7)],_0x3d925b[_0x25b15f(0x238)]=!![],_0x3d925b['loop']=!![],_0x3d925b[_0x25b15f(0x21d)]=!![],_0x3d925b[_0x25b15f(0x256)]=this[_0x25b15f(0xac)][_0x25b15f(0x1ad)],_0x1ceb5b[_0x25b15f(0x351)](_0x3d925b,_0x31884f);}else _0x31884f[_0x25b15f(0x243)]=this[_0x25b15f(0xac)]['imageUrl'];}else{if(_0x31884f[_0x25b15f(0x8e)]!==_0x25b15f(0x28e)){const _0x3657da=document['createElement'](_0x25b15f(0x85));_0x3657da['id']=_0x25b15f(0x31f),_0x3657da[_0x25b15f(0x243)]=this[_0x25b15f(0xac)]['imageUrl'],_0x3657da[_0x25b15f(0x256)]=this[_0x25b15f(0xac)][_0x25b15f(0x1ad)],_0x1ceb5b['replaceChild'](_0x3657da,_0x31884f);}else _0x31884f[_0x25b15f(0x243)]=this['player2']['imageUrl'];}const _0x341b4b=document['getElementById'](_0x25b15f(0x31f));_0x341b4b[_0x25b15f(0x163)]['transform']=this[_0x25b15f(0xac)]['orientation']===_0x25b15f(0x2d3)?'scaleX(-1)':_0x25b15f(0xf9),_0x341b4b[_0x25b15f(0x8e)]===_0x25b15f(0x28e)?_0x341b4b[_0x25b15f(0x168)]=function(){const _0x1bab90=_0x25b15f;_0x341b4b[_0x1bab90(0x163)][_0x1bab90(0x76)]=_0x1bab90(0x2ff);}:_0x341b4b['style'][_0x25b15f(0x76)]=_0x25b15f(0x2ff),p2Hp[_0x25b15f(0x26c)]=this['player2'][_0x25b15f(0x16d)]+'/'+this['player2'][_0x25b15f(0x152)],_0x341b4b['classList'][_0x25b15f(0x267)]('winner',_0x25b15f(0x35e));}[_0x1fe90e(0x35d)](){const _0x551c96=_0x1fe90e;if(this['selectedBoss']){console[_0x551c96(0x266)](_0x551c96(0x17e));const _0x50a570=this['loadBoardState']();if(_0x50a570)this[_0x551c96(0x324)]=_0x50a570,console[_0x551c96(0x266)]('initBoard:\x20Loaded\x20board\x20from\x20localStorage\x20for\x20boss\x20battle');else{this['board']=[];for(let _0xd041b6=0x0;_0xd041b6<this['height'];_0xd041b6++){this[_0x551c96(0x324)][_0xd041b6]=[];for(let _0xd26f63=0x0;_0xd26f63<this['width'];_0xd26f63++){let _0x2e0fed;do{_0x2e0fed=this[_0x551c96(0x215)]();}while(_0xd26f63>=0x2&&this[_0x551c96(0x324)][_0xd041b6][_0xd26f63-0x1]?.['type']===_0x2e0fed['type']&&this[_0x551c96(0x324)][_0xd041b6][_0xd26f63-0x2]?.[_0x551c96(0x103)]===_0x2e0fed['type']||_0xd041b6>=0x2&&this[_0x551c96(0x324)][_0xd041b6-0x1]?.[_0xd26f63]?.['type']===_0x2e0fed['type']&&this[_0x551c96(0x324)][_0xd041b6-0x2]?.[_0xd26f63]?.['type']===_0x2e0fed['type']);this['board'][_0xd041b6][_0xd26f63]=_0x2e0fed;}}console['log'](_0x551c96(0x157));}this[_0x551c96(0x19a)]();}else{const _0x517ca0=this[_0x551c96(0x11f)]();if(_0x517ca0)this[_0x551c96(0x324)]=_0x517ca0,console[_0x551c96(0x266)](_0x551c96(0x13f),this[_0x551c96(0x1f5)]);else{this[_0x551c96(0x324)]=[];for(let _0x429718=0x0;_0x429718<this[_0x551c96(0x317)];_0x429718++){this[_0x551c96(0x324)][_0x429718]=[];for(let _0x4100ed=0x0;_0x4100ed<this[_0x551c96(0x28b)];_0x4100ed++){let _0x56c3ce;do{_0x56c3ce=this['createRandomTile']();}while(_0x4100ed>=0x2&&this[_0x551c96(0x324)][_0x429718][_0x4100ed-0x1]?.[_0x551c96(0x103)]===_0x56c3ce[_0x551c96(0x103)]&&this[_0x551c96(0x324)][_0x429718][_0x4100ed-0x2]?.[_0x551c96(0x103)]===_0x56c3ce[_0x551c96(0x103)]||_0x429718>=0x2&&this['board'][_0x429718-0x1]?.[_0x4100ed]?.[_0x551c96(0x103)]===_0x56c3ce[_0x551c96(0x103)]&&this[_0x551c96(0x324)][_0x429718-0x2]?.[_0x4100ed]?.['type']===_0x56c3ce[_0x551c96(0x103)]);this[_0x551c96(0x324)][_0x429718][_0x4100ed]=_0x56c3ce;}}this[_0x551c96(0x1de)](),console['log'](_0x551c96(0x1b5),this[_0x551c96(0x1f5)]);}this[_0x551c96(0x19a)]();}}[_0x1fe90e(0x215)](){return{'type':randomChoice(this['tileTypes']),'element':null};}[_0x1fe90e(0x19a)](){const _0x1b1654=_0x1fe90e;this[_0x1b1654(0x21f)]();const _0x408924=document['getElementById'](_0x1b1654(0xaf));_0x408924[_0x1b1654(0x107)]='';if(!this[_0x1b1654(0x324)]||!Array[_0x1b1654(0x333)](this[_0x1b1654(0x324)])||this[_0x1b1654(0x324)]['length']!==this[_0x1b1654(0x317)]){console[_0x1b1654(0x1cc)](_0x1b1654(0x2c7));return;}for(let _0x2db984=0x0;_0x2db984<this[_0x1b1654(0x317)];_0x2db984++){if(!Array['isArray'](this[_0x1b1654(0x324)][_0x2db984])){console[_0x1b1654(0x1cc)]('renderBoard:\x20Row\x20'+_0x2db984+_0x1b1654(0x1e1));continue;}for(let _0x192ef2=0x0;_0x192ef2<this[_0x1b1654(0x28b)];_0x192ef2++){const _0x5e72eb=this[_0x1b1654(0x324)][_0x2db984][_0x192ef2];if(!_0x5e72eb||_0x5e72eb[_0x1b1654(0x103)]===null)continue;const _0x13fc25=document[_0x1b1654(0x2db)](_0x1b1654(0x373));_0x13fc25[_0x1b1654(0x206)]=_0x1b1654(0xbf)+_0x5e72eb[_0x1b1654(0x103)];if(this[_0x1b1654(0x268)])_0x13fc25[_0x1b1654(0x31b)][_0x1b1654(0x233)]('game-over');const _0x532ec7=document[_0x1b1654(0x2db)](_0x1b1654(0x85));_0x532ec7['src']=_0x1b1654(0x177)+_0x5e72eb[_0x1b1654(0x103)]+_0x1b1654(0x1f2),_0x532ec7[_0x1b1654(0x256)]=_0x5e72eb[_0x1b1654(0x103)],_0x13fc25['appendChild'](_0x532ec7),_0x13fc25[_0x1b1654(0x9e)]['x']=_0x192ef2,_0x13fc25[_0x1b1654(0x9e)]['y']=_0x2db984,_0x408924[_0x1b1654(0xdc)](_0x13fc25),_0x5e72eb[_0x1b1654(0x221)]=_0x13fc25,(!this[_0x1b1654(0x360)]||this['selectedTile']&&(this[_0x1b1654(0x261)]['x']!==_0x192ef2||this['selectedTile']['y']!==_0x2db984))&&(_0x13fc25['style'][_0x1b1654(0x289)]='translate(0,\x200)'),this[_0x1b1654(0xff)]?_0x13fc25[_0x1b1654(0x7e)]('touchstart',_0x17ac64=>this[_0x1b1654(0x209)](_0x17ac64)):_0x13fc25[_0x1b1654(0x7e)](_0x1b1654(0x2b1),_0x468842=>this[_0x1b1654(0x148)](_0x468842));}}document[_0x1b1654(0x86)](_0x1b1654(0x323))[_0x1b1654(0x163)][_0x1b1654(0x76)]=this['gameOver']?_0x1b1654(0x2ff):_0x1b1654(0xf9),console[_0x1b1654(0x266)](_0x1b1654(0x14e));}[_0x1fe90e(0x24f)](){const _0x21dcbf=_0x1fe90e,_0x3d6f33=document[_0x21dcbf(0x86)](_0x21dcbf(0xaf));this[_0x21dcbf(0xff)]?(_0x3d6f33[_0x21dcbf(0x7e)]('touchstart',_0xcffc8d=>this['handleTouchStart'](_0xcffc8d)),_0x3d6f33[_0x21dcbf(0x7e)](_0x21dcbf(0x145),_0x1972e5=>this[_0x21dcbf(0x102)](_0x1972e5)),_0x3d6f33[_0x21dcbf(0x7e)](_0x21dcbf(0x229),_0x49eddf=>this[_0x21dcbf(0xdd)](_0x49eddf))):(_0x3d6f33[_0x21dcbf(0x7e)]('mousedown',_0x1a7e6d=>this['handleMouseDown'](_0x1a7e6d)),_0x3d6f33[_0x21dcbf(0x7e)](_0x21dcbf(0x2d2),_0x480833=>this['handleMouseMove'](_0x480833)),_0x3d6f33['addEventListener'](_0x21dcbf(0x249),_0x369b5e=>this[_0x21dcbf(0x2f5)](_0x369b5e)));document['getElementById'](_0x21dcbf(0x1e3))[_0x21dcbf(0x7e)]('click',()=>this[_0x21dcbf(0x305)]()),document['getElementById'](_0x21dcbf(0x23a))['addEventListener']('click',()=>{const _0x138ef7=_0x21dcbf;this[_0x138ef7(0x166)]();});const _0x221a36=document[_0x21dcbf(0x86)]('refresh-board');_0x221a36[_0x21dcbf(0x7e)](_0x21dcbf(0x2da),()=>{const _0x296efe=_0x21dcbf;console[_0x296efe(0x266)](_0x296efe(0x15c)),this[_0x296efe(0x19b)]();});const _0x4f2b7a=document[_0x21dcbf(0x86)](_0x21dcbf(0x36f)),_0x2bcd44=document[_0x21dcbf(0x86)](_0x21dcbf(0x282)),_0xfaa44b=document[_0x21dcbf(0x86)](_0x21dcbf(0x31f));_0x4f2b7a['addEventListener'](_0x21dcbf(0x2da),()=>{const _0x4e4a22=_0x21dcbf;console[_0x4e4a22(0x266)]('addEventListeners:\x20Switch\x20Monster\x20button\x20clicked'),this['showCharacterSelect'](![]);}),_0x2bcd44[_0x21dcbf(0x7e)](_0x21dcbf(0x2da),()=>{const _0x26282c=_0x21dcbf;console[_0x26282c(0x266)](_0x26282c(0x1c8)),this[_0x26282c(0x106)](![]);}),document[_0x21dcbf(0x86)](_0x21dcbf(0x13e))[_0x21dcbf(0x7e)](_0x21dcbf(0x2da),()=>this[_0x21dcbf(0x26e)](this['player1'],document[_0x21dcbf(0x86)](_0x21dcbf(0x282)),![])),document[_0x21dcbf(0x86)](_0x21dcbf(0x1b2))[_0x21dcbf(0x7e)]('click',()=>this[_0x21dcbf(0x26e)](this['player2'],document[_0x21dcbf(0x86)](_0x21dcbf(0x31f)),!![]));}[_0x1fe90e(0x305)](){const _0x351cdc=_0x1fe90e;console[_0x351cdc(0x266)](_0x351cdc(0x193)+this[_0x351cdc(0x1f5)]+_0x351cdc(0x2ba)+this[_0x351cdc(0xac)][_0x351cdc(0x16d)]),this[_0x351cdc(0xac)][_0x351cdc(0x16d)]<=0x0&&this[_0x351cdc(0x1f5)]>opponentsConfig[_0x351cdc(0x10a)]&&(this['currentLevel']=0x1,console[_0x351cdc(0x266)]('Reset\x20to\x20Level\x201:\x20currentLevel='+this[_0x351cdc(0x1f5)])),this['initGame'](),console['log'](_0x351cdc(0x207)+this[_0x351cdc(0x1f5)]);}[_0x1fe90e(0x148)](_0x420167){const _0x520cec=_0x1fe90e;if(this[_0x520cec(0x268)]||this[_0x520cec(0x2a4)]!=='playerTurn'||this[_0x520cec(0x28d)]!==this[_0x520cec(0x178)])return;_0x420167['preventDefault']();const _0x4ea6b8=this[_0x520cec(0x2a6)](_0x420167);if(!_0x4ea6b8||!_0x4ea6b8[_0x520cec(0x221)])return;this[_0x520cec(0x360)]=!![],this['selectedTile']={'x':_0x4ea6b8['x'],'y':_0x4ea6b8['y']},_0x4ea6b8[_0x520cec(0x221)][_0x520cec(0x31b)][_0x520cec(0x233)](_0x520cec(0x89));const _0x329589=document[_0x520cec(0x86)](_0x520cec(0xaf))['getBoundingClientRect']();this[_0x520cec(0x254)]=_0x420167['clientX']-(_0x329589[_0x520cec(0x22a)]+this[_0x520cec(0x261)]['x']*this[_0x520cec(0x2e0)]),this[_0x520cec(0x330)]=_0x420167[_0x520cec(0xab)]-(_0x329589['top']+this[_0x520cec(0x261)]['y']*this['tileSizeWithGap']);}[_0x1fe90e(0x2c5)](_0x3f3551){const _0x4b62d6=_0x1fe90e;if(!this[_0x4b62d6(0x360)]||!this[_0x4b62d6(0x261)]||this['gameOver']||this[_0x4b62d6(0x2a4)]!=='playerTurn')return;_0x3f3551[_0x4b62d6(0x1df)]();const _0x4f07ce=document['getElementById']('game-board')['getBoundingClientRect'](),_0x3da6f8=_0x3f3551['clientX']-_0x4f07ce[_0x4b62d6(0x22a)]-this[_0x4b62d6(0x254)],_0x2ab471=_0x3f3551[_0x4b62d6(0xab)]-_0x4f07ce[_0x4b62d6(0x133)]-this['offsetY'],_0x16e4f6=this[_0x4b62d6(0x324)][this[_0x4b62d6(0x261)]['y']][this[_0x4b62d6(0x261)]['x']][_0x4b62d6(0x221)];_0x16e4f6[_0x4b62d6(0x163)][_0x4b62d6(0x33f)]='';if(!this[_0x4b62d6(0x17f)]){const _0x54e8cf=Math[_0x4b62d6(0x9d)](_0x3da6f8-this[_0x4b62d6(0x261)]['x']*this[_0x4b62d6(0x2e0)]),_0x3ba5ad=Math[_0x4b62d6(0x9d)](_0x2ab471-this['selectedTile']['y']*this[_0x4b62d6(0x2e0)]);if(_0x54e8cf>_0x3ba5ad&&_0x54e8cf>0x5)this['dragDirection']='row';else{if(_0x3ba5ad>_0x54e8cf&&_0x3ba5ad>0x5)this[_0x4b62d6(0x17f)]='column';}}if(!this[_0x4b62d6(0x17f)])return;if(this[_0x4b62d6(0x17f)]==='row'){const _0x2ee5fe=Math[_0x4b62d6(0x2cb)](0x0,Math[_0x4b62d6(0x153)]((this['width']-0x1)*this[_0x4b62d6(0x2e0)],_0x3da6f8));_0x16e4f6[_0x4b62d6(0x163)][_0x4b62d6(0x289)]=_0x4b62d6(0x2fe)+(_0x2ee5fe-this[_0x4b62d6(0x261)]['x']*this['tileSizeWithGap'])+'px,\x200)\x20scale(1.05)',this[_0x4b62d6(0x92)]={'x':Math[_0x4b62d6(0x179)](_0x2ee5fe/this[_0x4b62d6(0x2e0)]),'y':this[_0x4b62d6(0x261)]['y']};}else{if(this[_0x4b62d6(0x17f)]===_0x4b62d6(0x105)){const _0x26bc8d=Math[_0x4b62d6(0x2cb)](0x0,Math['min']((this[_0x4b62d6(0x317)]-0x1)*this['tileSizeWithGap'],_0x2ab471));_0x16e4f6[_0x4b62d6(0x163)][_0x4b62d6(0x289)]=_0x4b62d6(0x174)+(_0x26bc8d-this[_0x4b62d6(0x261)]['y']*this[_0x4b62d6(0x2e0)])+_0x4b62d6(0x11b),this[_0x4b62d6(0x92)]={'x':this[_0x4b62d6(0x261)]['x'],'y':Math[_0x4b62d6(0x179)](_0x26bc8d/this[_0x4b62d6(0x2e0)])};}}}['handleMouseUp'](_0x54df13){const _0x26c231=_0x1fe90e;if(!this[_0x26c231(0x360)]||!this[_0x26c231(0x261)]||!this[_0x26c231(0x92)]||this[_0x26c231(0x268)]||this['gameState']!=='playerTurn'){if(this[_0x26c231(0x261)]){const _0x4db7c4=this[_0x26c231(0x324)][this[_0x26c231(0x261)]['y']][this['selectedTile']['x']];if(_0x4db7c4[_0x26c231(0x221)])_0x4db7c4['element'][_0x26c231(0x31b)]['remove'](_0x26c231(0x89));}this[_0x26c231(0x360)]=![],this[_0x26c231(0x261)]=null,this['targetTile']=null,this['dragDirection']=null,this[_0x26c231(0x19a)]();return;}const _0x51cf01=this[_0x26c231(0x324)][this[_0x26c231(0x261)]['y']][this[_0x26c231(0x261)]['x']];if(_0x51cf01['element'])_0x51cf01[_0x26c231(0x221)]['classList'][_0x26c231(0x267)](_0x26c231(0x89));this['slideTiles'](this[_0x26c231(0x261)]['x'],this[_0x26c231(0x261)]['y'],this['targetTile']['x'],this['targetTile']['y']),this[_0x26c231(0x360)]=![],this[_0x26c231(0x261)]=null,this[_0x26c231(0x92)]=null,this[_0x26c231(0x17f)]=null;}[_0x1fe90e(0x209)](_0x1ea6fc){const _0x25acdb=_0x1fe90e;if(this[_0x25acdb(0x268)]||this[_0x25acdb(0x2a4)]!==_0x25acdb(0x223)||this[_0x25acdb(0x28d)]!==this[_0x25acdb(0x178)])return;_0x1ea6fc[_0x25acdb(0x1df)]();const _0x2356bf=this[_0x25acdb(0x2a6)](_0x1ea6fc[_0x25acdb(0xe9)][0x0]);if(!_0x2356bf||!_0x2356bf[_0x25acdb(0x221)])return;this[_0x25acdb(0x360)]=!![],this[_0x25acdb(0x261)]={'x':_0x2356bf['x'],'y':_0x2356bf['y']},_0x2356bf[_0x25acdb(0x221)][_0x25acdb(0x31b)][_0x25acdb(0x233)](_0x25acdb(0x89));const _0x3ad12e=document[_0x25acdb(0x86)](_0x25acdb(0xaf))[_0x25acdb(0x340)]();this['offsetX']=_0x1ea6fc[_0x25acdb(0xe9)][0x0][_0x25acdb(0x30b)]-(_0x3ad12e['left']+this[_0x25acdb(0x261)]['x']*this['tileSizeWithGap']),this[_0x25acdb(0x330)]=_0x1ea6fc[_0x25acdb(0xe9)][0x0][_0x25acdb(0xab)]-(_0x3ad12e[_0x25acdb(0x133)]+this[_0x25acdb(0x261)]['y']*this['tileSizeWithGap']);}[_0x1fe90e(0x102)](_0x1a617e){const _0x24e9b9=_0x1fe90e;if(!this['isDragging']||!this[_0x24e9b9(0x261)]||this[_0x24e9b9(0x268)]||this[_0x24e9b9(0x2a4)]!==_0x24e9b9(0x223))return;_0x1a617e[_0x24e9b9(0x1df)]();const _0xf8a9aa=document[_0x24e9b9(0x86)](_0x24e9b9(0xaf))[_0x24e9b9(0x340)](),_0x3b950b=_0x1a617e[_0x24e9b9(0xe9)][0x0][_0x24e9b9(0x30b)]-_0xf8a9aa[_0x24e9b9(0x22a)]-this[_0x24e9b9(0x254)],_0x1d9cda=_0x1a617e[_0x24e9b9(0xe9)][0x0][_0x24e9b9(0xab)]-_0xf8a9aa[_0x24e9b9(0x133)]-this[_0x24e9b9(0x330)],_0x16dae8=this[_0x24e9b9(0x324)][this[_0x24e9b9(0x261)]['y']][this[_0x24e9b9(0x261)]['x']][_0x24e9b9(0x221)];requestAnimationFrame(()=>{const _0x115cca=_0x24e9b9;if(!this[_0x115cca(0x17f)]){const _0x36bab6=Math[_0x115cca(0x9d)](_0x3b950b-this['selectedTile']['x']*this[_0x115cca(0x2e0)]),_0x358df5=Math[_0x115cca(0x9d)](_0x1d9cda-this['selectedTile']['y']*this[_0x115cca(0x2e0)]);if(_0x36bab6>_0x358df5&&_0x36bab6>0x7)this[_0x115cca(0x17f)]='row';else{if(_0x358df5>_0x36bab6&&_0x358df5>0x7)this[_0x115cca(0x17f)]=_0x115cca(0x105);}}_0x16dae8[_0x115cca(0x163)]['transition']='';if(this['dragDirection']==='row'){const _0x3b1fa2=Math[_0x115cca(0x2cb)](0x0,Math[_0x115cca(0x153)]((this[_0x115cca(0x28b)]-0x1)*this[_0x115cca(0x2e0)],_0x3b950b));_0x16dae8['style'][_0x115cca(0x289)]='translate('+(_0x3b1fa2-this['selectedTile']['x']*this[_0x115cca(0x2e0)])+_0x115cca(0x23c),this[_0x115cca(0x92)]={'x':Math['round'](_0x3b1fa2/this[_0x115cca(0x2e0)]),'y':this['selectedTile']['y']};}else{if(this[_0x115cca(0x17f)]===_0x115cca(0x105)){const _0x3655d0=Math['max'](0x0,Math['min']((this[_0x115cca(0x317)]-0x1)*this[_0x115cca(0x2e0)],_0x1d9cda));_0x16dae8[_0x115cca(0x163)][_0x115cca(0x289)]=_0x115cca(0x174)+(_0x3655d0-this[_0x115cca(0x261)]['y']*this[_0x115cca(0x2e0)])+'px)\x20scale(1.05)',this['targetTile']={'x':this[_0x115cca(0x261)]['x'],'y':Math[_0x115cca(0x179)](_0x3655d0/this[_0x115cca(0x2e0)])};}}});}[_0x1fe90e(0xdd)](_0x26c518){const _0x1c25ba=_0x1fe90e;if(!this[_0x1c25ba(0x360)]||!this[_0x1c25ba(0x261)]||!this[_0x1c25ba(0x92)]||this[_0x1c25ba(0x268)]||this[_0x1c25ba(0x2a4)]!=='playerTurn'){if(this[_0x1c25ba(0x261)]){const _0x2100ca=this[_0x1c25ba(0x324)][this[_0x1c25ba(0x261)]['y']][this[_0x1c25ba(0x261)]['x']];if(_0x2100ca[_0x1c25ba(0x221)])_0x2100ca[_0x1c25ba(0x221)]['classList'][_0x1c25ba(0x267)](_0x1c25ba(0x89));}this[_0x1c25ba(0x360)]=![],this[_0x1c25ba(0x261)]=null,this[_0x1c25ba(0x92)]=null,this[_0x1c25ba(0x17f)]=null,this[_0x1c25ba(0x19a)]();return;}const _0x1501d3=this['board'][this[_0x1c25ba(0x261)]['y']][this[_0x1c25ba(0x261)]['x']];if(_0x1501d3[_0x1c25ba(0x221)])_0x1501d3[_0x1c25ba(0x221)][_0x1c25ba(0x31b)][_0x1c25ba(0x267)](_0x1c25ba(0x89));this[_0x1c25ba(0x226)](this[_0x1c25ba(0x261)]['x'],this[_0x1c25ba(0x261)]['y'],this[_0x1c25ba(0x92)]['x'],this[_0x1c25ba(0x92)]['y']),this['isDragging']=![],this[_0x1c25ba(0x261)]=null,this[_0x1c25ba(0x92)]=null,this['dragDirection']=null;}[_0x1fe90e(0x2a6)](_0x3e441a){const _0x3967f6=_0x1fe90e,_0x2a9214=document[_0x3967f6(0x86)](_0x3967f6(0xaf))[_0x3967f6(0x340)](),_0x5baf03=Math[_0x3967f6(0x18a)]((_0x3e441a[_0x3967f6(0x30b)]-_0x2a9214[_0x3967f6(0x22a)])/this[_0x3967f6(0x2e0)]),_0x205e56=Math[_0x3967f6(0x18a)]((_0x3e441a[_0x3967f6(0xab)]-_0x2a9214[_0x3967f6(0x133)])/this[_0x3967f6(0x2e0)]);if(_0x5baf03>=0x0&&_0x5baf03<this[_0x3967f6(0x28b)]&&_0x205e56>=0x0&&_0x205e56<this['height'])return{'x':_0x5baf03,'y':_0x205e56,'element':this[_0x3967f6(0x324)][_0x205e56][_0x5baf03][_0x3967f6(0x221)]};return null;}[_0x1fe90e(0x226)](_0x242893,_0x2ea1d2,_0x74c2cc,_0x1fe220,_0x272c18=()=>this['endTurn']()){const _0x1b9d6e=_0x1fe90e,_0x2789e8=this['tileSizeWithGap'];let _0x281237;const _0x25d11e=[],_0x253cc2=[];if(_0x2ea1d2===_0x1fe220){_0x281237=_0x242893<_0x74c2cc?0x1:-0x1;const _0x571334=Math['min'](_0x242893,_0x74c2cc),_0xbb5afc=Math[_0x1b9d6e(0x2cb)](_0x242893,_0x74c2cc);for(let _0xb49826=_0x571334;_0xb49826<=_0xbb5afc;_0xb49826++){_0x25d11e[_0x1b9d6e(0x23d)]({...this['board'][_0x2ea1d2][_0xb49826]}),_0x253cc2[_0x1b9d6e(0x23d)](this[_0x1b9d6e(0x324)][_0x2ea1d2][_0xb49826][_0x1b9d6e(0x221)]);}}else{if(_0x242893===_0x74c2cc){_0x281237=_0x2ea1d2<_0x1fe220?0x1:-0x1;const _0x3e8926=Math['min'](_0x2ea1d2,_0x1fe220),_0x9a135a=Math['max'](_0x2ea1d2,_0x1fe220);for(let _0x5d475c=_0x3e8926;_0x5d475c<=_0x9a135a;_0x5d475c++){_0x25d11e[_0x1b9d6e(0x23d)]({...this[_0x1b9d6e(0x324)][_0x5d475c][_0x242893]}),_0x253cc2[_0x1b9d6e(0x23d)](this[_0x1b9d6e(0x324)][_0x5d475c][_0x242893]['element']);}}}const _0x1f39bb=this[_0x1b9d6e(0x324)][_0x2ea1d2][_0x242893]['element'],_0x376bb7=(_0x74c2cc-_0x242893)*_0x2789e8,_0x8cd1fa=(_0x1fe220-_0x2ea1d2)*_0x2789e8;_0x1f39bb[_0x1b9d6e(0x163)]['transition']='transform\x200.2s\x20ease',_0x1f39bb[_0x1b9d6e(0x163)][_0x1b9d6e(0x289)]=_0x1b9d6e(0x2fe)+_0x376bb7+'px,\x20'+_0x8cd1fa+'px)';let _0x494dc1=0x0;if(_0x2ea1d2===_0x1fe220)for(let _0x353f52=Math[_0x1b9d6e(0x153)](_0x242893,_0x74c2cc);_0x353f52<=Math[_0x1b9d6e(0x2cb)](_0x242893,_0x74c2cc);_0x353f52++){if(_0x353f52===_0x242893)continue;const _0x32dc9b=_0x281237*-_0x2789e8*(_0x353f52-_0x242893)/Math[_0x1b9d6e(0x9d)](_0x74c2cc-_0x242893);_0x253cc2[_0x494dc1][_0x1b9d6e(0x163)][_0x1b9d6e(0x33f)]=_0x1b9d6e(0x259),_0x253cc2[_0x494dc1][_0x1b9d6e(0x163)][_0x1b9d6e(0x289)]=_0x1b9d6e(0x2fe)+_0x32dc9b+_0x1b9d6e(0x1da),_0x494dc1++;}else for(let _0x3e03c3=Math[_0x1b9d6e(0x153)](_0x2ea1d2,_0x1fe220);_0x3e03c3<=Math[_0x1b9d6e(0x2cb)](_0x2ea1d2,_0x1fe220);_0x3e03c3++){if(_0x3e03c3===_0x2ea1d2)continue;const _0xf87ea9=_0x281237*-_0x2789e8*(_0x3e03c3-_0x2ea1d2)/Math['abs'](_0x1fe220-_0x2ea1d2);_0x253cc2[_0x494dc1][_0x1b9d6e(0x163)][_0x1b9d6e(0x33f)]=_0x1b9d6e(0x259),_0x253cc2[_0x494dc1][_0x1b9d6e(0x163)][_0x1b9d6e(0x289)]=_0x1b9d6e(0x174)+_0xf87ea9+_0x1b9d6e(0x1f8),_0x494dc1++;}setTimeout(()=>{const _0x5e65dc=_0x1b9d6e;if(_0x2ea1d2===_0x1fe220){const _0x310767=this[_0x5e65dc(0x324)][_0x2ea1d2],_0x3790ff=[..._0x310767];if(_0x242893<_0x74c2cc){for(let _0x53544b=_0x242893;_0x53544b<_0x74c2cc;_0x53544b++)_0x310767[_0x53544b]=_0x3790ff[_0x53544b+0x1];}else{for(let _0x25e082=_0x242893;_0x25e082>_0x74c2cc;_0x25e082--)_0x310767[_0x25e082]=_0x3790ff[_0x25e082-0x1];}_0x310767[_0x74c2cc]=_0x3790ff[_0x242893];}else{const _0x23c739=[];for(let _0x2b4fe5=0x0;_0x2b4fe5<this['height'];_0x2b4fe5++)_0x23c739[_0x2b4fe5]={...this[_0x5e65dc(0x324)][_0x2b4fe5][_0x242893]};if(_0x2ea1d2<_0x1fe220){for(let _0x5ff6c7=_0x2ea1d2;_0x5ff6c7<_0x1fe220;_0x5ff6c7++)this['board'][_0x5ff6c7][_0x242893]=_0x23c739[_0x5ff6c7+0x1];}else{for(let _0x1e2e32=_0x2ea1d2;_0x1e2e32>_0x1fe220;_0x1e2e32--)this['board'][_0x1e2e32][_0x242893]=_0x23c739[_0x1e2e32-0x1];}this[_0x5e65dc(0x324)][_0x1fe220][_0x74c2cc]=_0x23c739[_0x2ea1d2];}this['renderBoard']();const _0x2b94d8=this[_0x5e65dc(0x374)](_0x74c2cc,_0x1fe220);_0x2b94d8?this[_0x5e65dc(0x2a4)]='animating':(log(_0x5e65dc(0x240)),this[_0x5e65dc(0x265)][_0x5e65dc(0xe1)]['play'](),_0x1f39bb['style'][_0x5e65dc(0x33f)]='transform\x200.2s\x20ease',_0x1f39bb[_0x5e65dc(0x163)][_0x5e65dc(0x289)]=_0x5e65dc(0x253),_0x253cc2[_0x5e65dc(0x160)](_0x3f497b=>{const _0x4ff4f6=_0x5e65dc;_0x3f497b['style'][_0x4ff4f6(0x33f)]=_0x4ff4f6(0x259),_0x3f497b['style'][_0x4ff4f6(0x289)]=_0x4ff4f6(0x253);}),setTimeout(()=>{const _0x46f07c=_0x5e65dc;if(_0x2ea1d2===_0x1fe220){const _0x5f349a=Math[_0x46f07c(0x153)](_0x242893,_0x74c2cc);for(let _0x24f614=0x0;_0x24f614<_0x25d11e[_0x46f07c(0x10a)];_0x24f614++){this[_0x46f07c(0x324)][_0x2ea1d2][_0x5f349a+_0x24f614]={..._0x25d11e[_0x24f614],'element':_0x253cc2[_0x24f614]};}}else{const _0x10187c=Math['min'](_0x2ea1d2,_0x1fe220);for(let _0x37c5b4=0x0;_0x37c5b4<_0x25d11e[_0x46f07c(0x10a)];_0x37c5b4++){this['board'][_0x10187c+_0x37c5b4][_0x242893]={..._0x25d11e[_0x37c5b4],'element':_0x253cc2[_0x37c5b4]};}}this['renderBoard'](),this[_0x46f07c(0x2a4)]=this[_0x46f07c(0x28d)]===this[_0x46f07c(0x178)]?_0x46f07c(0x223):_0x46f07c(0x1b6);},0xc8));},0xc8);}[_0x1fe90e(0x374)](_0x42f090=null,_0x420967=null){const _0xff6a82=_0x1fe90e;console[_0xff6a82(0x266)](_0xff6a82(0x368),this[_0xff6a82(0x268)]);if(this[_0xff6a82(0x268)])return console[_0xff6a82(0x266)](_0xff6a82(0x2eb)),![];const _0x132224=_0x42f090!==null&&_0x420967!==null;console[_0xff6a82(0x266)](_0xff6a82(0x2a2)+_0x132224);const _0xe3617=this['checkMatches']();console['log'](_0xff6a82(0x142)+_0xe3617[_0xff6a82(0x10a)]+'\x20matches:',_0xe3617);let _0x1cb20b=0x1,_0x327690='';if(_0x132224&&_0xe3617[_0xff6a82(0x10a)]>0x1){const _0x12020a=_0xe3617['reduce']((_0xa4aa71,_0x3551c0)=>_0xa4aa71+_0x3551c0[_0xff6a82(0x1ba)],0x0);console[_0xff6a82(0x266)](_0xff6a82(0x1ae)+_0x12020a);if(_0x12020a>=0x6&&_0x12020a<=0x8)_0x1cb20b=1.2,_0x327690=_0xff6a82(0x18c)+_0x12020a+_0xff6a82(0x20e),this[_0xff6a82(0x265)][_0xff6a82(0x263)][_0xff6a82(0x16f)]();else _0x12020a>=0x9&&(_0x1cb20b=0x3,_0x327690=_0xff6a82(0x31d)+_0x12020a+'\x20tiles\x20matched\x20for\x20a\x20200%\x20bonus!',this[_0xff6a82(0x265)]['multiMatch'][_0xff6a82(0x16f)]());}if(_0xe3617[_0xff6a82(0x10a)]>0x0){const _0x266ead=new Set();let _0xd2ed93=0x0;const _0x471b4c=this[_0xff6a82(0x28d)],_0x1725b0=this[_0xff6a82(0x28d)]===this[_0xff6a82(0x178)]?this[_0xff6a82(0xac)]:this[_0xff6a82(0x178)];try{_0xe3617[_0xff6a82(0x160)](_0x13a8b6=>{const _0x1e14b5=_0xff6a82;console[_0x1e14b5(0x266)](_0x1e14b5(0x364),_0x13a8b6),_0x13a8b6['coordinates'][_0x1e14b5(0x160)](_0x1a33b2=>_0x266ead[_0x1e14b5(0x233)](_0x1a33b2));const _0x484d7f=this[_0x1e14b5(0x234)](_0x13a8b6,_0x132224);console[_0x1e14b5(0x266)](_0x1e14b5(0x111)+_0x484d7f);if(this[_0x1e14b5(0x268)]){console['log'](_0x1e14b5(0xe0));return;}if(_0x484d7f>0x0)_0xd2ed93+=_0x484d7f;});if(this[_0xff6a82(0x268)])return console[_0xff6a82(0x266)](_0xff6a82(0x2fd)),!![];return console['log'](_0xff6a82(0x2c3)+_0xd2ed93+',\x20tiles\x20to\x20clear:',[..._0x266ead]),_0xd2ed93>0x0&&!this[_0xff6a82(0x268)]&&setTimeout(()=>{const _0x351e7e=_0xff6a82;if(this['gameOver']){console[_0x351e7e(0x266)](_0x351e7e(0x2ed));return;}console[_0x351e7e(0x266)](_0x351e7e(0x149),_0x1725b0[_0x351e7e(0x1ad)]),this[_0x351e7e(0x1fe)](_0x1725b0,_0xd2ed93);},0x64),setTimeout(()=>{const _0x18d2d6=_0xff6a82;if(this[_0x18d2d6(0x268)]){console[_0x18d2d6(0x266)](_0x18d2d6(0x196));return;}console[_0x18d2d6(0x266)](_0x18d2d6(0x270),[..._0x266ead]),_0x266ead[_0x18d2d6(0x160)](_0x2305ab=>{const _0xd7afc5=_0x18d2d6,[_0x92e0c3,_0x18a122]=_0x2305ab['split'](',')[_0xd7afc5(0x1c4)](Number);this['board'][_0x18a122][_0x92e0c3]?.[_0xd7afc5(0x221)]?this['board'][_0x18a122][_0x92e0c3][_0xd7afc5(0x221)]['classList'][_0xd7afc5(0x233)](_0xd7afc5(0x32f)):console[_0xd7afc5(0x1cc)](_0xd7afc5(0x2cd)+_0x92e0c3+','+_0x18a122+_0xd7afc5(0x275));}),setTimeout(()=>{const _0x4a59d0=_0x18d2d6;if(this[_0x4a59d0(0x268)]){console[_0x4a59d0(0x266)](_0x4a59d0(0x1be));return;}console[_0x4a59d0(0x266)](_0x4a59d0(0xee),[..._0x266ead]),_0x266ead['forEach'](_0x5c3306=>{const _0x5dd52f=_0x4a59d0,[_0x915862,_0x5ebba2]=_0x5c3306[_0x5dd52f(0x303)](',')[_0x5dd52f(0x1c4)](Number);this[_0x5dd52f(0x324)][_0x5ebba2][_0x915862]&&(this[_0x5dd52f(0x324)][_0x5ebba2][_0x915862][_0x5dd52f(0x103)]=null,this[_0x5dd52f(0x324)][_0x5ebba2][_0x915862][_0x5dd52f(0x221)]=null);}),this[_0x4a59d0(0x265)][_0x4a59d0(0x1e2)][_0x4a59d0(0x16f)](),console[_0x4a59d0(0x266)](_0x4a59d0(0x2b8));if(_0x1cb20b>0x1&&this['roundStats'][_0x4a59d0(0x10a)]>0x0){const _0x25aab3=this['roundStats'][this[_0x4a59d0(0x129)][_0x4a59d0(0x10a)]-0x1],_0x29cb01=_0x25aab3[_0x4a59d0(0x1d9)];_0x25aab3[_0x4a59d0(0x1d9)]=Math[_0x4a59d0(0x179)](_0x25aab3[_0x4a59d0(0x1d9)]*_0x1cb20b),_0x327690&&(log(_0x327690),log(_0x4a59d0(0x217)+_0x29cb01+'\x20to\x20'+_0x25aab3['points']+_0x4a59d0(0xb7)));}this[_0x4a59d0(0x185)](()=>{const _0x403e5a=_0x4a59d0;if(this['gameOver']){console['log'](_0x403e5a(0x2ec));return;}console['log'](_0x403e5a(0x2b3)),this['endTurn']();});},0x12c);},0xc8),!![];}catch(_0xa7cf57){return console[_0xff6a82(0x162)]('Error\x20in\x20resolveMatches:',_0xa7cf57),this[_0xff6a82(0x2a4)]=this[_0xff6a82(0x28d)]===this[_0xff6a82(0x178)]?_0xff6a82(0x223):'aiTurn',![];}}return console['log']('No\x20matches\x20found,\x20returning\x20false'),![];}[_0x1fe90e(0x181)](){const _0x10bc7f=_0x1fe90e;console[_0x10bc7f(0x266)](_0x10bc7f(0xb9));const _0x294cd3=[];try{const _0x5c23db=[];for(let _0x16ed31=0x0;_0x16ed31<this[_0x10bc7f(0x317)];_0x16ed31++){let _0x12ae92=0x0;for(let _0x132fec=0x0;_0x132fec<=this[_0x10bc7f(0x28b)];_0x132fec++){const _0x25706d=_0x132fec<this[_0x10bc7f(0x28b)]?this[_0x10bc7f(0x324)][_0x16ed31][_0x132fec]?.['type']:null;if(_0x25706d!==this[_0x10bc7f(0x324)][_0x16ed31][_0x12ae92]?.['type']||_0x132fec===this[_0x10bc7f(0x28b)]){const _0x1e6589=_0x132fec-_0x12ae92;if(_0x1e6589>=0x3){const _0xb2d097=new Set();for(let _0x51f8f1=_0x12ae92;_0x51f8f1<_0x132fec;_0x51f8f1++){_0xb2d097[_0x10bc7f(0x233)](_0x51f8f1+','+_0x16ed31);}_0x5c23db['push']({'type':this['board'][_0x16ed31][_0x12ae92][_0x10bc7f(0x103)],'coordinates':_0xb2d097}),console[_0x10bc7f(0x266)](_0x10bc7f(0xbb)+_0x16ed31+_0x10bc7f(0x10d)+_0x12ae92+'-'+(_0x132fec-0x1)+':',[..._0xb2d097]);}_0x12ae92=_0x132fec;}}}for(let _0x1e0ca1=0x0;_0x1e0ca1<this[_0x10bc7f(0x28b)];_0x1e0ca1++){let _0x15a213=0x0;for(let _0x1d8a7c=0x0;_0x1d8a7c<=this['height'];_0x1d8a7c++){const _0x4a0196=_0x1d8a7c<this[_0x10bc7f(0x317)]?this[_0x10bc7f(0x324)][_0x1d8a7c][_0x1e0ca1]?.[_0x10bc7f(0x103)]:null;if(_0x4a0196!==this[_0x10bc7f(0x324)][_0x15a213][_0x1e0ca1]?.['type']||_0x1d8a7c===this[_0x10bc7f(0x317)]){const _0x10e023=_0x1d8a7c-_0x15a213;if(_0x10e023>=0x3){const _0x569d6c=new Set();for(let _0x243939=_0x15a213;_0x243939<_0x1d8a7c;_0x243939++){_0x569d6c['add'](_0x1e0ca1+','+_0x243939);}_0x5c23db[_0x10bc7f(0x23d)]({'type':this[_0x10bc7f(0x324)][_0x15a213][_0x1e0ca1][_0x10bc7f(0x103)],'coordinates':_0x569d6c}),console[_0x10bc7f(0x266)]('Vertical\x20match\x20found\x20at\x20col\x20'+_0x1e0ca1+_0x10bc7f(0x11c)+_0x15a213+'-'+(_0x1d8a7c-0x1)+':',[..._0x569d6c]);}_0x15a213=_0x1d8a7c;}}}const _0xee3d76=[],_0x303436=new Set();return _0x5c23db[_0x10bc7f(0x160)]((_0x19c6d8,_0x5bccee)=>{const _0x52f853=_0x10bc7f;if(_0x303436[_0x52f853(0xc6)](_0x5bccee))return;const _0x2ee9b7={'type':_0x19c6d8[_0x52f853(0x103)],'coordinates':new Set(_0x19c6d8[_0x52f853(0x154)])};_0x303436[_0x52f853(0x233)](_0x5bccee);for(let _0x4e4bf3=0x0;_0x4e4bf3<_0x5c23db[_0x52f853(0x10a)];_0x4e4bf3++){if(_0x303436[_0x52f853(0xc6)](_0x4e4bf3))continue;const _0xaa1abc=_0x5c23db[_0x4e4bf3];if(_0xaa1abc[_0x52f853(0x103)]===_0x2ee9b7[_0x52f853(0x103)]){const _0x3e9b0d=[..._0xaa1abc[_0x52f853(0x154)]][_0x52f853(0x342)](_0x244fcf=>_0x2ee9b7[_0x52f853(0x154)][_0x52f853(0xc6)](_0x244fcf));_0x3e9b0d&&(_0xaa1abc[_0x52f853(0x154)][_0x52f853(0x160)](_0x2cd659=>_0x2ee9b7['coordinates']['add'](_0x2cd659)),_0x303436['add'](_0x4e4bf3));}}_0xee3d76[_0x52f853(0x23d)]({'type':_0x2ee9b7[_0x52f853(0x103)],'coordinates':_0x2ee9b7['coordinates'],'totalTiles':_0x2ee9b7[_0x52f853(0x154)][_0x52f853(0x287)]});}),_0x294cd3[_0x10bc7f(0x23d)](..._0xee3d76),console[_0x10bc7f(0x266)](_0x10bc7f(0x251),_0x294cd3),_0x294cd3;}catch(_0x2bf64f){return console[_0x10bc7f(0x162)](_0x10bc7f(0x2c9),_0x2bf64f),[];}}[_0x1fe90e(0x234)](_0x4fff87,_0xa2bcaa=!![]){const _0x3ccc80=_0x1fe90e;console[_0x3ccc80(0x266)](_0x3ccc80(0x23b),_0x4fff87,_0x3ccc80(0x7a),_0xa2bcaa);const _0x4e74e6=this[_0x3ccc80(0x28d)],_0x259d69=this[_0x3ccc80(0x28d)]===this[_0x3ccc80(0x178)]?this[_0x3ccc80(0xac)]:this[_0x3ccc80(0x178)],_0xa69fa1=_0x4fff87['type'],_0x1ef1b2=_0x4fff87['totalTiles'];let _0x242106=0x0,_0x134a57=0x0;console[_0x3ccc80(0x266)](_0x259d69[_0x3ccc80(0x1ad)]+'\x20health\x20before\x20match:\x20'+_0x259d69[_0x3ccc80(0x16d)]);_0x1ef1b2==0x4&&(this[_0x3ccc80(0x265)]['powerGem'][_0x3ccc80(0x16f)](),log(_0x4e74e6[_0x3ccc80(0x1ad)]+'\x20created\x20a\x20match\x20of\x20'+_0x1ef1b2+_0x3ccc80(0x93)));_0x1ef1b2>=0x5&&(this[_0x3ccc80(0x265)][_0x3ccc80(0x2c4)]['play'](),log(_0x4e74e6[_0x3ccc80(0x1ad)]+'\x20created\x20a\x20match\x20of\x20'+_0x1ef1b2+'\x20tiles!'));if(_0xa69fa1==='first-attack'||_0xa69fa1===_0x3ccc80(0x30c)||_0xa69fa1===_0x3ccc80(0x2b0)||_0xa69fa1===_0x3ccc80(0x2c0)){_0x242106=Math[_0x3ccc80(0x179)](_0x4e74e6['strength']*(_0x1ef1b2===0x3?0x2:_0x1ef1b2===0x4?0x3:0x4));let _0x49a0b4=0x1;if(_0x1ef1b2===0x4)_0x49a0b4=1.5;else _0x1ef1b2>=0x5&&(_0x49a0b4=0x2);_0x242106=Math[_0x3ccc80(0x179)](_0x242106*_0x49a0b4),console['log'](_0x3ccc80(0x25d)+_0x4e74e6[_0x3ccc80(0x1c3)]*(_0x1ef1b2===0x3?0x2:_0x1ef1b2===0x4?0x3:0x4)+',\x20Match\x20bonus:\x20'+_0x49a0b4+_0x3ccc80(0x1ee)+_0x242106);_0xa69fa1===_0x3ccc80(0x2b0)&&(_0x242106=Math[_0x3ccc80(0x179)](_0x242106*1.2),console[_0x3ccc80(0x266)](_0x3ccc80(0x25a)+_0x242106));_0x4e74e6[_0x3ccc80(0x344)]&&(_0x242106+=_0x4e74e6['boostValue']||0xa,_0x4e74e6[_0x3ccc80(0x344)]=![],log(_0x4e74e6['name']+_0x3ccc80(0x79)),console['log'](_0x3ccc80(0x33c)+_0x242106));_0x134a57=_0x242106;const _0x120715=_0x259d69[_0x3ccc80(0xd5)]*0xa;Math['random']()*0x64<_0x120715&&(_0x242106=Math[_0x3ccc80(0x18a)](_0x242106/0x2),log(_0x259d69[_0x3ccc80(0x1ad)]+_0x3ccc80(0x2ef)+_0x242106+'\x20damage!'),console[_0x3ccc80(0x266)](_0x3ccc80(0x302)+_0x242106));let _0x4fbc01=0x0;_0x259d69['lastStandActive']&&(_0x4fbc01=Math[_0x3ccc80(0x153)](_0x242106,0x5),_0x242106=Math[_0x3ccc80(0x2cb)](0x0,_0x242106-_0x4fbc01),_0x259d69[_0x3ccc80(0x1dc)]=![],console[_0x3ccc80(0x266)]('Last\x20Stand\x20applied,\x20mitigated\x20'+_0x4fbc01+',\x20damage:\x20'+_0x242106));const _0x5dcf4a=_0xa69fa1===_0x3ccc80(0x34c)?_0x3ccc80(0xbd):_0xa69fa1==='second-attack'?_0x3ccc80(0x19f):_0x3ccc80(0x365);let _0x585ad6;if(_0x4fbc01>0x0)_0x585ad6=_0x4e74e6[_0x3ccc80(0x1ad)]+_0x3ccc80(0x100)+_0x5dcf4a+_0x3ccc80(0xa2)+_0x259d69['name']+'\x20for\x20'+_0x134a57+_0x3ccc80(0xe3)+_0x259d69[_0x3ccc80(0x1ad)]+_0x3ccc80(0x2ce)+_0x4fbc01+_0x3ccc80(0x1fc)+_0x242106+_0x3ccc80(0xae);else _0xa69fa1===_0x3ccc80(0x2c0)?_0x585ad6=_0x4e74e6[_0x3ccc80(0x1ad)]+_0x3ccc80(0xfc)+_0x242106+'\x20damage\x20to\x20'+_0x259d69['name']+_0x3ccc80(0x188):_0x585ad6=_0x4e74e6[_0x3ccc80(0x1ad)]+'\x20uses\x20'+_0x5dcf4a+_0x3ccc80(0xa2)+_0x259d69[_0x3ccc80(0x1ad)]+_0x3ccc80(0x15b)+_0x242106+_0x3ccc80(0xae);_0xa2bcaa?log(_0x585ad6):log('Cascade:\x20'+_0x585ad6),_0x259d69['health']=Math[_0x3ccc80(0x2cb)](0x0,_0x259d69[_0x3ccc80(0x16d)]-_0x242106),console[_0x3ccc80(0x266)](_0x259d69['name']+'\x20health\x20after\x20damage:\x20'+_0x259d69['health']),this['updateHealth'](_0x259d69),console[_0x3ccc80(0x266)]('Calling\x20checkGameOver\x20from\x20handleMatch'),this[_0x3ccc80(0x123)](),!this[_0x3ccc80(0x268)]&&(console[_0x3ccc80(0x266)](_0x3ccc80(0x290)),this['animateAttack'](_0x4e74e6,_0x242106,_0xa69fa1));}else _0xa69fa1==='power-up'&&(this[_0x3ccc80(0x1e0)](_0x4e74e6,_0x259d69,_0x1ef1b2),!this['gameOver']&&(console[_0x3ccc80(0x266)](_0x3ccc80(0x202)),this[_0x3ccc80(0x1a3)](_0x4e74e6)));(!this[_0x3ccc80(0x129)][this[_0x3ccc80(0x129)][_0x3ccc80(0x10a)]-0x1]||this['roundStats'][this[_0x3ccc80(0x129)][_0x3ccc80(0x10a)]-0x1]['completed'])&&this['roundStats'][_0x3ccc80(0x23d)]({'points':0x0,'matches':0x0,'healthPercentage':0x0,'completed':![]});const _0x13940d=this[_0x3ccc80(0x129)][this[_0x3ccc80(0x129)]['length']-0x1];return _0x13940d['points']+=_0x242106,_0x13940d[_0x3ccc80(0x291)]+=0x1,console['log']('handleMatch\x20completed,\x20damage\x20dealt:\x20'+_0x242106),_0x242106;}[_0x1fe90e(0x185)](_0x23a708){const _0x243ba1=_0x1fe90e;if(this[_0x243ba1(0x268)]){console[_0x243ba1(0x266)](_0x243ba1(0xa3));return;}const _0x1f1e99=this[_0x243ba1(0x25e)](),_0x5c13ad='falling';for(let _0x4e6c51=0x0;_0x4e6c51<this['width'];_0x4e6c51++){for(let _0x5d0f71=0x0;_0x5d0f71<this[_0x243ba1(0x317)];_0x5d0f71++){const _0x4f8e51=this[_0x243ba1(0x324)][_0x5d0f71][_0x4e6c51];if(_0x4f8e51[_0x243ba1(0x221)]&&_0x4f8e51['element'][_0x243ba1(0x163)][_0x243ba1(0x289)]==='translate(0px,\x200px)'){const _0x421298=this[_0x243ba1(0x16b)](_0x4e6c51,_0x5d0f71);_0x421298>0x0&&(_0x4f8e51[_0x243ba1(0x221)]['classList'][_0x243ba1(0x233)](_0x5c13ad),_0x4f8e51['element']['style'][_0x243ba1(0x289)]=_0x243ba1(0x174)+_0x421298*this[_0x243ba1(0x2e0)]+_0x243ba1(0x1f8));}}}this['renderBoard'](),_0x1f1e99?setTimeout(()=>{const _0x5aed65=_0x243ba1;if(this[_0x5aed65(0x268)]){console['log'](_0x5aed65(0x2e3));return;}this[_0x5aed65(0x265)][_0x5aed65(0x194)][_0x5aed65(0x16f)]();const _0x463fe0=this['resolveMatches'](),_0x4d329a=document[_0x5aed65(0x1d1)]('.'+_0x5c13ad);_0x4d329a[_0x5aed65(0x160)](_0x1ad942=>{const _0x14c317=_0x5aed65;_0x1ad942[_0x14c317(0x31b)][_0x14c317(0x267)](_0x5c13ad),_0x1ad942[_0x14c317(0x163)]['transform']=_0x14c317(0x253);}),!_0x463fe0&&_0x23a708();},0x12c):_0x23a708();}[_0x1fe90e(0x25e)](){const _0x40e593=_0x1fe90e;let _0x27673c=![];for(let _0x575871=0x0;_0x575871<this['width'];_0x575871++){let _0x166c1c=0x0;for(let _0x45bef5=this['height']-0x1;_0x45bef5>=0x0;_0x45bef5--){if(!this[_0x40e593(0x324)][_0x45bef5][_0x575871]['type'])_0x166c1c++;else _0x166c1c>0x0&&(this[_0x40e593(0x324)][_0x45bef5+_0x166c1c][_0x575871]=this[_0x40e593(0x324)][_0x45bef5][_0x575871],this['board'][_0x45bef5][_0x575871]={'type':null,'element':null},_0x27673c=!![]);}for(let _0x3565a0=0x0;_0x3565a0<_0x166c1c;_0x3565a0++){this[_0x40e593(0x324)][_0x3565a0][_0x575871]=this[_0x40e593(0x215)](),_0x27673c=!![];}}return _0x27673c;}['countEmptyBelow'](_0x118bbd,_0x2682b7){const _0x3f17dd=_0x1fe90e;let _0x29d5d5=0x0;for(let _0x3aa69a=_0x2682b7+0x1;_0x3aa69a<this[_0x3f17dd(0x317)];_0x3aa69a++){if(!this[_0x3f17dd(0x324)][_0x3aa69a][_0x118bbd][_0x3f17dd(0x103)])_0x29d5d5++;else break;}return _0x29d5d5;}[_0x1fe90e(0x1e0)](_0x223668,_0x42f65b,_0x30b34b){const _0x475f4b=_0x1fe90e,_0x1c9692=0x1-_0x42f65b[_0x475f4b(0xd5)]*0.05;let _0x17cb76,_0x424c6b,_0x127b87,_0x36c0c0=0x1,_0x420555='';if(_0x30b34b===0x4)_0x36c0c0=1.5,_0x420555='\x20(50%\x20bonus\x20for\x20match-4)';else _0x30b34b>=0x5&&(_0x36c0c0=0x2,_0x420555=_0x475f4b(0x33d));if(_0x223668['powerup']===_0x475f4b(0x124))_0x424c6b=0xa*_0x36c0c0,_0x17cb76=Math[_0x475f4b(0x18a)](_0x424c6b*_0x1c9692),_0x127b87=_0x424c6b-_0x17cb76,_0x223668[_0x475f4b(0x16d)]=Math[_0x475f4b(0x153)](_0x223668[_0x475f4b(0x152)],_0x223668['health']+_0x17cb76),log(_0x223668[_0x475f4b(0x1ad)]+_0x475f4b(0xc3)+_0x17cb76+'\x20HP'+_0x420555+(_0x42f65b[_0x475f4b(0xd5)]>0x0?_0x475f4b(0x1a5)+_0x424c6b+_0x475f4b(0x15a)+_0x127b87+_0x475f4b(0x1f9)+_0x42f65b[_0x475f4b(0x1ad)]+_0x475f4b(0x2cf):'')+'!');else{if(_0x223668[_0x475f4b(0x36a)]===_0x475f4b(0x176))_0x424c6b=0xa*_0x36c0c0,_0x17cb76=Math[_0x475f4b(0x18a)](_0x424c6b*_0x1c9692),_0x127b87=_0x424c6b-_0x17cb76,_0x223668[_0x475f4b(0x344)]=!![],_0x223668[_0x475f4b(0x310)]=_0x17cb76,log(_0x223668['name']+_0x475f4b(0xbe)+_0x17cb76+'\x20damage'+_0x420555+(_0x42f65b[_0x475f4b(0xd5)]>0x0?_0x475f4b(0x1a5)+_0x424c6b+',\x20reduced\x20by\x20'+_0x127b87+_0x475f4b(0x1f9)+_0x42f65b[_0x475f4b(0x1ad)]+_0x475f4b(0x2cf):'')+'!');else{if(_0x223668[_0x475f4b(0x36a)]===_0x475f4b(0x84))_0x424c6b=0x7*_0x36c0c0,_0x17cb76=Math[_0x475f4b(0x18a)](_0x424c6b*_0x1c9692),_0x127b87=_0x424c6b-_0x17cb76,_0x223668[_0x475f4b(0x16d)]=Math[_0x475f4b(0x153)](_0x223668[_0x475f4b(0x152)],_0x223668[_0x475f4b(0x16d)]+_0x17cb76),log(_0x223668[_0x475f4b(0x1ad)]+_0x475f4b(0x7d)+_0x17cb76+_0x475f4b(0x246)+_0x420555+(_0x42f65b['tactics']>0x0?_0x475f4b(0x1a5)+_0x424c6b+_0x475f4b(0x15a)+_0x127b87+_0x475f4b(0x1f9)+_0x42f65b['name']+_0x475f4b(0x2cf):'')+'!');else _0x223668[_0x475f4b(0x36a)]===_0x475f4b(0x2ac)&&(_0x424c6b=0x5*_0x36c0c0,_0x17cb76=Math['floor'](_0x424c6b*_0x1c9692),_0x127b87=_0x424c6b-_0x17cb76,_0x223668['health']=Math['min'](_0x223668[_0x475f4b(0x152)],_0x223668[_0x475f4b(0x16d)]+_0x17cb76),log(_0x223668[_0x475f4b(0x1ad)]+_0x475f4b(0x1b4)+_0x17cb76+_0x475f4b(0x246)+_0x420555+(_0x42f65b[_0x475f4b(0xd5)]>0x0?'\x20(originally\x20'+_0x424c6b+_0x475f4b(0x15a)+_0x127b87+_0x475f4b(0x1f9)+_0x42f65b[_0x475f4b(0x1ad)]+_0x475f4b(0x2cf):'')+'!'));}}this['updateHealth'](_0x223668);}['updateHealth'](_0x4e4f50){const _0x1d9c59=_0x1fe90e,_0x5004de=_0x4e4f50===this[_0x1d9c59(0x178)]?p1Health:p2Health,_0x555c84=_0x4e4f50===this[_0x1d9c59(0x178)]?p1Hp:p2Hp,_0x5e7d03=_0x4e4f50[_0x1d9c59(0x16d)]/_0x4e4f50[_0x1d9c59(0x152)]*0x64;_0x5004de[_0x1d9c59(0x163)]['width']=_0x5e7d03+'%';let _0x5039d2;if(_0x5e7d03>0x4b)_0x5039d2=_0x1d9c59(0x343);else{if(_0x5e7d03>0x32)_0x5039d2='#FFC105';else _0x5e7d03>0x19?_0x5039d2=_0x1d9c59(0x230):_0x5039d2=_0x1d9c59(0x175);}_0x5004de[_0x1d9c59(0x163)][_0x1d9c59(0x320)]=_0x5039d2,_0x555c84[_0x1d9c59(0x26c)]=_0x4e4f50[_0x1d9c59(0x16d)]+'/'+_0x4e4f50[_0x1d9c59(0x152)];}['endTurn'](){const _0x1d46ba=_0x1fe90e;if(this[_0x1d46ba(0x2a4)]===_0x1d46ba(0x268)||this[_0x1d46ba(0x268)]){console['log'](_0x1d46ba(0x2ec));return;}if(this[_0x1d46ba(0x12e)]){if(this[_0x1d46ba(0x28d)]===this[_0x1d46ba(0x178)])console[_0x1d46ba(0x266)](_0x1d46ba(0x208)),this[_0x1d46ba(0x114)](),console[_0x1d46ba(0x266)](_0x1d46ba(0x197)),this[_0x1d46ba(0x1cb)]();else this[_0x1d46ba(0x28d)]===this[_0x1d46ba(0xac)]&&(console[_0x1d46ba(0x266)](_0x1d46ba(0x358)),this[_0x1d46ba(0x195)](),console[_0x1d46ba(0x266)]('endTurn:\x20Boss\x20turn\x20ending,\x20saving\x20board\x20state'),this[_0x1d46ba(0x1cb)]());}this['currentTurn']=this[_0x1d46ba(0x28d)]===this['player1']?this['player2']:this['player1'],this[_0x1d46ba(0x2a4)]=this[_0x1d46ba(0x28d)]===this[_0x1d46ba(0x178)]?_0x1d46ba(0x223):_0x1d46ba(0x1b6),turnIndicator[_0x1d46ba(0x26c)]=this['selectedBoss']?'Boss\x20Battle\x20-\x20'+(this[_0x1d46ba(0x28d)]===this[_0x1d46ba(0x178)]?_0x1d46ba(0x126):_0x1d46ba(0x348))+_0x1d46ba(0xc5):'Level\x20'+this[_0x1d46ba(0x1f5)]+_0x1d46ba(0x308)+(this[_0x1d46ba(0x28d)]===this[_0x1d46ba(0x178)]?_0x1d46ba(0x126):_0x1d46ba(0x24d))+_0x1d46ba(0xc5),log(_0x1d46ba(0x346)+(this[_0x1d46ba(0x28d)]===this[_0x1d46ba(0x178)]?'Player':this[_0x1d46ba(0x12e)]?_0x1d46ba(0x348):_0x1d46ba(0x24d))),this[_0x1d46ba(0x28d)]===this[_0x1d46ba(0xac)]&&!this['gameOver']&&setTimeout(()=>this[_0x1d46ba(0x1b6)](),0x3e8);}[_0x1fe90e(0x1b6)](){const _0x4fbea9=_0x1fe90e;if(this['gameState']!==_0x4fbea9(0x1b6)||this['currentTurn']!==this[_0x4fbea9(0xac)])return;this['gameState']=_0x4fbea9(0xcc);const _0x51506b=this[_0x4fbea9(0x2f1)]();if(_0x51506b){log(this['player2'][_0x4fbea9(0x1ad)]+_0x4fbea9(0x295)+_0x51506b['x1']+',\x20'+_0x51506b['y1']+')\x20to\x20('+_0x51506b['x2']+',\x20'+_0x51506b['y2']+')');const _0x223c3f=()=>{const _0x208f8d=_0x4fbea9;this[_0x208f8d(0x12e)]&&this[_0x208f8d(0x195)](),this['endTurn']();};this[_0x4fbea9(0x226)](_0x51506b['x1'],_0x51506b['y1'],_0x51506b['x2'],_0x51506b['y2'],_0x223c3f);}else log(this[_0x4fbea9(0xac)]['name']+_0x4fbea9(0x151)),this['selectedBoss']&&this[_0x4fbea9(0x195)](),this['endTurn']();}[_0x1fe90e(0x2f1)](){const _0x5ed015=_0x1fe90e;for(let _0x62495d=0x0;_0x62495d<this[_0x5ed015(0x317)];_0x62495d++){for(let _0x44d3e7=0x0;_0x44d3e7<this[_0x5ed015(0x28b)];_0x44d3e7++){if(_0x44d3e7<this[_0x5ed015(0x28b)]-0x1&&this[_0x5ed015(0xe6)](_0x44d3e7,_0x62495d,_0x44d3e7+0x1,_0x62495d))return{'x1':_0x44d3e7,'y1':_0x62495d,'x2':_0x44d3e7+0x1,'y2':_0x62495d};if(_0x62495d<this[_0x5ed015(0x317)]-0x1&&this[_0x5ed015(0xe6)](_0x44d3e7,_0x62495d,_0x44d3e7,_0x62495d+0x1))return{'x1':_0x44d3e7,'y1':_0x62495d,'x2':_0x44d3e7,'y2':_0x62495d+0x1};}}return null;}[_0x1fe90e(0xe6)](_0x454b5e,_0x394eca,_0x22332b,_0x4b1d3d){const _0x4c1698=_0x1fe90e,_0x1992d4={...this['board'][_0x394eca][_0x454b5e]},_0x3b7d1d={...this[_0x4c1698(0x324)][_0x4b1d3d][_0x22332b]};this['board'][_0x394eca][_0x454b5e]=_0x3b7d1d,this['board'][_0x4b1d3d][_0x22332b]=_0x1992d4;const _0x2a873f=this[_0x4c1698(0x181)]()['length']>0x0;return this[_0x4c1698(0x324)][_0x394eca][_0x454b5e]=_0x1992d4,this[_0x4c1698(0x324)][_0x4b1d3d][_0x22332b]=_0x3b7d1d,_0x2a873f;}async[_0x1fe90e(0x123)](){const _0x54a4ab=_0x1fe90e;if(this['gameOver']||this['isCheckingGameOver']){console[_0x54a4ab(0x266)]('checkGameOver\x20skipped:\x20gameOver='+this['gameOver']+_0x54a4ab(0x1d4)+this[_0x54a4ab(0x236)]+_0x54a4ab(0x192)+this[_0x54a4ab(0x1f5)]);return;}this[_0x54a4ab(0x236)]=!![],console[_0x54a4ab(0x266)](_0x54a4ab(0x34a)+this['currentLevel']+',\x20player1.health='+this[_0x54a4ab(0x178)]['health']+_0x54a4ab(0x2ba)+this['player2'][_0x54a4ab(0x16d)]+',\x20selectedBoss='+(this[_0x54a4ab(0x12e)]?this[_0x54a4ab(0x12e)]['name']:_0x54a4ab(0xf9)));const _0x1b8e79=document['getElementById'](_0x54a4ab(0x1e3)),_0x1bf056=document['getElementById']('leaderboard-button');_0x1bf056[_0x54a4ab(0x107)]='';let _0x233295;this[_0x54a4ab(0x12e)]?_0x233295='\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<form\x20action=\x22leaderboards.php\x22\x20method=\x22post\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<input\x20type=\x22hidden\x22\x20name=\x22filterbybosses\x22\x20id=\x22filterbybosses\x22\x20value=\x22weekly-bosses\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<input\x20id=\x22leaderboard\x22\x20type=\x22submit\x22\x20value=\x22LEADERBOARD\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20</form>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20':_0x233295='\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<form\x20action=\x22leaderboards.php\x22\x20method=\x22post\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<input\x20type=\x22hidden\x22\x20name=\x22filterbystreak\x22\x20id=\x22filterbystreak\x22\x20value=\x22monthly-monstrocity\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<input\x20id=\x22leaderboard\x22\x20type=\x22submit\x22\x20value=\x22LEADERBOARD\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20</form>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20';_0x1bf056[_0x54a4ab(0x107)]=_0x233295;if(this[_0x54a4ab(0x178)][_0x54a4ab(0x16d)]<=0x0){console[_0x54a4ab(0x266)](_0x54a4ab(0xeb)+!!this['selectedBoss']);this[_0x54a4ab(0x12e)]&&(this[_0x54a4ab(0x178)][_0x54a4ab(0x16d)]=0x0,await this[_0x54a4ab(0x195)](),console[_0x54a4ab(0x266)](_0x54a4ab(0x2f8)),this[_0x54a4ab(0xa5)]());this['gameOver']=!![],this[_0x54a4ab(0x2a4)]=_0x54a4ab(0x268),gameOver[_0x54a4ab(0x26c)]=_0x54a4ab(0x199),turnIndicator['textContent']=_0x54a4ab(0xb8);const _0x19b4a7=document['createElement'](_0x54a4ab(0x1f4));_0x19b4a7['id']=_0x54a4ab(0x1e3),_0x19b4a7[_0x54a4ab(0x26c)]=this[_0x54a4ab(0x12e)]?_0x54a4ab(0x219):'TRY\x20AGAIN',_0x1b8e79[_0x54a4ab(0x276)]['replaceChild'](_0x19b4a7,_0x1b8e79),_0x19b4a7[_0x54a4ab(0x7e)]('click',()=>{const _0x2e251f=_0x54a4ab;console[_0x2e251f(0x266)](_0x2e251f(0x231)+_0x19b4a7['textContent']+_0x2e251f(0x2d7)+(this[_0x2e251f(0x12e)]?this[_0x2e251f(0x12e)][_0x2e251f(0x1ad)]:_0x2e251f(0xf9))),document[_0x2e251f(0x86)](_0x2e251f(0x323))[_0x2e251f(0x163)][_0x2e251f(0x76)]=_0x2e251f(0xf9),this[_0x2e251f(0x12e)]?this['showBossSelectionScreen']():this[_0x2e251f(0x305)]();}),document[_0x54a4ab(0x86)](_0x54a4ab(0x323))[_0x54a4ab(0x163)][_0x54a4ab(0x76)]=_0x54a4ab(0x2ff);try{this[_0x54a4ab(0x265)][_0x54a4ab(0x1d5)][_0x54a4ab(0x16f)]();}catch(_0x3fb8c2){console[_0x54a4ab(0x162)](_0x54a4ab(0x31e),_0x3fb8c2);}}else{if(this[_0x54a4ab(0xac)][_0x54a4ab(0x16d)]<=0x0){console[_0x54a4ab(0x266)]('Player\x202\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(win)');this['selectedBoss']&&(this[_0x54a4ab(0xac)][_0x54a4ab(0x16d)]=0x0,await this[_0x54a4ab(0x114)](),console['log'](_0x54a4ab(0x20d)),this['clearBoardState']());this['gameOver']=!![],this['gameState']=_0x54a4ab(0x268),gameOver[_0x54a4ab(0x26c)]=_0x54a4ab(0x127),turnIndicator[_0x54a4ab(0x26c)]=_0x54a4ab(0xb8);const _0x3abbcd=document[_0x54a4ab(0x2db)](_0x54a4ab(0x1f4));_0x3abbcd['id']=_0x54a4ab(0x1e3),_0x3abbcd[_0x54a4ab(0x26c)]=this[_0x54a4ab(0x12e)]?_0x54a4ab(0x219):this[_0x54a4ab(0x1f5)]===opponentsConfig[_0x54a4ab(0x10a)]?_0x54a4ab(0x94):_0x54a4ab(0x101),_0x1b8e79[_0x54a4ab(0x276)][_0x54a4ab(0x351)](_0x3abbcd,_0x1b8e79),_0x3abbcd[_0x54a4ab(0x7e)]('click',()=>{const _0x4401ca=_0x54a4ab;console['log']('checkGameOver:\x20Button\x20clicked,\x20text='+_0x3abbcd[_0x4401ca(0x26c)]+_0x4401ca(0x2d7)+(this[_0x4401ca(0x12e)]?this[_0x4401ca(0x12e)][_0x4401ca(0x1ad)]:_0x4401ca(0xf9))),document[_0x4401ca(0x86)](_0x4401ca(0x323))[_0x4401ca(0x163)]['display']='none',this[_0x4401ca(0x12e)]?this[_0x4401ca(0x33b)]():this[_0x4401ca(0x305)]();}),document[_0x54a4ab(0x86)](_0x54a4ab(0x323))[_0x54a4ab(0x163)][_0x54a4ab(0x76)]=_0x54a4ab(0x2ff);if(!this['selectedBoss']){if(this['currentTurn']===this[_0x54a4ab(0x178)]){const _0x5ac024=this[_0x54a4ab(0x129)][this[_0x54a4ab(0x129)]['length']-0x1];if(_0x5ac024&&!_0x5ac024[_0x54a4ab(0x35f)]){_0x5ac024[_0x54a4ab(0x2be)]=this[_0x54a4ab(0x178)][_0x54a4ab(0x16d)]/this['player1'][_0x54a4ab(0x152)]*0x64,_0x5ac024[_0x54a4ab(0x35f)]=!![];const _0x206b1d=_0x5ac024['matches']>0x0?_0x5ac024[_0x54a4ab(0x1d9)]/_0x5ac024['matches']/0x64*(_0x5ac024['healthPercentage']+0x14)*(0x1+this[_0x54a4ab(0x1f5)]/0x38):0x0;log(_0x54a4ab(0x141)+_0x5ac024[_0x54a4ab(0x1d9)]+',\x20matches='+_0x5ac024[_0x54a4ab(0x291)]+',\x20healthPercentage='+_0x5ac024['healthPercentage'][_0x54a4ab(0x2a1)](0x2)+_0x54a4ab(0x366)+this[_0x54a4ab(0x1f5)]),log(_0x54a4ab(0x136)+_0x5ac024[_0x54a4ab(0x1d9)]+_0x54a4ab(0x2bd)+_0x5ac024[_0x54a4ab(0x291)]+_0x54a4ab(0x25f)+_0x5ac024[_0x54a4ab(0x2be)]+_0x54a4ab(0x19e)+this[_0x54a4ab(0x1f5)]+_0x54a4ab(0x22e)+_0x206b1d),this[_0x54a4ab(0x132)]+=_0x206b1d,log('Round\x20Won!\x20Points:\x20'+_0x5ac024[_0x54a4ab(0x1d9)]+_0x54a4ab(0x341)+_0x5ac024[_0x54a4ab(0x291)]+_0x54a4ab(0x1cf)+_0x5ac024[_0x54a4ab(0x2be)][_0x54a4ab(0x2a1)](0x2)+'%'),log(_0x54a4ab(0x23e)+_0x206b1d+_0x54a4ab(0x237)+this[_0x54a4ab(0x132)]);}}await this['saveScoreToDatabase'](this[_0x54a4ab(0x1f5)]);if(this['currentLevel']===opponentsConfig['length']){try{this[_0x54a4ab(0x265)][_0x54a4ab(0xdf)][_0x54a4ab(0x16f)]();}catch(_0x32f5fd){console['error']('Error\x20playing\x20finalWin\x20sound:',_0x32f5fd);}log(_0x54a4ab(0x31c)+this[_0x54a4ab(0x132)]),this[_0x54a4ab(0x132)]=0x0,await this['clearProgress'](),log('Game\x20completed!\x20Grand\x20total\x20score\x20reset.');}else this[_0x54a4ab(0x184)](),this['currentLevel']+=0x1,await this[_0x54a4ab(0x173)](),console['log'](_0x54a4ab(0x14d)+this[_0x54a4ab(0x1f5)]),this[_0x54a4ab(0x265)][_0x54a4ab(0x293)][_0x54a4ab(0x16f)]();}let _0x641e14,_0x3d0d70=this[_0x54a4ab(0xac)][_0x54a4ab(0x2bf)]||_0x54a4ab(0x2c8);const _0x432a47=themes[_0x54a4ab(0x172)](_0x1bb38e=>_0x1bb38e[_0x54a4ab(0x2a5)])[_0x54a4ab(0x1f0)](_0x7e69ce=>_0x7e69ce['value']===this[_0x54a4ab(0xf3)]),_0x1e9dd2=_0x432a47?.[_0x54a4ab(0x143)]||_0x54a4ab(0x274);this[_0x54a4ab(0x12e)]?_0x641e14=this[_0x54a4ab(0xac)][_0x54a4ab(0x30d)]||_0x54a4ab(0xd4)+this[_0x54a4ab(0xac)][_0x54a4ab(0x1ad)][_0x54a4ab(0x332)]()[_0x54a4ab(0x1d2)](/ /g,'-')+'.'+(this[_0x54a4ab(0xac)][_0x54a4ab(0x143)]||'png'):_0x641e14=this[_0x54a4ab(0x334)]+_0x54a4ab(0x210)+this[_0x54a4ab(0xac)][_0x54a4ab(0x1ad)][_0x54a4ab(0x332)]()['replace'](/ /g,'-')+'.'+_0x1e9dd2;const _0x13ba08=document[_0x54a4ab(0x86)](_0x54a4ab(0x31f)),_0x59a0c9=_0x13ba08['parentNode'];if(this['player2'][_0x54a4ab(0x109)]===_0x54a4ab(0x25c)){if(_0x13ba08['tagName']!=='VIDEO'){const _0x309dfd=document[_0x54a4ab(0x2db)](_0x54a4ab(0x25c));_0x309dfd['id']='p2-image',_0x309dfd[_0x54a4ab(0x243)]=_0x641e14,_0x309dfd[_0x54a4ab(0x238)]=!![],_0x309dfd[_0x54a4ab(0x91)]=!![],_0x309dfd[_0x54a4ab(0x21d)]=!![],_0x309dfd[_0x54a4ab(0x256)]=this[_0x54a4ab(0xac)][_0x54a4ab(0x1ad)],_0x309dfd[_0x54a4ab(0x150)]=()=>{const _0x56e5c0=_0x54a4ab;console[_0x56e5c0(0x1cc)](_0x56e5c0(0x131)+_0x641e14+_0x56e5c0(0x7f)),_0x309dfd[_0x56e5c0(0x243)]=_0x3d0d70;},_0x59a0c9[_0x54a4ab(0x351)](_0x309dfd,_0x13ba08);}else _0x13ba08[_0x54a4ab(0x243)]=_0x641e14,_0x13ba08[_0x54a4ab(0x150)]=()=>{const _0xc609a1=_0x54a4ab;console[_0xc609a1(0x1cc)](_0xc609a1(0x131)+_0x641e14+',\x20using\x20fallback'),_0x13ba08[_0xc609a1(0x243)]=_0x3d0d70;};}else{if(_0x13ba08[_0x54a4ab(0x8e)]!==_0x54a4ab(0x28e)){const _0x4d955=document[_0x54a4ab(0x2db)](_0x54a4ab(0x85));_0x4d955['id']=_0x54a4ab(0x31f),_0x4d955[_0x54a4ab(0x243)]=_0x641e14,_0x4d955[_0x54a4ab(0x256)]=this[_0x54a4ab(0xac)][_0x54a4ab(0x1ad)],_0x4d955[_0x54a4ab(0x150)]=()=>{const _0x23f1ae=_0x54a4ab;console[_0x23f1ae(0x1cc)]('Failed\x20to\x20load\x20battle-damaged\x20image:\x20'+_0x641e14+_0x23f1ae(0x7f)),_0x4d955[_0x23f1ae(0x243)]=_0x3d0d70;},_0x59a0c9[_0x54a4ab(0x351)](_0x4d955,_0x13ba08);}else _0x13ba08[_0x54a4ab(0x243)]=_0x641e14,_0x13ba08[_0x54a4ab(0x150)]=()=>{const _0x2fee91=_0x54a4ab;console[_0x2fee91(0x1cc)]('Failed\x20to\x20load\x20battle-damaged\x20image:\x20'+_0x641e14+_0x2fee91(0x7f)),_0x13ba08[_0x2fee91(0x243)]=_0x3d0d70;};}const _0x12d768=document[_0x54a4ab(0x86)]('p2-image');_0x12d768[_0x54a4ab(0x163)][_0x54a4ab(0x76)]='block',_0x12d768[_0x54a4ab(0x31b)]['add']('loser'),p1Image[_0x54a4ab(0x31b)][_0x54a4ab(0x233)](_0x54a4ab(0x2a7)),this['renderBoard']();}}this[_0x54a4ab(0x236)]=![],console[_0x54a4ab(0x266)](_0x54a4ab(0x191)+this[_0x54a4ab(0x268)]+_0x54a4ab(0x32d)+this[_0x54a4ab(0x2a4)]);}async[_0x1fe90e(0xb0)](_0x3438e2){const _0x2fa1b1=_0x1fe90e,_0x7921b4={'level':_0x3438e2,'score':this[_0x2fa1b1(0x132)]};console[_0x2fa1b1(0x266)]('Saving\x20score:\x20level='+_0x7921b4[_0x2fa1b1(0xb5)]+',\x20score='+_0x7921b4[_0x2fa1b1(0x22b)]);try{const _0x4fc6ea=await fetch(_0x2fa1b1(0x2bc),{'method':_0x2fa1b1(0x2d5),'headers':{'Content-Type':'application/json'},'body':JSON[_0x2fa1b1(0xbc)](_0x7921b4)});if(!_0x4fc6ea['ok'])throw new Error(_0x2fa1b1(0x29a)+_0x4fc6ea['status']);const _0x54dc37=await _0x4fc6ea['json']();console[_0x2fa1b1(0x266)]('Save\x20response:',_0x54dc37),log(_0x2fa1b1(0x2c6)+_0x54dc37[_0x2fa1b1(0xb5)]+_0x2fa1b1(0x200)+_0x54dc37[_0x2fa1b1(0x22b)]['toFixed'](0x2)),_0x54dc37['status']==='success'?log('Score\x20Saved:\x20Level\x20'+_0x54dc37[_0x2fa1b1(0xb5)]+_0x2fa1b1(0xf4)+_0x54dc37[_0x2fa1b1(0x22b)][_0x2fa1b1(0x2a1)](0x2)+_0x2fa1b1(0x1a6)+_0x54dc37[_0x2fa1b1(0x138)]):log(_0x2fa1b1(0x118)+_0x54dc37[_0x2fa1b1(0x280)]);}catch(_0x54cb4f){console[_0x2fa1b1(0x162)](_0x2fa1b1(0x2b2),_0x54cb4f),log(_0x2fa1b1(0x83)+_0x54cb4f['message']);}}[_0x1fe90e(0x1e8)](_0x186677,_0x5553d8,_0x411eb6,_0x1a06be){const _0x159b4f=_0x1fe90e,_0x5cba1d=_0x186677['style'][_0x159b4f(0x289)]||'',_0x43c33c=_0x5cba1d[_0x159b4f(0x29d)](_0x159b4f(0x315))?_0x5cba1d['match'](/scaleX\([^)]+\)/)[0x0]:'';_0x186677['style']['transition']=_0x159b4f(0x2f6)+_0x1a06be/0x2/0x3e8+'s\x20linear',_0x186677['style'][_0x159b4f(0x289)]=_0x159b4f(0x245)+_0x5553d8+'px)\x20'+_0x43c33c,_0x186677[_0x159b4f(0x31b)][_0x159b4f(0x233)](_0x411eb6),setTimeout(()=>{const _0xf4ea8a=_0x159b4f;_0x186677[_0xf4ea8a(0x163)][_0xf4ea8a(0x289)]=_0x43c33c,setTimeout(()=>{const _0x305604=_0xf4ea8a;_0x186677['classList'][_0x305604(0x267)](_0x411eb6);},_0x1a06be/0x2);},_0x1a06be/0x2);}[_0x1fe90e(0x11a)](_0x2518b3,_0x458fdc,_0x37ca83){const _0x387fad=_0x1fe90e,_0x5423af=_0x2518b3===this[_0x387fad(0x178)]?p1Image:p2Image,_0x2fd693=_0x2518b3===this[_0x387fad(0x178)]?0x1:-0x1,_0xa7c6b8=Math[_0x387fad(0x153)](0xa,0x2+_0x458fdc*0.4),_0x18e8ae=_0x2fd693*_0xa7c6b8,_0xe49513=_0x387fad(0x372)+_0x37ca83;this[_0x387fad(0x1e8)](_0x5423af,_0x18e8ae,_0xe49513,0xc8);}['animatePowerup'](_0x3c202e){const _0xfb239e=_0x1fe90e,_0x386ccb=_0x3c202e===this[_0xfb239e(0x178)]?p1Image:p2Image;this[_0xfb239e(0x1e8)](_0x386ccb,0x0,_0xfb239e(0xc2),0xc8);}[_0x1fe90e(0x1fe)](_0x4a77a6,_0x47ca7c){const _0x459651=_0x1fe90e,_0x527218=_0x4a77a6===this['player1']?p1Image:p2Image,_0x27f4a4=_0x4a77a6===this['player1']?-0x1:0x1,_0x1548f1=Math[_0x459651(0x153)](0xa,0x2+_0x47ca7c*0.4),_0x3b5a80=_0x27f4a4*_0x1548f1;this['applyAnimation'](_0x527218,_0x3b5a80,'glow-recoil',0xc8);}}function randomChoice(_0x2053f4){const _0x5e9f82=_0x1fe90e;return _0x2053f4[Math['floor'](Math['random']()*_0x2053f4[_0x5e9f82(0x10a)])];}function log(_0x21c6c2){const _0x2aa1b7=_0x1fe90e,_0x564099=document['getElementById']('battle-log'),_0x2123fe=document[_0x2aa1b7(0x2db)]('li');_0x2123fe[_0x2aa1b7(0x26c)]=_0x21c6c2,_0x564099[_0x2aa1b7(0xa7)](_0x2123fe,_0x564099[_0x2aa1b7(0x98)]),_0x564099[_0x2aa1b7(0x239)][_0x2aa1b7(0x10a)]>0x32&&_0x564099[_0x2aa1b7(0xd2)](_0x564099[_0x2aa1b7(0x27a)]),_0x564099[_0x2aa1b7(0x28a)]=0x0;}const turnIndicator=document[_0x1fe90e(0x86)](_0x1fe90e(0x216)),p1Name=document[_0x1fe90e(0x86)](_0x1fe90e(0x2e6)),p1Image=document[_0x1fe90e(0x86)](_0x1fe90e(0x282)),p1Health=document[_0x1fe90e(0x86)](_0x1fe90e(0x2a8)),p1Hp=document[_0x1fe90e(0x86)](_0x1fe90e(0x17c)),p1Strength=document['getElementById'](_0x1fe90e(0x159)),p1Speed=document[_0x1fe90e(0x86)](_0x1fe90e(0x14a)),p1Tactics=document[_0x1fe90e(0x86)]('p1-tactics'),p1Size=document['getElementById'](_0x1fe90e(0x20b)),p1Powerup=document[_0x1fe90e(0x86)](_0x1fe90e(0x183)),p1Type=document[_0x1fe90e(0x86)]('p1-type'),p2Name=document[_0x1fe90e(0x86)](_0x1fe90e(0x113)),p2Image=document[_0x1fe90e(0x86)](_0x1fe90e(0x31f)),p2Health=document[_0x1fe90e(0x86)]('p2-health'),p2Hp=document['getElementById']('p2-hp'),p2Strength=document[_0x1fe90e(0x86)](_0x1fe90e(0x327)),p2Speed=document[_0x1fe90e(0x86)]('p2-speed'),p2Tactics=document['getElementById'](_0x1fe90e(0x1ef)),p2Size=document[_0x1fe90e(0x86)](_0x1fe90e(0xa6)),p2Powerup=document[_0x1fe90e(0x86)](_0x1fe90e(0x169)),p2Type=document[_0x1fe90e(0x86)](_0x1fe90e(0x1d0)),battleLog=document['getElementById']('battle-log'),gameOver=document['getElementById']('game-over'),assetCache={};async function getAssets(_0x366b1a){const _0x298355=_0x1fe90e;if(assetCache[_0x366b1a])return console[_0x298355(0x266)]('getAssets:\x20Cache\x20hit\x20for\x20'+_0x366b1a),assetCache[_0x366b1a];console[_0x298355(0x1bc)](_0x298355(0x33e)+_0x366b1a);let _0x3261a1=[];try{console[_0x298355(0x266)](_0x298355(0xcf));const _0x5bb5db=await Promise[_0x298355(0x2c1)]([fetch(_0x298355(0x299),{'method':_0x298355(0x2d5),'headers':{'Content-Type':_0x298355(0x297)},'body':JSON[_0x298355(0xbc)]({'theme':_0x298355(0x11e)})}),new Promise((_0x2e5dc3,_0x59d7f6)=>setTimeout(()=>_0x59d7f6(new Error(_0x298355(0xca))),0x1388))]);if(!_0x5bb5db['ok'])throw new Error(_0x298355(0x1ff)+_0x5bb5db[_0x298355(0x2fa)]);_0x3261a1=await _0x5bb5db['json'](),!Array[_0x298355(0x333)](_0x3261a1)&&(_0x3261a1=[_0x3261a1]),_0x3261a1=_0x3261a1[_0x298355(0x1c4)]((_0x44fb8e,_0xbe3310)=>({..._0x44fb8e,'theme':_0x298355(0x11e),'name':_0x44fb8e[_0x298355(0x1ad)]||_0x298355(0x318)+_0xbe3310,'strength':_0x44fb8e[_0x298355(0x1c3)]||0x4,'speed':_0x44fb8e[_0x298355(0x228)]||0x4,'tactics':_0x44fb8e[_0x298355(0xd5)]||0x4,'size':_0x44fb8e[_0x298355(0x287)]||_0x298355(0x190),'type':_0x44fb8e['type']||_0x298355(0x213),'powerup':_0x44fb8e[_0x298355(0x36a)]||_0x298355(0x84)}));}catch(_0x459dbb){console[_0x298355(0x162)](_0x298355(0x140),_0x459dbb),_0x3261a1=[{'name':'Craig','strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x298355(0x190),'type':_0x298355(0x213),'powerup':_0x298355(0x84),'theme':'monstrocity'},{'name':_0x298355(0x2ad),'strength':0x3,'speed':0x5,'tactics':0x3,'size':'Small','type':_0x298355(0x213),'powerup':_0x298355(0x124),'theme':_0x298355(0x11e)}];}if(_0x366b1a===_0x298355(0x11e))return assetCache[_0x366b1a]=_0x3261a1,console['timeEnd']('getAssets_'+_0x366b1a),_0x3261a1;const _0x5bf09c=themes[_0x298355(0x172)](_0x41f26b=>_0x41f26b[_0x298355(0x2a5)])[_0x298355(0x1f0)](_0x58b054=>_0x58b054[_0x298355(0x1c9)]===_0x366b1a);if(!_0x5bf09c)return console['warn'](_0x298355(0x27f)+_0x366b1a),assetCache[_0x366b1a]=_0x3261a1,console[_0x298355(0x119)](_0x298355(0x33e)+_0x366b1a),_0x3261a1;const _0x56645f=_0x5bf09c[_0x298355(0x17d)]?_0x5bf09c[_0x298355(0x17d)][_0x298355(0x303)](',')['filter'](_0x4378af=>_0x4378af[_0x298355(0xc0)]()):[];if(!_0x56645f['length'])return assetCache[_0x366b1a]=_0x3261a1,console[_0x298355(0x119)]('getAssets_'+_0x366b1a),_0x3261a1;let _0xedabf9=[];try{const _0x381955=_0x56645f[_0x298355(0x1c4)]((_0x4378b8,_0x30a327)=>({'policyId':_0x4378b8,'orientation':_0x5bf09c[_0x298355(0x264)]?.['split'](',')[_0x30a327]||_0x298355(0x2d3),'ipfsPrefix':_0x5bf09c[_0x298355(0x30f)]?.[_0x298355(0x303)](',')[_0x30a327]||_0x298355(0x2ea)})),_0x4f9036=await Promise[_0x298355(0x2c1)]([fetch(_0x298355(0x322),{'method':_0x298355(0x2d5),'headers':{'Content-Type':_0x298355(0x297)},'body':JSON[_0x298355(0xbc)]({'policyIds':_0x381955[_0x298355(0x1c4)](_0x3b0f1=>_0x3b0f1['policyId']),'theme':_0x366b1a})}),new Promise((_0x4988fc,_0x27e607)=>setTimeout(()=>_0x27e607(new Error(_0x298355(0x182))),0x2710))]);if(!_0x4f9036['ok'])throw new Error(_0x298355(0x2d6)+_0x4f9036[_0x298355(0x2fa)]);const _0x2e72f4=await _0x4f9036[_0x298355(0x1e5)]();_0xedabf9=Array[_0x298355(0x333)](_0x2e72f4)?_0x2e72f4:[_0x2e72f4],_0xedabf9=_0xedabf9[_0x298355(0x336)](_0x560ba8=>_0x560ba8&&_0x560ba8['name']&&_0x560ba8['ipfs'])[_0x298355(0x1c4)]((_0x19244f,_0xa953ed)=>({..._0x19244f,'theme':_0x366b1a,'name':_0x19244f[_0x298355(0x1ad)]||_0x298355(0x28c)+_0xa953ed,'strength':_0x19244f[_0x298355(0x1c3)]||0x4,'speed':_0x19244f[_0x298355(0x228)]||0x4,'tactics':_0x19244f[_0x298355(0xd5)]||0x4,'size':_0x19244f['size']||_0x298355(0x190),'type':_0x19244f[_0x298355(0x103)]||_0x298355(0x213),'powerup':_0x19244f['powerup']||'Regenerate','policyId':_0x19244f[_0x298355(0x10f)]||_0x381955[0x0]['policyId'],'ipfs':_0x19244f[_0x298355(0x36d)]||''}));}catch(_0x29a4bc){console[_0x298355(0x162)](_0x298355(0xa9)+_0x366b1a+':',_0x29a4bc);}const _0x4847db=[..._0x3261a1,..._0xedabf9];return assetCache[_0x366b1a]=_0x4847db,console['timeEnd'](_0x298355(0x33e)+_0x366b1a),_0x4847db;}document[_0x1fe90e(0x7e)](_0x1fe90e(0x24c),function(){var _0x3bc4a2=function(){const _0x1a54f5=_0x30ad;var _0x5b3287=localStorage['getItem'](_0x1a54f5(0x12a))||_0x1a54f5(0x11e);getAssets(_0x5b3287)['then'](function(_0x5c6a90){const _0x44d055=_0x1a54f5;console[_0x44d055(0x266)]('Main:\x20Player\x20characters\x20loaded:',_0x5c6a90);var _0x2acc6d=new MonstrocityMatch3(_0x5c6a90,_0x5b3287);console[_0x44d055(0x266)](_0x44d055(0x34d)),_0x2acc6d[_0x44d055(0x2e4)]()[_0x44d055(0x2d1)](function(){const _0x2e3866=_0x44d055;console['log'](_0x2e3866(0x137)),document['querySelector']('.game-logo')[_0x2e3866(0x243)]=_0x2acc6d[_0x2e3866(0x334)]+'logo.png';});})['catch'](function(_0x3b79a9){const _0x26c595=_0x1a54f5;console[_0x26c595(0x162)](_0x26c595(0x2f2),_0x3b79a9);});};_0x3bc4a2();});
  </script>
</body>
</html>