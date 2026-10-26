<?php
// Shared display helpers for the board report views.

$ar = locale() === 'ar';
$tpl = \App\Support\BoardReport::template();
$lvlCls = fn(?string $l) => match ($l) { 'critical' => 'danger', 'high' => 'warn', 'low' => 'ok', default => '' };
$lvl = fn(?string $l) => $l ? '<span class="badge ' . $lvlCls($l) . '">' . e(t('enum.brlevel.' . $l)) . '</span>' : '';
$secTitle = fn(int $no) => $ar ? $tpl['sections'][$no][0] : $tpl['sections'][$no][1];
/** Display value of a row field (escaped HTML). */
$disp = function (array $row, string $f, string $type) use ($lvl): string {
    $v = $row[$f] ?? null;
    if ($v === null || $v === '') {
        return '';
    }
    return match ($type) {
        'level' => $lvl((string)$v),
        'trend' => e(t('enum.brtrend.' . $v)),
        'dtype' => e(t('enum.brdtype.' . $v)),
        'priority' => '<span class="badge ' . ($v === 'urgent' ? 'danger' : ($v === 'high' ? 'warn' : '')) . '">' . e(t('enum.brprio.' . $v)) . '</span>',
        'tilelevel' => '<span class="badge ' . e((string)$v) . '">' . e(t('enum.brtile.' . $v)) . '</span>',
        'reftype' => e(t('enum.brref.' . $v)),
        'dept' => e($row['dept_ar'] ? lname(['name_ar' => $row['dept_ar'], 'name_en' => $row['dept_en']]) : ''),
        default => '<span class="pre">' . e((string)$v) . '</span>',
    };
};
