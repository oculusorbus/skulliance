<?php if (!empty($logs)): ?>
<div style="margin-top:18px;border-top:1px solid rgba(255,255,255,0.1);padding-top:14px;">
    <strong style="font-size:0.85rem;">Unclaimed Rewards</strong>
    <?php
    // Group duplicate entries by type+item
    $grouped = array();
    foreach ($logs as $log) {
        $key = $log['type'] . '_' . $log['item_id'];
        if (!isset($grouped[$key])) {
            $grouped[$key] = $log;
            $grouped[$key]['total_qty'] = intval($log['quantity']);
        } else {
            $grouped[$key]['total_qty'] += intval($log['quantity']);
        }
    }
    $gear_weapons = array_filter($grouped, fn($e) => $e['type'] === 'weapon');
    $gear_armors  = array_filter($grouped, fn($e) => $e['type'] === 'armor');
    $other        = array_filter($grouped, fn($e) => !in_array($e['type'], array('weapon','armor')));
    usort($gear_weapons, fn($a,$b) => $b['weapon_level'] - $a['weapon_level']);
    usort($gear_armors,  fn($a,$b) => $b['armor_level']  - $a['armor_level']);
    $has_gear = !empty($gear_weapons) || !empty($gear_armors);

    // Helper to render a single log row
    function _log_row($entry) {
        $qty  = $entry['total_qty'];
        $date = date('M j', strtotime($entry['created_date']));
        switch ($entry['type']) {
            case 'carbon':
                $label = number_format($qty) . ' CARBON';
                $icon  = 'icons/carbon.png'; break;
            case 'consumable':
                $label = $qty . '× ' . htmlspecialchars($entry['consumable_name']);
                $icon  = 'icons/' . strtolower(str_replace('%', '', str_replace(' ', '-', $entry['consumable_name']))) . '.png'; break;
            case 'weapon':
                $label = $qty . '× Lv' . $entry['weapon_level'] . ' ' . htmlspecialchars($entry['weapon_name']);
                $icon  = 'icons/' . strtolower(str_replace(' ', '-', $entry['weapon_name'])) . '.png'; break;
            case 'armor':
                $label = $qty . '× Lv' . $entry['armor_level'] . ' ' . htmlspecialchars($entry['armor_name']);
                $icon  = 'icons/' . strtolower(str_replace(' ', '-', $entry['armor_name'])) . '.png'; break;
            default:
                $label = ''; $icon = 'icons/skull.png';
        }
        echo '<div style="display:flex;align-items:center;gap:7px;font-size:0.8rem;background:rgba(255,255,255,0.04);border-radius:6px;padding:5px 8px;">';
        echo '<img class="icon" src="' . $icon . '" onerror="this.src=\'icons/skull.png\'" style="width:20px;height:20px;" />';
        echo '<span style="flex:1;">' . $label . '</span>';
        echo '<span style="opacity:0.45;font-size:0.75rem;">' . $date . '</span>';
        echo '</div>';
    }
    ?>
    <?php if (!empty($other)): ?>
    <div style="margin-top:10px;display:flex;flex-direction:column;gap:5px;">
        <?php foreach ($other as $entry) _log_row($entry); ?>
    </div>
    <?php endif; ?>
    <?php if ($has_gear): ?>
    <div style="margin-top:10px;display:flex;gap:12px;">
        <?php foreach (array('Weapons' => $gear_weapons, 'Armor' => $gear_armors) as $col_label => $col_items): ?>
        <?php if (!empty($col_items)): ?>
        <div style="flex:1;min-width:0;">
            <div style="font-size:0.72rem;opacity:0.5;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:5px;"><?php echo $col_label; ?></div>
            <div style="display:flex;flex-direction:column;gap:5px;">
                <?php foreach ($col_items as $entry) _log_row($entry); ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <button class="small-button" onclick="claimRealmLogs(<?php echo htmlspecialchars(json_encode($claim_types)); ?>)" style="margin-top:12px;background:#00c8a0;color:#000;width:100%;">Claim All</button>
</div>
<?php else: ?>
<div style="margin-top:18px;border-top:1px solid rgba(255,255,255,0.1);padding-top:14px;font-size:0.8rem;opacity:0.5;text-align:center;">
    No unclaimed rewards — check back after the next nightly run.
</div>
<?php endif; ?>
