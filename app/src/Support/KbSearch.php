<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;

/**
 * Search over the reference library: Arabic-aware normalisation and light stemming, a small Arabic/English term bridge,
 * and BM25 ranking over both language versions of every passage. Runs in PHP so it needs no search server.
 */
final class KbSearch
{
    private static ?array $index = null;

    private const STOP = ['في', 'من', 'على', 'الى', 'الي', 'عن', 'مع', 'او', 'ان', 'ما', 'هل', 'هي', 'هو', 'هذا', 'هذه', 'ذلك', 'التي', 'الذي', 'كيف', 'ماذا', 'متى', 'لماذا', 'كل', 'بين', 'عند', 'قد', 'لا', 'لم', 'ثم', 'اي', 'به', 'لها', 'له', 'تم', 'يتم', 'ذات',
        'the', 'a', 'an', 'of', 'to', 'in', 'on', 'for', 'and', 'or', 'is', 'are', 'be', 'by', 'with', 'what', 'how', 'when', 'which', 'who', 'does', 'do', 'should', 'must', 'can', 'that', 'this', 'it', 'as', 'at', 'from', 'our', 'we', 'i', 'my'];

    /** Arabic and English terms that mean the same thing, so a question in one language also matches the other. */
    private const BRIDGE = [
        'غسل' => 'laundering', 'غسيل' => 'laundering', 'اموال' => 'money', 'ارهاب' => 'terrorist', 'تمويل' => 'financing', 'عنايه' => 'diligence', 'واجبه' => 'due', 'مشدده' => 'enhanced', 'مبسطه' => 'simplified',
        'معرض' => 'politically', 'سياسيا' => 'pep', 'مشبوه' => 'suspicious', 'اشتباه' => 'suspicious', 'ابلاغ' => 'report', 'بلاغ' => 'report', 'عقوبات' => 'sanctions', 'تجميد' => 'freeze', 'مستفيد' => 'beneficial', 'حقيقي' => 'owner',
        'سجلات' => 'records', 'حفظ' => 'keeping', 'مراسل' => 'correspondent', 'مراسله' => 'correspondent', 'تحويل' => 'transfer', 'تحويلات' => 'transfers', 'برقيه' => 'wire', 'امتثال' => 'compliance', 'تدقيق' => 'audit',
        'مدقق' => 'auditor', 'ملاحظات' => 'findings', 'خطه' => 'plan', 'مخاطر' => 'risk', 'خطر' => 'risk', 'ضوابط' => 'controls', 'ضابط' => 'control', 'رقابه' => 'control', 'حوكمه' => 'governance', 'مجلس' => 'board', 'لجنه' => 'committee',
        'شرعيه' => 'sharia', 'شرعي' => 'sharia', 'ربا' => 'interest', 'فوائد' => 'interest', 'استمراريه' => 'continuity', 'اثر' => 'impact', 'استعاده' => 'recovery', 'مرونه' => 'resilience', 'تشغيليه' => 'operational', 'مؤشرات' => 'indicators',
        'عميل' => 'customer', 'عملاء' => 'customers', 'منتجات' => 'products', 'قنوات' => 'channels', 'دول' => 'countries', 'دوله' => 'country', 'جغرافيه' => 'geographic', 'تقييم' => 'assessment', 'مراجعه' => 'review', 'تدريب' => 'training',
        'استقلاليه' => 'independence', 'وحده' => 'unit', 'معلومات' => 'information', 'ماليه' => 'financial', 'مركزي' => 'central', 'مصرف' => 'bank', 'قانون' => 'law', 'تعميم' => 'circular', 'تعاميم' => 'circulars', 'امن' => 'security',
        'سيبراني' => 'cyber', 'تقنيه' => 'technology', 'جديده' => 'new', 'تقييمات' => 'evaluation', 'متبادل' => 'mutual', 'درجه' => 'score', 'تصنيف' => 'rating', 'اولويه' => 'priority', 'كامن' => 'inherent', 'متبقي' => 'residual',
    ];

    public static function reset(): void
    {
        self::$index = null;
    }

