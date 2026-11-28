<?php
/*
 * sk-audio.php -- the audio control strip, and the one thing about volume
 * that is not obvious.
 *
 * IOS IGNORES HTMLMediaElement.volume. Not "rounds it", not "warns" --
 * WKWebView plays at 1.0 whatever you set, with no error and no event, by
 * Apple's deliberate design, and that includes this PWA on a home screen.
 * So a volume slider wired the normal way does nothing at all on an
 * iPhone, which is where these games are mostly played. Routing the
 * element through a Web Audio GainNode works, because iOS does respect
 * gain -- the same workaround Howler.js and friends use.
 *
 * cryptcrawl.php found this the hard way and carries its own copy, tangled
 * up with its SFX_LEVEL scaling and crossfade ramp. This is that logic on
 * its own so the next game does not have to rediscover it. Crypt Crawl is
 * deliberately left alone: it works, and rewriting a game's audio to share
 * a helper is a bad trade.
 *
 * window.SkAudio:
 *   set(el, vol)  0-1, through the gain node where possible
 *   get(el)       what set() last applied
 *   unlock()      resume the context; cheap, call it on any gesture
 *
 * Include once per page, before anything that calls it.
 */
if (defined('SK_AUDIO_DONE')) return;
define('SK_AUDIO_DONE', 1);
?>
<style>
/* The strip itself. Games colour it with --ska-accent. */
.ska-player { display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
              padding: 7px 10px; background: rgba(0,0,0,.28);
              border: 1px solid rgba(255,255,255,.1); }
.ska-btn    { background: none; border: 0; color: inherit; cursor: pointer;
              font-size: 1rem; line-height: 1; padding: 4px 6px; }
.ska-btn:hover { background: rgba(255,255,255,.1); }
.ska-btn.off   { opacity: .35; }
.ska-name   { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis;
              white-space: nowrap; font-size: .7rem; letter-spacing: .08em;
              text-transform: uppercase; color: rgba(255,255,255,.45); }
.ska-vol    { flex: none; width: 76px; cursor: pointer;
              accent-color: var(--ska-accent, #00c8a0); }
/* The slider is the first thing to go when the row will not fit -- the
   hardware buttons still work, where a wrapped row shifts the layout. */
@media (max-width: 380px) { .ska-vol { width: 56px; } }
</style>
<script>
window.SkAudio = (function () {
	var ctx = null, gains = (typeof WeakMap !== 'undefined') ? new WeakMap() : null;
	function context() {
		if (ctx) return ctx;
		var AC = window.AudioContext || window.webkitAudioContext;
		if (!AC) return null;
		try { ctx = new AC(); } catch (e) { ctx = null; }
		return ctx;
	}
	/* Suspended until a trusted gesture resumes it -- the same restriction
	   autoplay has. A no-op once running, so call it freely. */
	function unlock() {
		var c = context();
		if (c && c.state === 'suspended') c.resume().catch(function () {});
	}
	function gainFor(el) {
		if (!gains) return null;
		if (gains.has(el)) return gains.get(el);
		var c = context();
		if (!c) return null;
		try {
			var src = c.createMediaElementSource(el), g = c.createGain();
			src.connect(g); g.connect(c.destination);
			gains.set(el, g);
			return g;
		} catch (e) {
			/* Same-origin files, so this should not throw -- but a failure
			   here must fall back, not kill the sound. */
			return null;
		}
	}
	function set(el, vol) {
		if (!el) return;
		vol = Math.max(0, Math.min(1, Number(vol) || 0));
		var g = gainFor(el);
		/* Once wrapped, the element's own volume is pinned at 1 forever and
		   the gain node is the only source of truth for it. Swapping .src
		   or calling .load() does not disturb the graph. */
		if (g) { g.gain.value = vol; el.volume = 1; }
		else   { el.volume = vol; }
	}
	function get(el) {
		if (!el) return 0;
		var g = gains && gains.get(el);
		return g ? g.gain.value : el.volume;
	}
	return { set: set, get: get, unlock: unlock };
})();
</script>
