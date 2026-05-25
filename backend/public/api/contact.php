<?php
// backend/public/api/contact.php

require_once __DIR__ . '/../../src/bootstrap.php';

/**
 * Pure handler. Returns ['status'=>int,'body'=>array].
 * Side effects: DB writes, mail sends.
 */
function contact_handle(array $input, ?string $packed_ip, string $userAgent): array
{
    if (is_honeypot_triggered($input)) {
        return ['status' => 200, 'body' => ['ok' => true, 'id' => 0]];
    }

    if (is_rate_limited($packed_ip, (int)config('rate_limit.max_per_hour'), 'PT1H')) {
        return ['status' => 429, 'body' => rate_limit_error()];
    }

    $errors = validate_contact($input);
    if ($errors) {
        return ['status' => 400, 'body' => validation_error($errors)];
    }

    $stmt = db()->prepare(
        "INSERT INTO contact_requests (name, contact, topic, message, ip_address, user_agent)
         VALUES (:name, :contact, :topic, :message, :ip, :ua)"
    );
    $stmt->execute([
        ':name'    => trim($input['name']),
        ':contact' => trim($input['contact']),
        ':topic'   => isset($input['topic']) ? trim($input['topic']) : null,
        ':message' => trim($input['message']),
        ':ip'      => $packed_ip,
        ':ua'      => mb_substr($userAgent, 0, 500),
    ]);
    $id = (int) db()->lastInsertId();

    _contact_notify_operator($id, $input);
    _contact_autoreply_visitor($input);

    return ['status' => 200, 'body' => ok_response($id)];
}

function _contact_notify_operator(int $id, array $in): void
{
    $cfg = config('mail');
    $adminUrl = config('urls.admin_base') . "/detail.php?type=contact&id={$id}";
    $body = implode("\n", [
        'Neue Kontaktanfrage:',
        '',
        'Name:    ' . $in['name'],
        'Kontakt: ' . $in['contact'],
        'Thema:   ' . ($in['topic'] ?? '-'),
        '',
        'Nachricht:',
        $in['message'],
        '',
        '────────────────────────────',
        'Eingegangen: ' . date('d.m.Y H:i'),
        'Im Admin:    ' . $adminUrl,
        'Antwort an:  ' . $in['contact'],
    ]);
    send_mail([
        'to'       => $cfg['to_address'],
        'subject'  => 'Neue Kontaktanfrage: ' . ($in['topic'] ?? 'Anfrage') . " (#{$id})",
        'body'     => $body,
        'reply_to' => $in['contact'],
    ]);
}

function _contact_autoreply_visitor(array $in): void
{
    if (!filter_var($in['contact'] ?? '', FILTER_VALIDATE_EMAIL)) return;
    $body = implode("\n", [
        'Vielen Dank für Ihre Nachricht.',
        '',
        'Wir haben Ihre Anfrage erhalten und melden uns innerhalb von 2 Werktagen.',
        '',
        'Ihre Anfrage:',
        '-------------',
        ($in['topic'] ?? '') !== '' ? 'Thema: ' . $in['topic'] : '',
        $in['message'],
        '',
        '────────────────────────────',
        'Technik- & Instandsetzungs GmbH',
        'Quitzower Damm 15, 19348 Sükow',
        'Tel.: +49 3876 612474',
    ]);
    send_mail([
        'to'      => $in['contact'],
        'subject' => 'Ihre Anfrage bei Technik- & Instandsetzungs GmbH',
        'body'    => $body,
    ]);
}

// Script body — only runs when called via HTTP, not in tests.
if (PHP_SAPI !== 'cli' && !defined('TI_TEST')) {
    handle_preflight_if_options($_SERVER['HTTP_ORIGIN'] ?? null, config('cors_origins'));
    emit_cors_headers(cors_headers_for($_SERVER['HTTP_ORIGIN'] ?? null, config('cors_origins')));

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        emit_json(405, ['ok' => false, 'error' => 'method_not_allowed']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
    $result = contact_handle(
        $input,
        pack_ip(client_ip()),
        (string)($_SERVER['HTTP_USER_AGENT'] ?? '')
    );
    emit_json($result['status'], $result['body']);
}
