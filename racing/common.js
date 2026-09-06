//=========================================================================
// minimalist DOM helpers
//=========================================================================

var Dom = {

  get:  function(id)                     { return ((id instanceof HTMLElement) || (id === document)) ? id : document.getElementById(id); },
  set:  function(id, html)               { Dom.get(id).innerHTML = html;                        },
  on:   function(ele, type, fn, capture) { Dom.get(ele).addEventListener(type, fn, capture);    },
  un:   function(ele, type, fn, capture) { Dom.get(ele).removeEventListener(type, fn, capture); },
  show: function(ele, type)              { Dom.get(ele).style.display = (type || 'block');      },
  blur: function(ev)                     { ev.target.blur();                                    },

  addClassName:    function(ele, name)     { Dom.toggleClassName(ele, name, true);  },
  removeClassName: function(ele, name)     { Dom.toggleClassName(ele, name, false); },
  toggleClassName: function(ele, name, on) {
    ele = Dom.get(ele);
    var classes = ele.className.split(' ');
    var n = classes.indexOf(name);
    on = (typeof on == 'undefined') ? (n < 0) : on;
    if (on && (n < 0))
      classes.push(name);
    else if (!on && (n >= 0))
      classes.splice(n, 1);
    ele.className = classes.join(' ');
  },

  storage: window.localStorage || {}

}

//=========================================================================
// general purpose helpers (mostly math)
//=========================================================================

var Util = {

  timestamp:        function()                  { return new Date().getTime();                                    },
  toInt:            function(obj, def)          { if (obj !== null) { var x = parseInt(obj, 10); if (!isNaN(x)) return x; } return Util.toInt(def, 0); },
  toFloat:          function(obj, def)          { if (obj !== null) { var x = parseFloat(obj);   if (!isNaN(x)) return x; } return Util.toFloat(def, 0.0); },
  limit:            function(value, min, max)   { return Math.max(min, Math.min(value, max));                     },
  randomInt:        function(min, max)          { return Math.round(Util.interpolate(min, max, Math.random()));   },
  randomChoice:     function(options)           { return options[Util.randomInt(0, options.length-1)];            },
  percentRemaining: function(n, total)          { return (n%total)/total;                                         },
  accelerate:       function(v, accel, dt)      { return v + (accel * dt);                                        },
  interpolate:      function(a,b,percent)       { return a + (b-a)*percent                                        },
  easeIn:           function(a,b,percent)       { return a + (b-a)*Math.pow(percent,2);                           },
  easeOut:          function(a,b,percent)       { return a + (b-a)*(1-Math.pow(1-percent,2));                     },
  easeInOut:        function(a,b,percent)       { return a + (b-a)*((-Math.cos(percent*Math.PI)/2) + 0.5);        },
  exponentialFog:   function(distance, density) { return 1 / (Math.pow(Math.E, (distance * distance * density))); },

  increase:  function(start, increment, max) { // with looping
    var result = start + increment;
    while (result >= max)
      result -= max;
    while (result < 0)
      result += max;
    return result;
  },

  project: function(p, cameraX, cameraY, cameraZ, cameraDepth, width, height, roadWidth) {
    p.camera.x     = (p.world.x || 0) - cameraX;
    p.camera.y     = (p.world.y || 0) - cameraY;
    p.camera.z     = (p.world.z || 0) - cameraZ;
    p.screen.scale = cameraDepth/p.camera.z;
    p.screen.x     = Math.round((width/2)  + (p.screen.scale * p.camera.x  * width/2));
    p.screen.y     = Math.round((height/2) - (p.screen.scale * p.camera.y  * height/2));
    p.screen.w     = Math.round(             (p.screen.scale * roadWidth   * width/2));
  },

  overlap: function(x1, w1, x2, w2, percent) {
    var half = (percent || 1)/2;
    var min1 = x1 - (w1*half);
    var max1 = x1 + (w1*half);
    var min2 = x2 - (w2*half);
    var max2 = x2 + (w2*half);
    return ! ((max1 < min2) || (min1 > max2));
  }

}

