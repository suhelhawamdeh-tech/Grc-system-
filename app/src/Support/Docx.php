<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Minimal Word (.docx) writer: headings, paragraphs, bullet lists, tables with a shaded header row, key/value tables,
 * page breaks, and a header/footer carrying the organisation, the classification and page numbers.
 * Right-to-left (Arabic) or left-to-right documents. No dependencies besides ext-zip.
 */
final class Docx
{
    private string $body = '';
    private const NAVY = '0B2545';
    private const GOLD = 'C9A227';

    public function __construct(private bool $rtl = true, private string $title = '', private string $headerText = '', private string $footerText = '', private string $font = 'Arial') {}

    public static function available(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    private static function esc(string $s): string
    {
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function rPr(array $o = []): string
    {
        $x = '<w:rPr><w:rFonts w:ascii="' . $this->font . '" w:hAnsi="' . $this->font . '" w:cs="' . $this->font . '"/>';
        if (!empty($o['bold'])) {
            $x .= '<w:b/><w:bCs/>';
        }
        if (!empty($o['italic'])) {
            $x .= '<w:i/><w:iCs/>';
        }
        if (!empty($o['color'])) {
            $x .= '<w:color w:val="' . $o['color'] . '"/>';
        }
        $sz = (int)(($o['size'] ?? 11) * 2);
        $x .= '<w:sz w:val="' . $sz . '"/><w:szCs w:val="' . $sz . '"/>';
        if ($this->rtl) {
            $x .= '<w:rtl/>';
        }
        return $x . '</w:rPr>';
    }

    private function pPr(array $o = []): string
    {
        $x = '<w:pPr>';
        if (!empty($o['style'])) {
            $x .= '<w:pStyle w:val="' . $o['style'] . '"/>';
        }
        if (!empty($o['numbered'])) {
            $x .= '<w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr>';
        }
        if (!empty($o['keepNext'])) {
            $x .= '<w:keepNext/>';
        }
        if (!empty($o['shade'])) {
            $x .= '<w:shd w:val="clear" w:color="auto" w:fill="' . $o['shade'] . '"/>';
        }
        $x .= '<w:spacing w:before="' . (int)($o['before'] ?? 0) . '" w:after="' . (int)($o['after'] ?? 120) . '"/>';
        if ($this->rtl) {
            $x .= '<w:bidi/>';
        }
        // Start alignment is the paragraph default (right in Arabic, left in English), so it is not written.
        $align = $o['align'] ?? 'start';
        if ($align === 'center' || $align === 'both') {
            $x .= '<w:jc w:val="' . $align . '"/>';
        }
        return $x . '</w:pPr>';
    }

    /** Runs: text, with "\n" turned into line breaks. */
    private function runs(string $text, array $o = []): string
    {
        $out = '';
        foreach (explode("\n", $text) as $i => $line) {
            $out .= '<w:r>' . $this->rPr($o) . ($i > 0 ? '<w:br/>' : '') . '<w:t xml:space="preserve">' . self::esc($line) . '</w:t></w:r>';
        }
        return $out;
    }

    public function heading(string $text, int $level = 1): self
    {
        $size = [1 => 16, 2 => 13, 3 => 12][$level] ?? 12;
        $this->body .= '<w:p>' . $this->pPr(['style' => 'Heading' . $level, 'keepNext' => true, 'before' => $level === 1 ? 240 : 160, 'after' => 100]) . $this->runs($text, ['bold' => true, 'size' => $size, 'color' => self::NAVY]) . '</w:p>';
        return $this;
    }

    public function para(string $text, array $o = []): self
    {
        $this->body .= '<w:p>' . $this->pPr($o) . $this->runs($text, $o) . '</w:p>';
        return $this;
    }

    /** A highlighted box (one-cell table) for key messages or reading notes. */
    public function box(string $title, array $lines): self
    {
        $cell = '<w:p>' . $this->pPr(['after' => 60]) . $this->runs($title, ['bold' => true, 'color' => self::NAVY]) . '</w:p>';
        foreach ($lines as $l) {
            $cell .= '<w:p>' . $this->pPr(['after' => 60]) . $this->runs($l, ['size' => 10.5]) . '</w:p>';
        }
        $this->body .= '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/>' . ($this->rtl ? '<w:bidiVisual/>' : '') . '<w:tblBorders><w:top w:val="single" w:sz="4" w:color="' . self::GOLD . '"/><w:bottom w:val="single" w:sz="4" w:color="' . self::GOLD . '"/>'
            . '<w:' . ($this->rtl ? 'right' : 'left') . ' w:val="single" w:sz="24" w:color="' . self::GOLD . '"/><w:' . ($this->rtl ? 'left' : 'right') . ' w:val="single" w:sz="4" w:color="' . self::GOLD . '"/></w:tblBorders></w:tblPr><w:tblGrid><w:gridCol w:w="9000"/></w:tblGrid>'
            . '<w:tr><w:tc><w:tcPr><w:tcW w:w="5000" w:type="pct"/><w:shd w:val="clear" w:color="auto" w:fill="F8F3E3"/></w:tcPr>' . $cell . '</w:tc></w:tr></w:tbl>';
        return $this->para('', ['after' => 60]);
    }

    public function bullets(array $items): self
    {
        foreach ($items as $it) {
            $this->body .= '<w:p>' . $this->pPr(['numbered' => true, 'after' => 60]) . $this->runs((string)$it) . '</w:p>';
        }
        return $this;
    }

    /**
     * @param list<string> $header
     * @param list<list<string|int|float|null>> $rows
     * @param list<int>|null $widths relative column widths
     */
    public function table(array $header, array $rows, ?array $widths = null, array $o = []): self
    {
        $n = max(count($header), $rows ? max(array_map('count', $rows)) : 0);
        if ($n === 0) {
            return $this;
        }
        $widths = $widths && count($widths) === $n ? $widths : array_fill(0, $n, 1);
        $sum = array_sum($widths);
        $tw = array_map(fn($w) => (int)round(9000 * $w / $sum), $widths);
        $x = '<w:tbl><w:tblPr><w:tblStyle w:val="TableGrid"/><w:tblW w:w="5000" w:type="pct"/>' . ($this->rtl ? '<w:bidiVisual/>' : '') . '<w:tblLayout w:type="fixed"/>'
            . '<w:tblCellMar><w:top w:w="40" w:type="dxa"/><w:bottom w:w="40" w:type="dxa"/><w:left w:w="80" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>';
        foreach ($tw as $w) {
            $x .= '<w:gridCol w:w="' . $w . '"/>';
        }
        $x .= '</w:tblGrid>';
        $cell = function (string $v, int $i, bool $head, ?string $fill = null) use ($tw): string {
            $p = '<w:p>' . $this->pPr(['after' => 0]) . $this->runs($v, ['bold' => $head, 'size' => 9.5, 'color' => $head ? 'FFFFFF' : null]) . '</w:p>';
            return '<w:tc><w:tcPr><w:tcW w:w="' . $tw[$i] . '" w:type="dxa"/>' . ($fill ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/>' : '') . '</w:tcPr>' . $p . '</w:tc>';
        };
        if ($header) {
            $x .= '<w:tr><w:trPr><w:tblHeader/><w:cantSplit/></w:trPr>';
            foreach (array_pad($header, $n, '') as $i => $h) {
                $x .= $cell((string)$h, $i, true, self::NAVY);
            }
            $x .= '</w:tr>';
        }
        foreach ($rows as $ri => $r) {
            $fill = !empty($o['totalLast']) && $ri === count($rows) - 1 ? 'EFE6C8' : ($ri % 2 ? 'F4F6F9' : null);
            $x .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';
            foreach (array_pad(array_values($r), $n, '') as $i => $v) {
                $x .= $cell($v === null ? '' : (string)$v, $i, false, $fill);
            }
            $x .= '</w:tr>';
        }
        $this->body .= $x . '</w:tbl>';
        return $this->para('', ['after' => 80]);
    }

    /** Two-column label/value table (document control blocks, notices). */
    public function kv(array $pairs): self
    {
        $rows = [];
        foreach ($pairs as $k => $v) {
            $rows[] = [(string)$k, (string)$v];
        }
        return $this->table([], $rows, [1, 2]);
    }

    public function pageBreak(): self
    {
        $this->body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
        return $this;
    }

    private function hdrFtr(string $tag, string $text, bool $pageNo): string
    {
        $p = '<w:p>' . $this->pPr(['align' => 'center', 'after' => 0]) . $this->runs($text, ['size' => 8.5, 'color' => '5A6473']);
        if ($pageNo) {
            $p .= '<w:r>' . $this->rPr(['size' => 8.5]) . '<w:t xml:space="preserve">  —  </w:t></w:r><w:r>' . $this->rPr(['size' => 8.5]) . '<w:fldChar w:fldCharType="begin"/></w:r><w:r>' . $this->rPr(['size' => 8.5])
                . '<w:instrText xml:space="preserve"> PAGE </w:instrText></w:r><w:r>' . $this->rPr(['size' => 8.5]) . '<w:fldChar w:fldCharType="separate"/></w:r><w:r>' . $this->rPr(['size' => 8.5]) . '<w:t>1</w:t></w:r><w:r>' . $this->rPr(['size' => 8.5]) . '<w:fldChar w:fldCharType="end"/></w:r>';
        }
        $p .= '</w:p>';
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:' . $tag . ' xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' . $p . '</w:' . $tag . '>';
    }

    /** @return string the .docx file contents */
    public function build(): string
    {
        $W = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"';
        $doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document ' . $W . '><w:body>' . $this->body
            . '<w:sectPr><w:headerReference w:type="default" r:id="rIdH"/><w:footerReference w:type="default" r:id="rIdF"/><w:pgSz w:w="11906" w:h="16838"/>'
            . '<w:pgMar w:top="1300" w:right="1134" w:bottom="1200" w:left="1134" w:header="567" w:footer="567" w:gutter="0"/>' . ($this->rtl ? '<w:bidi/>' : '') . '</w:sectPr></w:body></w:document>';
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles ' . $W . '><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="' . $this->font . '" w:hAnsi="' . $this->font . '" w:cs="' . $this->font . '"/>'
            . '<w:sz w:val="22"/><w:szCs w:val="22"/><w:lang w:val="' . ($this->rtl ? 'ar-LY' : 'en-GB') . '" w:bidi="ar-LY"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:qFormat/></w:style>';
        foreach ([1, 2, 3] as $l) {
            $styles .= '<w:style w:type="paragraph" w:styleId="Heading' . $l . '"><w:name w:val="heading ' . $l . '"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:qFormat/><w:pPr><w:keepNext/><w:outlineLvl w:val="' . ($l - 1) . '"/></w:pPr><w:rPr><w:b/><w:bCs/><w:color w:val="' . self::NAVY . '"/></w:rPr></w:style>';
        }
        $styles .= '<w:style w:type="table" w:styleId="TableGrid"><w:name w:val="Table Grid"/><w:tblPr><w:tblBorders><w:top w:val="single" w:sz="4" w:color="C9CED6"/><w:left w:val="single" w:sz="4" w:color="C9CED6"/><w:bottom w:val="single" w:sz="4" w:color="C9CED6"/><w:right w:val="single" w:sz="4" w:color="C9CED6"/><w:insideH w:val="single" w:sz="4" w:color="C9CED6"/><w:insideV w:val="single" w:sz="4" w:color="C9CED6"/></w:tblBorders></w:tblPr></w:style></w:styles>';
        $numbering = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:numbering ' . $W . '><w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/><w:lvlJc w:val="left"/><w:pPr><w:ind w:left="420" w:hanging="300"/></w:pPr></w:lvl></w:abstractNum><w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num></w:numbering>';
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $core = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . self::esc($this->title) . '</dc:title><dc:creator>GRC platform</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified></cp:coreProperties>';
        $tmp = tempnam(sys_get_temp_dir(), 'dx');
        $z = new \ZipArchive();
        $z->open($tmp, \ZipArchive::OVERWRITE);
        $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '<Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/><Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
            . '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/></Types>');
        $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/></Relationships>');
        $z->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdS" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rIdN" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/><Relationship Id="rIdH" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>'
            . '<Relationship Id="rIdF" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/></Relationships>');
        $z->addFromString('word/document.xml', $doc);
        $z->addFromString('word/styles.xml', $styles);
        $z->addFromString('word/numbering.xml', $numbering);
        $z->addFromString('word/header1.xml', $this->hdrFtr('hdr', $this->headerText, false));
        $z->addFromString('word/footer1.xml', $this->hdrFtr('ftr', $this->footerText, true));
        $z->addFromString('docProps/core.xml', $core);
        $z->close();
        $bin = (string)file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }

    /** Send as a download and stop. */
    public function send(string $filename): never
    {
        $bin = $this->build();
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"');
        header('Content-Length: ' . strlen($bin));
        header('X-Content-Type-Options: nosniff');
        echo $bin;
        exit;
    }
}
