<?php
declare(strict_types=1);

namespace App\Support;

use App\Core\Config;

/**
 * Minimal SMTP client (implicit TLS, STARTTLS or plain; AUTH LOGIN) with a PHP mail() fallback.
 * Settings live in the config file (never in the database): mail.host, port, secure (ssl|tls|none), user, pass, from, from_name, transport (smtp|mail).
 */
final class Mailer
{
    public static function configured(): bool
    {
        return (string)Config::get('mail.from', '') !== '' && ((string)Config::get('mail.host', '') !== '' || Config::get('mail.transport') === 'mail');
    }

    /** @return string|null error message, null when the message was accepted */
    public static function send(string $to, string $subject, string $body): ?string
    {
        if (!self::configured()) {
            return 'Mail is not configured';
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $to)) {
            return 'Invalid recipient address';
        }
        $from = (string)Config::get('mail.from');
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid sender address';
        }
        $subject = trim(preg_replace('/[\r\n]+/', ' ', $subject));
        $fromName = trim(preg_replace('/[\r\n"]+/', ' ', (string)Config::get('mail.from_name', '')));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . ($fromName !== '' ? '=?UTF-8?B?' . base64_encode($fromName) . '?= ' : '') . '<' . $from . '>',
            'To: <' . $to . '>',
            'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (substr(strrchr($from, '@'), 1) ?: 'localhost') . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Auto-Submitted: auto-generated',
        ];
        $payload = chunk_split(base64_encode($body), 76, "\r\n");
        if (Config::get('mail.transport') === 'mail' && (string)Config::get('mail.host', '') === '') {
            $h = $headers;
            $h = array_values(array_filter($h, fn($x) => !str_starts_with($x, 'To:') && !str_starts_with($x, 'Subject:')));
            return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $payload, implode("\r\n", $h)) ? null : 'mail() refused the message';
        }
        return self::smtp($from, $to, implode("\r\n", $headers) . "\r\n\r\n" . $payload);
    }

    private static function smtp(string $from, string $to, string $message): ?string
    {
        $host = (string)Config::get('mail.host');
        $port = (int)Config::get('mail.port', 587);
        $secure = (string)Config::get('mail.secure', 'tls');
        $ctx = stream_context_create(['ssl' => ['verify_peer' => (bool)Config::get('mail.verify_tls', true), 'verify_peer_name' => (bool)Config::get('mail.verify_tls', true), 'SNI_enabled' => true]]);
        $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            return 'Cannot connect to the mail server (' . $errno . ')';
        }
        stream_set_timeout($fp, 20);
        $read = static function () use ($fp): array {
            $code = 0;
            $text = '';
            while (($line = fgets($fp, 1024)) !== false) {
                $text .= $line;
                $code = (int)substr($line, 0, 3);
                if (strlen($line) < 4 || $line[3] !== '-') {
                    break;
                }
            }
            return [$code, $text];
        };
        $cmd = static function (string $c, array $ok) use ($fp, $read): ?string {
            fwrite($fp, $c . "\r\n");
            [$code, $text] = $read();
            return in_array($code, $ok, true) ? null : 'SMTP error ' . $code;
        };
        try {
            [$code] = $read();
            if ($code !== 220) {
                return 'Mail server not ready (' . $code . ')';
            }
            $ehlo = parse_url('x://' . ($_SERVER['SERVER_NAME'] ?? 'localhost'), PHP_URL_HOST) ?: 'localhost';
            if ($e = $cmd('EHLO ' . $ehlo, [250])) {
                return $e;
            }
            if ($secure === 'tls') {
                if ($e = $cmd('STARTTLS', [220])) {
                    return $e;
                }
                if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return 'TLS negotiation failed';
                }
                if ($e = $cmd('EHLO ' . $ehlo, [250])) {
                    return $e;
                }
            }
            $user = (string)Config::get('mail.user', '');
            if ($user !== '') {
                if ($e = $cmd('AUTH LOGIN', [334]) ?? $cmd(base64_encode($user), [334]) ?? $cmd(base64_encode((string)Config::get('mail.pass', '')), [235])) {
                    return 'Mail authentication failed';
                }
            }
            if ($e = $cmd('MAIL FROM:<' . $from . '>', [250]) ?? $cmd('RCPT TO:<' . $to . '>', [250, 251]) ?? $cmd('DATA', [354])) {
                return $e;
            }
            fwrite($fp, $message . "\r\n.\r\n");
            [$code] = $read();
            @fwrite($fp, "QUIT\r\n");
            return $code === 250 ? null : 'Message rejected (' . $code . ')';
        } finally {
            @fclose($fp);
        }
    }
}
