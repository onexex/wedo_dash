<?php 

  require_once 'includes/class.calendar.php';
  $phpCalendar = new PHPCalendar ();
  if (session_status() === PHP_SESSION_NONE) { session_start(); }
   if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
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
                if (password_verify($row['EmpID'], $_COOKIE["WeDoID"])){
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
  if (isset($_GET['addannoun'])){
      include 'w_conn.php';
      if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
      else{ header ('location: login.php'); }
  try{
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       }
    catch(PDOException $e)
       {
    die("ERROR: Could not connect. " . $e->getMessage());
       }
         date_default_timezone_set("Asia/Manila");
         $today = date("Y-m-d H:i:s");
       $ann='Announcement';
        $sql = "INSERT INTO announcements (EmpID,Title,ADesc,ADate) 
          VALUES (:id,:AnnT,:announ,:dtte)";
           $stmt = $pdo->prepare($sql);
           $stmt->bindParam(':id' ,$_SESSION['id']);
           $stmt->bindParam(':AnnT' ,$ann);
           $stmt->bindParam(':announ' ,$_POST['announ']);
             $stmt->bindParam(':dtte' ,$today);
           $stmt->execute(); 
       return;
}

if (isset($_GET['updateann'])){
      date_default_timezone_set("Asia/Manila"); 
     include 'w_conn.php';
      try{
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       }
    catch(PDOException $e)
       {
    die("ERROR: Could not connect. " . $e->getMessage());
       }
        $sql = "UPDATE announcements SET ADesc=:announ  WHERE aid=:id";
           $stmt = $pdo->prepare($sql);
           $stmt->bindParam(':id' ,$_GET['updateann']);
           $stmt->bindParam(':announ' ,$_POST['announ']);
           $stmt->execute();
           header("location: corner");
}

/* Opening the Corner marks every announcement this user hasn't seen yet as
   seen, which clears the unseen-announcement badge in the sidebar. Runs only
   on a normal page load (the handlers above exit on AJAX/redirect). */
if (isset($_SESSION['id']) && $_SESSION['id'] != "0") {
    try {
        include 'w_conn.php';
        $seenPdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
        $seenPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        date_default_timezone_set("Asia/Manila");
        $seenNow = date("Y-m-d H:i:s"); // datetime column: 24h, no AM/PM
        // recent announcements this user has not seen yet (same 30-day window
        // the sidebar badge counts, so opening the Corner clears the badge)
        $unseen = $seenPdo->prepare(
            "SELECT a.aid FROM announcements a
             LEFT JOIN annseen s ON s.aid = a.aid AND s.EmpID = :id
             WHERE s.aid IS NULL AND a.ADate >= (NOW() - INTERVAL 30 DAY)");
        $unseen->execute([':id' => $_SESSION['id']]);
        $unseenAids = $unseen->fetchAll(PDO::FETCH_COLUMN);
        if ($unseenAids) {
            $markSeen = $seenPdo->prepare(
                "INSERT INTO annseen (aid, EmpID, FSeenDate, LSeenDate, Status)
                 VALUES (:aid, :id, :sd, :ld, 1)");
            foreach ($unseenAids as $aid) {
                $markSeen->execute([':aid' => $aid, ':id' => $_SESSION['id'], ':sd' => $seenNow, ':ld' => $seenNow]);
            }
        }
    } catch (Exception $e) { /* non-fatal: badge simply persists until next view */ }
}
?>
<!DOCTYPE html>
<html>
<head>

    <title><?php  if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo $_SESSION['CompanyName'] . " Corner";  } ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
      <!-- <script type="text/javascript" src="assets/js/jquery-1.11.2.min.js"></script> -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <script type="text/javascript" src="assets/js/script.js"></script>
    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="assets/css/style-cal.css">
    <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
    <style type="text/css">
    .ann{
      overflow: scroll;
      height: 500px;
    }
    .hldyac{
      cursor: pointer;
      background-color: red !important;
      color: white !important;
    }
    .hldviewer{
      display: none;
      padding: 10px 0px;
      font-size: 20px;
      color: red;
    }
    .popover{
      display: inline-block !important;
    }
    .cn{
     background-color: #dddddd4d;
      margin-bottom: 5px;
      border-radius: 5px;
    }
    .hdr{
      
      height: 25px;
    }
    .cnt{
      padding: 10px;
      
    }
    .cnt img{
      height: 70px;
    }
    .cnt p{
      text-align: justify;
      margin-bottom: 5px;
    }
      .cnt i{
        font-size: 12px;
       }
       i:focus{
        outline: none;
       }
       .rdmore{
        display: none;
       }
        .cnt a{
          cursor: pointer;
        }
        .fa-check,.fa-seen{
          
        }
        .fa-bullhorn{
          font-size: 23px;
        }
        .pci-mid{
              width: 70px;
              height: 70px;
              border-radius: 35px;
              background-position: center;
              background-size: cover;
        }
        #modalWarning .modal-body{
          text-align: center;
          
        }
        #modalWarning i{
          font-size: 50px;
            margin-bottom: 10px;
            color: #d1c156;
        }
  </style>
  <script type="text/javascript">

    $(document).ready(function(){

       $('#myModal').on('shown.bs.modal', function () {
        
            $('#desc').focus();
        }) ; 
        $(document).on("click", ".clckday",function(){
           var ddi = $(this).attr("id");
           $(".hldviewer").hide();
           $("." + ddi).show();
          
        });

      $('[data-toggle="popover"]').popover();


        $(document).on("click", '.prev', function(event) { 
          var month =  $(this).data("prev-month");
          var year =  $(this).data("prev-year");
          getCalendar(month,year);
        });
        $(document).on("click", '.next', function(event) { 
          var month =  $(this).data("next-month");
          var year =  $(this).data("next-year");
          getCalendar(month,year);
        });
        $(document).on("blur", '#currentYear', function(event) { 
          var month =  $('#currentMonth').text();
          var year = $('#currentYear').text();
          getCalendar(month,year);
        });
        function getCalendar(month,year){
          $("#body-overlay").show();
        $.ajax({
          url: "includes/calendar-ajax.php",
          type: "POST",
          data:'month='+month+'&year='+year,
          success: function(response){
            setInterval(function() {$("#body-overlay").hide(); },500);
            $("#calendar-html-output").html(response);  
          },
          error: function(){} 
        });
      }
  
            $(".btnsaveann").click(function(){
                  if ($("#desc").val().length<6){
                    alert("Please input Announcement more than 8 letters.");
                    return;
                  }
                //display
                   var data=$(".addfrmannouncement").serialize();
                    $(this).text("Saving Data ..");
                    document.getElementById("saveAnn").disabled = true;
                   $.ajax({
                          url:'corner.php?addannoun', 
                          type:'post',
                           data:data,

                           success:function(data){
                             $(this).text("Save");
                              document.getElementById("saveAnn").enabled = true;
                              location.reload();
                           }
                    });
                
            });


            $(document).on("click", ".ann .btnrdmore", function(){

                var tid = $(this).attr("id");
                var hs = $(this).hasClass("cmore");
              
                if (hs==true){

                      $.ajax({
                          url:'query/Query-IUCorner.php', 
                          type:'post',
                          data: { idn : tid },

                           success:function(data){
                          
                           }
                      });

                    $("#rdv"+ tid).css( "display", "none");
                    $("#rdmore"+ tid).fadeIn(200);
                    $("#dtmore"+ tid).css( "display", "block");
                    $(this).text("Read Less..");
                    $(this).removeClass("cmore");
                    $(this).addClass("cless");


                } else{

                    $("#rdv"+ tid).css( "display", "block");
                    $("#rdmore"+ tid).css( "display", "none");
                    $("#dtmore"+ tid).css( "display", "none");
                    $(this).text("Read More..");
                    $(this).removeClass("cless");
                    $(this).addClass("cmore");
                }
            });
              });
  </script>
    
