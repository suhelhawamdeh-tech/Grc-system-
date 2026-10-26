<?php
declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\I18n;
use App\Core\Money;
use App\Core\Request;
use App\Core\Session;

function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function t(string $key, array $params = []): string
{
    return I18n::t($key, $params);
}

function locale(): string
{
    return I18n::locale();
}

function is_rtl(): bool
{
    return I18n::locale() === 'ar';
}

function base_path(): string
{
    return Request::basePath();
}

function url(string $path = '', array $query = []): string
{
    $u = Request::basePath() . '/' . ltrim($path, '/');
    $query = array_filter($query, static fn($v) => $v !== null && $v !== '');
    return $query ? $u . '?' . http_build_query($query) : $u;
}

function asset(string $path): string
{
    $file = APP_ROOT . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? (string)filemtime($file) : APP_VERSION;
    return Request::basePath() . '/assets/' . ltrim($path, '/') . '?v=' . $v;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Localised name from a row that has <base>_en / <base>_ar columns. */
function lname(?array $row, string $base = 'name', string $suffix = ''): string
{
    if (!$row) {
        return '';
    }
    $primary = $base . '_' . I18n::locale() . $suffix;
    $other = $base . '_' . (I18n::locale() === 'ar' ? 'en' : 'ar') . $suffix;
    $val = (string)($row[$primary] ?? '');
    return $val !== '' ? $val : (string)($row[$other] ?? '');
}

function money(mixed $v): string
{
    return Money::format($v);
}

function fdate(?string $d): string
{
    if (!$d || $d === '0000-00-00') {
        return '';
    }
    return substr($d, 0, 10);
}

function fdatetime(?string $d): string
{
    return $d ? substr($d, 0, 16) : '';
}

function old(string $key, mixed $default = ''): mixed
{
    $old = Session::peek('old') ?? [];
    return array_key_exists($key, $old) ? $old[$key] : $default;
}

function field_error(string $key): string
{
    $errs = Session::peek('errors') ?? [];
    return isset($errs[$key]) ? '<p class="field-error" role="alert">' . e($errs[$key]) . '</p>' : '';
}

function can(string $perm): bool
{
    return Auth::can($perm);
}

function current_user(): ?array
{
    return Auth::user();
}

function sel(mixed $a, mixed $b): string
{
    return (string)$a === (string)$b ? ' selected' : '';
}

function checked(mixed $a): string
{
    return $a ? ' checked' : '';
}

/** Current query string merged with overrides (null removes a key). */
function qs(array $merge = [], ?array $base = null): string
{
    $q = $base ?? $_GET;
    foreach ($merge as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    return $q ? '?' . http_build_query($q) : '';
}

/** Readable text colour (black/white) for a hex background. */
function contrast_text(string $hex): string
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) !== 6) {
        return '#000000';
    }
    $lin = static function (int $c): float {
        $c /= 255;
        return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    };
    $l = 0.2126 * $lin(hexdec(substr($hex, 0, 2))) + 0.7152 * $lin(hexdec(substr($hex, 2, 2))) + 0.0722 * $lin(hexdec(substr($hex, 4, 2)));
    return $l > 0.35 ? '#0b1f3a' : '#ffffff';
}

function safe_color(string $c): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : '#888888';
}

/** Coloured chip for a score: "12 · High". Colour and label come from the configurable score bands. */
function band_chip(int $score): string
{
    $b = \App\Support\Ref::band($score);
    if (!$b) {
        return '<span class="chip">' . e($score) . '</span>';
    }
    $c = safe_color((string)$b['color']);
    return '<span class="chip" style="background:' . e($c) . ';color:' . contrast_text($c) . '">' . e($score) . ' · ' . e(lname($b, 'label')) . '</span>';
}

function band_label_chip(array $b): string
{
    $c = safe_color((string)$b['color']);
    return '<span class="chip" style="background:' . e($c) . ';color:' . contrast_text($c) . '">' . e(lname($b, 'label')) . ' (' . (int)$b['min_score'] . '–' . (int)$b['max_score'] . ')</span>';
}

function enum_label(string $group, ?string $value): string
{
    return $value === null || $value === '' ? '' : t('enum.' . $group . '.' . $value);
}

/** Sortable table header link. */
function sort_link(string $label, string $key, array $f): string
{
    $dir = ($f['sort'] ?? '') === $key && ($f['dir'] ?? '') === 'asc' ? 'desc' : 'asc';
    $arrow = ($f['sort'] ?? '') === $key ? (($f['dir'] ?? '') === 'asc' ? ' ▲' : ' ▼') : '';
    return '<a href="' . e(qs(['sort' => $key, 'dir' => $dir, 'page' => null])) . '">' . e($label) . $arrow . '</a>';
}

