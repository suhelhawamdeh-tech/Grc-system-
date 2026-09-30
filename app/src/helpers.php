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

function status_badge(string $status): string
{
    $cls = match ($status) {
        'green', 'compliant', 'approved', 'completed', 'implemented', 'resolved', 'effective' => 'ok',
        'amber', 'in_progress', 'submitted', 'escalated', 'partially_effective', 'draft', 'returned' => 'warn',
        'red', 'non_compliant', 'open', 'ineffective' => 'danger',
        default => '',
    };
    return '<span class="badge ' . $cls . '">' . e(t('enum.status.' . $status)) . '</span>';
}
