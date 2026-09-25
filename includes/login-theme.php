<?php
/* ==========================================================================
   includes/login-theme.php  —  seasonal login page (Maintenance > Login Theme)
   ---------------------------------------------------------------------------
   Single source of truth for the preset catalog and the rule that picks which
   scheduled row (if any) shows on login.php today. Used by login.php (render),
   logintheme.php (admin screen) and query/logintheme-action.php (save).

   Rows live in `login_themes` (sql/2026-09-25-add-login-themes.sql). Colours
   and effects are NOT in the DB: they come from lt_presets(), are always
   emitted by us and never typed by a user, which is what keeps them safe to
   print into the page's <style>. Everything a user types is htmlspecialchars'd
   at render time.
   ========================================================================== */

if (!function_exists('lt_presets')) {

/**
 * Colour keys override login.php's theme variables:
 *   primary/primaryDark -> Sign in button + focus ring (replaces --brand)
 *   accent              -> headline highlight, badge, season pill
 *   bg1..3, glow1..2    -> page background + ambient glows
 *   panel1..3           -> admin swatches / quick-look preview
 * Icons are Font Awesome 6 solid names (the app loads FA 6.5.1).
 */
function lt_presets() {
    return [
        'christmas' => [
            'name' => 'Christmas', 'icon' => 'fa-tree', 'badge' => 'fa-gifts', 'pill_icon' => 'fa-snowflake',
            'lights' => true,
            'effect' => ['kind' => 'snow'],
            'colors' => [
                'primary' => '#c62828', 'primaryDark' => '#8e1b1b', 'primaryDeep' => '#0f3d23', 'accent' => '#f5c542',
                'bg1' => '#0f3d23', 'bg2' => '#0a2a18', 'bg3' => '#05170d',
                'glow1' => 'rgba(198,40,40,.40)', 'glow2' => 'rgba(245,197,66,.22)',
                'panel1' => '#1b5e36', 'panel2' => '#124726', 'panel3' => '#0a2a17',
            ],
            'defaults' => [
                'season_label' => "Season's Greetings", 'headline' => 'Maligayang', 'headline_accent' => 'Pasko!',
                'message' => 'Merry Christmas from all of us. Thank you for a wonderful year of hard work — enjoy the holidays with your loved ones.',
            ],
        ],
        'newyear' => [
            'name' => 'New Year', 'icon' => 'fa-champagne-glasses', 'badge' => 'fa-champagne-glasses', 'pill_icon' => 'fa-star',
            'effect' => ['kind' => 'confetti', 'colors' => ['#ffd76a', '#ffffff', '#c9a227', '#9d7bff', '#ff6b9d']],
            'colors' => [
                'primary' => '#c9a227', 'primaryDark' => '#8a6d12', 'primaryDeep' => '#0b1233', 'accent' => '#ffd76a',
                'bg1' => '#1a2152', 'bg2' => '#0b1233', 'bg3' => '#05081c',
                'glow1' => 'rgba(201,162,39,.35)', 'glow2' => 'rgba(120,90,255,.25)',
                'panel1' => '#26307a', 'panel2' => '#141b4d', 'panel3' => '#080c26',
            ],
            'defaults' => [
                'season_label' => 'Cheers to the New Year', 'headline' => 'Happy', 'headline_accent' => 'New Year!',
                'message' => 'A fresh year, fresh goals. Wishing you prosperity and good health.',
            ],
        ],
        'chinesenewyear' => [
            'name' => 'Chinese New Year', 'icon' => 'fa-dragon', 'badge' => 'fa-coins', 'pill_icon' => 'fa-dragon',
            'effect' => ['kind' => 'glow', 'colors' => ['#ffd54f', '#ff5252', '#ffab40']],
            'colors' => [
                'primary' => '#d32f2f', 'primaryDark' => '#9a0007', 'primaryDeep' => '#4a0000', 'accent' => '#ffd54f',
                'bg1' => '#5a0a0a', 'bg2' => '#3a0505', 'bg3' => '#1f0202',
                'glow1' => 'rgba(255,82,82,.40)', 'glow2' => 'rgba(255,213,79,.25)',
                'panel1' => '#b71c1c', 'panel2' => '#7f0000', 'panel3' => '#4a0000',
            ],
            'defaults' => [
                'season_label' => 'Kung Hei Fat Choi', 'headline' => 'Happy', 'headline_accent' => 'Chinese New Year!',
                'message' => 'Wishing you good fortune, good health and prosperity in the year ahead.',
            ],
        ],
        'valentines' => [
            'name' => "Valentine's Day", 'icon' => 'fa-heart', 'badge' => 'fa-heart', 'pill_icon' => 'fa-heart',
            'effect' => ['kind' => 'hearts'],
            'colors' => [
                'primary' => '#e0457b', 'primaryDark' => '#b0285a', 'primaryDeep' => '#5c0f2e', 'accent' => '#ffb3c9',
                'bg1' => '#5c0f2e', 'bg2' => '#3d0a1f', 'bg3' => '#220512',
                'glow1' => 'rgba(224,69,123,.45)', 'glow2' => 'rgba(255,179,201,.25)',
                'panel1' => '#e0457b', 'panel2' => '#b0285a', 'panel3' => '#5c0f2e',
            ],
            'defaults' => [
                'season_label' => 'Hearts Day', 'headline' => 'Happy', 'headline_accent' => 'Hearts Day!',
                'message' => 'Spread the love at work — show appreciation to the teammates who make your day better.',
            ],
        ],
        'independence' => [
            'name' => 'Independence Day', 'icon' => 'fa-flag', 'badge' => 'fa-sun', 'pill_icon' => 'fa-sun',
            'effect' => ['kind' => 'confetti', 'colors' => ['#0038a8', '#ce1126', '#fcd116', '#ffffff']],
            'colors' => [
                'primary' => '#0038a8', 'primaryDark' => '#002a7f', 'primaryDeep' => '#001a4d', 'accent' => '#fcd116',
                'bg1' => '#0a2a6b', 'bg2' => '#061b47', 'bg3' => '#030e26',
                'glow1' => 'rgba(206,17,38,.35)', 'glow2' => 'rgba(252,209,22,.25)',
                'panel1' => '#0038a8', 'panel2' => '#002a7f', 'panel3' => '#001a4d',
            ],
            'defaults' => [
                'season_label' => 'June 12', 'headline' => 'Mabuhay ang', 'headline_accent' => 'Pilipinas!',
                'message' => 'Happy Independence Day! Let us celebrate the freedom we share as Filipinos.',
            ],
        ],
        'halloween' => [
            'name' => 'Halloween / Undas', 'icon' => 'fa-ghost', 'badge' => 'fa-ghost', 'pill_icon' => 'fa-moon',
            'effect' => ['kind' => 'glow', 'colors' => ['#e8741c', '#b388ff', '#ffd180']],
            'colors' => [
                'primary' => '#e8741c', 'primaryDark' => '#b3540c', 'primaryDeep' => '#2a1140', 'accent' => '#ffb26b',
                'bg1' => '#2a1140', 'bg2' => '#1a0a29', 'bg3' => '#0d0515',
                'glow1' => 'rgba(232,116,28,.35)', 'glow2' => 'rgba(179,136,255,.25)',
                'panel1' => '#4a1e6e', 'panel2' => '#2e1147', 'panel3' => '#170826',
            ],
            'defaults' => [
                'season_label' => 'Undas Season', 'headline' => 'Happy', 'headline_accent' => 'Halloween!',
                'message' => 'Travel safe to the province and take time to remember your loved ones.',
            ],
        ],
        // WeDo brand red on navy, with the admin's own banner image.
        'custom' => [
            'name' => 'Company Event', 'icon' => 'fa-star', 'badge' => 'fa-star', 'pill_icon' => 'fa-bolt',
            'effect' => ['kind' => 'confetti', 'colors' => ['#f93627', '#ffffff', '#ffd76a', '#ff8a80']],
            'colors' => [
                'primary' => '#f93627', 'primaryDark' => '#bf2417', 'primaryDeep' => '#09152e', 'accent' => '#ffd76a',
                'bg1' => '#0f2147', 'bg2' => '#09152e', 'bg3' => '#050b1a',
                'glow1' => 'rgba(249,54,39,.35)', 'glow2' => 'rgba(255,215,106,.20)',
                'panel1' => '#f93627', 'panel2' => '#bf2417', 'panel3' => '#09152e',
            ],
            'defaults' => [
                'season_label' => 'Company Event', 'headline' => 'Company', 'headline_accent' => 'Anniversary',
                'message' => 'Join the celebration! See GM\'s Corner for the details.',
            ],
        ],
        // Keeps the normal login page; only the announcement bar shows. For
        // notices like "Office closed on Friday".
        'plain' => [
            'name' => 'Announcement only', 'icon' => 'fa-bullhorn', 'badge' => null, 'pill_icon' => null,
            'plain' => true,
            'effect' => null,
            'colors' => [],
            'defaults' => ['season_label' => null, 'headline' => null, 'headline_accent' => null, 'message' => null],
        ],
    ];
}

function lt_preset($key) {
    $p = lt_presets();
    return isset($p[$key]) ? $p[$key] : null;
}

function lt_today() {
    return new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
}

function lt_date($v) {
    return $v instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($v) : new DateTimeImmutable((string) $v, new DateTimeZone('Asia/Manila'));
}

/** Does the row's window cover $date? Yearly rows compare month-day only and may wrap the year end. */
function lt_covers(array $t, DateTimeInterface $date) {
    $s = lt_date($t['starts_on']);
    $e = lt_date($t['ends_on']);
    if (empty($t['repeats_yearly'])) {
        $d = $date->format('Y-m-d');
        return $d >= $s->format('Y-m-d') && $d <= $e->format('Y-m-d');
    }
    $md = $date->format('md');
    $sm = $s->format('md');
    $em = $e->format('md');
    return $sm <= $em ? ($md >= $sm && $md <= $em) : ($md >= $sm || $md <= $em);
}

/** Window length in days — the tie-breaker when two rows overlap. */
function lt_window_days(array $t) {
    $s = lt_date($t['starts_on']);
    $e = lt_date($t['ends_on']);
    if (empty($t['repeats_yearly'])) {
        return (int) $s->diff($e)->days;
    }
    $a = new DateTimeImmutable(sprintf('2001-%02d-%02d', (int) $s->format('n'), min((int) $s->format('j'), 28)));
    $b = new DateTimeImmutable(sprintf('2001-%02d-%02d', (int) $e->format('n'), min((int) $e->format('j'), 28)));
    if ($b < $a) { $b = $b->modify('+1 year'); }
    return (int) $a->diff($b)->days;
}

/**
 * The row showing on $date, or null for the standard login page. When windows
 * overlap: a one-time entry beats a yearly one, then the shorter window wins,
 * then the most recently edited.
 */
function lt_pick(array $rows, DateTimeInterface $date) {
    $hits = array_values(array_filter($rows, function ($t) use ($date) {
        return !empty($t['is_active']) && lt_preset($t['preset']) && lt_covers($t, $date);
    }));
    usort($hits, function ($a, $b) {
        return [(int) !empty($a['repeats_yearly']), lt_window_days($a), -strtotime((string) ($a['updated_at'] ?? '1970-01-01'))]
           <=> [(int) !empty($b['repeats_yearly']), lt_window_days($b), -strtotime((string) ($b['updated_at'] ?? '1970-01-01'))];
    });
    return $hits ? $hits[0] : null;
}

function lt_active_on(PDO $pdo, DateTimeInterface $date) {
    $rows = $pdo->query("SELECT * FROM login_themes WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    return lt_pick($rows, $date);
}

/**
 * What login.php renders today. Never throws: a missing table (before the SQL
 * file has been run) or a DB hiccup must not take the sign-in page down.
 */
function lt_for_login(PDO $pdo) {
    try {
        $t = lt_active_on($pdo, lt_today());
        return $t ? lt_view_data($t) : null;
    } catch (Throwable $e) {
        return null;
    }
}

function lt_banner_url($path) {
    return $path ? 'assets/images/login-themes/' . rawurlencode(basename($path)) : null;
}

/** Flattened preset + row for the view. Blank wording falls back to the preset's defaults. */
function lt_view_data(array $t) {
    $preset = lt_preset($t['preset']) ?: lt_preset('plain');
    $plain  = !empty($preset['plain']);
    $pick   = function ($f) use ($t, $preset, $plain) {
        if ($plain) { return null; }
        return (isset($t[$f]) && trim((string) $t[$f]) !== '') ? $t[$f] : $preset['defaults'][$f];
    };
    $fx = !empty($t['show_effects']);
    return [
        'id'           => (int) ($t['id'] ?? 0),
        'preset'       => $t['preset'],
        'plain'        => $plain,
        'colors'       => $preset['colors'],
        'badge'        => $plain ? null : $preset['badge'],
        'pill_icon'    => $preset['pill_icon'],
        'lights'       => !$plain && !empty($preset['lights']) && $fx,
        'effect'       => (!$plain && $fx) ? $preset['effect'] : null,
        'season_label' => $pick('season_label'),
        'headline'     => $pick('headline'),
        'accent'       => $pick('headline_accent'),
        'message'      => $pick('message'),
        'announcement' => (isset($t['announcement']) && trim((string) $t['announcement']) !== '') ? $t['announcement'] : null,
        'banner_url'   => $plain ? null : lt_banner_url($t['banner_path'] ?? null),
    ];
}

/** The next date this window opens on or after $from (null when a one-time window is over). */
function lt_next_start(array $t, DateTimeImmutable $from) {
    $s = lt_date($t['starts_on']);
    if (empty($t['repeats_yearly'])) {
        return lt_date($t['ends_on'])->format('Y-m-d') < $from->format('Y-m-d') ? null : $s;
    }
    $make = function ($year) use ($s) {
        $first = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, (int) $s->format('n')));
        return $first->setDate($year, (int) $s->format('n'), min((int) $s->format('j'), (int) $first->format('t')));
    };
    $c = $make((int) $from->format('Y'));
    return $c->format('Y-m-d') < $from->format('Y-m-d') ? $make((int) $from->format('Y') + 1) : $c;
}

