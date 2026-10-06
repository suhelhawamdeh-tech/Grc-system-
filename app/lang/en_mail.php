<?php
// E-mail notification strings (English). Keep keys identical to ar_mail.php.
return [
    'board.title' => 'Board risk pack', 'board.intro' => 'One document for the board: top risks, losses, overdue actions, indicators in alert, appetite breaches, weak controls, continuity gaps and Sharia events. Only what you are allowed to see is included.', 'board.open' => 'Open the board pack', 'board.pdf_hint' => 'In the opened page choose Print, then "Save as PDF" in your browser to get a PDF file.',
    'board.incidents' => 'Incidents', 'board.top_risks' => 'Top residual risks', 'board.losses' => 'Losses by category', 'board.overdue' => 'Overdue treatment actions', 'board.kris' => 'Indicators in alert', 'board.breaches' => 'Open risk appetite breaches', 'board.controls' => 'Controls needing attention', 'board.bia' => 'Critical processes: approval or recovery gaps', 'board.sharia' => 'Open Sharia events', 'board.none' => 'Nothing to report.',
    'mail.digest_subject' => 'GRC platform: :n item(s) need your attention', 'mail.digest_intro' => 'You have :n item(s) that need attention:', 'mail.open_link' => 'Open your tasks: :url', 'mail.footer' => 'You receive this once a day. You can switch it off under My account.',
    'mail.escalation_subject' => 'GRC platform escalation: :n overdue item(s)', 'mail.escalation_intro' => 'These items are more than :days days overdue:',
    'mail.test_subject' => 'GRC platform: test message', 'mail.test_body' => 'This is a test message from the GRC platform. If you can read it, e-mail delivery works.',
    'mail.card' => 'E-mail notifications', 'mail.status_on' => 'Configured', 'mail.status_off' => 'Not configured: add a "mail" section to the config file (see the deployment guide).', 'mail.test_to' => 'Send a test message to', 'mail.test_send' => 'Send test', 'mail.test_ok' => 'Test message sent.', 'mail.test_fail' => 'The message was not sent: :err',
    'mail.recent' => 'Recent messages', 'mail.cron_hint' => 'Digests are sent by the daily cron job (bin/notify.php).',
    'account.email_notifications' => 'E-mail reminders', 'account.email_on' => 'Send me a daily e-mail when something needs my attention', 'account.email_saved' => 'E-mail preference saved.',
];
