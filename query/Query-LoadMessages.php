<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once ("w_conn.php");

 date_default_timezone_set("Asia/Manila");

 if (isset($_GET['q'])){
                        $srch="select * from messageheader where SenderID='$_SESSION[id]' or RecieverID='$_SESSION[id]' order by dateMessage desc";
                        $qry=mysqli_query($con, $srch);
                     
                        $cnt= mysqli_num_rows ($qry);
                        while ($rw=mysqli_fetch_array($qry)){
                          if ($rw['SenderID']==$_SESSION['id']){
                            $sndid=$rw['RecieverID'];

                            $sql = "SELECT * FROM employees where EmpID='$rw[RecieverID]'";
                            $empq=mysqli_query($con, $sql);
                            $rw2=mysqli_fetch_array($empq);
                            $nm=$rw2['EmpFN'] . " " . $rw2['EmpLN'];

                            $sql = "SELECT * FROM empprofiles where EmpID='$rw[RecieverID]'";
                            $empq=mysqli_query($con, $sql);
                            $rw2=mysqli_fetch_array($empq);

                              $prfpath="background-image: url('" . $rw2['EmpPPath'] ."')";

                          }else{
                            $sndid=$rw['SenderID'];
                            $sql = "SELECT * FROM employees where EmpID='$rw[SenderID]'";
                            $empq=mysqli_query($con, $sql);
                            $rw2=mysqli_fetch_array($empq);
                            $nm=$rw2['EmpFN'] . " " . $rw2['EmpLN'];

                            $sql = "SELECT * FROM empprofiles where EmpID='$rw[SenderID]'";
                            $empq=mysqli_query($con, $sql);
                            $rw2=mysqli_fetch_array($empq);

                              $prfpath="background-image: url('" . $rw2['EmpPPath'] ."')";
                          }
                    ?>
                      <a href="#" id="<?php echo $sndid; ?>" class="emp-ms row">
                
                        <div class="img-s col-lg-4 <?php echo $rw2['EmpPPath']; ?>"  >
                        <div style="<?php 
                   if(file_exists($rw2['EmpPPath']))
                    {
       
                      echo  $prfpath;
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

                                     $newDate = date("F d, Y H:i:s A", strtotime($rw['dateMessage']));
                                     echo $newDate;
                                      ?></i>
                        </div>
                  
                      </a>
                    <?php      
                        } 
                      }
 elseif (isset($_GET['lde201'])){

$ISID=$_SESSION['rid'];
  

          $result = mysqli_query($con,"select * from messageheader where SenderID='$_SESSION[id]' and RecieverID='$ISID'");
          $res = mysqli_fetch_array($result); 
          $cnt= mysqli_num_rows ($result);
          if ($cnt>0){

            $result1 = mysqli_query($con,"select * from messages where MHID='$res[1]' order by DateSent asc");
            $sql="UPDATE messages SET Status=2 where MHID='$res[1]'";
              $sqlq=mysqli_query($con,$sql);
            while($rs=mysqli_fetch_array($result1)){
              if ($rs['SenderID']==$_SESSION['id']){

                $rs3=mysqli_query($con, "select * from empprofiles where EmpID='$rs[SenderID]'");
                $rw3=mysqli_fetch_array($rs3);
                ?>
              
                  <div class="s-message" >
                   <i><?php 
                                $dd = date("FdY", strtotime($rs['DateSent']));  
                                $td = date("FdY");
                                if ($dd==$td){
                                $newDate = date("h:i:s A", strtotime($rs['DateSent']));
                                $newDate = "Today " . $newDate;
                                }else{
                                  $newDate = date("F d, Y H:i:s A", strtotime($rs['DateSent']));
                                }
                                               echo $newDate;
                                                ?></i><p><?php echo $rs['Message']; ?></p>
                            
                      
                  </div>
              <?php 
              }
              else{
                $rs3=mysqli_query($con, "select * from empprofiles where EmpID='$rs[SenderID]'");
                $rw3=mysqli_fetch_array($rs3);
            ?>
              <div class="r-message">
           
                 
                      <p><?php echo $rs['Message']; ?></p><i><?php 

                                               $dd = date("FdY", strtotime($rs['DateSent'])); 
                                $td = date("FdY");
                                if ($dd==$td){
                                $newDate = date("h:i:s A", strtotime($rs['DateSent']));
                                $newDate = "Today " . $newDate;
                                }else{
                                  $newDate = date("F d, Y H:i:s A", strtotime($rs['DateSent']));
                                }
                                               echo $newDate;
                                                ?></i> 
            
                  </div>
            <?php   
              }
            
            } 
          }else{
            $result = mysqli_query($con,"select * from messageheader where RecieverID='$_SESSION[id]' and SenderID='$ISID'");
            $res = mysqli_fetch_array($result); 
            $cnt= mysqli_num_rows ($result);
            if ($cnt>0){
              $result1 = mysqli_query($con,"select * from messages where MHID='$res[1]' order by DateSent asc");

              $sql="UPDATE messages SET Status=2 where MHID='$res[1]'";
              $sqlq=mysqli_query($con,$sql);


              while($rs=mysqli_fetch_array($result1)){
              if ($rs['SenderID']==$_SESSION['id']){
                $rs3=mysqli_query($con, "select * from empprofiles where EmpID='$rs[SenderID]'");
                $rw3=mysqli_fetch_array($rs3);
                ?>
              
                  <div class="s-message">
                      <i><?php 

                                               $dd = date("FdY", strtotime($rs['DateSent'])); 
                                $td = date("FdY");
                                if ($dd==$td){
                                $newDate = date("h:i:s A", strtotime($rs['DateSent']));
                                $newDate = "Today " . $newDate;
                                }else{
                                  $newDate = date("F d, Y H:i:s A", strtotime($rs['DateSent']));
                                }
                                               echo $newDate;
                                                ?></i><p><?php echo $rs['Message']; ?></p>
                                              
                  </div>
              <?php 
              }
              else{
                $rs3=mysqli_query($con, "select * from empprofiles where EmpID='$rs[SenderID]'");
                $rw3=mysqli_fetch_array($rs3);
            ?>
              <div class="r-message">
            
                      <p><?php echo $rs['Message']; ?></p><i><?php 

                                               $dd = date("FdY", strtotime($rs['DateSent'])); 
                                $td = date("FdY");
                                if ($dd==$td){
                                $newDate = date("h:i:s A", strtotime($rs['DateSent']));
                                $newDate = "Today " . $newDate;
                                }else{
                                  $newDate = date("F d, Y H:i:s A", strtotime($rs['DateSent']));
                                }
                                               echo $newDate;
                                                ?></i> 
                  </div>
            <?php   
              }
            }
            }else{

            echo "Message First";
            }
          }
        
                      }
                    ?>