//=========================================================================
// POLYFILL for requestAnimationFrame
//=========================================================================

if (!window.requestAnimationFrame) { // http://paulirish.com/2011/requestanimationframe-for-smart-animating/
  window.requestAnimationFrame = window.webkitRequestAnimationFrame || 
                                 window.mozRequestAnimationFrame    || 
                                 window.oRequestAnimationFrame      || 
                                 window.msRequestAnimationFrame     || 
                                 function(callback, element) {
                                   window.setTimeout(callback, 1000 / 60);
                                 }
}

//=========================================================================
// GAME LOOP helpers
//=========================================================================

var Game = {  // a modified version of the game loop from my previous boulderdash game - see http://codeincomplete.com/posts/2011/10/25/javascript_boulderdash/#gameloop

  run: function(options) {

    Game.loadImages(options.images, function(images) {

      options.ready(images); // tell caller to initialize itself because images are loaded and we're ready to rumble

      Game.setKeyListener(options.keys);

      var canvas = options.canvas,    // canvas render target is provided by caller
          update = options.update,    // method to update game logic is provided by caller
          render = options.render,    // method to render the game is provided by caller
          step   = options.step,      // fixed frame step (1/fps) is specified by caller
          stats  = options.stats,     // stats instance is provided by caller
          now    = null,
          last   = Util.timestamp(),
          dt     = 0,
          gdt    = 0;

      Game._stopped = false;

      function frame() {
        if (Game._stopped) return; // e.g. a finish line was crossed -- stop looping instead of running forever
        now = Util.timestamp();
        dt  = Math.min(1, (now - last) / 1000); // using requestAnimationFrame have to be able to handle large delta's caused when it 'hibernates' in a background or non-visible tab
        gdt = gdt + dt;
        while (gdt > step) {
          gdt = gdt - step;
          update(step);
        }
        render();
        if (stats) stats.update(); // stats is optional now -- the clean player-facing page doesn't pass one
        last = now;
        requestAnimationFrame(frame, canvas);
      }
      frame(); // lets get this party started
      Game.playMusic();
    });
  },

  //---------------------------------------------------------------------------

  stop: function() { // used by pages with an actual finish line (the original track loops forever otherwise)
    Game._stopped = true;
  },

  //---------------------------------------------------------------------------

  loadImages: function(names, callback) { // load multiple images and callback when ALL images have loaded
    var result = [];
    var count  = names.length;

    var onload = function() {
      if (--count == 0)
        callback(result);
    };

    for(var n = 0 ; n < names.length ; n++) {
      var name = names[n];
      result[n] = document.createElement('img');
      Dom.on(result[n], 'load', onload);
      result[n].src = "images/" + name + ".png";
    }
  },

  //---------------------------------------------------------------------------

  setKeyListener: function(keys) {
    var onkey = function(ev, mode) {
      var n, k;
      for(n = 0 ; n < keys.length ; n++) {
        k = keys[n];
        k.mode = k.mode || 'up';
        if ((k.key == ev.keyCode) || (k.keys && (k.keys.indexOf(ev.keyCode) >= 0))) {
          // Space bar's default action is a full page-down scroll, arrow
          // keys scroll a line at a time -- neither is wanted while one of
          // these is actually driving the game instead. Only suppresses it
          // for keys a page actually bound here, not e.g. Tab or a browser
          // shortcut.
          ev.preventDefault();
          if (k.mode == mode) {
            k.action.call();
          }
        }
      }
    };
    Dom.on(document, 'keydown', function(ev) { onkey(ev, 'down'); } );
    Dom.on(document, 'keyup',   function(ev) { onkey(ev, 'up');   } );
  },

  //---------------------------------------------------------------------------

  stats: function(parentId, id) { // construct mr.doobs FPS counter - along with friendly good/bad/ok message box

    var result = new Stats();
    result.domElement.id = id || 'stats';
    Dom.get(parentId).appendChild(result.domElement);

    var msg = document.createElement('div');
    msg.style.cssText = "border: 2px solid gray; padding: 5px; margin-top: 5px; text-align: left; font-size: 1.15em; text-align: right;";
    msg.innerHTML = "Your canvas performance is ";
    Dom.get(parentId).appendChild(msg);

    var value = document.createElement('span');
    value.innerHTML = "...";
    msg.appendChild(value);

    setInterval(function() {
      var fps   = result.current();
      var ok    = (fps > 50) ? 'good'  : (fps < 30) ? 'bad' : 'ok';
      var color = (fps > 50) ? 'green' : (fps < 30) ? 'red' : 'gray';
      value.innerHTML       = ok;
      value.style.color     = color;
      msg.style.borderColor = color;
    }, 5000);
    return result;
  },

  //---------------------------------------------------------------------------

  playMusic: function() {
    var music = Dom.get('music');
    // Original racer.mp3/racer.ogg were licensed ONLY for the upstream
    // project's own demo (see this repo's README) -- subbed in Crypt
    // Crawl's own tracks temporarily, now replaced with Skull Racer's own
    // original chiptune set. Distinct songs, not a single track's format
    // fallback, so this cycles through them instead of looping just the
    // first one forever. Theme plays first, then 1/2/3 cycle in after.
    var playlist = [
      'music/skull-racer-theme.mp3',
      'music/skull-racer-1.mp3',
      'music/skull-racer-2.wav',
      'music/skull-racer-3.wav'
    ];
    var index    = 0;
    music.loop  = false;
    music.volume = 0.35; // was 0.05 -- inaudible at a sane system volume
    music.muted = (Dom.storage.muted === "true");
    music.addEventListener('ended', function() {
      index = (index + 1) % playlist.length;
      music.src = playlist[index];
      music.play();
    });
    Dom.toggleClassName('mute', 'on', music.muted);
    Dom.on('mute', 'click', function() {
      Dom.storage.muted = music.muted = !music.muted;
      Dom.toggleClassName('mute', 'on', music.muted);
    });

    // Calling play() immediately here (as this used to) gets silently
    // rejected by the browser's autoplay policy -- no real user gesture
    // has happened yet at "page ready" time -- and nothing ever retried,
    // so music just never started even though nothing looked broken.
    // Deferring to the first real interaction actually satisfies that
    // requirement, since a player has to press a key to drive anyway.
    Game.onFirstInteraction(function() { music.play(); });
  },

  //---------------------------------------------------------------------------

  // Shared first-real-user-gesture hook -- see playMusic()'s own comment.
  // Exists so OTHER audio (e.g. an engine sound on a specific page) can
  // start at the same genuinely-unlocked moment instead of each needing
  // its own listener, and so registering after the moment has already
  // passed still fires immediately rather than never firing at all.
  _firstInteractionFired: false,
  _firstInteractionCallbacks: [],
  _firstInteractionListening: false,
  onFirstInteraction: function(fn) {
    if (Game._firstInteractionFired) { fn(); return; }
    Game._firstInteractionCallbacks.push(fn);
    if (!Game._firstInteractionListening) {
      Game._firstInteractionListening = true;
      var fire = function() {
        Game._firstInteractionFired = true;
        document.removeEventListener('keydown', fire);
        document.removeEventListener('click', fire);
        document.removeEventListener('touchstart', fire);
        Game._firstInteractionCallbacks.forEach(function(cb) { cb(); });
        Game._firstInteractionCallbacks = [];
      };
      document.addEventListener('keydown', fire);
      document.addEventListener('click', fire);
      // touchstart, not just click -- iOS Safari only counts an AudioContext
      // .resume()/media .play() as gesture-triggered when it happens inside
      // the actual touch handler; the synthesized click a tap fires afterward
      // is too late for it, even though it's plenty trusted on desktop/Android.
      document.addEventListener('touchstart', fire, { passive: true });
    }
  }

}