/** Screen-facing status: ['key' => live|upcoming|off|ended, 'label' => ...]. */
function lt_status(array $t, DateTimeImmutable $today, $live) {
    if (empty($t['is_active'])) { return ['key' => 'off', 'label' => 'Off']; }
    if ($live && (int) $live['id'] === (int) $t['id']) { return ['key' => 'live', 'label' => 'Showing now']; }
    if (lt_covers($t, $today)) {
        return ['key' => 'upcoming', 'label' => 'Overlapped by "' . ($live['name'] ?? '') . '"'];
    }
    $day  = $today->setTime(0, 0);
    $next = lt_next_start($t, $day);
    if (!$next) { return ['key' => 'ended', 'label' => 'Ended']; }
    $days = (int) $day->diff($next->setTime(0, 0))->days;
    return ['key' => 'upcoming', 'label' => $days === 1 ? 'Starts tomorrow' : "Starts in {$days} days"];
}

/** Does this session hold the `logintheme` access right (value 2)? */
function lt_can_manage(PDO $pdo) {
    if (empty($_SESSION['id']) || $_SESSION['id'] == '0') { return false; }
    try {
        $st = $pdo->prepare("SELECT logintheme FROM accessrights WHERE EmpID = :id");
        $st->execute([':id' => $_SESSION['id']]);
        return (int) $st->fetchColumn() === 2;
    } catch (Throwable $e) {
        return false; // column missing = SQL file not run yet = no access
    }
}

/** Per-session token for the Login Theme write endpoint. */
function lt_csrf_token() {
    if (empty($_SESSION['lt_csrf'])) { $_SESSION['lt_csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['lt_csrf'];
}

} // function_exists guard
