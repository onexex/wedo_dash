<?php
/* ==========================================================================
   applicants-lib.php  —  rules for job applicants:
     - public registration (apply.php -> query/apply-action.php): the
       applicant fills in a full 201 profile (personal, address, government
       IDs, education, previous job, family / emergency contacts) + resume.
       No login. Spam guards: session token, honeypot, minimum fill time,
       per-address and global hourly limits, optional Cloudflare Turnstile.
     - the HR pipeline (applicants.php -> query/applicants-action.php):
       stages, notes, openings, the rehire check against former employees,
       deleting closed applications (Data Privacy: keep no longer than needed)
     - hiring (apl_hire): one transaction copies the profile into the
       employee tables — employees, empdetails, empdetails2, empprofiles,
       empeducationalbackground, fdetails, accessrights — as a Trainee or an
       Independent Contractor, with a generated EmpID, username and one-time
       temporary password; the matching contract can be emailed
       (includes/applicants-contract.php).
   Resumes, photos and exam result images: uploads/applicants/ (deny-all
   .htaccess), served only by query/applicant-resume.php /
   applicant-photo.php / applicant-file.php. The photo is taken live with the
   camera (sql/2026-10-15-add-applicant-photo.sql) and becomes the profile
   picture on hire. Exam results: table applicant_files
   (sql/2026-10-20-applicants-screening-exam-files.sql).
   Tables: sql/2026-10-14-add-applicants.sql.
   ========================================================================== */
date_default_timezone_set("Asia/Manila");
require_once __DIR__ . '/lifecycle-lib.php';
require_once __DIR__ . '/bot-guard.php';

const APL_STAGES = ['screening' => 'Screening', 'exam' => 'Exam / Assessment', 'interview' => 'Initial interview',
                    'final' => 'Final interview', 'offer' => 'Job offer', 'hired' => 'Hired', 'rejected' => 'Not selected', 'withdrawn' => 'Withdrawn'];
/** Stages no longer used, still named in older history ("New" was the first stage until 2026-10-20). */
const APL_OLD_STAGES = ['new' => 'New'];
const APL_CLOSED       = ['hired', 'rejected', 'withdrawn'];
const APL_PIPELINE     = ['screening', 'exam', 'interview', 'final', 'offer'];   // taken in this order, one step at a time; a new application starts at the first
const APL_HIRE_STAGE   = 'offer';         // Hire unlocks only once the application reaches Job offer
const APL_TRAINEE      = 3;               // empstatus.EmpStatID a new hire starts as
const APL_IC           = 2;               // empstatus.EmpStatID of an Independent Contractor
/** The classifications a new hire can start as, and the contract each one gets (includes/contracts/<kind>.html). */
const APL_HIRE_CLASSES = [APL_TRAINEE => 'trainee', APL_IC => 'independent-contractor'];
const APL_MAX_RESUME   = 5 * 1024 * 1024;
const APL_RESUME_TYPES = ['pdf', 'doc', 'docx'];
const APL_MAX_PHOTO    = 3 * 1024 * 1024;
const APL_PHOTO_PX     = 600;             // the photo is stored as a 600 x 600 JPEG
const APL_MAX_IMAGE    = 10 * 1024 * 1024; // one exam result image, as uploaded
const APL_IMAGE_PX     = 2400;            // exam result images are stored at most 2400 px on the long side
const APL_MAX_EXAM     = 30;              // exam result images per application
const APL_PER_IP_HOUR  = 3;               // applications from one address per hour
const APL_PER_HOUR_ALL = 60;              // all applications per hour (a flood from many addresses)
const APL_MIN_SECONDS  = 20;              // nobody fills the six steps faster than this
const APL_FORM_TTL     = 7200;            // a loaded form expires after 2 hours
const APL_REPEAT_DAYS  = 30;              // same email+position, mobile or resume file: once per 30 days
/** Throwaway-mailbox services (spam uses them; real applicants don't). */
const APL_DISPOSABLE = ['mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'sharklasers.com', '10minutemail.com',
    'tempmail.com', 'temp-mail.org', 'tempmail.net', 'yopmail.com', 'trashmail.com', 'getnada.com', 'maildrop.cc',
    'dispostable.com', 'throwawaymail.com', 'fakeinbox.com', 'mintemail.com', 'mohmal.com', 'emailondeck.com',
    'tempr.email', 'spamgourmet.com', 'mailnesia.com', 'mytemp.email', 'burnermail.io', 'moakt.com'];
const APL_MAX_FAMILY   = 6;
const APL_PROGRAMS     = ['Primary', 'Secondary', 'Tertiary'];
const APL_GENDERS      = ['Female', 'Male'];
const APL_CIVIL        = ['Single', 'Married', 'Divorced', 'Widowed'];

/**
 * Text fields of the public form: post name => [applicants column, max length, required].
 * The lengths fit the employee columns the values are copied to on hire.
 */
const APL_TEXT_FIELDS = [
    'firstName'      => ['FirstName', 80, true],   'middleName'   => ['MiddleName', 80, false],
    'lastName'       => ['LastName', 80, true],    'suffix'       => ['Suffix', 10, false],
    'citizenship'    => ['Citizenship', 60, false], 'religion'    => ['Religion', 60, false],
    'street'         => ['Street', 150, true],     'barangay'     => ['Barangay', 100, true],
    'city'           => ['City', 100, true],       'province'     => ['Province', 100, true],
    'zip'            => ['Zip', 10, false],        'country'      => ['Country', 60, true],
    'passportIssuer' => ['PassportIssuer', 80, false],
    'prevCompany'    => ['PrevCompany', 150, false], 'prevDept'   => ['PrevDept', 100, false],
    'prevPosition'   => ['PrevPosition', 100, false], 'source'    => ['Source', 80, false],
];
/** Government ID numbers (optional): post name => [column, label]. Letters, digits, dashes and spaces only. */
const APL_ID_FIELDS = [
    'sss' => ['SSS', 'SSS'], 'tin' => ['TIN', 'TIN'], 'philhealth' => ['PhilHealth', 'PhilHealth'],
    'pagibig' => ['PagIbig', 'Pag-IBIG'], 'umid' => ['UMID', 'UMID'], 'passportNo' => ['PassportNo', 'passport number'],
];
const APL_LABELS = [
    'firstName' => 'first name', 'lastName' => 'last name', 'street' => 'street address', 'barangay' => 'barangay',
    'city' => 'city / municipality', 'province' => 'province', 'country' => 'country',
];

/* ------------------------------------------------------------------ access */

function apl_can_manage(PDO $pdo) { return lcy_right($pdo, 'applicants'); }

/** Hiring creates a WeDo account, so it also needs "Enroll Employee". */
function apl_can_hire(PDO $pdo) { return apl_can_manage($pdo) && lcy_right($pdo, 'eemployee'); }

