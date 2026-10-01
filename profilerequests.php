<?php
    /* Profile change requests — review queue for users with "Update 201 Files".
       Employees submit these from UpdateEmployeeInfo.php (request mode). */
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login.php'); exit; }
    include 'w_conn.php';
    require_once __DIR__ . '/includes/profile-change.php';

    $pdo = new PDO("mysql:host=$servername;dbname=$db;charset=utf8mb4", $username, $password,
                   [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $allowed = pcr_can_update($pdo, (string)$_SESSION['id']);

    function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
    function show($v) { return trim((string)$v) === '' ? '<span class="pr-empty">(blank)</span>' : h($v); }

    $pending = $history = [];
    $setupMissing = false;
    if ($allowed) {
        $names = "TRIM(CONCAT(e.EmpFN, ' ', e.EmpLN))";
        try {
            $pending = $pdo->query("SELECT r.*, $names AS EmpName, p.PositionDesc
                                      FROM profile_change_requests r
                                      LEFT JOIN employees e ON e.EmpID = r.EmpID
                                      LEFT JOIN positions p ON p.PSID = e.PosID
                                     WHERE r.Status = 'pending' ORDER BY r.CreatedAt")->fetchAll(PDO::FETCH_ASSOC);
            $history = $pdo->query("SELECT r.*, $names AS EmpName, TRIM(CONCAT(v.EmpFN, ' ', v.EmpLN)) AS ReviewerName
                                      FROM profile_change_requests r
                                      LEFT JOIN employees e ON e.EmpID = r.EmpID
                                      LEFT JOIN employees v ON v.EmpID = r.ReviewedBy
                                     WHERE r.Status IN ('approved', 'rejected') ORDER BY r.ReviewedAt DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $setupMissing = true;
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Profile Change Requests</title>
    <link rel="icon" href="assets/images/logos/WeDo.png" type="image/x-icon">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>
    <link rel="stylesheet" href="assets/css/wedo-theme.css">
    <link rel="stylesheet" href="assets/css/employee-form.css?v=<?php echo filemtime(__DIR__ . '/assets/css/employee-form.css'); ?>">
    <script src="assets/js/script.js"></script>
    <style>
        .pr-list { display: flex; flex-direction: column; gap: 16px; }
        .pr-card__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
        .pr-card__who b { font-family: var(--font-head); font-size: 16px; color: var(--text); }
        .pr-card__who span { display: block; color: var(--text-2); font-size: 12.5px; }
        .pr-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .pr-table th { text-align: left; padding: 9px 12px; background: var(--surface-2); color: var(--text-3); font-family: var(--font-head);
            font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
        .pr-table td { padding: 9px 12px; border-top: 1px solid var(--border); vertical-align: top; color: var(--text-2); }
        .pr-table td:first-child { font-weight: 600; color: var(--text); white-space: nowrap; }
        .pr-table td.pr-new { color: var(--text); font-weight: 600; background: rgba(28,122,68,.06); }
        .pr-section td { background: var(--surface); color: var(--text-3) !important; font-size: 11px; font-weight: 700 !important;
            text-transform: uppercase; letter-spacing: .05em; padding-top: 14px !important; }
        .pr-stale { display: block; font-size: 11px; color: var(--warn-text); font-weight: 600; margin-top: 2px; }
        .pr-empty { color: var(--text-3); font-style: italic; font-weight: 400; }
        .pr-fam { margin: 0; padding-left: 16px; }
        .pr-actions { display: flex; gap: 8px; justify-content: flex-end; align-items: flex-start; margin-top: 14px; flex-wrap: wrap; }
        .pr-actions textarea { flex: 1 1 280px; min-height: 38px; border: 1px solid var(--border-2); border-radius: var(--radius);
            padding: 8px 10px; font-size: 13px; font-family: var(--font-body); resize: vertical; }
        .pr-empty-state { text-align: center; padding: 40px 20px; color: var(--text-2); }
        .pr-empty-state i { font-size: 30px; color: var(--ok-text); margin-bottom: 8px; }
        .pr-hist td { white-space: nowrap; }
        .pr-hist td:last-child { white-space: normal; }
    </style>
</head>
<body>
    <?php $wd_active = 'profilerequests'; include 'includes/wd-header.php'; ?>

    <div class="wd-pagehead">
        <div>
            <h1>Profile Change Requests</h1>
            <p>Employees' requested updates to their own 201 information. Approving writes the changes to their record.</p>
        </div>
    </div>

<?php if (!$allowed) { ?>
    <div class="ne-card ue-missing">
        <i class="fa-solid fa-lock"></i>
        <h3>Not available</h3>
        <p>Reviewing profile changes needs the <b>Update 201 Files</b> access right.</p>
    </div>
<?php } elseif ($setupMissing) { ?>
    <div class="ue-notice ue-notice--danger"><i class="fa-solid fa-triangle-exclamation"></i>
        <div>Change requests are not set up on this server yet. Run <code>sql/2026-10-01-add-profile-change-requests.sql</code>.</div></div>
<?php } else { ?>

    <div class="pr-list" id="prList">
    <?php if (!$pending) { ?>
        <div class="ne-card pr-empty-state"><i class="fa-solid fa-circle-check"></i><h3>All caught up</h3><p>No requests are waiting for review.</p></div>
    <?php } ?>
    <?php foreach ($pending as $r) {
        $changes = json_decode($r['Changes'], true) ?: [];
        $live    = pcr_current($pdo, $r['EmpID']) ?? [];
        $bySection = [];
        foreach ($changes['fields'] ?? [] as $c) { $bySection[$c['section']][] = $c; }
    ?>
        <div class="ne-card pr-card" data-id="<?php echo (int)$r['id']; ?>">
            <div class="pr-card__head">
                <div class="pr-card__who">
                    <b><?php echo h($r['EmpName'] ?: $r['EmpID']); ?></b>
                    <span><?php echo h($r['EmpID']); ?><?php if ($r['PositionDesc']) { echo ' · ' . h($r['PositionDesc']); } ?>
                        · submitted <?php echo h(date('M j, Y g:i A', strtotime($r['CreatedAt']))); ?></span>
                </div>
                <a class="wd-btn wd-btn--ghost wd-btn--sm" href="UpdateEmployeeInfo?sid=<?php echo urlencode($r['EmpID']); ?>"><i class="fa-solid fa-pen-to-square"></i> Open record</a>
            </div>

            <table class="pr-table">
                <thead><tr><th>Field</th><th>Current</th><th>Requested</th></tr></thead>
                <tbody>
                <?php foreach (PCR_SECTIONS as $sec => $secLabel) {
                    if ($sec === 'family' || empty($bySection[$sec])) { continue; } ?>
                    <tr class="pr-section"><td colspan="3"><?php echo h($secLabel); ?></td></tr>
                    <?php foreach ($bySection[$sec] as $c) {
                        $now = $live[$c['field']] ?? ''; ?>
                        <tr>
                            <td><?php echo h($c['label']); ?></td>
                            <td><?php echo show($now); ?>
                                <?php if ($now !== $c['old']) { ?><span class="pr-stale">changed since the request was made</span><?php } ?></td>
                            <td class="pr-new"><?php echo show($c['new']); ?></td>
                        </tr>
                    <?php } ?>
                <?php } ?>
                <?php if (!empty($changes['family'])) {
                    $fam = fn($list) => $list ? '<ul class="pr-fam">' . implode('', array_map(fn($f) =>
                        '<li>' . h($f['name']) . ' — ' . h($f['relationship']) . ($f['contact'] !== '' ? ', ' . h($f['contact']) : '')
                        . ($f['ice'] === 'Yes' ? ' <b>(ICE)</b>' : '') . '</li>', $list)) . '</ul>' : '<span class="pr-empty">(none)</span>'; ?>
                    <tr class="pr-section"><td colspan="3">Family details</td></tr>
                    <tr>
                        <td>Family / ICE list</td>
                        <td><?php echo $fam(pcr_family_from_post(pcr_family($pdo, $r['EmpID']))); ?></td>
                        <td class="pr-new"><?php echo $fam($changes['family']['new'] ?? []); ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>

            <div class="pr-actions">
                <textarea class="pr-remarks" placeholder="Remarks (required when rejecting)"></textarea>
                <button type="button" class="wd-btn wd-btn--ghost pr-act" data-action="reject"><i class="fa-solid fa-xmark"></i> Reject</button>
                <button type="button" class="wd-btn wd-btn--primary pr-act" data-action="approve"><i class="fa-solid fa-check"></i> Approve</button>
            </div>
        </div>
    <?php } ?>
    </div>

    <?php if ($history) { ?>
        <div class="ne-card" style="margin-top:20px">
            <h3 class="ne-card__title">Recently reviewed</h3>
            <p class="ne-card__sub">The last 30 decisions.</p>
            <div style="overflow-x:auto">
            <table class="pr-table pr-hist">
                <thead><tr><th>Employee</th><th>Decision</th><th>By</th><th>When</th><th>Remarks</th></tr></thead>
                <tbody>
                <?php foreach ($history as $r) { ?>
                    <tr>
                        <td><?php echo h($r['EmpName'] ?: $r['EmpID']); ?></td>
                        <td><span class="wd-pill wd-pill--<?php echo $r['Status'] === 'approved' ? 'ok' : 'danger'; ?>"><?php echo h(ucfirst($r['Status'])); ?></span></td>
                        <td><?php echo h($r['ReviewerName'] ?: $r['ReviewedBy']); ?></td>
                        <td><?php echo h(date('M j, Y g:i A', strtotime($r['ReviewedAt']))); ?></td>
                        <td><?php echo $r['Remarks'] !== null ? h($r['Remarks']) : '<span class="pr-empty">—</span>'; ?></td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
            </div>
        </div>
    <?php } ?>

    <script>
        $(document).on('click', '.pr-act', function () {
            var $card = $(this).closest('.pr-card'), action = $(this).data('action');
            var remarks = $.trim($card.find('.pr-remarks').val());
            if (action === 'reject' && remarks === '') {
                swal("Reason needed", "Please write why the request is rejected so the employee knows what to fix.", "warning");
                $card.find('.pr-remarks').focus();
                return;
            }
            $card.find('.pr-act').prop('disabled', true);
            $.ajax({ url: 'query/Query-reviewProfileChange.php', type: 'POST', dataType: 'json',
                     data: { id: $card.data('id'), action: action, remarks: remarks } })
              .done(function () {
                  swal(action === 'approve' ? "Approved" : "Rejected",
                       action === 'approve' ? "The employee's record was updated." : "The employee will see your remarks.", "success")
                    .then(function () { location.reload(); });
              })
              .fail(function (xhr) {
                  $card.find('.pr-act').prop('disabled', false);
                  swal("Not saved", (xhr.responseJSON && xhr.responseJSON.message) || "Please try again.", "error");
              });
        });
    </script>
<?php } ?>

    <?php include 'includes/wd-footer.php'; ?>
</body>
</html>
