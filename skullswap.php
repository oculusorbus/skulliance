<?php
include_once 'db.php';
include 'message.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
include 'header.php';
?>
<style>
    /*
        body {
            background: #0F0F0F;
            margin: 0;
            height: 95vh;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
        }
    */
    #game-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
    }
    #hud {
        width: 100%;
        display: flex;
        justify-content: space-between;
        padding-bottom: 10px;
    }
    #score {
        font-size: 24px;
        font-family: Arial;
        color: #fff;
        text-align: left;
    }
    #matches {
        font-size: 24px;
        font-family: Arial;
        color: #fff;
        text-align: right;
    }
    #game-board {
        display: grid;
        gap: 0.5vh;
        background: #333;
        padding: 1vh;
        box-sizing: border-box;
        user-select: none;
        position: relative;
        touch-action: none;
    }
    .tile {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2vh;
        cursor: pointer;
        transition: transform 0.2s ease, filter 0.5s ease;
        position: relative;
        background: #444;
        box-sizing: border-box;
        padding: 0.25vh;
        z-index: 1;
    }
    .tile.game-over {
        filter: grayscale(100%);
    }
    .tile img {
        width: 80%;
        height: 80%;
        object-fit: contain;
        position: absolute;
        z-index: 1;
    }
    .tile::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0.6;
        z-index: 0;
    }
    .selected {
        transform: scale(1.05);
        border: 0.25vh solid white;
        z-index: 1000;
        pointer-events: none;
        padding: 0;
    }
    .matched {
        animation: matchAnimation 0.4s ease forwards;
    }
    .falling {
        transition: transform 0.3s ease-out;
    }
    .bomb-creation {
        animation: bombPopIn 0.5s ease forwards;
    }
    .carbon-clear {
        animation: carbonSweep 0.8s ease forwards;
    }
    .diamond-clear {
        animation: diamondShockwave 1s ease forwards;
    }
    #game-over-container {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        z-index: 30;
        display: none;
        background: rgba(0, 0, 0, 0.8);
        padding: 20px;
        border-radius: 10px;
        max-width: 300px;
    }
    #game-over-buttons {
        display: flex;
        flex-direction: column;
        align-items: center;
        width: 100%;
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
        color: #ffffff;
        background-color: #444;
        border: 2px solid #fff;
        padding: 10px 20px;
        margin: 10px 0;
        cursor: pointer;
        transition: background-color 0.3s ease, transform 0.2s ease;
        width: 100%;
        box-sizing: border-box;
    }
    #try-again:hover {
        background-color: #666;
        transform: scale(1.05);
    }
    #leaderboard {
        font-size: 24px;
        font-family: Arial;
        color: #ffffff;
        background-color: #444;
        border: 2px solid #fff;
        padding: 10px 20px;
        margin: 10px 0;
        cursor: pointer;
        transition: background-color 0.3s ease, transform 0.2s ease;
        width: 100%;
        box-sizing: border-box;
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
    @keyframes bombPopIn {
        0% { transform: scale(0); opacity: 0; }
        70% { transform: scale(1.2); opacity: 1; }
        100% { transform: scale(1); opacity: 1; }
    }
    @keyframes carbonSweep {
        0% { transform: scale(1); opacity: 1; background-color: #ff6600; box-shadow: 0 0 10px #ff3300; }
        50% { transform: scale(1.2); opacity: 0.8; background-color: #ff9900; box-shadow: 0 0 20px #ff6600; }
        100% { transform: scale(0); opacity: 0; background-color: #ffcc00; box-shadow: 0 0 30px #ff9900; }
    }
    @keyframes diamondShockwave {
        0% { transform: scale(1); opacity: 1; background-color: #ffffff; box-shadow: 0 0 0 #ff00ff; }
        50% { transform: scale(1.5); opacity: 0.8; background-color: #ff00ff; box-shadow: 0 0 20px #ff00ff; }
        75% { transform: scale(1); opacity: 0.5; background-color: #9900cc; box-shadow: 0 0 40px #ff00ff; }
        100% { transform: scale(0); opacity: 0; background-color: #000000; box-shadow: 0 0 60px #ff00ff; }
    }
    @keyframes gameOverPulse {
        0% { transform: scale(1); opacity: 0.8; }
        50% { transform: scale(1.1); opacity: 1; }
        100% { transform: scale(1); opacity: 0.8; }
    }
</style>
<script>
class Match3Game {
    constructor() {
        this.isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0 || navigator.msMaxTouchPoints > 0 || window.matchMedia('(pointer: coarse)').matches;
        this.isMobile = this.isTouchDevice || window.innerWidth <= 768;

        this.width = this.isMobile ? 6 : 8;
        this.height = this.isMobile ? 10 : 8;

        this.board = [];
        this.selectedTile = null;
        this.score = 0;
        this.matchCount = 0;
        this.matchLimit = 25;
        this.gameOver = false;
        this.isDetonating = false;
        this.isGrandFinale = false;
        this.detonationChainActive = false;
        this.initialBombPositions = new Set();
        this.allIcons = [
            'https://www.skulliance.io/staking/icons/dark.png',
            'https://www.skulliance.io/staking/icons/maxi.png',
            'https://www.skulliance.io/staking/icons/dank.png',
            'https://www.skulliance.io/staking/icons/mipa.png',
            'https://www.skulliance.io/staking/icons/ustra.png',
            'https://www.skulliance.io/staking/icons/nat.png',
            'https://www.skulliance.io/staking/icons/fire.png',
            'https://www.skulliance.io/staking/icons/eye.png',
            'https://www.skulliance.io/staking/icons/sharon.png',
            'https://www.skulliance.io/staking/icons/lens.png',
            'https://www.skulliance.io/staking/icons/kala.png',
            'https://www.skulliance.io/staking/icons/ass.png',
            'https://www.skulliance.io/staking/icons/moon.png',
            'https://www.skulliance.io/staking/icons/ritual.png',
            'https://www.skulliance.io/staking/icons/wave.png',
            'https://www.skulliance.io/staking/icons/soul.png',
            'https://www.skulliance.io/staking/icons/stag.png',
            'https://www.skulliance.io/staking/icons/skowl.png',
            'https://www.skulliance.io/staking/icons/loot.png',
            'https://www.skulliance.io/staking/icons/venus.png',
            'https://www.skulliance.io/staking/icons/axion.png',
            'https://www.skulliance.io/staking/icons/void.png',
            'https://www.skulliance.io/staking/icons/muse.png',
            'https://www.skulliance.io/staking/icons/dn.png',
            'https://www.skulliance.io/staking/icons/tribe.png',
            'https://www.skulliance.io/staking/icons/bung.png',
            'https://www.skulliance.io/staking/icons/star.png',
            'https://www.skulliance.io/staking/icons/dread.png',
            'https://www.skulliance.io/staking/icons/hype.png',
        ];
        this.specialIcons = {
            carbon: 'https://www.skulliance.io/staking/icons/carbon.png',
            diamond: 'https://www.skulliance.io/staking/icons/diamond.png'
        };
        this.colorPalette = [
            '#800000', '#008080', '#408000', '#4B0082', '#666633', '#804000', '#004080'
        ];
        this.icons = this.selectRandomIcons(7);
        this.iconColorMap = this.createIconColorMap();
        this.specialTypes = { bomb4: 'carbon', bomb5: 'diamond' };
        this.isDragging = false;
        this.targetTile = null;
        this.dragDirection = null;
        this.offsetX = 0;
        this.offsetY = 0;
        this.currentTransform = null;

        this.bonusScores = {
            carbonDetonation: 50,
            diamondDetonation: 100,
            carbonCleared: 25,
            diamondCleared: 50
        };

        this.sounds = {
            match: new Audio('https://www.skulliance.io/staking/sounds/select.ogg'),
            carbonBombAppear: new Audio('https://www.skulliance.io/staking/sounds/hyperspace_gem_land_2.ogg'),
            diamondBombAppear: new Audio('https://www.skulliance.io/staking/sounds/hyperspace_gem_land_1.ogg'),
            carbonExplode: new Audio('https://www.skulliance.io/staking/sounds/bomb_explode.ogg'),
            diamondExplode: new Audio('https://www.skulliance.io/staking/sounds/gem_shatters.ogg'),
            cascade: new Audio('https://www.skulliance.io/staking/sounds/select.ogg'),
            badMove: new Audio('https://www.skulliance.io/staking/sounds/badmove.ogg'),
            gameOver: new Audio('https://www.skulliance.io/staking/sounds/voice_gameover.ogg'),
            reset: new Audio('https://www.skulliance.io/staking/sounds/voice_welcomeback.ogg')
        };
        Object.values(this.sounds).forEach(sound => sound.preload = 'auto');

        const boardElement = document.getElementById('game-board');
        const maxWidth = Math.min(window.innerWidth * 0.9, window.innerHeight * 0.9 * (this.width / this.height));
        const maxHeight = Math.min(window.innerHeight * 0.9, window.innerWidth * 0.9 * (this.height / this.width));
        boardElement.style.width = `${maxWidth}px`;
        boardElement.style.height = `${maxHeight}px`;
        boardElement.style.gridTemplateColumns = `repeat(${this.width}, 1fr)`;

        const hudElement = document.getElementById('hud');
        hudElement.style.width = `${maxWidth}px`;

        this.tileSizeWithGap = (maxWidth - (0.5 * (this.width - 1))) / this.width;

        this.preloadAssets();
        this.initBoard();
        this.renderBoard();
        this.addEventListeners();
        boardElement.style.pointerEvents = 'auto';

        this.tryAgainButton = document.getElementById('try-again');
        this.tryAgainButton.addEventListener('click', () => this.resetGame());

        this.leaderboardButton = document.getElementById('leaderboard');
        this.leaderboardForm = document.getElementById('leaderboard-form');
        this.leaderboardButton.addEventListener('click', () => this.leaderboardForm.submit());

        document.getElementById('row0').style.visibility = 'visible';
    }

    preloadAssets() {
        Object.values(this.allIcons).forEach(url => {
            const img = new Image();
            img.src = url;
            img.onerror = () => console.warn(`Failed to preload image: ${url}`);
        });
        Object.values(this.specialIcons).forEach(url => {
            const img = new Image();
            img.src = url;
            img.onerror = () => console.warn(`Failed to preload image: ${url}`);
        });
        Object.values(this.sounds).forEach(sound => {
            sound.load();
            sound.onerror = () => console.warn(`Failed to preload sound: ${sound.src}`);
        });
    }

    playSound(soundName) {
        const sound = this.sounds[soundName];
        if (sound) {
            sound.currentTime = 0;
            sound.play().catch(error => console.log('Sound error:', error));
        }
    }

    resetGame() {
        this.score = 0;
        this.matchCount = 0;
        this.gameOver = false;
        this.isGrandFinale = false;
        this.detonationChainActive = false;
        this.initialBombPositions.clear();
        this.currentTransform = null;
        document.getElementById('score').textContent = `Score: ${this.score}`;
        document.getElementById('matches').textContent = `Matches: ${this.matchCount}/${this.matchLimit}`;
        
        const board = document.getElementById('game-board');
        const tiles = board.querySelectorAll('.tile');
        tiles.forEach(tile => tile.classList.remove('game-over'));
        board.style.pointerEvents = 'auto';
        
        document.getElementById('game-over-container').style.display = 'none';
        
        this.playSound('reset');
        this.initBoard();
        this.renderBoard();
    }

    selectRandomIcons(count) {
        const shuffled = [...this.allIcons].sort(() => 0.5 - Math.random());
        return shuffled.slice(0, count);
    }

    createIconColorMap() {
        const map = {};
        this.icons.forEach((icon, index) => {
            map[icon] = this.colorPalette[index % this.colorPalette.length];
        });
        map[this.specialIcons.carbon] = '#333300';
        map[this.specialIcons.diamond] = '#000000';
        return map;
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
                    (x >= 2 && this.board[y][x-1].icon === tile.icon && this.board[y][x-2].icon === tile.icon) ||
                    (y >= 2 && this.board[y-1][x].icon === tile.icon && this.board[y-2][x].icon === tile.icon)
                );
                this.board[y][x] = tile;
            }
        }
    }

    createRandomTile() {
        return {
            icon: this.icons[Math.floor(Math.random() * this.icons.length)],
            special: null,
            element: null
        };
    }

    renderBoard() {
        const boardElement = document.getElementById('game-board');
        boardElement.innerHTML = '';

        for (let y = 0; y < this.height; y++) {
            for (let x = 0; x < this.width; x++) {
                const tile = this.board[y][x];
                const tileElement = document.createElement('div');
                tileElement.className = 'tile';
                if (this.gameOver) tileElement.classList.add('game-over');
                
                if (tile.special) {
                    const img = document.createElement('img');
                    img.src = this.specialIcons[tile.special];
                    tileElement.appendChild(img);
                    tileElement.style.backgroundColor = this.iconColorMap[this.specialIcons[tile.special]];
                } else if (tile.icon) {
                    const img = document.createElement('img');
                    img.src = tile.icon;
                    tileElement.appendChild(img);
                    tileElement.style.backgroundColor = this.iconColorMap[tile.icon];
                }

                tileElement.dataset.x = x;
                tileElement.dataset.y = y;
                boardElement.appendChild(tileElement);
                tile.element = tileElement;

                if (this.isDragging && this.selectedTile && this.selectedTile.x === x && this.selectedTile.y === y) {
                    tileElement.classList.add('selected');
                    if (this.currentTransform) tileElement.style.transform = this.currentTransform;
                } else {
                    tileElement.style.transform = 'translate(0, 0)';
                }
            }
        }
        
        document.getElementById('game-over-container').style.display = this.gameOver ? 'block' : 'none';
    }

    addEventListeners() {
        const board = document.getElementById('game-board');
        
        board.addEventListener('touchstart', (e) => this.handleTouchStart(e), { passive: false });
        board.addEventListener('touchmove', (e) => this.handleTouchMove(e), { passive: false });
        board.addEventListener('touchend', (e) => this.handleTouchEnd(e));
        board.addEventListener('mousedown', (e) => this.handleMouseDown(e));
        board.addEventListener('mousemove', (e) => this.handleMouseMove(e));
        board.addEventListener('mouseup', (e) => this.handleMouseUp(e));
    }

    handleMouseDown(e) {
        if (this.gameOver || this.isGrandFinale) return;
        e.preventDefault();
        const tile = this.getTileFromEvent(e);
        if (!tile || !tile.element) return;

        this.isDragging = true;
        this.selectedTile = { x: tile.x, y: tile.y };
        tile.element.classList.add('selected');

        const boardRect = document.getElementById('game-board').getBoundingClientRect();
        this.offsetX = e.clientX - (boardRect.left + this.selectedTile.x * this.tileSizeWithGap);
        this.offsetY = e.clientY - (boardRect.top + this.selectedTile.y * this.tileSizeWithGap);
    }

    handleMouseMove(e) {
        if (!this.isDragging || !this.selectedTile || this.gameOver) return;
        e.preventDefault();

        const boardRect = document.getElementById('game-board').getBoundingClientRect();
        const mouseX = e.clientX - boardRect.left - this.offsetX;
        const mouseY = e.clientY - boardRect.top - this.offsetY;

        const selectedTileElement = this.board[this.selectedTile.y][this.selectedTile.x].element;
        selectedTileElement.style.transition = '';
        selectedTileElement.style.zIndex = '1000';

        if (!this.dragDirection) {
            const dx = Math.abs(mouseX - (this.selectedTile.x * this.tileSizeWithGap));
            const dy = Math.abs(mouseY - (this.selectedTile.y * this.tileSizeWithGap));
            if (dx > dy && dx > 5) this.dragDirection = 'row';
            else if (dy > dx && dy > 5) this.dragDirection = 'column';
        }

        if (!this.dragDirection) return;

        if (this.dragDirection === 'row') {
            const constrainedX = Math.max(0, Math.min((this.width - 1) * this.tileSizeWithGap, mouseX));
            this.currentTransform = `translate(${constrainedX - this.selectedTile.x * this.tileSizeWithGap}px, 0) scale(1.05)`;
            selectedTileElement.style.transform = this.currentTransform;
            this.targetTile = {
                x: Math.round(constrainedX / this.tileSizeWithGap),
                y: this.selectedTile.y
            };
        } else if (this.dragDirection === 'column') {
            const constrainedY = Math.max(0, Math.min((this.height - 1) * this.tileSizeWithGap, mouseY));
            this.currentTransform = `translate(0, ${constrainedY - this.selectedTile.y * this.tileSizeWithGap}px) scale(1.05)`;
            selectedTileElement.style.transform = this.currentTransform;
            this.targetTile = {
                x: this.selectedTile.x,
                y: Math.round(constrainedY / this.tileSizeWithGap)
            };
        }
    }

    handleMouseUp(e) {
        if (!this.isDragging || !this.selectedTile || this.gameOver) {
            if (this.selectedTile) {
                this.board[this.selectedTile.y][this.selectedTile.x].element.classList.remove('selected');
            }
            this.cleanupDrag();
            this.playSound('badMove');
            return;
        }

        const tile = this.board[this.selectedTile.y][this.selectedTile.x];
        tile.element.classList.remove('selected');
        this.slideTiles(this.selectedTile.x, this.selectedTile.y, this.targetTile.x, this.targetTile.y);
        this.cleanupDrag();
    }

    handleTouchStart(e) {
        if (this.gameOver || this.isGrandFinale) return;
        e.preventDefault();
        const tile = this.getTileFromEvent(e.touches[0]);
        if (!tile || !tile.element) return;

        this.isDragging = true;
        this.selectedTile = { x: tile.x, y: tile.y };
        tile.element.classList.add('selected');

        const boardRect = document.getElementById('game-board').getBoundingClientRect();
        this.offsetX = e.touches[0].clientX - (boardRect.left + this.selectedTile.x * this.tileSizeWithGap);
        this.offsetY = e.touches[0].clientY - (boardRect.top + this.selectedTile.y * this.tileSizeWithGap);
    }

    handleTouchMove(e) {
        if (!this.isDragging || !this.selectedTile || this.gameOver) return;
        e.preventDefault();

        const boardRect = document.getElementById('game-board').getBoundingClientRect();
        const touchX = e.touches[0].clientX - boardRect.left - this.offsetX;
        const touchY = e.touches[0].clientY - boardRect.top - this.offsetY;

        const selectedTileElement = this.board[this.selectedTile.y][this.selectedTile.x].element;

        requestAnimationFrame(() => {
            if (!this.dragDirection) {
                const dx = Math.abs(touchX - (this.selectedTile.x * this.tileSizeWithGap));
                const dy = Math.abs(touchY - (this.selectedTile.y * this.tileSizeWithGap));
                if (dx > dy && dx > 7) this.dragDirection = 'row';
                else if (dy > dx && dy > 7) this.dragDirection = 'column';
            }

            selectedTileElement.style.transition = '';
            selectedTileElement.style.zIndex = '1000';

            if (this.dragDirection === 'row') {
                const constrainedX = Math.max(0, Math.min((this.width - 1) * this.tileSizeWithGap, touchX));
                this.currentTransform = `translate(${constrainedX - this.selectedTile.x * this.tileSizeWithGap}px, 0) scale(1.05)`;
                selectedTileElement.style.transform = this.currentTransform;
                this.targetTile = {
                    x: Math.round(constrainedX / this.tileSizeWithGap),
                    y: this.selectedTile.y
                };
            } else if (this.dragDirection === 'column') {
                const constrainedY = Math.max(0, Math.min((this.height - 1) * this.tileSizeWithGap, touchY));
                this.currentTransform = `translate(0, ${constrainedY - this.selectedTile.y * this.tileSizeWithGap}px) scale(1.05)`;
                selectedTileElement.style.transform = this.currentTransform;
                this.targetTile = {
                    x: this.selectedTile.x,
                    y: Math.round(constrainedY / this.tileSizeWithGap)
                };
            }
        });
    }

    handleTouchEnd(e) {
        if (!this.isDragging || !this.selectedTile || this.gameOver) {
            if (this.selectedTile) {
                this.board[this.selectedTile.y][this.selectedTile.x].element.classList.remove('selected');
            }
            this.cleanupDrag();
            this.playSound('badMove');
            return;
        }

        const tile = this.board[this.selectedTile.y][this.selectedTile.x];
        tile.element.classList.remove('selected');
        this.slideTiles(this.selectedTile.x, this.selectedTile.y, this.targetTile.x, this.targetTile.y);
        this.cleanupDrag();
    }

    cleanupDrag() {
        this.isDragging = false;
        this.selectedTile = null;
        this.targetTile = null;
        this.dragDirection = null;
        this.currentTransform = null;
        this.renderBoard();
    }

    getTileFromEvent(e) {
        const boardRect = document.getElementById('game-board').getBoundingClientRect();
        const x = Math.floor((e.clientX - boardRect.left) / this.tileSizeWithGap);
        const y = Math.floor((e.clientY - boardRect.top) / this.tileSizeWithGap);
        if (x >= 0 && x < this.width && y >= 0 && y < this.height) {
            return { x, y, element: this.board[y][x].element };
        }
        return null;
    }

    async slideTiles(startX, startY, endX, endY) {
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

        selectedElement.style.transition = 'transform 0.2s ease';
        selectedElement.style.transform = `translate(${dx}px, ${dy}px)`;

        let i = 0;
        if (startY === endY) {
            for (let x = Math.min(startX, endX); x <= Math.max(startX, endX); x++) {
                if (x === startX) continue;
                const offsetX = direction * -tileSizeWithGap * (x - startX) / Math.abs(endX - startX);
                tileElements[i].style.transition = 'transform 0.2s ease';
                tileElements[i].style.transform = `translate(${offsetX}px, 0)`;
                i++;
            }
        } else {
            for (let y = Math.min(startY, endY); y <= Math.max(startY, endY); y++) {
                if (y === startY) continue;
                const offsetY = direction * -tileSizeWithGap * (y - startY) / Math.abs(endY - startY);
                tileElements[i].style.transition = 'transform 0.2s ease';
                tileElements[i].style.transform = `translate(0, ${offsetY}px)`;
                i++;
            }
        }

        await new Promise(resolve => setTimeout(resolve, 200));

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
        console.log(`Swapped from (${startX}, ${startY}) to (${endX}, ${endY})`);
        const hasMatches = await this.resolveMatches(endX, endY);

        if (hasMatches) {
            this.matchCount++;
            document.getElementById('matches').textContent = `Matches: ${this.matchCount}/${this.matchLimit}`;
            if (this.matchCount >= this.matchLimit) await this.endGame();
        } else {
            this.playSound('badMove');
            selectedElement.style.transition = 'transform 0.2s ease';
            selectedElement.style.transform = 'translate(0, 0)';
            tileElements.forEach(element => {
                element.style.transition = 'transform 0.2s ease';
                element.style.transform = 'translate(0, 0)';
            });

            await new Promise(resolve => setTimeout(resolve, 200));
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
        }
    }

    async endGame() {
        console.log('Starting endgame sequence...');
        this.isGrandFinale = true;
        const board = document.getElementById('game-board');
        board.style.pointerEvents = 'none';

        // Detonate all bombs
        let bombPositions = this.getAllBombPositions();
        console.log(`Found ${bombPositions.length} bombs for grand finale`);

        while (bombPositions.length > 0) {
            const bomb = bombPositions.shift();
            if (bomb.type === 'carbon') {
                await this.clearRowAndColumn(bomb.x, bomb.y, true);
            } else if (bomb.type === 'diamond') {
                await this.clearBoard(bomb.x, bomb.y, true);
            }
            this.showerTiles();
            await this.cascadeTiles();
            this.renderBoard();
            await new Promise(resolve => setTimeout(resolve, 500)); // Increased delay for stability

            const newBombs = this.getAllBombPositions();
            bombPositions = [...bombPositions, ...newBombs.filter(nb => 
                !bombPositions.some(b => b.x === nb.x && b.y === nb.y))];
            console.log(`Detonated at (${bomb.x}, ${bomb.y}), ${bombPositions.length} bombs remain`);
        }

        // Clear all remaining matches with extended checks
        console.log('Clearing all remaining matches...');
        let hasMatches = true;
        let iterations = 0;
        const maxIterations = 50; // Increased to ensure exhaustive clearing
        while (hasMatches && iterations < maxIterations) {
            hasMatches = await this.resolveMatches();
            console.log(`Match check iteration ${iterations + 1}, hasMatches: ${hasMatches}`);
            if (hasMatches) {
                await this.cascadeTiles();
                this.showerTiles();
                this.renderBoard();
                await new Promise(resolve => setTimeout(resolve, 700)); // Extended delay for stability
                console.log('Current board state after cascade:');
                for (let y = 0; y < this.height; y++) {
                    let row = '';
                    for (let x = 0; x < this.width; x++) {
                        const tile = this.board[y][x];
                        row += tile.icon ? tile.icon.split('/').pop().replace('.png', '') + ' ' : 
                              tile.special ? tile.special + ' ' : 'null ';
                    }
                    console.log(row);
                }
            }
            iterations++;
        }

        // Final cascade to fill any gaps with extended checks
        let moved = true;
        iterations = 0;
        while (moved && iterations < maxIterations) {
            moved = this.cascadeTilesWithoutRender();
            if (moved) {
                this.showerTiles();
                await this.cascadeTiles();
                this.renderBoard();
                await new Promise(resolve => setTimeout(resolve, 700)); // Extended delay for stability
                console.log('Final cascade iteration, moved: true');
            }
            iterations++;
        }

        console.log('Board is fully resolved, showing game over...');
        const tiles = board.querySelectorAll('.tile');
        tiles.forEach(tile => tile.classList.add('game-over'));
        document.getElementById('game-over-container').style.display = 'block';
        this.gameOver = true;
        this.playSound('gameOver');
        this.saveSwapScore(this.score);
    }

    saveSwapScore(score) {
        var xhttp = new XMLHttpRequest();
        xhttp.open('GET', 'ajax/save-swap-score.php?score=' + score, true);
        xhttp.send();
    }

    getAllBombPositions() {
        const bombPositions = [];
        for (let y = 0; y < this.height; y++) {
            for (let x = 0; x < this.width; x++) {
                const tile = this.board[y][x];
                if (tile.special) {
                    bombPositions.push({ x, y, type: tile.special });
                }
            }
        }
        return bombPositions;
    }

    showerTiles() {
        for (let x = 0; x < this.width; x++) {
            let topEmpty = -1;
            for (let y = 0; y < this.height; y++) {
                if (!this.board[y][x].icon && !this.board[y][x].special) {
                    topEmpty = y;
                    break;
                }
            }
            if (topEmpty >= 0) {
                this.board[topEmpty][x] = this.createRandomTile();
            }
        }
    }

    cascadeTilesWithoutRender() {
        let moved = false;
        for (let x = 0; x < this.width; x++) {
            let emptySpaces = 0;
            for (let y = this.height - 1; y >= 0; y--) {
                if (!this.board[y][x].icon && !this.board[y][x].special) {
                    emptySpaces++;
                } else if (emptySpaces > 0) {
                    this.board[y + emptySpaces][x] = this.board[y][x];
                    this.board[y][x] = { icon: null, special: null, element: null };
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

    async cascadeTiles() {
        let moved = this.cascadeTilesWithoutRender();
        if (!moved) return;

        for (let x = 0; x < this.width; x++) {
            for (let y = 0; y < this.height; y++) {
                const tile = this.board[y][x];
                if (tile.element) {
                    const emptyBelow = this.countEmptyBelow(x, y);
                    if (emptyBelow > 0) {
                        tile.element.classList.add('falling');
                        tile.element.style.transform = `translate(0, ${emptyBelow * this.tileSizeWithGap}px)`;
                    }
                }
            }
        }

        this.renderBoard();
        this.playSound('cascade');
        await new Promise(resolve => setTimeout(resolve, 300));

        document.querySelectorAll('.falling').forEach(tile => {
            tile.classList.remove('falling');
            tile.style.transform = 'translate(0, 0)';
        });

        this.showerTiles();
        this.renderBoard();

        let hasMatches = await this.resolveMatches();
        while (hasMatches) {
            hasMatches = await this.resolveMatches();
            console.log(`Cascade match check, hasMatches: ${hasMatches}`);
        }
    }

    countEmptyBelow(x, y) {
        let count = 0;
        for (let i = y + 1; i < this.height; i++) {
            if (!this.board[i][x].icon && !this.board[i][x].special) count++;
            else break;
        }
        return count;
    }

    async resolveMatches(selectedX = null, selectedY = null) {
        if (this.gameOver || this.isGrandFinale) return false;
        console.log(`Resolving matches across the board, selected position: (${selectedX}, ${selectedY})`);
        const matchResult = this.checkMatches(selectedX, selectedY);
        if (matchResult.hasMatches && matchResult.matches.size >= 3) {
            console.log(`Match found: ${matchResult.matches.size} tiles at (${matchResult.bombX}, ${matchResult.bombY})`);
            const firstTile = this.board[matchResult.bombY][matchResult.bombX];
            if (firstTile && firstTile.special) {
                await this.handleBombMatches(matchResult.matches, firstTile.special, matchResult.bombX, matchResult.bombY);
            } else {
                await this.handleMatches(matchResult.matches, matchResult.bombType, matchResult.bombX, matchResult.bombY);
            }
            return true;
        }
        console.log('No matches found');
        return false;
    }

    checkMatches(selectedX = null, selectedY = null) {
        let hasMatches = false;
        const allMatches = new Set();
        let bombType = null;
        let bombX = null;
        let bombY = null;

        // Horizontal matches
        for (let y = 0; y < this.height; y++) {
            let matchStart = 0;
            let currentIcon = null;
            for (let x = 0; x <= this.width; x++) {
                const tile = x < this.width ? this.board[y][x] : null;
                const icon = tile ? (tile.special ? this.specialIcons[tile.special] : tile.icon) : null;

                if (icon !== currentIcon || x === this.width) {
                    const matchLength = x - matchStart;
                    if (matchLength >= 3) {
                        for (let i = matchStart; i < x; i++) {
                            allMatches.add(`${i},${y}`);
                        }
                        hasMatches = true;
                        // Set bomb position to the swapped tile if part of the match, otherwise use the center of the match
                        if (selectedX !== null && selectedY === y && selectedX >= matchStart && selectedX < x) {
                            bombX = selectedX;
                            bombY = y;
                        } else {
                            bombX = Math.floor((matchStart + (x - 1)) / 2); // Center of the match
                            bombY = y;
                        }
                    }
                    matchStart = x;
                    currentIcon = icon;
                }
            }
        }

        // Vertical matches
        for (let x = 0; x < this.width; x++) {
            let matchStart = 0;
            let currentIcon = null;
            for (let y = 0; y <= this.height; y++) {
                const tile = y < this.height ? this.board[y][x] : null;
                const icon = tile ? (tile.special ? this.specialIcons[tile.special] : tile.icon) : null;

                if (icon !== currentIcon || y === this.height) {
                    const matchLength = y - matchStart;
                    if (matchLength >= 3) {
                        for (let i = matchStart; i < y; i++) {
                            allMatches.add(`${x},${i}`);
                        }
                        hasMatches = true;
                        // Set bomb position to the swapped tile if part of the match, otherwise use the center of the match
                        if (selectedY !== null && selectedX === x && selectedY >= matchStart && selectedY < y) {
                            bombX = x;
                            bombY = selectedY;
                        } else {
                            bombX = x;
                            bombY = Math.floor((matchStart + (y - 1)) / 2); // Center of the match
                        }
                    }
                    matchStart = y;
                    currentIcon = icon;
                }
            }
        }

        if (hasMatches && allMatches.size >= 4) {
            bombType = allMatches.size === 4 ? 'bomb4' : 'bomb5';
            if (selectedX !== null && selectedY !== null) {
                bombX = selectedX;
                bombY = selectedY;
            } else if (bombX === null || bombY === null) {
                // Fallback to center of the first match if no swap position
                const firstMatch = Array.from(allMatches)[0].split(',').map(Number);
                bombX = firstMatch[0];
                bombY = firstMatch[1];
            }
            console.log(`Match detected: size=${allMatches.size}, bombType=${bombType}, position=(${bombX},${bombY})`);
        }

        return { hasMatches, matches: allMatches, bombType, bombX, bombY };
    }

    async handleMatches(matches, bombType, bombX, bombY) {
        console.log(`Handling match: ${matches.size} tiles at (${bombX}, ${bombY})`);
        this.playSound('match');
        matches.forEach(match => {
            const [x, y] = match.split(',').map(Number);
            if (this.board[y][x].element) this.board[y][x].element.classList.add('matched');
        });

        await new Promise(resolve => setTimeout(resolve, 400));

        matches.forEach(match => {
            const [x, y] = match.split(',').map(Number);
            this.board[y][x].icon = null;
            this.board[y][x].special = null;
            this.board[y][x].element = null;
        });

        if (bombType && bombX !== null && bombY !== null) {
            this.board[bombY][bombX] = {
                icon: null,
                special: this.specialTypes[bombType],
                element: null
            };
            this.renderBoard();
            this.board[bombY][bombX].element.classList.add('bomb-creation');
            this.playSound(bombType === 'bomb4' ? 'carbonBombAppear' : 'diamondBombAppear');
        } else {
            console.warn(`Bomb not created: invalid position (${bombX}, ${bombY}) for match size ${matches.size}`);
        }

        this.score += matches.size * 10;
        document.getElementById('score').textContent = `Score: ${this.score}`;
        await this.cascadeTiles();
    }

    async handleBombMatches(matches, bombType, bombX, bombY) {
        console.log(`Handling bomb match: ${matches.size} tiles at (${bombX}, ${bombY}) with ${bombType}`);
        this.playSound('match');
        matches.forEach(match => {
            const [x, y] = match.split(',').map(Number);
            if (this.board[y][x].element) this.board[y][x].element.classList.add('matched');
        });

        await new Promise(resolve => setTimeout(resolve, 400));

        matches.forEach(match => {
            const [x, y] = match.split(',').map(Number);
            this.board[y][x].icon = null;
            this.board[y][x].special = null;
            this.board[y][x].element = null;
        });

        this.score += matches.size * 10;

        if (bombType === 'carbon') {
            this.score += this.bonusScores.carbonDetonation;
            console.log(`Carbon bomb detonated at (${bombX}, ${bombY}), +${this.bonusScores.carbonDetonation} bonus`);
            await this.clearRowAndColumn(bombX, bombY);
        } else if (bombType === 'diamond') {
            this.score += this.bonusScores.diamondDetonation;
            console.log(`Diamond bomb detonated at (${bombX}, ${bombY}), +${this.bonusScores.diamondDetonation} bonus`);
            await this.clearBoard(bombX, bombY);
        }

        document.getElementById('score').textContent = `Score: ${this.score}`;
        await this.cascadeTiles();
    }

    async clearRowAndColumn(x, y, isEndGame = false) {
        console.log(`Clearing row ${y} and column ${x} (Carbon Bomb)`);
        this.playSound('carbonExplode');
        const affectedTiles = new Set();
        const newBombs = [];

        if (this.board[y][x].element) {
            this.board[y][x].element.classList.add('carbon-clear');
        }

        for (let i = 0; i < this.width; i++) {
            if (i !== x && this.board[y][i].element) {
                affectedTiles.add(`${i},${y}`);
            }
        }
        for (let j = 0; j < this.height; j++) {
            if (j !== y && this.board[j][x].element) {
                affectedTiles.add(`${x},${j}`);
            }
        }

        affectedTiles.forEach(pos => {
            const [tx, ty] = pos.split(',').map(Number);
            if (this.board[ty][tx].element) {
                this.board[ty][tx].element.classList.add('carbon-clear');
            }
        });

        await new Promise(resolve => setTimeout(resolve, isEndGame ? 250 : 800));

        affectedTiles.forEach(pos => {
            const [tx, ty] = pos.split(',').map(Number);
            if (this.board[ty][tx].special && !isEndGame) {
                newBombs.push({ x: tx, y: ty, type: this.board[ty][tx].special });
            }
            this.board[ty][tx].icon = null;
            this.board[ty][tx].special = null;
            this.board[ty][tx].element = null;
        });
        this.board[y][x].icon = null;
        this.board[y][x].special = null;
        this.board[y][x].element = null;

        this.score += (affectedTiles.size + 1) * 10;
        if (!isEndGame) {
            this.score += this.bonusScores.carbonCleared;
        }
        document.getElementById('score').textContent = `Score: ${this.score}`;
        console.log(`Carbon bomb cleared ${affectedTiles.size + 1} tiles, added ${(affectedTiles.size + 1) * 10} points`);

        if (!isEndGame) {
            for (const bomb of newBombs) {
                await this.handleBombDetonation(bomb.x, bomb.y, bomb.type);
            }
        }

        return newBombs;
    }

    async clearBoard(x = null, y = null, isEndGame = false) {
        console.log(`Clearing entire board (Diamond Bomb)${x !== null && y !== null ? ` at (${x}, ${y})` : ''}`);
        this.playSound('diamondExplode');
        const affectedTiles = new Set();
        const newBombs = [];

        if (x !== null && y !== null && this.board[y][x].element) {
            this.board[y][x].element.classList.add('diamond-clear');
        }

        for (let ty = 0; ty < this.height; ty++) {
            for (let tx = 0; tx < this.width; tx++) {
                if ((tx !== x || ty !== y) && this.board[ty][tx].element) {
                    affectedTiles.add(`${tx},${ty}`);
                    this.board[ty][tx].element.classList.add('diamond-clear');
                }
            }
        }

        await new Promise(resolve => setTimeout(resolve, isEndGame ? 250 : 1000));

        affectedTiles.forEach(pos => {
            const [tx, ty] = pos.split(',').map(Number);
            if (this.board[ty][tx].special && !isEndGame) {
                newBombs.push({ x: tx, y: ty, type: this.board[ty][tx].special });
            }
            this.board[ty][tx].icon = null;
            this.board[ty][tx].special = null;
            this.board[ty][tx].element = null;
        });
        if (x !== null && y !== null) {
            this.board[y][x].icon = null;
            this.board[y][x].special = null;
            this.board[y][x].element = null;
        }

        this.score += affectedTiles.size * 10 + (x !== null ? 10 : 0);
        if (!isEndGame) {
            this.score += this.bonusScores.diamondCleared;
        }
        document.getElementById('score').textContent = `Score: ${this.score}`;
        console.log(`Diamond bomb cleared ${affectedTiles.size + (x !== null ? 1 : 0)} tiles, added ${(affectedTiles.size + (x !== null ? 1 : 0)) * 10} points`);

        if (!isEndGame) {
            for (const bomb of newBombs) {
                await this.handleBombDetonation(bomb.x, bomb.y, bomb.type);
            }
        }

        return newBombs;
    }

    async handleBombDetonation(x, y, bombType) {
        console.log(`Detonating ${bombType} bomb at (${x}, ${y}) triggered by match or grand finale`);
        if (this.gameOver || this.isGrandFinale) {
            if (bombType === 'carbon') {
                this.score += this.bonusScores.carbonDetonation;
                console.log(`Carbon bomb chain-detonated at (${x}, ${y}) in grand finale, +${this.bonusScores.carbonDetonation} bonus`);
                await this.clearRowAndColumn(x, y, true);
            } else if (bombType === 'diamond') {
                this.score += this.bonusScores.diamondDetonation;
                console.log(`Diamond bomb chain-detonated at (${x}, ${y}) in grand finale, +${this.bonusScores.diamondDetonation} bonus`);
                await this.clearBoard(x, y, true);
            }
        } else {
            // Only detonate during a match in gameplay, not automatically
            if (bombType === 'carbon') {
                this.score += this.bonusScores.carbonDetonation;
                console.log(`Carbon bomb detonated at (${x}, ${y}) during match, +${this.bonusScores.carbonDetonation} bonus`);
                await this.clearRowAndColumn(x, y);
            } else if (bombType === 'diamond') {
                this.score += this.bonusScores.diamondDetonation;
                console.log(`Diamond bomb detonated at (${x}, ${y}) during match, +${this.bonusScores.diamondDetonation} bonus`);
                await this.clearBoard(x, y);
            }
        }
        this.showerTiles();
        await this.cascadeTiles();
        this.renderBoard();
        await new Promise(resolve => setTimeout(resolve, 200));
        document.getElementById('score').textContent = `Score: ${this.score}`;
    }
}

const game = new Match3Game();
</script>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>