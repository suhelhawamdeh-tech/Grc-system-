<?php
declare(strict_types=1);

namespace App\Core;

final class Migrator
{
    public static function dir(): string
    {
        return APP_ROOT . '/database/migrations';
    }

    /** @return array<string,string> version => path */
    public static function files(): array
    {
        $out = [];
        foreach (glob(self::dir() . '/*.sql') ?: [] as $f) {
            $out[basename($f, '.sql')] = $f;
        }
        ksort($out);
        return $out;
    }

    public static function applied(): array
    {
        try {
            return array_column(Db::all('SELECT version FROM schema_migrations'), 'version');
        } catch (\Throwable) {
            return [];
        }
    }

    public static function pending(): array
    {
        return array_diff_key(self::files(), array_flip(self::applied()));
    }

    /** Split a migration file into statements (statements end with ';' at end of line). */
    public static function statements(string $sql): array
    {
        $sql = preg_replace('/^\s*--.*$/m', '', $sql) ?? $sql;
        $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn($s) => $s !== ''));
    }

    /** Apply all pending migrations in order. DDL auto-commits in MySQL, so each file is recorded only after it fully succeeds. */
    public static function run(): array
    {
        $pdo = Db::pdo();
        $done = [];
        foreach (self::pending() as $version => $path) {
            foreach (self::statements((string)file_get_contents($path)) as $stmt) {
                $pdo->exec($stmt);
            }
            Db::exec('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)', [$version, date('Y-m-d H:i:s')]);
            $done[] = $version;
        }
        return $done;
    }
}
