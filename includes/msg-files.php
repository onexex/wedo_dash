<?php
/* =============================================================================
 * includes/msg-files.php — pictures and documents in Messages.
 *
 * Sending is gated by the `msgfile` access right (accessrights.msgfile = 2,
 * sql/2026-10-06-add-message-files-access-right.sql). Everyone in the
 * conversation can open what was sent there — and nobody else: the files live
 * in uploads/messages/ (web access denied by its own .htaccess) and are only
 * served through query/msg-file.php, which checks the viewer is in that chat.
 *
 * A picture is messages.Kind = 'image', a document Kind = 'file', with a small
 * JSON card as the text:
 *   {"k": "2026/10/<32 hex>.jpg", "n": "site visit.jpg", "s": 482113, "w": 1280, "h": 960}
 * k = stored path (random name, our own extension), n = the sender's file name
 * (shown only, never used on disk), s = bytes, w/h = picture size.
 *
 * Used by: query/Query-messages.php (send_file), query/msg-file.php (download)
 * ========================================================================== */

require_once __DIR__ . '/messages-lib.php';
require_once __DIR__ . '/msg-groups.php';

const MF_MAX_BYTES = 10 * 1024 * 1024;   // 10 MB

/** Allowed extensions => [kind, content type, mime types finfo may report for it]. */
function mf_types(): array
{
    $office = ['application/zip', 'application/octet-stream', 'application/x-zip-compressed'];   // docx/xlsx/pptx are zips
    $ole    = ['application/vnd.ms-office', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream'];
    return [
        'jpg'  => ['image', 'image/jpeg', ['image/jpeg']],
        'jpeg' => ['image', 'image/jpeg', ['image/jpeg']],
        'png'  => ['image', 'image/png',  ['image/png']],
        'gif'  => ['image', 'image/gif',  ['image/gif']],
        'webp' => ['image', 'image/webp', ['image/webp']],
        'pdf'  => ['file', 'application/pdf', ['application/pdf']],
        'doc'  => ['file', 'application/msword', array_merge(['application/msword'], $ole)],
        'docx' => ['file', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                   array_merge(['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], $office)],
        'xls'  => ['file', 'application/vnd.ms-excel', array_merge(['application/vnd.ms-excel'], $ole)],
        'xlsx' => ['file', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                   array_merge(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'], $office)],
        'ppt'  => ['file', 'application/vnd.ms-powerpoint', array_merge(['application/vnd.ms-powerpoint'], $ole)],
        'pptx' => ['file', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                   array_merge(['application/vnd.openxmlformats-officedocument.presentationml.presentation'], $office)],
        'txt'  => ['file', 'text/plain', ['text/plain']],
        'csv'  => ['file', 'text/csv',   ['text/plain', 'text/csv', 'application/csv']],
    ];
}

/** Where the files live; created (with a deny-all .htaccess) on first use. */
function mf_dir(?string $dir = null): string
{
    $dir = $dir ?? dirname(__DIR__) . '/uploads/messages';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    if (is_dir($dir) && !is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess',
            "# Message attachments: served only through query/msg-file.php (members of the chat)\n"
            . "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

/** Whether $me holds the `msgfile` right. False until the migration has run. */
function mf_can_send(PDO $pdo, string $me): bool
{
    if (!msg_has_kind($pdo)) { return false; }
    try {
        $st = $pdo->prepare("SELECT msgfile FROM accessrights WHERE EmpID = :id");
        $st->execute([':id' => $me]);
        return (int) $st->fetchColumn() === 2;
    } catch (Throwable $e) {
        return false;   // column not there yet
    }
}

/** "My Report (final).PDF" -> a display name: no path, no control characters, at most 100 characters. */
function mf_clean_name(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '');
    if ($name === '' || !mb_check_encoding($name, 'UTF-8')) { $name = 'file'; }
    if (mb_strlen($name) > 100) {
        $ext  = pathinfo($name, PATHINFO_EXTENSION);
        $name = rtrim(mb_substr($name, 0, 99 - mb_strlen($ext))) . '.' . $ext;
    }
    return $name;
}

/**
 * Check and store one upload ($_FILES entry). $uploaded = false only for tests
 * (copies a local file instead of move_uploaded_file).
 * Returns ['ok' => true, 'kind' => 'image'|'file', 'text' => card JSON, 'path' => stored file]
 *      or ['ok' => false, 'error' => '...'].
 */
function mf_store(array $f, bool $uploaded = true, ?string $dir = null): array
{
    $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'error' => 'That file is too large (10 MB at most).'];
    }
    if ($err !== UPLOAD_ERR_OK || empty($f['tmp_name'])) { return ['ok' => false, 'error' => 'The file didn’t upload — please try again.']; }
    if ($uploaded && !is_uploaded_file($f['tmp_name'])) { return ['ok' => false, 'error' => 'The file didn’t upload — please try again.']; }

    $size = (int) @filesize($f['tmp_name']);
    if ($size <= 0) { return ['ok' => false, 'error' => 'That file is empty.']; }
    if ($size > MF_MAX_BYTES) { return ['ok' => false, 'error' => 'That file is too large (10 MB at most).']; }

    $name  = mf_clean_name((string) ($f['name'] ?? 'file'));
    $ext   = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $types = mf_types();
    if (!isset($types[$ext])) {
        return ['ok' => false, 'error' => 'You can send pictures (JPG, PNG, GIF, WebP) and documents (PDF, Word, Excel, PowerPoint, TXT, CSV).'];
    }
    [$kind, , $mimes] = $types[$ext];

    // the content must match the extension (a renamed .php or .exe is refused)
    $mime = '';
    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string) finfo_file($fi, $f['tmp_name']);
        finfo_close($fi);
    }
    if ($mime !== '' && !in_array($mime, $mimes, true)) {
        return ['ok' => false, 'error' => 'That file doesn’t look like a real .' . $ext . ' file.'];
    }
    $card = ['n' => $name, 's' => $size];
    if ($kind === 'image') {
        $img = @getimagesize($f['tmp_name']);
        $want = ['jpg' => IMAGETYPE_JPEG, 'jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'gif' => IMAGETYPE_GIF, 'webp' => IMAGETYPE_WEBP][$ext];
        if (!$img || $img[2] !== $want) { return ['ok' => false, 'error' => 'That picture couldn’t be read.']; }
        $card['w'] = (int) $img[0];
        $card['h'] = (int) $img[1];
    } elseif ($ext !== 'txt' && $ext !== 'csv' && $mime === '') {
        // no finfo on this server: at least check the file signature
        $head = (string) @file_get_contents($f['tmp_name'], false, null, 0, 8);
        $ok = $ext === 'pdf' ? strncmp($head, '%PDF', 4) === 0
            : (in_array($ext, ['docx', 'xlsx', 'pptx'], true) ? strncmp($head, "PK\x03\x04", 4) === 0
            : strncmp($head, "\xD0\xCF\x11\xE0", 4) === 0);
        if (!$ok) { return ['ok' => false, 'error' => 'That file doesn’t look like a real .' . $ext . ' file.']; }
    }

    $sub  = date('Y') . '/' . date('m');
    $root = mf_dir($dir);
    if (!is_dir($root . '/' . $sub) && !@mkdir($root . '/' . $sub, 0775, true)) {
        return ['ok' => false, 'error' => 'The server couldn’t save the file (uploads folder not writable).'];
    }
    $key  = $sub . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
    $dest = $root . '/' . $key;
    $moved = $uploaded ? @move_uploaded_file($f['tmp_name'], $dest) : @copy($f['tmp_name'], $dest);
    if (!$moved) { return ['ok' => false, 'error' => 'The server couldn’t save the file (uploads folder not writable).']; }
    @chmod($dest, 0644);

    return ['ok' => true, 'kind' => $kind, 'path' => $dest,
            'text' => json_encode(['k' => $key] + $card, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)];
}

/** A message's card, or null when it doesn't name a stored file. */
function mf_card(string $text): ?array
{
    $c = json_decode($text, true);
    if (!is_array($c) || !isset($c['k']) || !is_string($c['k'])) { return null; }
    if (!preg_match('#^\d{4}/\d{2}/[a-f0-9]{32}\.([a-z]{3,4})$#', $c['k'], $m) || !isset(mf_types()[$m[1]])) { return null; }
    return $c + ['ext' => $m[1], 'n' => 'file', 's' => 0];
}

/**
 * The picture/document message $msid if $me may open it (they are in that
 * conversation), with its card; null otherwise.
 */
function mf_for_viewer(PDO $pdo, string $me, int $msid): ?array
{
    if ($msid <= 0 || !msg_has_kind($pdo)) { return null; }
    $st = $pdo->prepare("SELECT MSID, MHID, Message, Kind FROM messages WHERE MSID = :id");
    $st->execute([':id' => $msid]);
    $m = $st->fetch(PDO::FETCH_ASSOC);
    if (!$m || !in_array($m['Kind'], ['image', 'file'], true)) { return null; }
    $card = mf_card((string) $m['Message']);
    if (!$card) { return null; }

    $gid = grp_id_from_key((string) $m['MHID']);
    if ($gid !== null) {
        if (!grp_member($pdo, $gid, $me)) { return null; }
    } else {
        $h = $pdo->prepare("SELECT 1 FROM messageheader WHERE MHID = :h AND (SenderID = :a OR RecieverID = :b) LIMIT 1");
        $h->execute([':h' => $m['MHID'], ':a' => $me, ':b' => $me]);
        if (!$h->fetchColumn()) { return null; }
    }
    return ['id' => (int) $m['MSID'], 'kind' => $m['Kind'], 'card' => $card];
}
