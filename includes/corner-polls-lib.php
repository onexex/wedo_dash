<?php
/* ==========================================================================
   corner-polls-lib.php — polls in the Corner (corner.php, query/corner-polls.php)

   Tables (sql/2026-10-28-add-corner-polls.sql):
     corner_polls         one row per poll (question + settings)
     corner_poll_options  its choices, in SortOrder
     corner_poll_votes    one row per person per chosen option

   Super users and holders of the "Create Corner polls" access right start
   polls (wd_poll_can_create); every signed-in employee votes. A poll's author and super users may close
   or delete it. Results show once you have voted, once the poll is closed,
   and always to the people who manage it. Anonymous polls never send voter
   names to the browser.
   ========================================================================== */

require_once __DIR__ . '/corner-lib.php';

const WD_POLL_MAX_OPTIONS = 20;   // incl. suggestions; 10 when creating
const WD_POLL_FACES       = 3;    // voter avatars shown per option

function wd_poll_h($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

function wd_poll_now() { date_default_timezone_set("Asia/Manila"); return date('Y-m-d H:i:s'); }

/* the poll tables exist (the migration has run on this database) */
function wd_polls_ready(PDO $pdo) {
    static $ready = [];
    $key = spl_object_id($pdo);
    if (!isset($ready[$key])) {
        try {
            $st = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
                               AND TABLE_NAME IN ('corner_polls', 'corner_poll_options', 'corner_poll_votes')");
            $ready[$key] = ((int) $st->fetchColumn() === 3);
        } catch (PDOException $e) { $ready[$key] = false; }
    }
    return $ready[$key];
}

function wd_poll_csrf_token() {
    if (empty($_SESSION['poll_csrf'])) { $_SESSION['poll_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['poll_csrf'];
}

function wd_poll_len($s) { return function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s); }

/* collapse whitespace; poll text is single-line except Details */
function wd_poll_clean($s, $multiline = false) {
    $s = str_replace("\r", '', (string) $s);
    $s = $multiline ? preg_replace("/[ \t]+/u", ' ', $s) : preg_replace('/\s+/u', ' ', $s);
    return trim((string) $s);
}

function wd_poll_is_open(array $p) {
    if (!empty($p['ClosedAt'])) { return false; }
    return empty($p['ClosesAt']) || strtotime($p['ClosesAt']) > time();
}

/* May $me start polls? Super users always; everyone else needs the "Create
   Corner polls" access right (accessrights.cpoll = 2). Before
   sql/2026-10-28-add-create-polls-access-right.sql runs, the Corner posters. */
function wd_poll_can_create(PDO $pdo, $me) {
    if (($_SESSION['UserType'] ?? '') == 1) { return true; }
    try {
        $st = $pdo->prepare("SELECT cpoll FROM accessrights WHERE EmpID = ?");
        $st->execute([$me]);
        return (int) $st->fetchColumn() === 2;
    } catch (PDOException $e) {
        return wd_corner_can_post();   // the column isn't there yet
    }
}

function wd_poll_can_manage(array $p, $me) {
    return (string) $p['EmpID'] === (string) $me || (($_SESSION['UserType'] ?? '') == 1);
}

function wd_poll_person($fn, $ln) {
    return ($fn === 'admin') ? 'WeDo Family' : trim($fn . ' ' . $ln);
}

/* ---------------------------------------------------------------- loading */

/* Polls with their options, counts and (non-anonymous) voters.
   $which: 'open' | 'closed' | array of PollIDs. Closed = the most recent few. */
function wd_polls_load(PDO $pdo, $me, $which = 'open', $limit = 6) {
    date_default_timezone_set("Asia/Manila");
    $now = wd_poll_now();
    $sql = "SELECT p.*, e.EmpFN, e.EmpLN FROM corner_polls p LEFT JOIN employees e ON e.EmpID = p.EmpID ";
    $args = [];
    if (is_array($which)) {
        $ids = array_values(array_unique(array_map('intval', $which)));
        if (!$ids) { return []; }
        $sql .= 'WHERE p.PollID IN (' . implode(',', $ids) . ') ORDER BY p.CreatedAt DESC';
    } elseif ($which === 'closed') {
        $sql .= "WHERE p.ClosedAt IS NOT NULL OR (p.ClosesAt IS NOT NULL AND p.ClosesAt <= :now)
                 ORDER BY COALESCE(p.ClosedAt, p.ClosesAt) DESC LIMIT " . (int) $limit;
        $args[':now'] = $now;
    } else {
        $sql .= "WHERE p.ClosedAt IS NULL AND (p.ClosesAt IS NULL OR p.ClosesAt > :now) ORDER BY p.CreatedAt DESC";
        $args[':now'] = $now;
    }
    $st = $pdo->prepare($sql);
    $st->execute($args);
    $polls = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $p) {
        $p['options'] = [];
        $p['voters']  = [];      // EmpIDs who voted at all
        $p['mine']    = [];      // OptionIDs I picked
        $polls[(int) $p['PollID']] = $p;
    }
    if (!$polls) { return []; }
    $in = implode(',', array_keys($polls));

    foreach ($pdo->query("SELECT * FROM corner_poll_options WHERE PollID IN ($in) ORDER BY SortOrder, OptionID")->fetchAll(PDO::FETCH_ASSOC) as $o) {
        $o['votes'] = 0;
        $o['people'] = [];
        $polls[(int) $o['PollID']]['options'][(int) $o['OptionID']] = $o;
    }

    $root = dirname(__DIR__);
    $votes = $pdo->query("SELECT v.PollID, v.OptionID, v.EmpID, e.EmpFN, e.EmpLN,
            (SELECT pp.EmpPPath  FROM empprofiles pp WHERE pp.EmpID = v.EmpID LIMIT 1) AS EmpPPath,
            (SELECT pp.EmpGender FROM empprofiles pp WHERE pp.EmpID = v.EmpID LIMIT 1) AS EmpGender
        FROM corner_poll_votes v LEFT JOIN employees e ON e.EmpID = v.EmpID
        WHERE v.PollID IN ($in) ORDER BY v.VotedAt, v.EmpID")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($votes as $v) {
        $pid = (int) $v['PollID']; $oid = (int) $v['OptionID'];
        if (!isset($polls[$pid]['options'][$oid])) { continue; }
        $polls[$pid]['options'][$oid]['votes']++;
        $polls[$pid]['voters'][$v['EmpID']] = true;
        if ((string) $v['EmpID'] === (string) $me) { $polls[$pid]['mine'][] = $oid; }
        if (!(int) $polls[$pid]['Anonymous']) {
            $polls[$pid]['options'][$oid]['people'][] = [
                'name'   => wd_poll_person((string) $v['EmpFN'], (string) $v['EmpLN']) ?: (string) $v['EmpID'],
                'avatar' => wd_corner_avatar($v['EmpPPath'], $v['EmpGender'], $root),
            ];
        }
    }
    return $polls;
}

/* ---------------------------------------------------------------- writes
   Each returns null on success or a message for the person. */

function wd_poll_create(PDO $pdo, $me, array $in, &$newId = null) {
    $q = wd_poll_clean($in['question'] ?? '');
    $details = wd_poll_clean($in['details'] ?? '', true);
    if (wd_poll_len($q) < 3)    { return 'Write a question of at least 3 characters.'; }
    if (wd_poll_len($q) > 200)  { return 'Keep the question under 200 characters.'; }
    if (wd_poll_len($details) > 500) { return 'Keep the details under 500 characters.'; }

    $raw = $in['options'] ?? [];
    if (!is_array($raw)) { $raw = json_decode((string) $raw, true) ?: []; }
    $opts = []; $seen = [];
    foreach ($raw as $o) {
        $o = wd_poll_clean($o);
        if ($o === '') { continue; }
        if (wd_poll_len($o) > 120) { return 'Keep each option under 120 characters.'; }
        $k = function_exists('mb_strtolower') ? mb_strtolower($o, 'UTF-8') : strtolower($o);
        if (isset($seen[$k])) { continue; }
        $seen[$k] = true;
        $opts[] = $o;
    }
    if (count($opts) < 2)  { return 'Add at least 2 different options.'; }
    if (count($opts) > 10) { return 'A poll can start with up to 10 options.'; }

    $closesAt = null;
    $closes = (string) ($in['closes'] ?? 'none');
    $presets = ['1d' => '+1 day', '3d' => '+3 days', '7d' => '+7 days'];
    date_default_timezone_set("Asia/Manila");
    if (isset($presets[$closes])) {
        $closesAt = date('Y-m-d H:i:s', strtotime($presets[$closes]));
    } elseif ($closes === 'custom') {
        $t = strtotime((string) ($in['closes_at'] ?? ''));
        if (!$t || $t <= time() + 60)        { return 'Pick a closing time in the future.'; }
        if ($t > strtotime('+1 year'))       { return 'Pick a closing time within a year.'; }
        $closesAt = date('Y-m-d H:i:00', $t);
    }

    $now = wd_poll_now();
    $pdo->beginTransaction();
    try {
        $pdo->prepare("INSERT INTO corner_polls (EmpID, Question, Details, MultiChoice, AllowAdd, Anonymous, ClosesAt, CreatedAt)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$me, $q, $details, !empty($in['multi']) ? 1 : 0, !empty($in['allow_add']) ? 1 : 0,
                       !empty($in['anonymous']) ? 1 : 0, $closesAt, $now]);
        $newId = (int) $pdo->lastInsertId();
        $add = $pdo->prepare("INSERT INTO corner_poll_options (PollID, Label, AddedBy, SortOrder) VALUES (?, ?, ?, ?)");
        foreach ($opts as $i => $o) { $add->execute([$newId, $o, $me, $i + 1]); }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    wd_poll_announce($pdo, $me, $newId, $q, $now);
    return null;
}

/* Every notification surface (bell, Notifications page, Corner badge and
   bubble, the app) is built from `announcements`, so a new poll also posts a
   short one there. Title "Poll#<id>" marks it; the Corner feed shows it as a
   "started a poll" card. The table is latin1: characters it can't hold (emoji)
   are left out of that text. Never stops the poll itself from being created. */
function wd_poll_announce(PDO $pdo, $me, $pollId, $question, $when) {
    try {
        $text = trim(preg_replace('/\s+/u', ' ', preg_replace('/[^\x{0000}-\x{00FF}]/u', '', (string) $question)));
        $pdo->prepare("INSERT INTO announcements (EmpID, Title, ADesc, ADate) VALUES (?, ?, ?, ?)")
            ->execute([$me, 'Poll#' . (int) $pollId, 'New poll: ' . ($text !== '' ? $text : 'vote in the Corner'), $when]);
    } catch (PDOException $e) {
        error_log('[wedo polls] announcement for poll ' . (int) $pollId . ': ' . $e->getMessage());
    }
}

function wd_poll_row(PDO $pdo, $pollId) {
    $st = $pdo->prepare("SELECT * FROM corner_polls WHERE PollID = ?");
    $st->execute([(int) $pollId]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

/* Pick an option, or un-pick it when it is already yours. Single choice: a new
   pick replaces your old one. */
function wd_poll_vote(PDO $pdo, $me, $pollId, $optionId) {
    $p = wd_poll_row($pdo, $pollId);
    if (!$p) { return 'This poll no longer exists.'; }
    if (!wd_poll_is_open($p)) { return 'This poll is closed.'; }
    $st = $pdo->prepare("SELECT 1 FROM corner_poll_options WHERE OptionID = ? AND PollID = ?");
    $st->execute([(int) $optionId, (int) $pollId]);
    if (!$st->fetchColumn()) { return 'That option is no longer in this poll.'; }

    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare("DELETE FROM corner_poll_votes WHERE OptionID = ? AND EmpID = ?");
        $del->execute([(int) $optionId, $me]);
        if ($del->rowCount() === 0) {
            if (!(int) $p['MultiChoice']) {
                $pdo->prepare("DELETE FROM corner_poll_votes WHERE PollID = ? AND EmpID = ?")->execute([(int) $pollId, $me]);
            }
            $pdo->prepare("INSERT IGNORE INTO corner_poll_votes (PollID, OptionID, EmpID, VotedAt) VALUES (?, ?, ?, ?)")
                ->execute([(int) $pollId, (int) $optionId, $me, wd_poll_now()]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    return null;
}

/* A voter suggests an option (when the poll allows it); it also gets their vote. */
function wd_poll_add_option(PDO $pdo, $me, $pollId, $label) {
    $p = wd_poll_row($pdo, $pollId);
    if (!$p) { return 'This poll no longer exists.'; }
    if (!wd_poll_is_open($p)) { return 'This poll is closed.'; }
    if (!(int) $p['AllowAdd'] && !wd_poll_can_manage($p, $me)) { return 'This poll doesn\'t take suggestions.'; }
    $label = wd_poll_clean($label);
    if ($label === '')               { return 'Type an option first.'; }
    if (wd_poll_len($label) > 120)   { return 'Keep the option under 120 characters.'; }

    $st = $pdo->prepare("SELECT OptionID, Label FROM corner_poll_options WHERE PollID = ?");
    $st->execute([(int) $pollId]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $lower = function ($s) { return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s); };
    foreach ($rows as $r) {
        if ($lower($r['Label']) === $lower($label)) {
            // already there: just vote for it (unless it is already yours)
            $mine = $pdo->prepare("SELECT 1 FROM corner_poll_votes WHERE OptionID = ? AND EmpID = ?");
            $mine->execute([(int) $r['OptionID'], $me]);
            return $mine->fetchColumn() ? null : wd_poll_vote($pdo, $me, $pollId, $r['OptionID']);
        }
    }
    if (count($rows) >= WD_POLL_MAX_OPTIONS) { return 'This poll already has ' . WD_POLL_MAX_OPTIONS . ' options.'; }

    $pdo->prepare("INSERT INTO corner_poll_options (PollID, Label, AddedBy, SortOrder)
                   SELECT ?, ?, ?, COALESCE(MAX(SortOrder), 0) + 1 FROM corner_poll_options WHERE PollID = ?")
        ->execute([(int) $pollId, $label, $me, (int) $pollId]);
    return wd_poll_vote($pdo, $me, $pollId, (int) $pdo->lastInsertId());
}

function wd_poll_close(PDO $pdo, $me, $pollId) {
    $p = wd_poll_row($pdo, $pollId);
    if (!$p) { return 'This poll no longer exists.'; }
    if (!wd_poll_can_manage($p, $me)) { return 'Only the person who started this poll can close it.'; }
    if (!wd_poll_is_open($p)) { return null; }
    $pdo->prepare("UPDATE corner_polls SET ClosedAt = ? WHERE PollID = ?")->execute([wd_poll_now(), (int) $pollId]);
    return null;
}

function wd_poll_delete(PDO $pdo, $me, $pollId) {
    $p = wd_poll_row($pdo, $pollId);
    if (!$p) { return null; }
    if (!wd_poll_can_manage($p, $me)) { return 'Only the person who started this poll can delete it.'; }
    $pdo->beginTransaction();
    foreach (['corner_poll_votes', 'corner_poll_options', 'corner_polls'] as $t) {
        $pdo->prepare("DELETE FROM `$t` WHERE PollID = ?")->execute([(int) $pollId]);
    }
    // its "New poll" announcement goes too (bell, Notifications page, Corner feed)
    $pdo->prepare("DELETE s FROM annseen s JOIN announcements a ON a.aid = s.aid WHERE a.Title = ?")->execute(['Poll#' . (int) $pollId]);
    $pdo->prepare("DELETE FROM announcements WHERE Title = ?")->execute(['Poll#' . (int) $pollId]);
    $pdo->commit();
    return null;
}

/* ---------------------------------------------------------------- render */

/* "Closes in 25 min", "Closes today, 5:00 PM", "Closes tomorrow, 9:00 AM", "Closes Fri, 5:00 PM", "Closes Nov 12" */
function wd_poll_status(array $p) {
    date_default_timezone_set("Asia/Manila");
    if (!wd_poll_is_open($p)) {
        $when = wd_corner_ago($p['ClosedAt'] ?: $p['ClosesAt']);
        return 'Closed ' . ($when === 'Yesterday' ? 'yesterday' : $when);
    }
    if (empty($p['ClosesAt'])) { return 'Open'; }
    $t = strtotime($p['ClosesAt']);
    $left = $t - time();
    if ($left < 3600) { return 'Closes in ' . max(1, (int) ceil($left / 60)) . ' min'; }
    if (date('Y-m-d', $t) === date('Y-m-d'))                     { return 'Closes today, ' . date('g:i A', $t); }
    if (date('Y-m-d', $t) === date('Y-m-d', strtotime('+1 day'))) { return 'Closes tomorrow, ' . date('g:i A', $t); }
    if ($left < 6 * 86400) { return 'Closes ' . date('D, g:i A', $t); }
    return 'Closes ' . date('M j', $t);
}

function wd_poll_card(array $p, $me) {
    $open    = wd_poll_is_open($p);
    $manage  = wd_poll_can_manage($p, $me);
    $multi   = (int) $p['MultiChoice'] === 1;
    $anon    = (int) $p['Anonymous'] === 1;
    $people  = count($p['voters']);
    $voted   = count($p['mine']) > 0;
    $reveal  = $voted || !$open || $manage;
    $max     = 0;
    foreach ($p['options'] as $o) { $max = max($max, $o['votes']); }
    $pid     = (int) $p['PollID'];
    $sig     = md5(json_encode([$open, $p['mine'], array_map(function ($o) { return [$o['Label'], $o['votes'], count($o['people'])]; }, $p['options']), wd_poll_status($p)]));

    $h  = '<article id="poll-' . $pid . '" class="cp' . ($open ? '' : ' is-closed') . ($reveal ? ' is-revealed' : '') . '" data-poll="' . $pid . '" data-sig="' . $sig . '">';
    $h .= '<header class="cp__head">'
        . '<span class="cp__tag"><i class="fa-solid fa-square-poll-horizontal" aria-hidden="true"></i> Poll</span>'
        . '<span class="cp__status' . ($open ? ' is-open' : '') . '">' . wd_poll_h(wd_poll_status($p)) . '</span>';
    if ($manage) {
        $h .= '<div class="cp__menu">'
            . '<button type="button" class="cp__menubtn" aria-haspopup="true" aria-expanded="false" aria-label="Poll options"><i class="fa-solid fa-ellipsis"></i></button>'
            . '<div class="cp__menulist" role="menu" hidden>'
            . ($open ? '<button type="button" role="menuitem" data-act="close"><i class="fa-solid fa-lock"></i> Close voting now</button>' : '')
            . '<button type="button" role="menuitem" data-act="delete" class="is-danger"><i class="fa-solid fa-trash-can"></i> Delete poll</button>'
            . '</div></div>';
    }
    $h .= '</header>';
    $h .= '<h4 class="cp__q">' . wd_poll_h($p['Question']) . '</h4>';
    if (trim((string) $p['Details']) !== '') {
        $h .= '<p class="cp__details">' . nl2br(wd_poll_h($p['Details'])) . '</p>';
    }

    $h .= '<div class="cp__opts" role="group" aria-label="' . ($multi ? 'Pick any' : 'Pick one') . '">';
    foreach ($p['options'] as $oid => $o) {
        $mine = in_array((int) $oid, $p['mine'], true);
        $pct  = $people > 0 ? (int) round($o['votes'] * 100 / $people) : 0;
        $lead = $reveal && $max > 0 && $o['votes'] === $max;
        $cls  = 'cp__opt' . ($mine ? ' is-mine' : '') . ($lead ? ' is-lead' : '');
        $aria = wd_poll_h($o['Label']) . ($reveal ? ', ' . $o['votes'] . ($o['votes'] === 1 ? ' vote' : ' votes') . ', ' . $pct . '%' : '') . ($mine ? ', your vote' : '');
        $h .= '<button type="button" class="' . $cls . '" data-option="' . (int) $oid . '" aria-pressed="' . ($mine ? 'true' : 'false') . '"'
            . ' aria-label="' . $aria . '"' . ($open ? '' : ' disabled') . '>'
            . '<span class="cp__bar" data-w="' . ($reveal ? $pct : 0) . '" style="width:' . ($reveal ? $pct : 0) . '%"></span>'
            . '<span class="cp__mark cp__mark--' . ($multi ? 'box' : 'dot') . '" aria-hidden="true"><i class="fa-solid fa-check"></i></span>'
            . '<span class="cp__label">' . wd_poll_h($o['Label'])
            . ((string) $o['AddedBy'] !== (string) $p['EmpID'] ? ' <small class="cp__sugg">suggested</small>' : '')
            . (!$open && $lead ? ' <i class="fa-solid fa-trophy cp__trophy" title="Most votes"></i>' : '')
            . '</span>';
        if ($reveal) {
            if (!$anon && $o['people']) {
                $names = array_column($o['people'], 'name');
                $title = implode(', ', array_slice($names, 0, 15)) . (count($names) > 15 ? ' and ' . (count($names) - 15) . ' more' : '');
                $h .= '<span class="cp__faces" title="' . wd_poll_h($title) . '">';
                foreach (array_slice($o['people'], 0, WD_POLL_FACES) as $f) {
                    $h .= '<img src="' . wd_poll_h($f['avatar']) . '" alt="" width="22" height="22" loading="lazy">';
                }
                if (count($o['people']) > WD_POLL_FACES) { $h .= '<span class="cp__more">+' . (count($o['people']) - WD_POLL_FACES) . '</span>'; }
                $h .= '</span>';
            }
            $h .= '<span class="cp__pct">' . $pct . '%</span>';
        }
        $h .= '</button>';
    }
    $h .= '</div>';

    if ($open && ((int) $p['AllowAdd'] || $manage) && count($p['options']) < WD_POLL_MAX_OPTIONS) {
        $h .= '<form class="cp__add" autocomplete="off">'
            . '<i class="fa-solid fa-plus" aria-hidden="true"></i>'
            . '<input type="text" maxlength="120" placeholder="' . ((int) $p['AllowAdd'] ? 'Suggest another option…' : 'Add an option…') . '" aria-label="Add an option">'
            . '<button type="submit">Add</button>'
            . '</form>';
    }

    $by   = wd_poll_person((string) $p['EmpFN'], (string) $p['EmpLN']) ?: (string) $p['EmpID'];
    $bits = [($people === 1 ? '1 person voted' : $people . ' people voted')];
    if ($multi) { $bits[] = 'Pick any'; }
    if ($anon)  { $bits[] = '<span title="Nobody can see who voted for what"><i class="fa-solid fa-user-secret"></i> Anonymous</span>'; }
    $hint = '';
    if ($open && !$reveal)   { $hint = 'Vote to see the results'; }
    elseif ($open && $voted) { $hint = $multi ? 'Tap an option again to remove it' : 'Tap your pick again to take it back'; }

    $h .= '<footer class="cp__foot">'
        . '<span>' . implode(' · ', array_map(function ($b) { return strpos($b, '<span') === 0 ? $b : wd_poll_h($b); }, $bits)) . '</span>'
        . '<span class="cp__by">by ' . wd_poll_h($by) . ' · ' . wd_poll_h(wd_corner_ago($p['CreatedAt'])) . '</span>'
        . ($hint !== '' ? '<span class="cp__hint">' . wd_poll_h($hint) . '</span>' : '')
        . '</footer>';
    $h .= '</article>';
    return $h;
}
