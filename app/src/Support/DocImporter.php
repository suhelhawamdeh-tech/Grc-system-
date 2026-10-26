<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Db;
use App\Core\Settings;

/**
 * Imports a bank document (.docx) into the document register: reads its control table, creates or updates the
 * register entry, records a new version when the version number changes, keeps the file, links parent documents
 * and indexes the text in the reference library (restricted when the document is confidential or secret).
 */
final class DocImporter
{
    public const MAX_BYTES = 10 * 1024 * 1024;

    /** Section of the risk function from the document code, e.g. RSK-CRD-... -> credit. */
    private const CODE_SECTIONS = ['CRD' => 'credit', 'MKT' => 'market', 'OPR' => 'operational', 'BCM' => 'operational', 'BCMS' => 'operational', 'ISR' => 'infosec', 'BSL' => 'basel', 'GOV' => 'office'];

    /** Platform modules whose "related references" panels show documents of each section. */
    private const SECTION_MODULES = ['credit' => 'risk,kri', 'market' => 'risk,kri', 'operational' => 'risk,incidents,rcsa,controls,bia', 'infosec' => 'risk,controls,incidents', 'basel' => 'risk,governance', 'office' => 'governance,risk'];

    public static function section(string $code): ?string
    {
        foreach (explode('-', $code) as $part) {
            if (isset(self::CODE_SECTIONS[$part])) {
                return self::CODE_SECTIONS[$part];
            }
        }
        return null;
    }

