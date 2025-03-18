const rows = window.csvData.split('\n').slice(1);
const data = rows.map(row => {
    const [user_name, user_image, realm_name, realm_image, faction_name] = row.split('","').map(val => val.replace(/^"|"$/g, ''));
    return { user_name, user_image, realm_name, realm_image, faction_name };
});

const factions = Object.values(data.reduce((acc, { user_name, user_image, realm_name, realm_image, faction_name }) => {
    if (!acc[faction_name]) {
        acc[faction_name] = { name: faction_name, realms: [] };
    }
    acc[faction_name].realms.push({ user_name, user_image, realm_name, realm_image });
    return acc;
}, {}));

const container = document.getElementById('container');
const tileSize = 100;
const gap = 5;
const borderWidth = 20;
const minSpacing = 5;

const earthyColors = {
    '#8B4513': { light: '#A0522D', dark: '#5C4033' },
    '#D2B48C': { light: '#DEB887', dark: '#A1887F' },
    '#696969': { light: '#808080', dark: '#4F4F4F' },
    '#228B22': { light: '#32CD32', dark: '#006400' },
    '#006400': { light: '#228B22', dark: '#004D00' },
    '#B0C4DE': { light: '#D3DCE6', dark: '#87AFC7' },
    '#D3D3D3': { light: '#E8E8E8', dark: '#A9A9A9' }
};

const popupOverlay = document.getElementById('popup-overlay');
const popupImage = document.getElementById('popup-image');
const popupName = document.getElementById('popup-name');
const popupClose = document.getElementById('popup-close');

function showPopup(realmImage, realmName) {
    popupImage.src = realmImage;
    popupName.textContent = realmName;
    popupOverlay.style.display = 'flex';
}

function hidePopup() {
    popupOverlay.style.display = 'none';
}

popupOverlay.addEventListener('click', (e) => {
    if (e.target === popupOverlay) hidePopup();
});

popupClose.addEventListener('click', hidePopup);

const factionGrids = factions.map(faction => {
    const realmCount = faction.realms.length;
    if (realmCount === 0) return null;

    let width, height;
    const sqrtCount = Math.ceil(Math.sqrt(realmCount));
    const totalTiles = sqrtCount * sqrtCount;
    const emptyTiles = totalTiles - realmCount;
    const viewportWidth = window.innerWidth;

    const horizontalWidth = realmCount * tileSize + (realmCount - 1) * gap + 2 * borderWidth;

    if (emptyTiles > 0) {
        if (horizontalWidth <= viewportWidth * 0.5 && realmCount <= 3) {
            width = realmCount;
            height = 1;
        } else {
            width = 1;
            height = realmCount;
        }
    } else {
        width = sqrtCount;
        height = sqrtCount;
    }

    const totalTilesFinal = width * height;
    const baseColor = Object.keys(earthyColors)[Math.floor(Math.random() * Object.keys(earthyColors).length)];
    const { light, dark } = earthyColors[baseColor];

    const factionGrid = document.createElement('div');
    factionGrid.classList.add('faction-grid');
    factionGrid.style.setProperty('--border-color-light', light);
    factionGrid.style.setProperty('--border-color-dark', dark);
    factionGrid.setAttribute('data-faction-name', faction.name);

    for (let i = 0; i < totalTilesFinal; i++) {
        const tile = document.createElement('div');
        tile.classList.add('tile');
        if (i < realmCount) {
            const { user_name, user_image, realm_name, realm_image } = faction.realms[i];
            tile.style.backgroundImage = `url(${user_image})`;
            const nameSpan = document.createElement('span');
            nameSpan.textContent = user_name;
            tile.appendChild(nameSpan);

            tile.dataset.realmImage = realm_image;
            tile.dataset.realmName = realm_name;
            tile.dataset.userImage = user_image;
            tile.dataset.userName = user_name;

            const img = new Image();
            img.src = user_image;
            img.onerror = () => {
                tile.style.backgroundImage = `url(https://skulliance.io/staking/icons/skull.png)`;
                tile.dataset.userImage = 'https://skulliance.io/staking/icons/skull.png';
            };

            tile.addEventListener('mouseenter', () => {
                tile.style.backgroundImage = `url(${realm_image})`;
                nameSpan.textContent = realm_name;
            });
            tile.addEventListener('mouseleave', () => {
                tile.style.backgroundImage = `url(${tile.dataset.userImage})`;
                nameSpan.textContent = user_name;
            });

            tile.addEventListener('click', () => {
                showPopup(realm_image, realm_name);
            });
        } else {
            tile.classList.add('empty');
        }
        factionGrid.appendChild(tile);
    }

    const pixelWidth = width * tileSize + (width - 1) * gap + 2 * borderWidth;
    const pixelHeight = height * tileSize + (height - 1) * gap + 2 * borderWidth;

    return {
        element: factionGrid,
        width: width,
        height: height,
        pixelWidth: pixelWidth,
        pixelHeight: pixelHeight,
        originalWidth: width,
        originalHeight: height
    };
}).filter(grid => grid !== null);

