/**
 * musicvideos-fit-harness.js - the player's size, driven.
 *
 *   node musicvideos-fit-harness.js
 *   (also run by musicvideos-harness.php, reported as one assertion)
 *
 * THE RULE IS THAT A FILM IS NEVER BLOWN UP. A 600px video stretched
 * across a 760px box is a 600px video with soft edges -- upscaling adds
 * nothing and makes the compression visible -- so the player is set to
 * the file's own width and scaled DOWN only when the window cannot hold
 * it. Math.min(1, ...) is the whole rule, and it is one character away
 * from being wrong in a way nobody would notice on a big monitor.
 *
 * Driven against a stubbed DOM because the Chrome available here will
 * not decode video at all, so videoWidth is never anything but 0 in a
 * real page I can open.
 */
const fs = require("fs"), vm = require("vm"), path = require("path");
const page = fs.readFileSync(path.join(__dirname, 'musicvideos.php'), 'utf8');
const src = page.match(/<script>([\s\S]*?)<\/script>/)[1];

function run(videoW, videoH, veilW, veilH, headH) {
  let boxWidth = null, ratio = null, handlers = {};
  const el = (extra) => Object.assign({
    style: {}, hidden: false, classList: { add(){}, remove(){}, contains:()=>false },
    addEventListener(t, fn) { (handlers[t] = handlers[t] || []).push(fn); },
    getAttribute: () => '', removeAttribute(){}, setAttribute(){},
    focus(){}, click(){},
  }, extra || {});
  const veil = el({ hidden: false, clientWidth: veilW, clientHeight: veilH });
  const vid  = el({ videoWidth: videoW, videoHeight: videoH,
                    play: () => Promise.resolve(), pause(){}, load(){} });
  const box  = el({});
  const head = el({ offsetHeight: headH });
  const tiles = [el({})]; tiles.length = 1;
  const byId = { 'mvp-veil': veil, 'mvp-video': vid, 'mvp-box': box,
                 'mvp-title': el({ textContent: '' }), 'mvp-close': el({}), 'mvp-head': head };
  const sandbox = {
    document: { getElementById: (i) => byId[i] || null,
                querySelectorAll: () => tiles, addEventListener(){} },
    window: { addEventListener(){} },
    isFinite, Math,
  };
  sandbox.window.document = sandbox.document;
  vm.createContext(sandbox);
  vm.runInContext(src, sandbox);
  /* fire loadedmetadata the way the browser would */
  (handlers['loadedmetadata'] || []).forEach(f => f());
  return { width: box.style.width, aspect: vid.style.aspectRatio };
}

let fail = 0;
const ok = (c, w) => { if (!c) { fail++; console.log('  FAIL  ' + w); } };

console.log('a film is never shown bigger than it is');
for (const [vw, vh, label] of [[600,600,'600x600'], [720,720,'720x720'], [1080,1080,'1080x1080']]) {
  const r = run(vw, vh, 1800, 1200, 34);   // a roomy window
  const got = parseInt(r.width, 10);
  ok(got <= vw, label + ' was blown up to ' + r.width + ' in a big window');
  ok(got === vw || got === Math.min(vw, 1800-32), label + ' came out at ' + r.width);
  console.log('  ' + label.padEnd(12) + ' in 1800x1200 -> ' + r.width + '  aspect ' + r.aspect);
}

console.log('\nand it is scaled down when it will not fit');
{
  const r = run(1080, 1080, 700, 1200, 34);
  console.log('  1080x1080    in  700x1200 -> ' + r.width);
  ok(parseInt(r.width,10) <= 700 - 32, 'wider than the window: ' + r.width);
  const r2 = run(1080, 1080, 1800, 500, 34);
  console.log('  1080x1080    in 1800x500  -> ' + r2.width);
  ok(parseInt(r2.width,10) <= 500 - 32 - 34 - 10, 'taller than the window: ' + r2.width);
}

console.log('\nthe shape comes from the file, not from a guess');
{
  const r = run(1920, 1080, 3000, 3000, 34);
  ok(r.aspect === '1920 / 1080', 'a 16:9 film was given aspect ' + r.aspect);
  console.log('  1920x1080 -> aspect ' + r.aspect + ', width ' + r.width);
}

console.log('\nno metadata, no resizing');
{
  const r = run(0, 0, 1800, 1200, 34);
  ok(r.width === '' || r.width == null, 'sized to ' + r.width + ' with no dimensions known');
  console.log('  0x0 -> width "' + r.width + '" (css default stands)');
}
console.log(fail ? '\n' + fail + ' FAILED' : '\nall good');
process.exit(fail ? 1 : 0);
