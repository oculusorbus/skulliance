import json, re, collections
counts=json.load(open('dhc/onchain-counts.json'))
idx=json.load(open('dhc/web/trait-index.json'))
# ---------------------------------------------------------------------------
# MERGE: two on-chain NAMES that are one trait, so their counts must be ADDED.
#
# Different from ALIAS below. ALIAS points one art file at one metadata name;
# it cannot combine two. These are spelling variants inside the mint metadata
# itself, so without merging, the file matches whichever spelling it happens to
# resemble and the other spelling's fighters vanish from the count -- which
# makes a trait look far rarer than it is. XLON Digi Fire Attack was reading as
# LEGENDARY on a count of 1 while 7 more fighters wore it under 'XLONs'.
#
# CONFIRMED DISTINCT BY MAXINGO (2026-09-14) -- DO NOT ADD THESE:
#   ZX50 * vs ZX350 *          different torsos; ZX50 has no art (1/1 piece)
#   XLONs Complex vs ... Trap  different backgrounds; Trap has no art
#   DHC2 Comic Cover #3 vs #4  different effects; #4 has no art
#   XLONs 2YK Attack vs #2     different backgrounds; the bare name has no art
#   MK100 Blaster Arms vs Damaged  different arms; both have art, see ALIAS
# They look like typos and are not. Merging them would fold a 1/1 piece's
# rarity into a common trait and overstate what stakers can actually collect.
MERGE = {
 'torso':   {'ZX350 Sentinel': 'ZX350 Centinel'},
 'effects': {'XLONs Digi Fire Attack': 'XLON Digi Fire Attack'},
}
ALIAS = {
 'background': {'XLON Ddos Attack':'XLONs DDoS Attack', 'XLONs s Complex':'XLONs Complex'},
 'headgear':   {'Golden Cyber Plague Detecting Mask':'Golden Cyborg Plague Detection Mask',
                # Same correction as Code Sea Predator: the chain's single
                # 'DH Sorceror' is the DARK one. The blue piece we had under
                # that slug is not in the collection and is now
                # blue-dh-sorceror, scoring 0 -> mythic.
                'Dark DH Sorceror':'DH Sorceror',
                'Skull Collector Helmet':'Skull Collector',
                'MK100 Codesea Explorer Helmet':'MK100 Codesea Explorer',
                'MK200 Cyber Plague VR Mask':'MK200 Cyber Plague VR Headset'},
 'head':       {'Happy Cyborg Decoy':'Happy Robot Decoy', 'V12 Punk Raider':'V12 Raider'},
 'companion':  {'Arachnid Laser Cannon':'Aracnyd Laser Cannon',
                # The collection's own Code Sea Predator is the GREEN one; the
                # piece we had under that name is not in the collection at all
                # and is now steel-code-sea-predator, scoring 0 -> mythic.
                'Green Code Sea Predator':'Code Sea Predator',
                'U. Vigilance Device #1':'Unknown Vigilance Device #1',
                'U. Vigilance Device #2':'Unknown Vigilance Device #2'},
 'weapon':     {'Scythe Sash':'Scythe', 'Skull Krusher Sash':'Skull Krusher',
                # the strap halves, same as the two sashes above
                'Bio Weapon Strap':'Bio Weapon',
                'Bone Blaster 3X Strap':'Bone Blaster 3X'},
 'arms':       {'Deteriorated MK100 Blaster Arms':'Damaged MK100 Blaster Arms',
                # renamed on the platform; the chain still says "Replacement"
                'Perforator Arm Accent':'Perforator Arm Replacement',
                'symbio-flexx':'Symbio Flexx Arms'},
}
# ---------------------------------------------------------------------------
# THE METADATA SAYS A TRAIT IS THERE AND THE PICTURE DOES NOT.
#
# Rarity is meant to describe what a collector can actually find in the
# collection, and for one weapon those two disagree: Bone Blaster 3X is listed
# on 3 minted Fighters but only 2 of them render it -- the third carries the
# name with no weapon in the art. Counted as 3 it is epic; counted as what is
# visible it is legendary, which is the honest answer and the owner's call.
#
# Keyed by the ON-CHAIN NAME rather than by slug, so every piece of art that
# resolves to that name inherits the correction -- which is what makes the
# strap come out at the same tier as the weapon it hangs from, automatically.
#
# Add a row here only for a discrepancy that has been eyeballed on the actual
# NFT. It is a correction to a reading of the chain, not a balance lever.
COUNT_FIX = {
 'weapon': {'Bone Blaster 3X': 2},
}
# ---------------------------------------------------------------------------
# ART WORN THROUGH SOMETHING ELSE, so it has no on-chain name to count.
#
# The skull-and-chains is the front half of two DIFFERENT back weapons -- it
# hangs from the Axe (5 fighters) and from the real Electric Morning Star mace
# (3). Weapon is one slot on chain, so those two sets are disjoint and 8
# Fighters wear this art. Scoring it 0 would make a piece on 8 of 226 the
# rarest class of drop; inheriting one parent's tier the way a sash does would
# ignore the other parent entirely.
#
# Different from COUNT_FIX above: that corrects a name the chain HAS, this
# supplies a count for art the chain never names. Keyed by (slot, slug), and
# every row needs its arithmetic written down.
DERIVED_COUNT = {
 ('weapon', 'skull-and-chains'): 8,   # axe 5 + electric morning star 3, disjoint
}
def norm(s):
    s=s.lower().replace('&','and'); s=re.sub(r'[^a-z0-9]','',s); return re.sub(r'0+(\d)',r'\1',s)
