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
      background-image: url(https://www.skulliance.io/staking/images/monstrocity/biolab.png);
      background-size: cover;
      background-position: center;
    }
    
    h2 {
      margin-top: 0px;
      margin-bottom: 10px;
      font-weight: bold;
    }

    .game-container {
      margin-top: 20px;
      margin-bottom: 20px;
      text-align: center;
      padding: 20px;
      background-color: #002430;
      border-radius: 10px;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
      width: 910px;
      min-width: 910px;
      max-width: 910px;
      box-sizing: border-box;
      max-height: 1160px;
      border: 3px solid black;
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
      width: 230px;
      padding: 15px;
      background-color: #003A45;
      border-radius: 5px;
      text-align: center;
      flex-shrink: 0;
      position: relative;
      top: -225px;
      min-height: 510px;
      border: 1px solid black;
    }

    .character img {
      width: 100%;
      height: auto;
      margin-bottom: 10px;
      border-radius: 5px;
      transition: transform 0.1s linear, filter 0.5s ease;
    }
    
    .character p{
      font-weight: bold;
    }
    
    .character table {
      width: 100%;
      text-align: left;
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
    }

    .health {
      height: 100%;
      background-color: #4CAF50;
      transition: width 0.3s ease;
    }

    #game-board {
      display: grid;
      gap: 0.5vh;
      background: #003A45;
      padding: 1vh;
      box-sizing: border-box;
      user-select: none;
      position: relative;
      touch-action: none;
      width: 300px;
      height: 300px;
      grid-template-columns: repeat(5, 1fr);
      flex-shrink: 0;
      margin-top: 17px;
      border-radius: 5px;
      border: 1px solid black;
    }

    .tile {
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
      position: relative;
      top: -945px;
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
      background-color: #003A45;
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
	    background-color: #8FA5B2;
	    border: none;
	    border-radius: 5px;
	    cursor: pointer;
	    font-weight: bold;
	    margin-bottom: 20px;
	    min-width: 137px;
	    font-size: 13px;
    }

    button:hover { background-color: #95AFC0; }

    .legend {
      margin-top: 20px;
      text-align: left;
      background-color: #003A45;
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
      display: flex;
      align-items: center;
      margin: 5px 0;
      font-size: 0.9em;
    }

    .legend-tile {
      width: 20px;
      height: 20px;
      margin-right: 10px;
      display: inline-block;
      border: 1px solid #555;
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
		background: rgba(0, 36, 48, 1);
		padding: 20px;
		border-radius: 10px;
		z-index: 100;
		width: 80%;
		max-width: 875px;
		max-height: 94vh;
		overflow-y: auto;
		display: none;
		border: 3px solid black;
    }

    #character-select-container h2 {
      text-align: center;
      margin-bottom: 20px;
    }

    .character-option {
      display: inline-block;
      width: 200px;
      margin: 10px;
      padding: 10px;
      background: #003A45;
      border-radius: 5px;
      cursor: pointer;
      transition: transform 0.2s ease, background 0.2s ease;
    }

    .character-option:hover {
      transform: scale(1.05);
      background: #004D5A;
    }

    .character-option img {
      width: 100%;
      height: auto;
      border-radius: 5px;
    }

    .character-option p {
      margin: 5px 0;
      font-size: 0.9em;
    }

    @media (max-width: 950px) {
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
      
      .character img {
		  width: 85px;
          float: left;
          position: absolute;
          left: 15px;
          top: 55px;
      }
      
      .character table{
        width: 70%;
        float: right;
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
      
      #game-over-container {
        top: -1150px;
      }

      #character-select-container {
        width: 90%;
        padding: 10px;
      }

      .character-option {
        width: 140px;
        margin: 5px;
      }
    }
  </style>
