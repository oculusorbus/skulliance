<?php
/**
 * lib/partner-flyers.php -- the partner roster, in ONE place.
 *
 * Returns array of array(image filename, project name, X profile). Images
 * live on the server under /staking/images/projects/ and are deployed by
 * FTP, never committed, so this is a list of filenames and nothing more.
 *
 * IT WAS A LITERAL INSIDE homepage.php, and partner.php wanted the same
 * roster. Two copies of a list that grows every time an artist joins is a
 * list that is wrong on one page within a month, and the page it is wrong
 * on is whichever one nobody opened recently. The flyer set is a SUBSET of
 * the projects that stake -- not every partner has artwork on file -- so
 * never count this to state how many projects are on the platform; count
 * DISTINCT project_id in collections, which is what hp_stat_artists() does.
 */
return array(
	array('nemonium.jpg',      'Omen',                    'https://x.com/Omen4Omen'),
	array('discosolaris.png',  'Disco Solaris',           'https://x.com/discosolaris'),
	array('danketsu.png',      'Danketsu',                'https://x.com/DanketsuNFT'),
	array('squashua.jpg',      'Squashua',                'https://x.com/Joshua_Squashua'),
	array('netanelcohen.png',  'Netanel Cohen',           'https://x.com/netanelchn'),
	array('maxingo.png',       'Maxingo',                 'https://x.com/madmaxi__'),
	array('pendulum.jpg',      'Pendulum',                'https://x.com/Pendulum_NFT'),
	array('aeoniumsky.jpg',    'Aeoniumsky',              'https://x.com/aeoniumsky'),
	array('havocworlds.jpg',   'Havoc Worlds',            'https://x.com/havocworlds'),
	array('goattribe.jpg',     'Goat Tribe',              'https://x.com/adaGOATS'),
	array('threefoldbold.png', 'Threefold Bold',          'https://x.com/Threefoldbold'),
	array('bungking.jpg',      'Bungking',                'https://x.com/Fiqhi_Alfani'),
	array('darkula.jpg',       'Darkula',                 'https://x.com/darkula__'),
	array('heistonalpha.jpg',  'Heist on Alpha',          'https://x.com/heistonalpha'),
	array('apprentices.png',   'Apprentices',             'https://x.com/ApprenticesCNFT'),
	array('deadpophell.png',   'Dead Pop Hell',           'https://x.com/deadpophell'),
	array('fart.jpg',          'f.ART',                   'https://x.com/cnftfart'),
	array('muses.jpg',         'Muses of the Multiverse', 'https://x.com/joshuahoward'),
	array('cardanocamera.jpg', 'Cardano Camera',          'https://x.com/cardanocamera'),
	array('oldmoney.jpg',      'Old Money',               'https://x.com/OldMoneyNFT'),
	array('jordi.png',         'Jordi',                   'https://x.com/JordiLeitao'),
	array('ascenderone.jpg',   'Ascender One',            'https://x.com/AscenderOne'),
	array('ritual.png',        'Ritual',                  'https://x.com/thecgritual'),
	array('mipatoys.jpg',      'Mipa Toys',               'https://x.com/MipaToys'),
	array('stagwolf.jpg',      'Stagwolf',                'https://x.com/stagwolf'),
	array('grey.png',          'Grey',                    'https://x.com/diexgrey'),
	array('skowl.jpg',         'Skowl',                   'https://x.com/skowllwoks'),
);
