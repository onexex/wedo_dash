<?php
/* ==========================================================================
   includes/idcard-lib.php  —  Company ID card generator (Management > ID Card Generator)
   ---------------------------------------------------------------------------
   Single source of truth for:
     - the back-of-card text (idc_back_config)
     - how a name is printed and how an ID number is built
     - issuing a number (idc_issue) and saving the photo adjustment
   Used by idcard.php (screen) and query/idcard-action.php (writes). The card
   artwork itself is drawn in the browser by assets/js/idcard-render.js.

   Tables: idcard_cards, idcard_print_log (sql/2026-10-01-add-idcard.sql).

   ID number = issue year + initials (first, middle, last) + 3-digit running
   number per year, e.g. 2026BNF008. A missing middle name is written as X so
   every number has the same length. The number is assigned once, on the first
   print, and kept on every reprint; it is also written to
   employees.EmployeeIDNumber (the "Employee ID" on the 201 page).
   ========================================================================== */

if (!function_exists('idc_back_config')) {

/**
 * The editable company details on the back of every card: key => [label, max length, required].
 * HR changes them from the page (Card back settings); values live in idcard_settings.
 */
function idc_back_fields() {
    return [
        'phone'      => ['Phone', 30, true],
        'email'      => ['Email', 80, true],
        'address1'   => ['Address line 1', 45, true],
        'address2'   => ['Address line 2', 45, false],
        'signatory'  => ['Signatory name', 40, true],
        'sign_title' => ['Signatory title', 40, true],
    ];
}

/** Values used until HR saves their own. */
function idc_back_defaults() {
    return [
        'phone'      => '0967 378 4000',
        'email'      => 'art@wedoinc.ph',
        'address1'   => 'UNIT 3004-A WEST TEKTITE TOWER',
        'address2'   => 'EXCHANGE RD ORTIGAS, PASIG CITY 1605',
        'signatory'  => 'JOSE MODESTO A. FERRER',
        'sign_title' => 'GENERAL MANAGER',
    ];
}

/** The editable values: saved settings over the defaults (table missing = defaults). */
function idc_back_values(?PDO $pdo = null) {
    $v = idc_back_defaults();
    if ($pdo) {
        try {
            foreach ($pdo->query("SELECT setting_key, setting_value FROM idcard_settings")->fetchAll(PDO::FETCH_KEY_PAIR) as $k => $val) {
                if (array_key_exists($k, $v)) { $v[$k] = $val; }
            }
        } catch (Throwable $e) { /* migration not run yet: defaults */ }
    }
    return $v;
}

/**
 * Has HR confirmed the back details (saved them at least once)? Until then the
 * defaults are only a starting point and no card may be issued with them.
 */
function idc_back_is_set(PDO $pdo) {
    $required = array_keys(array_filter(idc_back_fields(), function ($f) { return $f[2]; }));
    try {
        $in = implode(',', array_fill(0, count($required), '?'));
        $st = $pdo->prepare("SELECT COUNT(*) FROM idcard_settings WHERE setting_key IN ($in) AND setting_value <> ''");
        $st->execute($required);
        return (int) $st->fetchColumn() === count($required);
    } catch (Throwable $e) {
        return false;
    }
}

/** Everything the renderer needs for the back of the card. */
function idc_back_config(?PDO $pdo = null) {
    $v = idc_back_values($pdo);
    return [
        'statement' => ['THE PERSON WHOSE NAME, PHOTO AND SIGNATURE', 'APPEAR HEREIN IS A PERSONNEL OF:'],
        'phone'     => $v['phone'],
        'email'     => $v['email'],
        'address'   => array_values(array_filter([$v['address1'], $v['address2']], 'strlen')),
        'signatory' => $v['signatory'],
        'sign_title'=> $v['sign_title'],
        // PLACEHOLDER: low-res (236x107) with a white background; replace with a
        // transparent PNG at least 600px wide. The card blends it so white drops out.
        'signature' => 'assets/images/sign/ogm.png',
        'logo'      => 'assets/images/logos/WeDo.png',
    ];
}

/**
 * Validate and save the back-of-card details.
 * Returns [] on success or [field => message] when something is wrong (nothing saved).
 */
function idc_save_back(PDO $pdo, array $input, $actor) {
    $clean = []; $errors = [];
    foreach (idc_back_fields() as $key => [$label, $max, $required]) {
        $val = trim(preg_replace('/\s+/u', ' ', (string) ($input[$key] ?? '')));
        if ($required && $val === '') { $errors[$key] = $label . ' is required.'; continue; }
        if (mb_strlen($val, 'UTF-8') > $max) { $errors[$key] = $label . ' is too long for the card (' . $max . ' characters max).'; continue; }
        $clean[$key] = $val;
    }
    if (!isset($errors['email']) && !filter_var($clean['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!isset($errors['phone']) && !preg_match('/^[0-9+()\-\s.\/]+$/', $clean['phone'] ?? '')) {
        $errors['phone'] = 'Use digits, spaces and + ( ) - only.';
    }
    if ($errors) { return $errors; }

    $st = $pdo->prepare("INSERT INTO idcard_settings (setting_key, setting_value, updated_by) VALUES (:k, :v, :by)
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)");
    $pdo->beginTransaction();
    foreach ($clean as $k => $v) { $st->execute([':k' => $k, ':v' => $v, ':by' => $actor]); }
    $pdo->commit();
    return [];
}

/** Trim, collapse inner spaces, upper-case (multibyte-safe). */
function idc_clean($s) {
    $s = preg_replace('/\s+/u', ' ', trim((string) $s));
    return mb_strtoupper($s ?? '', 'UTF-8');
}

/** A middle name that is really "none". */
function idc_blank_middle($mn) {
    $m = idc_clean($mn);
    return $m === '' || in_array($m, ['.', '-', 'N/A', 'NA', 'NONE', 'NMN'], true);
}

/** Name as printed on the card: FIRST M. LAST SUFFIX */
function idc_display_name($fn, $mn, $ln, $suffix = '') {
    $parts = [idc_clean($fn)];
    if (!idc_blank_middle($mn)) {
        $parts[] = mb_substr(idc_clean($mn), 0, 1, 'UTF-8') . '.';
    }
    $parts[] = idc_clean($ln);
    $sx = rtrim(idc_clean($suffix), '.');
    if ($sx !== '') {
        $parts[] = in_array($sx, ['JR', 'SR'], true) ? $sx . '.' : $sx;
    }
    return implode(' ', array_filter($parts, function ($p) { return $p !== ''; }));
}

/** First A–Z letter of a name, with accents folded (Ñ -> N, É -> E). '' if none. */
function idc_initial($name) {
    static $fold = [
        'Á'=>'A','À'=>'A','Â'=>'A','Ä'=>'A','Ã'=>'A','Å'=>'A','É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E',
        'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I','Ó'=>'O','Ò'=>'O','Ô'=>'O','Ö'=>'O','Õ'=>'O',
        'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','Ñ'=>'N','Ç'=>'C','Ý'=>'Y',
    ];
    $s = strtr(idc_clean($name), $fold);
    return preg_match('/[A-Z]/', $s, $m) ? $m[0] : '';
}

/** Three initials; a missing name part becomes X. */
function idc_initials($fn, $mn, $ln) {
    $out = '';
    foreach ([$fn, idc_blank_middle($mn) ? '' : $mn, $ln] as $part) {
        $out .= idc_initial($part) ?: 'X';
    }
    return $out;
}

function idc_format_number($year, $initials, $seq) {
    return sprintf('%04d%s%03d', $year, $initials, $seq);
}

/** Next running number for a year (what the next issue would get; not reserved). */
function idc_next_seq(PDO $pdo, $year) {
    $st = $pdo->prepare("SELECT COALESCE(MAX(seq), 0) + 1 FROM idcard_cards WHERE issue_year = :y");
    $st->execute([':y' => (int) $year]);
    return (int) $st->fetchColumn();
}

/** Has the `idcard` access right (==2). Missing column = migration not run = no access. */
function idc_can_manage(PDO $pdo) {
    if (empty($_SESSION['id']) || $_SESSION['id'] == '0') { return false; }
    try {
        $st = $pdo->prepare("SELECT idcard FROM accessrights WHERE EmpID = :id");
        $st->execute([':id' => $_SESSION['id']]);
        return (int) $st->fetchColumn() === 2;
    } catch (Throwable $e) {
        return false;
    }
}

/** Per-session token for the ID card write endpoint. */
function idc_csrf_token() {
    if (empty($_SESSION['idc_csrf'])) { $_SESSION['idc_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['idc_csrf'];
}

/**
 * Employees with everything the card needs, keyed by nothing (ordered list).
 * $only = list of EmpIDs to restrict to (null = everyone).
 */
function idc_employees(PDO $pdo, ?array $only = null) {
    $where = '';
    $args  = [];
    if ($only !== null) {
        if (!$only) { return []; }
        $where = 'WHERE e.EmpID IN (' . implode(',', array_fill(0, count($only), '?')) . ')';
        $args  = array_values($only);
    }
    $st = $pdo->prepare("SELECT e.EmpID, e.EmpFN, e.EmpMN, e.EmpLN, e.EmpSuffix, e.EmployeeIDNumber, e.EmpStatusID,
            p.PositionDesc, dep.DepartmentDesc, es.EmpStatDesc, pr.EmpPPath,
            c.id_number, c.photo_x, c.photo_y, c.photo_zoom, c.first_issued_at,
            (SELECT MAX(l.printed_at) FROM idcard_print_log l WHERE l.EmpID = e.EmpID) AS last_printed
        FROM employees e
        JOIN empdetails d        ON d.EmpID = e.EmpID
        LEFT JOIN positions p    ON p.PSID = e.PosID
        LEFT JOIN departments dep ON dep.DepartmentID = d.EmpdepID
        LEFT JOIN empstatus es   ON es.EmpStatID = d.EmpStatID
        LEFT JOIN empprofiles pr ON pr.EmpID = e.EmpID
        LEFT JOIN idcard_cards c ON c.EmpID = e.EmpID
        $where
        ORDER BY e.EmpLN, e.EmpFN");
    $st->execute($args);
    return array_map('idc_card_data', $st->fetchAll(PDO::FETCH_ASSOC));
}

/* ---------------------------------------------------------------- signatures
   The employee's own signature, printed on the front above the name. HR
   uploads a scan or photo; it is re-encoded as PNG (nothing of the original
   file is kept), the paper is made transparent and it is cropped to the ink.
   Stored as assets/images/id-signatures/<EmpID>.png (server data, gitignored). */

function idc_sign_dir() { return dirname(__DIR__) . '/assets/images/id-signatures'; }

/** File name for an employee's signature, or '' for an unusable id. */
function idc_sign_file($empId) {
    $safe = preg_replace('/[^A-Za-z0-9._-]/', '', basename((string) $empId));
    return ($safe === '' || $safe[0] === '.') ? '' : $safe . '.png';
}

/** Relative URL (cache-busted) of an employee's signature, or null. */
function idc_sign_url($empId) {
    $f = idc_sign_file($empId);
    $p = idc_sign_dir() . '/' . $f;
    return ($f !== '' && is_file($p)) ? 'assets/images/id-signatures/' . $f . '?v=' . filemtime($p) : null;
}

/** Working copy at most 1200 px on its longest side, on white (transparent areas = paper). */
function idc_work_canvas($src) {
    $w = imagesx($src); $h = imagesy($src);
    $scale = min(1, 1200 / max($w, $h));
    $cw = max(1, (int) round($w * $scale)); $ch = max(1, (int) round($h * $scale));
    $img = imagecreatetruecolor($cw, $ch);
    imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
    imagealphablending($img, true);
    imagecopyresampled($img, $src, 0, 0, 0, 0, $cw, $ch, $w, $h);
    return $img;
}

/**
 * Pull the ink out of a scan or phone photo of a signature: a transparent PNG
 * image cropped tight to the strokes, or null when no ink is found.
 *
 * $img is the small working copy from idc_work_canvas().
 *
 * Phone photos have shadows, uneven light, the paper's edge and the table
 * around it. A fixed "dark = ink" cut-off keeps all of that, the crop stays
 * big and the signature prints tiny. So:
 *   1. paper brightness is measured per 24 px cell (shadows are still paper);
 *   2. only cells that are clearly paper, and not on its edge, can hold ink;
 *   3. a pixel is ink when it is well darker than the paper around it;
 *   4. specks are dropped, and the crop is the box around the real strokes.
 */
function idc_extract_ink($img) {
    $cw = imagesx($img); $ch = imagesy($img);

    // luminance of every pixel, one byte each
    $L = str_repeat("\0", $cw * $ch);
    for ($y = 0, $i = 0; $y < $ch; $y++) {
        for ($x = 0; $x < $cw; $x++, $i++) {
            $c = imagecolorat($img, $x, $y);
            $L[$i] = chr((int) ((($c >> 16 & 0xFF) * 299 + ($c >> 8 & 0xFF) * 587 + ($c & 0xFF) * 114) / 1000));
        }
    }

    // 1. paper level per cell = 90th percentile brightness (ink is a small share of any cell)
    $C = 24;
    $gw = (int) ceil($cw / $C); $gh = (int) ceil($ch / $C);
    $cell = [];
    for ($gy = 0; $gy < $gh; $gy++) {
        for ($gx = 0; $gx < $gw; $gx++) {
            $hist = array_fill(0, 256, 0); $n = 0;
            for ($y = $gy * $C, $ye = min($ch, $y + $C); $y < $ye; $y++) {
                for ($x = $gx * $C, $xe = min($cw, $x + $C), $i = $y * $cw + $x; $x < $xe; $x++, $i++) {
                    $hist[ord($L[$i])]++; $n++;
                }
            }
            $want = $n * 0.9; $acc = 0; $v = 255;
            for ($b = 0; $b < 256; $b++) { $acc += $hist[$b]; if ($acc >= $want) { $v = $b; break; } }
            $cell[$gy][$gx] = $v;
        }
    }
    // a cell crowded with ink borrows the paper level of its neighbours
    $bg = [];
    for ($gy = 0; $gy < $gh; $gy++) {
        for ($gx = 0; $gx < $gw; $gx++) {
            $nb = [];
            for ($dy = -1; $dy <= 1; $dy++) { for ($dx = -1; $dx <= 1; $dx++) {
                if (isset($cell[$gy + $dy][$gx + $dx])) { $nb[] = $cell[$gy + $dy][$gx + $dx]; }
            } }
            sort($nb);
            $bg[$gy][$gx] = max($cell[$gy][$gx], $nb[(int) floor((count($nb) - 1) / 2)]);
        }
    }

    // 2. which cells are paper: bright compared with the page as a whole, and not on the paper's edge
    $all = [];
    foreach ($bg as $row) { foreach ($row as $v) { $all[] = $v; } }
    sort($all);
    $paperLevel = $all[(int) floor((count($all) - 1) * 0.75)];
    $isPaper = function ($gx, $gy) use ($bg, $paperLevel) {
        return isset($bg[$gy][$gx]) ? $bg[$gy][$gx] >= $paperLevel - 60 : true;   // outside the image counts as paper
    };
    $inkable = [];
    for ($gy = 0; $gy < $gh; $gy++) {
        for ($gx = 0; $gx < $gw; $gx++) {
            $ok = true;
            for ($dy = -1; $dy <= 1 && $ok; $dy++) { for ($dx = -1; $dx <= 1; $dx++) {
                if (!$isPaper($gx + $dx, $gy + $dy)) { $ok = false; break; }
            } }
            $inkable[$gy][$gx] = $ok;
        }
    }

    // 3. ink = clearly darker than the paper it sits on
    $M = str_repeat("\0", $cw * $ch);
    $inkCount = 0;
    for ($y = 0, $i = 0; $y < $ch; $y++) {
        $gy = intdiv($y, $C);
        for ($x = 0; $x < $cw; $x++, $i++) {
            $gx = intdiv($x, $C);
            if (!$inkable[$gy][$gx]) { continue; }
            $lum = ord($L[$i]);
            if ($lum < 200 && $bg[$gy][$gx] - $lum >= 45) { $M[$i] = "\1"; $inkCount++; }
        }
    }
    if ($inkCount === 0) { imagedestroy($img); return null; }

    // 4. connected strokes; specks (tiny next to the biggest stroke) are dropped
    $comps = []; $largest = 0;
    for ($start = 0, $N = $cw * $ch; $start < $N; $start++) {
        if ($M[$start] !== "\1") { continue; }
        $M[$start] = "\2";
        $stack = [$start]; $pix = [];
        while ($stack) {
            $p = array_pop($stack); $pix[] = $p;
            $px = $p % $cw; $py = intdiv($p, $cw);
            for ($dy = -1; $dy <= 1; $dy++) {
                $ny = $py + $dy; if ($ny < 0 || $ny >= $ch) { continue; }
                for ($dx = -1; $dx <= 1; $dx++) {
                    $nx = $px + $dx; if ($nx < 0 || $nx >= $cw) { continue; }
                    $q = $ny * $cw + $nx;
                    if ($M[$q] === "\1") { $M[$q] = "\2"; $stack[] = $q; }
                }
            }
        }
        $comps[] = $pix;
        $largest = max($largest, count($pix));
    }
    $minKeep = max(10, (int) ($largest * 0.02));
    $minX = $cw; $minY = $ch; $maxX = -1; $maxY = -1; $keep = [];
    foreach ($comps as $pix) {
        if (count($pix) < $minKeep) { continue; }
        foreach ($pix as $p) {
            $keep[] = $p;
            $x = $p % $cw; $y = intdiv($p, $cw);
            if ($x < $minX) { $minX = $x; } if ($x > $maxX) { $maxX = $x; }
            if ($y < $minY) { $minY = $y; } if ($y > $maxY) { $maxY = $y; }
        }
    }
    unset($comps);
    if ($maxX < 0) { imagedestroy($img); return null; }

    // transparent PNG of just the kept strokes, cropped with a small margin
    $pad = 4;
    $minX = max(0, $minX - $pad); $minY = max(0, $minY - $pad);
    $maxX = min($cw - 1, $maxX + $pad); $maxY = min($ch - 1, $maxY + $pad);
    $out = imagecreatetruecolor($maxX - $minX + 1, $maxY - $minY + 1);
    imagealphablending($out, false); imagesavealpha($out, true);
    imagefill($out, 0, 0, 0x7F000000);
    foreach ($keep as $p) {
        $x = $p % $cw; $y = intdiv($p, $cw);
        $d = $bg[intdiv($y, $C)][intdiv($x, $C)] - ord($L[$p]);   // how much darker than the paper
        $strength = min(1, max(0, ($d - 45) / 45));                 // soft anti-aliased edges
        $alpha = (int) round((1 - (0.4 + 0.6 * $strength)) * 127);
        $c = imagecolorat($img, $x, $y) & 0xFFFFFF;
        imagesetpixel($out, $x - $minX, $y - $minY, ($alpha << 24) | $c);
    }
    imagedestroy($img);
    return $out;
}

/**
 * Turn an image file into the stored signature. Returns '' on success or a
 * message for the user.
 */
function idc_store_signature($empId, $srcPath) {
    $file = idc_sign_file($empId);
    if ($file === '') { return 'Unknown employee.'; }
    $info = @getimagesize($srcPath);
    if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) { return 'Upload a PNG or JPG image.'; }
    if ($info[0] > 5000 || $info[1] > 5000) { return 'That image is too large (5000 px max per side).'; }
    $src = @imagecreatefromstring((string) file_get_contents($srcPath));
    if (!$src) { return 'That image could not be read.'; }

    // shrink first and let go of the full-size photo (a 12 MP photo is ~50 MB decoded)
    $work = idc_work_canvas($src);
    unset($src);
    $out = idc_extract_ink($work);
    unset($work);
    if (!$out) { return 'No signature found — the image looks blank. Use dark ink on white paper.'; }

    if (!is_dir(idc_sign_dir())) { @mkdir(idc_sign_dir(), 0755, true); }
    $ok = imagepng($out, idc_sign_dir() . '/' . $file, 9);
    imagedestroy($out);
    return $ok ? '' : 'The signature could not be saved on the server.';
}

function idc_remove_signature($empId) {
    $f = idc_sign_file($empId);
    if ($f !== '') { @unlink(idc_sign_dir() . '/' . $f); }
}

/** What a card still needs before it may print: [] or a list of 'photo' / 'signature'. */
function idc_card_missing(array $card) {
    $needs = [];
    if (empty($card['photo']))     { $needs[] = 'photo'; }
    if (empty($card['signature'])) { $needs[] = 'signature'; }
    return $needs;
}

/** One DB row -> the JSON the page and renderer use. */
function idc_card_data(array $r) {
    $root  = dirname(__DIR__);
    $photo = null; $pw = 0; $ph = 0;
    $path  = ltrim((string) ($r['EmpPPath'] ?? ''), '/');
    if ($path !== '' && strpos($path, '..') === false && is_file($root . '/' . $path)) {
        $size = @getimagesize($root . '/' . $path);
        if ($size) {
            [$pw, $ph] = $size;
            $photo = $path . '?v=' . filemtime($root . '/' . $path);
        }
    }
    return [
        'empId'      => $r['EmpID'],
        'name'       => idc_display_name($r['EmpFN'], $r['EmpMN'], $r['EmpLN'], $r['EmpSuffix']),
        'sortName'   => trim(idc_clean($r['EmpLN']) . ', ' . idc_clean($r['EmpFN'])),
        'listName'   => mb_convert_case(mb_strtolower(trim(idc_clean($r['EmpLN']) . ', ' . idc_clean($r['EmpFN'])), 'UTF-8'), MB_CASE_TITLE, 'UTF-8'),
        'listPosition' => trim(preg_replace('/\s+/u', ' ', (string) ($r['PositionDesc'] ?? ''))),   // as entered: keeps IT, SNS, CSR
        'initials'   => idc_initials($r['EmpFN'], $r['EmpMN'], $r['EmpLN']),
        'position'   => idc_clean($r['PositionDesc'] ?? ''),
        'department' => (string) ($r['DepartmentDesc'] ?? ''),
        'empType'    => (string) ($r['EmpStatDesc'] ?? ''),
        'employed'   => (int) $r['EmpStatusID'] === 1,
        'idNumber'   => $r['id_number'] ?: null,
        'issuedAt'   => $r['first_issued_at'] ?: null,
        'lastPrinted'=> $r['last_printed'] ?: null,
        'photo'      => $photo,
        'photoW'     => $pw,
        'photoH'     => $ph,
        'signature'  => idc_sign_url($r['EmpID']),
        'crop'       => [
            'x'    => isset($r['photo_x']) ? (float) $r['photo_x'] : 0.0,
            'y'    => isset($r['photo_y']) ? (float) $r['photo_y'] : 0.0,
            'zoom' => isset($r['photo_zoom']) ? (float) $r['photo_zoom'] : 1.0,
        ],
    ];
}

/** Save how the photo sits in the circle (clamped to the renderer's ranges). */
function idc_save_crop(PDO $pdo, $empId, $x, $y, $zoom, $actor) {
    $x    = max(-1, min(1, (float) $x));
    $y    = max(-1, min(1, (float) $y));
    $zoom = max(1, min(3, (float) $zoom));
    $pdo->prepare("INSERT INTO idcard_cards (EmpID, photo_x, photo_y, photo_zoom, updated_by)
                   VALUES (:id, :x, :y, :z, :by)
                   ON DUPLICATE KEY UPDATE photo_x = VALUES(photo_x), photo_y = VALUES(photo_y),
                                           photo_zoom = VALUES(photo_zoom), updated_by = VALUES(updated_by)")
        ->execute([':id' => $empId, ':x' => $x, ':y' => $y, ':z' => $zoom, ':by' => $actor]);
}

/**
 * Issue (first print) or reprint one employee's card and log it.
 * Returns ['id_number' => ..., 'action' => 'issue'|'reprint'].
 * Throws RuntimeException when the employee does not exist.
 */
function idc_issue(PDO $pdo, $empId, $actor, $year = null) {
    $year = (int) ($year ?: date('Y'));
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $pdo->beginTransaction();
        try {
            $emp = $pdo->prepare("SELECT EmpFN, EmpMN, EmpLN, EmployeeIDNumber FROM employees WHERE EmpID = :id FOR UPDATE");
            $emp->execute([':id' => $empId]);
            $e = $emp->fetch(PDO::FETCH_ASSOC);
            if (!$e) { throw new RuntimeException('Employee not found.'); }

            $cur = $pdo->prepare("SELECT id_number FROM idcard_cards WHERE EmpID = :id FOR UPDATE");
            $cur->execute([':id' => $empId]);
            $card = $cur->fetch(PDO::FETCH_ASSOC);

            if ($card && $card['id_number']) {
                $number = $card['id_number'];
                $action = 'reprint';
                $prev   = null;
            } else {
                $seq    = idc_next_seq($pdo, $year);
                $number = idc_format_number($year, idc_initials($e['EmpFN'], $e['EmpMN'], $e['EmpLN']), $seq);
                $action = 'issue';
                $prev   = $e['EmployeeIDNumber'];
                $pdo->prepare("INSERT INTO idcard_cards (EmpID, id_number, issue_year, seq, first_issued_at, updated_by)
                               VALUES (:id, :n, :y, :s, NOW(), :by)
                               ON DUPLICATE KEY UPDATE id_number = VALUES(id_number), issue_year = VALUES(issue_year),
                                   seq = VALUES(seq), first_issued_at = VALUES(first_issued_at), updated_by = VALUES(updated_by)")
                    ->execute([':id' => $empId, ':n' => $number, ':y' => $year, ':s' => $seq, ':by' => $actor]);
            }

            // keep the 201 "Employee ID" in step with the card
            $pdo->prepare("UPDATE employees SET EmployeeIDNumber = :n WHERE EmpID = :id")
                ->execute([':n' => $number, ':id' => $empId]);
            $pdo->prepare("INSERT INTO idcard_print_log (EmpID, id_number, action, prev_employee_id_number, printed_by)
                           VALUES (:id, :n, :a, :p, :by)")
                ->execute([':id' => $empId, ':n' => $number, ':a' => $action, ':p' => $prev, ':by' => $actor]);

            $pdo->commit();
            return ['id_number' => $number, 'action' => $action];
        } catch (PDOException $ex) {
            $pdo->rollBack();
            // another issue took the same running number in the meantime: try the next one
            if ($ex->getCode() === '23000' && $attempt < 4) { continue; }
            throw $ex;
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $ex;
        }
    }
    throw new RuntimeException('Could not assign an ID number. Try again.');
}

/** The issued card number for an employee, or null (table missing = null). */
function idc_issued_number(PDO $pdo, $empId) {
    try {
        $st = $pdo->prepare("SELECT id_number FROM idcard_cards WHERE EmpID = :id AND id_number IS NOT NULL");
        $st->execute([':id' => $empId]);
        return $st->fetchColumn() ?: null;
    } catch (Throwable $e) {
        return null;
    }
}

} // function_exists guard
