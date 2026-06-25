<?php
include 'w_conn.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['id']) && $_SESSION['id'] != "0") {} else {header('location: login.php');}

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

date_default_timezone_set("Asia/Manila");
$tdy = date("Y-m-d H:i:s");
$todaydt = date("Y-m-d H:i:s");

if ($_SESSION['UserType'] == 1) {
   
    if (isset($_GET['validate'])) {
        try {
            $sql = "SELECT * FROM hleaves as a where a.LeaveID=" . $_POST['idOf'] . " order by LStart";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $rowh = $stmt->fetch();
            $rowhcount = $stmt->rowCount();
            
            echo $_POST['idOf'];
            // $leaveType = 0;
            // $creditEarned = 0;
            // $lduration = 0;
            // $creditloop = 0;
            // $earnedCredit = 0;

            // if ($rowhcount == 1){
            //     $leaveType = $rowh['LType'];
            //     $EmplID = $rowh['EmpID'];
            //     $datestart = $rowh['LStart'];
            //     $dateend = $rowh['LEnd'];
            //     $lduration = $rowh['LDuration'];
            //     $date1 = date_create($dateend);
            //     $date2 = date_create($datestart);
            //     $diff = date_diff($date1, $date2);
            //     $DayDur = $diff->format("%a");
 
            //     if ($leaveType == 35){
            //         $sql = "select * from credit where EmpID = :id";
            //         $stmt = $pdo->prepare($sql);
            //         $stmt->bindParam(':id', $EmplID);
            //         $stmt->execute();
            //         $crdetail = $stmt->fetch();
            //         $crcnt = $stmt->rowCount();

            //         if ($crcnt > 0) {
            //             $crh = $crdetail['CTH'];
            //             $crth = $crdetail['CT'];
            //             $tdy = date("Y");

            //             $tdy1 = date("Y", strtotime(date("Y") . "+1 years"));
            //             $date1 = date_create("1/1/" . $tdy);
            //             $date2 = date_create("1/1/" . $tdy1);
            //             $diff = date_diff($date1, $date2);
            //             //output data
            //             $noOfDays = $diff->format("%a") / 12;
            //             //credit per month earning
            //             $cdPerMonth = $crh / 12;
            //             //credit per day earning
            //             $cdPerDay = $cdPerMonth / $noOfDays;
            //             //get no of days from jan to present
            //             $todaydate = date("Y");
            //             $todaydate1 = date("m/d/Y");
            //             $gnOfdJan = date_create("1/1/" . $todaydate);
            //             //   $gnOfdCur=date_create($dateend);
            //             $gnOfdCur = date_create($todaydate1);
            //             $diff2 = date_diff($gnOfdJan, $gnOfdCur);

            //             //output data
            //             $gnOfdJanCur = $diff2->format("%a");

            //             //get use credits and subtract to total earned credits ramon
            //             $useCredit = $crh - $crth;

            //             //get total earned creidit
            //             $creditEarned = ($cdPerDay * $gnOfdJanCur) - $useCredit;

            //             $earnedCredit = floor($creditEarned);
            //         } 

            //         if (floor($creditEarned) == 0 or floor($creditEarned) < 1) {
            //             try {
            //                 $reas = " No Credits";
            //                 $sql = "UPDATE hleaves SET LStatus=6,LDateTimeUpdated=:dtu,LHRReason=:rsn where LeaveID=:idd";
            //                 $stmt = $pdo->prepare($sql);
            //                 $stmt->bindParam(':dtu', $todaydt);
            //                 $stmt->bindParam(':rsn', $reas);
            //                 $stmt->bindParam(':idd', $_GET['id']);
            //                 $stmt->execute();

            //                 $sql = "UPDATE hleavesbd SET LStatus=6,LDateTimeUpdated=:dtu,LHRReason=:rsn where FID=:idd";
            //                 $stmt = $pdo->prepare($sql);
            //                 $stmt->bindParam(':dtu', $todaydt);
            //                 $stmt->bindParam(':rsn', $reas);
            //                 $stmt->bindParam(':idd', $_GET['id']);
            //                 $stmt->execute();

            //                 $id = $_SESSION['id'];
            //                 $ch = "Disapproved Leaves of " . $nameE;
            //                 // insert into dars
            //                 $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
            //                 $stmt = $pdo->prepare($sql);
            //                 $stmt->bindParam(':id', $id);
            //                 $stmt->bindParam(':empact', $ch);
            //                 $stmt->bindParam(':ddt', $todaydt);
            //                 $stmt->execute();
            //             } catch (Exception $e) {}
            //         }
            //     } 
            // } 
        } catch (Exception $e) {
            echo 'Caught exception: ', $e->getMessage(), "\n";
        }
    }
}    