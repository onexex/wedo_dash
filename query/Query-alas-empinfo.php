<?php
    // Returns the form details for an employee selected in ALAS (file-on-behalf).
    // Authorization: the requester may only fetch their OWN details or those of a
    // direct report (an employee whose immediate superior EmpISID is the requester).
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    header('Content-Type: application/json');

    if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
        echo json_encode(["ok" => false, "msg" => "Not authenticated."]);
        return;
    }

    include 'w_conn.php';
    try {
        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo json_encode(["ok" => false, "msg" => "Could not connect."]);
        return;
    }

    $loggedInId = $_SESSION['id'];
    $empid      = isset($_POST['empid']) ? $_POST['empid'] : (isset($_GET['empid']) ? $_GET['empid'] : '');

    if ($empid === '') {
        echo json_encode(["ok" => false, "msg" => "No employee selected."]);
        return;
    }

    // Authorize: self OR immediate superior of the target.
    if ($empid !== $loggedInId) {
        $auth = $pdo->prepare("SELECT EmpISID FROM empdetails WHERE EmpID = :tid");
        $auth->execute([':tid' => $empid]);
        $authRow = $auth->fetch();
        if (!$authRow || $authRow['EmpISID'] !== $loggedInId) {
            echo json_encode(["ok" => false, "msg" => "You are not authorized to file for this employee."]);
            return;
        }
    }

    // Personnel / company / department / designation
    $statement = $pdo->prepare("SELECT employees.EmpLN, employees.EmpFN, employees.EmpMN,
        companies.CompanyDesc, departments.DepartmentDesc, positions.PositionDesc
        FROM employees
        INNER JOIN empdetails ON employees.EmpID=empdetails.EmpID
        INNER JOIN companies ON empdetails.EmpCompID=companies.CompanyID
        INNER JOIN departments ON empdetails.EmpdepID=departments.DepartmentID
        INNER JOIN positions ON positions.PSID=employees.PosID
        WHERE employees.EmpID = :id");
    $statement->bindParam(':id', $empid);
    $statement->execute();
    $row = $statement->fetch();

    if (!$row) {
        echo json_encode(["ok" => false, "msg" => "Employee record not found."]);
        return;
    }

    // Gender (drives which leave types are offered)
    $gstmt = $pdo->prepare("SELECT EmpGender FROM empprofiles WHERE EmpID = :id");
    $gstmt->bindParam(':id', $empid);
    $gstmt->execute();
    $grow   = $gstmt->fetch();
    $gender = $grow ? $grow['EmpGender'] : '';

    // Credit
    $cstmt = $pdo->prepare("SELECT * FROM credit WHERE EmpID = :id");
    $cstmt->bindParam(':id', $empid);
    $cstmt->execute();
    $crow      = $cstmt->fetch();
    $hasCredit = $cstmt->rowCount() > 0;
    $creditVal = $hasCredit ? number_format((float) ($crow['CT'] ?? 0), 2, '.', '') : "0.00";

    // Leave-type options (mirrors alas.php logic)
    $leaveOptions = '';
    if ($hasCredit) {
        if ($gender == "Male") {
            $ids = ['29', '33', '30', '22'];
        } else if ($gender == "Female") {
            $ids = ['27', '33', '30', '22'];
        } else {
            $ids = ['29', '27', '36', '37', '33', '30', '22'];
        }
        $in    = implode(',', array_map('intval', $ids));
        $lstmt = $pdo->query("SELECT * FROM leaves WHERE LeaveID IN ($in) ORDER BY LeaveDesc ASC");
        while ($res = $lstmt->fetch()) {
            if ($empid != "WeDoinc-003" && $res['LeaveID'] == 34) {
                continue;
            }
            $leaveOptions .= '<option value="' . htmlspecialchars($res['LeaveID']) . '">'
                          . htmlspecialchars($res['LeaveDesc']) . '</option>';
        }
    } else {
        $leaveOptions = '<option value="33">Unpaid</option>';
    }

    echo json_encode([
        "ok"           => true,
        "name"         => $row['EmpLN'] . ' ' . $row['EmpFN'] . ' ' . $row['EmpMN'],
        "company"      => $row['CompanyDesc'],
        "department"   => $row['DepartmentDesc'],
        "designation"  => $row['PositionDesc'],
        "credit"       => $creditVal,
        "leaveOptions" => $leaveOptions,
    ]);
?>