function pager(int $total, int $page, int $per): string
{
    $pages = (int)max(1, ceil($total / $per));
    if ($pages <= 1) {
        return '';
    }
    $out = '<nav class="pager noprint" aria-label="' . e(t('common.pagination')) . '">';
    $window = range(max(1, $page - 3), min($pages, $page + 3));
    if ($page > 1) {
        $out .= '<a href="' . e(qs(['page' => $page - 1])) . '" rel="prev">' . e(t('common.prev')) . '</a>';
    }
    foreach ($window as $p) {
        $out .= $p === $page ? '<span class="cur" aria-current="page">' . $p . '</span>' : '<a href="' . e(qs(['page' => $p])) . '">' . $p . '</a>';
    }
    if ($page < $pages) {
        $out .= '<a href="' . e(qs(['page' => $page + 1])) . '" rel="next">' . e(t('common.next')) . '</a>';
    }
    return $out . '<span>' . e(t('common.total_n', ['n' => $total])) . '</span></nav>';
}

/** Horizontal bar chart; each row links to the records behind it. items: [label, value, href, valueLabel?] */
function bars(array $items, ?int $max = null): string
{
    if (!$items) {
        return '<p class="muted">' . e(t('common.no_data')) . '</p>';
    }
    $max ??= max(1, max(array_map(static fn($i) => (float)$i[1], $items)));
    $out = '<ul class="bars">';
    foreach ($items as $it) {
        $pct = $max > 0 ? max(0, min(100, (int)round(((float)$it[1] / $max) * 100))) : 0;
        $out .= '<li><a href="' . e($it[2]) . '"><span class="lbl">' . e($it[0]) . '</span><span class="track" aria-hidden="true"><span class="fill" style="width:' . $pct . '%"></span></span><span class="val">' . e($it[3] ?? $it[1]) . '</span></a></li>';
    }
    return $out . '</ul>';
}

/** 5x5 heat map. $grid[likelihood][impact] = count. $prefix is 'inh' or 'res' (filter parameter prefix). */
function heatmap(array $grid, string $prefix, array $lik, array $imp): string
{
    $out = '<table class="heat"><caption class="sr-only">' . e(t('dash.heat_caption_' . $prefix)) . '</caption><thead><tr><th scope="col"></th>';
    for ($l = 1; $l <= 5; $l++) {
        $out .= '<th scope="col">' . e($l . ' ' . lname($lik[$l] ?? null, 'label')) . '</th>';
    }
    $out .= '</tr></thead><tbody>';
    for ($i = 5; $i >= 1; $i--) {
        $out .= '<tr><th scope="row">' . e($i . ' ' . lname($imp[$i] ?? null, 'label')) . '</th>';
        for ($l = 1; $l <= 5; $l++) {
            $n = (int)($grid[$l][$i] ?? 0);
            $score = $l * $i;
            $b = \App\Support\Ref::band($score);
            $bg = safe_color((string)($b['color'] ?? '#cccccc'));
            $fg = contrast_text($bg);
            $aria = t('dash.heat_cell', ['l' => $l, 'i' => $i, 's' => $score, 'band' => lname($b, 'label'), 'n' => $n]);
            $out .= '<td class="' . ($n ? '' : 'zero') . '" style="background:' . e($bg) . ';color:' . $fg . '">';
            if ($n) {
                $href = url('risks', ['status' => 'active', $prefix . '_l' => $l, $prefix . '_i' => $i]);
                $out .= '<a href="' . e($href) . '" aria-label="' . e($aria) . '"><span class="n">' . $n . '</span><span class="s">' . $score . '</span></a>';
            } else {
                $out .= '<span aria-label="' . e($aria) . '" style="display:block"><span class="s">' . $score . '</span></span>';
            }
            $out .= '</td>';
        }
        $out .= '</tr>';
    }
    return $out . '</tbody></table><p class="axis-note">' . e(t('dash.heat_axes')) . '</p>';
}

/**
 * Form field helper. $o: required, hint, wide, options (value => label), blank (bool), dir, extra (raw attribute string).
 * Values come from old input first (validation round-trip), then $val.
 */
function ff(string $type, string $name, string $labelKey, mixed $val = '', array $o = []): string
{
    $val = old($name, $val);
    $err = field_error($name);
    $req = !empty($o['required']);
    $id = 'f_' . $name;
    $h = '<div class="field' . (!empty($o['wide']) ? ' wide' : '') . ($err ? ' has-error' : '') . '"><label for="' . $id . '">' . e(t($labelKey)) . ($req ? ' *' : '') . '</label>';
    $attrs = ' id="' . $id . '" name="' . e($name) . '"' . ($req ? ' required' : '') . (!empty($o['dir']) ? ' dir="' . e($o['dir']) . '"' : '') . ($o['extra'] ?? '');
    if ($type === 'select') {
        $h .= '<select' . $attrs . '>';
        if (($o['blank'] ?? true) !== false) {
            $h .= '<option value="">' . e(t($o['blank_label'] ?? 'common.select')) . '</option>';
        }
        foreach ($o['options'] ?? [] as $k => $label) {
            $h .= '<option value="' . e($k) . '"' . sel($val, $k) . '>' . e($label) . '</option>';
        }
        $h .= '</select>';
    } elseif ($type === 'textarea') {
        $h .= '<textarea' . $attrs . '>' . e($val) . '</textarea>';
    } else {
        $h .= '<input type="' . e($type) . '"' . $attrs . ' value="' . e($val) . '">';
    }
    if (!empty($o['hint'])) {
        $h .= '<p class="hint">' . e($o['hint']) . '</p>';
    }
    return $h . $err . '</div>';
}

