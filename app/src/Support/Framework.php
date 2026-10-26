<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Shared vocabulary of the risk management framework: the risk function's sections, the enterprise risk taxonomy
 * groups, the document hierarchy and document types. Labels live in the language files (enum.rmsec.*, enum.ermgrp.*, ...).
 */
final class Framework
{
    /** Sections of the risk management department (second line of defence). */
    public const SECTIONS = ['office', 'credit', 'market', 'operational', 'infosec', 'basel'];

    /** Main categories of the unified risk taxonomy and the section that monitors each. */
    public const GROUPS = ['credit' => 'credit', 'market' => 'market', 'rate_of_return' => 'market', 'liquidity' => 'market', 'operational' => 'operational', 'continuity' => 'operational',
        'infosec' => 'infosec', 'sharia' => 'operational', 'capital' => 'basel', 'strategic' => 'office', 'reputation' => 'office', 'compliance' => 'office'];

    /** Document types (stored in policies.category) and their default level in the document hierarchy (1 = highest). */
    public const DOC_TYPES = ['charter' => 1, 'framework' => 3, 'policy' => 4, 'manual' => 5, 'procedure' => 5, 'standard' => 6, 'methodology' => 6, 'tool' => 6, 'plan' => 7, 'template' => 7, 'report' => 7, 'sharia_resolution' => 4];

    public const LEVELS = [1, 2, 3, 4, 5, 6, 7];
    public const CLASSIFICATIONS = ['public', 'internal', 'confidential', 'secret'];

    /** Classifications whose documents are indexed as restricted in the reference library. */
    public const RESTRICTED = ['confidential', 'secret'];

    /** Codes of documents referenced in a text, e.g. RSK-CRD-POL-101 or BCMS-PL-001. */
    public static function codesIn(string $text): array
    {
        preg_match_all('/\b(?:[A-Z]{2,5}-){1,3}[A-Z]{1,5}-\d{3}\b/', $text, $m);
        $codes = array_values(array_unique($m[0]));
        sort($codes);
        return $codes;
    }

    /** Default hierarchy level for a document type; the risk appetite framework sits at level 2. */
    public static function defaultLevel(string $type, string $code = ''): int
    {
        if ($type === 'framework' && str_contains($code, '-RAF-')) {
            return 2;
        }
        return self::DOC_TYPES[$type] ?? 7;
    }
}
