<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Config;
use App\Core\Db;
use App\Core\I18n;

/** Daily e-mail digests of each user's open work, plus escalation of long-overdue items to the risk managers. */
final class Notifier
{
    /** @return array{sent:int,failed:int,skipped:int,escalations:int} */
    public static function run(bool $dryRun = false): array
    {
        $out = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'escalations' => 0];
        $orig = I18n::locale();
        $escDays = max(1, (int)Config::get('mail.escalation_days', 7));
        $cut = date('Y-m-d', strtotime("-$escDays days"));
        $stale = [];
        $users = Db::all("SELECT * FROM users WHERE is_active = 1 AND email IS NOT NULL AND email <> '' ORDER BY id");
        foreach ($users as $u) {
            $items = Attention::items($u);
            foreach ($items as $sec => $rows) {
                foreach ($rows as $r) {
                    if (!empty($r['due']) && $r['due'] < $cut && $sec !== 'notes') {
                        $stale[] = ['user' => $u['full_name'], 'sec' => $sec, 'title' => $r['title'], 'due' => $r['due']];
                    }
                }
            }
            $n = array_sum(array_map('count', $items));
            if ($n === 0 || Prefs::get((int)$u['id'], 'email_notifications') === '0' || self::sentToday((int)$u['id'], 'digest')) {
                $out['skipped']++;
                continue;
            }
            I18n::init($u['lang'] ?: 'ar');
            [$subject, $body] = self::digest($items, $n);
            self::deliver($u, 'digest', $subject, $body, $dryRun, $out);
        }
        if ($stale) {
            foreach (Db::all("SELECT * FROM users WHERE is_active = 1 AND role = 'risk_manager' AND email IS NOT NULL AND email <> ''") as $m) {
                if (self::sentToday((int)$m['id'], 'escalation')) {
                    continue;
                }
                I18n::init($m['lang'] ?: 'ar');
                $lines = [t('mail.escalation_intro', ['days' => $escDays]), ''];
                foreach (array_slice($stale, 0, 100) as $x) {
                    $lines[] = '- ' . $x['user'] . ' — ' . t(Attention::LABELS[$x['sec']] ?? 'my.title') . ': ' . $x['title'] . ' (' . $x['due'] . ')';
                }
                $lines[] = '';
                $lines[] = self::footer();
                self::deliver($m, 'escalation', t('mail.escalation_subject', ['n' => count($stale)]), implode("\n", $lines), $dryRun, $out);
                $out['escalations']++;
            }
        }
        I18n::init($orig);
        return $out;
    }

    private static function digest(array $items, int $n): array
    {
        $lines = [t('mail.digest_intro', ['n' => $n]), ''];
        foreach ($items as $sec => $rows) {
            if (!$rows) {
                continue;
            }
            $lines[] = t(Attention::LABELS[$sec] ?? 'my.title') . ':';
            foreach (array_slice($rows, 0, 20) as $r) {
                $lines[] = '  - ' . $r['title'] . (!empty($r['due']) ? ' (' . $r['due'] . ($r['overdue'] ? ', ' . t('my.overdue') : '') . ')' : '');
            }
            $lines[] = '';
        }
        $lines[] = self::footer();
        return [t('mail.digest_subject', ['n' => $n]), implode("\n", $lines)];
    }

    private static function footer(): string
    {
        $base = rtrim((string)Config::get('app.base_url', ''), '/');
        return ($base !== '' ? t('mail.open_link', ['url' => $base . '/my']) . "\n" : '') . t('mail.footer');
    }

    private static function sentToday(int $userId, string $kind): bool
    {
        return (bool)Db::val("SELECT 1 FROM email_log WHERE user_id = ? AND kind = ? AND status = 'sent' AND created_at >= CURDATE() LIMIT 1", [$userId, $kind]);
    }

    private static function deliver(array $u, string $kind, string $subject, string $body, bool $dry, array &$out): void
    {
        if ($dry) {
            $out['sent']++;
            return;
        }
        $err = Mailer::send($u['email'], $subject, $body);
        Db::insert('email_log', ['user_id' => $u['id'], 'kind' => $kind, 'recipient' => $u['email'], 'subject' => mb_substr($subject, 0, 250), 'status' => $err === null ? 'sent' : 'failed', 'error' => $err ? mb_substr($err, 0, 250) : null, 'created_at' => date('Y-m-d H:i:s')]);
        $out[$err === null ? 'sent' : 'failed']++;
    }
}
