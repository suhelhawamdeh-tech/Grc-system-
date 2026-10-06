<?php
declare(strict_types=1);

namespace App\Support;

/** In-app help content (app/help/<locale>.php): topics per role, page-to-topic map, glossary, role quick-starts. */
final class Help
{
    public static function data(): array
    {
        static $cache = [];
        $l = locale();
        return $cache[$l] ??= require APP_ROOT . '/app/help/' . $l . '.php';
    }

    /** Topics visible to a role, keyed by slug. */
    public static function topicsFor(string $role): array
    {
        $out = [];
        foreach (self::data()['topics'] as $slug => $tp) {
            $roles = $tp['roles'] ?? '*';
            if ($roles === '*' || in_array($role, (array)$roles, true)) {
                $out[$slug] = $tp;
            }
        }
        return $out;
    }

    public static function topic(string $slug, string $role): ?array
    {
        return self::topicsFor($role)[$slug] ?? null;
    }

    /** Longest-prefix match of a request path against the page map. */
    public static function slugForPath(string $path): ?string
    {
        $path = '/' . trim($path, '/');
        $best = null;
        $bestLen = -1;
        foreach (self::data()['pages'] as $prefix => $slug) {
            $p = '/' . trim($prefix, '/');
            $hit = $p === '/' ? $path === '/' : ($path === $p || str_starts_with($path, $p . '/'));
            if ($hit && strlen($p) > $bestLen) {
                $best = $slug;
                $bestLen = strlen($p);
            }
        }
        return $best;
    }

    public static function quickStart(string $role): array
    {
        return self::data()['roles'][$role] ?? ['intro' => '', 'steps' => []];
    }
}
