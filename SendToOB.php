<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
   if (isset($_SESSION['id']) && $_SESSION['id']!="0"){

     }
  else{
    if(!isset($_COOKIE["WeDoID"])) {

        header ('location: login');
    }else{
        if(!isset($_COOKIE["WeDoID"])) {
          session_destroy();
          header ('location: login');
        }else{
              try{
              include 'w_conn.php';
              $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
              $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                 }
              catch(PDOException $e)
                 {
              die("ERROR: Could not connect. " . $e->getMessage());
                 }
              $statement = $pdo->prepare("select * from empdetails");
              $statement->execute();

              while ($row=$statement->fetch()) {
                if ((!empty($row['remember_hash']) && password_verify($_COOKIE["WeDoID"], $row['remember_hash']) && (empty($row['remember_expiry']) || strtotime($row['remember_expiry']) > time()))){
                        $_SESSION['id']=$row['EmpID'];

                        $statement = $pdo->prepare("select * from empdetails where EmpID = :un");
                        $statement->bindParam(':un' , $_SESSION['id']);
                        $statement->execute();
                        $count=$statement->rowCount();
                        $row=$statement->fetch();
                        $hash = $row['EmpPW'];
                        $_SESSION['UserType']=$row['EmpRoleID'];
                        $cid=$row['EmpCompID'];
                        $_SESSION['CompID']=$row['EmpCompID'];
                        $_SESSION['EmpISID']=$row['EmpISID'];
                        $statement = $pdo->prepare("select * from companies where CompanyID = :pw");
                        $statement->bindParam(':pw' , $cid);
                        $statement->execute();
                        $comcount=$statement->rowCount();
                        $row=$statement->fetch();
                        if ($comcount>0){
                          $_SESSION['CompanyName']=$row['CompanyDesc'];
                          $_SESSION['CompanyLogo']=$row['logopath'];
                          $_SESSION['CompanyColor']=$row['comcolor'];
                        }else{
                          $_SESSION['CompanyName']="ADMIN";
                          $_SESSION['CompanyLogo']="";
                          $_SESSION['CompanyColor']="red";
                        }
                         $_SESSION['PassHash']=$hash;

                }
                else{

                }
              }
            }
    }

  }
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
    <title>Send to Official Business Trip</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Functional libs (modals + existing module JS) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <style>
        /* ===== Send to OB Trip Form modal — larger, more readable controls ===== */
        #newform .modal-dialog {
            width: 92%;
            max-width: 940px;
            margin: 30px auto;
        }
        #newform .modal-content { border-radius: 10px; overflow: hidden; }
        #newform .modal-header { padding: 16px 22px; }
        #newform .modal-title { font-size: 20px; font-weight: 600; }
        #newform .modal-body.ob-body { padding: 26px 28px; }
        #newform .modal-footer { padding: 14px 22px; }

        /* Labels: readable and consistently spaced */
        #newform label {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-2, #444);
            margin-bottom: 6px;
        }
        #newform h6 {
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .5px;
            color: var(--text-3, #666);
            margin: 4px 0 12px;
        }

        /* Inputs: bigger hit area and legible text */
        #newform .form-control {
            height: 44px;
            font-size: 15px;
            padding: 10px 14px;
            border-radius: 8px;
            box-shadow: none;
        }
        #newform select.form-control { height: 44px; }
        #newform textarea.form-control {
            height: auto;
            min-height: 92px;
            line-height: 1.4;
        }
        #newform .form-control:focus {
            border-color: #f93627;
            box-shadow: 0 0 0 3px rgba(249, 54, 39, .15);
        }
        #newform .form-control[disabled],
        #newform .form-control[readonly] {
            background: #f1f2f4;
            color: #555;
            cursor: not-allowed;
        }

        /* Consistent vertical rhythm between fields */
        #newform .form-group { margin-bottom: 18px; }

        /* Time/date inputs shouldn't clip their native icons */
        #newform input[type="time"],
        #newform input[type="date"] { padding-right: 10px; }

        #newform .btnsendtoob {
            height: 48px;
            font-size: 16px;
            font-weight: 600;
            margin-top: 8px;
        }

        @media (max-width: 767px) {
            #newform .modal-dialog { width: 96%; margin: 16px auto; }
            #newform .modal-body.ob-body { padding: 18px; }
        }
    </style>

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script src="assets/js/script-reports.js"></script>
    <script type="text/javascript" src="assets/js/script-modules.js"></script>
    <script type="text/javascript" src="assets/js/administrative.js"></script>
