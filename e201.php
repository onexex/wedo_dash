<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
?>
<!DOCTYPE html>
  <html>
  <head>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <link rel="icon" href="assets/images/logos/WeDo.png" type="image/x-icon">

    <!-- Functional libs (modals + existing module JS) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css?v=<?php echo @filemtime('assets/css/wedo-theme.css'); ?>">

    <script src="assets/js/script.js"></script>
    <script src="assets/js/script-e201.js?v=<?php echo filemtime(__DIR__ . "/assets/js/script-e201.js"); ?>"></script>
    <script src="assets/js/getlocationcoors.js" deffer></script>
    <title><?php  if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo $_SESSION['CompanyName']; } ?></title>
    <script type="text/javascript"> 
      $(document).ready(function(){
        $('[data-toggle="tooltip"]').tooltip(); 
      });
      $(document).ready(function(){
        $('[data-toggle="popover"]').popover();   
      });
    </script>
    <link rel="stylesheet" type="text/css" href="assets/css/e201.css?v=<?php echo filemtime(__DIR__ . "/assets/css/e201.css"); ?>">
  </head>

  <body>

  <?php $wd_active = 'e201'; include 'includes/wd-header.php'; ?>

  <?php
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $statement = $pdo->prepare("select * from accessrights where EmpID = :id");
    $statement->bindParam(':id' , $_SESSION['id']);
    $statement->execute();
    $rowbtn=$statement->fetch();
  ?>

  <div class="wd-pagehead">
    <div>
      <h1>Electronic 201 File</h1>
      <p>Employee profile, compliance documents and 201 records.</p>
    </div>
    <?php if ($rowbtn['srch']==2) { ?>
      <div class="e201-search">
        <input type="text" class="search-box" placeholder="Search employee&hellip;">
        <div class="dv-livesearch"></div>
      </div>
    <?php } ?>
  </div>

  <div id="e201" class="w-container e201-scope">
    <?php include_once ("w_conn.php");

    $q = $_SESSION['id'];

    // conversations with unread messages (Messages badge)
    require_once 'includes/messages-lib.php';
    $mnum = msg_unread_threads($pdo, (string) $q);

    $isSelf = true;
    $ISname = ''; $eid = '';   // set by the partial when the profile exists
    include 'includes/e201-profile.php';
    ?>
  </div>
  <!-- The Modal
  -->      <div class="modal" id="newjobd">
  <div class="modal-dialog modal-dialog-centered">
  <div class="modal-content">


  <div class="modal-header">

  <button type="button" class="close" data-dismiss="modal">&times;</button>
  </div>
  <!-- Modal body -->
  <div class="modal-body">
  <form>
  <div class="form-group">
  <label for="email" style="color:<?php echo  $_SESSION['CompanyColor']; ?>;">Employee Job Description</label>
  <div class="EmpListJD" id="EmpListJD" style="height: 114px;overflow-y: scroll;">
  <?php
  $res12=mysqli_query($con,"select * from jobdescription inner join empjobdesc on jobdescription.JD_ID=empjobdesc.JID where empjobdesc.EmpID='" . $eid . "'");
  while ($row3=mysqli_fetch_array($res12)) {
  ?>
  <a  class="btn"><?php echo $row3["JDescription"]; ?> <i class="fa-solid fa-times" id="<?php echo $row3['EJID']; ?>" aria-hidden="true"></i></a>
  <?php
  }
  ?>


  </div>

  <label for="email" style="color:<?php echo  $_SESSION['CompanyColor']; ?>;">List of Job Description</label>
  <input type="text" style="display: inline-block;width: 90%;" placeholder="Search Job Description" name="sjdesc" class="form-control txtsjdesc">
  <div class="ListJD" id="ListJD" style="height: 114px;overflow-y: scroll;">
  <?php
  $res13=mysqli_query($con,"select * from jobdescription order by JDescription asc");
  while ($row=mysqli_fetch_array($res13)) {
  ?>
  <a  class="btn"><?php echo $row[1]; ?> <i class="fa-solid fa-check-circle" id="<?php echo $row[0]; ?>" aria-hidden="true"></i></a>
  <?php
  }
  ?>


  </div>

  <?php if ($_SESSION['UserType']!=3){
  ?>
  <input type="text" style="display: inline-block;width: 90%;" placeholder="New Job Description" name="newjd" class="form-control txtnewjd"><button type="button" id="addnewJD" class="btn btn-success" style="margin-top: -5px;">+</button>
  <?php } ?>
  </div>
  <!--   <button type="submit" class="btn btn-success">Apply Changes</button> -->
  </form>
  </div>

  <!-- Modal footer -->
  <div class="modal-footer">

  </div>

  </div>
  </div>
  </div>

  <!-- The Modal -->
  <div class="modal" id="modalWarning">
  <div class="modal-dialog modal-dialog-centered">
  <div class="modal-content">

  <!-- Modal Header --> 
  <div class="modal-header" style="padding: 7px 8px;">
  <button type="button" class="close" data-dismiss="modal">&times;</button>
  </div>

  <!-- Modal body -->
  <div class="modal-body">
  <div class="alert alert-danger">

  </div>
  </div>

  <!-- Modal footer -->


  </div>
  </div>
  </div>
  <!-- modal end -->

  <!-- Shared 201-file PDF viewer. Lives outside #e201 so it isn't trapped by the
       profile cards (and survives live-search re-renders); script-e201.js loads
       the clicked .e2-file into it on demand. -->
  <div class="modal" id="e201PdfViewer" tabindex="-1">
    <div class="modal-dialog mdl-files">
      <div class="modal-content">
        <div class="modal-header">
          <h4 class="modal-title"><i class="fa-solid fa-file-pdf"></i><span class="e201-pdf-title">Document</span></h4>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <iframe class="e201-pdf-frame" title="201 file preview" src="about:blank"></iframe>
        </div>
        <div class="modal-footer">
          <a class="wd-btn wd-btn--ghost wd-btn--sm e201-pdf-open" href="#" target="_blank" rel="noopener"><i class="fa-solid fa-up-right-from-square"></i>Open in new tab</a>
          <button type="button" class="wd-btn wd-btn--primary wd-btn--sm" data-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <?php include 'includes/wd-footer.php'; ?>
  </body>
</html>