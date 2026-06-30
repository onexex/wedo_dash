<?php
/* =============================================================================
 * Query-LiloView.php — AJAX endpoint for the Attendance Viewer (liloviewer.php).
 * Returns a <thead>+<tbody> fragment that #viewlilo drops into #tab.
 *
 * Access to this endpoint is already gated by the module access right (lilov),
 * so anyone who can reach it may view ALL employees:
 *   - a specific employee chosen  -> just that employee
 *   - "All" + role 1 (super user) -> everyone, all companies
 *   - "All" + any other role      -> all employees in the user's company
 *
 * (Previously regular users fell through to `WHERE EmpID='all'` and saw nothing;
 *  before that, role 3 was wrongly limited to their own/supervised logs.)
 * ========================================================================== */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { header('location: login'); exit; }
date_default_timezone_set("Asia/Manila");

include 'w_conn.php';
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

$eid    = isset($_GET['eid'])   ? $_GET['eid']   : 'all';
$dfrom  = isset($_GET['dfrom']) ? $_GET['dfrom'] : date('Y-m-01');
$dto    = isset($_GET['dto'])   ? $_GET['dto']   : date('Y-m-d');
$dateTo = date('Y-m-d', strtotime($dto . ' +1 day'));   // make the "To" day inclusive
$uid    = $_SESSION['id'];
$role   = $_SESSION['UserType'];
$comp   = isset($_SESSION['CompID']) ? $_SESSION['CompID'] : '';

$cols = " SELECT employees.EmpID as Employee_ID, employees.EmpLN as LastName, employees.EmpFN as FirstName,
            employees.EmpMN as MiddleName, attendancelog.Timein as Timein, attendancelog.TimeOut as Timeout,
            attendancelog.durationtime as BillHours
          FROM employees
          INNER JOIN attendancelog ON attendancelog.EmpID = employees.EmpID
          INNER JOIN empdetails    ON employees.EmpID    = empdetails.EmpID
          WHERE attendancelog.Timein BETWEEN :dfrom AND :dto ";

$params = [':dfrom' => $dfrom, ':dto' => $dateTo];

if ($eid !== 'all') {
    $sql = $cols . " AND employees.EmpID = :eid ";
    $params[':eid'] = $eid;
} elseif ($role == 1) {
    $sql = $cols;                                   // super user: every company
} else {
    $sql = $cols . " AND empdetails.EmpCompID = :comp ";   // all employees in the user's company
    $params[':comp'] = $comp;
}
$sql .= " ORDER BY LastName, attendancelog.Timein DESC ";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Exception $e) {
    $rows = [];
}
?>
            <thead>
                <tr>
                    <th width="5%">No</th>
                    <th width="30%">Name</th>
                    <th width="25%">Time In</th>
                    <th width="25%">Time Out</th>
                    <th width="15%">Duration</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($rows)): $i = 0; foreach ($rows as $r): $i++; ?>
                <tr>
                    <td><?php echo $i; ?></td>
                    <td><?php echo htmlspecialchars($r["LastName"] . ', ' . $r["FirstName"] . ' ' . $r["MiddleName"]); ?></td>
                    <td><?php echo date("F j, Y h:i:s A", strtotime($r["Timein"])); ?></td>
                    <td><?php echo $r["Timeout"] ? date("F j, Y h:i:s A", strtotime($r["Timeout"])) : ''; ?></td>
                    <td><?php echo number_format((float) $r["BillHours"], 2); ?></td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="5" style="text-align:center;color:#909aab;padding:26px">No attendance records for this range.</td></tr>
                <?php endif; ?>
            </tbody>
