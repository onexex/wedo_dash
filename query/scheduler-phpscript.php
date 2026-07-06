<?php
include 'w_conn.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['id']) && $_SESSION['id'] != "0") { /* ok */ }
else { header('location: login.php'); exit; }
date_default_timezone_set("Asia/Manila");
$today = date("Y-m-d H:i:s");

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

/* ------------------------------------------------------------------ *
 *  Update an effectivity period's dates
 * ------------------------------------------------------------------ */
if (isset($_GET['updateEffectivity'])) {
    $id   = $_POST['id'];
    $from = $_POST['dfrom'];
    $to   = $_POST['dto'];

    $stmt = $pdo->prepare("UPDATE schedeffectivity SET dfrom=:dfrom, dto=:dto WHERE efids=:id");
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':dfrom', $from);
    $stmt->bindParam(':dto', $to);

    echo json_encode(["errcode" => $stmt->execute() ? 0 : 1]);
    exit;
}

/* ------------------------------------------------------------------ *
 *  Fetch one effectivity period (prefill the update-effectivity modal)
 * ------------------------------------------------------------------ */
if (isset($_GET['setEffectivity'])) {
    $id = $_POST['id'];
    $stmt = $pdo->prepare("SELECT * FROM schedeffectivity WHERE efids=:id");
    $stmt->bindParam(':id', $id);

    if ($stmt->execute()) {
        $empdata = [];
        while ($getrow = $stmt->fetch()) { $empdata[] = $getrow; }
        echo json_encode(["errcode" => 0, "data" => $empdata]);
    } else {
        echo json_encode(["errcode" => 1]);
    }
    exit;
}

/* ------------------------------------------------------------------ *
 *  Update a single day's shift + log the change to dars
 * ------------------------------------------------------------------ */
if (isset($_GET['updateTimeNow'])) {
    $id     = $_POST['id'];       // WID
    $timeid = $_POST['timeid'];   // WorkSchedID

    $stmt = $pdo->prepare("UPDATE workdays SET SchedTime=:schedid WHERE WID=:id");
    $stmt->bindParam(':id', $id);
    $stmt->bindParam(':schedid', $timeid);

    if ($stmt->execute()) {
        $actor = $_SESSION['id'];
        $ch    = "Updated Schedule to " . $_POST['me'] . " of employeeid " . $actor;
        $log = $pdo->prepare("INSERT INTO dars (EmpID, EmpActivity, DarDateTime) VALUES (:id, :empact, :ddt)");
        $log->bindParam(':id', $actor);
        $log->bindParam(':empact', $ch);
        $log->bindParam(':ddt', $today);
        $log->execute();
        echo json_encode(["errcode" => 0]);
    } else {
        echo json_encode(["errcode" => 1]);
    }
    exit;
}

/* ------------------------------------------------------------------ *
 *  Load the shift options for the update-time modal
 * ------------------------------------------------------------------ */
if (isset($_GET['setupdatesched'])) {
    $stmt = $pdo->prepare("SELECT * FROM workschedule");
    $stmt->execute();
    $count = $stmt->rowCount();
    $empdata = [];
    while ($getrow = $stmt->fetch()) { $empdata[] = $getrow; }

    if ($count > 0) {
        echo json_encode(["data" => $empdata, "errcode" => 0]);
    } else {
        echo json_encode(["errcode" => 1]);
    }
    exit;
}

/* ------------------------------------------------------------------ *
 *  View one schedule's per-day breakdown
 * ------------------------------------------------------------------ */
