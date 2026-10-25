<?php
/** Rating badge for AML levels (low / medium / high). */
$amlBadge = static fn(?string $r): string => $r === null ? '<span class="muted">—</span>' : '<span class="badge ' . ($r === 'high' ? 'danger' : ($r === 'medium' ? 'warn' : 'ok')) . '">' . e(t('enum.amlr.' . $r)) . '</span>';
$lvlName = static fn(int $l): string => ['', 'low', 'medium', 'high'][max(1, min(3, $l))];
