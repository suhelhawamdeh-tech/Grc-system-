<?php /** @var string $k @var array $def @var ?array $item @var array $departments @var array $users @var array $refs */ require __DIR__ . '/_fmt.php'; $d = $item['d'] ?? []; ?>
<div class="page-head"><div><h1><?= icon($def['icon']) ?> <?= e(t(sprintf('reg.%s', $k))) ?> — <?= e($item ? $item['code'] : t('reg.new')) ?></h1></div>
<div class="actions"><a class="btn secondary" href="<?= e($item ? url('registers/' . $item['id']) : url('registers') . '?r=' . $k) ?>"><?= e(t('common.back')) ?></a></div></div>
<form method="post" action="<?= e($item ? url('registers/' . $item['id']) : url('registers')) ?>" class="card" novalidate><?= csrf_field() ?><input type="hidden" name="r" value="<?= e($k) ?>"><div class="form-grid">
<?= ff('text', 'title', 'reg.f_title', $item['title'] ?? '', ['required' => $k !== 'maturity', 'wide' => true, 'hint' => t(sprintf('reg.%s_title_hint', $k))]) ?>
<?php if ($def['ref']): ?><?= ff('select', 'ref_id', sprintf('reg.ref_%s', $def['ref']), $item['ref_id'] ?? (\App\Core\Request::int('ref', true) ?? ''), ['required' => $def['ref'] === 'circular', 'options' => array_map(fn($l) => mb_strimwidth($l, 0, 90, '…'), $refs)]) ?><?php endif; ?>
<?php foreach ($def['fields'] as $f => $spec): [$type, $req] = $spec; $label = sprintf('reg.f_%s', $f); $v = $d[$f] ?? ''; ?>
<?php if ($type === 'select'): ?><?= ff('select', $f, $label, $v, ['required' => $req, 'options' => array_combine($spec[2], array_map(fn($o) => $opt($f, $o), $spec[2]))]) ?>
<?php elseif ($type === 'score'): ?><?= ff('select', $f, $label, $v, ['required' => $req, 'options' => [1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'], 'hint' => t(sprintf('reg.score_hint_%s', $f === 'level' || $f === 'target' ? 'maturity' : 'risk'))]) ?>
<?php elseif ($type === 'int'): ?><?= ff('number', $f, $label, $v ?: (int)date('Y'), ['required' => $req, 'extra' => ' min="2000" max="2100"']) ?>
<?php else: ?><?= ff($type === 'textarea' ? 'textarea' : ($type === 'date' ? 'date' : 'text'), $f, $label, $v, ['required' => $req, 'wide' => $type === 'textarea', 'extra' => $type === 'textarea' ? ' rows="3"' : '']) ?><?php endif; ?>
<?php endforeach; ?>
<?= ff('select', 'department_id', 'reg.department', $item['department_id'] ?? '', ['options' => opts($departments, fn($x) => lname($x))]) ?>
<?= ff('select', 'owner_user_id', 'reg.owner', $item['owner_user_id'] ?? '', ['options' => opts($users, fn($x) => $x['full_name'])]) ?>
<?php if ($def['due']): ?><?= ff('date', 'due_date', $def['due'], $item['due_date'] ?? '', ['required' => in_array($k, ['data', 'security', 'requirements'], true)]) ?><?php endif; ?>
<?= ff('select', 'status', 'reg.status', $item['status'] ?? $def['statuses'][0], ['required' => true, 'blank' => false, 'options' => array_combine($def['statuses'], array_map(fn($s) => t('enum.rgst.' . $s), $def['statuses'])), 'hint' => in_array($k, ['data', 'security'], true) ? t(sprintf('reg.%s_status_hint', $k)) : '']) ?>
</div><div class="actions"><button class="btn" type="submit"><?= e(t('common.save')) ?></button></div></form>
