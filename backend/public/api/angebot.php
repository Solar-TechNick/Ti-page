<?php
// backend/public/api/angebot.php

require_once __DIR__ . '/../../src/bootstrap.php';

function angebot_handle(array $input, array $filesSuperglobal, ?string $packed_ip, string $userAgent): array
{
    if (is_honeypot_triggered($input)) {
        return ['status' => 200, 'body' => ['ok' => true, 'id' => 0]];
    }

    if (is_rate_limited($packed_ip, (int)config('rate_limit.max_per_hour'), 'PT1H')) {
        return ['status' => 429, 'body' => rate_limit_error()];
    }

    $errors = validate_angebot($input);
    if ($errors) {
        return ['status' => 400, 'body' => validation_error($errors)];
    }

    $files = normalise_files_input($filesSuperglobal['files'] ?? []);
    $uploadCfg = config('uploads');
    if ($files) {
        $err = validate_uploads($files, $uploadCfg);
        if ($err !== null) {
            $status = $err['kind'] === 'too_large' ? 413 : 400;
            $body = $err['kind'] === 'too_large' ? too_large_error() : validation_error($err['fields']);
            return ['status' => $status, 'body' => $body];
        }
    }

    $components = implode(', ', array_map('trim', $input['components']));

    $voucherCode = isset($input['voucher_code']) ? trim((string)$input['voucher_code']) : '';
    $voucherCode = $voucherCode === '' ? null : $voucherCode;

    $stmt = db()->prepare(
        "INSERT INTO angebot_requests
        (name, phone, email, components, building,
         address_street, address_postal, address_city,
         roof, usage_profile, consumption, timeline, details,
         voucher_code, photos_followup, ip_address, user_agent)
        VALUES (:name,:phone,:email,:components,:building,
                :addr_street,:addr_postal,:addr_city,
                :roof,:usage,:consumption,:timeline,:details,
                :voucher,:photos,:ip,:ua)"
    );
    $stmt->execute([
        ':name'        => trim($input['name']),
        ':phone'       => trim($input['phone']),
        ':email'       => trim($input['email']),
        ':components'  => mb_substr($components, 0, 500),
        ':building'    => $input['building']       ?? null,
        ':addr_street' => $input['address_street'] ?? null,
        ':addr_postal' => $input['address_postal'] ?? null,
        ':addr_city'   => $input['address_city']   ?? null,
        ':roof'        => $input['roof']           ?? null,
        ':usage'       => $input['usage']          ?? null,
        ':consumption' => $input['consumption']    ?? null,
        ':timeline'    => $input['timeline']       ?? null,
        ':details'     => $input['details']        ?? null,
        ':voucher'     => $voucherCode,
        ':photos'      => !empty($input['photos_followup']) ? 1 : 0,
        ':ip'          => $packed_ip,
        ':ua'          => mb_substr($userAgent, 0, 500),
    ]);
    $id = (int)db()->lastInsertId();

    $uploadBase = $GLOBALS['__ti_override_upload_dir'] ?? $uploadCfg['dir'];
    $attachments = [];
    if ($files) {
        $attachments = store_uploads($files, $uploadBase, $id);
        $ins = db()->prepare(
            "INSERT INTO angebot_attachments (angebot_id, stored_name, original_name, mime_type, size_bytes)
             VALUES (:aid, :sn, :on, :mt, :sz)"
        );
        foreach ($attachments as $a) {
            $ins->execute([':aid'=>$id,':sn'=>$a['stored_name'],':on'=>$a['original_name'],
                           ':mt'=>$a['mime_type'],':sz'=>$a['size_bytes']]);
        }
    }

    _angebot_notify_operator($id, $input, $components, $attachments);
    _angebot_autoreply_visitor($input, $components);

    return ['status' => 200, 'body' => ok_response($id)];
}

