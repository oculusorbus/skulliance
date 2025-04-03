<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Monstrocity Match-3</title>
  <style>body {
  font-family: Arial, sans-serif;
  background-color: #1a1a1a;
  color: #fff;
  display: flex;
  justify-content: center;
  align-items: center;
  min-height: 100vh;
  margin: 0;
}

.game-container {
  text-align: center;
  padding: 20px;
  background-color: #2a2a2a;
  border-radius: 10px;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.5);
}

h1 { color: #ffcc00; }

.battlefield {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin: 20px 0;
}

.character {
  width: 25%;
  padding: 10px;
  background-color: #3a3a3a;
  border-radius: 5px;
  position: relative;
}

.health-bar {
  width: 100%;
  height: 20px;
  background-color: #555;
  border-radius: 5px;
  overflow: hidden;
}

.health {
  height: 100%;
  background-color: #ff4444;
  transition: width 0.5s ease;
}

.health.shake {
  animation: shake 0.5s;
}

@keyframes shake {
  0% { transform: translateX(0); }
  25% { transform: translateX(-5px); }
  50% { transform: translateX(5px); }
  75% { transform: translateX(-5px); }
  100% { transform: translateX(0); }
}

.character.slash::after {
  content: '';
  position: absolute;
  top: -50%;
  left: -50%;
  width: 200%;
  height: 200%;
  background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.8), transparent);
  animation: slash 0.4s linear;
}

@keyframes slash {
  0% { transform: translateX(-100%) rotate(45deg); }
  100% { transform: translateX(100%) rotate(45deg); }
}

.character.punch {
  animation: punch 0.3s ease;
}

@keyframes punch {
  0% { transform: scale(1); }
  50% { transform: scale(1.1); }
  100% { transform: scale(1); }
}

.character.burst::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0;
  height: 0;
  background: radial-gradient(circle, rgba(255, 204, 0, 0.8), transparent);
  animation: burst 0.6s ease-out;
  transform: translate(-50%, -50%);
}

@keyframes burst {
  0% { width: 0; height: 0; opacity: 1; }
  100% { width: 200px; height: 200px; opacity: 0; }
}

.game-container.explosion {
  animation: screenShake 0.5s;
}

@keyframes screenShake {
  0% { transform: translate(0, 0); }
  25% { transform: translate(-5px, 5px); }
  50% { transform: translate(5px, -5px); }
  75% { transform: translate(-5px, 5px); }
  100% { transform: translate(0, 0); }
}

.character.powerup-heal::after {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0;
  height: 0;
  background: radial-gradient(circle, rgba(0, 255, 0, 0.8), transparent);
  animation: healBurst 0.6s ease-out;
  transform: translate(-50%, -50%);
}

@keyframes healBurst {
  0% { width: 0; height: 0; opacity: 1; }
  100% { width: 150px; height: 150px; opacity: 0; }
}

.match3-board {
  width: 250px;
  height: 250px;
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  grid-template-rows: repeat(5, 1fr);
  gap: 5px;
  background-color: #333;
  padding: 10px;
  border-radius: 5px;
  user-select: none;
  position: relative;
}

.tile {
  width: 100%;
  height: 100%;
  border-radius: 5px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  color: #fff;
  transition: transform 0.2s ease, opacity 0.3s ease;
  cursor: pointer;
  position: relative;
  z-index: 1;
}

.tile.dragging {
  transform: scale(1.05);
  opacity: 0.8;
  z-index: 10;
}

.tile.matched {
  animation: matchFade 0.3s ease forwards;
}

.tile.falling {
  transition: transform 0.3s ease-out;
}

