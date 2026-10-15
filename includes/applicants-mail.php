<?php
/* ==========================================================================
   applicants-mail.php  —  the emails an applicant receives (via mail-lib.php):
     received      right after applying (query/apply-action.php)
     offer         HR moves the application to Job offer and ticks "Email"
     not_selected  HR moves it to Not selected and ticks "Email"
     welcome       on Hire, when HR ticks it — never contains the password
   $a is an application as apl_get()/apl_detail() return it. Every value is
   escaped; HR's personal message keeps its line breaks.
   ========================================================================== */
require_once __DIR__ . '/mail-lib.php';

const APL_MAIL_STAGES = ['offer', 'rejected'];   // stage moves that may email the applicant

function apl_mail_e($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

function apl_mail_position(array $a) { return $a['opening'] !== '' && $a['opening'] !== null ? $a['opening'] : 'a position with us'; }

/** HR's own words, set apart in a box; '' when there are none. */
function apl_mail_note($message, $label) {
    $message = trim((string) $message);
    if ($message === '') { return ['', '']; }
    return ['<div style="margin:18px 0;padding:14px 16px;background:#f0f2f5;border-radius:10px">'
          . '<div style="font-size:12px;font-weight:700;color:#586173;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px">' . apl_mail_e($label) . '</div>'
          . wd_mail_text($message) . '</div>',
            "\n$label:\n$message\n"];
}

/** @return array{subject:string, html:string, text:string} */
function apl_mail_received(array $a, $ref) {
    $pos = apl_mail_position($a);
    $html = '<p>Hi ' . apl_mail_e($a['first']) . ',</p>'
          . '<p>Thank you for applying for <b>' . apl_mail_e($pos) . '</b>. We received your application.</p>'
          . '<p style="margin:18px 0;padding:12px 16px;background:#f0f2f5;border-radius:10px">Reference no. <b style="font-family:Consolas,monospace">' . apl_mail_e($ref) . '</b><br>'
          . '<span style="color:#586173;font-size:13px">Quote it if you contact us about your application.</span></p>'
          . '<p style="margin-bottom:6px"><b>What happens next</b></p><ol style="margin-top:0;padding-left:20px;color:#586173">'
          . '<li>HR reviews your application — usually within a few working days.</li>'
          . '<li>We contact you by email or phone for an interview or assessment.</li>'
          . '<li>If you are hired, your details become your employee record — no need to fill them in again.</li></ol>'
          . '<p>Good luck!</p>';
    $text = "Hi {$a['first']},\n\nThank you for applying for $pos. We received your application.\n\nReference no. $ref\n\n"
          . "What happens next:\n1. HR reviews your application — usually within a few working days.\n"
          . "2. We contact you by email or phone for an interview or assessment.\n"
          . "3. If you are hired, your details become your employee record.\n\nGood luck!";
    return ['subject' => 'We received your application — ' . $ref, 'html' => wd_mail_layout('Application received', $html), 'text' => $text];
}

function apl_mail_offer(array $a, $message) {
    $pos = apl_mail_position($a);
    [$noteH, $noteT] = apl_mail_note($message, 'Details from HR');
    $html = '<p>Hi ' . apl_mail_e($a['first']) . ',</p>'
          . '<p>Good news! We are happy to offer you the position of <b>' . apl_mail_e($pos) . '</b>.</p>' . $noteH
          . '<p>Please reply to this email, or call us, to confirm that you accept. If you have questions, we are happy to help.</p>'
          . '<p>We look forward to working with you!</p>';
    $text = "Hi {$a['first']},\n\nGood news! We are happy to offer you the position of $pos.\n$noteT\n"
          . "Please reply to this email, or call us, to confirm that you accept.\n\nWe look forward to working with you!";
    return ['subject' => 'Job offer — ' . $pos, 'html' => wd_mail_layout('Job offer', $html), 'text' => $text];
}

function apl_mail_not_selected(array $a, $message) {
    $pos = apl_mail_position($a);
    [$noteH, $noteT] = apl_mail_note($message, 'A note from HR');
    $html = '<p>Hi ' . apl_mail_e($a['first']) . ',</p>'
          . '<p>Thank you for your interest in <b>' . apl_mail_e($pos) . '</b> and for the time you gave to the process.</p>'
          . '<p>After careful review, we have decided not to move forward with your application at this time.</p>' . $noteH
          . '<p>You are welcome to apply again for future openings. In line with the Data Privacy Act, your application may be deleted from our records.</p>'
          . '<p>We wish you the best.</p>';
    $text = "Hi {$a['first']},\n\nThank you for your interest in $pos and for the time you gave to the process.\n\n"
          . "After careful review, we have decided not to move forward with your application at this time.\n$noteT\n"
          . "You are welcome to apply again for future openings. In line with the Data Privacy Act, your application may be deleted from our records.\n\nWe wish you the best.";
    return ['subject' => 'Your application — ' . $pos, 'html' => wd_mail_layout('About your application', $html), 'text' => $text];
}

/** Welcome on hire: employee no. and username only — HR gives the temporary password in person. */
function apl_mail_welcome(array $a, $empId, $username, $signInUrl) {
    $btn = $signInUrl !== '' ? '<p style="margin:22px 0"><a href="' . apl_mail_e($signInUrl) . '" style="background:#f93627;color:#fff;text-decoration:none;'
          . 'padding:12px 22px;border-radius:999px;font-weight:700;display:inline-block">Go to WeDo sign-in</a></p>' : '';
    $html = '<p>Hi ' . apl_mail_e($a['first']) . ',</p>'
          . '<p>Welcome to the team! Your WeDo employee account is ready.</p>'
          . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:18px 0;background:#f0f2f5;border-radius:10px;width:100%">'
          . '<tr><td style="padding:12px 16px;color:#586173;font-size:13px">Employee no.</td><td style="padding:12px 16px;font-weight:700">' . apl_mail_e($empId) . '</td></tr>'
          . '<tr><td style="padding:0 16px 12px;color:#586173;font-size:13px">Username</td><td style="padding:0 16px 12px;font-weight:700;font-family:Consolas,monospace">' . apl_mail_e($username) . '</td></tr></table>'
          . '<p>For your security, <b>your temporary password is not in this email</b> — HR will give it to you in person. '
          . 'You will choose your own password the first time you sign in.</p>' . $btn
          . '<p>See you soon!</p>';
    $text = "Hi {$a['first']},\n\nWelcome to the team! Your WeDo employee account is ready.\n\nEmployee no.: $empId\nUsername: $username\n\n"
          . "For your security, your temporary password is not in this email — HR will give it to you in person. "
          . "You will choose your own password the first time you sign in.\n" . ($signInUrl !== '' ? "\nSign in: $signInUrl\n" : '') . "\nSee you soon!";
    return ['subject' => 'Welcome to the team, ' . $a['first'] . '!', 'html' => wd_mail_layout('Welcome aboard', $html), 'text' => $text];
}

/** Send one of the emails above to the applicant. @return array{ok:bool, error:string} */
function apl_mail_send(array $a, array $m) {
    return wd_mail_send((string) $a['email'], (string) $a['name'], $m['subject'], $m['html'], $m['text']);
}

/** After a public registration: the "we received your application" email. True when it was sent. */
function apl_mail_after_submit(PDO $pdo, $id, $ref) {
    if (!wd_mail_enabled()) { return false; }
    $a = apl_get($pdo, $id);
    return $a ? apl_mail_send($a, apl_mail_received($a, $ref))['ok'] : false;
}

/** The email for a stage move (offer / rejected), or null for stages that don't send one. */
function apl_mail_for_stage(array $a, $stage, $message) {
    if ($stage === 'offer')    { return apl_mail_offer($a, $message); }
    if ($stage === 'rejected') { return apl_mail_not_selected($a, $message); }
    return null;
}