/** Per-session token for the HR endpoint. */
function apl_csrf_token() {
    if (empty($_SESSION['apl_csrf'])) { $_SESSION['apl_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['apl_csrf'];
}

/**
 * Applicant times are Manila wall-clock. NOW() and the CURRENT_TIMESTAMP
 * column defaults follow the connection's time zone, and the servers' MySQL
 * runs on UTC — so every applicant connection is switched to Manila first.
 */
function apl_db_clock(PDO $pdo) {
    $pdo->exec("SET time_zone = '" . (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('P') . "'");
}

/** A stage's name, including stages only older history uses. */
function apl_stage_label($s) {
    return APL_STAGES[$s] ?? APL_OLD_STAGES[$s] ?? (string) $s;
}

function apl_ready(PDO $pdo) {
    try {
        $pdo->query("SELECT 1 FROM applicant_family LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

/** Relationship choices for family rows (Maintenance > Relationships). */
function apl_relationships(PDO $pdo) {
    try {
        $r = $pdo->query("SELECT FRelDesc FROM frelationship ORDER BY FRelDesc")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) { $r = []; }
    $r = array_values(array_filter(array_map('trim', $r), 'strlen'));
    return $r ?: ['Father', 'Mother', 'Spouse', 'Sister', 'Brother'];
}

/* ------------------------------------------------------------------ openings */

function apl_openings(PDO $pdo, $onlyOpen = true) {
    $sql = "SELECT o.*, (SELECT COUNT(*) FROM applicants a WHERE a.OpeningID = o.id) AS Applicants
            FROM job_openings o" . ($onlyOpen ? " WHERE o.IsOpen = 1" : "") . " ORDER BY o.IsOpen DESC, o.Title";
    $out = [];
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['id' => (int) $r['id'], 'title' => $r['Title'], 'location' => (string) $r['Location'],
                  'description' => (string) $r['Description'], 'open' => (int) $r['IsOpen'] === 1,
                  'applicants' => (int) $r['Applicants']];
    }
    return $out;
}

/** Add (id 0) or edit an opening. */
function apl_save_opening(PDO $pdo, $id, $title, $location, $description, $open, $actor) {
    $title = lcy_text($title, 120);
    if ($title === '') { return ['ok' => false, 'msg' => 'Enter the job title.']; }
    $args = [':t' => $title, ':l' => lcy_text($location, 120) ?: null,
             ':d' => mb_substr(trim((string) $description), 0, 2000) ?: null, ':o' => $open ? 1 : 0];
    if ((int) $id > 0) {
        $st = $pdo->prepare("UPDATE job_openings SET Title = :t, Location = :l, Description = :d, IsOpen = :o WHERE id = :id");
        $st->execute($args + [':id' => (int) $id]);
        if ($st->rowCount() === 0 && !$pdo->query("SELECT 1 FROM job_openings WHERE id = " . (int) $id)->fetchColumn()) {
            return ['ok' => false, 'msg' => 'That opening no longer exists.'];
        }
    } else {
        $pdo->prepare("INSERT INTO job_openings (Title, Location, Description, IsOpen, CreatedBy) VALUES (:t, :l, :d, :o, :b)")
            ->execute($args + [':b' => $actor]);
        $id = (int) $pdo->lastInsertId();
    }
    lcy_audit($pdo, 'Job opening saved: ' . $title . ($open ? ' (open)' : ' (closed)'));
    return ['ok' => true, 'id' => (int) $id];
}

/* ------------------------------------------------------------------ resume files */

/** uploads/applicants/, created with a deny-all .htaccess on first use. */
function apl_dir(?string $dir = null) {
    $dir = $dir ?? dirname(__DIR__) . '/uploads/applicants';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    if (is_dir($dir) && !is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess',
            "# Applicant resumes: served only through query/applicant-resume.php (HR with the applicants right)\n"
            . "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n"
            . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
        @file_put_contents($dir . '/index.html', '');
    }
    return $dir;
}

/**
 * Check and store a resume ($_FILES entry): PDF or Word, 5 MB at most, the
 * content must match the extension, saved under a random name.
 * $uploaded = false only for tests.
 * @return array{ok:bool, key?:string, name?:string, msg?:string}
 */
function apl_store_resume(array $f, $uploaded = true, ?string $dir = null) {
    require_once __DIR__ . '/msg-files.php';   // mf_store(): type sniffing + random file names
    if ((int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return ['ok' => false, 'msg' => 'Attach your resume (PDF or Word).']; }
    $ext = strtolower(pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($ext, APL_RESUME_TYPES, true)) { return ['ok' => false, 'msg' => 'Your resume must be a PDF or Word file (.pdf, .doc, .docx).']; }
    if ((int) ($f['size'] ?? 0) > APL_MAX_RESUME || (!empty($f['tmp_name']) && @filesize($f['tmp_name']) > APL_MAX_RESUME)) {
        return ['ok' => false, 'msg' => 'Your resume is too large (5 MB at most).'];
    }
    $r = mf_store($f, $uploaded, apl_dir($dir));
    if (!$r['ok']) { return ['ok' => false, 'msg' => $r['error']]; }
    $card = json_decode($r['text'], true);
    return ['ok' => true, 'key' => $card['k'], 'name' => $card['n']];
}

/** Full path of a stored resume, or null when the key isn't one of ours / the file is gone. */
function apl_resume_path($key, ?string $dir = null) {
    if (!is_string($key) || !preg_match('#^\d{4}/\d{2}/[a-f0-9]{32}\.(pdf|doc|docx)$#', $key)) { return null; }
    $path = apl_dir($dir) . '/' . $key;
    return is_file($path) ? $path : null;
}

/** Is applicants.PhotoKey there (sql/2026-10-15-add-applicant-photo.sql)? Until then the Apply page has no photo step. */
function apl_has_photo(PDO $pdo) {
    static $has = null;
    if ($has === null) {
        try { $pdo->query("SELECT PhotoKey FROM applicants LIMIT 1"); $has = true; } catch (Throwable $e) { $has = false; }
    }
    return $has;
}

/**
 * Check and store the applicant's camera photo ($_FILES entry): it must be a
 * real JPEG or PNG; it is re-drawn as a 600 x 600 JPEG (centre square), so
 * nothing of the uploaded file itself is kept — no metadata, no hidden payload.
 * $uploaded = false only for tests.
 * @return array{ok:bool, key?:string, msg?:string}
 */
function apl_store_photo(array $f, $uploaded = true, ?string $dir = null) {
    $retake = ['ok' => false, 'msg' => 'Your photo couldn’t be read — please take it again.'];
    $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_NO_FILE) { return ['ok' => false, 'msg' => 'Take your photo with the camera.']; }
    if ($err !== UPLOAD_ERR_OK || empty($f['tmp_name']) || ($uploaded && !is_uploaded_file($f['tmp_name']))) { return $retake; }
    $size = (int) @filesize($f['tmp_name']);
    if ($size <= 0 || $size > APL_MAX_PHOTO) { return $retake; }
    $info = @getimagesize($f['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) { return $retake; }
    if (min($info[0], $info[1]) < 200 || max($info[0], $info[1]) > 4000) { return $retake; }
    $src = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
    if (!$src) { return $retake; }
    $w = imagesx($src);
    $h = imagesy($src);
    $side = min($w, $h);
    $out = imagecreatetruecolor(APL_PHOTO_PX, APL_PHOTO_PX);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
    imagecopyresampled($out, $src, 0, 0, intdiv($w - $side, 2), intdiv($h - $side, 2), APL_PHOTO_PX, APL_PHOTO_PX, $side, $side);
    unset($src);
    $key  = date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.jpg';
    $path = apl_dir($dir) . '/' . $key;
    if (!is_dir(dirname($path))) { @mkdir(dirname($path), 0775, true); }
    $ok = @imagejpeg($out, $path, 88);
    unset($out);
    return $ok ? ['ok' => true, 'key' => $key] : ['ok' => false, 'msg' => 'Your photo could not be saved — please try again.'];
}

/** Full path of a stored photo, or null when the key isn't one of ours / the file is gone. */
function apl_photo_path($key, ?string $dir = null) {
    if (!is_string($key) || !preg_match('#^\d{4}/\d{2}/[a-f0-9]{32}\.jpg$#', $key)) { return null; }
    $path = apl_dir($dir) . '/' . $key;
    return is_file($path) ? $path : null;
}

/** The new employee's profile picture: the applicant's photo copied to assets/images/profiles/<EmpID>.jpg (EmpPPath). */
function apl_photo_to_profile($key, $empId, ?string $profiles = null) {
    $src = apl_photo_path($key);
    $empId = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $empId);
    if (!$src || $empId === '') { return false; }
    $profiles = $profiles ?? dirname(__DIR__) . '/assets/images/profiles';
    if (!is_dir($profiles)) { @mkdir($profiles, 0775, true); }
    return @copy($src, $profiles . '/' . $empId . '.jpg');
}

/* ------------------------------------------------------------------ exam result images */

function apl_has_files(PDO $pdo) {
    try { $pdo->query("SELECT 1 FROM applicant_files LIMIT 1"); return true; }
    catch (Throwable $e) { return false; }
}

/**
 * Check and store one exam result image ($_FILES entry): JPEG, PNG, WebP or
 * GIF, 10 MB at most. It is re-drawn as a JPEG (at most 2400 px on the long
 * side) so nothing but the picture is kept. $uploaded = false only for tests.
 * @return array{ok:bool, key?:string, w?:int, h?:int, msg?:string}
 */
function apl_store_image(array $f, $uploaded = true, ?string $dir = null) {
    $name = (string) ($f['name'] ?? 'the image');
    $bad = ['ok' => false, 'msg' => '"' . $name . '" is not a picture we can read — use JPG or PNG.'];
    $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) { return ['ok' => false, 'msg' => '"' . $name . '" is too large (10 MB at most).']; }
    if ($err !== UPLOAD_ERR_OK || empty($f['tmp_name']) || ($uploaded && !is_uploaded_file($f['tmp_name']))) { return $bad; }
    $size = (int) @filesize($f['tmp_name']);
    if ($size > APL_MAX_IMAGE) { return ['ok' => false, 'msg' => '"' . $name . '" is too large (10 MB at most).']; }
    $info = @getimagesize($f['tmp_name']);
    $types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF];
    if (defined('IMAGETYPE_WEBP')) { $types[] = IMAGETYPE_WEBP; }
    if ($size <= 0 || !$info || !in_array($info[2], $types, true)) { return $bad; }
    if ($info[0] < 50 || $info[1] < 50 || $info[0] * $info[1] > 40000000) { return $bad; }   // 40 megapixels: no memory bombs
    $src = @imagecreatefromstring((string) file_get_contents($f['tmp_name']));
    if (!$src) { return $bad; }
    $w = imagesx($src);
    $h = imagesy($src);
    $k = min(1, APL_IMAGE_PX / max($w, $h));
    $nw = max(1, (int) round($w * $k));
    $nh = max(1, (int) round($h * $k));
    $out = imagecreatetruecolor($nw, $nh);
    imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));   // transparent PNGs on white
    imagecopyresampled($out, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    unset($src);
    $key  = date('Y/m') . '/' . bin2hex(random_bytes(16)) . '.jpg';
    $path = apl_dir($dir) . '/' . $key;
    if (!is_dir(dirname($path))) { @mkdir(dirname($path), 0775, true); }
    $ok = @imagejpeg($out, $path, 88);
    unset($out);
    return $ok ? ['ok' => true, 'key' => $key, 'w' => $nw, 'h' => $nh] : ['ok' => false, 'msg' => '"' . $name . '" could not be saved — please try again.'];
}

/** An application's exam result images, oldest first. */
function apl_exam_files(PDO $pdo, $id) {
    if (!apl_has_files($pdo)) { return []; }
    $st = $pdo->prepare("SELECT f.id, f.Caption, f.Width, f.Height, f.CreatedAt, b.EmpFN, b.EmpLN
                         FROM applicant_files f LEFT JOIN employees b ON b.EmpID = f.ByEmpID
                         WHERE f.ApplicantID = :a AND f.Kind = 'exam' ORDER BY f.CreatedAt, f.id");
    $st->execute([':a' => (int) $id]);
    return array_map(function ($f) {
        return ['id' => (int) $f['id'], 'caption' => (string) $f['Caption'], 'w' => (int) $f['Width'], 'h' => (int) $f['Height'],
                'at' => $f['CreatedAt'], 'by' => trim(($f['EmpFN'] ?? '') . ' ' . ($f['EmpLN'] ?? ''))];
    }, $st->fetchAll(PDO::FETCH_ASSOC));
}

/** Exam results can be added once the application reaches the exam, and kept after it closes. */
function apl_exam_open($stage) {
    $i = array_search($stage, APL_PIPELINE, true);
    return $i === false ? in_array($stage, APL_CLOSED, true) : $i >= array_search('exam', APL_PIPELINE, true);
}

/**
 * Add exam result images to an application. $files: $_FILES entries (one per
 * image). All are checked before any is kept: one bad file adds none.
 * @return array{ok:bool, msg?:string, added?:int}
 */
function apl_add_exam_files(PDO $pdo, $id, array $files, $caption, $actor, $uploaded = true) {
    if (!apl_has_files($pdo)) { return ['ok' => false, 'msg' => 'Exam results are not set up on this server yet (database update pending).']; }
    $r = apl_row($pdo, $id);
    if (!$r) { return ['ok' => false, 'msg' => 'That application no longer exists.']; }
    if (!apl_exam_open($r['Stage'])) { return ['ok' => false, 'msg' => 'Move the application to "' . APL_STAGES['exam'] . '" first.']; }
    $files = array_values(array_filter($files, function ($f) { return (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE; }));
    if (!$files) { return ['ok' => false, 'msg' => 'Choose the picture(s) of the exam result.']; }
    $have = count(apl_exam_files($pdo, $id));
    if ($have + count($files) > APL_MAX_EXAM) { return ['ok' => false, 'msg' => 'An application keeps at most ' . APL_MAX_EXAM . ' exam images (' . $have . ' already).']; }
    $stored = [];
    foreach ($files as $f) {
        $s = apl_store_image($f, $uploaded);
        if (!$s['ok']) {
            foreach ($stored as $k) { @unlink(apl_photo_path($k['key'])); }
            return ['ok' => false, 'msg' => $s['msg']];
        }
        $stored[] = $s;
    }
    $caption = lcy_text($caption, 150);
    $ins = $pdo->prepare("INSERT INTO applicant_files (ApplicantID, Kind, FileKey, Caption, Width, Height, ByEmpID)
                          VALUES (:a, 'exam', :k, :c, :w, :h, :b)");
    foreach ($stored as $s) {
        $ins->execute([':a' => (int) $id, ':k' => $s['key'], ':c' => $caption === '' ? null : $caption, ':w' => $s['w'], ':h' => $s['h'], ':b' => $actor]);
    }
    $n = count($stored);
    apl_event($pdo, $id, $r['Stage'], $r['Stage'], 'Exam result uploaded (' . $n . ' image' . ($n === 1 ? '' : 's') . ')' . ($caption !== '' ? ': ' . $caption : ''), $actor);
    lcy_audit($pdo, 'Applicant #' . (int) $id . ': ' . $n . ' exam image(s) added');
    return ['ok' => true, 'added' => $n];
}

/** Path of one exam result image of an application, or null. */
function apl_exam_file_path(PDO $pdo, $fileId) {
    if (!apl_has_files($pdo)) { return null; }
    $st = $pdo->prepare("SELECT FileKey FROM applicant_files WHERE id = :f");
    $st->execute([':f' => (int) $fileId]);
    return apl_photo_path($st->fetchColumn());
}

function apl_delete_exam_file(PDO $pdo, $id, $fileId, $actor) {
    if (!apl_has_files($pdo)) { return ['ok' => false, 'msg' => 'That image no longer exists.']; }
    $st = $pdo->prepare("SELECT f.FileKey, a.Stage FROM applicant_files f JOIN applicants a ON a.id = f.ApplicantID
                         WHERE f.id = :f AND f.ApplicantID = :a");
    $st->execute([':f' => (int) $fileId, ':a' => (int) $id]);
    $f = $st->fetch(PDO::FETCH_ASSOC);
    if (!$f) { return ['ok' => false, 'msg' => 'That image no longer exists.']; }
    $pdo->prepare("DELETE FROM applicant_files WHERE id = :f")->execute([':f' => (int) $fileId]);
    $path = apl_photo_path($f['FileKey']);
    if ($path) { @unlink($path); }
    apl_event($pdo, $id, $f['Stage'], $f['Stage'], 'Exam result image removed', $actor);
    return ['ok' => true];
}

/* ------------------------------------------------------------------ public registration */

/** The visitor's address — see includes/bot-guard.php. */
function apl_client_ip() { return wd_client_ip(); }

/** Turnstile keys (config.local.php turnstile_site / turnstile_secret), or null — see includes/bot-guard.php. */
function apl_turnstile() { return wd_turnstile(); }
/** Token + render time for the public form (a bot posting straight away is refused). */
function apl_public_token() {
    if (empty($_SESSION['apl_pub'])) { $_SESSION['apl_pub'] = bin2hex(random_bytes(16)); }
    $_SESSION['apl_pub_at'] = time();
    return $_SESSION['apl_pub'];
}

/** Is applicants.ResumeHash there (sql/2026-10-14-add-bot-protection.sql)? */
function apl_has_resume_hash(PDO $pdo) {
    static $has = null;
    if ($has === null) {
        try { $pdo->query("SELECT ResumeHash FROM applicants LIMIT 1"); $has = true; } catch (Throwable $e) { $has = false; }
    }
    return $has;
}

/** A Philippine mobile number as typed (09xx xxx xxxx / +63 9xx …): the digits, or false. */
function apl_ph_mobile($v) {
    $d = preg_replace('/[\s\-()]/', '', (string) $v);
    if (preg_match('/^(?:\+?63|0)(9\d{9})$/', $d, $m)) { return '0' . $m[1]; }
    return false;
}

/** Spam signals in the posted text: a web link, or a name that isn't a name. Returns a message or ''. */
function apl_spam_signal(array $p) {
    foreach ($p as $k => $val) {
        if (in_array($k, ['token', 'cf-turnstile-response', 'email'], true)) { continue; }
        $flat = is_array($val) ? json_encode($val, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $val;
        if (preg_match('~(https?://|www\.|\b[a-z0-9-]+\.(com|net|org|xyz|ru|top|info|biz|io|co|site|online|click|link)\b(?!\.))~i', $flat)) {
            return 'Please remove web links from the form.';
        }
    }
    foreach (['firstName' => 'first name', 'middleName' => 'middle name', 'lastName' => 'last name'] as $k => $label) {
        $v = trim((string) ($p[$k] ?? ''));
        if ($v !== '' && !preg_match("/^\p{L}[\p{L}\p{M} .'\-]*$/u", $v)) { return "Check your $label — letters only."; }
    }
    $suffix = trim((string) ($p['suffix'] ?? ''));
    if ($suffix !== '' && !preg_match('/^(jr|sr|ii|iii|iv|v|vi)\.?$/i', $suffix)) { return 'Check your suffix (e.g. Jr, Sr, III).'; }
    return '';
}

/** A phone number as typed: digits, spaces, + ( ) - only. */
function apl_phone($v, $required) {
    $v = lcy_text($v, 30);
    if ($v === '') { return $required ? false : null; }
    return preg_match('/^[0-9+()\-\s]{7,30}$/', $v) ? $v : false;
}

/** Year typed for a school: '' or 4 digits between 1940 and five years from now. */
function apl_year($v) {
    $v = trim((string) $v);
    if ($v === '') { return null; }
    return (preg_match('/^\d{4}$/', $v) && (int) $v >= 1940 && (int) $v <= (int) date('Y') + 5) ? $v : false;
}

/**
 * Validate the posted registration into DB-ready rows.
 * @return array{ok:bool, msg?:string, row?:array, edu?:array, fam?:array}
 */
function apl_validate(PDO $pdo, array $p) {
    // nobody's name, address or school contains < or > — refusing them keeps markup out of
    // the employee records the registration is copied into (older pages print some unescaped)
    foreach ($p as $k => $val) {
        if ($k === 'token' || $k === 'cf-turnstile-response') { continue; }
        $flat = is_array($val) ? json_encode($val) : (string) $val;
        if (strpbrk($flat, '<>') !== false) { return ['ok' => false, 'msg' => 'Please remove the < and > characters from the form.']; }
    }
    $spam = apl_spam_signal($p);
    if ($spam !== '') { return ['ok' => false, 'msg' => $spam]; }
    $row = [];
    foreach (APL_TEXT_FIELDS as $k => [$col, $max, $req]) {
        $v = lcy_text($p[$k] ?? '', $max);
        if ($req && $v === '') { return ['ok' => false, 'msg' => 'Enter your ' . (APL_LABELS[$k] ?? $k) . '.']; }
        $row[$col] = $v === '' ? null : $v;
    }
    foreach (APL_ID_FIELDS as $k => [$col, $label]) {
        $v = lcy_text($p[$k] ?? '', 30);
        if ($v !== '' && !preg_match('/^[0-9A-Za-z][0-9A-Za-z\- ]{3,29}$/', $v)) { return ['ok' => false, 'msg' => "Check your $label."]; }
        $row[$col] = $v === '' ? null : $v;
    }

    $birth = lcy_date($p['birthDate'] ?? '');
    if ($birth === null) { return ['ok' => false, 'msg' => 'Enter your date of birth.']; }
    $age = (int) date_diff(date_create($birth), date_create('today'))->y;
    if ($birth > date('Y-m-d') || $age < 15 || $age > 80) { return ['ok' => false, 'msg' => 'Check your date of birth.']; }
    $row['BirthDate'] = $birth;
    foreach (['passportExpiry' => 'PassportExpiry', 'prevStart' => 'PrevStart'] as $k => $col) {
        $raw = trim((string) ($p[$k] ?? ''));
        $d = lcy_date($raw);
        if ($raw !== '' && $d === null) { return ['ok' => false, 'msg' => 'Check the dates you entered.']; }
        $row[$col] = $d;
    }
    if (!in_array($p['gender'] ?? '', APL_GENDERS, true)) { return ['ok' => false, 'msg' => 'Choose your gender.']; }
    if (!in_array($p['civilStatus'] ?? '', APL_CIVIL, true)) { return ['ok' => false, 'msg' => 'Choose your civil status.']; }
    $row['Gender'] = $p['gender'];
    $row['CivilStatus'] = $p['civilStatus'];

    $email = trim((string) ($p['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) { return ['ok' => false, 'msg' => 'Enter a valid email address.']; }
    $domain = strtolower(substr(strrchr($email, '@'), 1));
    foreach (APL_DISPOSABLE as $d) {
        if ($domain === $d || substr($domain, -strlen($d) - 1) === '.' . $d) {
            return ['ok' => false, 'msg' => 'Please use your own email address, not a temporary one.'];
        }
    }
    $row['Email'] = $email;
    $mobile = apl_ph_mobile($p['mobile'] ?? '');
    $home   = apl_phone($p['homePhone'] ?? '', false);
    if ($mobile === false) { return ['ok' => false, 'msg' => 'Enter a valid mobile number (e.g. 0917 123 4567).']; }
    if ($home === false) { return ['ok' => false, 'msg' => 'Check your home phone number.']; }
    $row['Mobile'] = $mobile;
    $row['HomePhone'] = $home;

    $edu = [];
    foreach (APL_PROGRAMS as $prog) {
        $e = (array) ($p['edu'][$prog] ?? []);
        $school = lcy_text($e['school'] ?? '', 100);
        $from = apl_year($e['from'] ?? '');
        $to   = apl_year($e['to'] ?? '');
        if ($from === false || $to === false || ($from && $to && $to < $from)) {
            return ['ok' => false, 'msg' => "Check the school years under $prog education."];
        }
        $edu[$prog] = ['School' => $school ?: null, 'YearStarted' => $from, 'YearEnded' => $to,
                       'Address' => lcy_text($e['address'] ?? '', 100) ?: null];
    }
    if (!array_filter(array_column($edu, 'School'))) { return ['ok' => false, 'msg' => 'Enter at least one school you attended.']; }

    $rels = array_map('strtolower', apl_relationships($pdo));
    $fam = [];
    foreach (array_slice(array_values((array) ($p['fam'] ?? [])), 0, APL_MAX_FAMILY) as $f) {
        $f = (array) $f;
        $name = lcy_text($f['name'] ?? '', 150);
        if ($name === '') { continue; }
        if (!preg_match("/^\p{L}[\p{L}\p{M} .'\-]*$/u", $name)) { return ['ok' => false, 'msg' => "Check the name \"$name\" — letters only."]; }
        if (count(preg_split('/\s+/u', $name)) < 2) { return ['ok' => false, 'msg' => "Enter the first and last name of \"$name\"."]; }
        $rel = lcy_text($f['relationship'] ?? '', 50);
        if (!in_array(strtolower($rel), $rels, true)) { return ['ok' => false, 'msg' => "Choose the relationship of $name."]; }
        // family contacts are mobile numbers too, stored as 09xxxxxxxxx
        $rawContact = trim((string) ($f['contact'] ?? ''));
        $contact = $rawContact === '' ? null : apl_ph_mobile($rawContact);
        if ($contact === false) { return ['ok' => false, 'msg' => "Enter an 11-digit mobile number for $name (e.g. 0917 123 4567)."]; }
        $fam[] = ['Name' => $name, 'Relationship' => $rel, 'Address' => lcy_text($f['address'] ?? '', 250) ?: null,
                  'Contact' => $contact, 'Emergency' => !empty($f['emergency']) ? 1 : 0];
    }
    $ice = array_filter($fam, function ($f) { return $f['Emergency'] && $f['Contact']; });
    if (!$ice) { return ['ok' => false, 'msg' => 'Add at least one emergency contact with a contact number.']; }

    return ['ok' => true, 'row' => $row, 'edu' => $edu, 'fam' => $fam];
}

/**
 * Save a public registration. $p = posted fields, $file = the resume, $photo = the
 * camera photo (optional; when sent it must be a real picture).
 * @return array{ok:bool, msg?:string, id?:int, code?:int}
 */
function apl_submit(PDO $pdo, array $p, ?array $file, $ip, $uploaded = true, ?array $photo = null) {
    $tok = (string) ($p['token'] ?? '');
    if ($tok === '' || empty($_SESSION['apl_pub']) || !hash_equals($_SESSION['apl_pub'], $tok)) {
        return ['ok' => false, 'msg' => 'This form has expired — reload the page and try again.', 'code' => 419];
    }
    if (trim((string) ($p['website'] ?? '')) !== '') {   // honeypot: people never see this field
        return ['ok' => true, 'id' => 0];                 // pretend it worked, keep nothing
    }
    $age = time() - (int) ($_SESSION['apl_pub_at'] ?? 0);
    if ($age < APL_MIN_SECONDS) {
        return ['ok' => false, 'msg' => 'That was quick — please check your details and send again.'];
    }
    if ($age > APL_FORM_TTL) {
        return ['ok' => false, 'msg' => 'This form has expired — reload the page and try again.', 'code' => 419];
    }
    $st = $pdo->prepare("SELECT COUNT(*) FROM applicants WHERE IP = :ip AND CreatedAt > (NOW() - INTERVAL 1 HOUR)");
    $st->execute([':ip' => $ip]);
    if ((int) $st->fetchColumn() >= APL_PER_IP_HOUR) {
        return ['ok' => false, 'msg' => 'Too many applications from this connection — please try again in an hour.', 'code' => 429];
    }
    $all = (int) $pdo->query("SELECT COUNT(*) FROM applicants WHERE CreatedAt > (NOW() - INTERVAL 1 HOUR)")->fetchColumn();
    if ($all >= APL_PER_HOUR_ALL) {
        return ['ok' => false, 'msg' => 'We are receiving a lot of applications right now — please try again later.', 'code' => 429];
    }
    // 'unreachable' (Cloudflare down) lets a real applicant through; the limits above still apply
    if (wd_turnstile_check($p['cf-turnstile-response'] ?? '', $ip) === 'bad') {
        return ['ok' => false, 'msg' => 'Please complete the "I am human" check and send again.'];
    }

    $v = apl_validate($pdo, $p);
    if (!$v['ok']) { return $v; }
    if (empty($p['consent'])) { return ['ok' => false, 'msg' => 'Please agree to the Data Privacy notice so we can process your application.']; }

    $opening = (int) ($p['opening'] ?? 0);
    if ($opening > 0) {
        $st = $pdo->prepare("SELECT IsOpen FROM job_openings WHERE id = :id");
        $st->execute([':id' => $opening]);
        if ((int) $st->fetchColumn() !== 1) { return ['ok' => false, 'msg' => 'That position is no longer open — please choose another.']; }
    }
    $recent = "CreatedAt > (NOW() - INTERVAL " . APL_REPEAT_DAYS . " DAY)";
    $st = $pdo->prepare("SELECT COUNT(*) FROM applicants WHERE Email = :e AND COALESCE(OpeningID, 0) = :o AND $recent");
    $st->execute([':e' => $v['row']['Email'], ':o' => $opening]);
    if ((int) $st->fetchColumn() > 0) {
        return ['ok' => false, 'msg' => 'You already applied for this position recently — we will contact you.'];
    }
    // one person (or bot) re-sending under different emails: same mobile or the very same resume file
    $st = $pdo->prepare("SELECT COUNT(*) FROM applicants WHERE Mobile = :m AND COALESCE(OpeningID, 0) = :o AND $recent");
    $st->execute([':m' => $v['row']['Mobile'], ':o' => $opening]);
    if ((int) $st->fetchColumn() > 0) {
        return ['ok' => false, 'msg' => 'An application with this mobile number was already received — we will contact you.'];
    }
    $hash = (!empty($file['tmp_name']) && is_file($file['tmp_name'])) ? sha1_file($file['tmp_name']) : null;
    if ($hash && apl_has_resume_hash($pdo)) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM applicants WHERE ResumeHash = :h AND $recent");
        $st->execute([':h' => $hash]);
        if ((int) $st->fetchColumn() > 0) {
            return ['ok' => false, 'msg' => 'This resume was already sent recently — we will contact you.'];
        }
    }

    $resume = apl_store_resume($file ?? ['error' => UPLOAD_ERR_NO_FILE], $uploaded);
    if (!$resume['ok']) { return ['ok' => false, 'msg' => $resume['msg']]; }
    $pic = null;
    if ($photo && (int) ($photo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE && apl_has_photo($pdo)) {
        $pic = apl_store_photo($photo, $uploaded);
        if (!$pic['ok']) {
            @unlink((string) apl_resume_path($resume['key']));
            return ['ok' => false, 'msg' => $pic['msg']];
        }
    }

    $row = $v['row'] + ['OpeningID' => $opening ?: null, 'ResumeKey' => $resume['key'], 'ResumeName' => mb_substr($resume['name'], 0, 120),
                        'IP' => $ip, 'Stage' => APL_PIPELINE[0]];
    if ($hash && apl_has_resume_hash($pdo)) { $row['ResumeHash'] = $hash; }
    if ($pic) { $row['PhotoKey'] = $pic['key']; }
    $cols = array_keys($row);
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO applicants (" . implode(', ', $cols) . ", ConsentAt) VALUES (:" . implode(', :', $cols) . ", NOW())")
            ->execute(array_combine(array_map(function ($c) { return ':' . $c; }, $cols), array_values($row)));
        $id = (int) $pdo->lastInsertId();
        $ins = $pdo->prepare("INSERT INTO applicant_education (ApplicantID, Program, School, YearStarted, YearEnded, Address)
                              VALUES (:a, :p, :s, :f, :t, :ad)");
        foreach ($v['edu'] as $prog => $e) {
            $ins->execute([':a' => $id, ':p' => $prog, ':s' => $e['School'], ':f' => $e['YearStarted'], ':t' => $e['YearEnded'], ':ad' => $e['Address']]);
        }
        $ins = $pdo->prepare("INSERT INTO applicant_family (ApplicantID, Name, Relationship, Address, Contact, Emergency)
                              VALUES (:a, :n, :r, :ad, :c, :e)");
        foreach ($v['fam'] as $f) {
            $ins->execute([':a' => $id, ':n' => $f['Name'], ':r' => $f['Relationship'], ':ad' => $f['Address'], ':c' => $f['Contact'], ':e' => $f['Emergency']]);
        }
        $pdo->prepare("INSERT INTO applicant_events (ApplicantID, FromStage, ToStage, Notes) VALUES (:a, NULL, '" . APL_PIPELINE[0] . "', 'Registered online')")
            ->execute([':a' => $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        foreach ([apl_resume_path($resume['key']), $pic ? apl_photo_path($pic['key']) : null] as $path) {
            if ($path) { @unlink($path); }
        }
        throw $e;
    }
    unset($_SESSION['apl_pub']);   // one application per form load
    return ['ok' => true, 'id' => $id];
}

/** Holders of the `applicants` right (push notification on a new application). */
function apl_reviewers(PDO $pdo) {
    try {
        return $pdo->query("SELECT EmpID FROM accessrights WHERE applicants = 2")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return [];
    }
}

/* ------------------------------------------------------------------ HR pipeline */

function apl_name(array $r) {
    return lcy_full_name(['EmpFN' => $r['FirstName'], 'EmpMN' => $r['MiddleName'], 'EmpLN' => $r['LastName'], 'EmpSuffix' => $r['Suffix']]);
}

/** List row: what the pipeline table shows. */
function apl_out(array $r) {
    return [
        'id' => (int) $r['id'], 'name' => apl_name($r), 'first' => $r['FirstName'], 'last' => $r['LastName'],
        'birthDate' => $r['BirthDate'], 'email' => $r['Email'], 'mobile' => $r['Mobile'], 'city' => (string) $r['City'],
        'openingId' => $r['OpeningID'] === null ? null : (int) $r['OpeningID'], 'opening' => (string) ($r['OpeningTitle'] ?? ''),
        'stage' => $r['Stage'], 'stageLabel' => apl_stage_label($r['Stage']), 'stageAt' => $r['StageAt'],
        'appliedAt' => $r['CreatedAt'], 'empId' => $r['EmpID'], 'hiredAt' => $r['HiredAt'],
        'hasPhoto' => !empty($r['PhotoKey']),
    ];
}

/** Applications, newest first. Filters: stage ('' = in progress, 'all', or one stage), opening, q (name / email). */
function apl_list(PDO $pdo, array $f = []) {
    $where = [];
    $args  = [];
    $stage = (string) ($f['stage'] ?? '');
    if ($stage === '') {
        $where[] = "a.Stage NOT IN ('" . implode("','", APL_CLOSED) . "')";
    } elseif ($stage !== 'all' && isset(APL_STAGES[$stage])) {
        $where[] = 'a.Stage = :s';
        $args[':s'] = $stage;
    }
    if (!empty($f['opening'])) {
        if ((string) $f['opening'] === 'any') { $where[] = 'a.OpeningID IS NULL'; }
        else { $where[] = 'a.OpeningID = :o'; $args[':o'] = (int) $f['opening']; }
    }
    $words = array_slice(array_filter(preg_split('/\s+/u', trim((string) ($f['q'] ?? ''))), 'strlen'), 0, 4);
    foreach ($words as $i => $w) {
        $where[] = "(a.FirstName LIKE :w{$i}a OR a.LastName LIKE :w{$i}b OR a.Email LIKE :w{$i}c)";
        $args[":w{$i}a"] = $args[":w{$i}b"] = $args[":w{$i}c"] = '%' . addcslashes($w, '%_\\') . '%';
    }
    $sql = "SELECT a.*, o.Title AS OpeningTitle FROM applicants a LEFT JOIN job_openings o ON o.id = a.OpeningID"
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY a.CreatedAt DESC, a.id DESC LIMIT 200";
    $st = $pdo->prepare($sql);
    $st->execute($args);
    $out = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $a = apl_out($r);
        $a['rehire'] = apl_rehire_flag(apl_rehire_check($pdo, $a));
        $out[] = $a;
    }
    return $out;
}

/** Applications per stage (for the filter chips). */
function apl_stage_counts(PDO $pdo) {
    $c = array_fill_keys(array_keys(APL_STAGES), 0);
    foreach ($pdo->query("SELECT Stage, COUNT(*) n FROM applicants GROUP BY Stage")->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $n) {
        if (isset($c[$k])) { $c[$k] = (int) $n; }
    }
    return $c;
}

/** The raw applicants row (all profile columns) or null. */
function apl_row(PDO $pdo, $id) {
    $st = $pdo->prepare("SELECT a.*, o.Title AS OpeningTitle FROM applicants a LEFT JOIN job_openings o ON o.id = a.OpeningID WHERE a.id = :id");
    $st->execute([':id' => (int) $id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ?: null;
}

function apl_get(PDO $pdo, $id) {
    $r = apl_row($pdo, $id);
    return $r ? apl_out($r) : null;
}

function apl_education(PDO $pdo, $id) {
    $st = $pdo->prepare("SELECT Program, School, YearStarted, YearEnded, Address FROM applicant_education WHERE ApplicantID = :a ORDER BY id");
    $st->execute([':a' => (int) $id]);
    $by = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $by[$r['Program']] = $r; }
    $out = [];
    foreach (APL_PROGRAMS as $p) {
        $out[] = $by[$p] ?? ['Program' => $p, 'School' => null, 'YearStarted' => null, 'YearEnded' => null, 'Address' => null];
    }
    return $out;
}

function apl_family(PDO $pdo, $id) {
    $st = $pdo->prepare("SELECT Name, Relationship, Address, Contact, Emergency FROM applicant_family WHERE ApplicantID = :a ORDER BY id");
    $st->execute([':a' => (int) $id]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** One application: profile, education, family, history and the rehire check. */
function apl_detail(PDO $pdo, $id) {
    $r = apl_row($pdo, $id);
    if (!$r) { return null; }
    $a = apl_out($r);
    $profile = [];
    foreach (['MiddleName', 'Suffix', 'Gender', 'CivilStatus', 'Citizenship', 'Religion', 'HomePhone', 'Street', 'Barangay',
              'City', 'Province', 'Zip', 'Country', 'SSS', 'TIN', 'PhilHealth', 'PagIbig', 'UMID', 'PassportNo', 'PassportExpiry',
              'PassportIssuer', 'PrevCompany', 'PrevStart', 'PrevDept', 'PrevPosition', 'Source', 'ConsentAt'] as $c) {
        $profile[$c] = $r[$c];
    }
    $a['profile']    = $profile;
    $a['hasResume']  = (string) $r['ResumeKey'] !== '';
    $a['resumeName'] = (string) $r['ResumeName'];
    $a['education']  = apl_education($pdo, $a['id']);
    $a['family']     = apl_family($pdo, $a['id']);
    $a['examFiles']  = apl_exam_files($pdo, $a['id']);
    $a['examOpen']   = apl_has_files($pdo) && apl_exam_open($a['stage']);

    $st = $pdo->prepare("SELECT ev.*, b.EmpFN, b.EmpLN FROM applicant_events ev LEFT JOIN employees b ON b.EmpID = ev.ByEmpID
                         WHERE ev.ApplicantID = :a ORDER BY ev.CreatedAt, ev.id");
    $st->execute([':a' => $a['id']]);
    $a['events'] = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $e) {
        $a['events'][] = ['from' => $e['FromStage'], 'to' => $e['ToStage'], 'toLabel' => apl_stage_label($e['ToStage']),
                          'fromLabel' => $e['FromStage'] !== null ? apl_stage_label($e['FromStage']) : '',
                          'notes' => (string) $e['Notes'], 'by' => trim(($e['EmpFN'] ?? '') . ' ' . ($e['EmpLN'] ?? '')) ?: 'Applicant',
                          'at' => $e['CreatedAt']];
    }
    $a['matches'] = apl_rehire_check($pdo, $a);
    $a['rehire']  = apl_rehire_flag($a['matches']);
    $a['allowedStages'] = apl_allowed_stages($a['stage'], apl_reopen_stage($pdo, $a['id'], $a['stage']));
    return $a;
}

/**
 * Has this applicant worked here before? Employees with the same first +
 * last name (a "strong" match when the birth date matches too), and legacy
 * AMS archive rows with the same name.
 */
function apl_rehire_check(PDO $pdo, array $a) {
    $out = [];
    $st = $pdo->prepare("SELECT e.EmpID, pr.EmpDOB FROM employees e
                         LEFT JOIN empprofiles pr ON pr.EmpID = e.EmpID
                         WHERE LOWER(TRIM(e.EmpFN)) = LOWER(:f) AND LOWER(TRIM(e.EmpLN)) = LOWER(:l) AND e.EmpID <> 'admin'");
    $st->execute([':f' => trim($a['first']), ':l' => trim($a['last'])]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if ($a['empId'] !== null && (string) $r['EmpID'] === (string) $a['empId']) { continue; }   // their own new account
        $emp = lcy_employee($pdo, $r['EmpID']);
        $sep = $emp && $emp['separated'] ? lcy_latest_separation($pdo, $r['EmpID']) : null;
        $out[] = [
            'source' => 'wedo', 'empId' => $r['EmpID'], 'name' => $emp ? $emp['name'] : $r['EmpID'],
            'strong' => lcy_date($r['EmpDOB']) !== null && lcy_date($r['EmpDOB']) === $a['birthDate'],
            'status' => $emp ? $emp['status'] : '', 'position' => $emp ? $emp['position'] : '',
            'hired' => $emp ? $emp['hired'] : null, 'lastDay' => $sep ? $sep['lastDay'] : null,
            'separation' => $sep ? $sep['typeLabel'] : null, 'reason' => $sep ? $sep['reason'] : '',
            'rehire' => $sep ? $sep['rehire'] : ($emp && $emp['separated'] ? 'review' : null),
            'rehireNote' => $sep ? $sep['rehireNote'] : '',
        ];
    }
    $st = $pdo->prepare("SELECT * FROM amsarchive WHERE LOWER(TRIM(fname)) = LOWER(:f) AND LOWER(TRIM(lname)) = LOWER(:l) LIMIT 5");
    $st->execute([':f' => trim($a['first']), ':l' => trim($a['last'])]);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $out[] = ['source' => 'ams', 'amsId' => (int) $r['id'], 'name' => trim($r['fname'] . ' ' . $r['lname']), 'strong' => false,
                  'status' => (string) $r['employmentstatus'], 'position' => (string) $r['pos'],
                  'hired' => (string) $r['empdatesfrom'], 'lastDay' => (string) $r['empdatesto'],
                  'separation' => null, 'reason' => (string) $r['reasonforleaving'], 'derogatory' => (string) $r['derogatoryrecords'],
                  'rehire' => null, 'rehireNote' => ''];
    }
    return $out;
}

/** The worst rehire signal among the matches: no > review > yes > former > null (no record). */
function apl_rehire_flag(array $matches) {
    if (!$matches) { return null; }
    $rank = ['no' => 4, 'review' => 3, 'yes' => 2];
    $best = 'former';
    $bestRank = 1;
    foreach ($matches as $m) {
        $r = $rank[$m['rehire'] ?? ''] ?? 1;
        if ($r > $bestRank) { $best = $m['rehire']; $bestRank = $r; }
    }
    return $best;
}

function apl_event(PDO $pdo, $id, $from, $to, $notes, $actor) {
    $pdo->prepare("INSERT INTO applicant_events (ApplicantID, FromStage, ToStage, Notes, ByEmpID) VALUES (:a, :f, :t, :n, :b)")
        ->execute([':a' => (int) $id, ':f' => $from, ':t' => $to, ':n' => $notes === '' ? null : mb_substr($notes, 0, 1000), ':b' => $actor]);
}

/**
 * The stage a closed (Not selected / Withdrawn) application had reached: it can be
 * reopened there or earlier, never further. The first stage when the history doesn't say.
 */
function apl_reopen_stage(PDO $pdo, $id, $stage) {
    if (!in_array($stage, ['rejected', 'withdrawn'], true)) { return null; }
    $st = $pdo->prepare("SELECT FromStage FROM applicant_events WHERE ApplicantID = :a AND ToStage = :s AND FromStage <> ToStage
                         ORDER BY CreatedAt DESC, id DESC LIMIT 1");
    $st->execute([':a' => (int) $id, ':s' => $stage]);
    $from = (string) $st->fetchColumn();
    return in_array($from, APL_PIPELINE, true) ? $from : APL_PIPELINE[0];
}

/**
 * Stages an application may move to from $stage (besides adding a note):
 * forward one step at a time, back to any earlier stage, Not selected /
 * Withdrawn at any point; a closed one reopens at $reopen or earlier.
 * Hired only comes from apl_hire().
 */
function apl_allowed_stages($stage, $reopen = null) {
    if ($stage === 'hired') { return []; }
    $i = array_search($stage, APL_PIPELINE, true);
    $upto = $i === false ? array_search($reopen ?: APL_PIPELINE[0], APL_PIPELINE, true) : min($i + 1, count(APL_PIPELINE) - 1);
    return array_merge(array_slice(APL_PIPELINE, 0, $upto + 1), ['rejected', 'withdrawn']);
}

/** Move an application to another stage, or add a note (same stage). Hired only happens through apl_hire(). */
function apl_set_stage(PDO $pdo, $id, $stage, $notes, $actor) {
    $a = apl_get($pdo, $id);
    if (!$a) { return ['ok' => false, 'msg' => 'That application no longer exists.']; }
    if (!isset(APL_STAGES[$stage])) { return ['ok' => false, 'msg' => 'Unknown stage.']; }
    if ($stage === 'hired') { return ['ok' => false, 'msg' => 'Use "Hire" — it creates the WeDo account and marks the applicant Hired.']; }
    if ($a['stage'] === 'hired') { return ['ok' => false, 'msg' => 'This applicant was already hired.']; }
    $notes = trim((string) $notes);
    if ($stage === $a['stage'] && $notes === '') { return ['ok' => false, 'msg' => 'Write a note, or choose a different stage.']; }
    if ($stage !== $a['stage']) {
        $reopen = apl_reopen_stage($pdo, $a['id'], $a['stage']);
        if (!in_array($stage, apl_allowed_stages($a['stage'], $reopen), true)) {
            if ($reopen !== null) {
                return ['ok' => false, 'msg' => 'Reopen it at "' . APL_STAGES[$reopen] . '" (where it was) or an earlier stage.'];
            }
            $next = APL_PIPELINE[array_search($a['stage'], APL_PIPELINE, true) + 1];
            return ['ok' => false, 'msg' => 'Stages go in order — the next one is "' . APL_STAGES[$next] . '".'];
        }
    }
    if ($stage !== $a['stage']) {
        $pdo->prepare("UPDATE applicants SET Stage = :s, StageAt = NOW() WHERE id = :id")->execute([':s' => $stage, ':id' => $a['id']]);
    }
    apl_event($pdo, $a['id'], $a['stage'], $stage, $notes, $actor);
    if ($stage !== $a['stage']) { lcy_audit($pdo, 'Applicant #' . $a['id'] . ' ' . $a['name'] . ': ' . APL_STAGES[$stage]); }
    return ['ok' => true];
}

/**
 * Delete a closed application (Not selected / Withdrawn) with its resume, photo,
 * exam result images, education and family rows — personal data is kept no longer than needed.
 * Hired applications stay: they link to the employee's account.
 */
function apl_delete(PDO $pdo, $id, $actor) {
    $r = apl_row($pdo, $id);
    if (!$r) { return ['ok' => false, 'msg' => 'That application no longer exists.']; }
    if (!in_array($r['Stage'], ['rejected', 'withdrawn'], true)) {
        return ['ok' => false, 'msg' => 'Only applications marked Not selected or Withdrawn can be deleted.'];
    }
    $pdo->beginTransaction();
    try {
        $examKeys = [];
        if (apl_has_files($pdo)) {
            $st = $pdo->prepare("SELECT FileKey FROM applicant_files WHERE ApplicantID = :a");
            $st->execute([':a' => (int) $id]);
            $examKeys = $st->fetchAll(PDO::FETCH_COLUMN);
            $pdo->prepare("DELETE FROM applicant_files WHERE ApplicantID = :a")->execute([':a' => (int) $id]);
        }
        foreach (['applicant_education', 'applicant_family', 'applicant_events'] as $t) {
            $pdo->prepare("DELETE FROM $t WHERE ApplicantID = :a")->execute([':a' => (int) $id]);
        }
        $pdo->prepare("DELETE FROM applicants WHERE id = :a")->execute([':a' => (int) $id]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    $paths = [apl_resume_path($r['ResumeKey']), apl_photo_path($r['PhotoKey'] ?? null)];
    foreach ($examKeys as $k) { $paths[] = apl_photo_path($k); }
    foreach ($paths as $path) {
        if ($path) { @unlink($path); }
    }
    lcy_audit($pdo, 'Applicant #' . (int) $id . ' deleted (' . apl_name($r) . ')');
    return ['ok' => true];
}

/* ------------------------------------------------------------------ hiring */

/** Choices for the hire dialog: companies, departments, positions, superiors, classifications (Trainee / IC only). */
function apl_hire_options(PDO $pdo) {
    $all = function ($sql) use ($pdo) { return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); };
    return [
        'companies'   => $all("SELECT CompanyID AS id, CompanyDesc AS name FROM companies ORDER BY CompanyDesc"),
        'departments' => $all("SELECT DepartmentID AS id, DepartmentDesc AS name, CompID AS company FROM departments ORDER BY DepartmentDesc"),
        'positions'   => $all("SELECT PSID AS id, PositionDesc AS name, DepartmentID AS department FROM positions ORDER BY PositionDesc"),
        'superiors'   => $all("SELECT e.EmpID AS id, CONCAT(e.EmpFN, ' ', e.EmpLN) AS name, d.EmpdepID AS department
                               FROM employees e JOIN empdetails d ON d.EmpID = e.EmpID
                               WHERE e.EmpStatusID = 1 AND d.EmpRoleID <> 3 ORDER BY e.EmpLN, e.EmpFN"),
        'classes'     => $all("SELECT EmpStatID AS id, EmpStatDesc AS name FROM empstatus
                               WHERE EmpStatID IN (" . implode(',', array_keys(APL_HIRE_CLASSES)) . ")
                               ORDER BY FIELD(EmpStatID, " . implode(',', array_keys(APL_HIRE_CLASSES)) . ")"),
        'trainee'     => APL_TRAINEE,
        'today'       => date('Y-m-d'),
    ];
}

/** Next EmpID for a company: <compcode>-0152 (highest number in use + 1, never reused). */
function apl_next_empid(PDO $pdo, $compcode) {
    $st = $pdo->prepare("SELECT EmpID FROM empdetails WHERE EmpID LIKE :p");
    $st->execute([':p' => addcslashes($compcode, '%_\\') . '-%']);
    $max = 0;
    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $id) {
        if (preg_match('/-(\d+)$/', (string) $id, $m)) { $max = max($max, (int) $m[1]); }
    }
    return $compcode . '-' . sprintf('%04d', $max + 1);
}


/**
 * The hire dialog's values, checked: what apl_hire() saves and what the
 * contract shows. $d: company, department, position, superior,
 * classification (Trainee / Independent Contractor), hired (date), basic,
 * allowance, hourly, idNumber. Agency and HMO are not asked on hire.
 * @return array{ok:bool, msg?:string, v?:array}
 */
function apl_hire_values(PDO $pdo, array $d) {
    $one = function ($sql, $args) use ($pdo) { $st = $pdo->prepare($sql); $st->execute($args); return $st->fetch(PDO::FETCH_ASSOC); };
    $comp = $one("SELECT CompanyID, compcode, CompanyDesc FROM companies WHERE CompanyID = :c", [':c' => (string) ($d['company'] ?? '')]);
    if (!$comp || trim((string) $comp['compcode']) === '') { return ['ok' => false, 'msg' => 'Choose the company.']; }
    $dep = $one("SELECT DepartmentID, DepartmentDesc FROM departments WHERE DepartmentID = :d AND CompID = :c", [':d' => (int) ($d['department'] ?? 0), ':c' => $comp['CompanyID']]);
    if (!$dep) { return ['ok' => false, 'msg' => 'Choose a department of that company.']; }
    $pos = $one("SELECT PSID, PositionDesc FROM positions WHERE PSID = :p AND DepartmentID = :d", [':p' => (int) ($d['position'] ?? 0), ':d' => $dep['DepartmentID']]);
    if (!$pos) { return ['ok' => false, 'msg' => 'Choose a position in that department.']; }
    $superior = (string) ($d['superior'] ?? 'N/A');
    $superiorName = '';
    if ($superior !== 'N/A') {
        $sup = $one("SELECT CONCAT(e.EmpFN, ' ', e.EmpLN) AS name FROM employees e JOIN empdetails d ON d.EmpID = e.EmpID
                     WHERE e.EmpID = :s AND e.EmpStatusID = 1 AND d.EmpRoleID <> 3", [':s' => $superior]);
        if (!$sup) { return ['ok' => false, 'msg' => 'Choose the immediate superior from the list.']; }
        $superiorName = $sup['name'];
    }
    $class = (int) ($d['classification'] ?? APL_TRAINEE);
    $cls = isset(APL_HIRE_CLASSES[$class]) ? $one("SELECT EmpStatDesc FROM empstatus WHERE EmpStatID = :c", [':c' => $class]) : false;
    if (!$cls) { return ['ok' => false, 'msg' => 'Choose Trainee or Independent Contractor.']; }
    $hired = lcy_date($d['hired'] ?? '');
    if ($hired === null) { return ['ok' => false, 'msg' => 'Enter the hire date.']; }
    $money = [];
    foreach (['basic' => 'monthly basic', 'allowance' => 'allowance', 'hourly' => 'hourly rate'] as $k => $label) {
        $v = trim(str_replace(',', '', (string) ($d[$k] ?? '')));
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $v)) { return ['ok' => false, 'msg' => "Enter the $label (a number)."]; }
        $money[$k] = $v;
    }
    return ['ok' => true, 'v' => [
        'companyId' => $comp['CompanyID'], 'compcode' => trim($comp['compcode']), 'company' => (string) $comp['CompanyDesc'],
        'departmentId' => $dep['DepartmentID'], 'department' => (string) $dep['DepartmentDesc'],
        'positionId' => $pos['PSID'], 'position' => (string) $pos['PositionDesc'],
        'superior' => $superior, 'superiorName' => $superiorName,
        'class' => $class, 'className' => (string) $cls['EmpStatDesc'], 'contract' => APL_HIRE_CLASSES[$class],
        'hired' => $hired, 'money' => $money, 'idNumber' => lcy_text($d['idNumber'] ?? '', 100),
    ]];
}

/**
 * Hire: copy the application into the employee tables in ONE transaction,
 * as Trainee or Independent Contractor, Employed, role Employee (3).
 * $d: see apl_hire_values(). Agency and HMO are left blank.
 * @return array{ok:bool, msg?:string, empId?:string, username?:string, password?:string, values?:array}
 */
function apl_hire(PDO $pdo, $id, array $d, $actor) {
    $r = apl_row($pdo, $id);
    if (!$r) { return ['ok' => false, 'msg' => 'That application no longer exists.']; }
    if ($r['Stage'] === 'hired' || $r['EmpID'] !== null) { return ['ok' => false, 'msg' => 'This applicant was already hired.']; }
    if (in_array($r['Stage'], ['rejected', 'withdrawn'], true)) {
        return ['ok' => false, 'msg' => 'Move the application back to an active stage before hiring.'];
    }
    if ($r['Stage'] !== APL_HIRE_STAGE) {   // hiring comes after the process, not instead of it
        return ['ok' => false, 'msg' => 'Move the application to "' . APL_STAGES[APL_HIRE_STAGE] . '" first — hiring comes after the job offer.'];
    }
    $chk = apl_hire_values($pdo, $d);
    if (!$chk['ok']) { return $chk; }
    $v = $chk['v'];
    $money = $v['money'];
    $class = $v['class'];
    $hired = $v['hired'];
    $superior = $v['superior'];

    require_once __DIR__ . '/username.php';
    require_once __DIR__ . '/account-gate.php';
    $empId    = apl_next_empid($pdo, $v['compcode']);
    $username = wd_free_username($pdo, wd_unique_username($pdo, $r['FirstName'], $r['LastName']));
    if ($username === '') { $username = wd_free_username($pdo, 'applicant' . (int) $id); }
    $password = wd_temp_password();
    $edu = apl_education($pdo, $id);
    $fam = apl_family($pdo, $id);

    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO employees (EmpID, EmpSuffix, EmpFN, EmpMN, EmpLN, PosID, EmpStatusID, EmployeeIDNumber)
                       VALUES (:e, :s, :f, :m, :l, :p, 1, :n)")
            ->execute([':e' => $empId, ':s' => (string) $r['Suffix'], ':f' => $r['FirstName'], ':m' => (string) $r['MiddleName'],
                       ':l' => $r['LastName'], ':p' => $v['positionId'], ':n' => $v['idNumber']]);
        $ins = $pdo->prepare("INSERT INTO empeducationalbackground (EmpID, Name_of_School, Program, Year_Started, Year_End, School_Address)
                              VALUES (:e, :s, :p, :f, :t, :a)");
        foreach ($edu as $e) {   // always all three programs: Update Employee Information edits them by Program
            $ins->execute([':e' => $empId, ':s' => (string) $e['School'], ':p' => $e['Program'], ':f' => (string) $e['YearStarted'],
                           ':t' => (string) $e['YearEnded'], ':a' => (string) $e['Address']]);
        }
        $pdo->prepare("INSERT INTO empprofiles (EmpID, EmpAddress1, EmpAddDis, EmpAddCity, EmpAddProv, EmpAddZip, EmpAddCountry,
                           EmpPhone, EmpDOB, EmpGender, EmpCS, EmpMobile, EmpEmail, EmpPPNo, EmpPPED, EmpPPIA, EmpSSS, EmpTIN,
                           EmpHMONumber, EmpPP, EmpPPSD, EmpPPDept, EmpPPPos, EmpPINo, EmpPHNo, EmpUMIDNo, EmpPPath, EmpCitezen, EmpReligion)
                       VALUES (:e, :a1, :ad, :ac, :ap, :az, :aco, :ph, :dob, :g, :cs, :hp, :em, :ppn, :ppe, :ppi, :sss, :tin,
                           :hmo, :pp, :ppsd, :ppd, :ppp, :pin, :phn, :umid, :pic, :cit, :rel)")
            ->execute([':e' => $empId, ':a1' => $r['Street'], ':ad' => $r['Barangay'], ':ac' => $r['City'], ':ap' => $r['Province'],
                       ':az' => (string) $r['Zip'], ':aco' => $r['Country'], ':ph' => $r['Mobile'], ':dob' => $r['BirthDate'],
                       ':g' => $r['Gender'], ':cs' => $r['CivilStatus'], ':hp' => (string) $r['HomePhone'], ':em' => $r['Email'],
                       ':ppn' => (string) $r['PassportNo'], ':ppe' => (string) $r['PassportExpiry'], ':ppi' => (string) $r['PassportIssuer'],
                       ':sss' => (string) $r['SSS'], ':tin' => (string) $r['TIN'], ':hmo' => '',
                       ':pp' => (string) $r['PrevCompany'], ':ppsd' => (string) $r['PrevStart'], ':ppd' => (string) $r['PrevDept'],
                       ':ppp' => (string) $r['PrevPosition'], ':pin' => (string) $r['PagIbig'], ':phn' => (string) $r['PhilHealth'],
                       ':umid' => (string) $r['UMID'], ':pic' => 'assets/images/profiles/' . $empId . '.jpg',
                       ':cit' => (string) $r['Citizenship'], ':rel' => (string) $r['Religion']]);
        $pdo->prepare("INSERT INTO empdetails2 (EmpID, EmpBasic, EmpAllowance, EmpHRate) VALUES (:e, :b, :a, :h)")
            ->execute([':e' => $empId, ':b' => $money['basic'], ':a' => $money['allowance'], ':h' => $money['hourly']]);
        $pdo->prepare("INSERT INTO accessrights (EmpID) VALUES (:e)")->execute([':e' => $empId]);
        $pdo->prepare("INSERT INTO empdetails (EmpID, EmpUN, EmpPW, EmpRoleID, EmpISID, EmpdepID, EmpCompID, EmpDateHired,
                           EmpDateResigned, EmpStatID, AgencyID, HMO_ID)
                       VALUES (:e, :u, :pw, 3, :is, :dep, :c, :dh, NULL, :cl, :ag, :hmo)")
            ->execute([':e' => $empId, ':u' => $username, ':pw' => password_hash($password, PASSWORD_DEFAULT), ':is' => $superior,
                       ':dep' => $v['departmentId'], ':c' => $v['companyId'], ':dh' => $hired, ':cl' => $class,
                       ':ag' => '', ':hmo' => '']);   // agency / HMO are not asked on hire (blank, like most employees)
        $ins = $pdo->prepare("INSERT INTO fdetails (FDetID, FName, FAdd, FRel, FContact, FICE) VALUES (:e, :n, :a, :r, :c, :i)");
        foreach ($fam as $f) {
            $ins->execute([':e' => $empId, ':n' => $f['Name'], ':a' => (string) $f['Address'], ':r' => $f['Relationship'],
                           ':c' => (string) $f['Contact'], ':i' => $f['Emergency'] ? 'Yes' : 'No']);
        }
        $pdo->prepare("UPDATE applicants SET EmpID = :e, Stage = 'hired', StageAt = NOW(), HiredAt = NOW() WHERE id = :id AND EmpID IS NULL")
            ->execute([':e' => $empId, ':id' => (int) $id]);
        apl_event($pdo, $id, $r['Stage'], 'hired', 'WeDo account created: ' . $empId, $actor);
        $classes = lcy_class_names($pdo);
        lcy_log_event($pdo, $empId, 'hired', null, $classes[$class] ?? null, $hired,
                      'From application #' . (int) $id . ($r['OpeningTitle'] ? ' — ' . $r['OpeningTitle'] : ''), $actor);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    wd_set_must_change_password($pdo, $empId, true);   // the temporary password works once: they must set their own
    apl_photo_to_profile($r['PhotoKey'] ?? null, $empId);   // their camera photo becomes the profile picture (EmpPPath)
    lcy_audit($pdo, 'Hired applicant #' . (int) $id . ' as ' . $empId . ' (' . apl_name($r) . ')');
    return ['ok' => true, 'empId' => $empId, 'username' => $username, 'password' => $password, 'values' => $v];
}
