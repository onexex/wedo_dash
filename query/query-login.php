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

/* --------------------------------------------------------------------------
   Brute-force protection (includes/bot-guard.php):
     - 5 wrong passwords for a username / 50 failed sign-ins from one address
       in 15 minutes -> "429|<minutes>|<seconds>|<user or ip>" until the window
       passes (the page turns the Sign in button off with a countdown)
     - a wrong password answers "1|<attempts left>" so the page can warn
       before the lock; the one that uses up the last attempt answers 429 at once
     - Cloudflare Turnstile is checked on the server when its keys are set
       (config.local.php turnstile_site / turnstile_secret) -> "428" if not passed
     - an unknown username and a wrong password get the same answer ("1")
       and take the same time, so accounts can't be discovered
   -------------------------------------------------------------------------- */
require_once __DIR__ . '/../includes/bot-guard.php';
require_once __DIR__ . '/../includes/account-gate.php';
$lgIp   = wd_client_ip();
$lgUser = (string) ($_POST['uname'] ?? '');
$lgPass = (string) ($_POST['pass'] ?? '');
$lgLock = wd_login_lock($pdo, $lgUser, $lgIp);
// locked: refused before any password is checked (nothing to guess),
// "429|<minutes>|<seconds>|user" (this account) or "…|ip" (this network, any account)
if ($lgLock['wait'] > 0) {
    echo '429|' . (int) ceil($lgLock['wait'] / 60) . '|' . (int) $lgLock['wait'] . '|' . $lgLock['scope'];
    exit;
}
// a wrong password (or unknown username — same answer): how many tries are left,
// or the lock itself when that was the last one
function wd_login_wrong(PDO $pdo, string $user, string $ip): string
{
    $lock = wd_login_lock($pdo, $user, $ip);
    if ($lock['wait'] > 0) {
        return '429|' . (int) ceil($lock['wait'] / 60) . '|' . (int) $lock['wait'] . '|' . $lock['scope'];
    }
    return '1|' . wd_login_user_left($pdo, $user);
}
if (wd_turnstile_check($_POST['cf-turnstile-response'] ?? '', $lgIp) === 'bad') {
    wd_login_record($pdo, $lgUser, $lgIp, false);
    echo '428';
    exit;
}

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
            	// same answer and the same work as a wrong password: usernames can't be probed
            	wd_login_dummy_verify($lgPass);
            	wd_login_record($pdo, $lgUser, $lgIp, false);
            	print wd_login_wrong($pdo, $lgUser, $lgIp);
            	session_destroy();
            ?>
            
            <?php
            }else{
                
            	$hash = $row['EmpPW'];
            
            	if (password_verify($_POST['pass'], $hash)) {
            	    $id=$row['EmpID'];
            	    wd_login_record($pdo, $lgUser, $lgIp, true);
            	    // a new session id at sign-in (no session fixation)
            	    session_regenerate_id(true);
            	    // still on a password WeDo hands out (enrolment / old reset): must set their own now
            	    if (in_array(strtolower($lgPass), WD_DEFAULT_PASSWORDS, true)) { wd_set_must_change_password($pdo, $id, true); }
            	    // resigned / terminated (unless allowed after separation): "no longer active"
            	    if (wd_account_blocked($pdo, $id)) {
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
                        // first sign-in of the day posts the Corner birthday greetings (includes/corner-lib.php);
                        // never let it block a sign-in
                        require_once __DIR__ . '/../includes/corner-lib.php';
                        try { wd_post_birthday_greetings($pdo); } catch (Throwable $e) { error_log('[wedo birthday] ' . $e->getMessage()); }
                       

            	          $_SESSION['quesID']=$row['EmpID'];
                    	    // secure remember-me: random 256-bit token; only its hash is stored server-side
                    	    $rtoken = bin2hex(random_bytes(32));
                            $stmtRemember = $pdo->prepare("UPDATE empdetails SET remember_hash=:h, remember_expiry=:e WHERE EmpID=:id");
                            $stmtRemember->execute([':h'=>password_hash($rtoken, PASSWORD_DEFAULT), ':e'=>date('Y-m-d H:i:s', time()+28800), ':id'=>$row['EmpID']]);
                            setcookie("WeDoID", $rtoken, ['expires'=>time()+28800,'path'=>'/','httponly'=>true,'samesite'=>'Lax','secure'=>(!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS'])!=='off')]);
                            // the mobile app stays signed in for 30 days with its own per-phone token
                            require_once __DIR__ . '/../includes/app-remember.php';
                            if (app_is_app()) { app_remember_issue($pdo, $row['EmpID']); }
                            
                            
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
            	    wd_login_record($pdo, $lgUser, $lgIp, false);
            	    echo wd_login_wrong($pdo, $lgUser, $lgIp);
            	}
            
             	?>
            	<?php
                  // $q = $_SESSION['id'];
            }

?>