def variants(n): return [n, re.sub(r'1$','',n), n+'1']
# Plain flat-colour backgrounds. Absent from the collection not because they are
# scarce but because they are the plain option -- Maxingo hand-assembled the
# minted Fighters and picked interesting scenes every time. The absence test
# would read that as mythic and make a black rectangle the rarest drop in the
# game, so they are pinned common instead.
SOLID_BACKGROUNDS = {
 'black','white','red','bright-red',
 'light-blue','light-green','light-purple','light-yellow',
}
# NEW ART HAS NO MINT PRECEDENT, so a count of 0 is a fact about the collection
# and not a judgement about the trait. tier() reads 0 as mythic, which is right
# for a piece deliberately held back and wrong for one simply drawn later --
# every trait Maxingo delivers from now on would be the rarest thing in the
# game by default. These are pinned by hand instead, the same way
# SOLID_BACKGROUNDS pins the plain backgrounds that are absent for an equally
# uninteresting reason.
#
# Tank Helmet and Manta Ray stay mythic on purpose: both are huge and oddly
# shaped, and handing them out freely would skew what an assembled Fighter
# looks like. The Samurai set is legendary because players will want to
# collect all three, which is a reason to make it gettable.
PINNED = {
 ('headgear','dh-insekto-helmet'):        'legendary',
 ('headgear','dh-samurai-helmet'):        'legendary',
 ('torso','dh-samurai-suit'):             'legendary',
 ('arms','dh-samurai-augmented-arms'):    'legendary',
 # 2026-10-30 delivery. Legendary at the owner's call: four pieces players will
 # want to chase, which is a reason to make them gettable rather than mythic.
 ('effects','dhc2-comic-cover-5'):        'legendary',
 ('torso','failed-immortality-body'):     'legendary',
 ('head','failed-immortality-head'):      'legendary',
 ('headgear','neural-crown'):             'legendary',
 # tank-helmet, dh-manta-ray-helmet and skulliance-helmet are left to the
 # count-0 default: mythic. The first two because they are huge and oddly
 # shaped and would skew what a Fighter looks like; the Skulliance Helmet
 # because it is the house's own badge.
}

def tier(c):
    if c == 0:  return 'mythic'
    if c <= 2:  return 'legendary'
    if c <= 5:  return 'epic'
    if c <= 9:  return 'uncommon'
    return 'common'
TIERS=['common','uncommon','epic','legendary','mythic']
# PER-TRAIT WEIGHTS, NOT TIER SHARES.
#
# These were a tier's SHARE of a category, split evenly among its members. That
# is only the same thing when every tier holds a similar number of traits, and
# it breaks badly when one does not: a tier with a single member handed that
# member the whole share. The DH Samurai Suit was the ONLY legendary torso, so
# it took all 9% and dropped more often than any individual common torso (5.50%)
# -- a legendary that is commoner than a common. Headgear had the mirror of it:
# one common taking all 44% on its own. Eight of 65 tier comparisons across the
# collection came out inverted, worst of them an uncommon weapon at 28.57%
# against a common at 14.97%.
#
# Read as per-trait weights the numbers are right, and the property that
# actually matters holds BY CONSTRUCTION rather than by luck of the counts:
# 44 > 28 > 17 > 9 > 2, so a rarer trait is always rarer than a commoner one in
# the same category, whatever the membership. A tier's total is then whatever
# its members add up to, which is the honest answer -- one common headgear
# SHOULD mean commons are seldom what you get there.
WEIGHT={'common':44.0,'uncommon':28.0,'epic':17.0,'legendary':9.0,'mythic':2.0}

