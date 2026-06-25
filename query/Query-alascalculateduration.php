<?php

include 'w_conn.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['id']) && $_SESSION['id'] != "0") {} else {header('location: login.php');}
try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {die("ERROR: Could not connect. " . $e->getMessage());
}
$id = $_SESSION['id'];
$dteStart = $_POST['dtst'];
$dteEnd = $_POST['dtend'];
$dteStart1 = new DateTime($_POST['dtst']);
$dteEnd1 = new DateTime($_POST['dtend']);
$dteDiff = $dteStart1->diff($dteEnd1);
$ltype = $_POST['ltype']; //2-12-2024

// $numdays = ($dteDiff->format("%D")) + 1;
$numdays = $dteDiff->format("%a") + 1; //9-11

//function get total number of restday between startdate and end date
$num_off = 0;
$num_off1 = 0;
$ctr = 0;
$x = 0;
$rts=0;
$a=[];
// while ($numdays>$ctr)  {
    if($ltype==27){ //2-12-2024
        $newduration = $numdays;
        echo $newduration;
    }else{
        while ($dteStart <= $dteEnd) {
            //30-10
            $day_desc = date("l", strtotime($dteStart));
            $statement = $pdo->prepare("Select * from workdays INNER JOIN
            workschedule ON workdays.SchedTime=workschedule.WorkSchedID
            inner join schedeffectivity as c on workdays.EFID=c.efids
            where (workdays.empid='$id') and (workdays.Day_s='$day_desc') and ('$dteStart' >= dfrom) and ('$dteStart' <= dto)  and (SchedTime = '0')");

            $statement->execute();
            $count = $statement->rowCount();
            if ($count > 0) {
                $num_off1 = $num_off1 + 1;
            }

            // $yr = date("Y", strtotime($_POST['dtst'] . "+" . $ctr . " day"));
            $mtnh = date("Y-m-d", strtotime($dteStart));
            // $dy = date("d", strtotime($_POST['dtst'] . "+" . $ctr . " day"));
            // $statementhol = $pdo->prepare("select * from holidays where month(Hdate)=:mnth and year(Hdate)=:yr and day(Hdate)=:dy");
            $statementhol = $pdo->prepare("select * from holidays where Hdate=:mnth order by sid desc");
            $statementhol->bindParam(':mnth', $mtnh);
            // $statementhol->bindParam(':dy', $dy);
            // $statementhol->bindParam(':yr', $yr);
            $statementhol->execute();
            $counthol = $statementhol->rowCount();
            if ($counthol > 0 && $count==0) {
                $num_off =( $num_off + 1 );
                // if ($num_off > 0){
                //     $num_off=$num_off-1;
                // }
                $rts = $dteStart;
            }

            $ctr = $ctr + 1;
            $dteStart = date("Y-m-d", strtotime($dteStart . " + 1 days"));
        }
        $newduration = $numdays - ($num_off + $num_off1) ;

        echo $newduration;
    }    
?>    
