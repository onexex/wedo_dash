<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include_once ("w_conn.php");
 date_default_timezone_set("Asia/Manila");
$q = $_GET['q'];
$_SESSION['rid']=$q;
$result = mysqli_query($con,"select * from messageheader where SenderID='$_SESSION[id]' and RecieverID='$_GET[q]'");
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
		
        <div class="msg-s " >
        	<div class="dvs"><i><?php 
        							$dd = date("FdY", strtotime($rs['DateSent']));	
        							$td = date("FdY");
        							if ($dd==$td){
									  	$newDate = date("h:i:s A", strtotime($rs['DateSent']));
									  	$newDate = "Today " . $newDate;
        							}else{
	        							$newDate = date("F d, Y h:i:s A", strtotime($rs['DateSent']));
        							}
                                     echo $newDate;
                                      ?></i><p><?php echo $rs['Message']; ?></p>
                                      <img src="<?php if (file_exists($rw3['EmpPPath'])){  
                                      	echo $rw3['EmpPPath']; 
                                      }else{  
                                      	 if ($rw3['EmpGender']=="Male"){
                                                    echo "assets/images/profiles/man_d.jpg";
                                            }else{
                                                    echo "assets/images/profiles/woman_d.jpg";
                                            }
                                      } 
                                      	?>">
                                  </div>
            
        </div>
		<?php	
		}
		else{
			$rs3=mysqli_query($con, "select * from empprofiles where EmpID='$rs[SenderID]'");
			$rw3=mysqli_fetch_array($rs3);
	?>
		<div class="msg-r">
			<div>
				<img src="<?php if (file_exists($rw3['EmpPPath'])){  
                                      	echo $rw3['EmpPPath']; 
                                      }else{  
                                      	                                        if ($rw3['EmpGender']=="Male"){
                                                    echo "assets/images/profiles/man_d.jpg";
                                            }else{
                                                    echo "assets/images/profiles/woman_d.jpg";
                                            }
                                      } 
                                      	?>">
            <p><?php echo $rs['Message']; ?></p><i><?php 

                                     $dd = date("FdY", strtotime($rs['DateSent']));	
        							$td = date("FdY");
        							if ($dd==$td){
									  	$newDate = date("h:i:s A", strtotime($rs['DateSent']));
									  	$newDate = "Today " . $newDate;
        							}else{
	        							$newDate = date("F d, Y h:i:s A", strtotime($rs['DateSent']));
        							}
                                     echo $newDate;
                                      ?></i> 
        </div>	
        </div>
	<?php		
		}
	
	} 
}else{
	$result = mysqli_query($con,"select * from messageheader where RecieverID='$_SESSION[id]' and SenderID='$_GET[q]'");
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
		
        <div class="msg-s">
            <i><?php 

                                     $dd = date("FdY", strtotime($rs['DateSent']));	
        							$td = date("FdY");
        							if ($dd==$td){
									  	$newDate = date("h:i:s A", strtotime($rs['DateSent']));
									  	$newDate = "Today " . $newDate;
        							}else{
	        							$newDate = date("F d, Y h:i:s A", strtotime($rs['DateSent']));
        							}
                                     echo $newDate;
                                      ?></i><p><?php echo $rs['Message']; ?></p>
                                      <img src="<?php if (file_exists($rw3['EmpPPath'])){  
                                      	echo $rw3['EmpPPath']; 
                                      }else{  
                                      	                                        if ($rw3['EmpGender']=="Male"){
                                                    echo "assets/images/profiles/man_d.jpg";
                                            }else{
                                                    echo "assets/images/profiles/woman_d.jpg";
                                            }
                                      } 
                                      	?>">
        </div>
		<?php	
		}
		else{
			$rs3=mysqli_query($con, "select * from empprofiles where EmpID='$rs[SenderID]'");
			$rw3=mysqli_fetch_array($rs3);
	?>
		<div class="msg-r">
			<img src="<?php if (file_exists($rw3['EmpPPath'])){  
                                      	echo $rw3['EmpPPath']; 
                                      }else{  
                                      	                                        if ($rw3['EmpGender']=="Male"){
                                                    echo "assets/images/profiles/man_d.jpg";
                                            }else{
                                                    echo "assets/images/profiles/woman_d.jpg";
                                            }
                                      } 
                                      	?>">
            <p><?php echo $rs['Message']; ?></p><i><?php 

                                     $dd = date("FdY", strtotime($rs['DateSent']));	
        							$td = date("FdY");
        							if ($dd==$td){
									  	$newDate = date("h:i:s A", strtotime($rs['DateSent']));
									  	$newDate = "Today " . $newDate;
        							}else{
	        							$newDate = date("F d, Y h:i:s A", strtotime($rs['DateSent']));
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
?>