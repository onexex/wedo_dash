<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){

  }
  else{ header ('location: login.php'); }


		include 'w_conn.php';
		try{
		$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		   }
		catch(PDOException $e)
		   {
		die("ERROR: Could not connect. " . $e->getMessage());
		   }
//alas
$id=$_SESSION['id'];
if (isset($_GET['alash']))
{
	?>
	        				<tbody id="tbalas">
	        					<?php
                                 try{
								  $statement = $pdo->prepare("SELECT a.LStatus as LST,a.FID as IDL,LeaveDesc,a.LFDate as FD,a.LStart as LS,a.LEnd as LE,a.LDuration as dur,a.LPurpose as LP,StatusDesc   from hleavesbd as a 
								    -- INNER JOIN hleaves on a.FID=hleaves.LeaveID
                                  INNER JOIN status as b on a.LStatus=b.StatusID 
                                  -- INNER JOIN leaves_validation c ON c.sid=a.LType 
                                  INNER JOIN leaves as d on a.LType=d.LeaveID where a.EmpID=:id and a.LStart BETWEEN '" . $_GET['dfrom'] . "' AND '" . $_GET['dto'] ."' and a.LStatus<>7 order by a.LStart desc ");
								  $statement->bindParam(':id' , $id);
								  $statement->execute();
								while ($row21 = $statement->fetch())
								{
        					?>

        					 <tr>
        					 <td><?php echo $row21['LeaveDesc']; ?></td>	
        					  <td><?php echo date("F j, Y", strtotime($row21['FD'])); ?></td>
        					 <td><?php echo date("F j, Y", strtotime($row21['LS'])); ?></td>
        					 <td><?php echo date("F j, Y", strtotime($row21['LE'])); ?></td>	
        					 <td><?php echo $row21['dur'] ?></td>
        					 <td><?php echo $row21['LP']; ?></td>
        					 <td><?php echo $row21['StatusDesc']; ?></td>
        				 <?php
                        if($row21['LST']==1){
                            ?>
                            <td><button type="button" class="btn btn-danger" data-toggle="modal" data-target="#myModalob<?php echo $row21['IDL']; ?>"><i class="fa fa-trash" aria-hidden="true"></i></button> </td>
                              <?php
                                }else{
                                    
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
                            }catch(Exception $e) {
                              echo 'Message: ' .$e->getMessage();
                            }
							?>
        				</tbody>
        				<?php 
}


if (isset($_GET['obh']))
{
	?>
	         <tbody id="tbob">
            <?php
                  $statement = $pdo->prepare("SELECT * from obs as a INNER JOIN status as b on a.OBStatus=b.StatusID  
                                  where a.EmpID=:id and a.OBStatus<>7 and  (a.OBDateFrom BETWEEN '" . $_GET['dfrom'] . "' AND '" . $_GET['dto'] ."' or a.OBDateTo BETWEEN '" . $_GET['dfrom'] . "' AND '" . $_GET['dto'] ."') order by a.OBInputDate desc");

                  $statement->bindParam(':id' , $id);
                  $statement->execute();
                while ($row21 = $statement->fetch())
                {
                  ?>
                   <tr>
                   <td><?php  echo date("F j, Y", strtotime($row21['OBFD'])); ?></td>  
                   <td><?php  echo date("F j, Y", strtotime($row21['OBDateFrom'])); ?></td>
                   <td><?php  echo date("F j, Y", strtotime($row21['OBDateTo'])); ?></td>
                   <td><?php echo date("h:i:s A", strtotime($row21['OBTimeFrom']));   ?></td> 
                   <td><?php echo date("h:i:s A", strtotime( $row21['OBTimeTo'])); ?></td>
                   <td><?php echo $row21['OBIFrom']; ?></td>
                   <td><?php echo $row21['OBITo']; ?></td>
                   <td><?php echo $row21['OBPurpose']; ?></td>
                     <td><?php echo $row21['OBCAAmt']; ?></td>
                     <td><?php echo $row21['OBCAPurpose']; ?></td>
                   <td><?php echo $row21['StatusDesc']; ?></td>
                 <?php
                        if($row21['OBStatus']==1){
                    ?>
                    <td><button type="button" class="btn btn-danger" data-toggle="modal" data-target="#myModalob<?php echo $row21['OBID']; ?>"><i class="fa fa-trash" aria-hidden="true"></i></button> </td>
                      <?php
                        }else{
                            
                        }
                        ?>
                  </tr> 
                      
                   <!-- The Modal -->
                    <div class="modal ob-viewdel" id="myModalob<?php echo $row21['OBID']; ?>">
                      <div class="modal-dialog">
                        <div class="modal-content">
                    
                          <!-- Modal Header -->
                          <div class="modal-header">
                            <h4 class="modal-title">Are you sure you want to remove this ??</h4>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                          </div>
                    
                          <!-- Modal body -->
                          <div class="modal-body">
                              <button type="button" id="<?php echo $row21['OBID']; ?>" class="btn btn-success ys_ob">Yes</button> 
                               <button type="button" class="btn btn-danger" data-dismiss="modal">No</button>
                          </div>
                    
                          <!-- Modal footer -->
                      
                    
                        </div>
                      </div>
                    </div>
              <?php 
              }

              ?>


    </tbody>
    <?php
}

if (isset($_GET['oth']))
{
	?>
	      
	         	<?php
                try{
            $date1=date('Y-m-d', strtotime($_POST['dtto']  . ' + 1 days'));
            $statement = $pdo->prepare("SELECT * from otattendancelog as a 
                                  INNER JOIN status as b on a.Status=b.StatusID  
                                  where a.EmpID=:id and TimeIn between :dt1 and :dt2 and a.Status<>7 order by a.DateTimeInputed DESC");

                  $statement->bindParam(':id' , $id);
                   $statement->bindParam(':dt1' , $_POST['dtfrom']);
                    $statement->bindParam(':dt2' , $date1);
                  $statement->execute();
                  $cnt=0;
                while ($row21 = $statement->fetch())
                {
                	$cnt=$cnt+1;
                  ?>
                   <tr>
                   <td><?php echo $cnt ?></td>  
                   <td><?php echo date("F j, Y h:i:s A", strtotime($row21['DateTimeInputed']));  ?></td>
                   <td><?php echo date("F j, Y h:i:s A", strtotime($row21['TimeIn'])); ?></td>
                   <td><?php echo date("F j, Y h:i:s A", strtotime($row21['TimeOut'])); ?></td> 
  				        <td><?php echo $row21['Purpose']; ?></td>
                   <td><?php echo $row21['Duration']; ?></td>
                   <td><?php echo $row21['StatusDesc']; ?></td>
                 <?php
                        if($row21['Status']==1){
                    ?>
                    <td><button type="button" value="<?php echo $row21['OTLOGID']; ?>" id="delot" class="btn btn-danger" ><i class="fa fa-trash" aria-hidden="true"></i></button> </td>
                   <?php
                        }else{
                            
                        }
                        ?>
                  </tr> 
                   <!-- The Modal -->
                    <div class="modal ob-viewdel" id="myModalob<?php echo $row21['OTLOGID']; ?>">
                      <div class="modal-dialog">
                        <div class="modal-content">
                    
                          <!-- Modal Header -->
                          <div class="modal-header">
                            <h4 class="modal-title">Are you sure you want to remove this ??</h4>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                          </div>
                    
                          <!-- Modal body -->
                          <div class="modal-body">
                              <button type="button" id="<?php echo $row21['OTLOGID']; ?>" class="btn btn-success ys_ot">Yes</button> 
                               <button type="button" class="btn btn-danger" data-dismiss="modal">No</button>
                          </div>
                    
                          <!-- Modal footer -->
                      
                    
                        </div>
                      </div>
                    </div>
              <?php 
              }
                }
                    catch(Exception $e)
                           {
                        echo ("ERROR: Could not connect. " . $e->getMessage());
                          }
              ?>


  
    <?php
}

if (isset($_GET['eoh']))
{
	?>
	        <tbody id="tbeodata">
                  <?php   
                  $id=$_SESSION['id'];
                  $statement = $pdo->prepare("SELECT * from earlyout as a INNER JOIN status as b on a.status=b.StatusID  where a.EmpID=:id and a.status<>7 and a.DFile BETWEEN '" . $_GET['dfrom'] . "' AND '" . $_GET['dto'] ."' order by a.DateTimeInputed desc");
                  $statement->bindParam(':id' , $id);
                  $statement->execute();
                while ($row21 = $statement->fetch())
                {
                  ?>
                   <tr>
                   <td><?php echo $row21['Purpose']; ?></td>
                   <td><?php echo $row21['DFile']; ?></td>  
                   <td><?php echo $row21['StatusDesc']; ?></td>
                    <td><?php echo $row21['DateTimeInputed']; ?></td>
                      <?php
                        if($row21['Status']==1){
                    ?>
                         <td><button type="button" class="btn btn-danger" data-toggle="modal" data-target="#myModalw<?php echo $row21['SID']; ?>"><i class="fa fa-trash" aria-hidden="true"></i></button> </td>    
                           <!-- The Modal -->
                    <div class="modal eo-delete" id="myModalw<?php echo $row21['SID']; ?>">
                      <div class="modal-dialog">
                        <div class="modal-content">
                    
                          <!-- Modal Header -->
                          <div class="modal-header">
                            <h4 class="modal-title">Are you sure you want to remove this ??</h4>
                            <button type="button" class="close" data-dismiss="modal">&times;</button>
                          </div>
                    
                          <!-- Modal body -->
                          <div class="modal-body">
                              <button type="button" id="<?php echo $row21['SID']; ?>"  class="btn btn-success ys_eo">Yes</button> 
                               <button type="button" class="btn btn-danger" data-dismiss="modal">No</button>
                          </div>
                    
                          <!-- Modal footer -->
                       
                        </div>
                      </div>
                    </div>
                    <?php
                        }else{
                            
                        }
                    ?>
                    
                       
                
                  </tr> 
              <?php 
              }

              ?>


    </tbody>
    <?php
}


if (isset($_GET['sob']))
{
	?>
	        <tbody id="tbsob">
      <?php
                

                 
                   if ($_SESSION['UserType']==1){
                         $statement = $pdo->prepare("SELECT * from employees inner join  obs as a on employees.EmpID=a.EmpID  INNER JOIN status as b on a.OBStatus=b.StatusID
                                  where  OBDateFrom between :dt1 and :dt2 and OBStatus=4 order by OBDateFrom desc");
                      }else{
                         $statement = $pdo->prepare("SELECT * from employees INNER JOIN empdetails on employees.EmpID=empdetails.EmpID inner join  obs as a on empdetails.EmpID=a.EmpID 
                                  INNER JOIN status as b on a.OBStatus=b.StatusID
                                  where empdetails.EmpISID=:id and OBDateFrom between :dt1 and :dt2 and a.OBType=2 order by OBDateFrom desc");
                  $statement->bindParam(':id' , $id);
                      }
                  $statement->bindParam(':dt1' , $_GET['dfrom']);
                  $statement->bindParam(':dt2' , $_GET['dto']);
                  $statement->execute();
                while ($row21 = $statement->fetch())
                {
                  ?>
                   <tr>
                   <td><?php echo date("F j, Y", strtotime($row21['OBFD'])); ?></td>  
                   <td><?php echo $row21['EmpLN']; ?></td>
                   <td><?php echo date("F j, Y", strtotime( $row21['OBDateFrom'])); ?></td>
                   <td><?php echo date("F j, Y", strtotime( $row21['OBDateTo'])); ?></td>
                   <td><?php echo $row21['OBITo']; ?></td>
                   <td><?php echo $row21['OBPurpose']; ?></td>
                   <td><?php echo $row21['OBCAAmt']; ?></td>
                   <td><?php echo $row21['StatusDesc']; ?></td>
                
                  </tr> 
              <?php 
              }

              ?>


    </tbody>
    <?php
}
?>