    public static function normalize(string $s): string
    {
        $s = mb_strtolower($s);
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $s) ?? $s; // diacritics, tatweel
        $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ى' => 'ي', 'ة' => 'ه', 'ؤ' => 'و', 'ئ' => 'ي',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
        return $s;
    }

    /** Light Arabic stemmer: strips the article and common prefixes/suffixes on longer words only. */
    private static function stem(string $w): string
    {
        if (!preg_match('/\p{Arabic}/u', $w)) {
            return strlen($w) > 4 ? preg_replace('/(ies|es|s|ing|ed)$/', '', $w) ?? $w : $w;
        }
        foreach (['وال', 'بال', 'كال', 'فال', 'لل', 'ال'] as $p) {
            if (mb_strlen($w) - mb_strlen($p) >= 3 && str_starts_with($w, $p)) {
                $w = mb_substr($w, mb_strlen($p));
                break;
            }
        }
        foreach (['ات', 'ون', 'ين', 'ها', 'هم', 'يه', 'ه'] as $suf) {
            if (mb_strlen($w) - mb_strlen($suf) >= 3 && str_ends_with($w, $suf)) {
                return mb_substr($w, 0, mb_strlen($w) - mb_strlen($suf));
            }
        }
        return $w;
    }

    /** @return list<string> */
    public static function tokens(string $s, bool $bridge = false): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', self::normalize($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $out = [];
        foreach ($words as $w) {
            if (mb_strlen($w) < 2 || in_array($w, self::STOP, true)) {
                continue;
            }
            if ($bridge) {
                $base = preg_replace('/^(وال|بال|كال|فال|لل|ال)/u', '', $w) ?? $w;
                if (isset(self::BRIDGE[$base])) {
                    $out[] = self::stem(self::BRIDGE[$base]);
                }
            }
            $out[] = self::stem($w);
        }
        return $out;
    }

    private static function index(): array
    {
        if (self::$index !== null) {
            return self::$index;
        }
        $docs = [];
        $df = [];
        $total = 0;
        foreach (Db::all("SELECT c.id, c.source_id, c.ref, c.heading_ar, c.heading_en, c.body_ar, c.body_en, s.code, s.title_ar, s.title_en, s.url, s.modules, s.restricted, s.authority, s.jurisdiction, s.category
            FROM kb_chunks c JOIN kb_sources s ON s.id = c.source_id WHERE s.status = 'active'") as $r) {
            $tf = [];
            foreach (self::tokens($r['heading_ar'] . ' ' . $r['heading_en'] . ' ' . $r['ref']) as $t) {
                $tf[$t] = ($tf[$t] ?? 0) + 2; // headings weigh double
            }
            foreach (self::tokens($r['body_ar'] . ' ' . $r['body_en'] . ' ' . $r['title_ar'] . ' ' . $r['title_en']) as $t) {
                $tf[$t] = ($tf[$t] ?? 0) + 1;
            }
            $len = array_sum($tf);
            foreach (array_keys($tf) as $t) {
                $df[$t] = ($df[$t] ?? 0) + 1;
            }
            $total += $len;
            $docs[(int)$r['id']] = ['row' => $r, 'tf' => $tf, 'len' => $len];
        }
        self::$index = ['docs' => $docs, 'df' => $df, 'avg' => $docs ? $total / count($docs) : 1, 'n' => count($docs)];
        return self::$index;
    }

    /**
     * Top passages for a question. Restricted sources are only searched for users allowed to see them;
     * passages from sources tagged with $module get a small boost.
     * @return list<array> passage rows with 'score'
     */
    public static function search(string $q, int $limit = 6, bool $restrictedOk = false, ?string $module = null): array
    {
        $ix = self::index();
        $terms = array_unique(self::tokens($q, true));
        if (!$terms || !$ix['n']) {
            return [];
        }
        $k1 = 1.5;
        $b = 0.75;
        $scores = [];
        foreach ($ix['docs'] as $id => $d) {
            if (!$restrictedOk && (int)$d['row']['restricted'] === 1) {
                continue;
            }
            $s = 0.0;
            foreach ($terms as $t) {
                $f = $d['tf'][$t] ?? 0;
                if (!$f) {
                    continue;
                }
                $idf = log(1 + ($ix['n'] - $ix['df'][$t] + 0.5) / ($ix['df'][$t] + 0.5));
                $s += $idf * ($f * ($k1 + 1)) / ($f + $k1 * (1 - $b + $b * $d['len'] / $ix['avg']));
            }
            if ($s <= 0) {
                continue;
            }
            if ($module !== null && in_array($module, explode(',', (string)$d['row']['modules']), true)) {
                $s *= 1.2;
            }
            // Laws, regulations and standards come before the platform's own method notes on equal relevance.
            if ($d['row']['category'] === 'platform') {
                $s *= 0.85;
            }
            $scores[$id] = $s;
        }
        arsort($scores);
        $out = [];
        $max = $scores ? reset($scores) : 0;
        foreach (array_slice($scores, 0, $limit, true) as $id => $s) {
            // Drop weak matches far below the best one.
            if ($s < 0.25 * $max) {
                break;
            }
            $out[] = $ix['docs'][$id]['row'] + ['score' => round($s, 3)];
        }
        return $out;
    }
}