//=========================================================================
// canvas rendering helpers
//=========================================================================

var Render = {

  polygon: function(ctx, x1, y1, x2, y2, x3, y3, x4, y4, color) {
    ctx.fillStyle = color;
    ctx.beginPath();
    ctx.moveTo(x1, y1);
    ctx.lineTo(x2, y2);
    ctx.lineTo(x3, y3);
    ctx.lineTo(x4, y4);
    ctx.closePath();
    ctx.fill();
  },

  //---------------------------------------------------------------------------

  segment: function(ctx, width, lanes, x1, y1, w1, x2, y2, w2, fog, color) {

    var r1 = Render.rumbleWidth(w1, lanes),
        r2 = Render.rumbleWidth(w2, lanes),
        l1 = Render.laneMarkerWidth(w1, lanes),
        l2 = Render.laneMarkerWidth(w2, lanes),
        lanew1, lanew2, lanex1, lanex2, lane;
    
    ctx.fillStyle = color.grass;
    ctx.fillRect(0, y2, width, y1 - y2);
    
    Render.polygon(ctx, x1-w1-r1, y1, x1-w1, y1, x2-w2, y2, x2-w2-r2, y2, color.rumble);
    Render.polygon(ctx, x1+w1+r1, y1, x1+w1, y1, x2+w2, y2, x2+w2+r2, y2, color.rumble);
    Render.polygon(ctx, x1-w1,    y1, x1+w1, y1, x2+w2, y2, x2-w2,    y2, color.road);
    
    if (color.lane) {
      lanew1 = w1*2/lanes;
      lanew2 = w2*2/lanes;
      lanex1 = x1 - w1 + lanew1;
      lanex2 = x2 - w2 + lanew2;
      for(lane = 1 ; lane < lanes ; lanex1 += lanew1, lanex2 += lanew2, lane++)
        Render.polygon(ctx, lanex1 - l1/2, y1, lanex1 + l1/2, y1, lanex2 + l2/2, y2, lanex2 - l2/2, y2, color.lane);
    }
    
    Render.fog(ctx, 0, y1, width, y2-y1, fog);
  },

  //---------------------------------------------------------------------------

  background: function(ctx, background, width, height, layer, rotation, offset) {

    rotation = rotation || 0;
    offset   = offset   || 0;

    var imageW = layer.w/2;
    var imageH = layer.h;

    var sourceX = layer.x + Math.floor(layer.w * rotation);
    var sourceY = layer.y
    var sourceW = Math.min(imageW, layer.x+layer.w-sourceX);
    var sourceH = imageH;
    
    var destX = 0;
    var destY = offset;
    var destW = Math.floor(width * (sourceW/imageW));
    var destH = height;

    ctx.drawImage(background, sourceX, sourceY, sourceW, sourceH, destX, destY, destW, destH);
    if (sourceW < imageW)
      ctx.drawImage(background, layer.x, sourceY, imageW-sourceW, sourceH, destW-1, destY, width-destW, destH);
  },

  //---------------------------------------------------------------------------

  sprite: function(ctx, width, height, resolution, roadWidth, sprites, sprite, scale, destX, destY, offsetX, offsetY, clipY) {

                    //  scale for projection AND relative to roadWidth (for tweakUI)
    var destW  = (sprite.w * scale * width/2) * (SPRITES.SCALE * roadWidth);
    var destH  = (sprite.h * scale * width/2) * (SPRITES.SCALE * roadWidth);

    destX = destX + (destW * (offsetX || 0));
    destY = destY + (destH * (offsetY || 0));

    var clipH = clipY ? Math.max(0, destY+destH-clipY) : 0;
    if (clipH < destH)
      ctx.drawImage(sprites, sprite.x, sprite.y, sprite.w, sprite.h - (sprite.h*clipH/destH), destX, destY, destW, destH - clipH);

  },

  //---------------------------------------------------------------------------

  player: function(ctx, width, height, resolution, roadWidth, sprites, speedPercent, scale, destX, destY, steer, updown) {

    var bounce = (1.5 * Math.random() * speedPercent * resolution) * Util.randomChoice([-1,1]);
    var sprite;
    if (steer < 0)
      sprite = (updown > 0) ? SPRITES.PLAYER_UPHILL_LEFT : SPRITES.PLAYER_LEFT;
    else if (steer > 0)
      sprite = (updown > 0) ? SPRITES.PLAYER_UPHILL_RIGHT : SPRITES.PLAYER_RIGHT;
    else
      sprite = (updown > 0) ? SPRITES.PLAYER_UPHILL_STRAIGHT : SPRITES.PLAYER_STRAIGHT;

    Render.sprite(ctx, width, height, resolution, roadWidth, sprites, sprite, scale, destX, destY + bounce, -0.5, -1);
  },

  //---------------------------------------------------------------------------

  fog: function(ctx, x, y, width, height, fog) {
    if (fog < 1) {
      ctx.globalAlpha = (1-fog)
      ctx.fillStyle = COLORS.FOG;
      ctx.fillRect(x, y, width, height);
      ctx.globalAlpha = 1;
    }
  },

  rumbleWidth:     function(projectedRoadWidth, lanes) { return projectedRoadWidth/Math.max(6,  2*lanes); },
  laneMarkerWidth: function(projectedRoadWidth, lanes) { return projectedRoadWidth/Math.max(32, 8*lanes); }

}

