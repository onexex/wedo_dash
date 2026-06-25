<?php
// server should keep session data for AT LEAST 1 hour
ini_set('session.gc_maxlifetime', 3600);

// each client should remember their session id for EXACTLY 1 hour
session_set_cookie_params(3600);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
include 'w_conn.php';
date_default_timezone_set("Asia/Manila");
try{
    $customTime = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('P');
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET time_zone='$customTime';");
   }
catch(PDOException $e)
   {
die("ERROR: Could not connect. " . $e->getMessage());
   }
// date_default_timezone_set("Asia/Manila");
//captcha
{
    // function CheckCaptcha($userResponse) {
    //         $fields_string = '';
    //         $fields = array(
    //             'secret' => '6LcxV-kUAAAAANKzoGx52jWLfAi2SXip4VA9k4rA',
    //             'response' => $userResponse
    //         );
    //         foreach($fields as $key=>$value)
    //         $fields_string .= $key . '=' . $value . '&';
    //         $fields_string = rtrim($fields_string, '&');

    //         $ch = curl_init();
    //         curl_setopt($ch, CURLOPT_URL, 'https://www.google.com/recaptcha/api/siteverify');
    //         curl_setopt($ch, CURLOPT_POST, count($fields));
    //         curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
    //         curl_setopt($ch, CURLOPT_RETURNTRANSFER, True);

    //         $res = curl_exec($ch);
    //         curl_close($ch);

    //         return json_decode($res, true);
    //     }


    //     // Call the function CheckCaptcha
    //     $result = CheckCaptcha($_POST['g-recaptcha-response']);

    //     if ($result['success']) {
    //         //If the user has checked the Captcha box
            
        
    //     } else {
    //         // If the CAPTCHA box wasn't checked
    //          echo '5';
    //     }
}

            $statement = $pdo->prepare("select * from empdetails where EmpUN = :un");
            $statement->bindParam(':un' , $_POST['uname']);
            $statement->execute(); 
            
            $count=$statement->rowCount();
            $row=$statement->fetch();
            if ($count<1){
            	print "0";
            	session_destroy();
            ?>
            
            <?php
            }else{
                
            	$hash = $row['EmpPW'];
            
            	if (password_verify($_POST['pass'], $hash)) {
            	    $id=$row['EmpID'];
            	    $stmt = $pdo->prepare("select * from employees where EmpID = :id and EmpStatusID=2");
                    $stmt->bindParam(':id' , $id);
                    $stmt->execute(); 
                    $scount=$stmt->rowCount();
            	    
            	    if($scount>0){
            	        echo '0101';
            	        return;
            	    }
            	   
            	    $todayMonth = date("m");
            	    $todayYear = date("Y");
            	    $todayDay = date("d");
            	    $YS="Answered YES to the questionnaire";
            	    $statement = $pdo->prepare("select * from dars where (month(DarDateTime)=:tdymnth and year(DarDateTime)=:tdyyear and day(DarDateTime)=:tdyday) and EmpActivity=:pw and EmpID=:eid");
            		$statement->bindParam(':pw' , $YS);
            		$statement->bindParam(':eid' , $row['EmpID']);
            		$statement->bindParam(':tdymnth' , $todayMonth);
            		$statement->bindParam(':tdyyear' , $todayYear);
            		$statement->bindParam(':tdyday' , $todayDay);
            		$statement->execute(); 
            	    $rwscount=$statement->rowCount();
            	    if ($rwscount>100){//change this to > 1 if you want to activate the questionnaire
            	        //for quest
                        {
                            // if ($_POST['uname']=="0002" || $_POST['uname']=="0003"){
                                        $_SESSION['quesID']=$row['EmpID'];
                                        echo 7;
                            //         }else{
                            //             	$_SESSION['id']=$row['EmpID'];
                            // 		$_SESSION['UserType']=$row['EmpRoleID'];
                            // 		$cid=$row['EmpCompID'];
                            // 		$_SESSION['CompID']=$row['EmpCompID'];
                            // 		$_SESSION['EmpISID']=$row['EmpISID'];
                            // 		$statement = $pdo->prepare("select * from companies where CompanyID = :pw");
                            // 		$statement->bindParam(':pw' , $cid);
                            // 		$statement->execute(); 
                            // 		$comcount=$statement->rowCount();
                            // 		$row=$statement->fetch();
                            // 		if ($comcount>0){
                            // 			$_SESSION['CompanyName']=$row['CompanyDesc'];
                            // 			$_SESSION['CompanyLogo']=$row['logopath'];
                            // 			$_SESSION['CompanyColor']=$row['comcolor'];
                            // 		}else{
                            // 			$_SESSION['CompanyName']="ADMIN";
                            // 			$_SESSION['CompanyLogo']="";
                            // 			$_SESSION['CompanyColor']="red";
                            // 		}
                            // 		$_SESSION['PassHash']=$hash;
                            
                                    
                            //             echo '123123';
                            //         }
                        }
                       
            	    }else{
                        $today = date("Y-m-d");
                        //validate the tblsysLog for daily record
                        $stmtValDayLog = $pdo->prepare("select * from tblsyslog where datecheck = '$today' ");
                        $stmtValDayLog->execute(); 
                        $varCountDayLog=$stmtValDayLog->rowCount();
                        $varRowDayLog=$stmtValDayLog->fetch();

                            //get the date 
                            //if have no action
                        if( $varCountDayLog > 0){
                            //no action
                        }else{
                            //if no record select for posible birthday in 
                            // $getEmployeeBday = $pdo->prepare("SELECT * FROM empprofiles WHERE DATE_FORMAT(EmpDOB,'%m-%d') = DATE_FORMAT(NOW(),'%m-%d')OR ((DATE_FORMAT(NOW(),'%Y') % 4 <> 0 
                            // OR (DATE_FORMAT(NOW(),'%Y') % 100 = 0 AND DATE_FORMAT(NOW(),'%Y') % 400 <> 0) )AND DATE_FORMAT(NOW(),'%m-%d') = '03-01'AND DATE_FORMAT(EmpDOB,'%m-%d') = '02-29');");
                            // $getEmployeeBday->execute(); 
                            // $varCountemployee=$getEmployeeBday->rowCount();
                            // $varEmloyeebday=$getEmployeeBday->fetch();
                             //if no record select for posible birthday in 
                            $getEmployeeBday = $pdo->prepare("SELECT * FROM empprofiles INNER JOIN employees ON empprofiles.EmpID=employees.EmpID WHERE EmpStatusID=1  AND DATE_FORMAT(EmpDOB,'%m-%d') = DATE_FORMAT(NOW(),'%m-%d')OR ((DATE_FORMAT(NOW(),'%Y') % 4 <> 0 
                            OR (DATE_FORMAT(NOW(),'%Y') % 100 = 0 AND DATE_FORMAT(NOW(),'%Y') % 400 <> 0) )AND DATE_FORMAT(NOW(),'%m-%d') = '03-01'AND DATE_FORMAT(EmpDOB,'%m-%d') = '02-29');");
                            $getEmployeeBday->execute(); 
                            $varCountemployee=$getEmployeeBday->rowCount();

                            $employeeData=[];
                            if( $varCountemployee>=1){
                               while ( $varEmloyeebday=$getEmployeeBday->fetch()) {
                                    //get employee
                                    $idemp = $varEmloyeebday['EmpID'];
                                    echo $idemp ;
                                    $stmtEmp = $pdo->prepare("select * from employees where EmpID = '$idemp' ");
                                    $stmtEmp->execute(); 
                                    $empRowcount=$stmtEmp->rowCount();
                                    $empRow=$stmtEmp->fetch();

                                    //create announcement 
                                    $todayDT = date("Y-m-d H:i:s");
                                    $creatorName='admin';
                                    $ann='Announcement';
                                    $message= ' Happy Birthday '. $empRow['EmpFN'] . "  ". $empRow['EmpLN'] . ' '. " <i class='fa fa-birthday-cake'></i>"  ;
                                    $sql = "INSERT INTO announcements (EmpID,Title,ADesc,ADate) 
                                       VALUES (:id,:AnnT,:announ,:dtte)";
                                        $stmt = $pdo->prepare($sql);
                                        $stmt->bindParam(':id' ,$creatorName);
                                        $stmt->bindParam(':AnnT' ,$ann);
                                        $stmt->bindParam(':announ' ,$message);
                                        $stmt->bindParam(':dtte' ,$todayDT);
                                        $stmt->execute(); 
                                }
                                //insert logs validator
                                $todaylogs = date("Y-m-d");
                                $todaylogsLong = date("Y-m-d");
                                $sql = "INSERT INTO tblsyslog (datecheck,created_at) 
                                       VALUES (:dc,:ca)";
                                        $stmt = $pdo->prepare($sql);
                                        // $stmt->bindParam(':id' ,$creatorName);
                                        $stmt->bindParam(':dc' ,$todaylogs);
                                        $stmt->bindParam(':ca' ,$todaylogsLong);
                                        $stmt->execute(); 
                            }else{
                                $todaylogs = date("Y-m-d");
                                $todaylogsLong = date("Y-m-d");
                                $sql = "INSERT INTO tblsyslog (datecheck,created_at) 
                                       VALUES (:dc,:ca)";
                                        $stmt = $pdo->prepare($sql);
                                        // $stmt->bindParam(':id' ,$creatorName);
                                        $stmt->bindParam(':dc' ,$todaylogs);
                                        $stmt->bindParam(':ca' ,$todaylogsLong);
                                        $stmt->execute(); 
                            }
                        }
                        //then create announcement 
                        //then insert copy of logs for the day
                        // echo $varCountDayLog;
                       

            	          $_SESSION['quesID']=$row['EmpID'];
                    	    $epass=password_hash($row['EmpID'], PASSWORD_DEFAULT);
                            setcookie("WeDoID",$epass, time()+28800, "/");
                            
                            
                             ###### get the status of user 
            
                             $stmtGetStatus= $pdo->prepare("SELECT * FROM `empdetails` where EmpID = :id");
                             $stmtGetStatus->bindParam(':id' , $row['EmpID']);
                             $stmtGetStatus->execute(); 
                             $rowStat=$stmtGetStatus->fetch();
                             $_SESSION['empstatIDSMON'] = $rowStat['EmpStatID'];

                             ######
                            
                             ###### get the gender for validation on leave 9-11
            
                            $statementGender = $pdo->prepare("SELECT * FROM `empprofiles` where EmpID = :id");
                            $statementGender->bindParam(':id' , $row['EmpID']);
                            $statementGender->execute(); 
                            
                            //$countGender=$statementGender->rowCount();
                            $rowGender=$statementGender->fetch();
                    		$_SESSION['gender'] = $rowGender['EmpGender'];
                            ######
                    	
                    	
                    		$_SESSION['id']=$row['EmpID'];
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
                        	echo "900";
            	    }
            	    
                        //other code
                        {
                             // 		$id=$_SESSION['id'];
                            //         $ch="Logged In to Dashboard";
                            //         $today = date("Y-m-d H:i:s");
                            //         // insert into dars
                            //         $sql = "INSERT INTO dars (EmpID,EmpActivity,DarDateTime) VALUES (:id,:empact,:ddt)";
                            //         $stmt = $pdo->prepare($sql);
                            //         $stmt->bindParam(':id' , $id);
                            //         $stmt->bindParam(':empact', $ch);
                            //         $stmt->bindParam(':ddt', $today);
                            //         $stmt->execute();
                            // 		echo  	'<div class="modal-header">';
                            // 	    echo  	'<h4 class="modal-title">Welcome to ' . $_SESSION['CompanyName'] . ' Dashboard</h4>';
                            // 	    echo 	'<button type="button" class="close" data-dismiss="modal">&times;</button>';
                            // 	    echo    '</div>';
                            // 		echo    '<div class="modal-body ob-body" style="background-color: ' . $_SESSION['CompanyColor'] .'">';
                            // 		echo 	'<img src=' . $_SESSION['CompanyLogo'] . '>';
                            // 	    echo    '</div>';           
                            // 	   	echo  	'<div class="modal-footer">';
                            // 	    echo 	'<button type="button" onclick="fnct()" class="btn btn-success" data-dismiss="modal">Proceed to Dashboard</button>';
                            // 	    echo    '</div>';    
            
                        }

            	} else {
            	    echo '1';
            	}
            
             	?>
            	<?php
                  // $q = $_SESSION['id'];
            }

?>