<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;
use App\Core\Settings;

/**
 * The reference library: built-in sources shipped with the code (app/kb/library.php) plus documents the bank adds.
 * Built-in sources are re-synced whenever the shipped version changes; bank-added sources are never touched by a sync.
 */
final class KbLibrary
{
    public const CATEGORIES = ['aml', 'compliance', 'risk', 'audit', 'sharia', 'governance', 'continuity', 'security', 'platform', 'bank'];
    public const JURISDICTIONS = ['international', 'regional', 'libya', 'bank'];
    public const AUTHORITIES = ['law', 'regulation', 'standard', 'guidance', 'list', 'internal'];
    public const MODULES = ['aml', 'compliance', 'risk', 'controls', 'kri', 'incidents', 'rcsa', 'ia', 'sharia', 'bia', 'governance'];

    private static function file(): array
    {
        return require APP_ROOT . '/app/kb/library.php';
    }

    /** Sync built-in sources if the shipped library version differs from the stored one. @return bool synced */
    public static function ensure(): bool
    {
        $lib = self::file();
        if (Settings::get('kb_library_version') === $lib['version']) {
            return false;
        }
        self::sync($lib);
        return true;
    }

    public static function sync(?array $lib = null): array
    {
        $lib ??= self::file();
        $now = date('Y-m-d H:i:s');
        $codes = [];
        $n = Db::tx(function () use ($lib, $now, &$codes) {
            $n = 0;
            foreach ($lib['sources'] as $s) {
                $codes[] = $s['code'];
                $d = ['title_ar' => $s['title_ar'], 'title_en' => $s['title_en'], 'issuer' => $s['issuer'], 'category' => $s['category'], 'jurisdiction' => $s['jurisdiction'], 'authority' => $s['authority'],
                    'pub_year' => $s['year'], 'url' => $s['url'], 'modules' => $s['modules'], 'note_ar' => $s['note_ar'], 'note_en' => $s['note_en'], 'is_builtin' => 1, 'restricted' => 0, 'status' => 'active', 'updated_at' => $now];
                $id = (int)Db::val('SELECT id FROM kb_sources WHERE code = ?', [$s['code']]);
                if ($id) {
                    Db::update('kb_sources', $d, 'id = ?', [$id]);
                    Db::exec('DELETE FROM kb_chunks WHERE source_id = ?', [$id]);
                } else {
                    $id = Db::insert('kb_sources', $d + ['code' => $s['code'], 'created_at' => $now]);
                }
                foreach ($s['chunks'] as $i => [$ref, $hAr, $hEn, $ar, $en]) {
                    Db::insert('kb_chunks', ['source_id' => $id, 'ref' => $ref, 'heading_ar' => $hAr, 'heading_en' => $hEn, 'body_ar' => $ar, 'body_en' => $en, 'sort_order' => $i, 'created_at' => $now]);
                    $n++;
                }
            }
            if ($codes) {
                Db::exec('UPDATE kb_sources SET status = \'retired\', updated_at = ? WHERE is_builtin = 1 AND code NOT IN (' . implode(',', array_fill(0, count($codes), '?')) . ')', array_merge([$now], $codes));
            }
            return $n;
        });
        Settings::set('kb_library_version', $lib['version']);
        KbSearch::reset();
        return ['sources' => count($codes), 'passages' => $n, 'version' => $lib['version']];
    }