.tile.first { background-color: #4CAF50; }
.tile.second { background-color: #2196F3; }
.tile.special { background-color: #FFC107; }
.tile.last-stand { background-color: #F44336; }
.tile.powerup { background-color: #9C27B0; }

@keyframes matchFade {
  0% { transform: scale(1); opacity: 1; }
  50% { transform: scale(1.2); opacity: 0.5; }
  100% { transform: scale(0); opacity: 0; }
}

.log {
  margin-top: 20px;
  text-align: left;
}

#battle-log {
  list-style: none;
  padding: 0;
  max-height: 200px;
  overflow-y: auto;
  background-color: #333;
  padding: 10px;
  border-radius: 5px;
}

#battle-log li {
  margin: 5px 0;
  opacity: 0;
  animation: fadeIn 0.5s forwards;
}

@keyframes fadeIn {
  to { opacity: 1; }
}

button {
  padding: 10px 20px;
  background-color: #ffcc00;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-weight: bold;
}

button:hover { background-color: #e6b800; }</style>
</head>
<body>
  <div class="game-container">
    <h1>Monstrocity Match-3</h1>
    <div class="battlefield">
      <div class="character" id="player1">
        <h2>Player 1: <span id="p1-name"></span></h2>
        <p>Title: <span id="p1-subject"></span></p>
        <div class="health-bar"><div class="health" id="p1-health"></div></div>
        <p>Health: <span id="p1-hp"></span></p>
        <p>Type: <span id="p1-type"></span></p>
        <p>Keywords: <span id="p1-keywords"></span></p>
        <p>Tactics: <span id="p1-tactics"></span></p>
        <p>Powerup: <span id="p1-powerup"></span></p>
      </div>
      <div class="match3-board" id="match3-board"></div>
      <div class="character" id="player2">
        <h2>Player 2: <span id="p2-name"></span></h2>
        <p>Title: <span id="p2-subject"></span></p>
        <div class="health-bar"><div class="health" id="p2-health"></div></div>
        <p>Health: <span id="p2-hp"></span></p>
        <p>Type: <span id="p2-type"></span></p>
        <p>Keywords: <span id="p2-keywords"></span></p>
        <p>Tactics: <span id="p2-tactics"></span></p>
        <p>Powerup: <span id="p2-powerup"></span></p>
      </div>
    </div>
    <div class="log">
      <h3>Battle Log</h3>
      <ul id="battle-log"></ul>
    </div>
    <button id="restart">Restart Game</button>
  </div>
<script type="text/javascript">
  const monstrocityData = {
    aliases: ["Koipon", "Billandar and Ted", "Katastrophy", "Ouchie", "Jarhead", "Texby", "Slime mind", "Mandiblus", "Merdock", "Dankle", "Drake", "Goblin ganger", "Spydrax", "Craig"],
    types: ["Battle damaged", "Leader", "Base"],
    sizes: ["Large", "Small", "Medium"],
    speeds: [1, 2, 3, 4, 5, 6, 7],
    strengths: [1, 2, 3, 4, 5, 6, 7, 8],
    tactics: [1, 2, 3, 4, 5, 6, 7],
    powerups: ["Bloody", "Cardano", "Ada", "None"],
    keywords: ["Spy", "Acid", "Claw", "Gang", "Mean", "Nice", "Gross", "Slime", "Swamp", "Zombie", "Stealth", "Military", "Pugilist", "Absorbent", "Hive mind", "Tuber", "Land", "Bloodied", "Water", "Fish", "Ronin", "Knife fighter", "Scout", "Urban", "Healer", "Mutant", "Sea", "Merman", "Mechanic", "Space", "Chopper", "Alien", "Feline", "Leader"],
    attacks: {
      first: ["A", "B"],
      second: ["B", "C"],
      special: ["1", "2"],
      lastStand: ["Pile up", "Armed attack", "Shuriken shed", "Tummy rumbles", "Won't go down", "Nuclear option", "Shadow warrior", "Slip and slide", "Electro grapple", "Intelligence overload", "Spinning whirlwind attack"]
    },
    subjects: ["The", "Crimson", "Silent", "Furious", "Ancient", "Cosmic", "Slithering", "Iron", "Shadow", "Mystic"]
  };

  // Game State
  let player1, player2, currentTurn;
  let board = [];
  const GRID_SIZE = 5;
  let draggedTile = null;
  let isProcessing = false;
  const TILE_SIZE = 50; // Approx tile size with gap

  // DOM Elements
  const p1Name = document.getElementById("p1-name");
  const p1Subject = document.getElementById("p1-subject");
  const p1Hp = document.getElementById("p1-hp");
  const p1Health = document.getElementById("p1-health");
  const p1Type = document.getElementById("p1-type");
  const p1Keywords = document.getElementById("p1-keywords");
  const p1Tactics = document.getElementById("p1-tactics");
  const p1Powerup = document.getElementById("p1-powerup");
  const p2Name = document.getElementById("p2-name");
  const p2Subject = document.getElementById("p2-subject");
  const p2Hp = document.getElementById("p2-hp");
  const p2Health = document.getElementById("p2-health");
  const p2Type = document.getElementById("p2-type");
  const p2Keywords = document.getElementById("p2-keywords");
  const p2Tactics = document.getElementById("p2-tactics");
  const p2Powerup = document.getElementById("p2-powerup");
  const match3Board = document.getElementById("match3-board");
  const battleLog = document.getElementById("battle-log");
  const restartBtn = document.getElementById("restart");

  // Utility Functions
  function randomChoice(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
  }

  function generateCharacter() {
    const size = randomChoice(monstrocityData.sizes);
    const strength = randomChoice(monstrocityData.strengths);
    const type = randomChoice(monstrocityData.types);
    return {
      name: randomChoice(monstrocityData.aliases),
      subject: `${randomChoice(monstrocityData.subjects)} ${randomChoice(monstrocityData.keywords)}`,
      type: type,
      size: size,
      speed: randomChoice(monstrocityData.speeds),
      strength: strength,
      tactics: randomChoice(monstrocityData.tactics),
      powerup: randomChoice(monstrocityData.powerups),
      keywords: [randomChoice(monstrocityData.keywords), randomChoice(monstrocityData.keywords)].filter((v, i, a) => a.indexOf(v) === i),
      attacks: {
        first: randomChoice(monstrocityData.attacks.first),
        second: randomChoice(monstrocityData.attacks.second),
        special: randomChoice(monstrocityData.attacks.special),
        lastStand: randomChoice(monstrocityData.attacks.lastStand)
      },
      health: (size === "Large" ? 100 : size === "Medium" ? 75 : 50) + strength * 5 + (type === "Battle damaged" ? 20 : 0),
      maxHealth: (size === "Large" ? 100 : size === "Medium" ? 75 : 50) + strength * 5 + (type === "Battle damaged" ? 20 : 0),
      powerupUsed: false,
      powerupTurns: 0
    };
  }

  function log(message) {
    const li = document.createElement("li");
    li.textContent = message;
    battleLog.insertBefore(li, battleLog.firstChild);
  }

  // Match-3 Logic
  const allTileTypes = ["first", "second", "special", "powerup", "last-stand"];

  function generateTile() {
    return randomChoice(allTileTypes);
  }

  function initializeBoard() {
    board = [];
    match3Board.innerHTML = "";
    for (let y = 0; y < GRID_SIZE; y++) {
      const row = [];
      for (let x = 0; x < GRID_SIZE; x++) {
        let type;
        do {
          type = generateTile();
        } while (
          (x >= 2 && row[x-1]?.type === type && row[x-2]?.type === type) ||
          (y >= 2 && board[y-1]?.[x]?.type === type && board[y-2]?.[x]?.type === type)
        );
        const tile = document.createElement("div");
        tile.classList.add("tile", type);
        tile.dataset.x = x;
        tile.dataset.y = y;
        tile.textContent = type.charAt(0).toUpperCase();
        tile.addEventListener("mousedown", handleMouseDown);
        match3Board.appendChild(tile);
        row.push({ type, element: tile });
      }
      board.push(row);
    }
    resolveMatches(currentTurn, currentTurn === player1 ? player2 : player1, false);
  }

  function handleMouseDown(e) {
    if (isProcessing || currentTurn !== player1) return;
    const tile = e.target;
    const x = parseInt(tile.dataset.x);
    const y = parseInt(tile.dataset.y);
    draggedTile = { x, y, element: tile, startX: e.clientX, startY: e.clientY, direction: null };
    tile.classList.add("dragging");

    document.addEventListener("mousemove", handleMouseMove);
    document.addEventListener("mouseup", handleMouseUp);
  }

  function handleMouseMove(e) {
    if (!draggedTile) return;
    const dx = e.clientX - draggedTile.startX;
    const dy = e.clientY - draggedTile.startY;
    draggedTile.element.style.transition = "";

    if (!draggedTile.direction) {
      if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 5) draggedTile.direction = "row";
      else if (Math.abs(dy) > 5) draggedTile.direction = "column";
    }

    if (draggedTile.direction === "row") {
      const constrainedX = Math.max(0, Math.min((GRID_SIZE - 1) * TILE_SIZE, draggedTile.x * TILE_SIZE + dx));
      draggedTile.element.style.transform = `translate(${constrainedX - draggedTile.x * TILE_SIZE}px, 0) scale(1.05)`;
    } else if (draggedTile.direction === "column") {
      const constrainedY = Math.max(0, Math.min((GRID_SIZE - 1) * TILE_SIZE, draggedTile.y * TILE_SIZE + dy));
      draggedTile.element.style.transform = `translate(0, ${constrainedY - draggedTile.y * TILE_SIZE}px) scale(1.05)`;
    }
  }

  function handleMouseUp(e) {
    if (!draggedTile) return;
    document.removeEventListener("mousemove", handleMouseMove);
    document.removeEventListener("mouseup", handleMouseUp);

    const dx = e.clientX - draggedTile.startX;
    const dy = e.clientY - draggedTile.startY;
    let targetX = draggedTile.x;
    let targetY = draggedTile.y;

    draggedTile.element.style.transition = "transform 0.2s ease";
    draggedTile.element.classList.remove("dragging");

    if (draggedTile.direction === "row") {
      targetX = Math.round((draggedTile.x * TILE_SIZE + dx) / TILE_SIZE);
      targetX = Math.max(0, Math.min(GRID_SIZE - 1, targetX));
    } else if (draggedTile.direction === "column") {
      targetY = Math.round((draggedTile.y * TILE_SIZE + dy) / TILE_SIZE);
      targetY = Math.max(0, Math.min(GRID_SIZE - 1, targetY));
    }

    if (targetX !== draggedTile.x || targetY !== draggedTile.y) {
      isProcessing = true;
      slideTiles(draggedTile.x, draggedTile.y, targetX, targetY, currentTurn, currentTurn === player1 ? player2 : player1);
    } else {
      draggedTile.element.style.transform = "translate(0, 0)";
      draggedTile = null;
    }
  }

  function slideTiles(startX, startY, endX, endY, attacker, defender) {
    // Define minX, minY, maxX, maxY at the top to avoid undefined errors
    let minX = 0, minY = 0, maxX = 0, maxY = 0;
    const originalTiles = [];
    const tileElements = [];
    let direction;

    // Determine direction and collect tiles to swap
    if (startY === endY) {
      direction = startX < endX ? 1 : -1;
      minX = Math.min(startX, endX);
      maxX = Math.max(startX, endX);
      for (let x = minX; x <= maxX; x++) {
        originalTiles.push({ ...board[startY][x] });
        tileElements.push(board[startY][x].element);
      }
    } else if (startX === endX) {
      direction = startY < endY ? 1 : -1;
      minY = Math.min(startY, endY);
      maxY = Math.max(startY, endY);
      for (let y = minY; y <= maxY; y++) {
        originalTiles.push({ ...board[y][startX] });
        tileElements.push(board[y][startX].element);
      }
    }

    const selectedElement = board[startY][startX].element;
    const dx = (endX - startX) * TILE_SIZE;
    const dy = (endY - startY) * TILE_SIZE;

    // Animate the swap
    selectedElement.style.transform = `translate(${dx}px, ${dy}px)`;
    let i = 0;
    if (startY === endY) {
      for (let x = minX; x <= maxX; x++) {
        if (x === startX) continue;
        const offsetX = direction * -TILE_SIZE * (x - startX) / Math.abs(endX - startX);
        tileElements[i].style.transform = `translate(${offsetX}px, 0)`;
        i++;
      }
    } else {
      for (let y = minY; y <= maxY; y++) {
        if (y === startY) continue;
        const offsetY = direction * -TILE_SIZE * (y - startY) / Math.abs(endY - startY);
        tileElements[i].style.transform = `translate(0, ${offsetY}px)`;
        i++;
      }
    }

    // Perform the swap in the board array
    setTimeout(() => {
      if (startY === endY) {
        const row = board[startY];
        const tempRow = [...row];
        if (startX < endX) {
          for (let x = startX; x < endX; x++) row[x] = tempRow[x + 1];
        } else {
          for (let x = startX; x > endX; x--) row[x] = tempRow[x - 1];
        }
        row[endX] = tempRow[startX];
        // Update dataset for all affected tiles
        for (let x = minX; x <= maxX; x++) {
          board[startY][x].element.dataset.x = x;
          board[startY][x].element.dataset.y = startY;
        }
      } else {
        const tempCol = [];
        for (let y = 0; y < GRID_SIZE; y++) tempCol[y] = { ...board[y][startX] };
        if (startY < endY) {
          for (let y = startY; y < endY; y++) board[y][startX] = tempCol[y + 1];
        } else {
          for (let y = startY; y > endY; y--) board[y][startX] = tempCol[y - 1];
        }
        board[endY][endX] = tempCol[startY];
        // Update dataset for all affected tiles
        for (let y = minY; y <= maxY; y++) {
          board[y][startX].element.dataset.x = startX;
          board[y][startX].element.dataset.y = y;
        }
      }

      // Reset transforms
      tileElements.forEach(el => el.style.transform = "translate(0, 0)");
      selectedElement.style.transform = "translate(0, 0)";

      // Check for matches and resolve them
      const hasMatches = resolveMatches(attacker, defender, true);

      // If no matches, revert the swap
      if (!hasMatches) {
        setTimeout(() => {
          if (startY === endY) {
            for (let x = minX, i = 0; x <= maxX; x++, i++) {
              board[startY][x] = { ...originalTiles[i], element: tileElements[i] };
              board[startY][x].element.dataset.x = x;
              board[startY][x].element.dataset.y = startY;
              board[startY][x].element.style.transform = "translate(0, 0)";
            }
          } else {
            for (let y = minY, i = 0; y <= maxY; y++, i++) {
              board[y][startX] = { ...originalTiles[i], element: tileElements[i] };
              board[y][startX].element.dataset.x = startX;
              board[y][startX].element.dataset.y = y;
              board[y][startX].element.style.transform = "translate(0, 0)";
            }
          }
          renderBoard();
          isProcessing = false;
          draggedTile = null;
          // Switch turn if no matches
          if (defender.health > 0) {
            currentTurn = defender;
            log(`${currentTurn === player1 ? "Player 1" : "Player 2"}'s turn...`);
            if (currentTurn === player2) {
              setTimeout(() => aiTurn(), 1000);
            }
          }
        }, 200);
      } else {
        draggedTile = null;
      }
    }, 200);
  }

  function renderBoard() {
    match3Board.innerHTML = "";
    for (let y = 0; y < GRID_SIZE; y++) {
      for (let x = 0; x < GRID_SIZE; x++) {
        const tile = board[y][x];
        const tileElement = document.createElement("div");
        tileElement.className = `tile ${tile.type}`;
        tileElement.dataset.x = x;
        tileElement.dataset.y = y;
        tileElement.textContent = tile.type.charAt(0).toUpperCase();
        tileElement.addEventListener("mousedown", handleMouseDown);
        match3Board.appendChild(tileElement);
        tile.element = tileElement;
        tile.element.style.transform = "translate(0, 0)"; // Ensure no lingering transforms
      }
    }
  }

  function findMatches() {
    const matches = [];
    const checked = new Set();

    // Check rows
    for (let y = 0; y < GRID_SIZE; y++) {
      let count = 1;
      let start = 0;
      for (let x = 1; x < GRID_SIZE; x++) {
        if (board[y][x].type === board[y][x - 1].type) {
          count++;
          if (count >= 3 && x === GRID_SIZE - 1) {
            const matchTiles = board[y].slice(start, start + count).map((t, i) => ({ x: start + i, y }));
            matches.push({ type: board[y][start].type, tiles: matchTiles });
            matchTiles.forEach(t => checked.add(`${t.x},${t.y}`));
          }
        } else {
          if (count >= 3) {
            const matchTiles = board[y].slice(start, x).map((t, i) => ({ x: start + i, y }));
            matches.push({ type: board[y][start].type, tiles: matchTiles });
            matchTiles.forEach(t => checked.add(`${t.x},${t.y}`));
          }
          count = 1;
          start = x;
        }
      }
    }

    // Check columns
    for (let x = 0; x < GRID_SIZE; x++) {
      let count = 1;
      let start = 0;
      for (let y = 1; y < GRID_SIZE; y++) {
        if (board[y][x].type === board[y - 1][x].type) {
          count++;
          if (count >= 3 && y === GRID_SIZE - 1) {
            const matchTiles = board.slice(start, start + count).map((r, i) => ({ x, y: start + i }));
            if (!matchTiles.every(t => checked.has(`${t.x},${t.y}`))) {
              matches.push({ type: board[start][x].type, tiles: matchTiles });
            }
          }
        } else {
          if (count >= 3) {
            const matchTiles = board.slice(start, y).map((r, i) => ({ x, y: start + i }));
            if (!matchTiles.every(t => checked.has(`${t.x},${t.y}`))) {
              matches.push({ type: board[start][x].type, tiles: matchTiles });
            }
          }
          count = 1;
          start = y;
        }
      }
    }

    return matches;
  }

  function resolveMatches(attacker, defender, switchTurn) {
    const matches = findMatches();
    if (matches.length === 0) {
      isProcessing = false;
      if (switchTurn && defender.health > 0) {
        currentTurn = defender;
        log(`${currentTurn === player1 ? "Player 1" : "Player 2"}'s turn...`);
        if (currentTurn === player2) {
          setTimeout(() => aiTurn(), 1000);
        }
      }
      return false;
    }

    matches.forEach(match => {
      const tileCount = match.tiles.length;
      let strength = tileCount === 3 ? "Normal" : tileCount === 4 ? "Powerful" : "Brutal";
      if (match.type === "first") attack(attacker, defender, "first", strength);
      else if (match.type === "second") attack(attacker, defender, "second", strength);
      else if (match.type === "special") attack(attacker, defender, "special", strength);
      else if (match.type === "last-stand") attack(attacker, defender, "lastStand", strength);
      else if (match.type === "powerup") usePowerupEffect(attacker, strength);

      match.tiles.forEach(({ x, y }) => {
        board[y][x].element.classList.add("matched");
      });
    });

    setTimeout(() => {
      matches.forEach(match => {
        match.tiles.forEach(({ x, y }) => {
          board[y][x].element.remove();
          board[y][x] = { type: null, element: null };
        });
      });
      cascadeTiles(() => resolveMatches(attacker, defender, switchTurn));
    }, 300);
    return true;
  }

  function cascadeTiles(callback) {
    let moved = false;
    for (let x = 0; x < GRID_SIZE; x++) {
      let bottom = GRID_SIZE - 1;
      for (let y = GRID_SIZE - 1; y >= 0; y--) {
        if (board[y][x].type) {
          if (y !== bottom) {
            board[bottom][x] = board[y][x];
            board[bottom][x].element.classList.add("falling");
            board[bottom][x].element.style.transform = `translateY(${(bottom - y) * TILE_SIZE}px)`;
            board[bottom][x].element.dataset.y = bottom;
            board[y][x] = { type: null, element: null };
            moved = true;
          }
          bottom--;
        }
      }
    }

    setTimeout(() => {
      for (let x = 0; x < GRID_SIZE; x++) {
        for (let y = 0; y < GRID_SIZE; y++) {
          if (board[y][x].element) {
            board[y][x].element.classList.remove("falling");
            board[y][x].element.style.transform = "translate(0, 0)";
          }
        }
      }
      refillBoard(callback);
    }, moved ? 300 : 0);
  }

  function refillBoard(callback) {
    for (let x = 0; x < GRID_SIZE; x++) {
      for (let y = 0; y < GRID_SIZE; y++) {
        if (!board[y][x].type) {
          const type = generateTile();
          const tile = document.createElement("div");
          tile.classList.add("tile", type);
          tile.dataset.x = x;
          tile.dataset.y = y;
          tile.textContent = type.charAt(0).toUpperCase();
          tile.addEventListener("mousedown", handleMouseDown);
          tile.style.opacity = "0";
          match3Board.appendChild(tile);
          board[y][x] = { type, element: tile };
          setTimeout(() => tile.style.opacity = "1", 10);
        }
      }
    }
    setTimeout(callback, 200);
  }

  // Game Logic
  function initGame() {
    player1 = generateCharacter();
    player2 = generateCharacter();
    currentTurn = player1.speed >= player2.speed ? player1 : player2;

    p1Name.textContent = player1.name;
    p1Subject.textContent = player1.subject;
    p1Hp.textContent = `${player1.health}/${player1.maxHealth}`;
    p1Health.style.width = "100%";
    p1Type.textContent = player1.type;
    p1Keywords.textContent = player1.keywords.join(", ");
    p1Tactics.textContent = player1.tactics;
    p1Powerup.textContent = player1.powerup;
    p2Name.textContent = player2.name;
    p2Subject.textContent = player2.subject;
    p2Hp.textContent = `${player2.health}/${player2.maxHealth}`;
    p2Health.style.width = "100%";
    p2Type.textContent = player2.type;
    p2Keywords.textContent = player2.keywords.join(", ");
    p2Tactics.textContent = player2.tactics;
    p2Powerup.textContent = player2.powerup;
    battleLog.innerHTML = "";
    isProcessing = false;

    log(`${currentTurn.name} (Speed: ${currentTurn.speed}) goes first!`);
    initializeBoard();

    if (currentTurn === player2) {
      log("AI (Player 2) takes first turn...");
      setTimeout(() => aiTurn(), 1000);
    }
  }

  function attack(attacker, defender, attackType, strength) {
    let damage = attacker.strength * 2;
    if (attackType === "special") damage *= 1.2;
    if (attackType === "lastStand") damage *= 1.5;
    if (attacker.type === "Leader") damage *= 1.1;
    if (strength === "Powerful") damage *= 1.3;
    if (strength === "Brutal") damage *= 1.6;

    if (attacker.powerupTurns > 0) {
      if (attacker.powerup === "Cardano" && Math.random() < 0.2) damage = 0;
      if (attacker.powerup === "Ada") damage += 5;
      attacker.powerupTurns--;
      if (attacker.powerupTurns === 0) log(`${attacker.name}'s ${attacker.powerup} ends!`);
    }

    if (damage > 0) {
      defender.health = Math.max(0, defender.health - damage);
      const defenderHealth = defender === player1 ? p1Health : p2Health;
      const defenderChar = defender === player1 ? document.getElementById("player1") : document.getElementById("player2");
      defenderHealth.classList.add("shake");
      if (attackType === "first") defenderChar.classList.add("slash");
      else if (attackType === "second") defenderChar.classList.add("punch");
      else if (attackType === "special") defenderChar.classList.add("burst");
      else if (attackType === "lastStand") document.querySelector(".game-container").classList.add("explosion");
      setTimeout(() => {
        defenderHealth.classList.remove("shake");
        defenderChar.classList.remove("slash", "punch", "burst");
        document.querySelector(".game-container").classList.remove("explosion");
      }, 500);
    }

    log(`${attacker.name} uses ${attackType === "first" ? attacker.attacks.first : attackType === "second" ? attacker.attacks.second : attackType === "special" ? attacker.attacks.special : attacker.attacks.lastStand} (${strength}) on ${defender.name} for ${damage.toFixed(1)} damage!`);

    const defenderHealth = defender === player1 ? p1Health : p2Health;
    const defenderHp = defender === player1 ? p1Hp : p2Hp;
    defenderHealth.style.width = `${(defender.health / defender.maxHealth) * 100}%`;
    defenderHp.textContent = `${defender.health}/${defender.maxHealth}`;

    if (defender.health <= 0) {
      log(`${defender.name} is defeated! ${attacker.name} wins!`);
      isProcessing = true;
    }
  }

  function usePowerupEffect(player, strength) {
    if (player.powerupUsed || player.powerup === "None") return;

    let effectMultiplier = strength === "Powerful" ? 1.3 : strength === "Brutal" ? 1.6 : 1;

    if (player.powerup === "Bloody") {
      const heal = 10 * effectMultiplier;
      player.health = Math.min(player.health + heal, player.maxHealth);
      log(`${player.name} uses Bloody (${strength}) and heals ${heal.toFixed(1)} HP!`);
      const playerChar = player === player1 ? document.getElementById("player1") : document.getElementById("player2");
      playerChar.classList.add("powerup-heal");
      setTimeout(() => playerChar.classList.remove("powerup-heal"), 600);
    } else if (player.powerup === "Cardano") {
      player.powerupTurns = Math.floor(3 * effectMultiplier);
      log(`${player.name} uses Cardano (${strength}) for +20% dodge for ${player.powerupTurns} turns!`);
    } else if (player.powerup === "Ada") {
      player.powerupTurns = Math.floor(3 * effectMultiplier);
      log(`${player.name} uses Ada (${strength}) for +5 damage for ${player.powerupTurns} turns!`);
    }

    player.powerupUsed = true;
    const playerHealth = player === player1 ? p1Health : p2Health;
    const playerHp = player === player1 ? p1Hp : p2Hp;
    playerHealth.style.width = `${(player.health / player.maxHealth) * 100}%`;
    playerHp.textContent = `${player.health}/${player.maxHealth}`;
  }

  function aiTurn() {
    if (isProcessing) return;
    isProcessing = true;

    const matches = findMatches();
    if (matches.length > 0) {
      log("AI found existing matches!");
      resolveMatches(player2, player1, true);
      return;
    }

    const possibleSwaps = [];
    for (let y = 0; y < GRID_SIZE; y++) {
      for (let x = 0; x < GRID_SIZE - 1; x++) {
        swapTilesAI(y, x, y, x + 1);
        const matches = findMatches();
        if (matches.length > 0) possibleSwaps.push({ startX: x, startY: y, endX: x + 1, endY: y, matches });
        swapTilesAI(y, x, y, x + 1);
      }
      for (let x = 0; x < GRID_SIZE; x++) {
        if (y < GRID_SIZE - 1) {
          swapTilesAI(y, x, y + 1, x);
          const matches = findMatches();
          if (matches.length > 0) possibleSwaps.push({ startX: x, startY: y, endX: x, endY: y + 1, matches });
          swapTilesAI(y, x, y + 1, x);
        }
      }
    }

    if (possibleSwaps.length > 0) {
      const bestSwap = possibleSwaps.reduce((best, curr) => {
        const currValue = curr.matches.reduce((sum, m) => sum + m.tiles.length, 0);
        const bestValue = best.matches.reduce((sum, m) => sum + m.tiles.length, 0);
        return currValue > bestValue ? curr : best;
      });
      log(`AI swaps (${bestSwap.startX}, ${bestSwap.startY}) with (${bestSwap.endX}, ${bestSwap.endY})`);
      slideTiles(bestSwap.startY, bestSwap.startX, bestSwap.endY, bestSwap.endX, player2, player1);
    } else {
      log("AI found no valid moves, turn skipped.");
      isProcessing = false;
      if (player1.health > 0) {
        currentTurn = player1;
        log("Player 1's turn...");
      }
    }
  }

  function swapTilesAI(startY, startX, endY, endX) {
    const tile1 = board[startY][startX];
    const tile2 = board[endY][endX];
    board[startY][startX] = tile2;
    board[endY][endX] = tile1;
  }

  // Event Listeners
  restartBtn.addEventListener("click", initGame);

  // Start Game
  initGame();
</script>
</body>
</html>