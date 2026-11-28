<?php
/*
 * Monstrocity themes: turn the hardcoded policy strings into collection links.
 *
 * WHY THIS EXISTS. A theme matches NFTs one of two ways: the legacy
 * `monstrocity_themes.policy_ids` CSV, or rows in
 * `monstrocity_theme_collections`. Most themes are still on the legacy
 * string -- and Gauntlets asks the LINK TABLE for a backdrop, because a
 * Monstrocity background belongs to a collection, not to a project. So a
 * themed collection with no rows in that table shows the Realms tile
 * instead of its own art. This is the one-time conversion.
 *
 * IT IS NOT "LINK EVERYTHING THAT MATCHES". Attaching collections replaces
 * the policy string WHOLESALE, so a theme whose five policies map to three
 * collections would quietly stop matching the other two. And `orientations`
 * is POSITIONAL against the policy list, so a shorter derived list
 * reassigns which way each collection faces. Only themes whose policies map
 * ONE FOR ONE are converted; everything else is listed with the reason and
 * left untouched.
 *
 * sort_order is FIND_IN_SET(...) - 1, the policy's own position in the CSV,
 * so the orientation list still lines up afterwards.
 *
 * Idempotent: a theme that already has links is skipped, and the insert is
 * INSERT IGNORE against the (theme_id, collection_id) primary key.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();
require_once __DIR__ . '/monstrocity-themes-lib.php';

$msgs = array('err' => array(), 'ok' => array(), 'warn' => array());

/* The conversion, as one statement. Everything it refuses to touch, it
   refuses in SQL -- the page and the script cannot disagree. */
function mtl_sql() {
	/*
	 * REPLACE(..., ' ', '') ON EVERY SIDE. FIND_IN_SET does not trim, and
	 * the stored CSVs have spaces after the commas -- ajax/get-nft-assets.php
	 * has always run array_map('trim', explode(',', ...)) over them, which is
	 * why nobody noticed. Without this, a theme written "p1, p2" matches
	 * only its FIRST policy and the conversion reports nothing to do.
	 * Stripping spaces does not move the commas, so FIND_IN_SET's index --
	 * and therefore sort_order and the positional orientations -- is intact.
	 */
	$csv = "REPLACE(t.policy_ids, ' ', '')";
	return "INSERT IGNORE INTO monstrocity_theme_collections (theme_id, collection_id, sort_order)\n"
	     . "SELECT t.id, c.id, FIND_IN_SET(TRIM(c.policy), $csv) - 1\n"
	     . "  FROM monstrocity_themes t\n"
	     . "  JOIN collections c ON FIND_IN_SET(TRIM(c.policy), $csv) > 0\n"
	     . " WHERE TRIM(c.policy) <> ''\n"
	     . "   AND NOT EXISTS (SELECT 1 FROM monstrocity_theme_collections x\n"
	     . "                    WHERE x.theme_id = t.id)\n"
	     . "   AND (SELECT COUNT(*) FROM collections c2\n"
	     . "         WHERE FIND_IN_SET(TRIM(c2.policy), $csv) > 0 AND TRIM(c2.policy) <> '')\n"
	     . "       = (LENGTH($csv) - LENGTH(REPLACE($csv, ',', '')) + 1);";
}

$have_table = false;
$tr = @$conn->query("SHOW TABLES LIKE 'monstrocity_theme_collections'");
if ($tr && $tr->num_rows) $have_table = true;

if (!$have_table) {
	$msgs['err'][] = 'monstrocity_theme_collections does not exist. Create it first '
	               . '(the CREATE is in monstrocity-schema.md); until it does, every '
	               . 'query against it returns false without throwing.';
}

if ($have_table && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply') {
	if (@$conn->query(mtl_sql())) {
		$n = $conn->affected_rows;
		$msgs['ok'][] = "Linked $n collection" . ($n === 1 ? '' : 's') . '.'
		              . ($n === 0 ? ' Nothing was ready to convert.' : '');
	} else {
		$msgs['err'][] = 'The conversion failed: ' . htmlspecialchars($conn->error);
	}
}

