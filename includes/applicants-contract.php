<?php
/* ==========================================================================
   applicants-contract.php  —  the contract a new hire gets by email on Hire:
     Trainee                 -> includes/contracts/trainee.html
     Independent Contractor  -> includes/contracts/independent-contractor.html
   The wording lives in those two files; {{field}} placeholders are filled in
   from the Hire dialog (company, department, position, superior, hire date,
   pay) and the registration (name, address). Every value is escaped.
   A template whose first line is "<!-- draft" is sample wording: the
   Hire dialog says so and does not tick "Email the contract" by default.
   ========================================================================== */
require_once __DIR__ . '/applicants-mail.php';

const APL_CONTRACT_TITLES = ['trainee' => 'Training Agreement', 'independent-contractor' => 'Independent Contractor Agreement'];

function apl_contract_file($kind) {
    return isset(APL_CONTRACT_TITLES[$kind]) ? __DIR__ . '/contracts/' . $kind . '.html' : null;
}

/** True while the template still has the sample wording (first line "<!-- draft"). */
function apl_contract_is_draft($kind) {
    $f = apl_contract_file($kind);
    if (!$f || !is_file($f)) { return true; }
    $h = @fopen($f, 'r');
    $first = $h ? (string) fgets($h) : '';
    if ($h) { fclose($h); }
    return stripos(ltrim($first), '<!-- draft') === 0;
}

/** For the Hire dialog: classification id => {kind, title, draft}. */
function apl_contract_info() {
    $out = [];
    foreach (APL_HIRE_CLASSES as $class => $kind) {
        $out[$class] = ['kind' => $kind, 'title' => APL_CONTRACT_TITLES[$kind], 'draft' => apl_contract_is_draft($kind)];
    }
    return $out;
}

function apl_contract_peso($v) { return '₱' . number_format((float) $v, 2); }

function apl_contract_long_date($ymd) {
    $t = strtotime((string) $ymd);
    return $t ? date('F j, Y', $t) : (string) $ymd;
}

/**
 * The placeholder values: $r an applicants row (apl_row), $v the checked
 * hire values (apl_hire_values), $empId the new employee no. ('' before hire).
 */
function apl_contract_fields(array $r, array $v, $empId) {
    $address = implode(', ', array_filter(array_map('trim', [(string) $r['Street'], (string) $r['Barangay'], (string) $r['City'],
                                                             (string) $r['Province'], (string) $r['Zip'], (string) $r['Country']])));
    return [
        'date'           => apl_contract_long_date(date('Y-m-d')),
        'name'           => apl_name($r),
        'first_name'     => (string) $r['FirstName'],
        'address'        => $address,
        'company'        => $v['company'],
        'department'     => $v['department'],
        'position'       => $v['position'],
        'superior'       => $v['superiorName'] !== '' ? $v['superiorName'] : 'the department head',
        'start_date'     => apl_contract_long_date($v['hired']),
        'basic'          => apl_contract_peso($v['money']['basic']),
        'allowance'      => apl_contract_peso($v['money']['allowance']),
        'hourly'         => apl_contract_peso($v['money']['hourly']),
        'emp_id'         => $empId !== '' ? $empId : '(given on hire)',
        'classification' => $v['className'],
    ];
}

/**
 * The contract for the checked hire values, filled in.
 * @return array{ok:bool, msg?:string, kind?:string, title?:string, html?:string, draft?:bool}
 */
function apl_contract_render(array $r, array $v, $empId = '') {
    $kind = $v['contract'] ?? '';
    $file = apl_contract_file($kind);
    if (!$file || !is_file($file)) { return ['ok' => false, 'msg' => 'There is no contract for this classification yet.']; }
    $tpl = (string) file_get_contents($file);
    $tpl = preg_replace('/<!--.*?-->\s*/s', '', $tpl);   // the notes for whoever edits the template
    $fields = apl_contract_fields($r, $v, (string) $empId);
    $html = preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', function ($m) use ($fields) {
        return isset($fields[$m[1]]) ? apl_mail_e($fields[$m[1]]) : $m[0];
    }, $tpl);
    $title = APL_CONTRACT_TITLES[$kind];
    return ['ok' => true, 'kind' => $kind, 'title' => $title, 'html' => '<h2 style="text-align:center;margin:0 0 18px">' . apl_mail_e($title) . '</h2>' . $html,
            'draft' => apl_contract_is_draft($kind)];
}

/** A whole printable page around the contract (the email attachment and the Hire dialog preview). */
function apl_contract_page(array $c) {
    return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . apl_mail_e($c['title']) . '</title>'
         . '<style>body{font-family:Georgia,"Times New Roman",serif;font-size:15px;line-height:1.6;color:#111;max-width:760px;margin:32px auto;padding:0 24px}'
         . 'ol li{margin-bottom:8px}@media print{body{margin:0 auto}}</style></head><body>' . $c['html'] . '</body></html>';
}

/** The contract email: a short note, the contract in the body, and the same as an attachment to print. */
function apl_mail_contract(array $a, array $c, $startDate) {
    $file = preg_replace('/[^A-Za-z0-9]+/', '-', $c['title'] . ' ' . $a['name']) . '.html';
    $html = '<p>Hi ' . apl_mail_e($a['first']) . ',</p>'
          . '<p>Here is your <b>' . apl_mail_e($c['title']) . '</b>. Please read it, print and sign it, and bring it on your first day, <b>'
          . apl_mail_e(apl_contract_long_date($startDate)) . '</b>. A printable copy is attached.</p>'
          . '<div style="margin:20px 0;padding:22px 24px;border:1px solid #e3e6ec;border-radius:10px;font-family:Georgia,\'Times New Roman\',serif;line-height:1.6">'
          . $c['html'] . '</div>'
          . '<p>If you have questions about the agreement, just reply to this email.</p>';
    $text = "Hi {$a['first']},\n\nHere is your {$c['title']}. Please read it, print and sign it, and bring it on your first day, "
          . apl_contract_long_date($startDate) . ".\nA printable copy is attached ($file).\n\n"
          . trim(preg_replace("/[ \t]*\n\s*\n\s*/", "\n\n", html_entity_decode(strip_tags(preg_replace('#<(br|/p|/li|/h2|/tr)\b[^>]*>#i', "\n", $c['html'])), ENT_QUOTES, 'UTF-8')))
          . "\n\nIf you have questions about the agreement, just reply to this email.";
    return ['subject' => $c['title'] . ' — ' . $a['name'], 'html' => wd_mail_layout($c['title'], $html), 'text' => $text,
            'attachments' => [['name' => $file, 'content' => apl_contract_page($c)]]];
}
