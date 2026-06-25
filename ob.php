<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
?>
<?php
    include 'w_conn.php';
      date_default_timezone_set("Asia/Manila");

    /* Map a status description to a themed pill colour. */
    if (!function_exists('wd_status_pill')) {
        function wd_status_pill($desc) {
            $d = strtolower((string) $desc);
            if (strpos($d, 'approve') !== false)                                     { $c = 'ok'; }
            elseif (strpos($d, 'reject') !== false || strpos($d, 'cancel') !== false
                 || strpos($d, 'disapprove') !== false || strpos($d, 'deny') !== false
                 || strpos($d, 'decline') !== false)                                 { $c = 'danger'; }
            elseif (strpos($d, 'pending') !== false || strpos($d, 'file') !== false
                 || strpos($d, 'process') !== false || strpos($d, 'review') !== false){ $c = 'warn'; }
            else                                                                     { $c = 'info'; }
            return '<span class="wd-pill wd-pill--' . $c . '">' . htmlspecialchars($desc) . '</span>';
        }
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Official Business Trip Tracker</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Functional libs (modals + existing module JS) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script src="assets/js/script-reports.js"></script>
    <script type="text/javascript" src="assets/js/script-modules.js"></script>
    <script type="text/javascript" src="assets/js/administrative.js"></script>
</head>
<body>
    <?php $wd_active = 'ob'; include 'includes/wd-header.php'; ?>

    <div class="wd-pagehead">
        <div>
            <h1>Official Business Trip Tracker</h1>
            <p>File an official business trip and review your recent filings.</p>
        </div>
        <?php if ($_SESSION['id']=="admin"): ?>
        <button type="button" class="wd-btn wd-btn--primary" id="eventListener" data-toggle="modal" data-target="#newform"><i class="fa-solid fa-plus"></i> Official Business Trip Form</button>
        <?php endif; ?>
    </div>

    <section class="wd-card">
        <div class="wd-card__head">
            <h3>OB history</h3>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <input type="date" class="wd-input" id="dtp1" name="d1" style="width:auto;padding:7px 10px"
                    value="<?php echo date('Y-m-d', strtotime(date("Y-m-d") . ' - 15 days')); ?>">
                <span style="color:var(--text-3);font-size:12px">to</span>
                <input type="date" class="wd-input" id="dtp2" name="d2" style="width:auto;padding:7px 10px"
                    value="<?php echo date("Y-m-d"); ?>">
                <button class="wd-btn wd-btn--ghost" id="obhistory" type="button" title="Refresh"><i class="fa-solid fa-rotate"></i></button>
            </div>
        </div>
        <div class="wd-tablewrap">
            <table class="wd-table">
                <thead>
                    <tr>
                        <th>Filing Date</th>
                        <th>Date From</th>
                        <th>Date To</th>
                        <th>Time From</th>
                        <th>Time To</th>
                        <th>Itinerary From</th>
                        <th>Itinerary To</th>
                        <th>Purpose</th>
                        <th>Cash Advance</th>
                        <th>CA Purpose</th>
                        <th>Status</th>
                        <th>Delete</th>
                    </tr>
                </thead>
                <tbody id="tbob">
                    <?php
                        try{
                        include 'w_conn.php';
                        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                           }
                        catch(PDOException $e)
                           {
                        die("ERROR: Could not connect. " . $e->getMessage());
                          }
                      $id=$_SESSION['id'];
                      $dt1= date('Y-m-d', strtotime(date("Y-m-d")  . ' - 15 days'));
                      $dt2=date("Y-m-d");
                     $statement = $pdo->prepare("SELECT * from obs as a
                                  INNER JOIN status AS b ON a.OBStatus=b.StatusID
                                  WHERE a.EmpID=:id and a.OBStatus<>7   and (a.OBDateFrom BETWEEN :dt1 AND :dt2 or a.OBDateTo BETWEEN :dt1 AND :dt2) order by a.OBInputDate desc");

                  $statement->bindParam(':id' , $id);
                  $statement->bindParam(':dt1' , $dt1);
                  $statement->bindParam(':dt2' , $dt2);
                  $statement->execute();
                while ($row21 = $statement->fetch()){
                  ?>
                    <tr>
                        <td><?php echo date("F j, Y", strtotime($row21['OBFD'])); ?></td>
                        <td><?php echo date("F j, Y", strtotime($row21['OBDateFrom'])); ?></td>
                        <td><?php echo date("F j, Y", strtotime($row21['OBDateTo'])); ?></td>
                        <td><?php echo date("h:i:s A", strtotime($row21['OBTimeFrom'])); ?></td>
                        <td><?php echo date("h:i:s A", strtotime($row21['OBTimeTo'])); ?></td>
                        <td><?php echo htmlspecialchars($row21['OBIFrom']); ?></td>
                        <td><?php echo htmlspecialchars($row21['OBITo']); ?></td>
                        <td><?php echo htmlspecialchars($row21['OBPurpose']); ?></td>
                        <td><?php echo htmlspecialchars($row21['OBCAAmt']); ?></td>
                        <td><?php echo htmlspecialchars($row21['OBCAPurpose']); ?></td>
                        <td><?php echo wd_status_pill($row21['StatusDesc']); ?></td>
                        <?php
                            if($row21['OBStatus']==1){?>
                        <td><button type="button" class="wd-iconbtn" style="width:32px;height:32px;font-size:14px;color:var(--danger-text);border-color:var(--danger-bg)"
                                data-toggle="modal" data-target="#myModalob<?php echo $row21['OBID']; ?>" title="Cancel request"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></td>
                          <?php
                        }else{ ?>
                        <td><span style="color:var(--text-3)">&mdash;</span></td>
                        <?php }
                            ?>
                    </tr>
                    <!-- Cancel-confirmation modal -->
                    <div class="modal ob-viewdel" id="myModalob<?php echo $row21['OBID']; ?>">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header" style="background-color:#f93627;color:#fff">
                                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1">&times;</button>
                                    <h4 class="modal-title">Cancel this OB request?</h4>
                                </div>
                                <div class="modal-body">
                                    <p style="color:var(--text-2)">This action removes the pending request. Continue?</p>
                                    <button type="button" id="<?php echo $row21['OBID']; ?>" class="wd-btn wd-btn--primary ys_ob">Yes, cancel it</button>
                                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">No</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php include 'includes/wd-footer.php'; ?>

    <!-- ===== Official Business Trip Form modal (hooks preserved for module JS) ===== -->
    <div class="modal" id="newform">
        <div class="modal-dialog">
            <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header" style="background-color:#f93627;color:#fff">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1">&times;</button>
                    <h4 class="modal-title">Official Business Trip Form</h4>
                </div>
                <?php
                  include 'w_conn.php';
                    try{
                    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                       }
                    catch(PDOException $e)
                       {
                    die("ERROR: Could not connect. " . $e->getMessage());
                       }

                      $id=$_SESSION['id'];
                      $isid=$_SESSION['EmpISID'];
                      $statement = $pdo->prepare("SELECT *
                                  FROM employees
                                  INNER JOIN empdetails ON employees.EmpID=empdetails.EmpID
                                  INNER JOIN companies ON empdetails.EmpCompID=companies.CompanyID
                                  INNER JOIN departments ON empdetails.EmpdepID=departments.DepartmentID
                                  INNER JOIN positions ON positions.PSID=employees.PosID where employees.EmpID=:id order by employees.EmpLN ASC ");
                      $statement->bindParam(':id' , $id);
                      $statement->execute();
                      $row = $statement->fetch();
                ?>
                <!-- Modal body -->
                <div class="modal-body ob-body">
                    <form id="obdata">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Personnel Name:</label>
                                    <input type="text" disabled class="form-control" value="<?php echo $row['EmpLN']. ' ' . $row['EmpFN']. ' ' .$row['EmpMN'] ?>">
                                </div>
                                <div class="form-group">
                                    <label>Company Name:</label>
                                    <input type="text" disabled class="form-control" value="<?php echo $row['CompanyDesc'] ?>">
                                </div>
                                <div class="form-group">
                                    <label>Department:</label>
                                    <input type="text" disabled class="form-control" value="<?php echo $row['DepartmentDesc'] ?>">
                                </div>
                                <div class="form-group">
                                    <label>Designation:</label>
                                    <input type="text" disabled class="form-control" value="<?php echo $row['PositionDesc'] ?>">
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Filing Date:</label>
                                    <input type="text" name="fdate" readonly="readonly" value="<?php echo date('F d, Y'); ?>" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>OB Date From:</label>
                                    <input type="date" name="obdatefrom" class="form-control obdatefrom">
                                </div>
                                <div class="form-group">
                                    <label>OB Date To:</label>
                                    <input type="date" name="obdateto" class="form-control obdateto">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-4"><h6>ITINERARY</h6>
                                <div class="row">
                                    <div class="col-lg-6 a">
                                        <div class="form-group">
                                            <label>From:</label>
                                            <input type="text" name="ifrom" class="form-control ifrom">
                                        </div>
                                    </div>
                                    <div class="col-lg-6 a">
                                        <div class="form-group">
                                            <label>To:</label>
                                            <input type="text" name="ito" class="form-control ito">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4"><h6>PURPOSE</h6>
                                <div class="form-group">
                                    <textarea class="form-control" name="purpose" rows="2" id="purpose"></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Cash Advance Amount:</label>
                                    <input type="number" name="ca" value="0.00" placeholder="0.00" class="form-control">
                                </div>
                            </div>
                            <div class="col-lg-4"><h6>INCLUSIVE TIME</h6>
                                <div class="row">
                                    <div class="col-lg-6 a">
                                        <div class="form-group">
                                            <label>Departure:</label>
                                            <input type="time" name="timefrom" class="form-control timefrom">
                                        </div>
                                    </div>
                                    <div class="col-lg-6 a">
                                        <div class="form-group">
                                            <label>Return:</label>
                                            <input type="time" name="timeto" class="form-control timeto">
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <label>Purpose:</label>
                                            <input type="text" name="capurpose" class="form-control capurpose">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <button type="button" id="obsave1" class="wd-btn wd-btn--primary" style="width:100%;justify-content:center">Submit</button>
                    </form>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>

            </div>
        </div>
    </div>

    <!-- The Modal -->
    <div class="modal" id="modalWarning">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="padding: 7px 8px;">
                    <h1 style="font-size: 25px; padding-left: 10px;color:red;"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></h1>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- modal end -->

    <!-- The Modal -->
    <div class="modal" id="modalSuccess">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="padding: 7px 8px;">
                    <h1 style="font-size: 25px; padding-left: 10px;color:green;"><i class="fa fa-check" aria-hidden="true"></i></h1>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success">
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- modal end -->
</body>
</html>
