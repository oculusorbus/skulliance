<?php
/**
 * lib/founding-artists.php -- the six who started it, in ONE place.
 *
 * Returns array of array(image filename, name, X profile), in the order
 * the homepage has always shown them. Images live on the server under
 * /staking/images/projects/ and are deployed by FTP, never committed.
 *
 * Extracted from homepage.php for the reason lib/partner-flyers.php was:
 * partner.php needs the same six, and two copies of a list means one of
 * them is wrong the first time anything changes. This list should not
 * change -- six artists founded Skulliance and that is history now -- but
 * a handle or a flyer can, and then it changes in one file.
 */
return array(
	array('sinderskullz.png', 'Sinder Skullz',   'https://x.com/SinderSkullz'),
	array('kimosabe.png',     'Kimosabe Art',    'https://x.com/Nft4R'),
	array('crypties.png',     'Crypties',        'https://x.com/cryptiesnft'),
	array('galactico.png',    'Galactico',       'https://x.com/GalacticoNFT'),
	array('ohhmeed.png',      'Ohh Meed',        'https://x.com/ohh_meed'),
	array('hype.png',         'H.Y.P.E.',        'https://x.com/haveyouseenhype'),
);
