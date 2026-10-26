<?php /** @var array $item @var array $def @var string $k @var ?string $refLabel @var ?array $esc */ require __DIR__ . '/_fmt.php'; $refUrl = \App\Support\Registers::refUrl($def['ref'], $item['ref_id'] ? (int)$item['ref_id'] : null); ?>
<div class="page-head"><div><h1><?= icon($def['icon']) ?> <?= e($item['title']) ?></h1><p class="sub"><?= e($item['code']) ?> · <?= e(t(sprintf('reg.%s', $k))) ?> · <?= $st($item['status']) ?><?= $item['overdue'] ? ' <span class="badge danger">' . e(t('reg.overdue')) . '</span>' : '' ?></p></div>
<div class="actions"><a class="btn secondary" href="<?= e(url('registers') . '?r=' . $k) ?>"><?= e(t('common.back')) ?></a><?php if (can('rm.work')): ?><a class="btn" href="<?= e(url('registers/' . $item['id'] . '/edit')) ?>"><?= e(t('common.edit')) ?></a><?php endif; ?></div></div>
<?php if ($esc): ?><div class="card notice"><p><?= icon('alert') ?> <?= e(t('reg.escalated')) ?> <a href="<?= e(url('rm/escalations/' . $esc['id'])) ?>"><?= e($esc['code']) ?></a></p></div><?php endif; ?>
<section class="card"><dl class="details">
<?php if ($def['ref']): ?><dt><?= e(t(sprintf('reg.ref_%s', $def['ref']))) ?></dt><dd><?= $refUrl ? '<a href="' . e(url($refUrl)) . '">' . e((string)$refLabel) . '</a>' : '—' ?></dd><?php endif; ?>
<?php foreach ($def['fields'] as $f => $spec): ?><dt><?= e(t(sprintf('reg.f_%s', $f))) ?></dt><dd><?= $val($f, $spec, $item['d'][$f] ?? null) ?></dd><?php endforeach; ?>
<dt><?= e(t('reg.department')) ?></dt><dd><?= e(lname(['name_ar' => $item['dept_ar'], 'name_en' => $item['dept_en']]) ?: '—') ?></dd>
<dt><?= e(t('reg.owner')) ?></dt><dd><?= e($item['owner'] ?? '—') ?></dd>
<?php if ($def['due']): ?><dt><?= e(t($def['due'])) ?></dt><dd><?= e(fdate($item['due_date']) ?: '—') ?></dd><?php endif; ?>
<dt><?= e(t('reg.recorded')) ?></dt><dd><?= e($item['creator']) ?> · <?= e(fdatetime($item['created_at'])) ?></dd></dl></section>
<?php if ($k === 'requirements' && $item['ref_id']): ?><p><a href="<?= e(url('registers') . '?r=requirements&ref=' . (int)$item['ref_id']) ?>"><?= e(t('reg.matrix_of_circular')) ?></a></p><?php endif; ?>