/*
 * THE REPORT, POLICY BY POLICY. A verdict on its own is not an answer:
 * "nothing ready to link" reads the same whether the collections are
 * missing, the policies are misspelled, or the matching is broken. So
 * every policy is resolved here in PHP -- trimmed, the way
 * ajax/get-nft-assets.php has always read these strings -- and the ones
 * with no collection are named.
 */
$bypolicy = array();
$cq = @$conn->query("SELECT id, name, policy FROM collections WHERE TRIM(policy) <> ''");
if ($cq) while ($c = $cq->fetch_assoc()) {
	$bypolicy[strtolower(trim($c['policy']))] = $c;
}

$rows = array();
if ($have_table) {
	$q = @$conn->query(
		"SELECT t.id, t.slug, t.title, t.policy_ids, t.orientations,
		        (SELECT COUNT(*) FROM monstrocity_theme_collections x
		          WHERE x.theme_id = t.id) AS linked
		   FROM monstrocity_themes t
		  ORDER BY t.sort_order, t.title");
	if ($q) while ($r = $q->fetch_assoc()) {
		$pols = array_values(array_filter(array_map('trim',
			explode(',', (string)$r['policy_ids'])), 'strlen'));
		$hit = array(); $miss = array();
		foreach ($pols as $pol) {
			if (isset($bypolicy[strtolower($pol)])) $hit[] = $bypolicy[strtolower($pol)];
			else                                    $miss[] = $pol;
		}
		$r['policies'] = $pols;
		$r['hit']      = $hit;
		$r['miss']     = $miss;
		$r['matched']  = count($hit);
		$r['verdict']  = monstrocity_link_verdict($r['policy_ids'], count($hit), $r['linked']);
		$rows[] = $r;
	}
}
$total_missing = 0;
foreach ($rows as $r) $total_missing += count($r['miss']);
$counts = array();
foreach ($rows as $r) $counts[$r['verdict']] = ($counts[$r['verdict']] ?? 0) + 1;
$ready = (int)($counts['ready'] ?? 0);

include 'header.php';
admin_chrome('themelink');
?>
<style>
.mtl-wrap { max-width: 1000px; margin: 0 auto; padding: 20px 16px 60px; }
.mtl-wrap h1 { font-size: 1.6rem; margin: 0 0 6px; }
.mtl-lede { color: rgba(255,255,255,.55); font-size: .9rem; margin: 0 0 18px; }
.mtl-msg { padding: 10px 12px; margin-bottom: 10px; font-size: .85rem; border-left: 3px solid; }
.mtl-msg.err  { background: rgba(224,85,85,.12); border-color: #e05555; }
.mtl-msg.ok   { background: rgba(0,200,160,.12); border-color: #00c8a0; }
.mtl-msg.warn { background: rgba(255,200,0,.12); border-color: #ffc800; }
.mtl-sum { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 16px; }
.mtl-chip { font-size: .72rem; text-transform: uppercase; letter-spacing: .08em;
            padding: 5px 9px; border: 1px solid rgba(255,255,255,.18); }
.mtl-chip b { color: #fff; }
.mtl-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.mtl-table th { text-align: left; font-size: .68rem; text-transform: uppercase;
                letter-spacing: .09em; color: rgba(255,255,255,.4);
                padding: 6px 8px; border-bottom: 1px solid rgba(255,255,255,.12); }
.mtl-table td { padding: 8px; border-bottom: 1px solid rgba(255,255,255,.06);
                vertical-align: top; }
.mtl-v { font-weight: 700; font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; }
.mtl-v.ready { color: #00c8a0; } .mtl-v.partial, .mtl-v.ambiguous { color: #ffc800; }
.mtl-v.none { color: #e05555; } .mtl-v.linked, .mtl-v.empty { color: rgba(255,255,255,.4); }
.mtl-why { color: rgba(255,255,255,.5); font-size: .76rem; }
.mtl-pol { font: .68rem/1.5 monospace; color: #ffc800; word-break: break-all; }
.mtl-sql { background: #07141d; border: 1px solid rgba(255,255,255,.12); padding: 12px;
           overflow-x: auto; font: .74rem/1.5 monospace; white-space: pre; color: #9fd8ff; }
.mtl-go { background: #00c8a0; color: #07111d; border: 0; padding: 12px 20px;
          font-weight: 700; cursor: pointer; margin: 14px 0; }
.mtl-go[disabled] { opacity: .4; cursor: default; }
@media (max-width: 700px) { .mtl-table thead { display: none; }
  .mtl-table tr { display: block; border-bottom: 1px solid rgba(255,255,255,.1); padding: 8px 0; }
  .mtl-table td { display: block; border: 0; padding: 2px 0; } }
</style>
<div class="mtl-wrap">
	<h1>Monstrocity theme links</h1>
	<p class="mtl-lede">Converts the hardcoded <code>policy_ids</code> strings into
	   <code>monstrocity_theme_collections</code> rows, so Gauntlets can find a
	   collection's own backdrop. Only themes that map one for one are converted.</p>

	<?php foreach (array('err','warn','ok') as $k): foreach ($msgs[$k] as $m): ?>
		<div class="mtl-msg <?php echo $k; ?>"><?php echo htmlspecialchars($m); ?></div>
	<?php endforeach; endforeach; ?>

	<?php if ($total_missing): ?>
		<div class="mtl-msg warn">
			<?php echo (int)$total_missing; ?> policy id<?php echo $total_missing === 1 ? '' : 's'; ?>
			across these themes belong to no collection. Those themes cannot drive a
			Gauntlets backdrop until the collection exists -- add it under
			Catalogue &gt; Collections with that exact policy, then come back here.
		</div>
	<?php endif; ?>

	<div class="mtl-sum">
		<?php foreach (array('ready','partial','ambiguous','none','linked','empty') as $v):
			if (empty($counts[$v])) continue; ?>
			<span class="mtl-chip"><b><?php echo (int)$counts[$v]; ?></b> <?php echo $v; ?></span>
		<?php endforeach; ?>
	</div>

	<form method="POST">
		<input type="hidden" name="action" value="apply">
		<button class="mtl-go" type="submit" <?php echo $ready ? '' : 'disabled'; ?>>
			<?php echo $ready ? 'Link the ' . $ready . ' ready theme' . ($ready === 1 ? '' : 's')
			                  : 'Nothing ready to link'; ?>
		</button>
	</form>

	<table class="mtl-table">
		<thead><tr><th>Theme</th><th>Policies</th><th>Collections</th><th>Verdict</th></tr></thead>
		<tbody>
		<?php foreach ($rows as $r):
			$n = count(array_filter(array_map('trim', explode(',', (string)$r['policy_ids'])), 'strlen')); ?>
			<tr>
				<td><strong><?php echo htmlspecialchars($r['title']); ?></strong><br>
				    <span class="mtl-why"><?php echo htmlspecialchars($r['slug']); ?></span></td>
				<td><?php echo $n; ?></td>
				<td><?php echo (int)$r['matched']; ?><?php
				    if ((int)$r['linked']) echo ' (' . (int)$r['linked'] . ' linked)'; ?>
				    <?php foreach ($r['hit'] as $c): ?>
				        <div class="mtl-why">&#10003; <?php echo htmlspecialchars($c['name']); ?></div>
				    <?php endforeach; ?></td>
				<td><span class="mtl-v <?php echo $r['verdict']; ?>"><?php echo $r['verdict']; ?></span><br>
				    <span class="mtl-why"><?php echo htmlspecialchars(monstrocity_link_reason($r['verdict'])); ?></span>
				    <?php if ($r['miss']): ?>
				        <div class="mtl-why" style="margin-top:4px">No collection holds:</div>
				        <?php foreach ($r['miss'] as $m): ?>
				            <div class="mtl-pol"><?php echo htmlspecialchars($m); ?></div>
				        <?php endforeach; ?>
				    <?php endif; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<p class="mtl-lede" style="margin-top:22px">The same conversion, if you would rather run it yourself:</p>
	<div class="mtl-sql"><?php echo htmlspecialchars(mtl_sql()); ?></div>
</div>
<?php include 'admin-css.php'; ?>
