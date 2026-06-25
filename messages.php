<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login'); }
  if (isset($_SESSION['rid'])){
     unset($_SESSION['rid']);
  }
?>
<!DOCTYPE html>
<html>
<head>

    <title><?php  if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo $_SESSION['CompanyName']; } ?></title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="assets/css/bootstrap.min.css">
  <script src="assets/js/jquery.min.js"></script>
  <script src="assets/js/popper.min.js"></script>
  <script src="assets/js/bootstrap.min.js"></script>
  <link rel="stylesheet" href="assets/css/font-awesome.min.css">
   <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<!--  <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script> -->
  <script src="assets/js/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
   <!-- <script src="https://code.jquery.com/jquery-1.12.4.js"></script>   -->
   <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
  <script type="text/javascript" src="assets/js/script.js"></script>
  <script type="text/javascript" src="assets/js/script-message.js"></script>
  
 
  <link rel="stylesheet" type="text/css" href="assets/css/style.css">
 <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
  <style type="text/css">
    .rsn{
      display: none;
    }
    .msg-content{
      border: 1px solid #ddd;
      height: 500px;
      border-radius: 5px;
      padding: 5px;
      overflow-y: scroll;
    }
    #msgc::-webkit-scrollbar-track
    {
   /*   -webkit-box-shadow: inset 0 0 6px rgba(0,0,0,0.3);
      background-color: #F5F5F5;*/
    }

    #msgc::-webkit-scrollbar
    {
      width: 10px;
    }

    #msgc::-webkit-scrollbar-thumb
    {
      background-color: #50a11e;
      border: 2px solid #50a11e;
    }
    .msg-r p{
      padding: 3px 15px;
      background-color: #1e77a1;
      color: #fff;
      width: fit-content;
      border-radius: 5px;
      display: inline-block;
      margin-right: 5px;
    }
    .msg-s{
      text-align: right;
    }
    .msg-s,.msg-r{
      display: block;

    }
    .msg-s p{
      padding: 3px 15px;
      background-color: #d5d8d9;
      color: #000;
      width: fit-content;
      border-radius: 5px;
      display: inline-block;
   
      margin-left: 5px;
    }
    .msg-r i,.msg-s i{
      font-size: 12px;
    }
    .msg-r img,.msg-s img{
      height: 25px;
    width: 25px;
    border-radius: 15px;
    border: 1px solid;
    }
    .send-m{
      margin-top: 5px;
    }
    .send-m input{
      display: inline-block;
      width: 90%;
    }
    .send-m button{
    }
    .emp-ms{
      padding: 10px;
      text-decoration: none;
      color: #000;
      background-color: #f1eded61;
      margin-top: 2px;
      border-left: 1px solid #0fca9f;
      border-right: 1px solid #0fca9f;
    }
    .emp-ms:hover{
      text-decoration: none;
      color: #fff;
       background-color: #0fca9f;
      margin-top: 2px;
      border-left: 1px solid;
    border-right: 1px solid;
    }
    .emp-ms .img-s{
      height: 50px;
      width: 50px;
      border-radius: 30px;
    background-size: cover;
    display: inline-block;
    background-repeat: no-repeat;
    background-position: center;
    }
    .emp-ms .s-name{
      display:  inline-block;
      margin-bottom: 1px;
    }
    .s-name p{
      margin-bottom: 1px;
      line-height: 1;
    }
    .s-name i{
      font-size: 12px;
    }
    .btnam{
       text-decoration: none;
      color: #fff;
       background-color: #0fca9f;
      margin-top: 2px;
      border-left: 1px solid;
    border-right: 1px solid;
    }
  </style>
  <script type="text/javascript">
  
  </script>

  </head>
<body style="background-image: none">
   <?php  include 'includes/header.php';  ?>
    <div class="w-container">
        <div class="row">
          <div class="col-lg-3"></div>
         <!-- website content -->
         <div class="col-lg-9">
             

          <br>
          <div class="row">
              <div class="col-lg-4">
                <form>
                  <input type="text" placeholder="Search Employee" name="search" class="form-control emp-id">
                </form>
                <h5>Chat</h5>
                <div class="dv-s-m" id="empmess">
                    <?php
                        $srch="select * from messageheader where SenderID='$_SESSION[id]' or RecieverID='$_SESSION[id]' order by dateMessage desc";
                        $qry=mysqli_query($con, $srch);
                     
                        $cnt= mysqli_num_rows ($qry);
                        while ($rw=mysqli_fetch_array($qry)){
                          if ($rw['SenderID']==$_SESSION['id']){
                            $sndid=$rw['RecieverID'];

                            $sql = "SELECT * FROM employees WHERE EmpID='$rw[RecieverID]'";
                            $empq=mysqli_query($con, $sql);
                            $rw2=mysqli_fetch_array($empq);
                            $nm=$rw2['EmpFN'] . " " . $rw2['EmpLN'];

                            $sql = "SELECT * FROM empprofiles WHERE EmpID='$rw[RecieverID]'";
                            $empq=mysqli_query($con, $sql);
                            $rw2=mysqli_fetch_array($empq);

                              $prfpath="background-image: url('" . $rw2['EmpPPath'] ."')";

                          }else{
                            $sndid=$rw['SenderID'];
                            $sql = "SELECT * FROM employees WHERE EmpID='$rw[SenderID]'";
                            $empq=mysqli_query($con, $sql);
                            $rw2=mysqli_fetch_array($empq);
                            $nm=$rw2['EmpFN'] . " " . $rw2['EmpLN'];

                            $sql = "SELECT * FROM empprofiles WHERE EmpID='$rw[SenderID]'";
                            $empq=mysqli_query($con, $sql);
                            $rw2=mysqli_fetch_array($empq);

                              $prfpath="background-image: url('" . $rw2['EmpPPath'] ."')";
                          }
                    ?>
                      <a href="#" id="<?php echo $sndid; ?>" class="emp-ms row">
                
                        <div class="img-s col-lg-4 <?php echo $prfpath; ?>"  >
                        <div style="<?php 
                   if(file_exists($rw2['EmpPPath']))
                    {
                    
                      echo $prfpath;
                    }

                    else
                    {
                     if ($rw2['EmpGender']=="Male"){
                              echo "background-image:url('assets/images/profiles/man_d.jpg')";
                      }else{
                              echo "background-image:url('assets/images/profiles/woman_d.jpg')";
                      }
                    }

                    ?>"class="img-s">
                          
                        </div>
                       </div>
                        <div class="s-name col-lg-8">

                          <p>
                              <?php echo $nm; ?>
                          </p>
                          <i><?php 

                                     $newDate = date("F d, Y h:i:s A", strtotime($rw['dateMessage']));
                                     echo $newDate;
                                      ?></i>
                        </div>
                  
                      </a>
                    <?php      
                        } 
                    ?>

                </div>
              

              </div>
              <div class="col-lg-8">
                <h3 class="r-name"></h3>
                <div class="msg-content" id="msgc">
                   Empty
    
                </div>
                <div class="send-m">
                  <input type="text" name="send" placeholder="Write message here.." class="form-control msend">
                  <button class="btn btnsendm"><i class="fa fa-paper-plane" aria-hidden="true"></i></button>
                </div>
              </div>
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
</body>
</html>