function _angebot_notify_operator(int $id, array $in, string $components, array $attachments): void
{
    $cfg = config('mail');
    $adminUrl = config('urls.admin_base') . "/detail.php?type=angebot&id={$id}";
    $totalSize = array_sum(array_column($attachments, 'size_bytes'));
    $attLine = $attachments
        ? sprintf('Anhänge: %d Dateien (%.1f MB) — im Admin ansehen:', count($attachments), $totalSize/1024/1024)
        : 'Anhänge: keine';

    $voucherLine = !empty($in['voucher_code']) ? trim((string)$in['voucher_code']) : '';

    $street   = trim((string)($in['address_street'] ?? ''));
    $cityLine = trim(($in['address_postal'] ?? '') . ' ' . ($in['address_city'] ?? ''));
    $street   = $street   === '' ? '-' : $street;
    $cityLine = $cityLine === '' ? '-' : $cityLine;

    $projectLines = [
        '  Komponenten:  ' . $components,
        '  Objekt:       ' . ($in['building']    ?? '-'),
        '  Adresse:      ' . $street,
        '                ' . $cityLine,
        '  Dachform:     ' . ($in['roof']        ?? '-'),
        '  Nutzung:      ' . ($in['usage']       ?? '-'),
        '  Verbrauch:    ' . ($in['consumption'] ?? '-'),
        '  Zeitraum:     ' . ($in['timeline']    ?? '-'),
    ];
    if ($voucherLine !== '') {
        $projectLines[] = '  Gutscheincode: ' . $voucherLine;
    }
    $body = implode("\n", array_merge([
        'Neue Angebotsanfrage:',
        '',
        'Kontakt',
        '  Name:    ' . $in['name'],
        '  Telefon: ' . $in['phone'],
        '  E-Mail:  ' . $in['email'],
        '',
        'Projekt',
    ], $projectLines, [
        '',
        'Details:',
        $in['details'] ?? '-',
        '',
        $attLine,
        $attachments ? $adminUrl : '',
        '',
        '────────────────────────────',
        'Eingegangen: ' . date('d.m.Y H:i'),
        'Im Admin:    ' . $adminUrl,
        'Antwort an:  ' . $in['email'],
    ]));
    send_mail([
        'to'       => $cfg['to_address'],
        'subject'  => "Neue Angebotsanfrage: {$components} (#{$id})",
        'body'     => $body,
        'reply_to' => $in['email'],
    ]);
}

function _angebot_autoreply_visitor(array $in, string $components): void
{
    $addressParts = array_filter([
        trim((string)($in['address_street'] ?? '')),
        trim(trim((string)($in['address_postal'] ?? '')) . ' ' . trim((string)($in['address_city'] ?? ''))),
    ], fn($p) => $p !== '');
    $addressLine = $addressParts ? implode(', ', $addressParts) : '-';

    $body = implode("\n", [
        'Vielen Dank für Ihre Angebotsanfrage.',
        '',
        'Wir haben Ihre Angaben erhalten und melden uns innerhalb von 2 Werktagen.',
        '',
        'Ihre Angaben (Auszug):',
        '  Komponenten: ' . $components,
        '  Objekt:      ' . ($in['building'] ?? '-'),
        '  Adresse:     ' . $addressLine,
        '',
        '────────────────────────────',
        'Technik- & Instandsetzungs GmbH',
        'Quitzower Damm 15, 19348 Sükow',
        'Tel.: +49 3876 612474',
    ]);
    send_mail([
        'to'      => $in['email'],
        'subject' => 'Ihre Angebotsanfrage bei Technik- & Instandsetzungs GmbH',
        'body'    => $body,
    ]);
}

// HTTP entry
if (PHP_SAPI !== 'cli' && !defined('TI_TEST')) {
    handle_preflight_if_options($_SERVER['HTTP_ORIGIN'] ?? null, config('cors_origins'));
    emit_cors_headers(cors_headers_for($_SERVER['HTTP_ORIGIN'] ?? null, config('cors_origins')));

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        emit_json(405, ['ok' => false, 'error' => 'method_not_allowed']);
        exit;
    }

    $input = $_POST;
    if (isset($input['components']) && !is_array($input['components'])) {
        $input['components'] = [$input['components']];
    }

    $result = angebot_handle($input, $_FILES, pack_ip(client_ip()),
        (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    emit_json($result['status'], $result['body']);
}
