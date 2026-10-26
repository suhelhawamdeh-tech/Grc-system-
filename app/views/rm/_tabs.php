<?php $tab = $tab ?? ''; $tabs = [];
if (can('rm.view')) { $tabs = ['home' => ['rm.tab_home', 'rm'], 'calendar' => ['rm.calendar', 'rm/calendar'], 'opinions' => ['rm.opinions', 'rm/opinions']]; }
if (can('rm.breaches')) { $tabs['breaches'] = ['rm.breaches', 'rm/breaches']; }
if (can('rm.view')) { $tabs['escalations'] = ['rm.escalations', 'rm/escalations']; $tabs['procedures'] = ['rm.procedures', 'rm/procedures']; } ?>
<nav class="pill-tabs" aria-label="<?= e(t('rm.title')) ?>"><?php foreach ($tabs as $k => [$lbl, $href]): ?><a href="<?= e(url($href)) ?>"<?= $k === $tab ? ' aria-current="page" class="active"' : '' ?>><?= e(t($lbl)) ?></a><?php endforeach; ?></nav>
