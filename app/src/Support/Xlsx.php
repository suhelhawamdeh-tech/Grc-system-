<?php
declare(strict_types=1);

namespace App\Support;

/** Minimal OOXML (.xlsx) writer: one sheet, bold frozen header row, auto-filter, column widths, right-to-left view for Arabic. No dependencies besides ext-zip. */
final class Xlsx
{
    public static function available(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    private static function col(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }
        return $s;
    }

    private static function esc(string $s): string
    {
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** @param iterable<array> $rows values may be int, float, string or null */
    public static function build(array $header, iterable $rows, string $sheetName = 'Data', bool $rtl = false): string
    {
        $data = [$header];
        foreach ($rows as $r) {
            $data[] = array_values($r);
        }
        $widths = [];
        $xml = '';
        foreach ($data as $ri => $row) {
            $xml .= '<row r="' . ($ri + 1) . '">';
            foreach ($row as $ci => $v) {
                $ref = self::col($ci) . ($ri + 1);
                $widths[$ci] = min(60, max($widths[$ci] ?? 8, mb_strlen((string)$v) + 2));
                $style = $ri === 0 ? ' s="1"' : '';
                if ($v === null || $v === '') {
                    $xml .= '<c r="' . $ref . '"' . $style . '/>';
                } elseif ((is_int($v) || is_float($v)) && $ri > 0) {
                    $xml .= '<c r="' . $ref . '"><v>' . $v . '</v></c>';
                } else {
                    $xml .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">' . self::esc((string)$v) . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        $cols = '';
        foreach ($widths as $i => $w) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        $last = self::col(max(0, count($header) - 1)) . count($data);
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"' . ($rtl ? ' rightToLeft="1"' : '') . '><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols>' . $cols . '</cols><sheetData>' . $xml . '</sheetData><autoFilter ref="A1:' . $last . '"/></worksheet>';
        $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0B2545"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf></cellXfs></styleSheet>';
        $name = self::esc(mb_substr(preg_replace('/[\[\]\*\?\/\\\\:]/u', ' ', $sheetName) ?: 'Data', 0, 31));
        $tmp = tempnam(sys_get_temp_dir(), 'xl');
        $z = new \ZipArchive();
        $z->open($tmp, \ZipArchive::OVERWRITE);
        $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $name . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $z->addFromString('xl/styles.xml', $styles);
        $z->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $z->close();
        $bin = (string)file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }
}