function packGrids() {
    container.innerHTML = '';

    const viewportWidth = window.innerWidth;
    const viewportHeight = window.innerHeight;

    const totalArea = factionGrids.reduce((sum, grid) => sum + grid.pixelWidth * grid.pixelHeight, 0);
    const maxWidth = Math.min(viewportWidth * 0.9, Math.sqrt(totalArea * 1.5));
    const containerWidth = Math.max(Math.min(viewportWidth, viewportHeight), maxWidth);
    let currentHeight = 0;

    const sortedGrids = [...factionGrids].sort((a, b) => (b.pixelWidth * b.pixelHeight) - (a.pixelWidth * a.pixelHeight));
    const placedGrids = [];
    const occupiedSpaces = [];

    sortedGrids.forEach(grid => {
        let placed = false;
        const orientations = [
            { pixelWidth: grid.pixelWidth, pixelHeight: grid.pixelHeight, width: grid.width, height: grid.height },
            { 
                pixelWidth: grid.originalHeight * tileSize + (grid.originalHeight - 1) * gap + 2 * borderWidth, 
                pixelHeight: grid.originalWidth * tileSize + (grid.originalWidth - 1) * gap + 2 * borderWidth,
                width: grid.originalHeight,
                height: grid.originalWidth
            }
        ];

        for (let orientation of orientations) {
            if (placed) break;

            grid.pixelWidth = orientation.pixelWidth;
            grid.pixelHeight = orientation.pixelHeight;
            grid.width = orientation.width;
            grid.height = orientation.height;
            grid.element.style.gridTemplateColumns = `repeat(${grid.width}, ${tileSize}px)`;
            grid.element.style.gridTemplateRows = `repeat(${grid.height}, ${tileSize}px)`;

            for (let y = 0; y <= (currentHeight || viewportHeight) && !placed; y += minSpacing) {
                for (let x = 0; x <= containerWidth - grid.pixelWidth && !placed; x += minSpacing) {
                    let canPlace = true;
                    const newRect = { x, y, width: grid.pixelWidth, height: grid.pixelHeight };

                    for (let space of occupiedSpaces) {
                        if (!(newRect.x + newRect.width + minSpacing <= space.x || 
                              newRect.x >= space.x + space.width + minSpacing || 
                              newRect.y + newRect.height + minSpacing <= space.y || 
                              newRect.y >= space.y + space.height + minSpacing)) {
                            canPlace = false;
                            break;
                        }
                    }

                    if (canPlace) {
                        placedGrids.push({
                            grid: grid.element,
                            x: x,
                            y: y,
                            pixelWidth: grid.pixelWidth,
                            pixelHeight: grid.pixelHeight
                        });
                        occupiedSpaces.push(newRect);
                        currentHeight = Math.max(currentHeight, y + grid.pixelHeight);
                        placed = true;
                    }
                }
            }
        }

        if (!placed) {
            let maxY = occupiedSpaces.length > 0 ? Math.max(...occupiedSpaces.map(s => s.y + s.height)) : 0;
            let x = 0;
            let y = maxY + minSpacing;

            for (let orientation of orientations) {
                grid.pixelWidth = orientation.pixelWidth;
                grid.pixelHeight = orientation.pixelHeight;
                grid.width = orientation.width;
                grid.height = orientation.height;
                grid.element.style.gridTemplateColumns = `repeat(${grid.width}, ${tileSize}px)`;
                grid.element.style.gridTemplateRows = `repeat(${grid.height}, ${tileSize}px)`;

                let canPlace = true;
                const newRect = { x, y, width: grid.pixelWidth, height: grid.pixelHeight };

                for (let space of occupiedSpaces) {
                    if (!(newRect.x + newRect.width + minSpacing <= space.x || 
                          newRect.x >= space.x + space.width + minSpacing || 
                          newRect.y + newRect.height + minSpacing <= space.y || 
                          newRect.y >= space.y + space.height + minSpacing)) {
                        canPlace = false;
                        break;
                    }
                }

                if (canPlace) {
                    placedGrids.push({
                        grid: grid.element,
                        x: x,
                        y: y,
                        pixelWidth: grid.pixelWidth,
                        pixelHeight: grid.pixelHeight
                    });
                    occupiedSpaces.push(newRect);
                    currentHeight = Math.max(currentHeight, y + grid.pixelHeight);
                    placed = true;
                    break;
                }
            }
        }
    });

    let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
    placedGrids.forEach(g => {
        minX = Math.min(minX, g.x);
        maxX = Math.max(maxX, g.x + g.pixelWidth);
        minY = Math.min(minY, g.y);
        maxY = Math.max(maxY, g.y + g.pixelHeight);
    });

    const actualWidth = maxX + minSpacing;
    const actualHeight = maxY + minSpacing;

    container.style.width = `${actualWidth}px`;
    container.style.height = `${actualHeight}px`;

    placedGrids.forEach(g => {
        g.grid.style.left = `${g.x}px`;
        g.grid.style.top = `${g.y}px`;
        g.grid.style.width = `${g.pixelWidth}px`;
        g.grid.style.height = `${g.pixelHeight}px`;
        container.appendChild(g.grid);
    });

    console.log("Packed grids:", placedGrids.length, "out of", factionGrids.length);
}

packGrids();

let resizeTimeout;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(packGrids, 200);
});