if (isset($_GET['viewsched'])) {
    $query = $_POST['id']; // EFID
    $stmt = $pdo->prepare("SELECT * FROM workdays AS a
                           LEFT JOIN workschedule AS b ON a.SchedTime = b.WorkSchedID
                           WHERE a.EFID = :efid
                           ORDER BY a.WID DESC");
    $stmt->bindParam(':efid', $query);
    $stmt->execute();
    $count = $stmt->rowCount();
    $empdata = [];
    while ($getrow = $stmt->fetch()) { $empdata[] = $getrow; }

    if ($count > 0) {
        echo json_encode(["data" => $empdata, "errcode" => 0]);
    } else {
        echo json_encode(["errcode" => 1]);
    }
    exit;
}

/* ------------------------------------------------------------------ *
 *  Filter + list existing schedules
 *  Optional: last name (LIKE), effectivity date range (overlap), and a
 *  whitelisted row limit (10 / 50 / 100 / 500). A schedule matches the
 *  range when its effectivity period overlaps [datefrom, dateto].
 * ------------------------------------------------------------------ */
if (isset($_GET['filter'])) {
    $name     = isset($_POST['name'])     ? trim($_POST['name'])     : '';
    $datefrom = isset($_POST['datefrom']) ? trim($_POST['datefrom']) : '';
    $dateto   = isset($_POST['dateto'])   ? trim($_POST['dateto'])   : '';
    $limit    = isset($_POST['limit'])    ? (int) $_POST['limit']    : 10;
    if (!in_array($limit, [10, 50, 100, 500], true)) { $limit = 10; }  // whitelist → safe to inline

    $where  = [];
    $params = [];
    if ($name !== '') {
        $where[] = "c.EmpLN LIKE :name";
        $params[':name'] = "%" . $name . "%";
    }
    if ($datefrom !== '') {
        // schedule does not end before the window starts (open-ended dto counts)
        $where[] = "(a.dto >= :df OR a.dto IS NULL OR a.dto = '')";
        $params[':df'] = $datefrom;
    }
    if ($dateto !== '') {
        // schedule starts on or before the window ends
        $where[] = "a.dfrom <= :dt";
        $params[':dt'] = $dateto;
    }
    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $pdo->prepare("SELECT * FROM schedeffectivity AS a
                           INNER JOIN workdays AS b ON a.efids = b.EFID
                           INNER JOIN employees AS c ON b.empid = c.EmpID
                           $whereSql
                           GROUP BY b.EFID
                           ORDER BY a.efids DESC
                           LIMIT $limit");
    $stmt->execute($params);
    $empdata = $stmt->fetchAll();

    echo json_encode([
        "errcode" => count($empdata) > 0 ? 0 : 1,
        "data"    => $empdata,
        "count"   => count($empdata),
    ]);
    exit;
}

/* ------------------------------------------------------------------ *
 *  Load employees + shift options for the create modal
 * ------------------------------------------------------------------ */
if (isset($_GET['loaddata'])) {
    $getemployee = $pdo->prepare("SELECT * FROM employees WHERE EmpStatusID='1' ORDER BY EmpLN");
    $getemployee->execute();
    $count = $getemployee->rowCount();
    $empdata = [];
    while ($getrow = $getemployee->fetch()) { $empdata[] = $getrow; }

    $getSched = $pdo->prepare("SELECT * FROM workschedule");
    $getSched->execute();
    $schedData = [];
    while ($rowsched = $getSched->fetch()) { $schedData[] = $rowsched; }

    if ($count > 0) {
        echo json_encode(["data" => $empdata, "errcode" => 0, "data2" => $schedData]);
    } else {
        echo json_encode(["errcode" => 1]);
    }
    exit;
}

/* ------------------------------------------------------------------ *
 *  Save a weekly schedule for one OR many employees.
 *  Each employee gets their own effectivity period + 7 workdays rows,
 *  so every downstream read/update/view keeps working per-employee.
 * ------------------------------------------------------------------ */
if (isset($_GET['save'])) {
    $employees = isset($_POST['employee']) ? $_POST['employee'] : [];
    if (!is_array($employees)) { $employees = [$employees]; }
    $dfrom = isset($_POST['dfrom']) ? $_POST['dfrom'] : '';
    $dto   = isset($_POST['dto'])   ? $_POST['dto']   : '';

    // day => chosen WorkSchedID (0 = rest day). Inserted Sunday-first so that a
    // "WID DESC" read renders Monday..Sunday, matching the original behaviour.
    $days = [
        'Sunday'    => isset($_POST['sunday'])    ? $_POST['sunday']    : '0',
        'Saturday'  => isset($_POST['saturday'])  ? $_POST['saturday']  : '0',
        'Friday'    => isset($_POST['friday'])    ? $_POST['friday']    : '0',
        'Thursday'  => isset($_POST['thursday'])  ? $_POST['thursday']  : '0',
        'Wednesday' => isset($_POST['wednesday']) ? $_POST['wednesday'] : '0',
        'Tuesday'   => isset($_POST['tuesday'])   ? $_POST['tuesday']   : '0',
        'Monday'    => isset($_POST['monday'])    ? $_POST['monday']    : '0',
    ];

    if (empty($employees) || $dfrom === '' || $dto === '') {
        echo json_encode(["errcode" => 1, "message" => "Please choose employee(s) and effectivity dates."]);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $effStmt = $pdo->prepare("INSERT INTO schedeffectivity (dfrom, dto) VALUES (:dfrom, :dto)");
        $wdStmt  = $pdo->prepare("INSERT INTO workdays (empid, Day_s, SchedTime, EFID) VALUES (:id, :day_s, :schedtime, :efid)");

        $count = 0;
        foreach ($employees as $emp) {
            $emp = trim($emp);
            if ($emp === '' || $emp === '0') { continue; }   // skip the "Choose.." sentinel

            $effStmt->execute([':dfrom' => $dfrom, ':dto' => $dto]);
            $efid = $pdo->lastInsertId();

            foreach ($days as $dayName => $schedTime) {
                $wdStmt->execute([
                    ':id'        => $emp,
                    ':day_s'     => $dayName,
                    ':schedtime' => $schedTime,
                    ':efid'      => $efid,
                ]);
            }
            $count++;
        }
        $pdo->commit();

        echo json_encode(["errcode" => 0, "count" => $count]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        echo json_encode(["errcode" => 1, "message" => "Save failed: " . $e->getMessage()]);
    }
    exit;
}
?>