    /** @return string|null error key when the file cannot be imported */
    public static function check(string $path, string $name): ?string
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ['docx', 'xlsx', 'pdf'], true)) {
            return 'docs.err_type';
        }
        $size = @filesize($path);
        if (!$size || $size > self::MAX_BYTES) {
            return 'upload.too_big';
        }
        if ($ext === 'pdf') {
            return (string)file_get_contents($path, false, null, 0, 5) === '%PDF-' ? null : 'upload.bad_type';
        }
        $z = new \ZipArchive();
        if ($z->open($path) !== true) {
            return 'upload.bad_type';
        }
        $ok = $z->locateName('[Content_Types].xml') !== false && $z->locateName($ext === 'docx' ? 'word/document.xml' : 'xl/workbook.xml') !== false;
        $z->close();
        return $ok ? null : 'upload.bad_type';
    }

    /** Text and metadata of any supported file: Word documents are read fully; for Excel tools and PDF plans the code and title come from the file name. */
    private static function readAny(string $path, string $name): array
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === 'docx') {
            $doc = DocxReader::read($path);
            return [$doc['text'], DocxReader::normalise($doc['meta'])];
        }
        $base = pathinfo($name, PATHINFO_FILENAME);
        $code = Framework::codesIn(strtoupper($base))[0] ?? '';
        $title = trim(preg_replace('/[_\s]+/u', ' ', str_ireplace($code, '', $base)) ?? '');
        $text = $ext === 'xlsx' ? self::xlsxText($path) : '';
        $type = str_contains($code, '-TL-') ? 'tool' : (str_contains($code, '-PLN-') ? 'plan' : ($ext === 'xlsx' ? 'tool' : 'plan'));
        return [$text, ['code' => $code, 'title' => mb_substr($title !== '' ? $title : $base, 0, 250), 'title_en' => '', 'category' => $type, 'doc_level' => Framework::defaultLevel($type, $code),
            'classification' => 'internal', 'status' => 'draft', 'version' => '1.0', 'approval_body' => '', 'parent' => '', 'owner' => '']];
    }

    /** Labels and text cells of a workbook (shared strings), one per line. */
    private static function xlsxText(string $path): string
    {
        $z = new \ZipArchive();
        if ($z->open($path) !== true) {
            return '';
        }
        $xml = $z->getFromName('xl/sharedStrings.xml');
        $z->close();
        if ($xml === false || strlen($xml) > 20_000_000) {
            return '';
        }
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            return '';
        }
        $lines = [];
        foreach ($dom->getElementsByTagName('si') as $si) {
            $t = trim($si->textContent);
            if ($t !== '' && !is_numeric($t)) {
                $lines[] = $t;
            }
        }
        return implode("\n\n", array_values(array_unique($lines)));
    }

    /**
     * @param bool $uploaded true for a browser upload (moved), false for a server file (copied)
     * @return array{id:int,code:string,action:string,version:string,refs:list<string>,passages:int}
     */
    public static function import(string $path, string $name, int $userId, bool $uploaded = false, bool $dryRun = false): array
    {
        [$text, $m] = self::readAny($path, $name);
        if ($m['title'] === '') {
            $m['title'] = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 250);
        }
        // A manual without a stated parent belongs to the policy with the same number (RSK-CRD-MAN-101 -> RSK-CRD-POL-101).
        if ($m['parent'] === '' && str_contains($m['code'], '-MAN-')) {
            $guess = str_replace('-MAN-', '-POL-', $m['code']);
            if (Db::val('SELECT 1 FROM policies WHERE code = ?', [$guess]) || str_contains($text, $guess)) {
                $m['parent'] = $guess;
            }
        }
        $refs = array_values(array_diff(Framework::codesIn($text), [$m['code']]));
        $ex = $m['code'] !== '' ? Db::one('SELECT * FROM policies WHERE code = ?', [$m['code']]) : null;
        $hasVersion = $ex ? (bool)Db::val('SELECT 1 FROM policy_versions WHERE policy_id = ? AND version_label = ?', [$ex['id'], $m['version']]) : false;
        $action = !$ex ? 'created' : ($hasVersion ? 'updated' : 'new_version');
        if ($dryRun) {
            return ['id' => (int)($ex['id'] ?? 0), 'code' => $m['code'], 'action' => $action, 'version' => $m['version'], 'refs' => $refs, 'passages' => $text === '' ? 0 : count(KbLibrary::chunkText($m['title'], $text)), 'meta' => $m];
        }
        $now = date('Y-m-d H:i:s');
        $section = self::section($m['code']);
        $parentId = $m['parent'] !== '' ? ((int)Db::val('SELECT id FROM policies WHERE code = ?', [$m['parent']]) ?: null) : null;
        $data = ['title' => $m['title'], 'title_en' => $m['title_en'] ?: null, 'category' => $m['category'], 'doc_level' => $m['doc_level'], 'classification' => $m['classification'],
            'approval_body' => $m['approval_body'] ?: null, 'parent_id' => $parentId, 'parent_code' => $m['parent'] ?: null, 'rm_section' => $section, 'refs' => implode(',', $refs) ?: null, 'updated_at' => $now];
        $id = Db::tx(function () use ($ex, $data, $m, $userId, $now, $hasVersion) {
            if ($ex) {
                $id = (int)$ex['id'];
                // A manual status decision (approved / retired) in the register is never overwritten by a draft file.
                Db::update('policies', $data + ($ex['status'] === 'draft' ? ['status' => $m['status']] : []), 'id = ?', [$id]);
            } else {
                $months = in_array($m['category'], ['charter', 'framework'], true) ? 24 : 12;
                $id = Db::insert('policies', $data + ['code' => $m['code'] !== '' ? $m['code'] : null, 'department_id' => null, 'owner_user_id' => $userId, 'status' => $m['status'],
                    'review_months' => $months, 'created_by' => $userId, 'created_at' => $now]);
                if ($m['code'] === '') {
                    Db::update('policies', ['code' => sprintf('POL-%03d', $id)], 'id = ?', [$id]);
                }
            }
            if (!$hasVersion) {
                Db::update('policy_versions', ['is_current' => 0], 'policy_id = ?', [$id]);
                Db::insert('policy_versions', ['policy_id' => $id, 'version_label' => $m['version'], 'effective_date' => date('Y-m-d'), 'approved_by' => null, 'approval_date' => null,
                    'change_summary' => null, 'is_current' => 1, 'created_by' => $userId, 'created_at' => $now]);
            }
            // Children imported earlier now find their parent.
            Db::exec('UPDATE policies SET parent_id = ? WHERE parent_code = ? AND parent_id IS NULL', [$id, (string)Db::val('SELECT code FROM policies WHERE id = ?', [$id])]);
            return $id;
        });
        $vid = (int)Db::val('SELECT id FROM policy_versions WHERE policy_id = ? AND version_label = ?', [$id, $m['version']]);
        $sha = hash_file('sha256', $path);
        if (!Db::val("SELECT 1 FROM attachments WHERE entity_type = 'policy_version' AND entity_id = ? AND sha256 = ? AND deleted_at IS NULL", [$vid, $sha])) {
            $uploaded ? Upload::store(['tmp_name' => $path, 'name' => $name, 'size' => filesize($path)], 'policy_version', $vid, $userId) : Upload::storeLocal($path, $name, 'policy_version', $vid, $userId);
        }
        $passages = $text === '' ? 0 : self::index($id, $m, $text, $userId);
        \App\Core\Audit::log('policy.imported', 'policy', $id, ['code' => $m['code'], 'version' => $m['version'], 'action' => $action, 'passages' => $passages]);
        return ['id' => $id, 'code' => (string)Db::val('SELECT code FROM policies WHERE id = ?', [$id]), 'action' => $action, 'version' => $m['version'], 'refs' => $refs, 'passages' => $passages];
    }

    /** Replace the document's passages in the reference library. */
    private static function index(int $policyId, array $m, string $text, int $userId): int
    {
        $old = (int)Db::val('SELECT kb_source_id FROM policies WHERE id = ?', [$policyId]);
        if ($old) {
            Db::update('kb_sources', ['status' => 'retired', 'updated_at' => date('Y-m-d H:i:s')], 'id = ?', [$old]);
        }
        $section = self::section($m['code']) ?? 'office';
        $modules = $m['category'] === 'charter' ? 'governance' : self::SECTION_MODULES[$section];
        $sid = KbLibrary::addDocument(['title_ar' => trim($m['code'] . ' — ' . $m['title'], ' —'), 'title_en' => trim($m['code'] . ' — ' . ($m['title_en'] ?: $m['title']), ' —'),
            'issuer' => mb_substr(Settings::get('org_name_ar', '') ?: 'المصرف', 0, 150), 'category' => 'bank', 'jurisdiction' => 'bank', 'authority' => 'internal', 'year' => (int)date('Y'), 'url' => null,
            'modules' => $modules, 'note' => $m['code'] . ' · v' . $m['version'] . ' · ' . $m['classification'], 'restricted' => in_array($m['classification'], Framework::RESTRICTED, true) ? 1 : 0], $text, $userId);
        Db::update('kb_sources', ['policy_id' => $policyId], 'id = ?', [$sid]);
        Db::update('policies', ['kb_source_id' => $sid], 'id = ?', [$policyId]);
        return (int)Db::val('SELECT COUNT(*) FROM kb_chunks WHERE source_id = ?', [$sid]);
    }

    /** Codes referenced by register documents that are not themselves in the register. @return array<string,list<string>> code => referencing codes */
    public static function missingReferences(): array
    {
        $have = array_flip(array_filter(array_column(Db::all('SELECT code FROM policies'), 'code')));
        $out = [];
        foreach (Db::all("SELECT code, refs, parent_code FROM policies WHERE status <> 'retired' AND (refs IS NOT NULL OR parent_code IS NOT NULL)") as $p) {
            foreach (array_filter(array_merge(explode(',', (string)$p['refs']), [(string)$p['parent_code']])) as $r) {
                if (!isset($have[$r])) {
                    $out[$r][] = $p['code'];
                }
            }
        }
        ksort($out);
        return array_map(fn($l) => array_values(array_unique($l)), $out);
    }
}