//=============================================================================
// RACING GAME CONSTANTS
//=============================================================================

var KEY = {
  LEFT:  37,
  UP:    38,
  RIGHT: 39,
  DOWN:  40,
  SPACE: 32,
  A:     65,
  D:     68,
  S:     83,
  W:     87
};

// Not sprite-based -- these are hardcoded fill colors for the
// procedurally-drawn road/grass/fog, so replacing every image asset never
// touched them. Bright greens (#10AA10/#009A00 grass, #005108 fog) swapped
// for a scorched-earth palette to match the dark wasteland theme.
var COLORS = {
  SKY:  '#72D7EE',
  TREE: '#2a1d16',
  FOG:  '#3a2216',
  LIGHT:  { road: '#6B6B6B', grass: '#4a2e1f', rumble: '#555555', lane: '#CCCCCC'  },
  DARK:   { road: '#696969', grass: '#3a2216', rumble: '#BBBBBB'                   },
  START:  { road: 'white',   grass: 'white',   rumble: 'white'                     },
  FINISH: { road: 'black',   grass: 'black',   rumble: 'black'                     }
};

var BACKGROUND = {
  HILLS: { x:   5, y:   5, w: 1280, h: 480 },
  SKY:   { x:   5, y: 495, w: 1280, h: 480 },
  TREES: { x:   5, y: 985, w: 1280, h: 480 }
};

