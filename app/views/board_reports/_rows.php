<?php
/** Rows of one kind: table, inline edit, add form. @var string $kind @var array $x @var bool $edit @var array $departments */
$fields = \App\Controllers\BoardReportController::FIELDS[$kind];
$rid = (int)$x['r']['id'];
$list = $x['rows'][$kind] ?? [];
$input = function (string $f, string $type, string $label, bool $req, mixed $val, string $idp) use ($departments): string {
    $id = $idp . $f;
    $h = '<div class="field' . ($type === 'textarea' ? ' wide' : '') . '"><label for="' . e($id) . '">' . e(t($label)) . ($req ? ' *' : '') . '</label>';
    $a = ' id="' . e($id) . '" name="' . e($f) . '"' . ($req ? ' required' : '');
    $choices = match ($type) {
        'level' => array_combine(\App\Support\BoardReport::LEVELS, array_map(fn($k) => t('enum.brlevel.' . $k), \App\Support\BoardReport::LEVELS)),
        'trend' => array_combine(\App\Support\BoardReport::TRENDS, array_map(fn($k) => t('enum.brtrend.' . $k), \App\Support\BoardReport::TRENDS)),
        'dtype' => array_combine(\App\Support\BoardReport::DECISION_TYPES, array_map(fn($k) => t('enum.brdtype.' . $k), \App\Support\BoardReport::DECISION_TYPES)),
        'priority' => array_combine(['urgent', 'high', 'medium'], array_map(fn($k) => t('enum.brprio.' . $k), ['urgent', 'high', 'medium'])),
        'tilelevel' => array_combine(['ok', 'warn', 'danger'], array_map(fn($k) => t('enum.brtile.' . $k), ['ok', 'warn', 'danger'])),
        'reftype' => array_combine(\App\Controllers\BoardReportController::REF_TYPES, array_map(fn($k) => t('enum.brref.' . $k), \App\Controllers\BoardReportController::REF_TYPES)),
        'dept' => opts($departments, fn($d) => lname($d)),
        default => null,
    };
    if ($choices !== null) {
        $h .= '<select' . $a . '><option value="">' . e(t('common.select')) . '</option>';
        foreach ($choices as $k => $l) {
            $h .= '<option value="' . e((string)$k) . '"' . ((string)$val === (string)$k ? ' selected' : '') . '>' . e($l) . '</option>';
        }
        $h .= '</select>';
    } elseif ($type === 'textarea') {
        $h .= '<textarea' . $a . ' rows="2">' . e((string)$val) . '</textarea>';
    } else {
        $h .= '<input type="' . ($type === 'number' ? 'number" min="0" max="3650' : 'text') . '"' . $a . ' value="' . e((string)$val) . '">';
    }
    return $h . '</div>';
};
?>
<div class="table-wrap" id="k-<?= e($kind) ?>"><table class="data"><thead><tr><th>#</th><?php foreach ($fields as [$type, $label]): ?><th><?= e(t($label)) ?></th><?php endforeach; ?><?php if ($kind === 'recommendation' && $x['r']['actions_created']): ?><th><?= e(t('brr.action')) ?></th><?php endif; ?><?php if ($edit): ?><th></th><?php endif; ?></tr></thead><tbody>
<?php if (!$list): ?><tr><td colspan="<?= count($fields) + 2 ?>" class="muted"><?= e(t('brr.no_rows')) ?></td></tr><?php endif; ?>
<?php foreach ($list as $i => $row): ?><tr><td><?= $i + 1 ?></td><?php foreach ($fields as $f => [$type]): ?><td><?= $disp($row, $f, $type) ?></td><?php endforeach; ?>
<?php if ($kind === 'recommendation' && $x['r']['actions_created']): ?><td><?php if ($row['decision_id']): ?><a href="<?= e(url('committees/' . $row['decision_id'])) ?>"><?= e(t('brr.view_action')) ?></a><?php endif; ?></td><?php endif; ?>
<?php if ($edit): ?><td><details><summary><?= e(t('common.edit')) ?></summary><form method="post" action="<?= e(url('board-reports/' . $rid . '/rows/' . $row['id'])) ?>" class="form-grid" novalidate><?= csrf_field() ?>
<?php foreach ($fields as $f => [$type, $label, $req]): ?><?= $input($f, $type, $label, $req, $row[$f] ?? '', 'r' . $row['id'] . '_') ?><?php endforeach; ?>
<div class="field"><button class="btn small" type="submit"><?= e(t('common.save')) ?></button></div></form>
<form method="post" action="<?= e(url('board-reports/' . $rid . '/rows/' . $row['id'] . '/delete')) ?>" class="inline" data-confirm="<?= e(t('brr.confirm_delete')) ?>"><?= csrf_field() ?><button class="btn small secondary" type="submit"><?= e(t('common.delete')) ?></button></form></details></td><?php endif; ?></tr><?php endforeach; ?>
</tbody></table></div>
<?php if ($edit): ?><details class="card add-row"><summary><?= e(t(sprintf('brr.add_%s', $kind))) ?></summary><form method="post" action="<?= e(url('board-reports/' . $rid . '/rows')) ?>" class="form-grid" novalidate><?= csrf_field() ?><input type="hidden" name="kind" value="<?= e($kind) ?>">
<?php foreach ($fields as $f => [$type, $label, $req]): ?><?= $input($f, $type, $label, $req, '', 'n_' . $kind . '_') ?><?php endforeach; ?>
<div class="field"><button class="btn" type="submit"><?= e(t('common.add')) ?></button></div></form></details><?php endif; ?>
