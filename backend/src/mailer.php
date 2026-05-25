<?php
// backend/src/mailer.php

function set_mail_transport(callable $transport): void
{
    $GLOBALS['__ti_mail_transport'] = $transport;
}

function send_mail(array $msg): bool
{
    $cfg = config('mail');
    $from = sprintf('%s <%s>', $cfg['from_name'], $cfg['from_address']);
    $subject = _sanitize_header(($msg['subject'] ?? '(ohne Betreff)'), 200);

    $headers = [
        "From: {$from}",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=utf-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    if (!empty($msg['reply_to'])) {
        $headers[] = 'Reply-To: ' . _sanitize_header($msg['reply_to'], 200);
    }
    $headersStr = implode("\r\n", $headers);

    $transport = $GLOBALS['__ti_mail_transport'] ?? function(array $m): bool {
        return @mail($m['to'], $m['subject'], $m['body'], $m['headers']);
    };

    $ok = $transport([
        'to'      => $msg['to'],
        'subject' => $subject,
        'body'    => $msg['body'],
        'headers' => $headersStr,
    ]);

    if (!$ok) {
        _mail_log_failure($msg, $subject);
    }
    return (bool)$ok;
}

function _sanitize_header(string $v, int $maxLen): string
{
    $v = preg_replace('/[\r\n]+/', ' ', $v);
    $v = mb_substr($v, 0, $maxLen);
    return $v;
}

function _mail_log_failure(array $msg, string $subject): void
{
    $dir = config('logs_dir');
    if (!is_dir($dir)) @mkdir($dir, 0750, true);
    $line = sprintf("[%s] mail failure to=%s subject=%s\n",
        date('c'), $msg['to'] ?? '?', $subject);
    @file_put_contents($dir . '/mail_errors.log', $line, FILE_APPEND);
}