// Kept in sync BY HAND with images/sprites.js (rake resprite's actual
// output) -- the game reads sprite coordinates from THIS copy, not that
// file, since nothing here loads sprites.js as a <script>. Forgetting this
// step after a resprite run doesn't error, it just silently samples the
// new sprites.png at the OLD (no longer matching) coordinates -- every
// sprite still "renders", just as whatever now happens to be at that old
// x/y instead. Always paste images/sprites.js's SPRITES object here again
// after every rake resprite.
var SPRITES = {
  PALM_TREE:              { x:    5, y:    5, w:  215, h:  540 },
  TREE1:                  { x:  230, y:    5, w:  360, h:  360 },
  DEAD_TREE1:             { x:  600, y:    5, w:  135, h:  332 },
  BOULDER3:               { x:  745, y:    5, w:  320, h:  220 },
  COLUMN:                 { x:    5, y:  555, w:  200, h:  315 },
  BOULDER2:               { x:  745, y:  235, w:  298, h:  140 },
  TREE2:                  { x:  215, y:  555, w:  282, h:  295 },
  DEAD_TREE2:             { x:  507, y:  555, w:  150, h:  260 },
  BOULDER1:               { x:  667, y:  555, w:  168, h:  248 },
  BUSH1:                  { x:  745, y:  385, w:  240, h:  155 },
  CACTUS:                 { x:  230, y:  375, w:  235, h:  118 },
  BUSH2:                  { x:    5, y:  880, w:  232, h:  152 },
  BILLBOARD02:            { x: 1075, y:    5, w:  230, h:  220 },
  BILLBOARD06:            { x: 1075, y:  235, w:  230, h:  220 },
  BILLBOARD07:            { x: 1075, y:  465, w:  230, h:  220 },
  BILLBOARD08:            { x: 1075, y:  695, w:  230, h:  220 },
  BILLBOARD09:            { x:    5, y: 1042, w:  230, h:  220 },
  BILLBOARD01:            { x:  245, y: 1042, w:  230, h:  220 },
  BILLBOARD04:            { x:  485, y: 1042, w:  230, h:  220 },
  BILLBOARD03:            { x:  725, y: 1042, w:  230, h:  220 },
  BILLBOARD05:            { x:  965, y: 1042, w:  230, h:  220 },
  STUMP:                  { x:  845, y:  555, w:  195, h:  140 },
  SEMI:                   { x:  600, y:  347, w:  122, h:  144 },
  TRUCK:                  { x: 1075, y:  925, w:  100, h:   78 },
  CAR03:                  { x: 1185, y:  925, w:   88, h:   55 },
  CAR02:                  { x:  475, y:  375, w:   80, h:   59 },
  CAR04:                  { x:  845, y:  705, w:   80, h:   57 },
  CAR01:                  { x:  935, y:  705, w:   80, h:   56 },
  PLAYER_UPHILL_RIGHT:    { x:  475, y:  444, w:   80, h:   45 },
  PLAYER_UPHILL_STRAIGHT: { x:  247, y:  880, w:   80, h:   45 },
  PLAYER_UPHILL_LEFT:     { x:  337, y:  880, w:   80, h:   45 },
  PLAYER_LEFT:            { x:  600, y:  501, w:   80, h:   41 },
  PLAYER_STRAIGHT:        { x:  230, y:  503, w:   80, h:   41 },
  PLAYER_RIGHT:           { x:  320, y:  503, w:   80, h:   41 }
};

SPRITES.SCALE = 0.3 * (1/SPRITES.PLAYER_STRAIGHT.w) // the reference sprite width should be 1/3rd the (half-)roadWidth

SPRITES.BILLBOARDS = [SPRITES.BILLBOARD01, SPRITES.BILLBOARD02, SPRITES.BILLBOARD03, SPRITES.BILLBOARD04, SPRITES.BILLBOARD05, SPRITES.BILLBOARD06, SPRITES.BILLBOARD07, SPRITES.BILLBOARD08, SPRITES.BILLBOARD09];
SPRITES.PLANTS     = [SPRITES.TREE1, SPRITES.TREE2, SPRITES.DEAD_TREE1, SPRITES.DEAD_TREE2, SPRITES.PALM_TREE, SPRITES.BUSH1, SPRITES.BUSH2, SPRITES.CACTUS, SPRITES.STUMP, SPRITES.BOULDER1, SPRITES.BOULDER2, SPRITES.BOULDER3];
SPRITES.CARS       = [SPRITES.CAR01, SPRITES.CAR02, SPRITES.CAR03, SPRITES.CAR04, SPRITES.SEMI, SPRITES.TRUCK];

