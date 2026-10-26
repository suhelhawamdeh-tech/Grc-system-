<?php
// Display helpers shared by the register views.
$opt = fn(string $field, $v) => $v === null || $v === '' ? '' : t(sprintf('enum.rg_%s.', $field) . $v);
$stCls = fn(string $s) => match ($s) { 'compliant', 'received', 'approved', 'presented', 'closed' => 'ok', 'late_received', 'partial', 'deferred', 'under_validation', 'renewed', 'in_progress' => 'warn', 'expired', 'not_received' => 'danger', default => '' };
$st = fn(string $s) => '<span class="badge ' . $stCls($s) . '">' . e(t('enum.rgst.' . $s)) . '</span>';
/** Escaped display of a register field. */
$val = function (string $f, array $spec, $v) use ($opt): string {
    if ($v === null || $v === '') {
        return '—';
    }
    return match ($spec[0]) {
        'select' => e($opt($f, $v)),
        'score' => '<strong>' . (int)$v . '</strong>/5',
        'date' => e(fdate((string)$v)),
        'textarea' => '<span class="pre">' . e((string)$v) . '</span>',
        default => e((string)$v),
    };
};
