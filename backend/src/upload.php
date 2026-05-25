<?php
// backend/src/upload.php

const _MIME_EXT = [
    'image/jpeg'      => 'jpg',
    'image/png'       => 'png',
    'image/heic'      => 'heic',
    'image/webp'      => 'webp',
    'application/pdf' => 'pdf',
];

/**
 * Convert PHP's bizarre $_FILES['fieldname'] layout into a flat list of files.
 * Accepts the inner array (e.g. $_FILES['files']) — not the whole $_FILES.
 */
function normalise_files_input(array $entry): array
{
    if (!isset($entry['name'])) return [];
    if (!is_array($entry['name'])) {
        return [[
            'name'     => $entry['name'],
            'type'     => $entry['type'],
            'tmp_name' => $entry['tmp_name'],
            'error'    => $entry['error'],
            'size'     => $entry['size'],
        ]];
    }
    $out = [];
    $n = count($entry['name']);
    for ($i = 0; $i < $n; $i++) {
        if (($entry['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
        $out[] = [
            'name'     => $entry['name'][$i],
            'type'     => $entry['type'][$i],
            'tmp_name' => $entry['tmp_name'][$i],
            'error'    => $entry['error'][$i],
            'size'     => $entry['size'][$i],
        ];
    }
    return $out;
}

/**
 * Validate a list of normalised uploads.
 * Returns null when OK, or ['kind' => 'too_large'|'validation', 'fields' => [...]].
 */
function validate_uploads(array $files, array $cfg): ?array
{
    if (count($files) > $cfg['max_file_count']) {
        return ['kind' => 'validation', 'fields' => ['files' => 'Zu viele Dateien (max. ' . $cfg['max_file_count'] . ').']];
    }

    $total = 0;
    foreach ($files as $f) {
        if ($f['error'] !== UPLOAD_ERR_OK) {
            return ['kind' => 'validation', 'fields' => ['files' => 'Fehler beim Upload: ' . $f['name']]];
        }
        if ($f['size'] > $cfg['max_file_bytes']) {
            return ['kind' => 'too_large', 'fields' => ['files' => $f['name'] . ' überschreitet ' . ($cfg['max_file_bytes']/1024/1024) . ' MB.']];
        }
        $total += $f['size'];

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detectedMime = finfo_file($finfo, $f['tmp_name']);
        finfo_close($finfo);
        if (!in_array($detectedMime, $cfg['allowed_mimes'], true)) {
            return ['kind' => 'validation', 'fields' => ['files' => 'Dateityp nicht erlaubt: ' . $f['name']]];
        }
    }
    if ($total > $cfg['max_total_bytes']) {
        return ['kind' => 'too_large', 'fields' => ['files' => 'Gesamtgröße überschritten.']];
    }
    return null;
}

/**
 * Move uploaded files into $baseDir/<id>/<random>.<ext>. Returns metadata rows for DB insert.
 */
function store_uploads(array $files, string $baseDir, int $requestId, ?array $mimeExtMap = null): array
{
    $mimeExtMap = $mimeExtMap ?? _MIME_EXT;
    $dir = $baseDir . '/' . $requestId;
    if (!is_dir($dir) && !mkdir($dir, 0750, true)) {
        throw new RuntimeException("Cannot create upload dir: {$dir}");
    }

    $out = [];
    foreach ($files as $f) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $f['tmp_name']);
        finfo_close($finfo);

        $ext = $mimeExtMap[$mime] ?? 'bin';
        $stored = bin2hex(random_bytes(6)) . '.' . $ext;
        $target = "{$dir}/{$stored}";

        // Use rename when not a real PHP upload (tests), move_uploaded_file in prod
        if (is_uploaded_file($f['tmp_name'])) {
            if (!move_uploaded_file($f['tmp_name'], $target)) {
                throw new RuntimeException('move_uploaded_file failed');
            }
        } else {
            if (!rename($f['tmp_name'], $target)) {
                throw new RuntimeException('rename failed');
            }
        }
        @chmod($target, 0640);

        $out[] = [
            'stored_name'   => $stored,
            'original_name' => mb_substr($f['name'], 0, 255),
            'mime_type'     => $mime,
            'size_bytes'    => filesize($target),
        ];
    }
    return $out;
}
