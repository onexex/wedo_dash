<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if((isset($_SESSION['id']) && $_SESSION['id']!="0")){
     header ('location: index.php');
 }else if ((isset($_SESSION['quesID']) && $_SESSION['quesID']!="0")){
     
 } 
 else{ header ('location: login.php'); }
?>
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
?>


<!DOCTYPE html>
<html>
<head>
	<title>Questionnaire</title>
	<meta charset="utf-8">
  	<meta name="viewport" content="width=device-width, initial-scale=1">
  	<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css">
  	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
  	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.0/umd/popper.min.js"></script>
  	<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js"></script>
  	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<script  src="assets/js/logintime.js"></script>
	<script>
	    $(document).ready(function(){
	        $("#myModal").modal("toggle");
	    });
	</script>
  	</head>
<body>
    <div class="container">
        <center>
            <?php
                $statement = $pdo->prepare("select * from empdetails inner join employees on empdetails.EmpID=employees.EmpID where employees.EmpID = :un");
                $statement->bindParam(':un' , $_SESSION['quesID']);
                $statement->execute(); 
                $rowEmp = $statement->fetch();
            ?>
           <!--<h1>Hello <?php echo $rowEmp["EmpFN"] . " " . $rowEmp["EmpLN"];  ?></h1>-->
    <!--<h4>Please click below to answer some question</h4>-->
    
    <br>
    <br>
    <button type="button" class="btn btn-danger" data-toggle="modal" data-target="#myModal">
	  Questionnaire
	</button>
        </center>
        <!-- Modal -->
            <div id="myModal" class="modal fade" role="dialog">
              <div class="modal-dialog" style="max-width:800px !important;">
            
                <!-- Modal content-->
                <div class="modal-content">
                  <div class="modal-header">
                    <a type="button" href="login.php?logout" class="close">&times;</a>
                 
                  </div>
                  <div class="modal-body">
                    <p>
                        <?php
                        
                            //$id=$_SESSION['id'];
                            $statement = $pdo->prepare("SELECT * FROM qt order by no asc");
                            $statement->execute();
                            while ($row = $statement->fetch()){
                        ?>
                            <?php if ($row[2]<=2){ ?>
                            <h4><?php echo $row[1] ?></h4>
                            <br>
                        <?php
                            }
                            else{
                        ?>
                            <h5><?php echo $row[1] ?></h5>
                            <br>
                        <?php
                            }
                            }
                        ?>
                        
                    </p>
                  </div>
                  <div class="modal-footer">
                     
                    <button type="button" class="btn btn-block btn-success" id="YS_ANS">Yes</button>
                 
                  </div>
                </div>

  </div>
</div>
    
        
         <!-- The Modal -->
        <div class="modal" id="modalSuccess">
          <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
            
              <!-- Modal Header --> 
              <div class="modal-header" style="padding: 7px 8px;">
                    <h1 style="font-size: 25px; padding-left: 10px;color:green;"><i class="fa fa-check" aria-hidden="true"></i></h1>
            
              </div>
              
              <!-- Modal body -->
              <div class="modal-body">
                <div class="alert alert-success">
                    Saving your Answer... Please Wait..
                </div>
              </div>
              
              <!-- Modal footer -->
           
              
            </div>
          </div>
        </div>
        <!-- modal end -->  
    
    </div>
    
 
</body>
</html>    