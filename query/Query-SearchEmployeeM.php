 <?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login'); }

  include_once ("w_conn.php");
                        $srch="select * from messageheader where SenderID='$_SESSION[id]' or RecieverID='$_SESSION[id]' order by dateMessage desc";
                        $qry=mysqli_query($con, $srch);
                     
                        $cnt= mysqli_num_rows ($qry);
                        while ($rw=mysqli_fetch_array($qry)){
                          if ($rw['SenderID']==$_SESSION['id']){
                            $sndid=$rw['RecieverID'];

                            $sql = "SELECT * FROM Employees where EmpID='$rw[RecieverID]'";
                            $empq=mysqli_query($con, $sql);
                            $rw2=mysqli_fetch_array($empq);
                            $nm=$rw2['EmpFN'] . " " . $rw2['EmpLN'];

                            $sql = "SELECT * FROM empprofiles where EmpID='$rw[RecieverID]'";
                            $empq=mysqli_query($con, $sql);
                            $rw2=mysqli_fetch_array($empq);

                              $prfpath="background-image: url('" . $rw2['EmpPPath'] ."')";

                          }else{
                            $sndid=$rw['SenderID'];
                            $sql = "SELECT * FROM Employees where EmpID='$rw[SenderID]'";
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
                
                        <div class="img-s col-lg-4"> 
                           <div style="<?php if (file_exists($prfpath)){  
                                        echo $prfpath; 
                                      }else{  
                                                                                if ($rw3['EmpGender']=="Male"){
                                                    echo "assets/images/profiles/man_d.jpg";
                                            }else{
                                                    echo "assets/images/profiles/woman_d.jpg";
                                            }
                                      } 
                                        ?>" class="img-s">
                          
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