</head>
<body style="background-image: none">
    <?php 
    include 'includes/header.php';

  ?>
     <div class="w-container">
        <div class="row">
          <div class="col-lg-3"></div>
         <!-- website content -->
         <div class="col-lg-5">
            <br>
            <h3>Announcements</h3>
            <?php 
              if ($_SESSION['UserType']==1 || $_SESSION['id']=="WeDoinc-002" || $_SESSION['id']=="WeDoinc-006" || $_SESSION['id']=="WeDoinc-003"){
                ?>
            <button class="btn btn-info" onclick="getFocus()" data-toggle="modal" data-target="#myModal"> Add New Announcement</button>

                <?php
              }
            ?>

            <!-- The Modal -->
            <div class="modal" id="myModal">
              <div class="modal-dialog">
                <div class="modal-content">

                  <!-- Modal Header -->
                  <div class="modal-header">
                    <h4 class="modal-title">Announcements</h4>
                  </div>

                  <!-- Modal body -->
                  <div class="modal-body">
                    <form method="post"  class="addfrmannouncement">
                     <!--  <div class="form-group">
                        <label for="usr">Title:</label>
                        <input type="text" class="form-control" required="required" name="titles">
                      </div> -->
                      <div class="form-group">
                        <label for="usr">Announcement:</label>
                         <textarea class="form-control" minlength="6" required="required" rows="5" name="announ" id="desc"></textarea>
                      </div>
                      <button id="saveAnn" class="btn btn-primary btn-block btnsaveann">Save</button>
                    </form>
                  </div>

                  <!-- Modal footer -->
                  <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                  </div>

                </div>
              </div>
            </div>



                 <br>
                 <br>
                   <div class="dtpar"><!-- 
            <p>Date Parameters:</p> -->
            <!--<div class="row">-->
            <!--  <div class="col-lg-4">-->
            <!--<label>From:</label>-->
            <!--<input type="date" id="dpfrom" class="form-control"></div>-->
            <!--  <div class="col-lg-4">-->
            <!--  <label>To:</label>-->
            <!--<input type="date" id="dpto" class="form-control"></div>-->
            <!--<div class="col-lg-4">-->
              
            <!--<button class="btn btn-warning btn-block btndparann" style="position: absolute; bottom: 0;"   type="button">View</button>-->
            <!--</div>-->
            <!--</div>-->
            <br>
        </div>
            <div class="ann">
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
                        $statement = $pdo->prepare("select * from announcements inner join employees on announcements.EmpID=employees.EmpID   order by ADate desc");
                        $statement->execute();

                        while ($row2 = $statement->fetch()){
                            $st = $pdo->prepare("select * from empprofiles where EmpID=:id");
                            $st->bindParam(':id' , $row2['EmpID']);
                            $st->execute(); 

                            $count=$st->rowCount();
                            $row=$st->fetch();
                            if ($count<1){
                                 $path="assets/images/profiles/default.png";
                                $url = "'assets/images/profiles/default.png'";
                            }else{
                                 $path="assets/images/profiles/default.png";
                              try{
                                if ($row['EmpPPath']==""){
                                  $path="assets/images/profiles/default.png";
                                   $url= 'assets/images/profiles/default.png';

                                }else{
                                  $path=$row['EmpPPath'];
                                   $url=  $row['EmpPPath'];
                                }
                              }
                              catch(Exception $e) {
                                $path="assets/images/profiles/default.png";
                                 $url = "'assets/images/profiles/default.png'";
                              }
                        }
                          $stmt=$pdo->prepare("Select * from annseen where aid='$row2[aid]'");
                          $stmt->execute();
                          $nrow=$stmt->rowCount();
                          $snby="";
                          while($rrow=$stmt->fetch()){
                                $state=$pdo->prepare("select * from employees where EmpID='$rrow[EmpID]'");
                                $state->execute();
                                $roow2=$state->fetch();
                                $snby=$snby . $roow2['EmpFN'] . " " . $roow2['EmpLN'] . "<br/>";
                          }
                        
                    ?>
                          <!-- announcemetn -->
                            <div class="cn" id="cn">
                                <div class="cnt">
                                 <div class="row">
                                  <div class="col-lg-2">
                                    <div class="pci-mid" style="background-image: url('<?php    if(file_exists($url))
                    { echo $url; }else{
                        if ($row['EmpGender']=="Male"){
                              echo "assets/images/profiles/man_d.jpg";
                      }else{
                              echo "assets/images/profiles/woman_d.jpg";
                      }
                    } ?>');">
                                    
                                    </div>
                                  
                                  </div>
                                  <div class="col-lg-10">
                                    <h5> <i class="fa fa-bullhorn" aria-hidden="true" style="font-size: 23px; <?php echo "color: " . $_SESSION['CompanyColor']; ?>"></i>
                                       
                                     <?php echo $row2['Title'];  ?> <!-- <?php  if ($_SESSION['UserType']==1){ ?> 
                                      <i class="fa fa-pencil-square" style="font-size: 23px; " data-toggle="modal" data-target="#myModal<?php echo $row2[0]; ?>" aria-hidden="true"></i><?php } ?> -->
                                     
                                     <!--  <i href="#" title="Seen by" role="button" cursor: pointer; data-trigger="focus" tabindex="0"  data-toggle="popover"  data-html="true"  data-content="<?php echo $snby; ?>" class="fa-seen" style="margin-left: 10px;">Seen by <?php echo $nrow; ?></i> -->
                                      <?php  if ($_SESSION['id']==$row2['EmpID']){ ?>
                                     <a href=""data-toggle="modal" data-target="#myModal<?php echo $row2[0]; ?>"><i class="fa fa-pencil-square" data-toggle="tooltip" data-placement="bottom" title="EDIT This Announcement" style="margin-left: 10px;cursor:pointer;font-size: 23px; "  aria-hidden="true"></i></a>
                                   <?php } ?>
                                     <i class="fa fa-check" aria-hidden="true"></i>
                                    </h5>
                                    <h6><?php if( $row2['EmpFN']=="admin") {
                                        echo "WeDo Family";
                                    }else{
                                      echo $row2['EmpFN'] . " " . $row2['EmpLN'];
                                    }
                                    
                                   ?> </h6>

                                   
                                                              <!-- The Modal -->
                                    <div class="modal" id="myModal<?php echo $row2[0]; ?>">
                                      <div class="modal-dialog">
                                        <div class="modal-content">

                                          <!-- Modal Header -->
                                          <div class="modal-header">
                                            <h4 class="modal-title">Announcements</h4>
                                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                                          </div>

                                          <!-- Modal body -->
                                          <div class="modal-body">
                                            <form method="post" action="?updateann=<?php echo $row2[0]; ?>" class="frmannouncement">
                                           
                                              <div class="form-group">
                                                <label for="usr">Description:</label>
                                                 <textarea class="form-control" required="required" rows="5" name="announ" id="desc"><?php echo $row2['ADesc']; ?></textarea>
                                              </div>
                                              <button class="btn btn-success btn-block btnupdating" >Update</button>
                                            </form>
                                          </div>

                                          <!-- Modal footer -->
                                          <div class="modal-footer">
                                            <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                                          </div>

                                        </div>
                                      </div>
                                    </div>
                                    <!-- end of modal   -->
                                 
                                
                                    <p class="rdv" id="rdv<?php echo $row2[0]; ?>" >
                                      <h2><?php echo $row2['ADesc']; ?>  </h2>
                                      <p><?php  $newDate = date("F d, Y h:i:s A", strtotime($row2['ADate']));
                                     echo $newDate; ?></p>
                                    <?php
                                      // echo substr($row2['ADesc'],0,25) . "...."; ?>
                                    </p>
                                    <p class="rdmore" id="rdmore<?php echo $row2[0]; ?>">
                                      <?php
                                        ?>
                                    </p>
                                     <p class="rdmore" id="dtmore<?php echo $row2[0]; ?>">
                                     <?php 

                                    
                                      ?>
                                    </p>
                                   <!--  <a  class="btn btn-link btnrdmore cmore" id="<?php echo $row2[0]; ?>">Read More..</a> -->

                                  </div>
                                </div>
                                
                              </div>
                            </div>
                            <!-- end of content -->
                    <?php     
                        }
                    ?>

                  
                   

                  

            </div>
          </div>
          <div class="col-lg-4">
            <br>
                     <h3>Calendar </h3>
                    <div style="height:15px;background: #000;width:15px;display:inline-block; margin-right:3px;"></div><h5 style="display:inline-block;"> Current Day</h5>
                 
                    <div style="height:15px;background: red;width:15px;display:inline-block; margin-right:3px;"></div><h5 style="display:inline-block;"> Holiday</h5>
                    <!--<div style="height:15px;background: linear-gradient(45deg, red 50%, #000 50%);width:15px;display:inline-block; margin-right:3px;"></div><h5 style="display:inline-block;"> Current Day and Holiday</h5>-->
                   
            <div id="calendar-html-output">
              <?php
              $calendarHTML = $phpCalendar->getCalendarHTML();
              echo $calendarHTML;
              ?>
              </div>
              <br>
              <!-- <button class="btn btn-success">Add New Holiday</button> -->
          </div>

  </div>
</div>
   <!-- The Modal -->
        <div class="modal" id="modalWarning">
          <div class="modal-dialog">
            <div class="modal-content">
            
              <!-- Modal Header --> 
              <div class="modal-header" style="padding: 7px 8px;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
              </div>
              
              <!-- Modal body -->
              <div class="modal-body">
                <i class="fa fa-exclamation-circle" aria-hidden="true"></i>
                <div class="alert alert-danger">
            
            </div>
              </div>
              
              <!-- Modal footer -->
           
              
            </div>
          </div>
        </div>
        <!-- modal end -->  

     
</body>
</html>
