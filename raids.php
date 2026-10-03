<?php
/*
 * raids.php -- redirects to the Realms page's Raids section.
 *
 * WHAT THIS USED TO BE. A standalone full-history view: the same getRaids()
 * renderer the Realms page uses, with the LIMIT 10 lifted. It predates the
 * Realms redesign, and the redesign put the raid lists inside realms.php
 * behind its own nav with .rr-* styling that lives in that page's <style>.
 * This page never got any of it, so it rendered as an unstyled wall of
 * markup -- white cards, overlapping text, no layout. Reported from the
 * installed PWA, reached from DHC Fighters' trait-drop list, which still
 * pointed here.
 *
 * It had no link anywhere else: the one that existed ("view history", under
 * every completed list) was removed earlier for being built for a single
 * player who is no longer active. So this was a page nothing pointed at
 * except one stale config entry, in a state nobody would want to land in.
 *
 * WHAT IS LOST: rows 11 and beyond of each completed list. The Realms page
 * loads ten and has a "Show all" control; the full history beyond that is
 * not reachable from the interface any more. Restoring it properly means
 * giving the Raids section a paging control, not reviving a page with no
 * stylesheet -- a separate piece of work rather than something to leave a
 * broken page standing for.
 *
 * 302, not 301: a permanent redirect is cached by the browser forever and
 * would be awkward to undo if that paging work brings this page back.
 *
 * NOTHING MAY PRINT FIRST -- no db.php, no header.php. A redirect after
 * output is not a redirect, and db.php runs with display_errors on.
 */
header('Location: realms.php#raids', true, 302);
exit;
