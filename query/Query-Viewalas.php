<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['id']) && $_SESSION['id'] != "0") {} else {header('location: login.php');}

try {
    include 'w_conn.php';
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}
$id = $_SESSION['id'];
$dt1 = date('Y-m-d', strtotime(date("Y-m-d") . ' - 15 days'));
$dt2 = date("Y-m-d");
$statement = $pdo->prepare("SELECT a.LStatus as LST,a.FID as IDL,LeaveDesc,a.LFDate as FD,a.LStart as LS,a.LEnd as LE,a.LDuration as dur,a.LPurpose as LP,StatusDesc from hleavesbd as a
								                                INNER JOIN hleaves on a.FID=hleaves.LeaveID
															  	INNER JOIN status as b on a.LStatus=b.StatusID
															  	INNER JOIN leaves_validation c ON c.sid=a.LType
															  	INNER JOIN leaves as d on c.lid=d.LeaveID
															  	where a.EmpID=:id and a.LStatus<>7 and a.LEnd BETWEEN :dt1 AND :dt2 order by a.LStart desc");

//  $statement = $pdo->prepare("SELECT a.LStatus as LST,a.FID as IDL,LeaveDesc,a.LFDate as FD,a.LStart as LS,a.LEnd as LE,a.LDuration as dur,a.LPurpose as LP,StatusDesc   from hleavesbd as a
//                                     -- INNER JOIN hleaves on a.FID=hleaves.LeaveID
//                                   INNER JOIN status as b on a.LStatus=b.StatusID
//                                   -- INNER JOIN leaves_validation c ON c.sid=a.LType
//                                   INNER JOIN leaves as d on a.LType=d.LeaveID where a.EmpID=:id and a.LStart BETWEEN '" . $_GET['dfrom'] . "' AND '" . $_GET['dto'] ."' and a.LStatus<>7 order by a.LStart desc ");
//                                   $statement->bindParam(':id' , $id);
//                                   $statement->execute();
$statement->bindParam(':id', $id);
$statement->bindParam(':dt1', $dt1);
$statement->bindParam(':dt2', $dt2);
$statement->execute();
while ($row21 = $statement->fetch()) {
    ?>

<tr>
    <td><?php echo $row21['LeaveDesc']; ?></td>
    <td><?php echo date("F j, Y", strtotime($row21['FD'])); ?></td>
    <td><?php echo date("F j, Y", strtotime($row21['LS'])); ?></td>
    <td><?php echo date("F j, Y", strtotime($row21['LE'])); ?></td>
    <td>1</td>
    <td><?php echo $row21['LP']; ?></td>
    <td><?php echo $row21['StatusDesc']; ?></td>
    <?php
if ($row21['LST'] == 1) {
        ?>
    <td><button type="button" class="btn btn-danger" data-toggle="modal"
            data-target="#myModalob<?php echo $row21['IDL']; ?>"><i class="fa fa-trash" aria-hidden="true"></i></button>
    </td>
    <?php
} else {

    }
    ?>
</tr>

<!-- The Modal -->
<div class="modal ob-viewdel" id="myModalob<?php echo $row21['IDL']; ?>">
    <div class="modal-dialog">
        <div class="modal-content">

            <!-- Modal Header -->
            <div class="modal-header">
                <h4 class="modal-title">Are you sure you want to remove this ??</h4>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>

            <!-- Modal body -->
            <div class="modal-body">
                <button type="button" id="<?php echo $row21['IDL']; ?>" class="btn btn-success ys_leave">Yes</button>
                <button type="button" class="btn btn-danger" data-dismiss="modal">No</button>
            </div>

            <!-- Modal footer -->


        </div>
    </div>
</div>
<?php
}

?>