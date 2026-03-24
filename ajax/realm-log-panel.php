<?php if (!empty($logs)): ?>
<div style="margin-top:18px;border-top:1px solid rgba(255,255,255,0.1);padding-top:14px;">
    <strong style="font-size:0.85rem;">Unclaimed Rewards</strong>
    <div style="margin-top:10px;display:flex;flex-direction:column;gap:6px;">
    <?php foreach ($logs as $log):
        $date = date('M j', strtotime($log['created_date']));
        switch ($log['type']) {
            case 'carbon':
                $label = number_format($log['quantity']) . ' CARBON';
                $icon  = 'icons/carbon.png';
                break;
            case 'consumable':
                $label = $log['quantity'] . '× ' . htmlspecialchars($log['consumable_name']);
                $icon  = 'icons/' . strtolower(str_replace('%', '', str_replace(' ', '-', $log['consumable_name']))) . '.png';
                break;
            case 'weapon':
                $label = 'Lv' . $log['weapon_level'] . ' ' . htmlspecialchars($log['weapon_name']);
                $icon  = 'icons/weapons/' . strtolower(str_replace(' ', '-', $log['weapon_name'])) . '.png';
                break;
            case 'armor':
                $label = 'Lv' . $log['armor_level'] . ' ' . htmlspecialchars($log['armor_name']);
                $icon  = 'icons/armor/' . strtolower(str_replace(' ', '-', $log['armor_name'])) . '.png';
                break;
            default:
                $label = ''; $icon = 'icons/skull.png';
        }
    ?>
    <div style="display:flex;align-items:center;gap:8px;font-size:0.82rem;">
        <img class="icon" src="<?php echo $icon; ?>" onerror="this.src='icons/skull.png'" style="width:20px;height:20px;" />
        <span style="flex:1;"><?php echo $label; ?></span>
        <span style="opacity:0.45;font-size:0.75rem;"><?php echo $date; ?></span>
    </div>
    <?php endforeach; ?>
    </div>
    <button class="small-button" onclick="claimRealmLogs(<?php echo htmlspecialchars(json_encode($claim_types)); ?>)" style="margin-top:12px;background:#00c8a0;color:#000;width:100%;">Claim All</button>
</div>
<?php else: ?>
<div style="margin-top:18px;border-top:1px solid rgba(255,255,255,0.1);padding-top:14px;font-size:0.8rem;opacity:0.5;text-align:center;">
    No unclaimed rewards — check back after the next nightly run.
</div>
<?php endif; ?>