out={}; tally=collections.Counter()
import glob, os
ART='dhc/web/1000'
cats={}
for d in sorted(os.listdir(ART)):
    # Match on substring, not an exact name. The armless-torso directory is
    # 'torso armless' on disk, not 'torso-noarms', so an equality test let all
    # 25 of them through as a phantom category of mythic traits -- they are the
    # SAME torsos, drawn without arms, and must never be rarity-scored twice.
    # 'companion-front' is the SAME companion drawn as its front half -- the
    # Demon's claws. Same trap as the armless torsos directly above: a
    # directory that is not a selectable slot must never be rarity-scored,
    # or one trait is counted as two and the real one's rate is halved.
    # 'neckfill' is the third of these: one shared black block drawn under the
    # torso to close the neckline, keyed to nothing and selectable nowhere. It
    # slipped in as a one-trait category of its own and was scored MYTHIC at
    # 0.24%, which is a drop nobody can ever receive and a slot nobody can
    # fill. Same family as the two below -- a directory that is not a slot must
    # never be rarity-scored.
    if 'armless' in d or 'noarms' in d or d.endswith('-front') or d == 'neckfill' \
       or not os.path.isdir(os.path.join(ART,d)): continue
    cats[d]={os.path.basename(f)[:-4]: None for f in sorted(glob.glob(os.path.join(ART,d,'*.png')))}
for cat, traits in cats.items():
    # SUM on collision, never overwrite. The mint metadata contains case typos
    # -- 'Xbot' and 'XBot', 'DH Cop #3' and 'Dh Cop #3' -- which normalise to the
    # same trait. Building this dict by assignment kept whichever came last and
    # silently threw the other count away, which pushed traits worn by a dozen
    # fighters down into legendary.
    on=collections.Counter()
    mg={norm(k):norm(v) for k,v in MERGE.get(cat,{}).items()}
    fx={norm(k):v for k,v in COUNT_FIX.get(cat,{}).items()}
    for k,v in counts.get(cat,{}).items():
        n=norm(k)
        # before MERGE, so a corrected name still folds into its merge target
        if n in fx: v=fx[n]
        on[mg.get(n,n)]+=v
    al={norm(k):norm(v) for k,v in ALIAS.get(cat,{}).items()}
    raw={}
    for slug in traits:
        nm=(idx.get(cat,{}).get(slug) or {}).get('name') or slug.replace('-',' ').title()
        n=norm(nm); n=al.get(n,n); c=None
        for v in variants(n):
            if v in on: c=on[v]; break
        if (cat,slug) in DERIVED_COUNT: c=DERIVED_COUNT[(cat,slug)]
        raw[slug]=c or 0
    bytier=collections.defaultdict(list)
    for slug,c in raw.items():
        t = PINNED.get((cat,slug)) \
            or ('common' if (cat=='background' and slug in SOLID_BACKGROUNDS) else tier(c))
        bytier[t].append(slug)
    # Every trait carries its tier's weight; the category normalises to 100%.
    # No special case for an absent tier -- it simply contributes no weight,
    # where the old model had to redistribute its share.
    total=sum(WEIGHT[t]*len(v) for t,v in bytier.items() if v)
    for t in TIERS:
        if not bytier[t]: continue
        per=WEIGHT[t]/total*100.0
        for slug in bytier[t]:
            out.setdefault(cat,{})[slug]={'tier':t,'count':raw[slug],'rate':round(per,2)}
            tally[t]+=1
json.dump(out, open('dhc/rarity.json','w'), indent=1)

# Write dhcrarity.php itself. Keeping the header and regenerating only the table
# means the file is never hand-edited and never drifts from the chain data.
php=open('dhcrarity.php').read()
head=php.split('return array(')[0]
body=['return array(']
for cat in sorted(out):
    body.append("\t'%s' => array(" % cat)
    for slug in sorted(out[cat]):
        v=out[cat][slug]
        body.append("\t\t'%s' => array('%s', %d, %s)," % (slug, v['tier'], v['count'], v['rate']))
    body.append('\t),')
body.append(');')
open('dhcrarity.php','w').write(head + '\n'.join(body) + '\n')
print('wrote dhcrarity.php')
print('traits by tier:', {t:tally[t] for t in TIERS})
print('\nper-category rate sums (must be ~100) and mythic count:')
for cat,v in sorted(out.items()):
    s=sum(x['rate'] for x in v.values()); m=sum(1 for x in v.values() if x['tier']=='mythic')
    print(f'  {cat:11s} {len(v):3d} traits  sum={s:6.1f}%  mythic={m}')
r=out['head']
ex=sorted(r.items(), key=lambda kv: -kv[1]['count'])[:3]+sorted(r.items(), key=lambda kv: kv[1]['count'])[:2]
print('\nhead examples:')
for s,x in ex: print(f"  {s:28s} {x['tier']:10s} {x['count']:2d}/226  {x['rate']}%")
