<?php
/**
 * admin-copy.php — click a name, it is on the clipboard.
 *
 * WHY THIS EXISTS. Every outreach panel is a list of people you are
 * about to go and find in Discord, and the first move for each one is
 * getting their username into Discord's search box. Selecting a name by
 * dragging over it is fiddly on a desktop and close to impossible on a
 * phone, where a long-press lands on the row rather than the text -- so
 * the panels each had a Copy name button buried in a dialog you had to
 * open first, which is two clicks to avoid one drag.
 *
 * HOW TO USE IT. Include this once per page, then give any element
 *
 *     data-copy="the text"
 *
 * and clicking it copies that text. Add class="adm-copy" for the hover
 * affordance. Nothing else is needed; the handler is delegated, so it
 * works on rows added after load.
 *
 * ONE IMPLEMENTATION, NOT ONE PER PANEL. admin-lapsed.php and
 * admin-welcome.php had their own copies of the clipboard dance already
 * -- and that dance has a real trap in it (see below) which is not worth
 * getting right twice.
 */
?>
<style>
/* The affordance is deliberately quiet: this sits on every row of a
   three-hundred-row table, so it may not read as a button. A dotted
   underline on hover and a cursor is enough to be discoverable without
   turning the list into a wall of controls. */
.adm-copy { cursor:pointer; border-bottom:1px dotted transparent; }
.adm-copy:hover { border-bottom-color:#5f7d90; }
.adm-copy:focus-visible { outline:2px solid #00c8a0; outline-offset:2px; border-radius:3px; }
/* Confirmation rides on the element itself rather than a toast: a toast
   in the corner of a long table is nowhere near where you are looking. */
.adm-copy.adm-copied { border-bottom-color:#00c8a0; }
.adm-copied-tag { color:#00c8a0; font-size:.68rem; letter-spacing:.06em;
  text-transform:uppercase; margin-left:7px; }
</style>
<script>
(function () {
	/*
	 * execCommand is deprecated and is still the fallback, because
	 * navigator.clipboard needs a secure context and can be refused by
	 * permission -- and an admin panel opened over plain http on the
	 * local network is exactly where that bites.
	 */
	function copy(text, done) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done, function () { legacy(text, done); });
		} else {
			legacy(text, done);
		}
	}
	function legacy(text, done) {
		/*
		 * A TEXTAREA, OFF SCREEN, NOT display:none -- select() does
		 * nothing on a hidden element, which is the trap each panel had
		 * to learn separately. readOnly stops iOS raising the keyboard,
		 * and the explicit range is what iOS needs instead of select().
		 */
		var t = document.createElement('textarea');
		t.value = text;
		t.readOnly = true;
		t.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0';
		document.body.appendChild(t);
		t.select();
		t.setSelectionRange(0, t.value.length);
		try { document.execCommand('copy'); done(); } catch (e) {}
		document.body.removeChild(t);
	}

	document.addEventListener('click', function (e) {
		var el = e.target.closest ? e.target.closest('[data-copy]') : null;
		if (!el) return;
		/* A name inside a link or a button must not swallow that control. */
		if (e.target.closest('a,button') && !e.target.closest('[data-copy]').isSameNode(el)) return;
		e.preventDefault();
		var text = el.getAttribute('data-copy') || el.textContent.trim();
		if (!text) return;
		copy(text, function () {
			el.classList.add('adm-copied');
			var tag = el.querySelector('.adm-copied-tag');
			if (!tag) {
				tag = document.createElement('span');
				tag.className = 'adm-copied-tag';
				tag.textContent = 'copied';
				el.appendChild(tag);
			}
			clearTimeout(el._admT);
			el._admT = setTimeout(function () {
				el.classList.remove('adm-copied');
				if (tag && tag.parentNode) tag.parentNode.removeChild(tag);
			}, 1400);
		});
	});

	/* Reachable without a mouse: the element is focusable and Enter or
	   Space does what a click does. */
	document.addEventListener('keydown', function (e) {
		if (e.key !== 'Enter' && e.key !== ' ') return;
		var el = document.activeElement;
		if (!el || !el.hasAttribute || !el.hasAttribute('data-copy')) return;
		e.preventDefault();
		el.click();
	});
})();
</script>
