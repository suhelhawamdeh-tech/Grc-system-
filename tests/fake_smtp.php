<?php
// Tiny SMTP sink for tests: php tests/fake_smtp.php <port> <outfile>. Accepts AUTH LOGIN; appends each message to <outfile> separated by a marker line.
[$_, $port, $out] = $argv;
$srv = stream_socket_server("tcp://127.0.0.1:$port", $en, $es);
while ($c = @stream_socket_accept($srv, 600)) {
    fwrite($c, "220 fake ESMTP\r\n");
    $data = false; $buf = '';
    while (($line = fgets($c)) !== false) {
        if ($data) {
            if (rtrim($line, "\r\n") === '.') { $data = false; file_put_contents($out, $buf . "\n=====END=====\n", FILE_APPEND); $buf = ''; fwrite($c, "250 queued\r\n"); continue; }
            $buf .= $line; continue;
        }
        $cmd = strtoupper(substr($line, 0, 4));
        if ($cmd === 'EHLO') fwrite($c, "250-fake\r\n250 AUTH LOGIN\r\n");
        elseif ($cmd === 'AUTH') { fwrite($c, "334 VXNlcm5hbWU6\r\n"); fgets($c); fwrite($c, "334 UGFzc3dvcmQ6\r\n"); fgets($c); fwrite($c, "235 ok\r\n"); }
        elseif ($cmd === 'MAIL' || $cmd === 'RCPT') { if ($cmd === 'RCPT') $buf = ''; file_put_contents($out, trim($line) . "\n", FILE_APPEND); fwrite($c, "250 ok\r\n"); }
        elseif ($cmd === 'DATA') { $data = true; fwrite($c, "354 go\r\n"); }
        elseif ($cmd === 'QUIT') { fwrite($c, "221 bye\r\n"); break; }
        else fwrite($c, "250 ok\r\n");
    }
    fclose($c);
}
