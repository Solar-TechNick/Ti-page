<?php
// backend/src/validate.php

function is_honeypot_triggered(array $input): bool
{
    return !empty($input['website'] ?? '');
}

function _str(array $in, string $k, int $max): string|false|null
{
    $v = $in[$k] ?? null;
    if ($v === null) return null;
    $v = trim((string)$v);
    if ($v === '') return null;
    if (mb_strlen($v) > $max) return false; // sentinel for "too long"
    return $v;
}

function validate_contact(array $in): array
{
    $errors = [];

    $name = _str($in, 'name', 200);
    if ($name === null) $errors['name'] = 'Bitte geben Sie Ihren Namen an.';
    elseif ($name === false) $errors['name'] = 'Name darf höchstens 200 Zeichen lang sein.';

    $contact = _str($in, 'contact', 200);
    if ($contact === null) $errors['contact'] = 'Bitte geben Sie einen Kontakt an.';
    elseif ($contact === false) $errors['contact'] = 'Kontakt darf höchstens 200 Zeichen lang sein.';

    $message = _str($in, 'message', 5000);
    if ($message === null) $errors['message'] = 'Bitte schreiben Sie uns eine Nachricht.';
    elseif ($message === false) $errors['message'] = 'Nachricht darf höchstens 5000 Zeichen lang sein.';

    if (isset($in['topic']) && mb_strlen((string)$in['topic']) > 200) {
        $errors['topic'] = 'Thema darf höchstens 200 Zeichen lang sein.';
    }

    return $errors;
}

function validate_angebot(array $in): array
{
    $errors = [];

    $name = _str($in, 'name', 200);
    if (!$name) $errors['name'] = 'Bitte geben Sie Ihren Namen an.';

    $phone = _str($in, 'phone', 100);
    if (!$phone) $errors['phone'] = 'Bitte geben Sie eine Telefonnummer an.';

    $email = _str($in, 'email', 200);
    if (!$email) {
        $errors['email'] = 'Bitte geben Sie eine E-Mail-Adresse an.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Bitte geben Sie eine gültige E-Mail-Adresse an.';
    }

    $components = $in['components'] ?? null;
    if (!is_array($components) || count($components) === 0) {
        $errors['components'] = 'Bitte wählen Sie mindestens eine Komponente aus.';
    }

    $privacy = $in['privacy'] ?? null;
    if ($privacy !== '1' && $privacy !== true && $privacy !== 1) {
        $errors['privacy'] = 'Bitte bestätigen Sie die Datenschutzerklärung.';
    }

    foreach ([
        'building'      => 200,
        'location'      => 200,
        'address_street'=> 200,
        'address_postal'=> 20,
        'address_city'  => 100,
        'roof'          => 100,
        'usage'         => 100,
        'consumption'   => 100,
        'timeline'      => 100,
    ] as $k => $max) {
        if (isset($in[$k]) && mb_strlen((string)$in[$k]) > $max) {
            $errors[$k] = ucfirst($k) . " darf höchstens {$max} Zeichen lang sein.";
        }
    }
    if (isset($in['details']) && mb_strlen((string)$in['details']) > 5000) {
        $errors['details'] = 'Details dürfen höchstens 5000 Zeichen lang sein.';
    }

    if (isset($in['voucher_code']) && trim((string)$in['voucher_code']) !== '') {
        $code = trim((string)$in['voucher_code']);
        if (mb_strlen($code) > 50) {
            $errors['voucher_code'] = 'Gutscheincode darf höchstens 50 Zeichen lang sein.';
        } elseif (find_active_voucher($code) === null) {
            $errors['voucher_code'] = 'Gutscheincode ungültig oder abgelaufen.';
        }
    }

    return $errors;
}
