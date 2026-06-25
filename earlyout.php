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
    <title>Early Out Filing System</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Functional libs (modals + existing module JS) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <script src="assets/js/script-eo.js"></script>
    <script src="assets/js/script-reports.js"></script>
    <script type="text/javascript" src="assets/js/script-home.js"></script>
    <script type="text/javascript" src="assets/js/script.js"></script>
</head>
<body>
    <?php $wd_active = 'earlyout'; include 'includes/wd-header.php'; ?>

    <div class="wd-pagehead">
        <div>
            <h1>Early Out Filing System</h1>
            <p>File an early-out request and review your recent filings.</p>
        </div>
        <button type="button" class="wd-btn wd-btn--primary" data-toggle="modal" data-target="#newform"><i class="fa-solid fa-plus"></i> Early Out Form</button>
    </div>

    <section class="wd-card">
        <div class="wd-card__head">
            <h3>Early Out history</h3>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <input type="date" class="wd-input" id="dtp1" style="width:auto;padding:7px 10px"
                    value="<?php echo date('Y-m-d', strtotime(date("Y-m-d") . ' - 15 days')); ?>">
                <span style="color:var(--text-3);font-size:12px">to</span>
                <input type="date" class="wd-input" id="dtp2" style="width:auto;padding:7px 10px"
                    value="<?php echo date("Y-m-d"); ?>">
                <button class="wd-btn wd-btn--ghost" id="eohistory" type="button" title="Refresh"><i class="fa-solid fa-rotate"></i></button>
            </div>
        </div>
        <div class="wd-tablewrap">
            <table class="wd-table">
                <thead>
                    <tr>
                        <th>Purpose</th>
                        <th>Date Filed</th>
                        <th>Status</th>
                        <th>Date Time Inputed</th>
                        <th>Delete</th>
                    </tr>
                </thead>
                <tbody id="tbeodata">
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
                  $dt1=date('Y-m-d', strtotime(date("Y-m-d")  . ' - 15 days'));
                  $dt2=date('Y-m-d', strtotime(date("Y-m-d")  . ' + 1 days'));
                  $statement = $pdo->prepare("SELECT * FROM earlyout AS a INNER JOIN status AS b ON a.Status=b.StatusID  WHERE a.EmpID=:id and status<>7 and DFile  between :d1 and :d2  ORDER BY DateTimeInputed DESC");
                  $statement->bindParam(':id' , $id);
                  $statement->bindParam(':d1' , $dt1);
                  $statement->bindParam(':d2' , $dt2);
                  $statement->execute();
                while ($row21 = $statement->fetch())
                {
                  ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row21['Purpose']); ?></td>
                        <td><?php echo date("F j, Y", strtotime($row21['DFile'])); ?></td>
                        <td><?php echo wd_status_pill($row21['StatusDesc']); ?></td>
                        <td><?php echo date("F j, Y h:i:s A", strtotime($row21['DateTimeInputed'])); ?></td>
                        <?php
                            if($row21['Status']==1){
                        ?>
                        <td><button type="button" class="wd-iconbtn" style="width:32px;height:32px;font-size:14px;color:var(--danger-text);border-color:var(--danger-bg)"
                                data-toggle="modal" data-target="#myModalw<?php echo $row21['SID']; ?>" title="Cancel request"><i class="fa-solid fa-trash" aria-hidden="true"></i></button></td>
                        <?php
                        }else{ ?>
                        <td><span style="color:var(--text-3)">&mdash;</span></td>
                        <?php }
                        ?>
                    </tr>

                    <!-- Cancel-confirmation modal -->
                    <div class="modal eo-delete" id="myModalw<?php echo $row21['SID']; ?>">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header" style="background-color:#f93627;color:#fff">
                                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1">&times;</button>
                                    <h4 class="modal-title">Cancel this early-out request?</h4>
                                </div>
                                <div class="modal-body">
                                    <p style="color:var(--text-2)">This action removes the pending request. Continue?</p>
                                    <button type="button" id="<?php echo $row21['SID']; ?>" class="wd-btn wd-btn--primary ys_eo">Yes, cancel it</button>
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

    <!-- ===== Early Out Form modal (hooks preserved for script-eo.js) ===== -->
    <div class="modal" id="newform">
        <div class="modal-dialog">
            <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header" style="background-color:#f93627;color:#fff">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1">&times;</button>
                    <h4 class="modal-title">Early Out Form</h4>
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
                              INNER JOIN positions ON positions.PSID=employees.PosID
                              WHERE employees.EmpID=:id ORDER BY employees.EmpLN ASC ");
                  $statement->bindParam(':id' , $id);
                  $statement->execute();
                  $row = $statement->fetch();
                ?>
                <!-- Modal body -->
                <div class="modal-body">
                    <form id="eodata" action="">
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
                                    <input type="text" disabled value="<?php echo date('F d, Y h:i:s A'); ?>" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>EO Date:</label>
                                    <input type="date" name="dttd" value="<?php echo date('Y-m-d');?>" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>Purpose:</label>
                                    <textarea class="form-control" name="pur" rows="4" id="comment"></textarea>
                                </div>
                                <button type="button" class="wd-btn wd-btn--primary" style="width:100%;justify-content:center" data-toggle="modal" data-target="#myModalnew">Submit</button>
                            </div>
                        </div>
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
                <div class="modal-header" style="padding: 7px 8px;background-color: #327e0b;color: #fff;">
                    <label>Message</label>
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

    <!-- The Modal — apply confirmation (triggers the actual submit) -->
    <div class="modal eo-delete" id="myModalnew">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background-color:#f93627;color:#fff">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1">&times;</button>
                    <h4 class="modal-title">Are you sure you want to apply Early Out?</h4>
                </div>
                <div class="modal-body">
                    <button type="button" id="eosave" class="wd-btn wd-btn--primary yestoeo">Yes</button>
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">No</button>
                </div>
            </div>
        </div>
    </div>
    <!-- modal end -->
</body>
</html>
