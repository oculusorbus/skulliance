<?php
session_start();

// Initialize default variables
$member = false;
$elite = false;
$innercircle = false;

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
	}

    .game-logo {
      max-width: 300px;
      height: auto;
      margin: 0 auto 10px;
      display: block;
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
	    background-color: #003044;
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

    button:hover { background-color: #54d4ff; }

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
	
	#theme-select-button {
	  padding: 10px 20px;
	  background-color: #49BBE3;
	  border: none;
	  border-radius: 5px;
	  cursor: pointer;
	  font-weight: bold;
	  font-size: 16px;
	  margin: 10px 0;
	  min-width: 150px;
	  color: black;
	}

	#theme-select-button:hover {
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
</head>
<body>
  <div class="game-container">
    <div id="game-over-container">
      <div id="game-over"></div>
      <div id="game-over-buttons">
        <button id="try-again"></button>
		<form action="leaderboards.php" method="post"><input type="hidden" name="filterbystreak" id="filterbystreak" value="monthly-monstrocity"><input id="leaderboard" type="submit" value="LEADERBOARD"></form>
      </div>
    </div>
    <img src="https://www.skulliance.io/staking/images/monstrocity/logo.png" alt="Monstrocity Logo" class="game-logo">
    <button id="restart">Restart Level</button>
    <button id="change-character" style="display: none;">Switch Character</button>
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
	  <h2>Select Character</h2>
      <div id="character-options"></div>
    </div>
	<!-- New Theme Select Modal -->
	<!-- Theme Select Template (initially empty, built by JS) -->
	<div id="theme-select-container" style="display: none;"></div>
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
	  
const _0xe5e5=_0x3390;function _0x3390(_0x51977a,_0x3b213a){const _0xdf88da=_0xdf88();return _0x3390=function(_0x33908b,_0x12f5b5){_0x33908b=_0x33908b-0x16c;let _0x5ba781=_0xdf88da[_0x33908b];return _0x5ba781;},_0x3390(_0x51977a,_0x3b213a);}(function(_0x1595bb,_0x426dfc){const _0x2ffc99=_0x3390,_0x40d9f9=_0x1595bb();while(!![]){try{const _0x3f3301=-parseInt(_0x2ffc99(0x367))/0x1+parseInt(_0x2ffc99(0x18e))/0x2*(parseInt(_0x2ffc99(0x2e2))/0x3)+-parseInt(_0x2ffc99(0x38e))/0x4+parseInt(_0x2ffc99(0x1c6))/0x5*(-parseInt(_0x2ffc99(0x24f))/0x6)+-parseInt(_0x2ffc99(0x270))/0x7*(parseInt(_0x2ffc99(0x2d7))/0x8)+-parseInt(_0x2ffc99(0x256))/0x9*(parseInt(_0x2ffc99(0x1f9))/0xa)+parseInt(_0x2ffc99(0x241))/0xb;if(_0x3f3301===_0x426dfc)break;else _0x40d9f9['push'](_0x40d9f9['shift']());}catch(_0xc85f56){_0x40d9f9['push'](_0x40d9f9['shift']());}}}(_0xdf88,0xa03ed));function showThemeSelect(_0x5d8512){const _0x180e77=_0x3390;console['time'](_0x180e77(0x21e));let _0x2f8297=document[_0x180e77(0x192)]('theme-select-container');const _0x2b5a60=document[_0x180e77(0x192)](_0x180e77(0x38b));_0x2f8297[_0x180e77(0x2be)]=_0x180e77(0x2ba);const _0x5803a6=document['getElementById'](_0x180e77(0x21a));_0x2f8297['style']['display']=_0x180e77(0x20c),_0x2b5a60[_0x180e77(0x28c)][_0x180e77(0x1a2)]=_0x180e77(0x24d),themes[_0x180e77(0x25f)](_0x39af8a=>{const _0x498e42=_0x180e77,_0x525a25=document['createElement'](_0x498e42(0x20b));_0x525a25[_0x498e42(0x2f9)]=_0x498e42(0x32b);const _0x2a4f31=document[_0x498e42(0x232)]('h3');_0x2a4f31[_0x498e42(0x31d)]=_0x39af8a[_0x498e42(0x231)],_0x525a25[_0x498e42(0x292)](_0x2a4f31),_0x39af8a[_0x498e42(0x1f4)][_0x498e42(0x25f)](_0x26e68b=>{const _0x37fd90=_0x498e42,_0x3420ce=document[_0x37fd90(0x232)](_0x37fd90(0x20b));_0x3420ce['className']=_0x37fd90(0x1e0);if(_0x26e68b[_0x37fd90(0x2a5)]){const _0xcd28be=_0x37fd90(0x378)+_0x26e68b['value']+_0x37fd90(0x2ca);_0x3420ce['style'][_0x37fd90(0x329)]='url('+_0xcd28be+')';}const _0x2f77ff=_0x37fd90(0x378)+_0x26e68b['value']+_0x37fd90(0x2a2);_0x3420ce[_0x37fd90(0x2be)]=_0x37fd90(0x2f7)+_0x2f77ff+_0x37fd90(0x22b)+_0x26e68b[_0x37fd90(0x2ae)]+_0x37fd90(0x327)+_0x26e68b[_0x37fd90(0x363)]+_0x37fd90(0x17f)+_0x26e68b['title']+_0x37fd90(0x27c),_0x3420ce[_0x37fd90(0x1f8)](_0x37fd90(0x30c),()=>{const _0x138722=_0x37fd90,_0x1392e3=document[_0x138722(0x192)](_0x138722(0x261));_0x1392e3&&(_0x1392e3[_0x138722(0x2be)]='<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>Loading\x20new\x20characters...</p>'),_0x2f8297[_0x138722(0x2be)]='',_0x2f8297['style']['display']=_0x138722(0x24d),_0x2b5a60[_0x138722(0x28c)]['display']=_0x138722(0x20c),_0x5d8512['updateTheme'](_0x26e68b['value']);}),_0x525a25[_0x37fd90(0x292)](_0x3420ce);}),_0x5803a6[_0x498e42(0x292)](_0x525a25);}),console['timeEnd'](_0x180e77(0x21e));}const opponentsConfig=[{'name':_0xe5e5(0x255),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0xe5e5(0x19d),'type':_0xe5e5(0x372),'powerup':_0xe5e5(0x357),'theme':_0xe5e5(0x35b)},{'name':'Merdock','strength':0x1,'speed':0x1,'tactics':0x1,'size':_0xe5e5(0x271),'type':'Base','powerup':_0xe5e5(0x357),'theme':_0xe5e5(0x35b)},{'name':_0xe5e5(0x26d),'strength':0x2,'speed':0x2,'tactics':0x2,'size':'Small','type':_0xe5e5(0x372),'powerup':_0xe5e5(0x357),'theme':_0xe5e5(0x35b)},{'name':_0xe5e5(0x257),'strength':0x2,'speed':0x2,'tactics':0x2,'size':'Medium','type':'Base','powerup':_0xe5e5(0x357),'theme':_0xe5e5(0x35b)},{'name':_0xe5e5(0x1ec),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0xe5e5(0x19d),'type':_0xe5e5(0x372),'powerup':_0xe5e5(0x2d4),'theme':_0xe5e5(0x35b)},{'name':'Koipon','strength':0x3,'speed':0x3,'tactics':0x3,'size':_0xe5e5(0x19d),'type':'Base','powerup':_0xe5e5(0x2d4),'theme':_0xe5e5(0x35b)},{'name':_0xe5e5(0x336),'strength':0x4,'speed':0x4,'tactics':0x4,'size':'Small','type':_0xe5e5(0x372),'powerup':_0xe5e5(0x2d4),'theme':_0xe5e5(0x35b)},{'name':_0xe5e5(0x1ee),'strength':0x4,'speed':0x4,'tactics':0x4,'size':'Medium','type':_0xe5e5(0x372),'powerup':_0xe5e5(0x2d4),'theme':_0xe5e5(0x35b)},{'name':_0xe5e5(0x18b),'strength':0x5,'speed':0x5,'tactics':0x5,'size':'Medium','type':'Base','powerup':_0xe5e5(0x2bb),'theme':'monstrocity'},{'name':_0xe5e5(0x1b4),'strength':0x5,'speed':0x5,'tactics':0x5,'size':'Medium','type':_0xe5e5(0x372),'powerup':'Boost\x20Attack','theme':_0xe5e5(0x35b)},{'name':_0xe5e5(0x395),'strength':0x6,'speed':0x6,'tactics':0x6,'size':'Small','type':_0xe5e5(0x372),'powerup':'Heal','theme':'monstrocity'},{'name':_0xe5e5(0x2b6),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0xe5e5(0x271),'type':_0xe5e5(0x372),'powerup':'Heal','theme':_0xe5e5(0x35b)},{'name':'Ouchie','strength':0x7,'speed':0x7,'tactics':0x7,'size':_0xe5e5(0x19d),'type':_0xe5e5(0x372),'powerup':_0xe5e5(0x17c),'theme':'monstrocity'},{'name':_0xe5e5(0x215),'strength':0x8,'speed':0x7,'tactics':0x7,'size':'Medium','type':_0xe5e5(0x372),'powerup':'Heal','theme':_0xe5e5(0x35b)},{'name':_0xe5e5(0x255),'strength':0x1,'speed':0x1,'tactics':0x1,'size':_0xe5e5(0x19d),'type':_0xe5e5(0x29a),'powerup':_0xe5e5(0x357),'theme':'monstrocity'},{'name':_0xe5e5(0x1bd),'strength':0x1,'speed':0x1,'tactics':0x1,'size':'Large','type':_0xe5e5(0x29a),'powerup':_0xe5e5(0x357),'theme':'monstrocity'},{'name':_0xe5e5(0x26d),'strength':0x2,'speed':0x2,'tactics':0x2,'size':'Small','type':_0xe5e5(0x29a),'powerup':'Minor\x20Regen','theme':_0xe5e5(0x35b)},{'name':_0xe5e5(0x257),'strength':0x2,'speed':0x2,'tactics':0x2,'size':_0xe5e5(0x19d),'type':_0xe5e5(0x29a),'powerup':_0xe5e5(0x3a1),'theme':_0xe5e5(0x35b)},{'name':_0xe5e5(0x1ec),'strength':0x3,'speed':0x3,'tactics':0x3,'size':_0xe5e5(0x19d),'type':_0xe5e5(0x29a),'powerup':_0xe5e5(0x2d4),'theme':_0xe5e5(0x35b)},{'name':'Koipon','strength':0x3,'speed':0x3,'tactics':0x3,'size':_0xe5e5(0x19d),'type':_0xe5e5(0x29a),'powerup':'Regenerate','theme':'monstrocity'},{'name':_0xe5e5(0x336),'strength':0x4,'speed':0x4,'tactics':0x4,'size':'Small','type':_0xe5e5(0x29a),'powerup':_0xe5e5(0x2d4),'theme':'monstrocity'},{'name':_0xe5e5(0x1ee),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0xe5e5(0x19d),'type':_0xe5e5(0x29a),'powerup':_0xe5e5(0x2d4),'theme':_0xe5e5(0x35b)},{'name':_0xe5e5(0x18b),'strength':0x5,'speed':0x5,'tactics':0x5,'size':_0xe5e5(0x19d),'type':'Leader','powerup':'Boost\x20Attack','theme':_0xe5e5(0x35b)},{'name':_0xe5e5(0x1b4),'strength':0x5,'speed':0x5,'tactics':0x5,'size':'Medium','type':_0xe5e5(0x29a),'powerup':_0xe5e5(0x2bb),'theme':_0xe5e5(0x35b)},{'name':_0xe5e5(0x395),'strength':0x6,'speed':0x6,'tactics':0x6,'size':_0xe5e5(0x320),'type':'Leader','powerup':_0xe5e5(0x17c),'theme':_0xe5e5(0x35b)},{'name':'Katastrophy','strength':0x7,'speed':0x7,'tactics':0x7,'size':_0xe5e5(0x271),'type':_0xe5e5(0x29a),'powerup':_0xe5e5(0x17c),'theme':'monstrocity'},{'name':_0xe5e5(0x28a),'strength':0x7,'speed':0x7,'tactics':0x7,'size':_0xe5e5(0x19d),'type':_0xe5e5(0x29a),'powerup':_0xe5e5(0x17c),'theme':'monstrocity'},{'name':'Drake','strength':0x8,'speed':0x7,'tactics':0x7,'size':_0xe5e5(0x19d),'type':_0xe5e5(0x29a),'powerup':'Heal','theme':_0xe5e5(0x35b)}],characterDirections={'Billandar\x20and\x20Ted':_0xe5e5(0x34e),'Craig':_0xe5e5(0x34e),'Dankle':_0xe5e5(0x34e),'Drake':_0xe5e5(0x2fb),'Goblin\x20Ganger':_0xe5e5(0x34e),'Jarhead':'Right','Katastrophy':_0xe5e5(0x2fb),'Koipon':_0xe5e5(0x34e),'Mandiblus':_0xe5e5(0x34e),'Merdock':_0xe5e5(0x34e),'Ouchie':_0xe5e5(0x34e),'Slime\x20Mind':'Right','Spydrax':_0xe5e5(0x2fb),'Texby':_0xe5e5(0x34e)};class MonstrocityMatch3{constructor(_0x46a299,_0x38e794){const _0x11bc86=_0xe5e5;this[_0x11bc86(0x382)]=_0x11bc86(0x277)in window||navigator[_0x11bc86(0x252)]>0x0||navigator[_0x11bc86(0x185)]>0x0,this[_0x11bc86(0x216)]=0x5,this[_0x11bc86(0x39b)]=0x5,this['board']=[],this[_0x11bc86(0x22a)]=null,this[_0x11bc86(0x2ee)]=![],this[_0x11bc86(0x2e5)]=null,this[_0x11bc86(0x250)]=null,this[_0x11bc86(0x200)]=null,this[_0x11bc86(0x2b4)]=_0x11bc86(0x2e0),this[_0x11bc86(0x297)]=![],this['targetTile']=null,this[_0x11bc86(0x16e)]=null,this[_0x11bc86(0x17e)]=0x0,this['offsetY']=0x0,this[_0x11bc86(0x227)]=0x1,this[_0x11bc86(0x1b6)]=_0x46a299,this[_0x11bc86(0x236)]=[],this[_0x11bc86(0x321)]=![],this[_0x11bc86(0x2ef)]=[_0x11bc86(0x219),_0x11bc86(0x2af),_0x11bc86(0x334),_0x11bc86(0x239),_0x11bc86(0x2d1)],this['roundStats']=[],this[_0x11bc86(0x35f)]=0x0;const _0x4b2f1c=themes[_0x11bc86(0x342)](_0x24cd9b=>_0x24cd9b['items'])[_0x11bc86(0x1b1)](_0x35ee26=>_0x35ee26[_0x11bc86(0x23e)]),_0x1dca39=localStorage[_0x11bc86(0x380)](_0x11bc86(0x235));this[_0x11bc86(0x24c)]=_0x1dca39&&_0x4b2f1c[_0x11bc86(0x2e9)](_0x1dca39)?_0x1dca39:_0x38e794&&_0x4b2f1c[_0x11bc86(0x2e9)](_0x38e794)?_0x38e794:_0x11bc86(0x35b),console['log']('constructor:\x20initialTheme='+_0x38e794+_0x11bc86(0x295)+_0x1dca39+',\x20selected\x20theme='+this[_0x11bc86(0x24c)]),this[_0x11bc86(0x1e1)]=_0x11bc86(0x378)+this[_0x11bc86(0x24c)]+'/',this[_0x11bc86(0x306)]={'match':new Audio(_0x11bc86(0x207)),'cascade':new Audio(_0x11bc86(0x207)),'badMove':new Audio(_0x11bc86(0x1ac)),'gameOver':new Audio(_0x11bc86(0x1f2)),'reset':new Audio(_0x11bc86(0x1a7)),'loss':new Audio('https://www.skulliance.io/staking/sounds/skullcoinlose.ogg'),'win':new Audio('https://www.skulliance.io/staking/sounds/voice_levelcomplete.ogg'),'finalWin':new Audio(_0x11bc86(0x2e1)),'powerGem':new Audio(_0x11bc86(0x311)),'hyperCube':new Audio(_0x11bc86(0x2b1)),'multiMatch':new Audio(_0x11bc86(0x351))},this['updateTileSizeWithGap'](),this[_0x11bc86(0x2ea)]();}async[_0xe5e5(0x259)](){const _0x25e426=_0xe5e5;console[_0x25e426(0x32f)]('init:\x20Starting\x20async\x20initialization'),this[_0x25e426(0x236)]=this[_0x25e426(0x1b6)]['map'](_0x9babcd=>this[_0x25e426(0x375)](_0x9babcd)),await this[_0x25e426(0x23a)](!![]);const _0x103b5a=await this[_0x25e426(0x247)](),{loadedLevel:_0x1afeb8,loadedScore:_0x1889de,hasProgress:_0x25aac0}=_0x103b5a;if(_0x25aac0){console[_0x25e426(0x32f)](_0x25e426(0x36e)+_0x1afeb8+_0x25e426(0x3a0)+_0x1889de);const _0x52094f=await this[_0x25e426(0x324)](_0x1afeb8,_0x1889de);_0x52094f?(this[_0x25e426(0x227)]=_0x1afeb8,this[_0x25e426(0x35f)]=_0x1889de,log(_0x25e426(0x174)+this['currentLevel']+_0x25e426(0x213)+this[_0x25e426(0x35f)])):(this['currentLevel']=0x1,this[_0x25e426(0x35f)]=0x0,await this[_0x25e426(0x298)](),log(_0x25e426(0x349)));}else this[_0x25e426(0x227)]=0x1,this[_0x25e426(0x35f)]=0x0,log(_0x25e426(0x266));console['log'](_0x25e426(0x1e2));}['setBackground'](){const _0x46072d=_0xe5e5;console[_0x46072d(0x32f)](_0x46072d(0x1e8)+this['theme']);const _0x445dbc=themes[_0x46072d(0x342)](_0x1c0f77=>_0x1c0f77[_0x46072d(0x1f4)])[_0x46072d(0x1c4)](_0x32664f=>_0x32664f[_0x46072d(0x23e)]===this[_0x46072d(0x24c)]);console[_0x46072d(0x32f)](_0x46072d(0x1c9),_0x445dbc);const _0x46ad0d=_0x46072d(0x378)+this[_0x46072d(0x24c)]+_0x46072d(0x2ca);console[_0x46072d(0x32f)]('setBackground:\x20Setting\x20background\x20to\x20'+_0x46ad0d),_0x445dbc&&_0x445dbc['background']?(document[_0x46072d(0x208)][_0x46072d(0x28c)]['backgroundImage']=_0x46072d(0x19b)+_0x46ad0d+')',document[_0x46072d(0x208)][_0x46072d(0x28c)]['backgroundSize']='cover',document[_0x46072d(0x208)][_0x46072d(0x28c)][_0x46072d(0x290)]=_0x46072d(0x2ed)):document[_0x46072d(0x208)][_0x46072d(0x28c)][_0x46072d(0x329)]=_0x46072d(0x24d);}['updateTheme'](_0x19f665){const _0xb02048=_0xe5e5;if(updatePending){console['log'](_0xb02048(0x345));return;}updatePending=!![],console['time'](_0xb02048(0x37c)+_0x19f665);const _0x116195=this;this[_0xb02048(0x24c)]=_0x19f665,this['baseImagePath']=_0xb02048(0x378)+this[_0xb02048(0x24c)]+'/',localStorage[_0xb02048(0x184)](_0xb02048(0x235),this['theme']),this[_0xb02048(0x1e7)](),document['querySelector'](_0xb02048(0x237))[_0xb02048(0x248)]=this[_0xb02048(0x1e1)]+_0xb02048(0x323);const _0x38719c=document[_0xb02048(0x192)]('character-options');_0x38719c&&(_0x38719c[_0xb02048(0x2be)]=_0xb02048(0x1ef)),getAssets(this[_0xb02048(0x24c)])[_0xb02048(0x31e)](function(_0x4f89ed){const _0x58e482=_0xb02048;console[_0x58e482(0x203)](_0x58e482(0x2e6)+_0x19f665),_0x116195[_0x58e482(0x1b6)]=_0x4f89ed,_0x116195[_0x58e482(0x236)]=[],_0x4f89ed[_0x58e482(0x25f)](_0x3c1348=>{const _0x248b5d=_0x58e482,_0x35abef=_0x116195[_0x248b5d(0x375)](_0x3c1348);if(_0x35abef[_0x248b5d(0x36f)]===_0x248b5d(0x3a8)){const _0xbc0f56=new Image();_0xbc0f56[_0x248b5d(0x248)]=_0x35abef[_0x248b5d(0x2ce)],_0xbc0f56[_0x248b5d(0x39e)]=()=>console[_0x248b5d(0x32f)]('Preloaded:\x20'+_0x35abef[_0x248b5d(0x2ce)]),_0xbc0f56[_0x248b5d(0x3aa)]=()=>console[_0x248b5d(0x32f)](_0x248b5d(0x1b0)+_0x35abef['imageUrl']);}_0x116195[_0x248b5d(0x236)]['push'](_0x35abef);});if(_0x116195[_0x58e482(0x250)]){const _0x25f1f7=_0x116195['playerCharactersConfig'][_0x58e482(0x1c4)](_0xfe4a31=>_0xfe4a31[_0x58e482(0x1e6)]===_0x116195[_0x58e482(0x250)][_0x58e482(0x1e6)])||_0x116195[_0x58e482(0x1b6)][0x0];_0x116195['player1']=_0x116195['createCharacter'](_0x25f1f7),_0x116195[_0x58e482(0x2a0)]();}_0x116195[_0x58e482(0x200)]&&(_0x116195[_0x58e482(0x200)]=_0x116195[_0x58e482(0x375)](opponentsConfig[_0x116195[_0x58e482(0x227)]-0x1]),_0x116195[_0x58e482(0x181)]());if(_0x116195[_0x58e482(0x250)]&&_0x116195[_0x58e482(0x2b4)]!=='initializing'){const _0x353186=document[_0x58e482(0x21d)]('.tile');_0x353186[_0x58e482(0x25f)](_0x453859=>{const _0x492358=_0x58e482;_0x453859[_0x492358(0x1f5)](_0x492358(0x1aa),_0x116195['handleMouseDown']),_0x453859['removeEventListener'](_0x492358(0x30e),_0x116195['handleTouchStart']);}),_0x116195['renderBoard'](),console[_0x58e482(0x32f)](_0x58e482(0x304));}else console[_0x58e482(0x32f)](_0x58e482(0x269));_0x116195['player1']&&(_0x116195[_0x58e482(0x297)]=![],_0x116195[_0x58e482(0x22a)]=null,_0x116195[_0x58e482(0x188)]=null,_0x116195['gameState']=_0x116195[_0x58e482(0x2e5)]===_0x116195['player1']?_0x58e482(0x16c):_0x58e482(0x37e));const _0x35ee84=document[_0x58e482(0x192)](_0x58e482(0x38b));_0x35ee84['style'][_0x58e482(0x1a2)]=_0x58e482(0x20c),_0x116195['showCharacterSelect'](_0x116195[_0x58e482(0x250)]===null),console[_0x58e482(0x191)](_0x58e482(0x2e6)+_0x19f665),console['timeEnd'](_0x58e482(0x37c)+_0x19f665),updatePending=![];})['catch'](function(_0x1b24c5){const _0x99f4e7=_0xb02048;console[_0x99f4e7(0x19c)](_0x99f4e7(0x198),_0x1b24c5),_0x116195[_0x99f4e7(0x1b6)]=[{'name':_0x99f4e7(0x255),'strength':0x4,'speed':0x4,'tactics':0x4,'size':'Medium','type':_0x99f4e7(0x372),'powerup':_0x99f4e7(0x2d4),'theme':_0x99f4e7(0x35b)},{'name':_0x99f4e7(0x18b),'strength':0x3,'speed':0x5,'tactics':0x3,'size':'Small','type':_0x99f4e7(0x372),'powerup':_0x99f4e7(0x17c),'theme':_0x99f4e7(0x35b)}],_0x116195['playerCharacters']=_0x116195[_0x99f4e7(0x1b6)][_0x99f4e7(0x1b1)](_0x54451a=>_0x116195[_0x99f4e7(0x375)](_0x54451a));const _0x4e0ce9=document[_0x99f4e7(0x192)](_0x99f4e7(0x38b));_0x4e0ce9[_0x99f4e7(0x28c)][_0x99f4e7(0x1a2)]='block',_0x116195[_0x99f4e7(0x23a)](_0x116195[_0x99f4e7(0x250)]===null),console[_0x99f4e7(0x191)]('updateTheme_'+_0x19f665),updatePending=![];});}async[_0xe5e5(0x274)](){const _0xb2406b=_0xe5e5,_0x3c75d8={'currentLevel':this[_0xb2406b(0x227)],'grandTotalScore':this[_0xb2406b(0x35f)]};console[_0xb2406b(0x32f)]('Sending\x20saveProgress\x20request\x20with\x20data:',_0x3c75d8);try{const _0x28a8da=await fetch('ajax/save-monstrocity-progress.php',{'method':_0xb2406b(0x1a5),'headers':{'Content-Type':_0xb2406b(0x2c0)},'body':JSON['stringify'](_0x3c75d8)});console[_0xb2406b(0x32f)]('Response\x20status:',_0x28a8da[_0xb2406b(0x2c7)]);const _0x47de10=await _0x28a8da['text']();console[_0xb2406b(0x32f)]('Raw\x20response\x20text:',_0x47de10);if(!_0x28a8da['ok'])throw new Error('HTTP\x20error!\x20Status:\x20'+_0x28a8da['status']);const _0x376b29=JSON['parse'](_0x47de10);console[_0xb2406b(0x32f)](_0xb2406b(0x1ae),_0x376b29),_0x376b29[_0xb2406b(0x2c7)]==='success'?log(_0xb2406b(0x35d)+this[_0xb2406b(0x227)]):console[_0xb2406b(0x19c)](_0xb2406b(0x31a),_0x376b29[_0xb2406b(0x194)]);}catch(_0x3c82a){console['error'](_0xb2406b(0x2bf),_0x3c82a);}}async[_0xe5e5(0x247)](){const _0x1a5525=_0xe5e5;try{console['log'](_0x1a5525(0x2dc));const _0x4a1007=await fetch(_0x1a5525(0x2a3),{'method':_0x1a5525(0x19f),'headers':{'Content-Type':_0x1a5525(0x2c0)}});console[_0x1a5525(0x32f)](_0x1a5525(0x253),_0x4a1007[_0x1a5525(0x2c7)]);if(!_0x4a1007['ok'])throw new Error(_0x1a5525(0x2a6)+_0x4a1007['status']);const _0x414ed1=await _0x4a1007[_0x1a5525(0x19e)]();console[_0x1a5525(0x32f)]('Parsed\x20response:',_0x414ed1);if(_0x414ed1[_0x1a5525(0x2c7)]===_0x1a5525(0x332)&&_0x414ed1[_0x1a5525(0x262)]){const _0x308543=_0x414ed1[_0x1a5525(0x262)];return{'loadedLevel':_0x308543[_0x1a5525(0x227)]||0x1,'loadedScore':_0x308543[_0x1a5525(0x35f)]||0x0,'hasProgress':!![]};}else return console[_0x1a5525(0x32f)](_0x1a5525(0x325),_0x414ed1),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}catch(_0x11c396){return console[_0x1a5525(0x19c)](_0x1a5525(0x1a6),_0x11c396),{'loadedLevel':0x1,'loadedScore':0x0,'hasProgress':![]};}}async[_0xe5e5(0x298)](){const _0x434d59=_0xe5e5;try{const _0x2d22fd=await fetch(_0x434d59(0x212),{'method':_0x434d59(0x1a5),'headers':{'Content-Type':_0x434d59(0x2c0)}});if(!_0x2d22fd['ok'])throw new Error(_0x434d59(0x2a6)+_0x2d22fd[_0x434d59(0x2c7)]);const _0x30ab33=await _0x2d22fd['json']();_0x30ab33['status']==='success'&&(this[_0x434d59(0x227)]=0x1,this[_0x434d59(0x35f)]=0x0,log(_0x434d59(0x37d)));}catch(_0x137634){console['error'](_0x434d59(0x293),_0x137634);}}['updateTileSizeWithGap'](){const _0xe96b8e=_0xe5e5,_0x17b5dd=document[_0xe96b8e(0x192)](_0xe96b8e(0x1dd)),_0x54bb52=_0x17b5dd[_0xe96b8e(0x1af)]||0x12c;this[_0xe96b8e(0x173)]=(_0x54bb52-0.5*(this['width']-0x1))/this['width'];}[_0xe5e5(0x375)](_0x4c9575){const _0x4964f5=_0xe5e5;console['log'](_0x4964f5(0x2dd),_0x4c9575);var _0x51e9bb,_0x14ccc1,_0x313497,_0x54615e=_0x4964f5(0x34e),_0x4be1c7=![],_0x31283d=_0x4964f5(0x3a8);const _0x21c99a=themes[_0x4964f5(0x342)](_0x58ff90=>_0x58ff90[_0x4964f5(0x1f4)])[_0x4964f5(0x1c4)](_0x594fef=>_0x594fef['value']===this[_0x4964f5(0x24c)]),_0x5a97f2=_0x21c99a?.[_0x4964f5(0x34a)]||'png',_0x1b4b7f=[_0x4964f5(0x230),'mp4'],_0x18dfec='https://ipfs.io/ipfs/';if(_0x4c9575[_0x4964f5(0x1fe)]&&_0x4c9575['policyId']){_0x4be1c7=!![];var _0x4fb8d2={'orientation':_0x4964f5(0x2fb),'ipfsPrefix':_0x21c99a?.['ipfsPrefixes']||_0x18dfec};if(_0x21c99a&&_0x21c99a['policyIds']){var _0x49aa4b=_0x21c99a['policyIds'][_0x4964f5(0x217)](',')[_0x4964f5(0x25b)](_0x17099a=>_0x17099a[_0x4964f5(0x183)]()),_0x1c6aae=_0x21c99a[_0x4964f5(0x39a)]?_0x21c99a[_0x4964f5(0x39a)][_0x4964f5(0x217)](',')['filter'](_0x1dcf52=>_0x1dcf52['trim']()):[],_0xb1853b=_0x21c99a[_0x4964f5(0x17d)]?_0x21c99a[_0x4964f5(0x17d)][_0x4964f5(0x217)](',')[_0x4964f5(0x25b)](_0x1eab93=>_0x1eab93[_0x4964f5(0x183)]()):[_0x18dfec],_0x4b0886=_0x49aa4b[_0x4964f5(0x218)](_0x4c9575['policyId']);_0x4b0886!==-0x1&&(_0x4fb8d2={'orientation':_0x1c6aae[_0x4964f5(0x2ec)]===0x1?_0x1c6aae[0x0]:_0x1c6aae[_0x4b0886]||_0x4964f5(0x2fb),'ipfsPrefix':_0xb1853b['length']===0x1?_0xb1853b[0x0]:_0xb1853b[_0x4b0886]||_0x18dfec});}_0x4fb8d2['orientation']===_0x4964f5(0x3a5)?_0x54615e=Math['random']()<0.5?_0x4964f5(0x34e):'Right':_0x54615e=_0x4fb8d2[_0x4964f5(0x285)];_0x14ccc1=_0x4fb8d2['ipfsPrefix']+_0x4c9575[_0x4964f5(0x1fe)],_0x313497=_0x18dfec+_0x4c9575['ipfs'];const _0x2caf60=_0x14ccc1['split']('.')['pop']()['toLowerCase']();_0x1b4b7f[_0x4964f5(0x2e9)](_0x2caf60)&&(_0x31283d='video');}else{switch(_0x4c9575[_0x4964f5(0x296)]){case'Base':_0x51e9bb=_0x4964f5(0x287);break;case _0x4964f5(0x29a):_0x51e9bb=_0x4964f5(0x31b);break;case'Battle\x20Damaged':_0x51e9bb=_0x4964f5(0x1e5);break;default:_0x51e9bb=_0x4964f5(0x287);}_0x14ccc1=this[_0x4964f5(0x1e1)]+_0x51e9bb+'/'+_0x4c9575[_0x4964f5(0x1e6)]['toLowerCase']()[_0x4964f5(0x1cc)](/ /g,'-')+'.'+_0x5a97f2,_0x313497=_0x4964f5(0x25c),_0x54615e=characterDirections[_0x4c9575[_0x4964f5(0x1e6)]]||_0x4964f5(0x34e),_0x1b4b7f[_0x4964f5(0x2e9)](_0x5a97f2[_0x4964f5(0x30b)]())&&(_0x31283d=_0x4964f5(0x1b2));}var _0xaf4655;switch(_0x4c9575['type']){case _0x4964f5(0x29a):_0xaf4655=0x64;break;case _0x4964f5(0x3a2):_0xaf4655=0x46;break;case _0x4964f5(0x372):default:_0xaf4655=0x55;}var _0x168eaf=0x1,_0x4f6352=0x0;switch(_0x4c9575['size']){case _0x4964f5(0x271):_0x168eaf=1.2,_0x4f6352=_0x4c9575[_0x4964f5(0x317)]>0x1?-0x2:0x0;break;case _0x4964f5(0x320):_0x168eaf=0.8,_0x4f6352=_0x4c9575[_0x4964f5(0x317)]<0x6?0x2:0x7-_0x4c9575[_0x4964f5(0x317)];break;case'Medium':_0x168eaf=0x1,_0x4f6352=0x0;break;}var _0xf34375=Math[_0x4964f5(0x2d3)](_0xaf4655*_0x168eaf),_0x5e49cf=Math[_0x4964f5(0x361)](0x1,Math[_0x4964f5(0x36a)](0x7,_0x4c9575['tactics']+_0x4f6352));return{'name':_0x4c9575[_0x4964f5(0x1e6)],'type':_0x4c9575[_0x4964f5(0x296)],'strength':_0x4c9575[_0x4964f5(0x186)],'speed':_0x4c9575[_0x4964f5(0x35c)],'tactics':_0x5e49cf,'size':_0x4c9575[_0x4964f5(0x34b)],'powerup':_0x4c9575['powerup'],'health':_0xf34375,'maxHealth':_0xf34375,'boostActive':![],'boostValue':0x0,'lastStandActive':![],'imageUrl':_0x14ccc1,'fallbackUrl':_0x313497,'orientation':_0x54615e,'isNFT':_0x4be1c7,'mediaType':_0x31283d};}[_0xe5e5(0x2e8)](_0x5f5c0b,_0x23403b,_0x4f9a35=![]){const _0x33eae1=_0xe5e5;_0x5f5c0b[_0x33eae1(0x285)]===_0x33eae1(0x34e)?(_0x5f5c0b[_0x33eae1(0x285)]='Right',_0x23403b[_0x33eae1(0x28c)]['transform']=_0x4f9a35?'scaleX(-1)':_0x33eae1(0x24d)):(_0x5f5c0b[_0x33eae1(0x285)]='Left',_0x23403b[_0x33eae1(0x28c)]['transform']=_0x4f9a35?_0x33eae1(0x24d):_0x33eae1(0x2c6)),log(_0x5f5c0b[_0x33eae1(0x1e6)]+_0x33eae1(0x171)+_0x5f5c0b[_0x33eae1(0x285)]+'!');}['showCharacterSelect'](_0x3e1dd1){const _0x1e3f60=_0xe5e5;console[_0x1e3f60(0x203)]('showCharacterSelect');const _0x5397df=document[_0x1e3f60(0x192)](_0x1e3f60(0x38b)),_0x5c7439=document['getElementById'](_0x1e3f60(0x261));_0x5c7439[_0x1e3f60(0x2be)]='',_0x5397df['style']['display']=_0x1e3f60(0x20c);if(!this[_0x1e3f60(0x236)]||this['playerCharacters']['length']===0x0){console[_0x1e3f60(0x354)](_0x1e3f60(0x242)),_0x5c7439[_0x1e3f60(0x2be)]=_0x1e3f60(0x211),console['timeEnd'](_0x1e3f60(0x23a));return;}document[_0x1e3f60(0x192)]('theme-select-button')[_0x1e3f60(0x225)]=()=>{showThemeSelect(this);};const _0x4a5dc8=document[_0x1e3f60(0x1f1)]();this[_0x1e3f60(0x236)][_0x1e3f60(0x25f)](_0x3d5867=>{const _0xca8100=_0x1e3f60,_0x16a31c=document[_0xca8100(0x232)]('div');_0x16a31c[_0xca8100(0x2f9)]=_0xca8100(0x1db),_0x16a31c[_0xca8100(0x2be)]=_0x3d5867[_0xca8100(0x36f)]===_0xca8100(0x1b2)?'<video\x20src=\x22'+_0x3d5867[_0xca8100(0x2ce)]+_0xca8100(0x376)+_0x3d5867[_0xca8100(0x1e6)]+_0xca8100(0x17a)+_0x3d5867[_0xca8100(0x172)]+'\x27\x22></video>'+(_0xca8100(0x177)+_0x3d5867[_0xca8100(0x1e6)]+_0xca8100(0x343))+(_0xca8100(0x1a1)+_0x3d5867[_0xca8100(0x296)]+_0xca8100(0x356))+('<p>Health:\x20'+_0x3d5867['maxHealth']+_0xca8100(0x356))+(_0xca8100(0x352)+_0x3d5867[_0xca8100(0x186)]+_0xca8100(0x356))+('<p>Speed:\x20'+_0x3d5867[_0xca8100(0x35c)]+_0xca8100(0x356))+(_0xca8100(0x328)+_0x3d5867[_0xca8100(0x317)]+_0xca8100(0x356))+(_0xca8100(0x1b9)+_0x3d5867[_0xca8100(0x34b)]+'</p>')+('<p>Power-Up:\x20'+_0x3d5867['powerup']+'</p>'):_0xca8100(0x340)+_0x3d5867[_0xca8100(0x2ce)]+_0xca8100(0x22b)+_0x3d5867[_0xca8100(0x1e6)]+_0xca8100(0x17a)+_0x3d5867[_0xca8100(0x172)]+'\x27\x22>'+(_0xca8100(0x177)+_0x3d5867[_0xca8100(0x1e6)]+'</strong></p>')+(_0xca8100(0x1a1)+_0x3d5867[_0xca8100(0x296)]+'</p>')+(_0xca8100(0x2eb)+_0x3d5867[_0xca8100(0x1a0)]+_0xca8100(0x356))+(_0xca8100(0x352)+_0x3d5867[_0xca8100(0x186)]+_0xca8100(0x356))+(_0xca8100(0x189)+_0x3d5867[_0xca8100(0x35c)]+_0xca8100(0x356))+(_0xca8100(0x328)+_0x3d5867[_0xca8100(0x317)]+_0xca8100(0x356))+(_0xca8100(0x1b9)+_0x3d5867[_0xca8100(0x34b)]+_0xca8100(0x356))+('<p>Power-Up:\x20'+_0x3d5867['powerup']+_0xca8100(0x356)),_0x16a31c[_0xca8100(0x1f8)](_0xca8100(0x30c),()=>{const _0x2284dd=_0xca8100;console['log'](_0x2284dd(0x279)+_0x3d5867[_0x2284dd(0x1e6)]),_0x5397df[_0x2284dd(0x28c)]['display']='none',_0x3e1dd1?(this['player1']={..._0x3d5867},console[_0x2284dd(0x32f)](_0x2284dd(0x310)+this[_0x2284dd(0x250)][_0x2284dd(0x1e6)]),this['initGame']()):this[_0x2284dd(0x21b)](_0x3d5867);}),_0x4a5dc8[_0xca8100(0x292)](_0x16a31c);}),_0x5c7439[_0x1e3f60(0x292)](_0x4a5dc8),console['log'](_0x1e3f60(0x275)+this['playerCharacters'][_0x1e3f60(0x2ec)]+_0x1e3f60(0x303)),console[_0x1e3f60(0x191)](_0x1e3f60(0x23a));}[_0xe5e5(0x21b)](_0x3ed000){const _0x16fcdc=_0xe5e5,_0x325b36=this['player1'][_0x16fcdc(0x2b5)],_0x3cd846=this[_0x16fcdc(0x250)][_0x16fcdc(0x1a0)],_0x1f0ed2={..._0x3ed000},_0x45ca46=Math[_0x16fcdc(0x36a)](0x1,_0x325b36/_0x3cd846);_0x1f0ed2[_0x16fcdc(0x2b5)]=Math[_0x16fcdc(0x2d3)](_0x1f0ed2[_0x16fcdc(0x1a0)]*_0x45ca46),_0x1f0ed2[_0x16fcdc(0x2b5)]=Math[_0x16fcdc(0x361)](0x0,Math[_0x16fcdc(0x36a)](_0x1f0ed2[_0x16fcdc(0x1a0)],_0x1f0ed2[_0x16fcdc(0x2b5)])),_0x1f0ed2[_0x16fcdc(0x389)]=![],_0x1f0ed2[_0x16fcdc(0x316)]=0x0,_0x1f0ed2[_0x16fcdc(0x28b)]=![],this[_0x16fcdc(0x250)]=_0x1f0ed2,this[_0x16fcdc(0x2a0)](),this['updateHealth'](this[_0x16fcdc(0x250)]),log(this['player1'][_0x16fcdc(0x1e6)]+_0x16fcdc(0x30a)+this['player1']['health']+'/'+this['player1'][_0x16fcdc(0x1a0)]+_0x16fcdc(0x233)),this[_0x16fcdc(0x2e5)]=this[_0x16fcdc(0x250)][_0x16fcdc(0x35c)]>this[_0x16fcdc(0x200)][_0x16fcdc(0x35c)]?this[_0x16fcdc(0x250)]:this[_0x16fcdc(0x200)][_0x16fcdc(0x35c)]>this[_0x16fcdc(0x250)][_0x16fcdc(0x35c)]?this[_0x16fcdc(0x200)]:this[_0x16fcdc(0x250)]['strength']>=this['player2']['strength']?this[_0x16fcdc(0x250)]:this['player2'],turnIndicator[_0x16fcdc(0x31d)]=_0x16fcdc(0x245)+this[_0x16fcdc(0x227)]+_0x16fcdc(0x22c)+(this['currentTurn']===this[_0x16fcdc(0x250)]?_0x16fcdc(0x18c):_0x16fcdc(0x280))+'\x27s\x20Turn',this[_0x16fcdc(0x2e5)]===this['player2']&&this['gameState']!==_0x16fcdc(0x2ee)&&setTimeout(()=>this['aiTurn'](),0x3e8);}['showProgressPopup'](_0x4dd0d8,_0x2b75ff){const _0x1c46bf=_0xe5e5;return console[_0x1c46bf(0x32f)](_0x1c46bf(0x1d9)+_0x4dd0d8+_0x1c46bf(0x369)+_0x2b75ff),new Promise(_0x36ea3f=>{const _0x4932b6=_0x1c46bf,_0x50ea73=document[_0x4932b6(0x232)]('div');_0x50ea73['id']=_0x4932b6(0x244),_0x50ea73[_0x4932b6(0x2f9)]=_0x4932b6(0x244);const _0x2d4443=document[_0x4932b6(0x232)](_0x4932b6(0x20b));_0x2d4443[_0x4932b6(0x2f9)]=_0x4932b6(0x1c3);const _0x3811a6=document[_0x4932b6(0x232)]('p');_0x3811a6['id']=_0x4932b6(0x38f),_0x3811a6[_0x4932b6(0x31d)]=_0x4932b6(0x388)+_0x4dd0d8+_0x4932b6(0x2fc)+_0x2b75ff+'?',_0x2d4443[_0x4932b6(0x292)](_0x3811a6);const _0x37c58b=document[_0x4932b6(0x232)](_0x4932b6(0x20b));_0x37c58b[_0x4932b6(0x2f9)]='progress-modal-buttons';const _0x4d0b5f=document[_0x4932b6(0x232)](_0x4932b6(0x187));_0x4d0b5f['id']='progress-resume',_0x4d0b5f[_0x4932b6(0x31d)]=_0x4932b6(0x196),_0x37c58b[_0x4932b6(0x292)](_0x4d0b5f);const _0xeb1681=document[_0x4932b6(0x232)](_0x4932b6(0x187));_0xeb1681['id']=_0x4932b6(0x337),_0xeb1681[_0x4932b6(0x31d)]=_0x4932b6(0x384),_0x37c58b[_0x4932b6(0x292)](_0xeb1681),_0x2d4443[_0x4932b6(0x292)](_0x37c58b),_0x50ea73[_0x4932b6(0x292)](_0x2d4443),document[_0x4932b6(0x208)][_0x4932b6(0x292)](_0x50ea73),_0x50ea73['style'][_0x4932b6(0x1a2)]=_0x4932b6(0x26f);const _0x362a31=()=>{const _0x252094=_0x4932b6;console[_0x252094(0x32f)]('showProgressPopup:\x20User\x20chose\x20Resume'),_0x50ea73['style']['display']='none',document[_0x252094(0x208)][_0x252094(0x326)](_0x50ea73),_0x4d0b5f[_0x252094(0x1f5)](_0x252094(0x30c),_0x362a31),_0xeb1681[_0x252094(0x1f5)](_0x252094(0x30c),_0x1f4598),_0x36ea3f(!![]);},_0x1f4598=()=>{const _0x7ed602=_0x4932b6;console[_0x7ed602(0x32f)](_0x7ed602(0x222)),_0x50ea73['style']['display']=_0x7ed602(0x24d),document[_0x7ed602(0x208)][_0x7ed602(0x326)](_0x50ea73),_0x4d0b5f[_0x7ed602(0x1f5)](_0x7ed602(0x30c),_0x362a31),_0xeb1681[_0x7ed602(0x1f5)](_0x7ed602(0x30c),_0x1f4598),_0x36ea3f(![]);};_0x4d0b5f['addEventListener'](_0x4932b6(0x30c),_0x362a31),_0xeb1681[_0x4932b6(0x1f8)](_0x4932b6(0x30c),_0x1f4598);});}['initGame'](){const _0x43ce3c=_0xe5e5;var _0x5824d7=this;console[_0x43ce3c(0x32f)](_0x43ce3c(0x381)+this[_0x43ce3c(0x227)]);var _0x33a819=document[_0x43ce3c(0x32a)](_0x43ce3c(0x383)),_0x2123fa=document[_0x43ce3c(0x192)](_0x43ce3c(0x1dd));_0x33a819[_0x43ce3c(0x28c)][_0x43ce3c(0x1a2)]=_0x43ce3c(0x20c),_0x2123fa[_0x43ce3c(0x28c)][_0x43ce3c(0x2df)]=_0x43ce3c(0x265),this['setBackground'](),this[_0x43ce3c(0x306)]['reset'][_0x43ce3c(0x333)](),log(_0x43ce3c(0x30d)+this['currentLevel']+_0x43ce3c(0x29d)),this[_0x43ce3c(0x200)]=this['createCharacter'](opponentsConfig[this[_0x43ce3c(0x227)]-0x1]),console[_0x43ce3c(0x32f)](_0x43ce3c(0x350)+this[_0x43ce3c(0x227)]+':\x20'+this[_0x43ce3c(0x200)][_0x43ce3c(0x1e6)]+_0x43ce3c(0x32c)+(this[_0x43ce3c(0x227)]-0x1)+'])'),this[_0x43ce3c(0x250)]['health']=this['player1']['maxHealth'],this[_0x43ce3c(0x2e5)]=this[_0x43ce3c(0x250)][_0x43ce3c(0x35c)]>this[_0x43ce3c(0x200)][_0x43ce3c(0x35c)]?this[_0x43ce3c(0x250)]:this['player2']['speed']>this['player1']['speed']?this[_0x43ce3c(0x200)]:this[_0x43ce3c(0x250)]['strength']>=this[_0x43ce3c(0x200)]['strength']?this[_0x43ce3c(0x250)]:this[_0x43ce3c(0x200)],this[_0x43ce3c(0x2b4)]=_0x43ce3c(0x2e0),this[_0x43ce3c(0x2ee)]=![],this[_0x43ce3c(0x1ba)]=[];const _0x47a214=document[_0x43ce3c(0x192)]('p1-image'),_0x56ecbb=document['getElementById'](_0x43ce3c(0x1a8));if(_0x47a214)_0x47a214[_0x43ce3c(0x1d4)]['remove'](_0x43ce3c(0x228),_0x43ce3c(0x29b));if(_0x56ecbb)_0x56ecbb[_0x43ce3c(0x1d4)][_0x43ce3c(0x229)](_0x43ce3c(0x228),_0x43ce3c(0x29b));this[_0x43ce3c(0x2a0)](),this['updateOpponentDisplay']();if(_0x47a214)_0x47a214['style']['transform']=this[_0x43ce3c(0x250)]['orientation']===_0x43ce3c(0x34e)?_0x43ce3c(0x2c6):_0x43ce3c(0x24d);if(_0x56ecbb)_0x56ecbb[_0x43ce3c(0x28c)][_0x43ce3c(0x39c)]=this[_0x43ce3c(0x200)][_0x43ce3c(0x285)]===_0x43ce3c(0x2fb)?_0x43ce3c(0x2c6):_0x43ce3c(0x24d);this[_0x43ce3c(0x377)](this['player1']),this[_0x43ce3c(0x377)](this[_0x43ce3c(0x200)]),battleLog[_0x43ce3c(0x2be)]='',gameOver[_0x43ce3c(0x31d)]='',this[_0x43ce3c(0x250)]['size']!=='Medium'&&log(this['player1'][_0x43ce3c(0x1e6)]+_0x43ce3c(0x2bd)+this[_0x43ce3c(0x250)][_0x43ce3c(0x34b)]+_0x43ce3c(0x2d9)+(this[_0x43ce3c(0x250)][_0x43ce3c(0x34b)]===_0x43ce3c(0x271)?'boosts\x20health\x20to\x20'+this['player1']['maxHealth']+'\x20but\x20dulls\x20tactics\x20to\x20'+this['player1'][_0x43ce3c(0x317)]:_0x43ce3c(0x398)+this[_0x43ce3c(0x250)][_0x43ce3c(0x1a0)]+'\x20but\x20sharpens\x20tactics\x20to\x20'+this[_0x43ce3c(0x250)]['tactics'])+'!'),this[_0x43ce3c(0x200)][_0x43ce3c(0x34b)]!==_0x43ce3c(0x19d)&&log(this[_0x43ce3c(0x200)][_0x43ce3c(0x1e6)]+'\x27s\x20'+this[_0x43ce3c(0x200)][_0x43ce3c(0x34b)]+_0x43ce3c(0x2d9)+(this['player2'][_0x43ce3c(0x34b)]===_0x43ce3c(0x271)?'boosts\x20health\x20to\x20'+this['player2'][_0x43ce3c(0x1a0)]+_0x43ce3c(0x22d)+this['player2'][_0x43ce3c(0x317)]:_0x43ce3c(0x398)+this['player2'][_0x43ce3c(0x1a0)]+_0x43ce3c(0x359)+this[_0x43ce3c(0x200)][_0x43ce3c(0x317)])+'!'),log(this[_0x43ce3c(0x250)][_0x43ce3c(0x1e6)]+'\x20starts\x20at\x20full\x20strength\x20with\x20'+this['player1'][_0x43ce3c(0x2b5)]+'/'+this[_0x43ce3c(0x250)][_0x43ce3c(0x1a0)]+_0x43ce3c(0x233)),log(this['currentTurn']['name']+_0x43ce3c(0x1ed)),this[_0x43ce3c(0x1dc)](),this['gameState']=this['currentTurn']===this[_0x43ce3c(0x250)]?'playerTurn':'aiTurn',turnIndicator[_0x43ce3c(0x31d)]=_0x43ce3c(0x245)+this[_0x43ce3c(0x227)]+_0x43ce3c(0x22c)+(this['currentTurn']===this[_0x43ce3c(0x250)]?_0x43ce3c(0x18c):_0x43ce3c(0x280))+_0x43ce3c(0x22e),this[_0x43ce3c(0x236)][_0x43ce3c(0x2ec)]>0x1&&(document[_0x43ce3c(0x192)](_0x43ce3c(0x2ac))['style']['display']=_0x43ce3c(0x1b8)),this[_0x43ce3c(0x2e5)]===this[_0x43ce3c(0x200)]&&setTimeout(function(){_0x5824d7['aiTurn']();},0x3e8);}[_0xe5e5(0x2a0)](){const _0x19a59d=_0xe5e5;p1Name['textContent']=this[_0x19a59d(0x250)]['isNFT']||this['theme']===_0x19a59d(0x35b)?this['player1'][_0x19a59d(0x1e6)]:'Player\x201',p1Type[_0x19a59d(0x31d)]=this['player1'][_0x19a59d(0x296)],p1Strength[_0x19a59d(0x31d)]=this[_0x19a59d(0x250)][_0x19a59d(0x186)],p1Speed[_0x19a59d(0x31d)]=this[_0x19a59d(0x250)][_0x19a59d(0x35c)],p1Tactics['textContent']=this['player1'][_0x19a59d(0x317)],p1Size[_0x19a59d(0x31d)]=this[_0x19a59d(0x250)][_0x19a59d(0x34b)],p1Powerup[_0x19a59d(0x31d)]=this[_0x19a59d(0x250)]['powerup'];const _0x2dd23e=document['getElementById'](_0x19a59d(0x294)),_0x59435d=_0x2dd23e['parentNode'];if(this[_0x19a59d(0x250)][_0x19a59d(0x36f)]===_0x19a59d(0x1b2)){if(_0x2dd23e[_0x19a59d(0x180)]!==_0x19a59d(0x2d2)){const _0x5a215f=document[_0x19a59d(0x232)](_0x19a59d(0x1b2));_0x5a215f['id']=_0x19a59d(0x294),_0x5a215f['src']=this['player1'][_0x19a59d(0x2ce)],_0x5a215f[_0x19a59d(0x1f6)]=!![],_0x5a215f[_0x19a59d(0x2f3)]=!![],_0x5a215f[_0x19a59d(0x1c2)]=!![],_0x5a215f['alt']=this['player1'][_0x19a59d(0x1e6)],_0x5a215f[_0x19a59d(0x3aa)]=()=>{const _0x4032af=_0x19a59d;_0x5a215f[_0x4032af(0x248)]=this[_0x4032af(0x250)]['fallbackUrl'];},_0x59435d['replaceChild'](_0x5a215f,_0x2dd23e);}else _0x2dd23e[_0x19a59d(0x248)]=this[_0x19a59d(0x250)][_0x19a59d(0x2ce)],_0x2dd23e[_0x19a59d(0x3aa)]=()=>{const _0x3f56fe=_0x19a59d;_0x2dd23e[_0x3f56fe(0x248)]=this['player1']['fallbackUrl'];};}else{if(_0x2dd23e[_0x19a59d(0x180)]!==_0x19a59d(0x3a4)){const _0x1c152d=document['createElement']('img');_0x1c152d['id']=_0x19a59d(0x294),_0x1c152d[_0x19a59d(0x248)]=this[_0x19a59d(0x250)][_0x19a59d(0x2ce)],_0x1c152d[_0x19a59d(0x1d3)]=this['player1']['name'],_0x1c152d[_0x19a59d(0x3aa)]=()=>{const _0x9271b7=_0x19a59d;_0x1c152d[_0x9271b7(0x248)]=this[_0x9271b7(0x250)][_0x9271b7(0x172)];},_0x59435d[_0x19a59d(0x31f)](_0x1c152d,_0x2dd23e);}else _0x2dd23e[_0x19a59d(0x248)]=this[_0x19a59d(0x250)][_0x19a59d(0x2ce)],_0x2dd23e[_0x19a59d(0x3aa)]=()=>{const _0x3c0cc4=_0x19a59d;_0x2dd23e[_0x3c0cc4(0x248)]=this[_0x3c0cc4(0x250)]['fallbackUrl'];};}const _0x5b5ff6=document[_0x19a59d(0x192)](_0x19a59d(0x294));_0x5b5ff6[_0x19a59d(0x28c)][_0x19a59d(0x39c)]=this['player1'][_0x19a59d(0x285)]===_0x19a59d(0x34e)?'scaleX(-1)':_0x19a59d(0x24d),_0x5b5ff6[_0x19a59d(0x180)]===_0x19a59d(0x3a4)?_0x5b5ff6[_0x19a59d(0x39e)]=function(){const _0x2ce3a9=_0x19a59d;_0x5b5ff6[_0x2ce3a9(0x28c)][_0x2ce3a9(0x1a2)]=_0x2ce3a9(0x20c);}:_0x5b5ff6[_0x19a59d(0x28c)]['display']='block',p1Hp['textContent']=this['player1'][_0x19a59d(0x2b5)]+'/'+this[_0x19a59d(0x250)][_0x19a59d(0x1a0)],_0x5b5ff6[_0x19a59d(0x225)]=()=>{const _0x623cdf=_0x19a59d;console[_0x623cdf(0x32f)]('Player\x201\x20media\x20clicked'),this['showCharacterSelect'](![]);};}[_0xe5e5(0x181)](){const _0x4b303d=_0xe5e5;p2Name[_0x4b303d(0x31d)]=this[_0x4b303d(0x24c)]==='monstrocity'?this[_0x4b303d(0x200)][_0x4b303d(0x1e6)]:_0x4b303d(0x2b0),p2Type[_0x4b303d(0x31d)]=this[_0x4b303d(0x200)]['type'],p2Strength[_0x4b303d(0x31d)]=this[_0x4b303d(0x200)][_0x4b303d(0x186)],p2Speed[_0x4b303d(0x31d)]=this[_0x4b303d(0x200)][_0x4b303d(0x35c)],p2Tactics[_0x4b303d(0x31d)]=this[_0x4b303d(0x200)]['tactics'],p2Size[_0x4b303d(0x31d)]=this['player2']['size'],p2Powerup[_0x4b303d(0x31d)]=this[_0x4b303d(0x200)][_0x4b303d(0x37b)];const _0x556a78=document[_0x4b303d(0x192)](_0x4b303d(0x1a8)),_0x90e20d=_0x556a78[_0x4b303d(0x319)];if(this[_0x4b303d(0x200)][_0x4b303d(0x36f)]===_0x4b303d(0x1b2)){if(_0x556a78[_0x4b303d(0x180)]!=='VIDEO'){const _0x5ecf3a=document[_0x4b303d(0x232)](_0x4b303d(0x1b2));_0x5ecf3a['id']=_0x4b303d(0x1a8),_0x5ecf3a[_0x4b303d(0x248)]=this['player2'][_0x4b303d(0x2ce)],_0x5ecf3a['autoplay']=!![],_0x5ecf3a[_0x4b303d(0x2f3)]=!![],_0x5ecf3a['muted']=!![],_0x5ecf3a[_0x4b303d(0x1d3)]=this[_0x4b303d(0x200)][_0x4b303d(0x1e6)],_0x90e20d[_0x4b303d(0x31f)](_0x5ecf3a,_0x556a78);}else _0x556a78['src']=this['player2']['imageUrl'];}else{if(_0x556a78[_0x4b303d(0x180)]!==_0x4b303d(0x3a4)){const _0x360b74=document[_0x4b303d(0x232)](_0x4b303d(0x178));_0x360b74['id']='p2-image',_0x360b74[_0x4b303d(0x248)]=this[_0x4b303d(0x200)][_0x4b303d(0x2ce)],_0x360b74[_0x4b303d(0x1d3)]=this['player2']['name'],_0x90e20d[_0x4b303d(0x31f)](_0x360b74,_0x556a78);}else _0x556a78['src']=this[_0x4b303d(0x200)][_0x4b303d(0x2ce)];}const _0x131b6c=document['getElementById'](_0x4b303d(0x1a8));_0x131b6c[_0x4b303d(0x28c)][_0x4b303d(0x39c)]=this[_0x4b303d(0x200)][_0x4b303d(0x285)]===_0x4b303d(0x2fb)?'scaleX(-1)':_0x4b303d(0x24d),_0x131b6c[_0x4b303d(0x180)]==='IMG'?_0x131b6c[_0x4b303d(0x39e)]=function(){const _0x20b75f=_0x4b303d;_0x131b6c[_0x20b75f(0x28c)][_0x20b75f(0x1a2)]='block';}:_0x131b6c[_0x4b303d(0x28c)][_0x4b303d(0x1a2)]='block',p2Hp[_0x4b303d(0x31d)]=this[_0x4b303d(0x200)][_0x4b303d(0x2b5)]+'/'+this['player2'][_0x4b303d(0x1a0)],_0x131b6c['classList']['remove']('winner','loser');}[_0xe5e5(0x1dc)](){const _0x85b17f=_0xe5e5;this[_0x85b17f(0x1f7)]=[];for(let _0x389dac=0x0;_0x389dac<this['height'];_0x389dac++){this[_0x85b17f(0x1f7)][_0x389dac]=[];for(let _0x5ec949=0x0;_0x5ec949<this['width'];_0x5ec949++){let _0x530265;do{_0x530265=this[_0x85b17f(0x28d)]();}while(_0x5ec949>=0x2&&this['board'][_0x389dac][_0x5ec949-0x1]?.[_0x85b17f(0x296)]===_0x530265[_0x85b17f(0x296)]&&this['board'][_0x389dac][_0x5ec949-0x2]?.['type']===_0x530265['type']||_0x389dac>=0x2&&this[_0x85b17f(0x1f7)][_0x389dac-0x1]?.[_0x5ec949]?.[_0x85b17f(0x296)]===_0x530265[_0x85b17f(0x296)]&&this[_0x85b17f(0x1f7)][_0x389dac-0x2]?.[_0x5ec949]?.[_0x85b17f(0x296)]===_0x530265['type']);this[_0x85b17f(0x1f7)][_0x389dac][_0x5ec949]=_0x530265;}}console['log']('initBoard:\x20Board\x20initialized\x20with\x20dimensions',this[_0x85b17f(0x216)],'x',this[_0x85b17f(0x39b)]),this[_0x85b17f(0x1f3)]();}['createRandomTile'](){const _0x26cab3=_0xe5e5;return{'type':randomChoice(this[_0x26cab3(0x2ef)]),'element':null};}[_0xe5e5(0x1f3)](){const _0x142a6e=_0xe5e5;this[_0x142a6e(0x1fb)]();const _0xcbba8b=document[_0x142a6e(0x192)](_0x142a6e(0x1dd));_0xcbba8b['innerHTML']='';if(!this[_0x142a6e(0x1f7)]||!Array['isArray'](this[_0x142a6e(0x1f7)])||this[_0x142a6e(0x1f7)][_0x142a6e(0x2ec)]!==this[_0x142a6e(0x39b)]){console[_0x142a6e(0x354)](_0x142a6e(0x1bf));return;}for(let _0x535fd1=0x0;_0x535fd1<this[_0x142a6e(0x39b)];_0x535fd1++){if(!Array[_0x142a6e(0x27b)](this[_0x142a6e(0x1f7)][_0x535fd1])){console[_0x142a6e(0x354)](_0x142a6e(0x2fa)+_0x535fd1+'\x20is\x20not\x20an\x20array,\x20skipping');continue;}for(let _0x59efb9=0x0;_0x59efb9<this['width'];_0x59efb9++){const _0x33cd33=this['board'][_0x535fd1][_0x59efb9];if(!_0x33cd33||_0x33cd33['type']===null)continue;const _0x405f41=document[_0x142a6e(0x232)](_0x142a6e(0x20b));_0x405f41['className']=_0x142a6e(0x1d2)+_0x33cd33[_0x142a6e(0x296)];if(this[_0x142a6e(0x2ee)])_0x405f41[_0x142a6e(0x1d4)][_0x142a6e(0x2fd)]('game-over');const _0x5f3412=document[_0x142a6e(0x232)](_0x142a6e(0x178));_0x5f3412[_0x142a6e(0x248)]=_0x142a6e(0x26e)+_0x33cd33['type']+_0x142a6e(0x1c0),_0x5f3412[_0x142a6e(0x1d3)]=_0x33cd33[_0x142a6e(0x296)],_0x405f41[_0x142a6e(0x292)](_0x5f3412),_0x405f41[_0x142a6e(0x299)]['x']=_0x59efb9,_0x405f41[_0x142a6e(0x299)]['y']=_0x535fd1,_0xcbba8b[_0x142a6e(0x292)](_0x405f41),_0x33cd33['element']=_0x405f41,(!this[_0x142a6e(0x297)]||this[_0x142a6e(0x22a)]&&(this['selectedTile']['x']!==_0x59efb9||this[_0x142a6e(0x22a)]['y']!==_0x535fd1))&&(_0x405f41['style'][_0x142a6e(0x39c)]=_0x142a6e(0x17b)),this[_0x142a6e(0x382)]?_0x405f41[_0x142a6e(0x1f8)](_0x142a6e(0x30e),_0x1002e6=>this[_0x142a6e(0x3a9)](_0x1002e6)):_0x405f41[_0x142a6e(0x1f8)](_0x142a6e(0x1aa),_0xc5c798=>this[_0x142a6e(0x1de)](_0xc5c798));}}document[_0x142a6e(0x192)](_0x142a6e(0x202))[_0x142a6e(0x28c)][_0x142a6e(0x1a2)]=this[_0x142a6e(0x2ee)]?_0x142a6e(0x20c):'none',console['log']('renderBoard:\x20Board\x20rendered\x20successfully');}[_0xe5e5(0x2ea)](){const _0x23f8a9=_0xe5e5,_0x241f59=document[_0x23f8a9(0x192)](_0x23f8a9(0x1dd));this['isTouchDevice']?(_0x241f59[_0x23f8a9(0x1f8)](_0x23f8a9(0x30e),_0x42736d=>this[_0x23f8a9(0x3a9)](_0x42736d)),_0x241f59['addEventListener'](_0x23f8a9(0x2fe),_0x446165=>this[_0x23f8a9(0x2cc)](_0x446165)),_0x241f59[_0x23f8a9(0x1f8)](_0x23f8a9(0x276),_0x3967dc=>this[_0x23f8a9(0x267)](_0x3967dc))):(_0x241f59['addEventListener'](_0x23f8a9(0x1aa),_0xbffe20=>this[_0x23f8a9(0x1de)](_0xbffe20)),_0x241f59[_0x23f8a9(0x1f8)](_0x23f8a9(0x264),_0x521fbb=>this[_0x23f8a9(0x249)](_0x521fbb)),_0x241f59['addEventListener']('mouseup',_0x409b48=>this[_0x23f8a9(0x2a9)](_0x409b48)));document[_0x23f8a9(0x192)](_0x23f8a9(0x322))['addEventListener'](_0x23f8a9(0x30c),()=>this[_0x23f8a9(0x1a9)]()),document[_0x23f8a9(0x192)](_0x23f8a9(0x2d6))[_0x23f8a9(0x1f8)](_0x23f8a9(0x30c),()=>{const _0x4e268c=_0x23f8a9;this[_0x4e268c(0x1e4)]();});const _0x118ced=document[_0x23f8a9(0x192)](_0x23f8a9(0x2ac)),_0x442e2e=document['getElementById']('p1-image'),_0x50f0ff=document[_0x23f8a9(0x192)](_0x23f8a9(0x1a8));_0x118ced['addEventListener'](_0x23f8a9(0x30c),()=>{const _0x436ff3=_0x23f8a9;console[_0x436ff3(0x32f)](_0x436ff3(0x1a4)),this['showCharacterSelect'](![]);}),_0x442e2e[_0x23f8a9(0x1f8)]('click',()=>{const _0xa54550=_0x23f8a9;console[_0xa54550(0x32f)](_0xa54550(0x206)),this['showCharacterSelect'](![]);}),document[_0x23f8a9(0x192)]('flip-p1')[_0x23f8a9(0x1f8)]('click',()=>this[_0x23f8a9(0x2e8)](this['player1'],document[_0x23f8a9(0x192)](_0x23f8a9(0x294)),![])),document[_0x23f8a9(0x192)](_0x23f8a9(0x254))['addEventListener']('click',()=>this['flipCharacter'](this['player2'],document[_0x23f8a9(0x192)]('p2-image'),!![]));}[_0xe5e5(0x1a9)](){const _0xd1414c=_0xe5e5;console[_0xd1414c(0x32f)](_0xd1414c(0x18a)+this[_0xd1414c(0x227)]+_0xd1414c(0x260)+this[_0xd1414c(0x200)]['health']),this[_0xd1414c(0x200)]['health']<=0x0&&this[_0xd1414c(0x227)]>opponentsConfig[_0xd1414c(0x2ec)]&&(this[_0xd1414c(0x227)]=0x1,console[_0xd1414c(0x32f)](_0xd1414c(0x209)+this[_0xd1414c(0x227)])),this[_0xd1414c(0x1e4)](),console[_0xd1414c(0x32f)](_0xd1414c(0x344)+this['currentLevel']);}['handleMouseDown'](_0x297bec){const _0x4d3e3c=_0xe5e5;if(this[_0x4d3e3c(0x2ee)]||this[_0x4d3e3c(0x2b4)]!=='playerTurn'||this['currentTurn']!==this['player1'])return;_0x297bec[_0x4d3e3c(0x1eb)]();const _0x44fa77=this[_0x4d3e3c(0x358)](_0x297bec);if(!_0x44fa77||!_0x44fa77[_0x4d3e3c(0x1cd)])return;this[_0x4d3e3c(0x297)]=!![],this[_0x4d3e3c(0x22a)]={'x':_0x44fa77['x'],'y':_0x44fa77['y']},_0x44fa77[_0x4d3e3c(0x1cd)][_0x4d3e3c(0x1d4)][_0x4d3e3c(0x2fd)](_0x4d3e3c(0x34f));const _0x89c0bc=document['getElementById'](_0x4d3e3c(0x1dd))['getBoundingClientRect']();this[_0x4d3e3c(0x17e)]=_0x297bec['clientX']-(_0x89c0bc['left']+this['selectedTile']['x']*this[_0x4d3e3c(0x173)]),this['offsetY']=_0x297bec[_0x4d3e3c(0x179)]-(_0x89c0bc[_0x4d3e3c(0x2a4)]+this['selectedTile']['y']*this['tileSizeWithGap']);}[_0xe5e5(0x249)](_0x36e3be){const _0x47dcb3=_0xe5e5;if(!this[_0x47dcb3(0x297)]||!this[_0x47dcb3(0x22a)]||this[_0x47dcb3(0x2ee)]||this[_0x47dcb3(0x2b4)]!==_0x47dcb3(0x16c))return;_0x36e3be[_0x47dcb3(0x1eb)]();const _0x588994=document[_0x47dcb3(0x192)](_0x47dcb3(0x1dd))[_0x47dcb3(0x2da)](),_0x498a36=_0x36e3be[_0x47dcb3(0x35a)]-_0x588994[_0x47dcb3(0x2c4)]-this[_0x47dcb3(0x17e)],_0x5ac7f1=_0x36e3be[_0x47dcb3(0x179)]-_0x588994[_0x47dcb3(0x2a4)]-this[_0x47dcb3(0x26c)],_0x1fe2ba=this[_0x47dcb3(0x1f7)][this[_0x47dcb3(0x22a)]['y']][this[_0x47dcb3(0x22a)]['x']][_0x47dcb3(0x1cd)];_0x1fe2ba[_0x47dcb3(0x28c)][_0x47dcb3(0x394)]='';if(!this[_0x47dcb3(0x16e)]){const _0x495603=Math['abs'](_0x498a36-this[_0x47dcb3(0x22a)]['x']*this[_0x47dcb3(0x173)]),_0x8feedb=Math['abs'](_0x5ac7f1-this[_0x47dcb3(0x22a)]['y']*this[_0x47dcb3(0x173)]);if(_0x495603>_0x8feedb&&_0x495603>0x5)this['dragDirection']=_0x47dcb3(0x29c);else{if(_0x8feedb>_0x495603&&_0x8feedb>0x5)this[_0x47dcb3(0x16e)]=_0x47dcb3(0x2cd);}}if(!this[_0x47dcb3(0x16e)])return;if(this['dragDirection']===_0x47dcb3(0x29c)){const _0xc1db61=Math[_0x47dcb3(0x361)](0x0,Math['min']((this[_0x47dcb3(0x216)]-0x1)*this[_0x47dcb3(0x173)],_0x498a36));_0x1fe2ba[_0x47dcb3(0x28c)][_0x47dcb3(0x39c)]=_0x47dcb3(0x302)+(_0xc1db61-this[_0x47dcb3(0x22a)]['x']*this[_0x47dcb3(0x173)])+_0x47dcb3(0x38d),this[_0x47dcb3(0x188)]={'x':Math[_0x47dcb3(0x2d3)](_0xc1db61/this[_0x47dcb3(0x173)]),'y':this[_0x47dcb3(0x22a)]['y']};}else{if(this[_0x47dcb3(0x16e)]===_0x47dcb3(0x2cd)){const _0x5bfeb6=Math[_0x47dcb3(0x361)](0x0,Math[_0x47dcb3(0x36a)]((this[_0x47dcb3(0x39b)]-0x1)*this[_0x47dcb3(0x173)],_0x5ac7f1));_0x1fe2ba['style'][_0x47dcb3(0x39c)]='translate(0,\x20'+(_0x5bfeb6-this[_0x47dcb3(0x22a)]['y']*this['tileSizeWithGap'])+'px)\x20scale(1.05)',this[_0x47dcb3(0x188)]={'x':this['selectedTile']['x'],'y':Math[_0x47dcb3(0x2d3)](_0x5bfeb6/this['tileSizeWithGap'])};}}}[_0xe5e5(0x2a9)](_0x29eea5){const _0x5cdae6=_0xe5e5;if(!this[_0x5cdae6(0x297)]||!this[_0x5cdae6(0x22a)]||!this[_0x5cdae6(0x188)]||this[_0x5cdae6(0x2ee)]||this['gameState']!=='playerTurn'){if(this[_0x5cdae6(0x22a)]){const _0x50023d=this[_0x5cdae6(0x1f7)][this[_0x5cdae6(0x22a)]['y']][this[_0x5cdae6(0x22a)]['x']];if(_0x50023d[_0x5cdae6(0x1cd)])_0x50023d[_0x5cdae6(0x1cd)]['classList'][_0x5cdae6(0x229)](_0x5cdae6(0x34f));}this[_0x5cdae6(0x297)]=![],this[_0x5cdae6(0x22a)]=null,this[_0x5cdae6(0x188)]=null,this['dragDirection']=null,this[_0x5cdae6(0x1f3)]();return;}const _0x586ef1=this[_0x5cdae6(0x1f7)][this[_0x5cdae6(0x22a)]['y']][this['selectedTile']['x']];if(_0x586ef1[_0x5cdae6(0x1cd)])_0x586ef1[_0x5cdae6(0x1cd)][_0x5cdae6(0x1d4)][_0x5cdae6(0x229)](_0x5cdae6(0x34f));this[_0x5cdae6(0x26a)](this['selectedTile']['x'],this[_0x5cdae6(0x22a)]['y'],this[_0x5cdae6(0x188)]['x'],this[_0x5cdae6(0x188)]['y']),this['isDragging']=![],this['selectedTile']=null,this[_0x5cdae6(0x188)]=null,this[_0x5cdae6(0x16e)]=null;}['handleTouchStart'](_0x557c4c){const _0x406f3d=_0xe5e5;if(this['gameOver']||this[_0x406f3d(0x2b4)]!==_0x406f3d(0x16c)||this[_0x406f3d(0x2e5)]!==this[_0x406f3d(0x250)])return;_0x557c4c[_0x406f3d(0x1eb)]();const _0x50adbe=this[_0x406f3d(0x358)](_0x557c4c['touches'][0x0]);if(!_0x50adbe||!_0x50adbe[_0x406f3d(0x1cd)])return;this[_0x406f3d(0x297)]=!![],this['selectedTile']={'x':_0x50adbe['x'],'y':_0x50adbe['y']},_0x50adbe['element'][_0x406f3d(0x1d4)][_0x406f3d(0x2fd)](_0x406f3d(0x34f));const _0x567039=document[_0x406f3d(0x192)](_0x406f3d(0x1dd))[_0x406f3d(0x2da)]();this[_0x406f3d(0x17e)]=_0x557c4c[_0x406f3d(0x23b)][0x0][_0x406f3d(0x35a)]-(_0x567039[_0x406f3d(0x2c4)]+this['selectedTile']['x']*this[_0x406f3d(0x173)]),this[_0x406f3d(0x26c)]=_0x557c4c['touches'][0x0][_0x406f3d(0x179)]-(_0x567039[_0x406f3d(0x2a4)]+this['selectedTile']['y']*this[_0x406f3d(0x173)]);}[_0xe5e5(0x2cc)](_0x19a301){const _0x3f743a=_0xe5e5;if(!this['isDragging']||!this[_0x3f743a(0x22a)]||this[_0x3f743a(0x2ee)]||this[_0x3f743a(0x2b4)]!=='playerTurn')return;_0x19a301['preventDefault']();const _0x35ea27=document['getElementById'](_0x3f743a(0x1dd))[_0x3f743a(0x2da)](),_0xf0174f=_0x19a301['touches'][0x0]['clientX']-_0x35ea27[_0x3f743a(0x2c4)]-this[_0x3f743a(0x17e)],_0x80dd13=_0x19a301[_0x3f743a(0x23b)][0x0][_0x3f743a(0x179)]-_0x35ea27[_0x3f743a(0x2a4)]-this[_0x3f743a(0x26c)],_0x25e7e3=this[_0x3f743a(0x1f7)][this[_0x3f743a(0x22a)]['y']][this[_0x3f743a(0x22a)]['x']][_0x3f743a(0x1cd)];requestAnimationFrame(()=>{const _0x27c4cf=_0x3f743a;if(!this[_0x27c4cf(0x16e)]){const _0x311ce1=Math[_0x27c4cf(0x2a1)](_0xf0174f-this['selectedTile']['x']*this[_0x27c4cf(0x173)]),_0x3bcf7a=Math[_0x27c4cf(0x2a1)](_0x80dd13-this[_0x27c4cf(0x22a)]['y']*this[_0x27c4cf(0x173)]);if(_0x311ce1>_0x3bcf7a&&_0x311ce1>0x7)this[_0x27c4cf(0x16e)]=_0x27c4cf(0x29c);else{if(_0x3bcf7a>_0x311ce1&&_0x3bcf7a>0x7)this[_0x27c4cf(0x16e)]=_0x27c4cf(0x2cd);}}_0x25e7e3[_0x27c4cf(0x28c)][_0x27c4cf(0x394)]='';if(this[_0x27c4cf(0x16e)]===_0x27c4cf(0x29c)){const _0x66cc37=Math[_0x27c4cf(0x361)](0x0,Math[_0x27c4cf(0x36a)]((this[_0x27c4cf(0x216)]-0x1)*this['tileSizeWithGap'],_0xf0174f));_0x25e7e3[_0x27c4cf(0x28c)][_0x27c4cf(0x39c)]=_0x27c4cf(0x302)+(_0x66cc37-this[_0x27c4cf(0x22a)]['x']*this[_0x27c4cf(0x173)])+'px,\x200)\x20scale(1.05)',this[_0x27c4cf(0x188)]={'x':Math[_0x27c4cf(0x2d3)](_0x66cc37/this[_0x27c4cf(0x173)]),'y':this[_0x27c4cf(0x22a)]['y']};}else{if(this[_0x27c4cf(0x16e)]===_0x27c4cf(0x2cd)){const _0x5c1334=Math[_0x27c4cf(0x361)](0x0,Math[_0x27c4cf(0x36a)]((this['height']-0x1)*this[_0x27c4cf(0x173)],_0x80dd13));_0x25e7e3[_0x27c4cf(0x28c)]['transform']=_0x27c4cf(0x224)+(_0x5c1334-this['selectedTile']['y']*this[_0x27c4cf(0x173)])+'px)\x20scale(1.05)',this[_0x27c4cf(0x188)]={'x':this[_0x27c4cf(0x22a)]['x'],'y':Math[_0x27c4cf(0x2d3)](_0x5c1334/this[_0x27c4cf(0x173)])};}}});}[_0xe5e5(0x267)](_0x94bb5){const _0x5bc096=_0xe5e5;if(!this[_0x5bc096(0x297)]||!this[_0x5bc096(0x22a)]||!this[_0x5bc096(0x188)]||this[_0x5bc096(0x2ee)]||this[_0x5bc096(0x2b4)]!==_0x5bc096(0x16c)){if(this[_0x5bc096(0x22a)]){const _0xb41101=this[_0x5bc096(0x1f7)][this[_0x5bc096(0x22a)]['y']][this[_0x5bc096(0x22a)]['x']];if(_0xb41101['element'])_0xb41101[_0x5bc096(0x1cd)]['classList'][_0x5bc096(0x229)](_0x5bc096(0x34f));}this[_0x5bc096(0x297)]=![],this[_0x5bc096(0x22a)]=null,this[_0x5bc096(0x188)]=null,this[_0x5bc096(0x16e)]=null,this[_0x5bc096(0x1f3)]();return;}const _0x1a0afe=this[_0x5bc096(0x1f7)][this[_0x5bc096(0x22a)]['y']][this[_0x5bc096(0x22a)]['x']];if(_0x1a0afe[_0x5bc096(0x1cd)])_0x1a0afe[_0x5bc096(0x1cd)][_0x5bc096(0x1d4)][_0x5bc096(0x229)](_0x5bc096(0x34f));this[_0x5bc096(0x26a)](this['selectedTile']['x'],this['selectedTile']['y'],this[_0x5bc096(0x188)]['x'],this[_0x5bc096(0x188)]['y']),this['isDragging']=![],this[_0x5bc096(0x22a)]=null,this['targetTile']=null,this[_0x5bc096(0x16e)]=null;}[_0xe5e5(0x358)](_0x555da6){const _0x550df7=_0xe5e5,_0x356c30=document[_0x550df7(0x192)](_0x550df7(0x1dd))[_0x550df7(0x2da)](),_0x34aed2=Math['floor']((_0x555da6[_0x550df7(0x35a)]-_0x356c30[_0x550df7(0x2c4)])/this['tileSizeWithGap']),_0x5b79c2=Math[_0x550df7(0x33a)]((_0x555da6[_0x550df7(0x179)]-_0x356c30[_0x550df7(0x2a4)])/this[_0x550df7(0x173)]);if(_0x34aed2>=0x0&&_0x34aed2<this[_0x550df7(0x216)]&&_0x5b79c2>=0x0&&_0x5b79c2<this['height'])return{'x':_0x34aed2,'y':_0x5b79c2,'element':this[_0x550df7(0x1f7)][_0x5b79c2][_0x34aed2][_0x550df7(0x1cd)]};return null;}[_0xe5e5(0x26a)](_0x20c70f,_0x4e4870,_0xd70963,_0x2874d0){const _0x150f10=_0xe5e5,_0x21c8a5=this['tileSizeWithGap'];let _0x506f48;const _0xe7ef5a=[],_0x400838=[];if(_0x4e4870===_0x2874d0){_0x506f48=_0x20c70f<_0xd70963?0x1:-0x1;const _0x485706=Math['min'](_0x20c70f,_0xd70963),_0x530638=Math['max'](_0x20c70f,_0xd70963);for(let _0x35e1f4=_0x485706;_0x35e1f4<=_0x530638;_0x35e1f4++){_0xe7ef5a['push']({...this[_0x150f10(0x1f7)][_0x4e4870][_0x35e1f4]}),_0x400838[_0x150f10(0x2ab)](this[_0x150f10(0x1f7)][_0x4e4870][_0x35e1f4][_0x150f10(0x1cd)]);}}else{if(_0x20c70f===_0xd70963){_0x506f48=_0x4e4870<_0x2874d0?0x1:-0x1;const _0x1a57cc=Math[_0x150f10(0x36a)](_0x4e4870,_0x2874d0),_0x281f5a=Math[_0x150f10(0x361)](_0x4e4870,_0x2874d0);for(let _0x489efa=_0x1a57cc;_0x489efa<=_0x281f5a;_0x489efa++){_0xe7ef5a[_0x150f10(0x2ab)]({...this[_0x150f10(0x1f7)][_0x489efa][_0x20c70f]}),_0x400838[_0x150f10(0x2ab)](this[_0x150f10(0x1f7)][_0x489efa][_0x20c70f][_0x150f10(0x1cd)]);}}}const _0x3cc854=this[_0x150f10(0x1f7)][_0x4e4870][_0x20c70f][_0x150f10(0x1cd)],_0x2883ab=(_0xd70963-_0x20c70f)*_0x21c8a5,_0x4a0f03=(_0x2874d0-_0x4e4870)*_0x21c8a5;_0x3cc854[_0x150f10(0x28c)]['transition']=_0x150f10(0x300),_0x3cc854[_0x150f10(0x28c)][_0x150f10(0x39c)]=_0x150f10(0x302)+_0x2883ab+_0x150f10(0x20d)+_0x4a0f03+_0x150f10(0x33c);let _0x4153f0=0x0;if(_0x4e4870===_0x2874d0)for(let _0x3c4d97=Math[_0x150f10(0x36a)](_0x20c70f,_0xd70963);_0x3c4d97<=Math[_0x150f10(0x361)](_0x20c70f,_0xd70963);_0x3c4d97++){if(_0x3c4d97===_0x20c70f)continue;const _0x50c20b=_0x506f48*-_0x21c8a5*(_0x3c4d97-_0x20c70f)/Math[_0x150f10(0x2a1)](_0xd70963-_0x20c70f);_0x400838[_0x4153f0][_0x150f10(0x28c)][_0x150f10(0x394)]='transform\x200.2s\x20ease',_0x400838[_0x4153f0][_0x150f10(0x28c)]['transform']=_0x150f10(0x302)+_0x50c20b+_0x150f10(0x25a),_0x4153f0++;}else for(let _0x1d780c=Math['min'](_0x4e4870,_0x2874d0);_0x1d780c<=Math[_0x150f10(0x361)](_0x4e4870,_0x2874d0);_0x1d780c++){if(_0x1d780c===_0x4e4870)continue;const _0x91b99a=_0x506f48*-_0x21c8a5*(_0x1d780c-_0x4e4870)/Math[_0x150f10(0x2a1)](_0x2874d0-_0x4e4870);_0x400838[_0x4153f0][_0x150f10(0x28c)][_0x150f10(0x394)]=_0x150f10(0x300),_0x400838[_0x4153f0]['style']['transform']=_0x150f10(0x224)+_0x91b99a+_0x150f10(0x33c),_0x4153f0++;}setTimeout(()=>{const _0x4ffa65=_0x150f10;if(_0x4e4870===_0x2874d0){const _0x263ff2=this[_0x4ffa65(0x1f7)][_0x4e4870],_0x5c378c=[..._0x263ff2];if(_0x20c70f<_0xd70963){for(let _0x23c55d=_0x20c70f;_0x23c55d<_0xd70963;_0x23c55d++)_0x263ff2[_0x23c55d]=_0x5c378c[_0x23c55d+0x1];}else{for(let _0x153d66=_0x20c70f;_0x153d66>_0xd70963;_0x153d66--)_0x263ff2[_0x153d66]=_0x5c378c[_0x153d66-0x1];}_0x263ff2[_0xd70963]=_0x5c378c[_0x20c70f];}else{const _0x323355=[];for(let _0x5d08bc=0x0;_0x5d08bc<this[_0x4ffa65(0x39b)];_0x5d08bc++)_0x323355[_0x5d08bc]={...this[_0x4ffa65(0x1f7)][_0x5d08bc][_0x20c70f]};if(_0x4e4870<_0x2874d0){for(let _0x362a2e=_0x4e4870;_0x362a2e<_0x2874d0;_0x362a2e++)this[_0x4ffa65(0x1f7)][_0x362a2e][_0x20c70f]=_0x323355[_0x362a2e+0x1];}else{for(let _0x2b6755=_0x4e4870;_0x2b6755>_0x2874d0;_0x2b6755--)this[_0x4ffa65(0x1f7)][_0x2b6755][_0x20c70f]=_0x323355[_0x2b6755-0x1];}this[_0x4ffa65(0x1f7)][_0x2874d0][_0xd70963]=_0x323355[_0x4e4870];}this[_0x4ffa65(0x1f3)]();const _0x2cc043=this[_0x4ffa65(0x341)](_0xd70963,_0x2874d0);_0x2cc043?this[_0x4ffa65(0x2b4)]='animating':(log(_0x4ffa65(0x387)),this[_0x4ffa65(0x306)]['badMove'][_0x4ffa65(0x333)](),_0x3cc854['style'][_0x4ffa65(0x394)]=_0x4ffa65(0x300),_0x3cc854[_0x4ffa65(0x28c)][_0x4ffa65(0x39c)]=_0x4ffa65(0x17b),_0x400838[_0x4ffa65(0x25f)](_0x255635=>{const _0x3c4bc8=_0x4ffa65;_0x255635[_0x3c4bc8(0x28c)][_0x3c4bc8(0x394)]='transform\x200.2s\x20ease',_0x255635[_0x3c4bc8(0x28c)][_0x3c4bc8(0x39c)]=_0x3c4bc8(0x17b);}),setTimeout(()=>{const _0x4479b5=_0x4ffa65;if(_0x4e4870===_0x2874d0){const _0x1e1daa=Math[_0x4479b5(0x36a)](_0x20c70f,_0xd70963);for(let _0x1e8cc5=0x0;_0x1e8cc5<_0xe7ef5a[_0x4479b5(0x2ec)];_0x1e8cc5++){this[_0x4479b5(0x1f7)][_0x4e4870][_0x1e1daa+_0x1e8cc5]={..._0xe7ef5a[_0x1e8cc5],'element':_0x400838[_0x1e8cc5]};}}else{const _0x4b7910=Math[_0x4479b5(0x36a)](_0x4e4870,_0x2874d0);for(let _0x56c59f=0x0;_0x56c59f<_0xe7ef5a[_0x4479b5(0x2ec)];_0x56c59f++){this[_0x4479b5(0x1f7)][_0x4b7910+_0x56c59f][_0x20c70f]={..._0xe7ef5a[_0x56c59f],'element':_0x400838[_0x56c59f]};}}this[_0x4479b5(0x1f3)](),this[_0x4479b5(0x2b4)]=_0x4479b5(0x16c);},0xc8));},0xc8);}[_0xe5e5(0x341)](_0x2987d9=null,_0x212d08=null){const _0xbb8002=_0xe5e5;console[_0xbb8002(0x32f)](_0xbb8002(0x3a7),this[_0xbb8002(0x2ee)]);if(this[_0xbb8002(0x2ee)])return console['log'](_0xbb8002(0x24e)),![];const _0x4b7b85=_0x2987d9!==null&&_0x212d08!==null;console[_0xbb8002(0x32f)]('Is\x20initial\x20move:\x20'+_0x4b7b85);const _0x1339f5=this[_0xbb8002(0x2f6)]();console[_0xbb8002(0x32f)](_0xbb8002(0x1c1)+_0x1339f5[_0xbb8002(0x2ec)]+_0xbb8002(0x283),_0x1339f5);let _0x394b96=0x1,_0x4329cd='';if(_0x4b7b85&&_0x1339f5[_0xbb8002(0x2ec)]>0x1){const _0x4e06c6=_0x1339f5['reduce']((_0xcd1630,_0x467d5b)=>_0xcd1630+_0x467d5b[_0xbb8002(0x176)],0x0);console[_0xbb8002(0x32f)](_0xbb8002(0x2b3)+_0x4e06c6);if(_0x4e06c6>=0x6&&_0x4e06c6<=0x8)_0x394b96=1.2,_0x4329cd='Multi-Match!\x20'+_0x4e06c6+_0xbb8002(0x305),this[_0xbb8002(0x306)][_0xbb8002(0x2a8)][_0xbb8002(0x333)]();else _0x4e06c6>=0x9&&(_0x394b96=0x3,_0x4329cd=_0xbb8002(0x2f2)+_0x4e06c6+_0xbb8002(0x2b8),this[_0xbb8002(0x306)][_0xbb8002(0x2a8)]['play']());}if(_0x1339f5[_0xbb8002(0x2ec)]>0x0){const _0x18a930=new Set();let _0x5f3927=0x0;const _0x418f10=this['currentTurn'],_0x599896=this[_0xbb8002(0x2e5)]===this['player1']?this[_0xbb8002(0x200)]:this[_0xbb8002(0x250)];try{_0x1339f5[_0xbb8002(0x25f)](_0x56c461=>{const _0x431743=_0xbb8002;console[_0x431743(0x32f)](_0x431743(0x170),_0x56c461),_0x56c461['coordinates'][_0x431743(0x25f)](_0x148b6e=>_0x18a930['add'](_0x148b6e));const _0x55dd45=this['handleMatch'](_0x56c461,_0x4b7b85);console[_0x431743(0x32f)](_0x431743(0x1f0)+_0x55dd45);if(this['gameOver']){console[_0x431743(0x32f)](_0x431743(0x1c8));return;}if(_0x55dd45>0x0)_0x5f3927+=_0x55dd45;});if(this[_0xbb8002(0x2ee)])return console[_0xbb8002(0x32f)]('Game\x20over\x20after\x20processing\x20matches,\x20exiting\x20resolveMatches'),!![];return console[_0xbb8002(0x32f)](_0xbb8002(0x205)+_0x5f3927+_0xbb8002(0x2ad),[..._0x18a930]),_0x5f3927>0x0&&!this[_0xbb8002(0x2ee)]&&setTimeout(()=>{const _0xd1408a=_0xbb8002;if(this[_0xd1408a(0x2ee)]){console[_0xd1408a(0x32f)](_0xd1408a(0x2cf));return;}console['log'](_0xd1408a(0x335),_0x599896[_0xd1408a(0x1e6)]),this[_0xd1408a(0x2b2)](_0x599896,_0x5f3927);},0x64),setTimeout(()=>{const _0x3c33a1=_0xbb8002;if(this['gameOver']){console['log'](_0x3c33a1(0x32d));return;}console[_0x3c33a1(0x32f)](_0x3c33a1(0x33f),[..._0x18a930]),_0x18a930[_0x3c33a1(0x25f)](_0x32422f=>{const _0x2a9556=_0x3c33a1,[_0x566049,_0xc39937]=_0x32422f[_0x2a9556(0x217)](',')[_0x2a9556(0x1b1)](Number);this[_0x2a9556(0x1f7)][_0xc39937][_0x566049]?.[_0x2a9556(0x1cd)]?this['board'][_0xc39937][_0x566049]['element'][_0x2a9556(0x1d4)][_0x2a9556(0x2fd)](_0x2a9556(0x27e)):console[_0x2a9556(0x354)](_0x2a9556(0x273)+_0x566049+','+_0xc39937+_0x2a9556(0x313));}),setTimeout(()=>{const _0x235662=_0x3c33a1;if(this[_0x235662(0x2ee)]){console['log'](_0x235662(0x2c9));return;}console['log'](_0x235662(0x1a3),[..._0x18a930]),_0x18a930[_0x235662(0x25f)](_0x5a4656=>{const _0x4a9e39=_0x235662,[_0x4f9a32,_0x21ed97]=_0x5a4656[_0x4a9e39(0x217)](',')[_0x4a9e39(0x1b1)](Number);this[_0x4a9e39(0x1f7)][_0x21ed97][_0x4f9a32]&&(this[_0x4a9e39(0x1f7)][_0x21ed97][_0x4f9a32][_0x4a9e39(0x296)]=null,this['board'][_0x21ed97][_0x4f9a32][_0x4a9e39(0x1cd)]=null);}),this[_0x235662(0x306)][_0x235662(0x38c)][_0x235662(0x333)](),console['log']('Cascading\x20tiles');if(_0x394b96>0x1&&this['roundStats'][_0x235662(0x2ec)]>0x0){const _0x413324=this[_0x235662(0x1ba)][this[_0x235662(0x1ba)]['length']-0x1],_0x2419b5=_0x413324[_0x235662(0x246)];_0x413324[_0x235662(0x246)]=Math[_0x235662(0x2d3)](_0x413324[_0x235662(0x246)]*_0x394b96),_0x4329cd&&(log(_0x4329cd),log(_0x235662(0x204)+_0x2419b5+'\x20to\x20'+_0x413324[_0x235662(0x246)]+_0x235662(0x2d0)));}this[_0x235662(0x21f)](()=>{const _0x23b0e5=_0x235662;if(this[_0x23b0e5(0x2ee)]){console[_0x23b0e5(0x32f)](_0x23b0e5(0x28f));return;}console['log'](_0x23b0e5(0x26b)),this[_0x23b0e5(0x27f)]();});},0x12c);},0xc8),!![];}catch(_0x420c73){return console[_0xbb8002(0x19c)]('Error\x20in\x20resolveMatches:',_0x420c73),this['gameState']=this[_0xbb8002(0x2e5)]===this['player1']?_0xbb8002(0x16c):_0xbb8002(0x37e),![];}}return console[_0xbb8002(0x32f)]('No\x20matches\x20found,\x20returning\x20false'),![];}[_0xe5e5(0x2f6)](){const _0x2b79a5=_0xe5e5;console[_0x2b79a5(0x32f)](_0x2b79a5(0x370));const _0x1c0cce=[];try{const _0xe9d392=[];for(let _0x35a718=0x0;_0x35a718<this[_0x2b79a5(0x39b)];_0x35a718++){let _0x168ee2=0x0;for(let _0x35f59a=0x0;_0x35f59a<=this[_0x2b79a5(0x216)];_0x35f59a++){const _0x12f6ce=_0x35f59a<this[_0x2b79a5(0x216)]?this['board'][_0x35a718][_0x35f59a]?.['type']:null;if(_0x12f6ce!==this[_0x2b79a5(0x1f7)][_0x35a718][_0x168ee2]?.['type']||_0x35f59a===this[_0x2b79a5(0x216)]){const _0x12783f=_0x35f59a-_0x168ee2;if(_0x12783f>=0x3){const _0x1aa1a6=new Set();for(let _0x2a04f8=_0x168ee2;_0x2a04f8<_0x35f59a;_0x2a04f8++){_0x1aa1a6['add'](_0x2a04f8+','+_0x35a718);}_0xe9d392[_0x2b79a5(0x2ab)]({'type':this[_0x2b79a5(0x1f7)][_0x35a718][_0x168ee2]['type'],'coordinates':_0x1aa1a6}),console[_0x2b79a5(0x32f)](_0x2b79a5(0x373)+_0x35a718+_0x2b79a5(0x28e)+_0x168ee2+'-'+(_0x35f59a-0x1)+':',[..._0x1aa1a6]);}_0x168ee2=_0x35f59a;}}}for(let _0x427941=0x0;_0x427941<this[_0x2b79a5(0x216)];_0x427941++){let _0x5a25cb=0x0;for(let _0x4d0ecf=0x0;_0x4d0ecf<=this['height'];_0x4d0ecf++){const _0xaa3ee2=_0x4d0ecf<this[_0x2b79a5(0x39b)]?this[_0x2b79a5(0x1f7)][_0x4d0ecf][_0x427941]?.['type']:null;if(_0xaa3ee2!==this[_0x2b79a5(0x1f7)][_0x5a25cb][_0x427941]?.[_0x2b79a5(0x296)]||_0x4d0ecf===this[_0x2b79a5(0x39b)]){const _0x5a448c=_0x4d0ecf-_0x5a25cb;if(_0x5a448c>=0x3){const _0x251913=new Set();for(let _0x5360ab=_0x5a25cb;_0x5360ab<_0x4d0ecf;_0x5360ab++){_0x251913['add'](_0x427941+','+_0x5360ab);}_0xe9d392['push']({'type':this['board'][_0x5a25cb][_0x427941][_0x2b79a5(0x296)],'coordinates':_0x251913}),console[_0x2b79a5(0x32f)](_0x2b79a5(0x238)+_0x427941+_0x2b79a5(0x214)+_0x5a25cb+'-'+(_0x4d0ecf-0x1)+':',[..._0x251913]);}_0x5a25cb=_0x4d0ecf;}}}const _0x5e83b6=[],_0x5cee63=new Set();return _0xe9d392[_0x2b79a5(0x25f)]((_0x5bee91,_0x388ab3)=>{const _0x118bc4=_0x2b79a5;if(_0x5cee63[_0x118bc4(0x353)](_0x388ab3))return;const _0xbecc0c={'type':_0x5bee91[_0x118bc4(0x296)],'coordinates':new Set(_0x5bee91[_0x118bc4(0x39d)])};_0x5cee63[_0x118bc4(0x2fd)](_0x388ab3);for(let _0x377122=0x0;_0x377122<_0xe9d392[_0x118bc4(0x2ec)];_0x377122++){if(_0x5cee63[_0x118bc4(0x353)](_0x377122))continue;const _0x2f836e=_0xe9d392[_0x377122];if(_0x2f836e[_0x118bc4(0x296)]===_0xbecc0c[_0x118bc4(0x296)]){const _0x277fb2=[..._0x2f836e[_0x118bc4(0x39d)]][_0x118bc4(0x37a)](_0xa84909=>_0xbecc0c[_0x118bc4(0x39d)][_0x118bc4(0x353)](_0xa84909));_0x277fb2&&(_0x2f836e['coordinates']['forEach'](_0x3a2a25=>_0xbecc0c[_0x118bc4(0x39d)]['add'](_0x3a2a25)),_0x5cee63['add'](_0x377122));}}_0x5e83b6['push']({'type':_0xbecc0c[_0x118bc4(0x296)],'coordinates':_0xbecc0c[_0x118bc4(0x39d)],'totalTiles':_0xbecc0c[_0x118bc4(0x39d)][_0x118bc4(0x34b)]});}),_0x1c0cce[_0x2b79a5(0x2ab)](..._0x5e83b6),console['log'](_0x2b79a5(0x2f1),_0x1c0cce),_0x1c0cce;}catch(_0x111980){return console[_0x2b79a5(0x19c)](_0x2b79a5(0x210),_0x111980),[];}}['handleMatch'](_0x1c5895,_0x2bcee=!![]){const _0x80fbb5=_0xe5e5;console[_0x80fbb5(0x32f)]('handleMatch\x20started,\x20match:',_0x1c5895,'isInitialMove:',_0x2bcee);const _0x1bb40c=this[_0x80fbb5(0x2e5)],_0x3c03fe=this[_0x80fbb5(0x2e5)]===this[_0x80fbb5(0x250)]?this[_0x80fbb5(0x200)]:this[_0x80fbb5(0x250)],_0x3743cd=_0x1c5895[_0x80fbb5(0x296)],_0x1175ce=_0x1c5895['totalTiles'];let _0x17e6df=0x0,_0x5102d1=0x0;console['log'](_0x3c03fe[_0x80fbb5(0x1e6)]+'\x20health\x20before\x20match:\x20'+_0x3c03fe[_0x80fbb5(0x2b5)]);_0x1175ce==0x4&&(this[_0x80fbb5(0x306)][_0x80fbb5(0x374)][_0x80fbb5(0x333)](),log(_0x1bb40c[_0x80fbb5(0x1e6)]+_0x80fbb5(0x1d0)+_0x1175ce+_0x80fbb5(0x1df)));_0x1175ce>=0x5&&(this[_0x80fbb5(0x306)][_0x80fbb5(0x30f)][_0x80fbb5(0x333)](),log(_0x1bb40c[_0x80fbb5(0x1e6)]+_0x80fbb5(0x1d0)+_0x1175ce+_0x80fbb5(0x1df)));if(_0x3743cd===_0x80fbb5(0x219)||_0x3743cd==='second-attack'||_0x3743cd===_0x80fbb5(0x334)||_0x3743cd==='last-stand'){_0x17e6df=Math[_0x80fbb5(0x2d3)](_0x1bb40c['strength']*(_0x1175ce===0x3?0x2:_0x1175ce===0x4?0x3:0x4));let _0x5a973d=0x1;if(_0x1175ce===0x4)_0x5a973d=1.5;else _0x1175ce>=0x5&&(_0x5a973d=0x2);_0x17e6df=Math[_0x80fbb5(0x2d3)](_0x17e6df*_0x5a973d),console[_0x80fbb5(0x32f)]('Base\x20damage:\x20'+_0x1bb40c['strength']*(_0x1175ce===0x3?0x2:_0x1175ce===0x4?0x3:0x4)+_0x80fbb5(0x193)+_0x5a973d+_0x80fbb5(0x347)+_0x17e6df);_0x3743cd===_0x80fbb5(0x334)&&(_0x17e6df=Math[_0x80fbb5(0x2d3)](_0x17e6df*1.2),console[_0x80fbb5(0x32f)](_0x80fbb5(0x243)+_0x17e6df));_0x1bb40c[_0x80fbb5(0x389)]&&(_0x17e6df+=_0x1bb40c['boostValue']||0xa,_0x1bb40c['boostActive']=![],log(_0x1bb40c['name']+_0x80fbb5(0x33e)),console[_0x80fbb5(0x32f)]('Boost\x20applied,\x20damage:\x20'+_0x17e6df));_0x5102d1=_0x17e6df;const _0x447226=_0x3c03fe['tactics']*0xa;Math[_0x80fbb5(0x21c)]()*0x64<_0x447226&&(_0x17e6df=Math['floor'](_0x17e6df/0x2),log(_0x3c03fe[_0x80fbb5(0x1e6)]+_0x80fbb5(0x314)+_0x17e6df+_0x80fbb5(0x2ff)),console[_0x80fbb5(0x32f)](_0x80fbb5(0x197)+_0x17e6df));let _0x228d20=0x0;_0x3c03fe[_0x80fbb5(0x28b)]&&(_0x228d20=Math[_0x80fbb5(0x36a)](_0x17e6df,0x5),_0x17e6df=Math[_0x80fbb5(0x361)](0x0,_0x17e6df-_0x228d20),_0x3c03fe[_0x80fbb5(0x28b)]=![],console[_0x80fbb5(0x32f)](_0x80fbb5(0x360)+_0x228d20+_0x80fbb5(0x288)+_0x17e6df));const _0x191339=_0x3743cd===_0x80fbb5(0x219)?_0x80fbb5(0x29e):_0x3743cd===_0x80fbb5(0x2af)?_0x80fbb5(0x20a):'Shadow\x20Strike';let _0x2b7b28;if(_0x228d20>0x0)_0x2b7b28=_0x1bb40c[_0x80fbb5(0x1e6)]+_0x80fbb5(0x1c5)+_0x191339+_0x80fbb5(0x27d)+_0x3c03fe[_0x80fbb5(0x1e6)]+_0x80fbb5(0x199)+_0x5102d1+_0x80fbb5(0x2cb)+_0x3c03fe[_0x80fbb5(0x1e6)]+_0x80fbb5(0x16d)+_0x228d20+_0x80fbb5(0x2d5)+_0x17e6df+_0x80fbb5(0x2ff);else _0x3743cd==='last-stand'?_0x2b7b28=_0x1bb40c[_0x80fbb5(0x1e6)]+_0x80fbb5(0x281)+_0x17e6df+_0x80fbb5(0x1ad)+_0x3c03fe['name']+'\x20and\x20preparing\x20to\x20mitigate\x205\x20damage\x20on\x20the\x20next\x20attack!':_0x2b7b28=_0x1bb40c[_0x80fbb5(0x1e6)]+_0x80fbb5(0x1c5)+_0x191339+_0x80fbb5(0x27d)+_0x3c03fe[_0x80fbb5(0x1e6)]+_0x80fbb5(0x199)+_0x17e6df+_0x80fbb5(0x2ff);_0x2bcee?log(_0x2b7b28):log('Cascade:\x20'+_0x2b7b28),_0x3c03fe[_0x80fbb5(0x2b5)]=Math[_0x80fbb5(0x361)](0x0,_0x3c03fe[_0x80fbb5(0x2b5)]-_0x17e6df),console[_0x80fbb5(0x32f)](_0x3c03fe[_0x80fbb5(0x1e6)]+'\x20health\x20after\x20damage:\x20'+_0x3c03fe[_0x80fbb5(0x2b5)]),this[_0x80fbb5(0x377)](_0x3c03fe),console['log']('Calling\x20checkGameOver\x20from\x20handleMatch'),this[_0x80fbb5(0x1ca)](),!this[_0x80fbb5(0x2ee)]&&(console[_0x80fbb5(0x32f)]('Game\x20not\x20over,\x20animating\x20attack'),this['animateAttack'](_0x1bb40c,_0x17e6df,_0x3743cd));}else _0x3743cd==='power-up'&&(this['usePowerup'](_0x1bb40c,_0x3c03fe,_0x1175ce),!this[_0x80fbb5(0x2ee)]&&(console[_0x80fbb5(0x32f)](_0x80fbb5(0x355)),this['animatePowerup'](_0x1bb40c)));(!this[_0x80fbb5(0x1ba)][this[_0x80fbb5(0x1ba)][_0x80fbb5(0x2ec)]-0x1]||this['roundStats'][this[_0x80fbb5(0x1ba)][_0x80fbb5(0x2ec)]-0x1]['completed'])&&this['roundStats'][_0x80fbb5(0x2ab)]({'points':0x0,'matches':0x0,'healthPercentage':0x0,'completed':![]});const _0x452e0b=this[_0x80fbb5(0x1ba)][this['roundStats'][_0x80fbb5(0x2ec)]-0x1];return _0x452e0b[_0x80fbb5(0x246)]+=_0x17e6df,_0x452e0b[_0x80fbb5(0x318)]+=0x1,console['log'](_0x80fbb5(0x1e3)+_0x17e6df),_0x17e6df;}[_0xe5e5(0x21f)](_0x3e9022){const _0x3f9aaf=_0xe5e5;if(this['gameOver']){console['log'](_0x3f9aaf(0x289));return;}const _0x56c60f=this[_0x3f9aaf(0x1b5)](),_0x9dbe95=_0x3f9aaf(0x35e);for(let _0x165dd3=0x0;_0x165dd3<this[_0x3f9aaf(0x216)];_0x165dd3++){for(let _0x188dd9=0x0;_0x188dd9<this[_0x3f9aaf(0x39b)];_0x188dd9++){const _0x5c0fdb=this['board'][_0x188dd9][_0x165dd3];if(_0x5c0fdb[_0x3f9aaf(0x1cd)]&&_0x5c0fdb[_0x3f9aaf(0x1cd)][_0x3f9aaf(0x28c)][_0x3f9aaf(0x39c)]==='translate(0px,\x200px)'){const _0xb4933=this['countEmptyBelow'](_0x165dd3,_0x188dd9);_0xb4933>0x0&&(_0x5c0fdb[_0x3f9aaf(0x1cd)][_0x3f9aaf(0x1d4)][_0x3f9aaf(0x2fd)](_0x9dbe95),_0x5c0fdb[_0x3f9aaf(0x1cd)][_0x3f9aaf(0x28c)][_0x3f9aaf(0x39c)]=_0x3f9aaf(0x224)+_0xb4933*this[_0x3f9aaf(0x173)]+_0x3f9aaf(0x33c));}}}this['renderBoard'](),_0x56c60f?setTimeout(()=>{const _0x51ebde=_0x3f9aaf;if(this[_0x51ebde(0x2ee)]){console[_0x51ebde(0x32f)](_0x51ebde(0x263));return;}this[_0x51ebde(0x306)][_0x51ebde(0x2d8)][_0x51ebde(0x333)]();const _0x1eaf56=this['resolveMatches'](),_0x4c3531=document[_0x51ebde(0x21d)]('.'+_0x9dbe95);_0x4c3531[_0x51ebde(0x25f)](_0x5a0908=>{const _0x3c0b3b=_0x51ebde;_0x5a0908[_0x3c0b3b(0x1d4)][_0x3c0b3b(0x229)](_0x9dbe95),_0x5a0908[_0x3c0b3b(0x28c)][_0x3c0b3b(0x39c)]='translate(0,\x200)';}),!_0x1eaf56&&_0x3e9022();},0x12c):_0x3e9022();}[_0xe5e5(0x1b5)](){const _0x23b37e=_0xe5e5;let _0x2c22f4=![];for(let _0x26745e=0x0;_0x26745e<this[_0x23b37e(0x216)];_0x26745e++){let _0x57d4cf=0x0;for(let _0x3bf353=this[_0x23b37e(0x39b)]-0x1;_0x3bf353>=0x0;_0x3bf353--){if(!this[_0x23b37e(0x1f7)][_0x3bf353][_0x26745e]['type'])_0x57d4cf++;else _0x57d4cf>0x0&&(this['board'][_0x3bf353+_0x57d4cf][_0x26745e]=this[_0x23b37e(0x1f7)][_0x3bf353][_0x26745e],this['board'][_0x3bf353][_0x26745e]={'type':null,'element':null},_0x2c22f4=!![]);}for(let _0x5b63c0=0x0;_0x5b63c0<_0x57d4cf;_0x5b63c0++){this[_0x23b37e(0x1f7)][_0x5b63c0][_0x26745e]=this['createRandomTile'](),_0x2c22f4=!![];}}return _0x2c22f4;}[_0xe5e5(0x362)](_0x4ac64d,_0x30791c){const _0xdb1a2b=_0xe5e5;let _0x314fcb=0x0;for(let _0x12a744=_0x30791c+0x1;_0x12a744<this[_0xdb1a2b(0x39b)];_0x12a744++){if(!this[_0xdb1a2b(0x1f7)][_0x12a744][_0x4ac64d][_0xdb1a2b(0x296)])_0x314fcb++;else break;}return _0x314fcb;}[_0xe5e5(0x2b7)](_0x58ab17,_0xd20793,_0xb06db2){const _0x2f9e15=_0xe5e5,_0x504d1b=0x1-_0xd20793['tactics']*0.05;let _0xd67479,_0x51550c,_0x20153b,_0x8e99a9=0x1,_0x1e6dd7='';if(_0xb06db2===0x4)_0x8e99a9=1.5,_0x1e6dd7='\x20(50%\x20bonus\x20for\x20match-4)';else _0xb06db2>=0x5&&(_0x8e99a9=0x2,_0x1e6dd7=_0x2f9e15(0x390));if(_0x58ab17[_0x2f9e15(0x37b)]===_0x2f9e15(0x17c))_0x51550c=0xa*_0x8e99a9,_0xd67479=Math[_0x2f9e15(0x33a)](_0x51550c*_0x504d1b),_0x20153b=_0x51550c-_0xd67479,_0x58ab17[_0x2f9e15(0x2b5)]=Math['min'](_0x58ab17['maxHealth'],_0x58ab17[_0x2f9e15(0x2b5)]+_0xd67479),log(_0x58ab17[_0x2f9e15(0x1e6)]+_0x2f9e15(0x2c8)+_0xd67479+_0x2f9e15(0x331)+_0x1e6dd7+(_0xd20793[_0x2f9e15(0x317)]>0x0?'\x20(originally\x20'+_0x51550c+',\x20reduced\x20by\x20'+_0x20153b+_0x2f9e15(0x1ea)+_0xd20793[_0x2f9e15(0x1e6)]+_0x2f9e15(0x385):'')+'!');else{if(_0x58ab17[_0x2f9e15(0x37b)]==='Boost\x20Attack')_0x51550c=0xa*_0x8e99a9,_0xd67479=Math[_0x2f9e15(0x33a)](_0x51550c*_0x504d1b),_0x20153b=_0x51550c-_0xd67479,_0x58ab17['boostActive']=!![],_0x58ab17[_0x2f9e15(0x316)]=_0xd67479,log(_0x58ab17[_0x2f9e15(0x1e6)]+_0x2f9e15(0x339)+_0xd67479+_0x2f9e15(0x190)+_0x1e6dd7+(_0xd20793[_0x2f9e15(0x317)]>0x0?_0x2f9e15(0x33b)+_0x51550c+_0x2f9e15(0x391)+_0x20153b+'\x20due\x20to\x20'+_0xd20793[_0x2f9e15(0x1e6)]+_0x2f9e15(0x385):'')+'!');else{if(_0x58ab17[_0x2f9e15(0x37b)]===_0x2f9e15(0x2d4))_0x51550c=0x7*_0x8e99a9,_0xd67479=Math[_0x2f9e15(0x33a)](_0x51550c*_0x504d1b),_0x20153b=_0x51550c-_0xd67479,_0x58ab17[_0x2f9e15(0x2b5)]=Math[_0x2f9e15(0x36a)](_0x58ab17[_0x2f9e15(0x1a0)],_0x58ab17[_0x2f9e15(0x2b5)]+_0xd67479),log(_0x58ab17[_0x2f9e15(0x1e6)]+_0x2f9e15(0x308)+_0xd67479+'\x20HP'+_0x1e6dd7+(_0xd20793[_0x2f9e15(0x317)]>0x0?_0x2f9e15(0x33b)+_0x51550c+_0x2f9e15(0x391)+_0x20153b+_0x2f9e15(0x1ea)+_0xd20793[_0x2f9e15(0x1e6)]+_0x2f9e15(0x385):'')+'!');else _0x58ab17['powerup']===_0x2f9e15(0x357)&&(_0x51550c=0x5*_0x8e99a9,_0xd67479=Math[_0x2f9e15(0x33a)](_0x51550c*_0x504d1b),_0x20153b=_0x51550c-_0xd67479,_0x58ab17[_0x2f9e15(0x2b5)]=Math['min'](_0x58ab17[_0x2f9e15(0x1a0)],_0x58ab17['health']+_0xd67479),log(_0x58ab17['name']+_0x2f9e15(0x175)+_0xd67479+_0x2f9e15(0x331)+_0x1e6dd7+(_0xd20793[_0x2f9e15(0x317)]>0x0?_0x2f9e15(0x33b)+_0x51550c+_0x2f9e15(0x391)+_0x20153b+_0x2f9e15(0x1ea)+_0xd20793[_0x2f9e15(0x1e6)]+'\x27s\x20tactics)':'')+'!'));}}this['updateHealth'](_0x58ab17);}[_0xe5e5(0x377)](_0x13fae0){const _0x52277a=_0xe5e5,_0x42740e=_0x13fae0===this[_0x52277a(0x250)]?p1Health:p2Health,_0x441850=_0x13fae0===this['player1']?p1Hp:p2Hp,_0xddcfe0=_0x13fae0[_0x52277a(0x2b5)]/_0x13fae0[_0x52277a(0x1a0)]*0x64;_0x42740e['style'][_0x52277a(0x216)]=_0xddcfe0+'%';let _0x2fe5ad;if(_0xddcfe0>0x4b)_0x2fe5ad=_0x52277a(0x2c5);else{if(_0xddcfe0>0x32)_0x2fe5ad=_0x52277a(0x393);else _0xddcfe0>0x19?_0x2fe5ad=_0x52277a(0x1be):_0x2fe5ad='#F44336';}_0x42740e['style'][_0x52277a(0x22f)]=_0x2fe5ad,_0x441850[_0x52277a(0x31d)]=_0x13fae0[_0x52277a(0x2b5)]+'/'+_0x13fae0['maxHealth'];}[_0xe5e5(0x27f)](){const _0x2e4283=_0xe5e5;if(this['gameState']==='gameOver'||this[_0x2e4283(0x2ee)]){console[_0x2e4283(0x32f)](_0x2e4283(0x28f));return;}this[_0x2e4283(0x2e5)]=this[_0x2e4283(0x2e5)]===this[_0x2e4283(0x250)]?this['player2']:this['player1'],this['gameState']=this[_0x2e4283(0x2e5)]===this[_0x2e4283(0x250)]?_0x2e4283(0x16c):_0x2e4283(0x37e),turnIndicator[_0x2e4283(0x31d)]=_0x2e4283(0x245)+this['currentLevel']+_0x2e4283(0x22c)+(this[_0x2e4283(0x2e5)]===this['player1']?_0x2e4283(0x18c):_0x2e4283(0x280))+_0x2e4283(0x22e),log(_0x2e4283(0x18d)+(this['currentTurn']===this[_0x2e4283(0x250)]?'Player':_0x2e4283(0x280))),this[_0x2e4283(0x2e5)]===this[_0x2e4283(0x200)]&&setTimeout(()=>this[_0x2e4283(0x37e)](),0x3e8);}[_0xe5e5(0x37e)](){const _0x3c8144=_0xe5e5;if(this['gameState']!==_0x3c8144(0x37e)||this[_0x3c8144(0x2e5)]!==this[_0x3c8144(0x200)])return;this[_0x3c8144(0x2b4)]=_0x3c8144(0x2a7);const _0x3f0e54=this[_0x3c8144(0x32e)]();_0x3f0e54?(log(this[_0x3c8144(0x200)][_0x3c8144(0x1e6)]+_0x3c8144(0x1cb)+_0x3f0e54['x1']+',\x20'+_0x3f0e54['y1']+_0x3c8144(0x2e4)+_0x3f0e54['x2']+',\x20'+_0x3f0e54['y2']+')'),this[_0x3c8144(0x26a)](_0x3f0e54['x1'],_0x3f0e54['y1'],_0x3f0e54['x2'],_0x3f0e54['y2'])):(log(this[_0x3c8144(0x200)][_0x3c8144(0x1e6)]+'\x20passes...'),this['endTurn']());}[_0xe5e5(0x32e)](){const _0x316796=_0xe5e5;for(let _0x437b19=0x0;_0x437b19<this[_0x316796(0x39b)];_0x437b19++){for(let _0x1d4b11=0x0;_0x1d4b11<this['width'];_0x1d4b11++){if(_0x1d4b11<this['width']-0x1&&this[_0x316796(0x2db)](_0x1d4b11,_0x437b19,_0x1d4b11+0x1,_0x437b19))return{'x1':_0x1d4b11,'y1':_0x437b19,'x2':_0x1d4b11+0x1,'y2':_0x437b19};if(_0x437b19<this[_0x316796(0x39b)]-0x1&&this[_0x316796(0x2db)](_0x1d4b11,_0x437b19,_0x1d4b11,_0x437b19+0x1))return{'x1':_0x1d4b11,'y1':_0x437b19,'x2':_0x1d4b11,'y2':_0x437b19+0x1};}}return null;}['canMakeMatch'](_0x1ff801,_0x4e3a66,_0x36bb09,_0x9300bc){const _0x28621a=_0xe5e5,_0x3aabe1={...this[_0x28621a(0x1f7)][_0x4e3a66][_0x1ff801]},_0x16aff9={...this[_0x28621a(0x1f7)][_0x9300bc][_0x36bb09]};this['board'][_0x4e3a66][_0x1ff801]=_0x16aff9,this['board'][_0x9300bc][_0x36bb09]=_0x3aabe1;const _0xe3eb50=this[_0x28621a(0x2f6)]()[_0x28621a(0x2ec)]>0x0;return this[_0x28621a(0x1f7)][_0x4e3a66][_0x1ff801]=_0x3aabe1,this[_0x28621a(0x1f7)][_0x9300bc][_0x36bb09]=_0x16aff9,_0xe3eb50;}async[_0xe5e5(0x1ca)](){const _0x449b60=_0xe5e5;if(this['gameOver']||this['isCheckingGameOver']){console['log'](_0x449b60(0x2f8)+this[_0x449b60(0x2ee)]+_0x449b60(0x1ab)+this['isCheckingGameOver']+_0x449b60(0x16f)+this['currentLevel']);return;}this['isCheckingGameOver']=!![],console[_0x449b60(0x32f)]('checkGameOver\x20started:\x20currentLevel='+this[_0x449b60(0x227)]+_0x449b60(0x379)+this[_0x449b60(0x250)]['health']+',\x20player2.health='+this[_0x449b60(0x200)][_0x449b60(0x2b5)]);const _0x2b944f=document['getElementById'](_0x449b60(0x322));if(this[_0x449b60(0x250)][_0x449b60(0x2b5)]<=0x0){console['log']('Player\x201\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(loss)'),this[_0x449b60(0x2ee)]=!![],this[_0x449b60(0x2b4)]='gameOver',gameOver['textContent']='You\x20Lose!',turnIndicator[_0x449b60(0x31d)]=_0x449b60(0x1e9),log(this['player2'][_0x449b60(0x1e6)]+_0x449b60(0x39f)+this[_0x449b60(0x250)]['name']+'!'),_0x2b944f[_0x449b60(0x31d)]='TRY\x20AGAIN',document[_0x449b60(0x192)](_0x449b60(0x202))[_0x449b60(0x28c)][_0x449b60(0x1a2)]='block';try{this['sounds'][_0x449b60(0x34d)][_0x449b60(0x333)]();}catch(_0x27804f){console['error'](_0x449b60(0x1d1),_0x27804f);}}else{if(this['player2'][_0x449b60(0x2b5)]<=0x0){console['log']('Player\x202\x20health\x20<=\x200,\x20triggering\x20game\x20over\x20(win)'),this[_0x449b60(0x2ee)]=!![],this[_0x449b60(0x2b4)]=_0x449b60(0x2ee),gameOver['textContent']=_0x449b60(0x371),turnIndicator[_0x449b60(0x31d)]=_0x449b60(0x1e9),_0x2b944f[_0x449b60(0x31d)]=this[_0x449b60(0x227)]===opponentsConfig[_0x449b60(0x2ec)]?_0x449b60(0x2f0):_0x449b60(0x366),document[_0x449b60(0x192)](_0x449b60(0x202))[_0x449b60(0x28c)][_0x449b60(0x1a2)]='block';if(this[_0x449b60(0x2e5)]===this[_0x449b60(0x250)]){const _0x1da832=this[_0x449b60(0x1ba)][this['roundStats']['length']-0x1];if(_0x1da832&&!_0x1da832['completed']){_0x1da832['healthPercentage']=this['player1'][_0x449b60(0x2b5)]/this[_0x449b60(0x250)][_0x449b60(0x1a0)]*0x64,_0x1da832[_0x449b60(0x223)]=!![];const _0x4911f1=_0x1da832[_0x449b60(0x318)]>0x0?_0x1da832[_0x449b60(0x246)]/_0x1da832[_0x449b60(0x318)]/0x64*(_0x1da832[_0x449b60(0x368)]+0x14)*(0x1+this[_0x449b60(0x227)]/0x38):0x0;log(_0x449b60(0x2e3)+_0x1da832[_0x449b60(0x246)]+_0x449b60(0x36b)+_0x1da832['matches']+_0x449b60(0x29f)+_0x1da832[_0x449b60(0x368)][_0x449b60(0x23d)](0x2)+_0x449b60(0x2f4)+this[_0x449b60(0x227)]),log(_0x449b60(0x307)+_0x1da832[_0x449b60(0x246)]+'\x20/\x20'+_0x1da832[_0x449b60(0x318)]+_0x449b60(0x1fc)+_0x1da832[_0x449b60(0x368)]+_0x449b60(0x2c3)+this[_0x449b60(0x227)]+_0x449b60(0x365)+_0x4911f1),this[_0x449b60(0x35f)]+=_0x4911f1,log(_0x449b60(0x301)+_0x1da832[_0x449b60(0x246)]+_0x449b60(0x1da)+_0x1da832[_0x449b60(0x318)]+_0x449b60(0x3a6)+_0x1da832['healthPercentage']['toFixed'](0x2)+'%'),log('Round\x20Score:\x20'+_0x4911f1+_0x449b60(0x315)+this[_0x449b60(0x35f)]);}}await this['saveScoreToDatabase'](this[_0x449b60(0x227)]);this['currentLevel']===opponentsConfig[_0x449b60(0x2ec)]?(this[_0x449b60(0x306)][_0x449b60(0x386)]['play'](),log('Final\x20level\x20completed!\x20Final\x20score:\x20'+this[_0x449b60(0x35f)]),this[_0x449b60(0x35f)]=0x0,await this[_0x449b60(0x298)](),log(_0x449b60(0x1bb))):(this['currentLevel']+=0x1,await this['saveProgress'](),console[_0x449b60(0x32f)](_0x449b60(0x2c2)+this['currentLevel']),this['sounds'][_0x449b60(0x195)]['play']());const _0x199af6=themes[_0x449b60(0x342)](_0x26b356=>_0x26b356[_0x449b60(0x1f4)])[_0x449b60(0x1c4)](_0x24fec3=>_0x24fec3[_0x449b60(0x23e)]===this[_0x449b60(0x24c)]),_0x3c47b7=_0x199af6?.[_0x449b60(0x34a)]||_0x449b60(0x36c),_0xd3198a=this[_0x449b60(0x1e1)]+_0x449b60(0x1c7)+this['player2'][_0x449b60(0x1e6)][_0x449b60(0x30b)]()[_0x449b60(0x1cc)](/ /g,'-')+'.'+_0x3c47b7,_0x178012=document['getElementById']('p2-image'),_0xbcfd4e=_0x178012[_0x449b60(0x319)];if(this[_0x449b60(0x200)]['mediaType']===_0x449b60(0x1b2)){if(_0x178012['tagName']!==_0x449b60(0x2d2)){const _0x51f0ba=document[_0x449b60(0x232)](_0x449b60(0x1b2));_0x51f0ba['id']='p2-image',_0x51f0ba[_0x449b60(0x248)]=_0xd3198a,_0x51f0ba[_0x449b60(0x1f6)]=!![],_0x51f0ba['loop']=!![],_0x51f0ba[_0x449b60(0x1c2)]=!![],_0x51f0ba[_0x449b60(0x1d3)]=this['player2'][_0x449b60(0x1e6)],_0xbcfd4e[_0x449b60(0x31f)](_0x51f0ba,_0x178012);}else _0x178012[_0x449b60(0x248)]=_0xd3198a;}else{if(_0x178012[_0x449b60(0x180)]!==_0x449b60(0x3a4)){const _0x271dcf=document['createElement'](_0x449b60(0x178));_0x271dcf['id']=_0x449b60(0x1a8),_0x271dcf[_0x449b60(0x248)]=_0xd3198a,_0x271dcf[_0x449b60(0x1d3)]=this[_0x449b60(0x200)][_0x449b60(0x1e6)],_0xbcfd4e[_0x449b60(0x31f)](_0x271dcf,_0x178012);}else _0x178012[_0x449b60(0x248)]=_0xd3198a;}const _0x90b39=document['getElementById'](_0x449b60(0x1a8));_0x90b39[_0x449b60(0x28c)][_0x449b60(0x1a2)]=_0x449b60(0x20c),_0x90b39[_0x449b60(0x1d4)][_0x449b60(0x2fd)]('loser'),p1Image['classList'][_0x449b60(0x2fd)](_0x449b60(0x228)),this['renderBoard']();}}this[_0x449b60(0x321)]=![],console['log'](_0x449b60(0x38a)+this[_0x449b60(0x227)]+',\x20gameOver='+this[_0x449b60(0x2ee)]);}async[_0xe5e5(0x330)](_0x3e7039){const _0x33f631=_0xe5e5,_0x20b730={'level':_0x3e7039,'score':this[_0x33f631(0x35f)]};console['log'](_0x33f631(0x19a)+_0x20b730[_0x33f631(0x346)]+',\x20score='+_0x20b730['score']);try{const _0x2d558d=await fetch(_0x33f631(0x1d5),{'method':_0x33f631(0x1a5),'headers':{'Content-Type':_0x33f631(0x2c0)},'body':JSON['stringify'](_0x20b730)});if(!_0x2d558d['ok'])throw new Error(_0x33f631(0x2a6)+_0x2d558d[_0x33f631(0x2c7)]);const _0x2d8d98=await _0x2d558d['json']();console[_0x33f631(0x32f)](_0x33f631(0x24a),_0x2d8d98),log('Level\x20'+_0x2d8d98[_0x33f631(0x346)]+_0x33f631(0x251)+_0x2d8d98[_0x33f631(0x37f)][_0x33f631(0x23d)](0x2)),_0x2d8d98[_0x33f631(0x2c7)]===_0x33f631(0x332)?log(_0x33f631(0x240)+_0x2d8d98[_0x33f631(0x346)]+_0x33f631(0x213)+_0x2d8d98[_0x33f631(0x37f)][_0x33f631(0x23d)](0x2)+_0x33f631(0x396)+_0x2d8d98[_0x33f631(0x1bc)]):log(_0x33f631(0x25d)+_0x2d8d98[_0x33f631(0x194)]);}catch(_0x326456){console[_0x33f631(0x19c)](_0x33f631(0x24b),_0x326456),log(_0x33f631(0x18f)+_0x326456[_0x33f631(0x194)]);}}[_0xe5e5(0x272)](_0x2d28b5,_0x2e78fd,_0x4ca2d6,_0x346b24){const _0x3dd084=_0xe5e5,_0x4ca616=_0x2d28b5[_0x3dd084(0x28c)][_0x3dd084(0x39c)]||'',_0x6f17a=_0x4ca616[_0x3dd084(0x2e9)]('scaleX')?_0x4ca616[_0x3dd084(0x38c)](/scaleX\([^)]+\)/)[0x0]:'';_0x2d28b5[_0x3dd084(0x28c)]['transition']='transform\x20'+_0x346b24/0x2/0x3e8+_0x3dd084(0x3a3),_0x2d28b5[_0x3dd084(0x28c)][_0x3dd084(0x39c)]='translateX('+_0x2e78fd+_0x3dd084(0x282)+_0x6f17a,_0x2d28b5[_0x3dd084(0x1d4)]['add'](_0x4ca2d6),setTimeout(()=>{const _0x152a14=_0x3dd084;_0x2d28b5[_0x152a14(0x28c)][_0x152a14(0x39c)]=_0x6f17a,setTimeout(()=>{const _0x2ecb93=_0x152a14;_0x2d28b5[_0x2ecb93(0x1d4)][_0x2ecb93(0x229)](_0x4ca2d6);},_0x346b24/0x2);},_0x346b24/0x2);}[_0xe5e5(0x234)](_0x40ef50,_0x48f042,_0xd12241){const _0x185be7=_0xe5e5,_0x3e95cf=_0x40ef50===this['player1']?p1Image:p2Image,_0x2f05c7=_0x40ef50===this[_0x185be7(0x250)]?0x1:-0x1,_0x3f65c2=Math[_0x185be7(0x36a)](0xa,0x2+_0x48f042*0.4),_0x44705c=_0x2f05c7*_0x3f65c2,_0x3a1b8a=_0x185be7(0x1d6)+_0xd12241;this[_0x185be7(0x272)](_0x3e95cf,_0x44705c,_0x3a1b8a,0xc8);}[_0xe5e5(0x284)](_0x2df515){const _0x5d9e95=_0xe5e5,_0x327503=_0x2df515===this[_0x5d9e95(0x250)]?p1Image:p2Image;this[_0x5d9e95(0x272)](_0x327503,0x0,'glow-power-up',0xc8);}['animateRecoil'](_0x261adf,_0x3cb172){const _0x4f8811=_0xe5e5,_0x1e3b40=_0x261adf===this[_0x4f8811(0x250)]?p1Image:p2Image,_0x3a4c20=_0x261adf===this[_0x4f8811(0x250)]?-0x1:0x1,_0x2ec09a=Math[_0x4f8811(0x36a)](0xa,0x2+_0x3cb172*0.4),_0x2d667f=_0x3a4c20*_0x2ec09a;this[_0x4f8811(0x272)](_0x1e3b40,_0x2d667f,_0x4f8811(0x2c1),0xc8);}}function randomChoice(_0x341dc){const _0x4fcb0b=_0xe5e5;return _0x341dc[Math[_0x4fcb0b(0x33a)](Math[_0x4fcb0b(0x21c)]()*_0x341dc[_0x4fcb0b(0x2ec)])];}function log(_0x5921f5){const _0x57da22=_0xe5e5,_0x91ab95=document[_0x57da22(0x192)](_0x57da22(0x25e)),_0x5976a2=document[_0x57da22(0x232)]('li');_0x5976a2[_0x57da22(0x31d)]=_0x5921f5,_0x91ab95[_0x57da22(0x2e7)](_0x5976a2,_0x91ab95['firstChild']),_0x91ab95[_0x57da22(0x221)][_0x57da22(0x2ec)]>0x32&&_0x91ab95[_0x57da22(0x326)](_0x91ab95[_0x57da22(0x2bc)]),_0x91ab95[_0x57da22(0x1ce)]=0x0;}const turnIndicator=document[_0xe5e5(0x192)](_0xe5e5(0x397)),p1Name=document[_0xe5e5(0x192)](_0xe5e5(0x20e)),p1Image=document['getElementById'](_0xe5e5(0x294)),p1Health=document['getElementById'](_0xe5e5(0x2de)),p1Hp=document['getElementById']('p1-hp'),p1Strength=document['getElementById'](_0xe5e5(0x268)),p1Speed=document[_0xe5e5(0x192)](_0xe5e5(0x226)),p1Tactics=document[_0xe5e5(0x192)]('p1-tactics'),p1Size=document[_0xe5e5(0x192)](_0xe5e5(0x201)),p1Powerup=document[_0xe5e5(0x192)](_0xe5e5(0x364)),p1Type=document[_0xe5e5(0x192)](_0xe5e5(0x1fa)),p2Name=document[_0xe5e5(0x192)](_0xe5e5(0x2f5)),p2Image=document[_0xe5e5(0x192)](_0xe5e5(0x1a8)),p2Health=document[_0xe5e5(0x192)](_0xe5e5(0x399)),p2Hp=document[_0xe5e5(0x192)](_0xe5e5(0x286)),p2Strength=document[_0xe5e5(0x192)]('p2-strength'),p2Speed=document['getElementById'](_0xe5e5(0x1fd)),p2Tactics=document[_0xe5e5(0x192)]('p2-tactics'),p2Size=document['getElementById'](_0xe5e5(0x34c)),p2Powerup=document[_0xe5e5(0x192)]('p2-powerup'),p2Type=document['getElementById'](_0xe5e5(0x23c)),battleLog=document[_0xe5e5(0x192)](_0xe5e5(0x25e)),gameOver=document[_0xe5e5(0x192)](_0xe5e5(0x291)),assetCache={};async function getAssets(_0x47334){const _0x3a03b1=_0xe5e5;if(assetCache[_0x47334])return console[_0x3a03b1(0x32f)](_0x3a03b1(0x23f)+_0x47334),assetCache[_0x47334];console['time']('getAssets_'+_0x47334);let _0xe70528=[];try{console[_0x3a03b1(0x32f)](_0x3a03b1(0x348));const _0x2a2236=await Promise['race']([fetch(_0x3a03b1(0x27a),{'method':'POST','headers':{'Content-Type':_0x3a03b1(0x2c0)},'body':JSON[_0x3a03b1(0x1b7)]({'theme':_0x3a03b1(0x35b)})}),new Promise((_0x159885,_0x8aed74)=>setTimeout(()=>_0x8aed74(new Error(_0x3a03b1(0x392))),0x1388))]);if(!_0x2a2236['ok'])throw new Error(_0x3a03b1(0x220)+_0x2a2236['status']);_0xe70528=await _0x2a2236[_0x3a03b1(0x19e)](),!Array[_0x3a03b1(0x27b)](_0xe70528)&&(_0xe70528=[_0xe70528]),_0xe70528=_0xe70528[_0x3a03b1(0x1b1)]((_0x124070,_0xcd48ce)=>({..._0x124070,'theme':_0x3a03b1(0x35b),'name':_0x124070['name']||_0x3a03b1(0x312)+_0xcd48ce,'strength':_0x124070['strength']||0x4,'speed':_0x124070['speed']||0x4,'tactics':_0x124070[_0x3a03b1(0x317)]||0x4,'size':_0x124070[_0x3a03b1(0x34b)]||_0x3a03b1(0x19d),'type':_0x124070[_0x3a03b1(0x296)]||'Base','powerup':_0x124070['powerup']||_0x3a03b1(0x2d4)}));}catch(_0x2563e9){console[_0x3a03b1(0x19c)](_0x3a03b1(0x1b3),_0x2563e9),_0xe70528=[{'name':_0x3a03b1(0x255),'strength':0x4,'speed':0x4,'tactics':0x4,'size':_0x3a03b1(0x19d),'type':_0x3a03b1(0x372),'powerup':_0x3a03b1(0x2d4),'theme':'monstrocity'},{'name':'Dankle','strength':0x3,'speed':0x5,'tactics':0x3,'size':_0x3a03b1(0x320),'type':_0x3a03b1(0x372),'powerup':_0x3a03b1(0x17c),'theme':_0x3a03b1(0x35b)}];}if(_0x47334===_0x3a03b1(0x35b))return assetCache[_0x47334]=_0xe70528,console[_0x3a03b1(0x191)](_0x3a03b1(0x31c)+_0x47334),_0xe70528;const _0x591417=themes[_0x3a03b1(0x342)](_0x42da7a=>_0x42da7a[_0x3a03b1(0x1f4)])[_0x3a03b1(0x1c4)](_0x1b6b06=>_0x1b6b06['value']===_0x47334);if(!_0x591417)return console[_0x3a03b1(0x354)]('getAssets:\x20Theme\x20not\x20found:\x20'+_0x47334),assetCache[_0x47334]=_0xe70528,console[_0x3a03b1(0x191)](_0x3a03b1(0x31c)+_0x47334),_0xe70528;const _0x4ecea3=_0x591417[_0x3a03b1(0x1d8)]?_0x591417['policyIds'][_0x3a03b1(0x217)](',')[_0x3a03b1(0x25b)](_0x103ba4=>_0x103ba4[_0x3a03b1(0x183)]()):[];if(!_0x4ecea3['length'])return assetCache[_0x47334]=_0xe70528,console[_0x3a03b1(0x191)](_0x3a03b1(0x31c)+_0x47334),_0xe70528;let _0x1204d3=[];try{const _0xbabfb8=_0x4ecea3[_0x3a03b1(0x1b1)]((_0x21c0ec,_0x18990a)=>({'policyId':_0x21c0ec,'orientation':_0x591417['orientations']?.[_0x3a03b1(0x217)](',')[_0x18990a]||'Right','ipfsPrefix':_0x591417['ipfsPrefixes']?.[_0x3a03b1(0x217)](',')[_0x18990a]||_0x3a03b1(0x20f)})),_0x4c147b=await Promise[_0x3a03b1(0x1d7)]([fetch(_0x3a03b1(0x309),{'method':_0x3a03b1(0x1a5),'headers':{'Content-Type':'application/json'},'body':JSON[_0x3a03b1(0x1b7)]({'policyIds':_0xbabfb8[_0x3a03b1(0x1b1)](_0x1fd543=>_0x1fd543[_0x3a03b1(0x182)]),'theme':_0x47334})}),new Promise((_0x598a69,_0x3f3ae4)=>setTimeout(()=>_0x3f3ae4(new Error(_0x3a03b1(0x258))),0x2710))]);if(!_0x4c147b['ok'])throw new Error(_0x3a03b1(0x2b9)+_0x4c147b[_0x3a03b1(0x2c7)]);const _0x4c53a1=await _0x4c147b[_0x3a03b1(0x19e)]();_0x1204d3=Array[_0x3a03b1(0x27b)](_0x4c53a1)?_0x4c53a1:[_0x4c53a1],_0x1204d3=_0x1204d3[_0x3a03b1(0x25b)](_0x143879=>_0x143879&&_0x143879['name']&&_0x143879[_0x3a03b1(0x1fe)])[_0x3a03b1(0x1b1)]((_0x2df476,_0x3bfd42)=>({..._0x2df476,'theme':_0x47334,'name':_0x2df476['name']||_0x3a03b1(0x36d)+_0x3bfd42,'strength':_0x2df476[_0x3a03b1(0x186)]||0x4,'speed':_0x2df476['speed']||0x4,'tactics':_0x2df476['tactics']||0x4,'size':_0x2df476[_0x3a03b1(0x34b)]||'Medium','type':_0x2df476[_0x3a03b1(0x296)]||_0x3a03b1(0x372),'powerup':_0x2df476[_0x3a03b1(0x37b)]||_0x3a03b1(0x2d4),'policyId':_0x2df476[_0x3a03b1(0x182)]||_0xbabfb8[0x0][_0x3a03b1(0x182)],'ipfs':_0x2df476[_0x3a03b1(0x1fe)]||''}));}catch(_0x49312c){console['error']('getAssets:\x20NFT\x20fetch\x20error\x20for\x20theme\x20'+_0x47334+':',_0x49312c);}const _0x38ac2d=[..._0xe70528,..._0x1204d3];return assetCache[_0x47334]=_0x38ac2d,console[_0x3a03b1(0x191)]('getAssets_'+_0x47334),_0x38ac2d;}document[_0xe5e5(0x1f8)](_0xe5e5(0x1cf),function(){var _0x2754f6=function(){const _0x4822c9=_0x3390;var _0x7b41ab=localStorage[_0x4822c9(0x380)](_0x4822c9(0x235))||'monstrocity';getAssets(_0x7b41ab)[_0x4822c9(0x31e)](function(_0x49b82d){const _0xf0b76c=_0x4822c9;console[_0xf0b76c(0x32f)](_0xf0b76c(0x33d),_0x49b82d);var _0x43d67d=new MonstrocityMatch3(_0x49b82d,_0x7b41ab);console[_0xf0b76c(0x32f)](_0xf0b76c(0x1ff)),_0x43d67d['init']()[_0xf0b76c(0x31e)](function(){const _0x473752=_0xf0b76c;console[_0x473752(0x32f)](_0x473752(0x278)),document[_0x473752(0x32a)](_0x473752(0x237))[_0x473752(0x248)]=_0x43d67d['baseImagePath']+_0x473752(0x323);});})[_0x4822c9(0x338)](function(_0x5809ae){const _0x186653=_0x4822c9;console[_0x186653(0x19c)](_0x186653(0x2aa),_0x5809ae);});};_0x2754f6();});function _0xdf88(){const _0x329dfb=['\x20Score:\x20','maxTouchPoints','Response\x20status:','flip-p2','Craig','2162610SDZQHR','Texby','NFT\x20timeout','init','px,\x200)','filter','/staking/icons/skull.png','Score\x20Not\x20Saved:\x20','battle-log','forEach',',\x20player2.health=','character-options','progress','Game\x20over,\x20skipping\x20cascade\x20resolution','mousemove','visible','No\x20saved\x20progress\x20found,\x20starting\x20at\x20Level\x201','handleTouchEnd','p1-strength','updateTheme:\x20Skipping\x20board\x20render,\x20no\x20active\x20game','slideTiles','Cascade\x20complete,\x20ending\x20turn','offsetY','Goblin\x20Ganger','https://www.skulliance.io/staking/icons/','flex','38479Rcwsqv','Large','applyAnimation','Tile\x20at\x20(','saveProgress','showCharacterSelect:\x20Rendered\x20','touchend','ontouchstart','Main:\x20Game\x20initialized\x20successfully','showCharacterSelect:\x20Character\x20selected:\x20','ajax/get-monstrocity-assets.php','isArray','</p>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20','\x20on\x20','matched','endTurn','Opponent','\x20uses\x20Last\x20Stand,\x20dealing\x20','px)\x20','\x20matches:','animatePowerup','orientation','p2-hp','base',',\x20damage:\x20','Game\x20over,\x20skipping\x20cascadeTiles','Ouchie','lastStandActive','style','createRandomTile',',\x20cols\x20','Game\x20over,\x20skipping\x20endTurn','backgroundPosition','game-over','appendChild','Error\x20clearing\x20progress:','p1-image',',\x20storedTheme=','type','isDragging','clearProgress','dataset','Leader','loser','row','...','Slash',',\x20healthPercentage=','updatePlayerDisplay','abs','/logo.png','ajax/load-monstrocity-progress.php','top','background','HTTP\x20error!\x20Status:\x20','animating','multiMatch','handleMouseUp','Main:\x20Error\x20initializing\x20game:','push','change-character',',\x20tiles\x20to\x20clear:','title','second-attack','AI\x20Opponent','https://www.skulliance.io/staking/sounds/hypercube_create.ogg','animateRecoil','Total\x20tiles\x20matched\x20from\x20player\x20move:\x20','gameState','health','Katastrophy','usePowerup','\x20tiles\x20matched\x20for\x20a\x20200%\x20bonus!','NFT\x20HTTP\x20error!\x20Status:\x20','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<h2>Select\x20Theme</h2>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<div\x20id=\x22theme-options\x22></div>\x0a\x09\x20\x20\x20\x20\x20\x20','Boost\x20Attack','lastChild','\x27s\x20','innerHTML','Error\x20saving\x20progress:','application/json','glow-recoil','Progress\x20saved:\x20currentLevel=','\x20+\x2020))\x20*\x20(1\x20+\x20','left','#4CAF50','scaleX(-1)','status','\x20uses\x20Heal,\x20restoring\x20','Game\x20over,\x20skipping\x20tile\x20clearing\x20and\x20cascading','/monstrocity.png','\x20damage,\x20but\x20','handleTouchMove','column','imageUrl','Game\x20over,\x20skipping\x20recoil\x20animation','\x20after\x20multi-match\x20bonus!','last-stand','VIDEO','round','Regenerate','\x20damage,\x20resulting\x20in\x20','restart','976NAzXds','cascade','\x20size\x20','getBoundingClientRect','canMakeMatch','Fetching\x20progress\x20from\x20ajax/load-monstrocity-progress.php','createCharacter:\x20config=','p1-health','visibility','initializing','https://www.skulliance.io/staking/sounds/badgeawarded.ogg','463467WHkNMg','Calculating\x20round\x20score:\x20points=',')\x20to\x20(','currentTurn','updateCharacters_','insertBefore','flipCharacter','includes','addEventListeners','<p>Health:\x20','length','center','gameOver','tileTypes','START\x20OVER','checkMatches\x20completed,\x20returning\x20matches:','Mega\x20Multi-Match!\x20','loop',',\x20level=','p2-name','checkMatches','\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<img\x20src=\x22','checkGameOver\x20skipped:\x20gameOver=','className','renderBoard:\x20Row\x20','Right','\x20with\x20Score\x20of\x20','add','touchmove','\x20damage!','transform\x200.2s\x20ease','Round\x20Won!\x20Points:\x20','translate(','\x20characters','updateTheme:\x20Board\x20rendered\x20for\x20active\x20game','\x20tiles\x20matched\x20for\x20a\x2020%\x20bonus!','sounds','Round\x20Score\x20Formula:\x20(((','\x20uses\x20Regen,\x20restoring\x20','ajax/get-nft-assets.php','\x20steps\x20into\x20the\x20fray\x20with\x20','toLowerCase','click','Starting\x20Level\x20','touchstart','hyperCube','showCharacterSelect:\x20this.player1\x20set:\x20','https://www.skulliance.io/staking/sounds/powergem_created.ogg','Monstrocity_Unknown_',')\x20has\x20no\x20element\x20to\x20animate','\x27s\x20tactics\x20halve\x20the\x20blow,\x20taking\x20only\x20',',\x20Grand\x20Total\x20Score:\x20','boostValue','tactics','matches','parentNode','Failed\x20to\x20save\x20progress:','leader','getAssets_','textContent','then','replaceChild','Small','isCheckingGameOver','try-again','logo.png','showProgressPopup','No\x20progress\x20found\x20or\x20status\x20not\x20success:','removeChild','\x22\x20data-project=\x22','<p>Tactics:\x20','backgroundImage','querySelector','theme-group','\x20(opponentsConfig[','Game\x20over,\x20skipping\x20match\x20animation\x20and\x20cascading','findAIMove','log','saveScoreToDatabase','\x20HP','success','play','special-attack','Animating\x20recoil\x20for\x20defender:','Slime\x20Mind','progress-start-fresh','catch','\x20uses\x20Power\x20Surge,\x20next\x20attack\x20+','floor','\x20(originally\x20','px)','Main:\x20Player\x20characters\x20loaded:','\x27s\x20Boost\x20fades.','Animating\x20matched\x20tiles,\x20allMatchedTiles:','<img\x20loading=\x22eager\x22\x20src=\x22','resolveMatches','flatMap','</strong></p>','handleGameOverButton\x20completed:\x20currentLevel=','updateTheme:\x20Skipped\x20due\x20to\x20pending\x20update','level',',\x20Total\x20damage:\x20','getAssets:\x20Fetching\x20Monstrocity\x20assets','Starting\x20fresh\x20at\x20Level\x201','extension','size','p2-size','loss','Left','selected','Loaded\x20opponent\x20for\x20level\x20','https://www.skulliance.io/staking/sounds/speedmatch1.ogg','<p>Strength:\x20','has','warn','Animating\x20powerup','</p>','Minor\x20Regen','getTileFromEvent','\x20but\x20sharpens\x20tactics\x20to\x20','clientX','monstrocity','speed','Progress\x20saved:\x20Level\x20','falling','grandTotalScore','Last\x20Stand\x20applied,\x20mitigated\x20','max','countEmptyBelow','project','p1-powerup','\x20/\x2056)\x20=\x20','NEXT\x20LEVEL','69288BxzuzN','healthPercentage',',\x20score=','min',',\x20matches=','png','NFT_Unknown_','init:\x20Prompting\x20with\x20loadedLevel=','mediaType','checkMatches\x20started','You\x20Win!','Base','Horizontal\x20match\x20found\x20at\x20row\x20','powerGem','createCharacter','\x22\x20autoplay\x20loop\x20muted\x20alt=\x22','updateHealth','https://www.skulliance.io/staking/images/monstrocity/',',\x20player1.health=','some','powerup','updateTheme_','Progress\x20cleared','aiTurn','score','getItem','initGame:\x20Started\x20with\x20this.currentLevel=','isTouchDevice','.game-container','Restart','\x27s\x20tactics)','finalWin','No\x20match,\x20reverting\x20tiles...','Resume\x20from\x20Level\x20','boostActive','checkGameOver\x20completed:\x20currentLevel=','character-select-container','match','px,\x200)\x20scale(1.05)','3158984SGccIg','progress-message','\x20(100%\x20bonus\x20for\x20match-5+)',',\x20reduced\x20by\x20','Monstrocity\x20timeout','#FFC105','transition','Spydrax',',\x20Completions:\x20','turn-indicator','drops\x20health\x20to\x20','p2-health','orientations','height','transform','coordinates','onload','\x20defeats\x20',',\x20loadedScore=','Minor\x20Régén','Battle\x20Damaged','s\x20linear','IMG','Random',',\x20Health\x20Left:\x20','resolveMatches\x20started,\x20gameOver:','image','handleTouchStart','onerror','playerTurn','\x27s\x20Last\x20Stand\x20mitigates\x20','dragDirection',',\x20currentLevel=','Processing\x20match:','\x27s\x20orientation\x20flipped\x20to\x20','fallbackUrl','tileSizeWithGap','Resumed\x20at\x20Level\x20','\x20uses\x20Minor\x20Regen,\x20restoring\x20','totalTiles','<p><strong>','img','clientY','\x22\x20onerror=\x22this.src=\x27','translate(0,\x200)','Heal','ipfsPrefixes','offsetX','\x22\x20onerror=\x22this.src=\x27/staking/icons/skull.png\x27\x22>\x0a\x09\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20\x20<p>','tagName','updateOpponentDisplay','policyId','trim','setItem','msMaxTouchPoints','strength','button','targetTile','<p>Speed:\x20','handleGameOverButton\x20started:\x20currentLevel=','Dankle','Player','Turn\x20switched\x20to\x20','10MTebGg','Error\x20saving\x20score:\x20','\x20damage','timeEnd','getElementById',',\x20Match\x20bonus:\x20','message','win','Resume','Tactics\x20applied,\x20damage\x20reduced\x20to:\x20','Error\x20updating\x20theme\x20assets:','\x20for\x20','Saving\x20score:\x20level=','url(','error','Medium','json','GET','maxHealth','<p>Type:\x20','display','Clearing\x20matched\x20tiles:','addEventListeners:\x20Switch\x20Monster\x20button\x20clicked','POST','Error\x20loading\x20progress:','https://www.skulliance.io/staking/sounds/voice_go.ogg','p2-image','handleGameOverButton','mousedown',',\x20isCheckingGameOver=','https://www.skulliance.io/staking/sounds/badmove.ogg','\x20damage\x20to\x20','Parsed\x20response:','offsetWidth','Failed\x20to\x20preload:\x20','map','video','getAssets:\x20Monstrocity\x20fetch\x20error:','Jarhead','cascadeTilesWithoutRender','playerCharactersConfig','stringify','inline-block','<p>Size:\x20','roundStats','Game\x20completed!\x20Grand\x20total\x20score\x20reset.','attempts','Merdock','#FFA500','renderBoard:\x20Board\x20not\x20initialized,\x20skipping\x20render','.png','Found\x20','muted','progress-modal-content','find','\x20uses\x20','30tXtBWj','battle-damaged/','Game\x20over\x20detected\x20during\x20match\x20processing,\x20stopping\x20further\x20processing','setBackground:\x20themeData=','checkGameOver','\x20swaps\x20tiles\x20at\x20(','replace','element','scrollTop','DOMContentLoaded','\x20created\x20a\x20match\x20of\x20','Error\x20playing\x20lose\x20sound:','tile\x20','alt','classList','ajax/save-monstrocity-score.php','glow-','race','policyIds','showProgressPopup:\x20Displaying\x20popup\x20for\x20level=',',\x20Matches:\x20','character-option','initBoard','game-board','handleMouseDown','\x20tiles!','theme-option','baseImagePath','init:\x20Async\x20initialization\x20completed','handleMatch\x20completed,\x20damage\x20dealt:\x20','initGame','battle-damaged','name','setBackground','setBackground:\x20Attempting\x20for\x20theme=','Game\x20Over','\x20due\x20to\x20','preventDefault','Mandiblus','\x20goes\x20first!','Billandar\x20and\x20Ted','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>Loading\x20new\x20characters...</p>','Damage\x20from\x20match:\x20','createDocumentFragment','https://www.skulliance.io/staking/sounds/voice_gameover.ogg','renderBoard','items','removeEventListener','autoplay','board','addEventListener','50HmYapT','p1-type','updateTileSizeWithGap',')\x20/\x20100)\x20*\x20(','p2-speed','ipfs','Main:\x20Game\x20instance\x20created','player2','p1-size','game-over-container','time','Round\x20points\x20increased\x20from\x20','Total\x20damage\x20dealt:\x20','addEventListeners:\x20Player\x201\x20media\x20clicked','https://www.skulliance.io/staking/sounds/select.ogg','body','Reset\x20to\x20Level\x201:\x20currentLevel=','Bite','div','block','px,\x20','p1-name','https://ipfs.io/ipfs/','Error\x20in\x20checkMatches:','<p\x20style=\x22color:\x20#fff;\x20text-align:\x20center;\x22>No\x20characters\x20available.\x20Please\x20try\x20another\x20theme.</p>','ajax/clear-monstrocity-progress.php',',\x20Score\x20',',\x20rows\x20','Drake','width','split','indexOf','first-attack','theme-options','swapPlayerCharacter','random','querySelectorAll','showThemeSelect','cascadeTiles','Monstrocity\x20HTTP\x20error!\x20Status:\x20','children','showProgressPopup:\x20User\x20chose\x20Restart','completed','translate(0,\x20','onclick','p1-speed','currentLevel','winner','remove','selectedTile','\x22\x20alt=\x22','\x20-\x20','\x20but\x20dulls\x20tactics\x20to\x20','\x27s\x20Turn','backgroundColor','mov','group','createElement','\x20HP!','animateAttack','gameTheme','playerCharacters','.game-logo','Vertical\x20match\x20found\x20at\x20col\x20','power-up','showCharacterSelect','touches','p2-type','toFixed','value','getAssets:\x20Cache\x20hit\x20for\x20','Score\x20Saved:\x20Level\x20','29454194gCewQB','showCharacterSelect:\x20No\x20characters\x20available,\x20using\x20fallback','Special\x20attack\x20multiplier\x20applied,\x20damage:\x20','progress-modal','Level\x20','points','loadProgress','src','handleMouseMove','Save\x20response:','Error\x20saving\x20to\x20database:','theme','none','Game\x20over,\x20exiting\x20resolveMatches','62616tHNSrT','player1'];_0xdf88=function(){return _0x329dfb;};return _0xdf88();}
  </script>
</body>
</html>