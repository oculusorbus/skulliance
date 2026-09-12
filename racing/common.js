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

    // ?v= cache-buster when the page supplied one (skullracer.php sets
    // RACER_ASSET_V from the root VERSION file -- see its own comment on
    // why: everything static under racing/ is served with a seven-day
    // max-age and no revalidation, so a repacked sprites.png would
    // otherwise keep being read from cache while the freshly-loaded
    // coordinates in SPRITES expect the NEW sheet -- every sprite
    // silently sampling the wrong part of the image). Optional on
    // purpose: standalone racing/index.html and the v1-v3/dev.html pages
    // don't define it, and just load the plain URL as before.
    var assetV = (typeof RACER_ASSET_V !== 'undefined' && RACER_ASSET_V) ? ('?v=' + RACER_ASSET_V) : '';

    for(var n = 0 ; n < names.length ; n++) {
      var name = names[n];
      result[n] = document.createElement('img');
      Dom.on(result[n], 'load', onload);
      result[n].src = "/staking/racing/images/" + name + ".png" + assetV;
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

  // Web Audio API, NOT the <audio id='music'> element this used to drive.
  // Root cause of a long-running, badly misdiagnosed bug: on iOS, a playing
  // HTMLMediaElement creates a system "Now Playing" session, and once one
  // exists the OS routes a connected game controller's buttons to the MEDIA
  // REMOTE (play/pause/skip) instead of delivering them to the page's
  // Gamepad API at all. In the installed PWA that read as the controller
  // working for exactly one press and then going dead -- and pausing the
  // music from that popped-up media player handed control straight back,
  // which is what finally identified it. Several rounds of "fixes" to the
  // gamepad polling itself were chasing this symptom; none of them could
  // have worked, because the input was never reaching the page to begin
  // with. An AudioContext creates no Now Playing session, so there is
  // nothing for iOS to hand the controller to. Same reasoning that already
  // moved the collision/lap SFX off <audio> elements (see racing/index.html
  // and sounds/collision-data.js) -- this is the last one that was left.
  //
  // fetch()+decodeAudioData rather than the SFX's base64-embedding: these
  // tracks are ~3MB each, far too big to inline, and one at a time rather
  // than all three up front since a decoded AudioBuffer is raw PCM (tens of
  // MB per track) and holding all three would be pointless on a phone. The
  // trade is a short silent gap while the next one decodes at each track
  // change, which is a fine price for the controller working at all.
  playMusic: function() {
    // Original racer.mp3/racer.ogg were licensed ONLY for the upstream
    // project's own demo (see this repo's README) -- subbed in Crypt
    // Crawl's own tracks temporarily, now replaced with Skull Racer's own
    // original chiptune set. Distinct songs, not a single track's format
    // fallback, so this cycles through them instead of looping just the
    // first one forever. Theme plays first, then 1/2 cycle in after.
    // (Was 4 files/1-3 -- the original 2 turned out to be a duplicate of 1
    // and got dropped, the original 3 renamed to 2 in its place; all mp3
    // now too, the stray .wav's were a mistake.)
    var playlist = [
      '/staking/racing/music/skull-racer-theme.mp3',
      '/staking/racing/music/skull-racer-1.mp3',
      '/staking/racing/music/skull-racer-2.mp3'
    ];
    var index = 0;
    var ctx   = new (window.AudioContext || window.webkitAudioContext)();
    var gain  = ctx.createGain();
    var muted = (Dom.storage.muted === "true");
    var MUSIC_VOLUME = 0.35; // was 0.05 -- inaudible at a sane system volume
    gain.gain.value = muted ? 0 : MUSIC_VOLUME;
    gain.connect(ctx.destination);
    var currentSource = null;
    var musicStarted  = false;

    function playTrack(i) {
      fetch(playlist[i])
        .then(function(response) { return response.arrayBuffer(); })
        .then(function(encoded) { return ctx.decodeAudioData(encoded); })
        .then(function(audioBuffer) {
          var src = ctx.createBufferSource();
          src.buffer = audioBuffer;
          src.connect(gain);
          src.onended = function() {
            // Ignore a node that's already been superseded -- stop()ing or
            // replacing a source fires this too, and acting on those would
            // skip tracks or start two at once.
            if (src !== currentSource) return;
            index = (index + 1) % playlist.length;
            playTrack(index);
          };
          currentSource = src;
          src.start(0);
        })
        .catch(function() {}); // a track failing to load shouldn't take the game's audio down with it
    }

    // Mute is a gain value now, not <audio>.muted -- the track keeps
    // playing silently, exactly as before, so unmuting drops back into the
    // right place rather than restarting. Dom.storage stays the single
    // source of truth that racing/index.html reads for the Web Audio
    // engine/collision/lap sounds.
    //
    // Exposed as Game.toggleMute() rather than living only inside the click
    // handler so the gamepad can drive it directly. It used to do that by
    // dispatching a synthetic .click() on #mute, which meant a DOM event
    // fired from inside the input poll loop -- harmless in itself, but it
    // was also toggling a live <audio> element back when one existed, which
    // is what actually killed controller input (see this function's own
    // top comment). Calling a plain function instead keeps that whole
    // category of interaction off the table.
    Game.toggleMute = function() {
      muted = !muted;
      Dom.storage.muted = muted;
      gain.gain.value = muted ? 0 : MUSIC_VOLUME;
      Dom.toggleClassName('mute', 'on', muted);
      return muted;
    };
    Dom.toggleClassName('mute', 'on', muted);
    Dom.on('mute', 'click', function() { Game.toggleMute(); });

    // Starting immediately here (as this used to) gets silently rejected by
    // the browser's autoplay policy -- no real user gesture has happened at
    // "page ready" time. Deferring to the first interaction satisfies that,
    // since a player has to press something to drive anyway.
    //
    // But it can't be ONE-SHOT, and this is why: an AudioContext starts
    // 'suspended' and iOS only honours resume() from inside a genuine
    // user-gesture call stack. A gamepad button press very likely isn't one
    // there (same platform family as iOS reserving the controller's PS
    // button system-wide), so a controller-only player would fire the first
    // -interaction hook, get resume() quietly refused, and sit in silence
    // forever with nothing retrying -- reported exactly that way once the
    // music moved off <audio>. So: starting is idempotent, and every later
    // real gesture gets another go at unlocking, until one takes. resume()
    // is safe to call repeatedly, and a source started on a still-suspended
    // context simply begins the moment it does resume.
    Game.startMusic = function() {
      if (ctx.resume) ctx.resume();
      if (musicStarted) return;
      musicStarted = true;
      playTrack(index);
    };
    Game.onFirstInteraction(Game.startMusic);
    ['click', 'touchstart', 'keydown'].forEach(function(type) {
      document.addEventListener(type, function() { Game.startMusic(); }, { passive: true });
    });
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
        window.removeEventListener('gamepadconnected', fire);
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
      // A controller-only player (paired over Bluetooth, never taps/clicks
      // the page or touches a keyboard) never fires any of the 3 above --
      // audio stayed silent until they manually hit the mute icon, whose
      // own click was what ACTUALLY unlocked it, not anything about the
      // icon itself. gamepadconnected only ever fires after a genuine
      // physical button press on the controller (see racing/index.html's
      // own comment on that same requirement) -- a real gesture, just one
      // the other 3 listeners have no way to see.
      window.addEventListener('gamepadconnected', fire);
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

  /*
   * REALISTIC BACKGROUND. Sibling of background() above, for a layer that lives
   * in its OWN file as a single copy rather than as a band in the packed sheet.
   *
   * background() takes `imageW = layer.w/2` because every band in background.png
   * holds the panorama TWICE side by side -- the duplicate exists purely so a
   * scroll window can straddle the seam. Point that at a single-copy file and it
   * shows half the image, zoomed 2x, and reads past the right edge into nothing.
   *
   * ASPECT IS PRESERVED, which the first version of this got wrong. It mapped the
   * full image width to the full canvas width AND the full height to the full
   * height, so any art whose aspect differed from the canvas was distorted --
   * hills at 2.67 into a 1.33 canvas came out squeezed to exactly half width
   * ("narrow mountaintops"), and an 11% squeeze on the sky turned a round moon
   * into an oval. The retro bands escaped it only by being authored at 4:3.
   *
   * So the scale comes from the VERTICAL -- the band fills the canvas height,
   * matching background()'s convention -- and the horizontal scale is then forced
   * to match it. One copy of the image therefore occupies iw*scale canvas pixels,
   * which may be wider OR narrower than the canvas, so it tiles in a loop rather
   * than in a fixed pair of draws. That also removes the old assumption that the
   * image is at least as wide as the viewport: a 1168px sky on a 1800px canvas
   * now repeats instead of running out.
   *
   * `lift` raises the band by a fraction of the canvas height. The parallax art
   * is framed with its content at different heights -- measured, the realistic
   * treeline starts 62% down its frame against the retro band's 31% -- and
   * anything below the horizon is painted over by the road, which draws after the
   * background. lift is the per-layer correction for that; 0 means "as framed".
   */
  backgroundSingle: function(ctx, img, width, height, rotation, offset, lift) {

    if (!img || !img.width) return;

    rotation = rotation || 0;
    offset   = offset   || 0;

    /*
     * ONE COPY SPANS ONE SCREEN WIDTH. That is the retro convention -- a band in
     * background.png is 640 logical px mapped across the whole canvas -- and the
     * realistic art was drawn for it, so it is the scale the art expects.
     *
     * An earlier attempt drove the scale off the HEIGHT instead (fill the canvas
     * vertically, let width follow). That is aspect-correct but it is the wrong
     * zoom: a 2000x750 band then spans two screen widths, so you see half the
     * panorama at ~1:1 and the trees come out as redwoods. Reported exactly that
     * way.
     *
     * So: fit the WIDTH, and let the height fall out of the same factor. The band
     * ends up SHORTER than the canvas rather than filling it, which is what a
     * parallax strip should be -- sky and ridgeline above the horizon, nothing
     * below it. It also happens to put the art back where the retro band had it:
     * the realistic treeline's content starts 62% into a band 384px tall, which
     * is y=238 on a 768px canvas, and the retro band's 31% of 768 is also 238.
     */
    var iw = img.width, ih = img.height;
    var scale = width / iw;                // horizontal drives it; vertical follows
    var dwPer = width;                     // one copy spans exactly one screen
    var destH = ih * scale;
    if (!(dwPer > 0) || !(destH > 0)) return;

    var destY = offset - (lift || 0) * height;
    // rotation can arrive negative or >1; fold it into [0,1) before scaling.
    var frac  = rotation - Math.floor(rotation);
    var x     = -frac * dwPer;

    // Tile until the canvas is covered. One extra copy at the left edge keeps a
    // sub-pixel gap from showing when x lands just past 0.
    for (; x < width; x += dwPer)
      ctx.drawImage(img, 0, 0, iw, ih, Math.floor(x), destY, Math.ceil(dwPer) + 1, Math.ceil(destH));
  },

  //---------------------------------------------------------------------------

  /*
   * REALISTIC ROAD. A drop-in sibling of segment() above -- same arguments, same
   * one call site in index.html -- so the retro renderer stays byte-identical and
   * cannot regress when this is switched on.
   *
   * ONE change from flat shading: the LIP on the kerb. A flat kerb looks like
   * coloured tape; a real one has a top face and a shadowed outer side. Drawing
   * the outer half a shade darker is two extra polygons and reads as depth. It
   * is a split across the kerb's WIDTH, so it travels with the road.
   *
   * NO VERTICAL GRADIENTS. This function used to grade each band from darker at
   * its far edge to lighter at its near edge, and it was a mistake: the gradient
   * was built from createLinearGradient(0, y2, 0, y1) -- SCREEN coordinates.
   * Perspective puts each band at roughly the same screen y on every frame, so
   * the ramp stayed pinned to the display while the road slid underneath it.
   * Near the horizon the bands are 1-2px tall and it did nothing; near the camera
   * they are tall, so it painted a fixed light-to-dark wash across the bottom of
   * the screen that never moved. Reported as looking "like a broken gradient
   * image", and correctly so.
   *
   * Anything shaded by DISTANCE has to key off the segment index (which scrolls)
   * or the existing fog term, never off y. The fog() call below already does the
   * distance job properly.
   *
   * Deliberately no texture sampling either: that is a per-scanline drawImage,
   * ~768 calls a frame, and it needs its own performance decision.
   */
  segmentRealistic: function(ctx, width, lanes, x1, y1, w1, x2, y2, w2, fog, color) {

    var r1 = Render.rumbleWidth(w1, lanes),
        r2 = Render.rumbleWidth(w2, lanes),
        l1 = Render.laneMarkerWidth(w1, lanes),
        l2 = Render.laneMarkerWidth(w2, lanes),
        lanew1, lanew2, lanex1, lanex2, lane;

    ctx.fillStyle = color.grass;
    ctx.fillRect(0, y2, width, y1 - y2);

    // Kerb: outer half shaded down so it reads as a raised edge, not tape.
    Render.polygon(ctx, x1-w1-r1, y1, x1-w1, y1, x2-w2, y2, x2-w2-r2, y2, Render.shade(color.rumble, -0.28));
    Render.polygon(ctx, x1+w1+r1, y1, x1+w1, y1, x2+w2, y2, x2+w2+r2, y2, Render.shade(color.rumble, -0.28));
    Render.polygon(ctx, x1-w1-r1/2, y1, x1-w1, y1, x2-w2, y2, x2-w2-r2/2, y2, color.rumble);
    Render.polygon(ctx, x1+w1+r1/2, y1, x1+w1, y1, x2+w2, y2, x2+w2+r2/2, y2, color.rumble);

    Render.polygon(ctx, x1-w1, y1, x1+w1, y1, x2+w2, y2, x2-w2, y2, color.road);

    if (color.lane) {
      lanew1 = w1*2/lanes;
      lanew2 = w2*2/lanes;
      lanex1 = x1 - w1 + lanew1;
      lanex2 = x2 - w2 + lanew2;
      for(lane = 1 ; lane < lanes ; lanex1 += lanew1, lanex2 += lanew2, lane++)
        Render.polygon(ctx, lanex1 - l1/2, y1, lanex1 + l1/2, y1, lanex2 + l2/2, y2, lanex2 - l2/2, y2, color.lane);
    }

    Render.fog(ctx, 0, y1, width, y2-y1, fog, COLORS_REALISTIC.FOG);
  },

  /*
   * Lighten (amt > 0) or darken (amt < 0) a #rrggbb by a fraction. Named colours
   * are returned untouched -- COLORS_REALISTIC.START/FINISH are 'white'/'black'
   * and must pass through, and a start line that silently turned grey would be a
   * confusing thing to debug.
   */
  shade: function(hex, amt) {
    if (typeof hex !== 'string' || hex.charAt(0) !== '#' || hex.length !== 7) return hex;
    var n = parseInt(hex.slice(1), 16);
    var r = (n >> 16) & 255, g = (n >> 8) & 255, b = n & 255;
    function adj(c) { return Math.max(0, Math.min(255, Math.round(amt < 0 ? c * (1 + amt) : c + (255 - c) * amt))); }
    r = adj(r); g = adj(g); b = adj(b);
    return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
  },

  //---------------------------------------------------------------------------

  sprite: function(ctx, width, height, resolution, roadWidth, sprites, sprite, scale, destX, destY, offsetX, offsetY, clipY, flip) {

    /*
     * SOURCE SIZE AND LAYOUT SIZE ARE NOT THE SAME THING, once art exists at a
     * higher resolution than the sheet it replaces.
     *
     * SPRITES.SCALE is 0.3/PLAYER_STRAIGHT.w, so on-screen size is measured in
     * units of the PLAYER sprite's pixel width. That is fine while every sprite
     * shares one resolution -- scale them all and nothing moves. It breaks the
     * moment one sprite is re-rendered on its own: a realistic car at 841px
     * against an 80px player would draw ~10.5x too large and fill the screen.
     *
     * So `w`/`h` stay the SOURCE region to sample, and optional `dw`/`dh` give
     * the footprint to lay it out at. Both default to w/h, so every existing
     * sprite is byte-for-byte unaffected and the retro sheet cannot regress.
     *
     * `sprite.img` likewise lets one entry come from its own image instead of
     * the shared sheet -- which is what makes a partial art pass previewable
     * without repacking sprites.png first.
     */
    var sheet  = sprite.img || sprites;
    var layoutW = (sprite.dw || sprite.w);
    var layoutH = (sprite.dh || sprite.h);
    var destW  = (layoutW * scale * width/2) * (SPRITES.SCALE * roadWidth);
    var destH  = (layoutH * scale * width/2) * (SPRITES.SCALE * roadWidth);

    destX = destX + (destW * (offsetX || 0));
    destY = destY + (destH * (offsetY || 0));

    var clipH = clipY ? Math.max(0, destY+destH-clipY) : 0;
    var drawH = destH - clipH;
    if (clipH < destH) {
      if (flip) {
        // Traffic cars are a single static sprite drawn at a fixed
        // perspective angle -- always looks like it's leaning into the
        // same turn regardless of which way the road actually curves
        // there. Mirroring horizontally (around its own destX..destX+destW
        // span, not the whole canvas) flips that lean the other way, so
        // the sprite reads as banking into whichever direction the road
        // is actually curving at that point.
        ctx.save();
        ctx.translate(destX + destW, destY);
        ctx.scale(-1, 1);
        ctx.drawImage(sheet, sprite.x, sprite.y, sprite.w, sprite.h - (sprite.h*clipH/destH), 0, 0, destW, drawH);
        ctx.restore();
      } else {
        ctx.drawImage(sheet, sprite.x, sprite.y, sprite.w, sprite.h - (sprite.h*clipH/destH), destX, destY, destW, drawH);
      }
    }

  },

  //---------------------------------------------------------------------------

  player: function(ctx, width, height, resolution, roadWidth, sprites, speedPercent, scale, destX, destY, steer, updown, jumpOffset, braking) {

    var bounce = (1.5 * Math.random() * speedPercent * resolution) * Util.randomChoice([-1,1]);
    var name;
    if (steer < 0)
      name = (updown > 0) ? 'PLAYER_UPHILL_LEFT' : 'PLAYER_LEFT';
    else if (steer > 0)
      name = (updown > 0) ? 'PLAYER_UPHILL_RIGHT' : 'PLAYER_RIGHT';
    else
      name = (updown > 0) ? 'PLAYER_UPHILL_STRAIGHT' : 'PLAYER_STRAIGHT';
    // braking (optional, undefined on every page but index.html/
    // v4.final.html's own brake-light feature) swaps to that same angle's
    // _BRAKE sprite -- identical car, just the tail-light pixels recolored
    // brighter (see racing/images/sprites/ and this project's own
    // SPRITE-REPLACEMENT-BRIEF.md) -- rather than a separate lighting
    // effect layered on top.
    if (braking) name += '_BRAKE';
    var sprite = SPRITES[name];

    // jumpOffset (optional, undefined on every page but index.html/
    // v4.final.html's own crest-jump feature -- see that page's own
    // comment) lifts the sprite in screen-space only, same idea as
    // `bounce` just driven by the road's actual shape instead of noise.
    Render.sprite(ctx, width, height, resolution, roadWidth, sprites, sprite, scale, destX, destY + bounce - (jumpOffset || 0), -0.5, -1);
  },

  //---------------------------------------------------------------------------

  fog: function(ctx, x, y, width, height, fog, color) {
    if (fog < 1) {
      ctx.globalAlpha = (1-fog)
      /*
       * Optional colour, defaulting to the retro palette. This used to read
       * COLORS.FOG unconditionally, which meant the realistic palette's own FOG
       * was never applied -- distant grass faded to the retro warm brown
       * (#241a10) and then met a near-black treeline, leaving a hard step at the
       * horizon. Reported from a screenshot.
       */
      ctx.fillStyle = color || COLORS.FOG;
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
// touched them. First pass swapped the original bright greens for a
// generic "scorched-earth" brown; this pass samples the actual box art
// (images/skullracer.jpg -- cracked charcoal-brown pavement, near-black
// rocky ground) for the real values instead of an approximation. Kept the
// original's alternating-contrast PATTERN (road stays close to constant
// between light/dark segments, rumble strips swing hard between much
// darker and much lighter than the road for the "flashing curb" motion
// cue, grass stays darkest throughout) -- only retinted into the box
// art's family, not restructured, so it doesn't get any harder to read
// the road at speed than it already was.
var COLORS = {
  SKY:  '#72D7EE',
  TREE: '#2a1d16',
  FOG:  '#241a10',
  LIGHT:  { road: '#4a3d2c', grass: '#201808', rumble: '#241c10', lane: '#d9c8a0'  },
  DARK:   { road: '#453825', grass: '#170f06', rumble: '#9c8a68'                   },
  START:  { road: 'white',   grass: 'white',   rumble: 'white'                     },
  FINISH: { road: 'black',   grass: 'black',   rumble: 'black'                     }
};

/*
 * REALISTIC PALETTE. Paired with Render.segmentRealistic below and selected by
 * index.html's REALISTIC_PREVIEW flag; COLORS above is untouched.
 *
 * THE ALTERNATION IS THE MOTION CUE, not decoration. Consecutive segments flip
 * between LIGHT and DARK, and those scrolling bands are how the eye reads speed
 * on a road with no other moving reference. An earlier version of this palette
 * set road and grass IDENTICAL in both states to kill the banding -- which did
 * remove the stripes, and removed all sense of movement with them. Reported from
 * play: "it appears like a flat image underneath that doesn't move."
 *
 * So the alternation stays and is TUNED instead. Measured luminance deltas:
 *
 *   original   road 5.1   grass 8.5   (grass was the loud one)
 *   here       road 4.0   grass 5.0   kerb unchanged at ~110
 *
 * Quiet enough to stop reading as 16-bit striping, loud enough that the road
 * still moves under you. Zero is not the target; subtle is.
 *
 * Rumble and lane markers keep their full contrast -- that is a painted kerb and
 * a dashed centre line, which stripe in reality too.
 *
 * Desaturated relative to COLORS because the originals are quite warm/saturated
 * for asphalt; real tarmac sits near neutral grey-brown.
 */
var COLORS_REALISTIC = {
  SKY:  '#8fa4b0',
  TREE: '#1e1a16',
  /*
   * MEASURED, not chosen: this is the average colour of the bottom edge of
   * background-realistic/trees.png (#0a0907), which is what the fogged grass
   * meets at the horizon. Anything lighter leaves a visible step where the
   * terrain stops and the treeline starts -- at #20211f it was 3.6x brighter
   * than the treeline base and the seam was obvious.
   *
   * If the tree art is re-cut, re-sample it:
   *   ffmpeg -i trees.png -vf "crop=iw:ih*0.04:0:ih*0.96,scale=1:1" \
   *          -f rawvideo -pix_fmt rgb24 -
   */
  FOG:  '#0b0a08',
  LIGHT:  { road: '#43413e', grass: '#272b1f', rumble: '#8e877b', lane: '#c8c2b2' },
  DARK:   { road: '#3f3d3a', grass: '#22261a', rumble: '#2b2a27'                  },
  START:  { road: 'white',   grass: 'white',   rumble: 'white'                    },
  FINISH: { road: 'black',   grass: 'black',   rumble: 'black'                    }
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
  PALM_TREE:                    { x:    5, y:    5, w:  215, h:  540 },
  TREE1:                        { x:  230, y:    5, w:  360, h:  360 },
  DEAD_TREE1:                   { x:  600, y:    5, w:  135, h:  332 },
  BOULDER3:                     { x:  745, y:    5, w:  320, h:  220 },
  COLUMN:                       { x:    5, y:  555, w:  200, h:  315 },
  BOULDER2:                     { x:  745, y:  235, w:  298, h:  140 },
  TREE2:                        { x:  215, y:  555, w:  282, h:  295 },
  DEAD_TREE2:                   { x:  507, y:  555, w:  150, h:  260 },
  BOULDER1:                     { x:  667, y:  555, w:  168, h:  248 },
  BUSH1:                        { x:  745, y:  385, w:  240, h:  155 },
  CACTUS:                       { x:  230, y:  375, w:  235, h:  118 },
  BUSH2:                        { x:    5, y:  880, w:  232, h:  152 },
  BILLBOARD01:                  { x: 1075, y:    5, w:  230, h:  220 },
  BILLBOARD02:                  { x: 1075, y:  235, w:  230, h:  220 },
  BILLBOARD03:                  { x: 1075, y:  465, w:  230, h:  220 },
  BILLBOARD04:                  { x: 1075, y:  695, w:  230, h:  220 },
  BILLBOARD05:                  { x:    5, y: 1042, w:  230, h:  220 },
  BILLBOARD06:                  { x:  245, y: 1042, w:  230, h:  220 },
  BILLBOARD07:                  { x:  485, y: 1042, w:  230, h:  220 },
  BILLBOARD08:                  { x:  725, y: 1042, w:  230, h:  220 },
  BILLBOARD09:                  { x:  965, y: 1042, w:  230, h:  220 },
  STUMP:                        { x:  845, y:  555, w:  195, h:  140 },
  SEMI:                         { x:  600, y:  347, w:  122, h:  144 },
  TRUCK:                        { x: 1075, y:  925, w:  100, h:   78 },
  CAR03:                        { x: 1185, y:  925, w:   88, h:   55 },
  CAR02:                        { x:  475, y:  375, w:   80, h:   59 },
  CAR04:                        { x:  845, y:  705, w:   80, h:   57 },
  CAR01:                        { x:  935, y:  705, w:   80, h:   56 },
  PLAYER_UPHILL_STRAIGHT_BRAKE: { x:  475, y:  444, w:   80, h:   45 },
  PLAYER_UPHILL_LEFT:           { x:  247, y:  880, w:   80, h:   45 },
  PLAYER_UPHILL_LEFT_BRAKE:     { x:  337, y:  880, w:   80, h:   45 },
  PLAYER_UPHILL_RIGHT:          { x:  427, y:  880, w:   80, h:   45 },
  PLAYER_UPHILL_RIGHT_BRAKE:    { x:  517, y:  880, w:   80, h:   45 },
  PLAYER_UPHILL_STRAIGHT:       { x:  607, y:  880, w:   80, h:   45 },
  PLAYER_LEFT:                  { x:  600, y:  501, w:   80, h:   41 },
  PLAYER_STRAIGHT_BRAKE:        { x:  230, y:  503, w:   80, h:   41 },
  PLAYER_RIGHT_BRAKE:           { x:  320, y:  503, w:   80, h:   41 },
  PLAYER_RIGHT:                 { x:  410, y:  503, w:   80, h:   41 },
  PLAYER_LEFT_BRAKE:            { x:  500, y:  503, w:   80, h:   41 },
  PLAYER_STRAIGHT:              { x:  697, y:  880, w:   80, h:   41 }
};

SPRITES.SCALE = 0.3 * (1/SPRITES.PLAYER_STRAIGHT.w) // the reference sprite width should be 1/3rd the (half-)roadWidth

SPRITES.BILLBOARDS = [SPRITES.BILLBOARD01, SPRITES.BILLBOARD02, SPRITES.BILLBOARD03, SPRITES.BILLBOARD04, SPRITES.BILLBOARD05, SPRITES.BILLBOARD06, SPRITES.BILLBOARD07, SPRITES.BILLBOARD08, SPRITES.BILLBOARD09];
SPRITES.PLANTS     = [SPRITES.TREE1, SPRITES.TREE2, SPRITES.DEAD_TREE1, SPRITES.DEAD_TREE2, SPRITES.PALM_TREE, SPRITES.BUSH1, SPRITES.BUSH2, SPRITES.CACTUS, SPRITES.STUMP, SPRITES.BOULDER1, SPRITES.BOULDER2, SPRITES.BOULDER3];
SPRITES.CARS       = [SPRITES.CAR01, SPRITES.CAR02, SPRITES.CAR03, SPRITES.CAR04, SPRITES.SEMI, SPRITES.TRUCK];