/** Build an options map from rows using a label callback. */
function opts(array $rows, callable $label, ?string $key = 'id'): array
{
    $out = [];
    foreach ($rows as $r) {
        $out[$key === null ? $r : $r[$key]] = $label($r);
    }
    return $out;
}

/** Badge for an independent risk opinion (no objection, conditional, reservation). */
function op_badge(string $o): string
{
    $cls = match ($o) { 'no_objection' => 'ok', 'conditional' => 'warn', default => 'danger' };
    return '<span class="badge ' . $cls . '">' . e(t('enum.rmop.' . $o)) . '</span>';
}

/** Badge for a document classification (public, internal, confidential, secret). */
function cls_badge(string $c): string
{
    $cls = match ($c) { 'secret' => 'danger', 'confidential' => 'warn', 'public' => 'ok', default => '' };
    return '<span class="badge ' . $cls . '">' . e(t('enum.cls.' . $c)) . '</span>';
}

function status_badge(string $status): string
{
    $cls = match ($status) {
        'green', 'compliant', 'approved', 'completed', 'implemented', 'resolved', 'effective' => 'ok',
        'amber', 'in_progress', 'submitted', 'escalated', 'partially_effective', 'draft', 'returned' => 'warn',
        'red', 'critical', 'non_compliant', 'open', 'ineffective' => 'danger',
        default => '',
    };
    return '<span class="badge ' . $cls . '">' . e(t('enum.status.' . $status)) . '</span>';
}

/** Inline SVG icon (decorative: hidden from assistive technology). Paths are 24x24 stroke icons. */
function icon(string $name): string
{
    static $p = [
        'home' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'alert' => '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
        'check' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/>',
        'pulse' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'target' => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
        'scale' => '<path d="M12 3v18M5 21h14M5 7l-3 7a3.5 3.5 0 0 0 6 0zM19 7l-3 7a3.5 3.5 0 0 0 6 0zM5 7h14"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
        'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
        'clipboard' => '<path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 12h6M9 16h6"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="M21 21l-4.3-4.3"/>',
        'sliders' => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'help' => '<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/>',
        'note' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'tasks' => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'bulb' => '<path d="M9 18h6M10 22h4"/><path d="M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.2 1 2V17h6v-.3c0-.8.4-1.5 1-2A7 7 0 0 0 12 2z"/>',
        'flag' => '<path d="M4 22V4M4 4h13l-2 4 2 4H4"/>',
        'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'layers' => '<path d="M12 2L2 7l10 5 10-5z"/><path d="M2 17l10 5 10-5M2 12l10 5 10-5"/>',
        'flask' => '<path d="M9 2h6M10 2v6L4 19a2 2 0 0 0 1.8 3h12.4A2 2 0 0 0 20 19l-6-11V2"/><path d="M7 15h10"/>',
        'link' => '<path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'close' => '<path d="M18 6L6 18M6 6l12 12"/>',
        'eye' => '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>',
        'spark' => '<path d="M12 3l1.9 5.6L19.5 10l-5.6 1.9L12 17.5l-1.9-5.6L4.5 10l5.6-1.4z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/>',
        'lock' => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    ];
    return '<svg class="ico" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($p[$name] ?? $p['help']) . '</svg>';
}

/** "Related references" panel for a module: trusted sources from the reference library, with a link to ask the assistant. */
function kb_refs(string $module): string
{
    if (!can('ai.use')) {
        return '';
    }
    try {
        \App\Support\KbLibrary::ensure();
        $rows = \App\Support\KbLibrary::forModule($module, can('kb.manage'));
    } catch (\Throwable) {
        return '';
    }
    if (!$rows) {
        return '';
    }
    $h = '<details class="card refs"><summary><strong>' . icon('book') . ' ' . e(t('ai.refs_title')) . '</strong> <span class="muted small">(' . count($rows) . ')</span></summary><ul>';
    foreach ($rows as $r) {
        $h .= '<li><a href="' . e(url('assistant/library/' . $r['id'])) . '">' . e(lname($r, 'title')) . '</a> <span class="muted small">' . e($r['issuer']) . ($r['pub_year'] ? ' · ' . (int)$r['pub_year'] : '') . '</span></li>';
    }
    return $h . '</ul><p><a class="btn small secondary" href="' . e(url('assistant')) . '">' . icon('spark') . ' ' . e(t('ai.ask_assistant')) . '</a> <a class="small" href="' . e(url('assistant/library')) . '">' . e(t('ai.library')) . '</a></p></details>';
}

/** Button opening the assistant with a de-identified context of the current record. */
function ai_ask_button(string $context, int $id): string
{
    if (!can('ai.use') || \App\Core\Settings::get('ai_enabled', '1') !== '1') {
        return '';
    }
    return '<a class="btn secondary ai-btn" href="' . e(url('assistant', ['context' => $context, 'id' => $id])) . '">' . icon('spark') . ' ' . e(t('ai.ask_about_this')) . '</a>';
}