</head>
<body>
  <div class="game-container">
    <img src="https://www.skulliance.io/staking/images/monstrocity/logo.png" alt="Monstrocity Logo" class="game-logo">
    <button id="restart">Restart Level</button>
    <button id="change-character" style="display: none;">Switch Monster</button>
    <div class="turn-indicator" id="turn-indicator">Player 1's Turn</div>

    <div class="battlefield">
      <div class="character" id="player1">
        <h2><span id="p1-name"></span></h2>
        <img id="p1-image" src="" alt="Player 1 Image">
        <div class="health-bar"><div class="health" id="p1-health"></div></div>
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
        <li><span class="legend-tile first-attack"><img src="https://www.skulliance.io/staking/icons/first-attack.png" alt="First Attack"></span><strong>First Attack (Slash): </strong> Deals damage (Strength × 2/3/4 for 3/4/5 tiles)</li>
        <li><span class="legend-tile second-attack"><img src="https://www.skulliance.io/staking/icons/second-attack.png" alt="Second Attack"></span><strong>Second Attack (Bite): </strong> Deals damage (Strength × 2/3/4 for 3/4/5 tiles)</li>
        <li><span class="legend-tile special-attack"><img src="https://www.skulliance.io/staking/icons/special-attack.png" alt="Special Attack"></span><strong>Special Attack (Shadow Strike): </strong> Deals 1.2× damage (Strength × 2/3/4 for 3/4/5 tiles)</li>
        <li><span class="legend-tile power-up"><img src="https://www.skulliance.io/staking/icons/power-up.png" alt="Power Up"></span><strong>Power-Up: </strong> Activates a random powerup (see below)</li>
        <li><span class="legend-tile last-stand"><img src="https://www.skulliance.io/staking/icons/last-stand.png" alt="Last Stand"></span><strong>Last Stand: </strong> Deals damage and mitigates 5 damage on the next attack received</li>
      </ul>
      <br>
      <h3>Power-Up Effects</h3>
      <ul>
        <li><strong>Heal (Bloody): </strong> Restores 10 HP (reduced by enemy tactics)</li>
        <li><strong>Boost Attack (Cardano): </strong> Adds +10 damage to the next attack (reduced by enemy tactics)</li>
        <li><strong>Regenerate (ADA): </strong> Restores 7 HP (reduced by enemy tactics)</li>
        <li><strong>Minor Regen (None): </strong> Restores 5 HP (reduced by enemy tactics)</li>
      </ul>
    </div>
    <div id="game-over-container">
      <div id="game-over"></div>
      <div id="game-over-buttons">
        <button id="try-again"></button>
      </div>
    </div>
    <div id="character-select-container">
      <h2>Select Your Character</h2>
      <div id="character-options"></div>
    </div>
  </div>

  <script>
    const opponentsConfig = [
      { name: "Craig", strength: 1, speed: 1, tactics: 1, size: "Medium", type: "Base", powerup: "Minor Regen" },
      { name: "Merdock", strength: 1, speed: 1, tactics: 1, size: "Large", type: "Base", powerup: "Minor Regen" },
      { name: "Goblin Ganger", strength: 2, speed: 2, tactics: 2, size: "Small", type: "Base", powerup: "Minor Regen" },
      { name: "Texby", strength: 2, speed: 2, tactics: 2, size: "Medium", type: "Base", powerup: "Minor Regen" },
      { name: "Mandiblus", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Base", powerup: "Regenerate" },
      { name: "Koipon", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Base", powerup: "Regenerate" },
      { name: "Slime Mind", strength: 4, speed: 4, tactics: 4, size: "Small", type: "Base", powerup: "Regenerate" },
      { name: "Billandar and Ted", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
      { name: "Dankle", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Base", powerup: "Boost Attack" },
      { name: "Jarhead", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Base", powerup: "Boost Attack" },
      { name: "Spydrax", strength: 6, speed: 6, tactics: 6, size: "Small", type: "Base", powerup: "Heal" },
      { name: "Katastrophy", strength: 7, speed: 7, tactics: 7, size: "Large", type: "Base", powerup: "Heal" },
      { name: "Ouchie", strength: 7, speed: 7, tactics: 7, size: "Medium", type: "Base", powerup: "Heal" },
      { name: "Drake", strength: 8, speed: 7, tactics: 7, size: "Medium", type: "Base", powerup: "Heal" },
      { name: "Craig", strength: 1, speed: 1, tactics: 1, size: "Medium", type: "Leader", powerup: "Minor Regen" },
      { name: "Merdock", strength: 1, speed: 1, tactics: 1, size: "Large", type: "Leader", powerup: "Minor Regen" },
      { name: "Goblin Ganger", strength: 2, speed: 2, tactics: 2, size: "Small", type: "Leader", powerup: "Minor Regen" },
      { name: "Texby", strength: 2, speed: 2, tactics: 2, size: "Medium", type: "Leader", powerup: "Minor Regen" },
      { name: "Mandiblus", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
      { name: "Koipon", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
      { name: "Slime Mind", strength: 4, speed: 4, tactics: 4, size: "Small", type: "Leader", powerup: "Regenerate" },
      { name: "Billandar and Ted", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Leader", powerup: "Regenerate" },
      { name: "Dankle", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Leader", powerup: "Boost Attack" },
      { name: "Jarhead", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Leader", powerup: "Boost Attack" },
      { name: "Spydrax", strength: 6, speed: 6, tactics: 6, size: "Small", type: "Leader", powerup: "Heal" },
      { name: "Katastrophy", strength: 7, speed: 7, tactics: 7, size: "Large", type: "Leader", powerup: "Heal" },
      { name: "Ouchie", strength: 7, speed: 7, tactics: 7, size: "Medium", type: "Leader", powerup: "Heal" },
      { name: "Drake", strength: 8, speed: 7, tactics: 7, size: "Medium", type: "Leader", powerup: "Heal" }
    ];
	
	 function getAssets() {
	     var xhttp = new XMLHttpRequest();
	     xhttp.open('GET', 'ajax/get-monstrocity-assets.php', true);
	     xhttp.send();
	     xhttp.onreadystatechange = function() {
	         if (xhttp.readyState == XMLHttpRequest.DONE) {
	             if (xhttp.status == 200) {
	                 var data = xhttp.responseText;
	                 setTimeout(() => { // Delay the additional sounds
						 if(data != 'false'){
							 return data;
					 	 }else{
							 return '{ name: "Craig", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" }';
					 	 }
	                 }, 2000); // 2000ms (2 seconds) delay; adjust as needed
	                 console.log(data);
	             }
	         }
	     }.bind(this); // Bind the Match3Game instance to the function
	 }
	 
	 const playerCharactersConfig = [getAssets()];
	 /*
    const playerCharactersConfig = [
        { name: "Craig", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Merdock", strength: 4, speed: 4, tactics: 4, size: "Large", type: "Base", powerup: "Regenerate" },
        { name: "Goblin Ganger", strength: 4, speed: 4, tactics: 4, size: "Small", type: "Base", powerup: "Regenerate" },
        { name: "Texby", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Mandiblus", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Koipon", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Slime Mind", strength: 4, speed: 4, tactics: 4, size: "Small", type: "Base", powerup: "Regenerate" },
        { name: "Billandar and Ted", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Dankle", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Jarhead", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Spydrax", strength: 4, speed: 4, tactics: 4, size: "Small", type: "Base", powerup: "Regenerate" },
        { name: "Katastrophy", strength: 4, speed: 4, tactics: 4, size: "Large", type: "Base", powerup: "Regenerate" },
        { name: "Ouchie", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Drake", strength: 4, speed: 4, tactics: 4, size: "Medium", type: "Base", powerup: "Regenerate" },
        { name: "Craig", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Merdock", strength: 3, speed: 3, tactics: 3, size: "Large", type: "Leader", powerup: "Regenerate" },
        { name: "Goblin Ganger", strength: 3, speed: 3, tactics: 3, size: "Small", type: "Leader", powerup: "Regenerate" },
        { name: "Texby", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Mandiblus", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Koipon", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Slime Mind", strength: 3, speed: 3, tactics: 3, size: "Small", type: "Leader", powerup: "Regenerate" },
        { name: "Billandar and Ted", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Dankle", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Jarhead", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Spydrax", strength: 3, speed: 3, tactics: 3, size: "Small", type: "Leader", powerup: "Regenerate" },
        { name: "Katastrophy", strength: 3, speed: 3, tactics: 3, size: "Large", type: "Leader", powerup: "Regenerate" },
        { name: "Ouchie", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Drake", strength: 3, speed: 3, tactics: 3, size: "Medium", type: "Leader", powerup: "Regenerate" },
        { name: "Craig", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Merdock", strength: 5, speed: 5, tactics: 5, size: "Large", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Goblin Ganger", strength: 5, speed: 5, tactics: 5, size: "Small", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Texby", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Mandiblus", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Koipon", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Slime Mind", strength: 5, speed: 5, tactics: 5, size: "Small", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Billandar and Ted", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Dankle", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Jarhead", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Spydrax", strength: 5, speed: 5, tactics: 5, size: "Small", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Katastrophy", strength: 5, speed: 5, tactics: 5, size: "Large", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Ouchie", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" },
        { name: "Drake", strength: 5, speed: 5, tactics: 5, size: "Medium", type: "Battle Damaged", powerup: "Regenerate" }
    ];*/

    const characterDirections = {
      "Billandar and Ted": "Left",
      "Craig": "Left",
      "Dankle": "Left",
      "Drake": "Right",
      "Goblin Ganger": "Left",
      "Jarhead": "Right",
      "Katastrophy": "Right",
      "Koipon": "Left",
      "Mandiblus": "Left",
      "Merdock": "Left",
      "Ouchie": "Left",
      "Slime Mind": "Right",
      "Spydrax": "Right",
      "Texby": "Left"
    };

    class MonstrocityMatch3 {
      constructor() {
        this.isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0;
        this.width = 5;
        this.height = 5;
        this.board = [];
        this.selectedTile = null;
        this.gameOver = false;
        this.currentTurn = null;
        this.player1 = null;
        this.player2 = null;
        this.gameState = "initializing";
        this.isDragging = false;
        this.targetTile = null;
        this.dragDirection = null;
        this.offsetX = 0;
        this.offsetY = 0;
        this.currentLevel = 0;
        this.playerCharacters = playerCharactersConfig.map(config => this.createCharacter(config));

        this.tileTypes = ["first-attack", "second-attack", "special-attack", "power-up", "last-stand"];
        this.updateTileSizeWithGap();

        this.sounds = {
          match: new Audio('https://www.skulliance.io/staking/sounds/select.ogg'),
          cascade: new Audio('https://www.skulliance.io/staking/sounds/select.ogg'),
          badMove: new Audio('https://www.skulliance.io/staking/sounds/badmove.ogg'),
          gameOver: new Audio('https://www.skulliance.io/staking/sounds/voice_gameover.ogg'),
          reset: new Audio('https://www.skulliance.io/staking/sounds/voice_go.ogg'),
          loss: new Audio('https://www.skulliance.io/staking/sounds/skullcoinlose.ogg'),
          win: new Audio('https://www.skulliance.io/staking/sounds/voice_levelcomplete.ogg'),
          finalWin: new Audio('https://www.skulliance.io/staking/sounds/badgeawarded.ogg')
        };

        this.showCharacterSelect(true);
        this.addEventListeners();
      }

      updateTileSizeWithGap() {
        const boardElement = document.getElementById("game-board");
        const boardWidth = boardElement.offsetWidth || 300;
        this.tileSizeWithGap = (boardWidth - (0.5 * (this.width - 1))) / this.width;
      }

      createCharacter(config) {
        let typeFolder;
        switch (config.type) {
          case "Base":
            typeFolder = "base";
            break;
          case "Leader":
            typeFolder = "leader";
            break;
          case "Battle Damaged":
            typeFolder = "battle-damaged";
            break;
          default:
            typeFolder = "base";
        }
        const imageUrl = `https://www.skulliance.io/staking/images/monstrocity/${typeFolder}/${config.name.toLowerCase().replace(/ /g, '-')}.png`;
        
        let baseHealth;
        switch (config.type) {
          case "Leader":
            baseHealth = 100;
            break;
          case "Battle Damaged":
            baseHealth = 70;
            break;
          case "Base":
          default:
            baseHealth = 85;
        }
        
        let healthModifier = 1;
        let tacticsAdjust = 0;
        switch (config.size) {
          case "Large":
            healthModifier = 1.2;
            tacticsAdjust = config.tactics > 1 ? -2 : 0;
            break;
          case "Small":
            healthModifier = 0.8;
            tacticsAdjust = config.tactics < 6 ? 2 : 7 - config.tactics;
            break;
          case "Medium":
            healthModifier = 1;
            tacticsAdjust = 0;
            break;
        }
        
        const adjustedHealth = Math.round(baseHealth * healthModifier);
        const adjustedTactics = Math.max(1, Math.min(7, config.tactics + tacticsAdjust));

        return {
          name: config.name,
          type: config.type,
          strength: config.strength,
          speed: config.speed,
          tactics: adjustedTactics,
          size: config.size,
          powerup: config.powerup,
          health: adjustedHealth,
          maxHealth: adjustedHealth,
          boostActive: false,
          boostValue: 0,
          lastStandActive: false,
          imageUrl
        };
      }

      showCharacterSelect(isInitial = false) {
        const container = document.getElementById("character-select-container");
        const optionsDiv = document.getElementById("character-options");
        optionsDiv.innerHTML = "";
        container.style.display = "block";

        this.playerCharacters.forEach((character, index) => {
          const option = document.createElement("div");
          option.className = "character-option";
          option.innerHTML = `
            <img src="${character.imageUrl}" alt="${character.name}">
            <p><strong>${character.name}</strong></p>
            <p>Type: ${character.type}</p>
            <p>Health: ${character.maxHealth}</p>
            <p>Strength: ${character.strength}</p>
            <p>Speed: ${character.speed}</p>
            <p>Tactics: ${character.tactics}</p>
            <p>Size: ${character.size}</p>
            <p>Power-Up: ${character.powerup}</p>
          `;
          option.addEventListener("click", () => {
            container.style.display = "none";
            if (isInitial) {
              this.player1 = { ...character };
              this.initGame();
            } else {
              this.swapPlayerCharacter(character);
            }
          });
          optionsDiv.appendChild(option);
        });
      }

      swapPlayerCharacter(newCharacter) {
        const oldHealth = this.player1.health;
        const oldMaxHealth = this.player1.maxHealth;
        const newInstance = { ...newCharacter }; // Fresh instance with new maxHealth
        
        // Scale health proportionally based on remaining health percentage, capped at new maxHealth
        const healthPercentage = Math.min(1, oldHealth / oldMaxHealth); // Clamp to 100% max
        newInstance.health = Math.round(newInstance.maxHealth * healthPercentage);
        
        // Ensure health is never below 0 or above new maxHealth
        newInstance.health = Math.max(0, Math.min(newInstance.maxHealth, newInstance.health));
        
        // Reset temporary effects
        newInstance.boostActive = false;
        newInstance.boostValue = 0;
        newInstance.lastStandActive = false;
        
        this.player1 = newInstance;
        this.updatePlayerDisplay();
        this.updateHealth(this.player1); // Refresh health bar with new maxHealth
        
        log(`${this.player1.name} steps into the fray with ${this.player1.health}/${this.player1.maxHealth} HP!`);
        
        // Adjust turn order based on new character's speed and strength
        this.currentTurn = this.player1.speed > this.player2.speed 
          ? this.player1 
          : this.player2.speed > this.player1.speed 
            ? this.player2 
            : this.player1.strength >= this.player2.strength 
              ? this.player1 
              : this.player2;
        turnIndicator.textContent = `Level ${this.currentLevel + 1} - ${this.currentTurn === this.player1 ? "Player" : "Opponent"}'s Turn`;
        
        if (this.currentTurn === this.player2 && this.gameState !== "gameOver") {
          setTimeout(() => this.aiTurn(), 1000);
        }
      }

      initGame() {
        this.sounds.reset.play();
        log(`Starting Level ${this.currentLevel + 1}...`);
        
        this.player2 = this.createCharacter(opponentsConfig[this.currentLevel]);
        
        // Reset player health to maxHealth for a new round
        this.player1.health = this.player1.maxHealth;
        
        this.currentTurn = this.player1.speed > this.player2.speed 
          ? this.player1 
          : this.player2.speed > this.player1.speed 
            ? this.player2 
            : this.player1.strength >= this.player2.strength 
              ? this.player1 
              : this.player2;
        this.gameState = "initializing";
        this.gameOver = false;

        p1Image.classList.remove('winner', 'loser');
        p2Image.classList.remove('winner', 'loser');
        this.updatePlayerDisplay();
        this.updateOpponentDisplay();

        if (characterDirections[this.player1.name] === "Left") {
          p1Image.style.transform = "scaleX(-1)";
        } else {
          p1Image.style.transform = "none";
        }

        if (characterDirections[this.player2.name] === "Right") {
          p2Image.style.transform = "scaleX(-1)";
        } else {
          p2Image.style.transform = "none";
        }

        this.updateHealth(this.player1);
        this.updateHealth(this.player2);

        battleLog.innerHTML = "";
        gameOver.textContent = "";

        if (this.player1.size !== "Medium") {
          log(`${this.player1.name}'s ${this.player1.size} size ${this.player1.size === "Large" ? "boosts health to " + this.player1.maxHealth + " but dulls tactics to " + this.player1.tactics : "drops health to " + this.player1.maxHealth + " but sharpens tactics to " + this.player1.tactics}!`);
        }
        if (this.player2.size !== "Medium") {
          log(`${this.player2.name}'s ${this.player2.size} size ${this.player2.size === "Large" ? "boosts health to " + this.player2.maxHealth + " but dulls tactics to " + this.player2.tactics : "drops health to " + this.player2.maxHealth + " but sharpens tactics to " + this.player2.tactics}!`);
        }

        log(`${this.player1.name} starts at full strength with ${this.player1.health}/${this.player1.maxHealth} HP!`);
        log(`${this.currentTurn.name} goes first!`);

        this.initBoard();
        this.gameState = this.currentTurn === this.player1 ? "playerTurn" : "aiTurn";
        turnIndicator.textContent = `Level ${this.currentLevel + 1} - ${this.currentTurn === this.player1 ? "Player" : "Opponent"}'s Turn`;

        if (this.playerCharacters.length > 1) {
          document.getElementById("change-character").style.display = "inline-block";
        }

        if (this.currentTurn === this.player2) {
          setTimeout(() => this.aiTurn(), 1000);
        }
      }

	  updatePlayerDisplay() {
        p1Name.textContent = this.player1.name;
        p1Type.textContent = this.player1.type;
        p1Strength.textContent = this.player1.strength;
        p1Speed.textContent = this.player1.speed;
        p1Tactics.textContent = this.player1.tactics;
        p1Size.textContent = this.player1.size;
        p1Powerup.textContent = this.player1.powerup;
        p1Image.src = this.player1.imageUrl;
        p1Image.onload = () => p1Image.style.display = "block"; // Show when loaded
      }

      updateOpponentDisplay() {
        p2Name.textContent = this.player2.name;
        p2Type.textContent = this.player2.type;
        p2Strength.textContent = this.player2.strength;
        p2Speed.textContent = this.player2.speed;
        p2Tactics.textContent = this.player2.tactics;
        p2Size.textContent = this.player2.size;
        p2Powerup.textContent = this.player2.powerup;
        p2Image.src = this.player2.imageUrl;
        p2Image.onload = () => p2Image.style.display = "block"; // Show when loaded
      }

      initBoard() {
        this.board = [];
        for (let y = 0; y < this.height; y++) {
          this.board[y] = [];
          for (let x = 0; x < this.width; x++) {
            let tile;
            do {
              tile = this.createRandomTile();
            } while (
              (x >= 2 && this.board[y][x-1]?.type === tile.type && this.board[y][x-2]?.type === tile.type) ||
              (y >= 2 && this.board[y-1]?.[x]?.type === tile.type && this.board[y-2]?.[x]?.type === tile.type)
            );
            this.board[y][x] = tile;
          }
        }
        this.renderBoard();
      }

      createRandomTile() {
        return {
          type: randomChoice(this.tileTypes),
          element: null
        };
      }

      renderBoard() {
        this.updateTileSizeWithGap();
        const boardElement = document.getElementById("game-board");
        boardElement.innerHTML = "";

        for (let y = 0; y < this.height; y++) {
          for (let x = 0; x < this.width; x++) {
            const tile = this.board[y][x];
            if (tile.type === null) continue;
            const tileElement = document.createElement("div");
            tileElement.className = `tile ${tile.type}`;
            if (this.gameOver) tileElement.classList.add("game-over");
            const img = document.createElement('img');
            img.src = `https://www.skulliance.io/staking/icons/${tile.type}.png`;
            img.alt = tile.type;
            tileElement.appendChild(img);
            tileElement.dataset.x = x;
            tileElement.dataset.y = y;
            boardElement.appendChild(tileElement);
            tile.element = tileElement;

            if (!this.isDragging || (this.selectedTile && (this.selectedTile.x !== x || this.selectedTile.y !== y))) {
              tileElement.style.transform = "translate(0, 0)";
            }
          }
        }

        document.getElementById("game-over-container").style.display = this.gameOver ? "block" : "none";
      }

      addEventListeners() {
        const board = document.getElementById("game-board");

        if (this.isTouchDevice) {
          board.addEventListener("touchstart", (e) => this.handleTouchStart(e));
          board.addEventListener("touchmove", (e) => this.handleTouchMove(e));
          board.addEventListener("touchend", (e) => this.handleTouchEnd(e));
        } else {
          board.addEventListener("mousedown", (e) => this.handleMouseDown(e));
          board.addEventListener("mousemove", (e) => this.handleMouseMove(e));
          board.addEventListener("mouseup", (e) => this.handleMouseUp(e));
        }

        document.getElementById("try-again").addEventListener("click", () => this.handleGameOverButton());
        document.getElementById("restart").addEventListener("click", () => {
          this.initGame();
        });
        document.getElementById("change-character").addEventListener("click", () => {
          this.showCharacterSelect(false);
        });
        document.getElementById("p1-image").addEventListener("click", () => {
          this.showCharacterSelect(false);
        });
      }

      handleGameOverButton() {
        if (this.player2.health <= 0) {
          this.currentLevel++;
          if (this.currentLevel >= opponentsConfig.length) {
            this.currentLevel = 0;
          }
          this.initGame();
        } else {
          this.initGame();
        }
      }

      handleMouseDown(e) {
        if (this.gameOver || this.gameState !== "playerTurn" || this.currentTurn !== this.player1) return;
        e.preventDefault();
        const tile = this.getTileFromEvent(e);
        if (!tile || !tile.element) return;

        this.isDragging = true;
        this.selectedTile = { x: tile.x, y: tile.y };
        tile.element.classList.add("selected");

        const boardRect = document.getElementById("game-board").getBoundingClientRect();
        this.offsetX = e.clientX - (boardRect.left + this.selectedTile.x * this.tileSizeWithGap);
        this.offsetY = e.clientY - (boardRect.top + this.selectedTile.y * this.tileSizeWithGap);
      }

      handleMouseMove(e) {
        if (!this.isDragging || !this.selectedTile || this.gameOver || this.gameState !== "playerTurn") return;
        e.preventDefault();

        const boardRect = document.getElementById("game-board").getBoundingClientRect();
        const mouseX = e.clientX - boardRect.left - this.offsetX;
        const mouseY = e.clientY - boardRect.top - this.offsetY;

        const selectedTileElement = this.board[this.selectedTile.y][this.selectedTile.x].element;
        selectedTileElement.style.transition = "";

        if (!this.dragDirection) {
          const dx = Math.abs(mouseX - (this.selectedTile.x * this.tileSizeWithGap));
          const dy = Math.abs(mouseY - (this.selectedTile.y * this.tileSizeWithGap));
          if (dx > dy && dx > 5) this.dragDirection = "row";
          else if (dy > dx && dy > 5) this.dragDirection = "column";
        }

        if (!this.dragDirection) return;

        if (this.dragDirection === "row") {
          const constrainedX = Math.max(0, Math.min((this.width - 1) * this.tileSizeWithGap, mouseX));
          selectedTileElement.style.transform = `translate(${constrainedX - this.selectedTile.x * this.tileSizeWithGap}px, 0) scale(1.05)`;
          this.targetTile = {
            x: Math.round(constrainedX / this.tileSizeWithGap),
            y: this.selectedTile.y
          };
        } else if (this.dragDirection === "column") {
          const constrainedY = Math.max(0, Math.min((this.height - 1) * this.tileSizeWithGap, mouseY));
          selectedTileElement.style.transform = `translate(0, ${constrainedY - this.selectedTile.y * this.tileSizeWithGap}px) scale(1.05)`;
          this.targetTile = {
            x: this.selectedTile.x,
            y: Math.round(constrainedY / this.tileSizeWithGap)
          };
        }
      }

      handleMouseUp(e) {
        if (!this.isDragging || !this.selectedTile || !this.targetTile || this.gameOver || this.gameState !== "playerTurn") {
          if (this.selectedTile) {
            const tile = this.board[this.selectedTile.y][this.selectedTile.x];
            if (tile.element) tile.element.classList.remove("selected");
          }
          this.isDragging = false;
          this.selectedTile = null;
          this.targetTile = null;
          this.dragDirection = null;
          this.renderBoard();
          return;
        }

        const tile = this.board[this.selectedTile.y][this.selectedTile.x];
        if (tile.element) tile.element.classList.remove("selected");

        this.slideTiles(this.selectedTile.x, this.selectedTile.y, this.targetTile.x, this.targetTile.y);

        this.isDragging = false;
        this.selectedTile = null;
        this.targetTile = null;
        this.dragDirection = null;
      }

      handleTouchStart(e) {
        if (this.gameOver || this.gameState !== "playerTurn" || this.currentTurn !== this.player1) return;
        e.preventDefault();
        const tile = this.getTileFromEvent(e.touches[0]);
        if (!tile || !tile.element) return;

        this.isDragging = true;
        this.selectedTile = { x: tile.x, y: tile.y };
        tile.element.classList.add("selected");

        const boardRect = document.getElementById("game-board").getBoundingClientRect();
        this.offsetX = e.touches[0].clientX - (boardRect.left + this.selectedTile.x * this.tileSizeWithGap);
        this.offsetY = e.touches[0].clientY - (boardRect.top + this.selectedTile.y * this.tileSizeWithGap);
      }

      handleTouchMove(e) {
        if (!this.isDragging || !this.selectedTile || this.gameOver || this.gameState !== "playerTurn") return;
        e.preventDefault();

        const boardRect = document.getElementById("game-board").getBoundingClientRect();
        const touchX = e.touches[0].clientX - boardRect.left - this.offsetX;
        const touchY = e.touches[0].clientY - boardRect.top - this.offsetY;

        const selectedTileElement = this.board[this.selectedTile.y][this.selectedTile.x].element;

        requestAnimationFrame(() => {
          if (!this.dragDirection) {
            const dx = Math.abs(touchX - (this.selectedTile.x * this.tileSizeWithGap));
            const dy = Math.abs(touchY - (this.selectedTile.y * this.tileSizeWithGap));
            if (dx > dy && dx > 7) this.dragDirection = "row";
            else if (dy > dx && dy > 7) this.dragDirection = "column";
          }

          selectedTileElement.style.transition = "";

          if (this.dragDirection === "row") {
            const constrainedX = Math.max(0, Math.min((this.width - 1) * this.tileSizeWithGap, touchX));
            selectedTileElement.style.transform = `translate(${constrainedX - this.selectedTile.x * this.tileSizeWithGap}px, 0) scale(1.05)`;
            this.targetTile = {
              x: Math.round(constrainedX / this.tileSizeWithGap),
              y: this.selectedTile.y
            };
          } else if (this.dragDirection === "column") {
            const constrainedY = Math.max(0, Math.min((this.height - 1) * this.tileSizeWithGap, touchY));
            selectedTileElement.style.transform = `translate(0, ${constrainedY - this.selectedTile.y * this.tileSizeWithGap}px) scale(1.05)`;
            this.targetTile = {
              x: this.selectedTile.x,
              y: Math.round(constrainedY / this.tileSizeWithGap)
            };
          }
        });
      }

      handleTouchEnd(e) {
        if (!this.isDragging || !this.selectedTile || !this.targetTile || this.gameOver || this.gameState !== "playerTurn") {
          if (this.selectedTile) {
            const tile = this.board[this.selectedTile.y][this.selectedTile.x];
            if (tile.element) tile.element.classList.remove("selected");
          }
          this.isDragging = false;
          this.selectedTile = null;
          this.targetTile = null;
          this.dragDirection = null;
          this.renderBoard();
          return;
        }

        const tile = this.board[this.selectedTile.y][this.selectedTile.x];
        if (tile.element) tile.element.classList.remove("selected");

        this.slideTiles(this.selectedTile.x, this.selectedTile.y, this.targetTile.x, this.targetTile.y);

        this.isDragging = false;
        this.selectedTile = null;
        this.targetTile = null;
        this.dragDirection = null;
      }

      getTileFromEvent(e) {
        const boardRect = document.getElementById("game-board").getBoundingClientRect();
        const x = Math.floor((e.clientX - boardRect.left) / this.tileSizeWithGap);
        const y = Math.floor((e.clientY - boardRect.top) / this.tileSizeWithGap);
        if (x >= 0 && x < this.width && y >= 0 && y < this.height) {
          return { x, y, element: this.board[y][x].element };
        }
        return null;
      }

      slideTiles(startX, startY, endX, endY) {
        const tileSizeWithGap = this.tileSizeWithGap;
        let direction;

        const originalTiles = [];
        const tileElements = [];
        if (startY === endY) {
          direction = startX < endX ? 1 : -1;
          const minX = Math.min(startX, endX);
          const maxX = Math.max(startX, endX);
          for (let x = minX; x <= maxX; x++) {
            originalTiles.push({ ...this.board[startY][x] });
            tileElements.push(this.board[startY][x].element);
          }
        } else if (startX === endX) {
          direction = startY < endY ? 1 : -1;
          const minY = Math.min(startY, endY);
          const maxY = Math.max(startY, endY);
          for (let y = minY; y <= maxY; y++) {
            originalTiles.push({ ...this.board[y][startX] });
            tileElements.push(this.board[y][startX].element);
          }
        }

        const selectedElement = this.board[startY][startX].element;
        const dx = (endX - startX) * tileSizeWithGap;
        const dy = (endY - startY) * tileSizeWithGap;

        selectedElement.style.transition = "transform 0.2s ease";
        selectedElement.style.transform = `translate(${dx}px, ${dy}px)`;

        let i = 0;
        if (startY === endY) {
          for (let x = Math.min(startX, endX); x <= Math.max(startX, endX); x++) {
            if (x === startX) continue;
            const offsetX = direction * -tileSizeWithGap * (x - startX) / Math.abs(endX - startX);
            tileElements[i].style.transition = "transform 0.2s ease";
            tileElements[i].style.transform = `translate(${offsetX}px, 0)`;
            i++;
          }
        } else {
          for (let y = Math.min(startY, endY); y <= Math.max(startY, endY); y++) {
            if (y === startY) continue;
            const offsetY = direction * -tileSizeWithGap * (y - startY) / Math.abs(endY - startY);
            tileElements[i].style.transition = "transform 0.2s ease";
            tileElements[i].style.transform = `translate(0, ${offsetY}px)`;
            i++;
          }
        }

        setTimeout(() => {
          if (startY === endY) {
            const row = this.board[startY];
            const tempRow = [...row];
            if (startX < endX) {
              for (let x = startX; x < endX; x++) row[x] = tempRow[x + 1];
            } else {
              for (let x = startX; x > endX; x--) row[x] = tempRow[x - 1];
            }
            row[endX] = tempRow[startX];
          } else {
            const tempCol = [];
            for (let y = 0; y < this.height; y++) tempCol[y] = { ...this.board[y][startX] };
            if (startY < endY) {
              for (let y = startY; y < endY; y++) this.board[y][startX] = tempCol[y + 1];
            } else {
              for (let y = startY; y > endY; y--) this.board[y][startX] = tempCol[y - 1];
            }
            this.board[endY][endX] = tempCol[startY];
          }

          this.renderBoard();
          const hasMatches = this.resolveMatches(endX, endY);

          if (hasMatches) {
            this.gameState = "animating";
          } else {
            log("No match, reverting tiles...");
            this.sounds.badMove.play();
            selectedElement.style.transition = "transform 0.2s ease";
            selectedElement.style.transform = "translate(0, 0)";
            tileElements.forEach(element => {
              element.style.transition = "transform 0.2s ease";
              element.style.transform = "translate(0, 0)";
            });

            setTimeout(() => {
              if (startY === endY) {
                const minX = Math.min(startX, endX);
                for (let i = 0; i < originalTiles.length; i++) {
                  this.board[startY][minX + i] = { ...originalTiles[i], element: tileElements[i] };
                }
              } else {
                const minY = Math.min(startY, endY);
                for (let i = 0; i < originalTiles.length; i++) {
                  this.board[minY + i][startX] = { ...originalTiles[i], element: tileElements[i] };
                }
              }
              this.renderBoard();
              this.gameState = "playerTurn";
            }, 200);
          }
        }, 200);
      }

      resolveMatches(selectedX = null, selectedY = null) {
        if (this.gameOver) return false;
        const matches = this.checkMatches();
        if (matches.length > 0) {
          const allMatchedTiles = new Set();
          let totalDamage = 0;
          const attacker = this.currentTurn;
          const defender = this.currentTurn === this.player1 ? this.player2 : this.player1;

          matches.forEach(match => {
            const damage = this.handleMatch(match);
            if (damage > 0) totalDamage += damage;
            match.coordinates.forEach(coord => allMatchedTiles.add(coord));
          });

          if (totalDamage > 0 && !this.gameOver) {
            setTimeout(() => this.animateRecoil(defender, totalDamage), 100);
          }

          setTimeout(() => {
            allMatchedTiles.forEach(tile => {
              const [x, y] = tile.split(",").map(Number);
              if (this.board[y][x].element) {
                this.board[y][x].element.classList.add("matched");
              }
            });

            setTimeout(() => {
              allMatchedTiles.forEach(tile => {
                const [x, y] = tile.split(",").map(Number);
                this.board[y][x].type = null;
                this.board[y][x].element = null;
              });
              this.sounds.match.play();
              this.cascadeTiles(() => {
                this.endTurn();
              });
            }, 300);
          }, 200);

          return true;
        }
        return false;
      }

      checkMatches() {
        const matches = [];

        for (let y = 0; y < this.height; y++) {
          let startX = 0;
          for (let x = 0; x <= this.width; x++) {
            const currentType = x < this.width ? this.board[y][x].type : null;
            if (currentType !== this.board[y][startX].type || x === this.width) {
              const matchLength = x - startX;
              if (matchLength >= 3) {
                const matchCoordinates = new Set();
                for (let i = startX; i < x; i++) {
                  matchCoordinates.add(`${i},${y}`);
                }
                matches.push({ type: this.board[y][startX].type, coordinates: matchCoordinates });
              }
              startX = x;
            }
          }
        }

        for (let x = 0; x < this.width; x++) {
          let startY = 0;
          for (let y = 0; y <= this.height; y++) {
            const currentType = y < this.height ? this.board[y][x].type : null;
            if (currentType !== this.board[startY][x].type || y === this.height) {
              const matchLength = y - startY;
              if (matchLength >= 3) {
                const matchCoordinates = new Set();
                for (let i = startY; i < y; i++) {
                  matchCoordinates.add(`${x},${i}`);
                }
                matches.push({ type: this.board[startY][x].type, coordinates: matchCoordinates });
              }
              startY = y;
            }
          }
        }

        return matches;
      }

      handleMatch(match) {
        const attacker = this.currentTurn;
        const defender = this.currentTurn === this.player1 ? this.player2 : this.player1;
        const type = match.type;
        const size = match.coordinates.size;
        let damage = 0;

        if (type === "first-attack" || type === "second-attack" || type === "special-attack" || type === "last-stand") {
          damage = Math.round(attacker.strength * (size === 3 ? 2 : size === 4 ? 3 : 4));
          if (type === "special-attack") damage = Math.round(damage * 1.2);
          if (attacker.boostActive) {
            damage += attacker.boostValue || 10;
            attacker.boostActive = false;
            log(`${attacker.name}'s Boost fades.`);
          }

          const tacticsChance = defender.tactics * 10;
          if (Math.random() * 100 < tacticsChance) {
            damage = Math.floor(damage / 2);
            log(`${defender.name}'s tactics halve the blow, taking only ${damage} damage!`);
          }

          if (defender.lastStandActive) {
            damage = Math.max(0, damage - 5);
            defender.lastStandActive = false;
            log(`${defender.name}'s Last Stand mitigates 5 damage!`);
          }

          if (type === "last-stand") {
            attacker.lastStandActive = true;
            log(`${attacker.name} uses Last Stand, dealing ${damage} damage to ${defender.name} and preparing to mitigate 5 damage on the next attack!`);
          } else {
            log(`${attacker.name} uses ${type === "first-attack" ? "Slash" : type === "second-attack" ? "Bite" : "Shadow Strike"} on ${defender.name} for ${damage} damage!`);
          }

          defender.health = Math.max(0, defender.health - damage);
          this.updateHealth(defender);
          this.checkGameOver();
          if (!this.gameOver) this.animateAttack(attacker, damage, type);
        } else if (type === "power-up") {
          this.usePowerup(attacker, defender);
          if (!this.gameOver) this.animatePowerup(attacker);
        }

        return damage;
      }

      cascadeTiles(callback) {
        if (this.gameOver) return;

        const moved = this.cascadeTilesWithoutRender();
        const fallClass = "falling";

        for (let x = 0; x < this.width; x++) {
          for (let y = 0; y < this.height; y++) {
            const tile = this.board[y][x];
            if (tile.element && tile.element.style.transform === "translate(0px, 0px)") {
              const emptyBelow = this.countEmptyBelow(x, y);
              if (emptyBelow > 0) {
                tile.element.classList.add(fallClass);
                tile.element.style.transform = `translate(0, ${emptyBelow * this.tileSizeWithGap}px)`;
              }
            }
          }
        }

        this.renderBoard();

        if (moved) {
          setTimeout(() => {
            if (this.gameOver) return;
            this.sounds.cascade.play();
            const hasMatches = this.resolveMatches();
            const tiles = document.querySelectorAll(`.${fallClass}`);
            tiles.forEach(tile => {
              tile.classList.remove(fallClass);
              tile.style.transform = "translate(0, 0)";
            });
            if (!hasMatches) {
              callback();
            }
          }, 300);
        } else {
          callback();
        }
      }

      cascadeTilesWithoutRender() {
        let moved = false;
        for (let x = 0; x < this.width; x++) {
          let emptySpaces = 0;
          for (let y = this.height - 1; y >= 0; y--) {
            if (!this.board[y][x].type) {
              emptySpaces++;
            } else if (emptySpaces > 0) {
              this.board[y + emptySpaces][x] = this.board[y][x];
              this.board[y][x] = { type: null, element: null };
              moved = true;
            }
          }
          for (let i = 0; i < emptySpaces; i++) {
            this.board[i][x] = this.createRandomTile();
            moved = true;
          }
        }
        return moved;
      }

      countEmptyBelow(x, y) {
        let count = 0;
        for (let i = y + 1; i < this.height; i++) {
          if (!this.board[i][x].type) {
            count++;
          } else {
            break;
          }
        }
        return count;
      }

      usePowerup(player, defender) {
        const reductionFactor = 1 - (defender.tactics * 0.05);
        let effectValue;

        if (player.powerup === "Heal") {
          effectValue = Math.floor(10 * reductionFactor);
          player.health = Math.min(player.maxHealth, player.health + effectValue);
          log(`${player.name} uses Heal, restoring ${effectValue} HP${defender.tactics > 0 ? ` (weakened by ${defender.name}'s tactics)` : ""}!`);
        } else if (player.powerup === "Boost Attack") {
          effectValue = Math.floor(10 * reductionFactor);
          player.boostActive = true;
          player.boostValue = effectValue;
          log(`${player.name} uses Power Surge, next attack +${effectValue} damage${defender.tactics > 0 ? ` (weakened by ${defender.name}'s tactics)` : ""}!`);
        } else if (player.powerup === "Regenerate") {
          effectValue = Math.floor(7 * reductionFactor);
          player.health = Math.min(player.maxHealth, player.health + effectValue);
          log(`${player.name} uses Regen, restoring ${effectValue} HP${defender.tactics > 0 ? ` (weakened by ${defender.name}'s tactics)` : ""}!`);
        } else if (player.powerup === "Minor Regen") {
          effectValue = Math.floor(5 * reductionFactor);
          player.health = Math.min(player.maxHealth, player.health + effectValue);
          log(`${player.name} uses Minor Regen, restoring ${effectValue} HP${defender.tactics > 0 ? ` (weakened by ${defender.name}'s tactics)` : ""}!`);
        }
        this.updateHealth(player);
      }

      updateHealth(player) {
        const healthBar = player === this.player1 ? p1Health : p2Health;
        const hpText = player === this.player1 ? p1Hp : p2Hp;

        const percentage = (player.health / player.maxHealth) * 100;
        healthBar.style.width = `${percentage}%`;

        let color;
        if (percentage > 75) {
          color = '#4CAF50'; // Green for >75%
        } else if (percentage > 50) {
          color = '#FFC105'; // Yellow for >50%
        } else if (percentage > 25) {
          color = '#FFA500'; // Orange for >25%
        } else {
          color = '#F44336'; // Red for ≤25%
        }

        healthBar.style.backgroundColor = color;
        hpText.textContent = `${player.health}/${player.maxHealth}`;
      }

      endTurn() {
        if (this.gameState === "gameOver") return;
        this.currentTurn = this.currentTurn === this.player1 ? this.player2 : this.player1;
        this.gameState = this.currentTurn === this.player1 ? "playerTurn" : "aiTurn";
        turnIndicator.textContent = `Level ${this.currentLevel + 1} - ${this.currentTurn === this.player1 ? "Player" : "Opponent"}'s Turn`;
        log(`Turn switched to ${this.currentTurn === this.player1 ? "Player" : "Opponent"}`);

        if (this.currentTurn === this.player2) {
          setTimeout(() => this.aiTurn(), 1000);
        }
      }

      aiTurn() {
        if (this.gameState !== "aiTurn" || this.currentTurn !== this.player2) return;
        this.gameState = "animating";
        const move = this.findAIMove();
        if (move) {
          log(`${this.player2.name} swaps tiles at (${move.x1}, ${move.y1}) to (${move.x2}, ${move.y2})`);
          this.slideTiles(move.x1, move.y1, move.x2, move.y2);
        } else {
          log(`${this.player2.name} passes...`);
          this.endTurn();
        }
      }

      findAIMove() {
        for (let y = 0; y < this.height; y++) {
          for (let x = 0; x < this.width; x++) {
            if (x < this.width - 1 && this.canMakeMatch(x, y, x + 1, y)) return { x1: x, y1: y, x2: x + 1, y2: y };
            if (y < this.height - 1 && this.canMakeMatch(x, y, x, y + 1)) return { x1: x, y1: y, x2: x, y2: y + 1 };
          }
        }
        return null;
      }

      canMakeMatch(x1, y1, x2, y2) {
        const temp1 = { ...this.board[y1][x1] };
        const temp2 = { ...this.board[y2][x2] };
        this.board[y1][x1] = temp2;
        this.board[y2][x2] = temp1;
        const matches = this.checkMatches().length > 0;
        this.board[y1][x1] = temp1;
        this.board[y2][x2] = temp2;
        return matches;
      }

      checkGameOver() {
        const tryAgainButton = document.getElementById("try-again");
        if (this.player1.health <= 0) {
          this.gameOver = true;
          this.gameState = "gameOver";
          gameOver.textContent = "You Lose!";
          turnIndicator.textContent = "Game Over";
          log(`${this.player2.name} defeats ${this.player1.name}!`);
          tryAgainButton.textContent = "TRY AGAIN";
          document.getElementById("game-over-container").style.display = "block";
          this.sounds.loss.play();
          const damagedUrl = `https://skulliance.io/staking/images/monstrocity/battle-damaged/${this.player1.name.toLowerCase().replace(/ /g, '-')}.png`;
          p1Image.src = damagedUrl;
          p1Image.classList.add('loser');
          p2Image.classList.add('winner');
          this.renderBoard();
        } else if (this.player2.health <= 0) {
          this.gameOver = true;
          this.gameState = "gameOver";
          gameOver.textContent = "You Win!";
          turnIndicator.textContent = "Game Over";
          log(`${this.player1.name} defeats ${this.player2.name}!`);
          tryAgainButton.textContent = this.currentLevel === opponentsConfig.length - 1 ? "START OVER" : "NEXT LEVEL";
          document.getElementById("game-over-container").style.display = "block";
          if (this.currentLevel === opponentsConfig.length - 1) {
            this.sounds.finalWin.play();
          } else {
            this.sounds.win.play();
          }
          const damagedUrl = `https://skulliance.io/staking/images/monstrocity/battle-damaged/${this.player2.name.toLowerCase().replace(/ /g, '-')}.png`;
          p2Image.src = damagedUrl;
          p2Image.classList.add('loser');
          p1Image.classList.add('winner');
          this.renderBoard();
        }
      }

            applyAnimation(imageElement, shiftX, glowClass, duration) {
        const originalTransform = imageElement.style.transform || '';
        const scalePart = originalTransform.includes('scaleX') ? originalTransform.match(/scaleX\([^)]+\)/)[0] : '';
        imageElement.style.transition = `transform ${duration / 2 / 1000}s linear`;
        imageElement.style.transform = `translateX(${shiftX}px) ${scalePart}`;
        imageElement.classList.add(glowClass);
        setTimeout(() => {
          imageElement.style.transform = scalePart;
          setTimeout(() => {
            imageElement.classList.remove(glowClass);
          }, duration / 2);
        }, duration / 2);
      }

      animateAttack(attacker, damage, type) {
        const imageElement = attacker === this.player1 ? p1Image : p2Image;
        const shiftDirection = attacker === this.player1 ? 1 : -1;
        const shiftDistance = Math.min(10, 2 + damage * 0.4);
        const shiftX = shiftDirection * shiftDistance;
        const glowClass = `glow-${type}`;
        this.applyAnimation(imageElement, shiftX, glowClass, 200);
      }

      animatePowerup(attacker) {
        const imageElement = attacker === this.player1 ? p1Image : p2Image;
        this.applyAnimation(imageElement, 0, 'glow-power-up', 200);
      }

      animateRecoil(defender, totalDamage) {
        const imageElement = defender === this.player1 ? p1Image : p2Image;
        const shiftDirection = defender === this.player1 ? -1 : 1;
        const shiftDistance = Math.min(10, 2 + totalDamage * 0.4);
        const shiftX = shiftDirection * shiftDistance;
        this.applyAnimation(imageElement, shiftX, 'glow-recoil', 200);
      }
    }

    function randomChoice(arr) {
      return arr[Math.floor(Math.random() * arr.length)];
    }

    function log(message) {
      const li = document.createElement("li");
      li.textContent = message;
      battleLog.insertBefore(li, battleLog.firstChild);
      if (battleLog.children.length > 10) battleLog.removeChild(battleLog.lastChild);
    }

    const turnIndicator = document.getElementById("turn-indicator");
    const p1Name = document.getElementById("p1-name");
    const p1Image = document.getElementById("p1-image");
    const p1Health = document.getElementById("p1-health");
    const p1Hp = document.getElementById("p1-hp");
    const p1Strength = document.getElementById("p1-strength");
    const p1Speed = document.getElementById("p1-speed");
    const p1Tactics = document.getElementById("p1-tactics");
    const p1Size = document.getElementById("p1-size");
    const p1Powerup = document.getElementById("p1-powerup");
    const p1Type = document.getElementById("p1-type");
    const p2Name = document.getElementById("p2-name");
    const p2Image = document.getElementById("p2-image");
    const p2Health = document.getElementById("p2-health");
    const p2Hp = document.getElementById("p2-hp");
    const p2Strength = document.getElementById("p2-strength");
    const p2Speed = document.getElementById("p2-speed");
    const p2Tactics = document.getElementById("p2-tactics");
    const p2Size = document.getElementById("p2-size");
    const p2Powerup = document.getElementById("p2-powerup");
    const p2Type = document.getElementById("p2-type");
    const battleLog = document.getElementById("battle-log");
    const gameOver = document.getElementById("game-over");

    const game = new MonstrocityMatch3();
  </script>
</body>
</html>