</head>
<body>
    <?php $wd_active = 'SendToOB'; include 'includes/wd-header.php'; ?>

    <div class="wd-pagehead">
        <div>
            <h1>Send to Official Business Trip</h1>
            <p>File an official business trip on behalf of personnel and review recent filings.</p>
        </div>
        <button type="button" class="wd-btn wd-btn--primary" id="eventListener" data-toggle="modal" data-target="#newform"><i class="fa-solid fa-plus"></i> Send to OB Trip Form</button>
    </div>

    <section class="wd-card">
        <div class="wd-card__head">
            <h3>Send to OB History</h3>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <input type="date" class="wd-input" id="dtp1" style="width:auto;padding:7px 10px"
                    value="<?php echo date('Y-m-d', strtotime(date("Y-m-d") . ' - 15 days')); ?>">
                <span style="color:var(--text-3);font-size:12px">to</span>
                <input type="date" class="wd-input" id="dtp2" style="width:auto;padding:7px 10px"
                    value="<?php echo date("Y-m-d"); ?>">
                <button class="wd-btn wd-btn--ghost" id="sob" type="button" title="Refresh"><i class="fa-solid fa-rotate"></i></button>
            </div>
        </div>
        <div class="wd-tablewrap">
            <table class="wd-table">
                <thead>
                    <tr>
                        <th>Filing Date</th>
                        <th>Name</th>
                        <th>Date From</th>
                        <th>Date To</th>
                        <th>Itinerary To</th>
                        <th>Purpose</th>
                        <th>Cash Advance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="tbsob">
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
                      if ($_SESSION['UserType']==1){
                         $statement = $pdo->prepare("SELECT * from employees inner join  obs as a on employees.EmpID=a.EmpID  INNER JOIN status as b on a.OBStatus=b.StatusID
                                  where  OBDateFrom between :dt1 and :dt2 and OBStatus=4 order by OBDateFrom desc");
                      }else{
                         $statement = $pdo->prepare("SELECT * from employees INNER JOIN empdetails on employees.EmpID=empdetails.EmpID inner join  obs as a on empdetails.EmpID=a.EmpID
                                  INNER JOIN status as b on a.OBStatus=b.StatusID
                                  where (empdetails.EmpID=:id or empdetails.EmpISID=:id) and OBDateFrom between :dt1 and :dt2 and (a.OBType<>7 or a.OBType<>5 or a.OBType<>3) order by OBDateFrom desc");
                  $statement->bindParam(':id' , $id);
                      }

                  $statement->bindParam(':dt1' , $dt1);
                  $statement->bindParam(':dt2' , $dt2);
                  $statement->execute();

                while ($row21 = $statement->fetch())
                {
                  ?>
                   <tr>
                   <td><?php echo date("F j, Y", strtotime($row21['OBFD'])); ?></td>
                   <td><?php echo htmlspecialchars($row21['EmpLN']); ?></td>
                   <td><?php echo date("F j, Y", strtotime( $row21['OBDateFrom'])); ?></td>
                   <td><?php echo date("F j, Y", strtotime( $row21['OBDateTo'])); ?></td>
                   <td><?php echo htmlspecialchars($row21['OBITo']); ?></td>
                   <td><?php echo htmlspecialchars($row21['OBPurpose']); ?></td>
                   <td><?php echo htmlspecialchars($row21['OBCAAmt']); ?></td>
                   <td><?php echo wd_status_pill($row21['StatusDesc']); ?></td>
                  </tr>
              <?php
              }
              ?>
                </tbody>
            </table>
        </div>
    </section>

    <?php include 'includes/wd-footer.php'; ?>

    <!-- ===== Send to OB Trip Form modal (hooks preserved for script-modules.js) ===== -->
    <div class="modal" id="newform">
        <div class="modal-dialog">
            <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header" style="background-color:#f93627;color:#fff">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1">&times;</button>
                    <h4 class="modal-title">Send to OB Trip Form</h4>
                </div>

                <!-- Modal body -->
                <div class="modal-body ob-body">
                    <form method="post" class="frmsendtoob" id="frmsendtoob">
                        <div class="row">
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Personnel Name:</label>
                                    <select class="form-control" name="empidob" id="empname">
                                        <option></option>
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
                                               if ($_SESSION['UserType']==1){
                                                 $statement = $pdo->prepare("select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID where employees.EmpStatusID='1' AND empdetails.EmpID<>'admin' order by EmpLN");
                                                 $statement->execute();
                                               }else if ($_SESSION['UserType']==2){
                                                   $statement = $pdo->prepare("select * from employees inner join empdetails on employees.EmpID=empdetails.EmpID where employees.EmpStatusID='1' AND EmpISID='$_SESSION[id]' or employees.EmpID='$_SESSION[id]' order by EmpLN");
                                                   $statement->execute();
                                               }

                                            while ($row2 = $statement->fetch()){
                                                if ($row2['EmpID']=="WeDoinc-016" || $row2['EmpID']=="WeDoinc-017" || $row2['EmpID']=="WeDoinc-018" || $row2['EmpID']=="WeDoinc-019" || $row2['EmpID']=="WeDoinc-014" || $row2['EmpID']=="WeDoinc-003"){

                                              }else{
                                          ?>
                                            <option value="<?php echo $row2['EmpID']; ?>"><?php echo $row2['EmpLN'] . ", " . $row2['EmpFN'] ; ?></option>
                                          <?php
                                            }
                                            }
                                          ?>
                                    </select>
                                </div>
                                <div id="empinfo">
                                    <div class="form-group">
                                        <label>Company Name:</label>
                                        <input type="text" disabled class="form-control" id="empcomp">
                                    </div>
                                    <div class="form-group">
                                        <label>Department:</label>
                                        <input type="text" disabled class="form-control" id="empdep">
                                    </div>
                                    <div class="form-group">
                                        <label>Designation:</label>
                                        <input type="text" disabled class="form-control" id="empdesig">
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Filing Date:</label>
                                    <input type="text" disabled value="<?php echo date('F d , Y'); ?>" class="form-control">
                                </div>
                                <div class="form-group">
                                    <label>OB Date From:</label>
                                    <input type="date" value="<?php echo date("Y-m-d");?>" class="form-control" name="obdf" id="obdf">
                                </div>
                                <div class="form-group">
                                    <label>OB Date To:</label>
                                    <input type="date" value="<?php echo date("Y-m-d");?>" class="form-control" name="obdt" id="obdt">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-4"><h6>ITINERARY</h6>
                                <div class="row">
                                    <div class="col-lg-6 a">
                                        <div class="form-group">
                                            <label>From:</label>
                                            <input type="text" readonly value="Tektite" class="form-control" name="itfrom" id="itfrom">
                                        </div>
                                    </div>
                                    <div class="col-lg-6 a">
                                        <div class="form-group">
                                            <label>To:</label>
                                            <input type="text" class="form-control" name="itto" id="itto">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4"><h6>PURPOSE</h6>
                                <div class="form-group">
                                    <textarea class="form-control" rows="2" name="emppurpose" id="emppurpose"></textarea>
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
                                            <input type="time" name="depart" value="08:00" id="depart" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-lg-6 a">
                                        <div class="form-group">
                                            <label>Return:</label>
                                            <input type="time" name="return" value="19:00" id="return" class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="form-group">
                                            <label>Purpose:</label>
                                            <input type="text" class="form-control" name="capurpose">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="wd-btn wd-btn--primary btnsendtoob" style="width:100%;justify-content:center">Submit</button>
                    </form>
                </div>

                <!-- Modal footer -->
                <div class="modal-footer">
                    <button type="button" class="wd-btn wd-btn--ghost" data-dismiss="modal">Close</button>
                </div>

            </div>
        </div>
    </div>

    <!-- The Modal — warning -->
    <div class="modal" id="modalWarning">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="padding: 7px 8px;">
                    <h1 style="font-size: 25px; padding-left: 10px;color:red;"><i class="fa fa-exclamation-triangle" aria-hidden="true"></i></h1>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <h5></h5>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- modal end -->

    <!-- The Modal — success -->
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