    /**
     * Split pasted text into passages of about 1,200 characters on paragraph boundaries.
     * @return list<array{heading:string,body:string}>
     */
    public static function chunkText(string $title, string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", trim($text));
        $paras = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/u', $text) ?: []), static fn($p) => $p !== ''));
        $out = [];
        $buf = '';
        $head = '';
        foreach ($paras as $p) {
            $isHeading = mb_strlen($p) <= 90 && !str_contains($p, "\n") && !preg_match('/[.!؟?:،]$/u', $p);
            if ($isHeading && $buf === '') {
                // Consecutive headings (e.g. chapter then article) are joined.
                $head = $head !== '' ? $head . ' — ' . $p : $p;
                continue;
            }
            if ($buf !== '' && mb_strlen($buf) + mb_strlen($p) > 1200) {
                $out[] = ['heading' => $head !== '' ? $head : $title, 'body' => $buf];
                $buf = '';
                $head = '';
            }
            if ($isHeading) {
                if ($buf !== '') {
                    $out[] = ['heading' => $head !== '' ? $head : $title, 'body' => $buf];
                    $buf = '';
                }
                $head = $p;
                continue;
            }
            $buf = $buf === '' ? $p : $buf . "\n\n" . $p;
        }
        if ($buf !== '') {
            $out[] = ['heading' => $head !== '' ? $head : $title, 'body' => $buf];
        } elseif ($head !== '') {
            // Trailing short lines with no paragraph after them are text, not a heading: nothing is dropped.
            $out[] = ['heading' => $title, 'body' => $head];
        }
        // A single very long paragraph is split on sentence boundaries into several passages (nothing is dropped).
        $split = [];
        foreach ($out as $c) {
            if (mb_strlen($c['body']) <= 1500) {
                $split[] = $c;
                continue;
            }
            $part = '';
            foreach (preg_split('/(?<=[.!?؟؛])\s+/u', $c['body']) ?: [$c['body']] as $sentence) {
                while (mb_strlen($sentence) > 1500) {
                    $split[] = ['heading' => $c['heading'], 'body' => trim($part . ' ' . mb_substr($sentence, 0, 1500))];
                    $part = '';
                    $sentence = mb_substr($sentence, 1500);
                }
                if ($part !== '' && mb_strlen($part) + mb_strlen($sentence) > 1200) {
                    $split[] = ['heading' => $c['heading'], 'body' => $part];
                    $part = '';
                }
                $part = $part === '' ? $sentence : $part . ' ' . $sentence;
            }
            if (trim($part) !== '') {
                $split[] = ['heading' => $c['heading'], 'body' => trim($part)];
            }
        }
        // Number only headings shared by several passages, e.g. "Scope (1)", "Scope (2)".
        $count = array_count_values(array_column($split, 'heading'));
        $seen = [];
        foreach ($split as &$c) {
            if ($count[$c['heading']] > 1) {
                $seen[$c['heading']] = ($seen[$c['heading']] ?? 0) + 1;
                $c['heading'] .= ' (' . $seen[$c['heading']] . ')';
            }
        }
        unset($c);
        return $split;
    }

    public static function isArabic(string $s): bool
    {
        return (bool)preg_match('/\p{Arabic}/u', $s);
    }

    /** Store a bank document as a library source with its passages. */
    public static function addDocument(array $meta, string $text, int $userId, ?int $circularId = null): int
    {
        $now = date('Y-m-d H:i:s');
        return Db::tx(function () use ($meta, $text, $userId, $circularId, $now) {
            $title = $meta['title_ar'] !== '' ? $meta['title_ar'] : $meta['title_en'];
            $id = Db::insert('kb_sources', ['code' => 'TMP-' . bin2hex(random_bytes(6)), 'title_ar' => $meta['title_ar'] !== '' ? $meta['title_ar'] : $meta['title_en'], 'title_en' => $meta['title_en'] !== '' ? $meta['title_en'] : $meta['title_ar'],
                'issuer' => $meta['issuer'], 'category' => $meta['category'], 'jurisdiction' => $meta['jurisdiction'], 'authority' => $meta['authority'], 'pub_year' => $meta['year'], 'url' => $meta['url'], 'modules' => $meta['modules'],
                'note_ar' => $meta['note'] ?: null, 'note_en' => $meta['note'] ?: null, 'is_builtin' => 0, 'restricted' => $meta['restricted'], 'status' => 'active', 'circular_id' => $circularId, 'created_by' => $userId, 'created_at' => $now, 'updated_at' => $now]);
            Db::update('kb_sources', ['code' => sprintf('BANK-%04d', $id)], 'id = ?', [$id]);
            foreach (self::chunkText($title, $text) as $i => $c) {
                $ar = self::isArabic($c['body']);
                Db::insert('kb_chunks', ['source_id' => $id, 'ref' => null, 'heading_ar' => mb_substr($c['heading'], 0, 250), 'heading_en' => mb_substr($c['heading'], 0, 250),
                    'body_ar' => $ar ? $c['body'] : '', 'body_en' => $ar ? '' : $c['body'], 'sort_order' => $i, 'created_at' => $now]);
            }
            KbSearch::reset();
            return $id;
        });
    }

    /** Active sources tagged for a module (for the "related references" panels). */
    public static function forModule(string $module, bool $restrictedOk, int $limit = 8): array
    {
        $rows = Db::all("SELECT id, code, title_ar, title_en, issuer, authority, jurisdiction, url, pub_year FROM kb_sources WHERE status = 'active' AND FIND_IN_SET(?, modules) > 0" . ($restrictedOk ? '' : ' AND restricted = 0') . "
            ORDER BY FIELD(jurisdiction, 'libya', 'bank', 'regional', 'international'), FIELD(authority, 'law', 'regulation', 'standard', 'guidance', 'list', 'internal'), code LIMIT ?", [$module, $limit]);
        return $rows;
    }
}
