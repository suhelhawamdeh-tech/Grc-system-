<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Reads a Word (.docx) file without external libraries: paragraphs with their style, and tables as rows of cells.
 * Also recognises the document-control table used in the bank's policies ("رمز الوثيقة", "الإصدار", "التصنيف" ...).
 */
final class DocxReader
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** Control-table labels (Arabic and English) mapped to metadata keys. */
    private const LABELS = ['رمز الوثيقة' => 'code', 'document code' => 'code', 'نوع الوثيقة' => 'type', 'document type' => 'type', 'الإصدار' => 'version', 'version' => 'version',
        'الحالة' => 'status', 'status' => 'status', 'التصنيف' => 'classification', 'classification' => 'classification', 'الإدارة المالكة' => 'owner', 'owner' => 'owner',
        'جهة الاعتماد' => 'approval_body', 'approving body' => 'approval_body', 'تاريخ الاعتماد' => 'approval_date', 'تاريخ النفاذ' => 'effective_date', 'المراجعة القادمة' => 'next_review',
        'السياسة المرجعية' => 'parent', 'المرجع الرقابي' => 'regulatory_ref'];

    /**
     * @return array{blocks:list<array>,text:string,meta:array<string,string>}
     * @throws \RuntimeException when the file is not a readable .docx
     */
    public static function read(string $path): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \RuntimeException('zip extension missing');
        }
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('not a docx file');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false || strlen($xml) > 30_000_000) {
            throw new \RuntimeException('not a docx file');
        }
        $dom = new \DOMDocument();
        // LIBXML_NONET: never fetch external resources; entities are not substituted.
        if (!@$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \RuntimeException('unreadable document');
        }
        $body = $dom->getElementsByTagNameNS(self::W, 'body')->item(0);
        $blocks = [];
        foreach ($body ? $body->childNodes : [] as $n) {
            if (!$n instanceof \DOMElement) {
                continue;
            }
            if ($n->localName === 'p') {
                $t = self::paraText($n);
                if ($t !== '') {
                    $blocks[] = ['type' => 'p', 'style' => self::style($n), 'text' => $t];
                }
            } elseif ($n->localName === 'tbl') {
                $rows = self::table($n);
                if ($rows) {
                    $blocks[] = ['type' => 'table', 'rows' => $rows];
                }
            }
        }
        return ['blocks' => $blocks, 'text' => self::toText($blocks), 'meta' => self::meta($blocks)];
    }

    private static function style(\DOMElement $p): string
    {
        foreach ($p->getElementsByTagNameNS(self::W, 'pStyle') as $s) {
            return (string)$s->getAttributeNS(self::W, 'val');
        }
        return '';
    }

    private static function paraText(\DOMElement $p): string
    {
        $t = '';
        foreach ($p->getElementsByTagNameNS(self::W, '*') as $el) {
            if ($el->localName === 't') {
                $t .= $el->textContent;
            } elseif ($el->localName === 'tab') {
                $t .= ' ';
            } elseif ($el->localName === 'br' || $el->localName === 'cr') {
                $t .= "\n";
            }
        }
        return trim(preg_replace('/[ \x{00A0}]+/u', ' ', $t) ?? $t);
    }

    /** @return list<list<string>> rows of cells; paragraphs inside a cell are joined with a new line. */
    private static function table(\DOMElement $tbl): array
    {
        $rows = [];
        foreach ($tbl->childNodes as $tr) {
            if (!$tr instanceof \DOMElement || $tr->localName !== 'tr') {
                continue;
            }
            $cells = [];
            foreach ($tr->childNodes as $tc) {
                if (!$tc instanceof \DOMElement || $tc->localName !== 'tc') {
                    continue;
                }
                $parts = [];
                foreach ($tc->getElementsByTagNameNS(self::W, 'p') as $p) {
                    $x = self::paraText($p);
                    if ($x !== '') {
                        $parts[] = $x;
                    }
                }
                $cells[] = implode("\n", $parts);
            }
            if (array_filter($cells, fn($c) => $c !== '')) {
                $rows[] = $cells;
            }
        }
        return $rows;
    }

    /** Plain text: headings and paragraphs separated by blank lines; table rows as "cell | cell". */
    public static function toText(array $blocks): string
    {
        $out = [];
        foreach ($blocks as $b) {
            if ($b['type'] === 'p') {
                $out[] = $b['text'];
            } else {
                $lines = [];
                foreach ($b['rows'] as $r) {
                    $lines[] = implode(' | ', array_map(fn($c) => str_replace("\n", ' / ', $c), array_values(array_unique(array_filter($r, fn($c) => $c !== '')))));
                }
                $out[] = implode("\n", $lines);
            }
        }
        return implode("\n\n", $out);
    }

    /** Document-control metadata plus titles taken from the cover table ("type / Arabic title / English title"). */
    private static function meta(array $blocks): array
    {
        $m = [];
        foreach ($blocks as $i => $b) {
            if ($b['type'] !== 'table') {
                continue;
            }
            if (!isset($m['title_ar']) && $i < 3 && count($b['rows']) === 1) {
                $lines = array_values(array_filter(array_map('trim', explode("\n", $b['rows'][0][0]))));
                if (count($lines) >= 2) {
                    $m['doc_label'] = $lines[0];
                    $m['title_ar'] = $lines[1];
                    if (isset($lines[2]) && !preg_match('/\p{Arabic}/u', $lines[2])) {
                        $m['title_en'] = $lines[2];
                    }
                }
            }
            foreach ($b['rows'] as $r) {
                if (count($r) >= 2) {
                    $k = self::LABELS[mb_strtolower(trim($r[0]))] ?? null;
                    if ($k !== null && !isset($m[$k]) && trim($r[1]) !== '' && trim($r[1]) !== '—') {
                        $m[$k] = trim(explode("\n", $r[1])[0]);
                    }
                } elseif (count($r) === 1 && !isset($m['code']) && preg_match('/الرمز\s*\n?\s*((?:[A-Z]{2,5}-){1,3}[A-Z]{1,5}-\d{3})/u', $r[0], $mm)) {
                    $m['code'] = $mm[1];
                }
            }
        }
        if (!isset($m['code'])) {
            foreach ($blocks as $b) {
                $x = $b['type'] === 'p' ? $b['text'] : implode(' ', array_merge(...$b['rows']));
                if (preg_match('/\b(?:[A-Z]{2,5}-){1,3}[A-Z]{1,5}-\d{3}\b/', $x, $mm)) {
                    $m['code'] = $mm[0];
                    break;
                }
            }
        }
        if (isset($m['parent'])) {
            $m['parent'] = Framework::codesIn($m['parent'])[0] ?? '';
        }
        if (!isset($m['title_ar'])) {
            foreach ($blocks as $b) {
                if ($b['type'] === 'p' && mb_strlen($b['text']) > 8) {
                    $m['title_ar'] = mb_substr($b['text'], 0, 250);
                    break;
                }
            }
        }
        return $m;
    }

    /** Map the control-table words to the register's values. */
    public static function normalise(array $m): array
    {
        // The cover label ("سياسة", "دليل إجراءات", "ميثاق" ...) decides; the title is used only when there is no label.
        $label = ($m['doc_label'] ?? '') . ' ' . ($m['type'] ?? '');
        if (trim($label) === '') {
            $label = $m['title_ar'] ?? '';
        }
        $type = match (true) {
            str_contains($label, 'ميثاق') => 'charter',
            str_starts_with(trim($m['title_ar'] ?? ''), 'إطار') => 'framework',
            str_contains($label, 'دليل') => 'manual',
            str_contains($label, 'أداة') => 'tool',
            str_contains($label, 'خطة') => 'plan',
            str_contains($label, 'تقرير') || str_contains($label, 'مذكرة') => 'report',
            str_contains($label, 'نموذج') => 'template',
            str_contains($label, 'منهجية') => 'methodology',
            default => 'policy',
        };
        $cls = $m['classification'] ?? '';
        $classification = match (true) {
            str_contains($cls, 'للغاية') || str_contains(mb_strtolower($cls), 'secret') && str_contains(mb_strtolower($cls), 'top') => 'secret',
            str_contains($cls, 'سري') || str_contains(mb_strtolower($cls), 'confidential') => 'confidential',
            str_contains($cls, 'عام') || str_contains(mb_strtolower($cls), 'public') => 'public',
            default => 'internal',
        };
        $st = $m['status'] ?? '';
        $status = (str_contains($st, 'معتمد') || str_contains($st, 'نافذ') || str_contains(mb_strtolower($st), 'approved')) ? 'approved' : 'draft';
        $code = $m['code'] ?? '';
        return ['code' => $code, 'title' => mb_substr($m['title_ar'] ?? '', 0, 250), 'title_en' => mb_substr($m['title_en'] ?? '', 0, 250), 'category' => $type,
            'doc_level' => Framework::defaultLevel($type, $code), 'classification' => $classification, 'status' => $status, 'version' => mb_substr(trim(preg_split('/\s+[—-]\s+/u', $m['version'] ?? '1.0')[0]), 0, 30) ?: '1.0',
            'approval_body' => mb_substr($m['approval_body'] ?? '', 0, 150), 'parent' => $m['parent'] ?? '', 'owner' => $m['owner'] ?? ''];
    }
}
