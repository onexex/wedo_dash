<?php 

//new table for holiday logger tagging

//connection
{
	include 'w_conn.php';if (session_status() === PHP_SESSION_NONE) { session_start(); }
	if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
	else{ header ('location: login.php'); }
  
  date_default_timezone_set("Asia/Manila");
  $today = date("Y-m-d H:i:s");
  
  try{
	  $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
	  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	 }
  
  catch(PDOException $e){
	  die("ERROR: Could not connect. " . $e->getMessage());
	 }
}

//initialized ciinfo global vars
{
	global $ot,$otval,$dtto,$dtfr,$adj2,$ids,$taxcatid,$taxinc,$sss,$ids,$pi,$sil,$sssloan,$piloan,$sln,$Loan,$adjmoney,$RatePerMin1,$incomeTax,$eo,$ob,$leave,$labelval,$adjDatamin;
	$adjDatamin=0;
	$sss=0;
	$ids=0;$pi=0;$sil=0;$sl=0;$tah=0;$tbh=0;$gross=0;$ot=0;$sssloan=0;$piloan=0;$taxinc=0;$inctax=0;$padj=0;$pnp=0;$pr=0;$month13=0;$prevGross=0;$taxdesc;
	$taxcatid=0;
	$incomeTax=0;
	$taxinc=0;
	$prevGross=0;
	$Cstart=0;$Cend=0;$PYDate=0;$Loan=0;$tax=0;$GovDues=0;$SalaryLoan=0;
	$SSEE=0;
	$SSER=0;
	$mfee=0;
	$PHER=0;
	$PIER=0;
	$allowanceAdjusmentMoney=0;
	$afterHoliday=0;

	$ltype = $_POST['vl'];  
	$dtfr  = $_POST['dtfr'];  
	$dtto  = $_POST['dtto'];
	$hr=0;
	$afterHoliday='';
	$holidayDesc='';
	$holidayStatus='';
	$newDDTO = date('Y-m-d', strtotime($dtto . ' + 1 days'));
}

// 8921
{
	$getPayApproved = $pdo->prepare("Select * from payrol where PYDate=:pydate group by PYDate");
	$getPayApproved->bindParam(':pydate' , $ltype);
	$getPayApproved->execute();
	$getrow = $getPayApproved->fetch();
	$count = $getPayApproved->rowCount();
	if($count > 0){
		if($getrow['hrApproved']==1){
			//validate data if approved
			print 10004;
			return;
		}
	}
}

{
	$getPendingHRApproved = $pdo->prepare("Select * from payrol where hrApproved=:hr and PYDate<>:pydate group by PYDate");
	$getPendingHRApproved->bindParam(':pydate' , $ltype);
	$getPendingHRApproved->bindParam(':hr', $hr);
	$getPendingHRApproved->execute();
	$getrow = $getPendingHRApproved->fetch();
	$count = $getPendingHRApproved->rowCount();
	if($count > 0){
		print 10005;
		return;
	
	}
}

//end 8921
//VALIDATE IF PENDING LOGS
{
	$getPendingEO = $pdo->prepare("Select * from earlyout where (DFile BETWEEN :dateFrom and :dateTo) and Status IN ('1','2')");
	$getPendingEO->bindParam(':dateFrom' , $dtfr);
	$getPendingEO->bindParam(':dateTo' , $newDDTO);
	$getPendingEO->execute();		
	$getRowEO = $getPendingEO->fetch();
	$getRowCounteo = $getPendingEO->rowCount();  		
	if($getRowCounteo>0) {
		print 10000;
		return;
	}
}

//validate pending ob
{
	$getPendingOb = $pdo->prepare("Select * from obshbd where (OBDateFrom BETWEEN :dateFrom and :dateTo) and OBStatus IN ('1','2')");
	$getPendingOb->bindParam(':dateFrom' , $dtfr);
	$getPendingOb->bindParam(':dateTo' , $newDDTO);
	$getPendingOb->execute();	
	$getRowOB = $getPendingOb->fetch();
	$getRowCountob = $getPendingOb->rowCount(); 

	if($getRowCountob>0) {
		print 10001;
		return;
	}
}

//validate leavepeding
{
	$getRowpendingL= $pdo->prepare("Select * from hleavesbd where (LStart BETWEEN :dateFrom and :dateTo) and LStatus IN ('1','2') and LType <> 9");
	$getRowpendingL->bindParam(':dateFrom' , $dtfr);
	$getRowpendingL->bindParam(':dateTo' , $dtto);
	$getRowpendingL->execute();	
	$getRowCountL = $getRowpendingL->fetch();
	$getRowCountL = $getRowpendingL->rowCount(); 			

	if($getRowCountL>0) {
		print 10002;
		return;
	}
}

//validate ot pending
{
	$getRowpendingOT= $pdo->prepare("Select * from otattendancelog where (TimeIn BETWEEN :dateFrom and :dateTo) and Status IN ('1','2') ");
	$getRowpendingOT->bindParam(':dateFrom' , $dtfr);
	$getRowpendingOT->bindParam(':dateTo' , $newDDTO);
	$getRowpendingOT->execute();	
	$getRowCountOT = $getRowpendingOT->fetch();
	$getRowCountOT = $getRowpendingOT->rowCount(); 			

	if($getRowCountOT>0) {
		print 10003;
		return;
	}
	
}

$today = date("Y-m-d H:i:s");

//delete 	
{
	$stmtdel = $pdo->prepare("Delete from temp_payroll where Pdate=:pdate");
	$stmtdel->bindParam(':pdate' , $ltype);
	$stmtdel->execute();
	//$rowdel = $stmtdel->fetch();
	$countdel = $stmtdel->rowCount();  

	//delete 	
	$stmtdelp = $pdo->prepare("Delete from payrol where PYDate=:pdate");
	$stmtdelp->bindParam(':pdate' , $ltype);
	$stmtdelp->execute();
	//$rowdel = $stmtdel->fetch();
	$countdelp = $stmtdelp->rowCount();


	$stmtdel = $pdo->prepare("Delete from tblholidaypayroll where pdate=:pdate");
	$stmtdel->bindParam(':pdate' , $ltype);
	$stmtdel->execute();
	//$rowdel = $stmtdel->fetch();
	$countdel = $stmtdel->rowCount();  
}
 
//get the employee info
{
	$statement = $pdo->prepare("Select * from employees as a 
	inner join empdetails as b on a.EmpID = b.EmpID
	inner join empprofiles as f on a.EmpID = f.EmpID
	inner join empdetails2 as c on b.EmpID = c.EmpID
	inner join cal_values as d on b.CatType = d.category 
    where a.EmpStatusID='1' and a.EmpID <> 'admin' and a.EmpID <> 'WeDoinc-003'
    and a.EmpID <> 'WeDoinc-002'
    and a.EmpID <> 'WeDoinc-012'
    and a.EmpID <> 'WeDoinc-006'
    ORDER BY a.EmpLN Asc");
    
    
// 	where a.EmpStatusID='1' and a.EmpID = 'WeDoinc-0010'  ORDER BY a.EmpLN Asc");	
    // where a.EmpStatusID='1' and a.EmpID <> 'admin' and a.EmpID <> 'WeDoinc-003'  ORDER BY a.EmpLN Asc");
// 	where a.EmpStatusID='1' and a.EmpID = 'WeDoinc-0010'  ORDER BY a.EmpLN Asc");

// 	where a.EmpStatusID='1' and a.EmpID = 'WeDoinc-041'  ORDER BY a.EmpLN Asc");

	// where a.EmpStatusID='1' and a.EmpID <> 'WeDoinc-041'  ORDER BY a.EmpLN Asc");
	// WeDoinc-053
	$statement->execute();
	$calload = $statement->rowCount();
} 
        if ($calload==0){ }
    	  	else{
    	  	    
    			while ( $calrow = $statement->fetch()) {
    			    
					//initialized value for insert payroll
						$taxcatid=$calrow['EmpTaxCat'];
    					$dtfrom=$dtfr;
    					$avgDaysyear=$calrow['avgNoDaysYr'];
    					$hrsPerDay=$calrow['HrsPerDay'];
    					$hrsHalf=($hrsPerDay / 2)+1;
						$nolilo=0;
						$updateleave=0;
						$leaveapprove=0;
						$allowancehalf=0;
						$allowancewhole=0;
						//*******employee rates
						$basicwhole=$calrow['EmpBasic'];
						$basichalf=$calrow['EmpBasic']/2;
						$allowancewhole=$calrow['EmpAllowance'];
						$allowancehalf=$calrow['EmpAllowance']/2;
						$perHourPay=$calrow['EmpHRate'];

							//get employee minute rate
							$RatePerMin1=((($basicwhole * 12)/$avgDaysyear)/ $hrsPerDay)/60;
							try{
                					while ( $dtfrom <=$dtto ){	
            							$adjmoney=0;	
                							//validate backend employee
											$ids=$calrow['EmpID'];
											$longdate=date("l",strtotime($dtfrom));
		
											//get the work schedule
											$stmsched = $pdo->prepare("Select * from workdays as a 
																		inner join workschedule as b on a.Schedtime=b.WorkSchedID
																		inner join schedeffectivity as c on a.EFID=c.efids
																		where (((a.empid=:empid) and (a.Day_s=:daylong))
																		and ((:dfrom >= dfrom) and (:dfrom <= dto)) and SchedTime<>0)
																	  ");
																	
											$stmsched->bindParam(':empid' , $ids);
											$stmsched->bindParam(':daylong' , $longdate);
											$stmsched->bindParam(':dfrom' , $dtfrom);
											$stmsched->execute();
											$getsched = $stmsched->fetch();
											$countsched = $stmsched->rowCount();
											if($countsched >0){
												$tfrom = $getsched['TimeFrom'];
												$tto = $getsched['TimeTo'];
											}
											
            							if ($calrow['EmpStatID']==1){	 
												$haseo=0;
												$OBblhours=0;

												//check eo
												$stmteo = $pdo->prepare("Select * from earlyout where DFile=:datedtfr and EmpID=:id and Status='4'");
												$stmteo->bindParam(':datedtfr' , $dtfrom);
												$stmteo->bindParam(':id' , $ids);
												$stmteo->execute();								        
												$countlilo = $stmteo->rowCount();
													if($countlilo > 0 ){																 
															$liloadj=0;
															$haseo=1;
													}else{}	
													
            									if(($countsched == 0)){
													//loop through OT	// $otval=0;
													$otDay2=date("Y-m-d", strtotime($dtfrom . " + 1 days"));  
													//7/182023 rbg
													$sql = "SELECT * FROM otattendancelog where EmpID=:id and (TimeIn BETWEEN :d1 and :d2) order by OTLOGID desc";
													$stmt = $pdo->prepare($sql);
													$stmt->bindParam(':id' , $ids);
													$stmt->bindParam(':d1' , $dtfrom);
													$stmt->bindParam(':d2' , $otDay2);
													$stmt->execute();
													$countOT = $stmt->rowCount();
            									
            										//loop through atteandance- adjusment
            											$stmlilo = $pdo->prepare("Select * from attendancelog where EmpID=:id and WSFrom=:datedtfr");
            											$stmlilo->bindParam(':datedtfr' , $dtfrom);
            											$stmlilo->bindParam(':id' , $ids);
            											$stmlilo->execute();
            											$getlilo = $stmlilo->fetch();
            											$countlilo = $stmlilo->rowCount();
            											$liloadj=0;
        												if($countlilo > 0){
        														//insert adjustment														
        														//$liloadj=$getlilo['MinsLack2'];
        														$liloadj=0;
        														$TypeOff="Attendance";
        														$adjmoney=($liloadj*60)*$RatePerMin1;
        														insertdata($pdo,$ids,$TypeOff,$liloadj,$dtfrom,$ltype,$today,$adjmoney);
        														//echo $dtfrom. "-".$longdate. "-".  $liloadj."\n" ;
    													}elseif($countOT>0){
															$rowdataOT = $stmt->fetch();
															if($rowdataOT['Status']==4){
																$TypeOff="OT Paid";
																$adjmoney=0;
    																insertdata($pdo,$ids,$TypeOff,$liloadj,$dtfrom,$ltype,$today,$adjmoney);
															}else{
															    if($rowdataOT['Status']==2){
															           $TypeOff="OT Not Paid - Pending HR Approval";
																        $adjmoney=0;
															    }elseif($rowdataOT['Status']==1){
															        $TypeOff="OT Not Paid - Pending IS Approval";
																    $adjmoney=0;
															    }else{
															        $TypeOff="OT Not Paid";
																$adjmoney=0;
															    }
																
    														insertdata($pdo,$ids,$TypeOff,$liloadj,$dtfrom,$ltype,$today,$adjmoney);
															}


														}else{
    														//do nothing																
    														$TypeOff="Restday";
    														$adjmoney=0;
    														insertdata($pdo,$ids,$TypeOff,$liloadj,$dtfrom,$ltype,$today,$adjmoney);																												
    													}									
            									}else{ 

													//revalidate data previuos payroll holiday data
													//check if 
													$revalidateBefore=0;
													$revalidateAfter=0;
													$restdaycheck=1;
													$restdaycheck2=1;
													$datefromnew2=$dtfrom;

													$stmReVal = $pdo->prepare("Select * from tblholidaypayroll where pdate=:datedtfr and empid=:id");
													$stmReVal->bindParam(':datedtfr' , $ltype);
													$stmReVal->bindParam(':id' , $ids);
													$stmReVal->execute();
													$countReVal = $stmReVal->rowCount();

													if($countReVal>0){
														$holidayDesc=$gethd['holiday_desc'];													
														$logBefore=0;
														$logAfter=0;
															while ( $getReVal = $stmReVal->fetch()){
																while ($restdaycheck2==1){
																	$datefromnew2=date("Y-m-d", strtotime($datefromnew2 . " + 1 days"));
																	$longdate=date("l",strtotime($datefromnew2));
											
																	//get the work schedule
																	$stmt = $pdo->prepare("Select * from workdays as a 
																				inner join workschedule as b on a.Schedtime=b.WorkSchedID
																				inner join schedeffectivity as c on a.EFID=c.efids
																				where (((a.empid=:empid) and (a.Day_s=:daylong))
																				and ((:dfrom >= dfrom) and (:dfrom <= dto)))
																			");
																							
																	$stmt->bindParam(':empid' , $ids);
																	$stmt->bindParam(':daylong' , $longdate);
																	$stmt->bindParam(':dfrom' , $datefromnew2);
																	$stmt->execute();
																	$getsched = $stmt->fetch();
																	$countsched = $stmt->rowCount();

																	//check if holiday
																	$stmhd1 = $pdo->prepare("Select * from holidays where Hdate=:datedtfr");
																	$stmhd1->bindParam(':datedtfr' , $datefromnew2);
																	$stmhd1->execute();
																	$gethd1 = $stmhd1->fetch();
																	$counthdafter = $stmhd1->rowCount();

																	if($counthdafter>0){
																		// $afterHoliday=$dtfrom;
																		//it must return the loop
																	}else{
																		if($getsched['SchedTime']>0){
																			$restdaycheck2=0;
																			$leaveAfter=0;
																			$obAfter=0;
																			$liloAfter=0;
			
																			$stmtOb = $pdo->prepare("Select * from obshbd where (OBDateFrom = :dateFrom) and OBStatus IN ('4') and EmpID=:id");
																			$stmtOb->bindParam(':dateFrom' , $datefromnew2);
																			$stmtOb->bindParam(':id' , $ids);
																			$stmtOb->execute();	
																			$rowOb = $stmtOb->fetch();
																			$rowObCount = $stmtOb->rowCount(); 
			
																			if($rowObCount>0) {
																				$obAfter=$rowOb['OBDuration'];
																					if($obAfter>=$hrsPerDay + 1 ){
																						$obAfter=$hrsPerDay + 1;
																					}
																			}																			
							
																			$stmtLeave= $pdo->prepare("Select * from hleavesbd where (LStart = :dateFrom) and EmpID=:id and LStatus IN ('4') and LType <> 33");
																			$stmtLeave->bindParam(':dateFrom' , $datefromnew2);
																			$stmtLeave->bindParam(':id' , $ids);
																			$stmtLeave->execute();	
																			$rowLeave = $stmtLeave->fetch();
																			$rowLeaveCount = $stmtLeave->rowCount(); 			
			
																			if($rowLeaveCount>0){
																				$leaveAfter=$rowLeave['LDuration'];
																					if($leaveAfter>=$hrsPerDay + 1 ){
																						$leaveAfter=$hrsPerDay + 1;
																					}
																			
																			}				
			
																			$stmlilo = $pdo->prepare("Select * from attendancelog where WSFrom=:dateFrom and EmpID=:id ");
																			$stmlilo->bindParam(':dateFrom' , $datefromnew2);
																			$stmlilo->bindParam(':id' , $ids);
																			$stmlilo->execute();								        
																			$rowLiloCount = $stmlilo->rowCount();
																			$rowLiloBefore = $stmlilo->fetch();
			
																			if($rowLiloCount>0) {
																				######
																					$liloAfter=0;
																				if($rowLiloBefore['durationtime']==0){
																					$timin=$rowLiloBefore['TimeIn'];
																					$dfrom=$rowLiloBefore['WSFrom'];
																					$timeout=$rowLiloBefore['TimeOut'];
																					$fromwcs=$dfrom. ' ' .$tfrom;
																					$dto=$rowLiloBefore['WSTo'];
																					$wto=$dto. ' ' .$tto;
				
																					if ($timin >= $fromwcs){														
																						if(strtotime($timeout) >= strtotime($wto)){
																							$liloAfter= strtotime($wto) - strtotime($timin) ;
																						}else{
																							$liloAfter= strtotime($timeout) - strtotime($timin) ; 																		
																						} 
																							
																							$liloAfter = $liloAfter / 60 ;		
				
																							if($liloAfter > 600){
																								// $liloAfter = 600;
																								$liloAfter = $liloAfter-60;

																								// $liloAfter = 600;
																							}
																							elseif($liloAfter > 360){
																								$liloAfter =$liloAfter - 60 ;
																							}
																							elseif($liloAfter > 300){
																								$liloAfter =300;
																							}
																							elseif($liloAfter < 1 || $liloAfter < 0)	
																							{
																								$liloAfter = 0;
																							}	      
																					}else{
																							if(strtotime($timeout) >= strtotime($wto)){
																								$liloAfter= strtotime($wto) - strtotime($fromwcs);
																							}
																							else{
																								$liloAfter= strtotime($timeout)- strtotime($fromwcs); 																							
																							}	
				
																							$liloAfter	 = $liloAfter / 60 ;
				
																							if($liloAfter > 600){
																								$liloAfter = $liloAfter-60;
																							}
																							elseif($liloAfter > 360){
																								$liloAfter =$liloAfter - 60 ;
																							}
																							elseif($liloAfter > 300){
																								$liloAfter =300;
																							}
																							elseif($liloAfter < 1 || $liloAfter < 0)	
																							{
																								$liloAfter = 0;
																							}	      																							
																					}		
																				}else{
																					$liloAfter=$rowLiloBefore['durationtime'];
																				}
																			
																				######
																				if($liloAfter>=11 ){
																					$liloAfter=$liloAfter;
																				}																
																			}		
																			
																			if($obAfter + $leaveAfter + $liloAfter >=11 ){
																				$logAfter=1;
																			}  

																			if($logAfter==0){
																				//tag the holiday as not paid add to adjustment
																				insertdata($pdo,$ids,$TypeOff,$liloadj,$dtfrom,$ltype,$today,$adjmoney);
																				$liloadj=0;
																			}
																	}
																}
															}
														}
													}

            										//check if holiday
            										$stmhd = $pdo->prepare("Select * from holidays where Hdate=:datedtfr");
            										$stmhd->bindParam(':datedtfr' , $dtfrom);
            										$stmhd->execute();
            										$gethd = $stmhd->fetch();
            										$counthd = $stmhd->rowCount();
            										if($counthd>0){
            											$liloadj=0;
                                                        //insert holiday status
                                                        $dateFromNew=$dtfrom;
                                                        $restdaycheck=1;
                                                        $restdaycheck2=1;
                                                        $datefromnew2=$dtfrom;
                                                     
                                                        if($gethd['Htype']<>0) {
															$afterHoliday=$dtfrom;
															$holidayDesc=$gethd['Hdescription'];
															$holidayStatus=$gethd['Htype'];													
                                                            $logBefore=0;
                                                            $logAfter=0;
                                                             
                                                              
                                                            	//if true validate before and after lilo if absent 
                                                            while ($restdaycheck==1){
                                                                $dateFromNew=date("Y-m-d", strtotime($dateFromNew . " - 1 days"));
                                                                $longdate=date("l",strtotime($dateFromNew));
                                                                $stmt = $pdo->prepare("Select * from workdays as a 
																inner join workschedule as b on a.Schedtime=b.WorkSchedID
																inner join schedeffectivity as c on a.EFID=c.efids
																where (((a.empid=:empid) and (a.Day_s=:daylong))
																and ((:dfrom >= dfrom) and (:dfrom <= dto)))
																");
                                                                                        
                                                                $stmt->bindParam(':empid' , $ids);
                                                                $stmt->bindParam(':daylong' , $longdate);
                                                                $stmt->bindParam(':dfrom' , $dateFromNew);
                                                                $stmt->execute();
                                                                $getsched = $stmt->fetch();
                                                                $countsched = $stmt->rowCount();
                                                                
                                                                //check if holiday
                                                                $stmhd = $pdo->prepare("Select * from holidays where Hdate=:datedtfr");
                                                                $stmhd->bindParam(':datedtfr' , $dateFromNew);
                                                                $stmhd->execute();
                                                                $gethd = $stmhd->fetch();
                                                                $counthdbefore = $stmhd->rowCount();
                                                                    
																//if holiday systen will loop back minus days
                                                                if($counthdbefore>0){
																}else{
																	if($getsched['SchedTime']>0){
																	
																		$restdaycheck=0;
																		$leaveBefore=0;
																		$obBefore=0;
																		$liloBefore=0;	
																		//validate pending ob
																		$stmtOb = $pdo->prepare("Select * from obshbd where (OBDateFrom = :dateFrom) and OBStatus IN ('4') and EmpID=:id");
																		$stmtOb->bindParam(':dateFrom' , $dateFromNew);
																		$stmtOb->bindParam(':id' , $ids);
																		$stmtOb->execute();	
																		$rowOb = $stmtOb->fetch();
																		$rowObCount = $stmtOb->rowCount(); 
			
																		if($rowObCount>0) {
																			$obBefore=$rowOb['OBDuration'];
																				if($obBefore>=$hrsPerDay + 1 ){
																					$obBefore=$hrsPerDay + 1;
																				}
																		}
																		
																		$stmtLeave= $pdo->prepare("Select * from hleavesbd where (LStart = :dateFrom) and EmpID=:id and LStatus IN ('4') and LType <> 33");
																		$stmtLeave->bindParam(':dateFrom' , $dateFromNew);
																		$stmtLeave->bindParam(':id' , $ids);
																		$stmtLeave->execute();	
																		$rowLeave = $stmtLeave->fetch();
																		$rowLeaveCount = $stmtLeave->rowCount(); 			
			
																		if($rowLeaveCount>0) {
																			$leaveBefore=$rowLeave['LDuration'];
																				if($leaveBefore>=$hrsPerDay + 1 ){
																					$leaveBefore=$hrsPerDay + 1;
																				}
																		}				
			
																		$stmlilo = $pdo->prepare("Select * from attendancelog where WSFrom=:dateFrom and EmpID=:id ");
																		$stmlilo->bindParam(':dateFrom' , $dateFromNew);
																		$stmlilo->bindParam(':id' , $ids);
																		$stmlilo->execute();								        
																		$rowLiloCount = $stmlilo->rowCount();
																		$rowLiloBefore = $stmlilo->fetch();
			
																		if($rowLiloCount>0) {
																			$liloBefore=0;
																			if($rowLiloBefore['durationtime']==0){
																						$timin=$rowLiloBefore['TimeIn'];
																						$dfrom=$rowLiloBefore['WSFrom'];
																						$timeout=$rowLiloBefore['TimeOut'];
																						$fromwcs=$dfrom. ' ' .$tfrom;
																						$dto=$rowLiloBefore['WSTo'];
																						$wto=$dto. ' ' .$tto;
				
																					if ($timin >= $fromwcs){														
																						if(strtotime($timeout) >= strtotime($wto)){
																							$liloBefore= strtotime($wto) - strtotime($timin) ;
																						}else{
																							$liloBefore= strtotime($timeout) - strtotime($timin) ; 																		
																						} 
																							
																							$liloBefore = $liloBefore / 60 ;		
				
																							if($liloBefore > 600){
																								// $liloBefore = 600;
																								$liloBefore = $liloBefore -60 ;

																							}
																							elseif($liloBefore > 360){
																								$liloBefore =$liloBefore - 60 ;
																							}
																							elseif($liloBefore > 300){
																								$liloBefore =300;
																							}
																							elseif($liloBefore < 1 || $liloBefore < 0)	
																							{
																								$liloBefore = 0;
																							}	      
																					}else{
																							if(strtotime($timeout) >= strtotime($wto)){
																								$liloBefore= strtotime($wto) - strtotime($fromwcs);
																							}
																							else{
																								$liloBefore= strtotime($timeout)- strtotime($fromwcs); 																							
																							}	
				
																							$liloBefore	 = $liloBefore / 60 ;
				
																							if($liloBefore > 600){
																								$liloBefore = $liloBefore -60 ;
																							}
																							elseif($liloBefore > 360){
																								$liloBefore =$liloBefore - 60 ;
																							}
																							elseif($liloBefore > 300){
																								$liloBefore =300;
																							}
																							elseif($liloBefore < 1 || $liloBefore < 0)	
																							{
																								$liloBefore = 0;
																							}	      																							
																						}		
																			}else{
																				$liloBefore=$rowLiloBefore['durationtime'];
																			}
																			
																			if($liloBefore>=6 ){
																				$liloBefore=$liloBefore;
																			}
																		}	
																		if($obBefore + $leaveBefore + $liloBefore >=6 ){
																			$logBefore=1;
																		}												
																	}	
																}

                                                            }
                                                            
                                                            
                                                            if($logBefore==0){
                                                                //dont check forafter
                                                            }else{
                                                                //if true validate after lilo if absent 
                                                                
																$cntDaysWthSched=0;
                                                                while ($restdaycheck2==1){
                                                                    
                                                                    $datefromnew2=date("Y-m-d", strtotime($datefromnew2 . " + 1 days"));
                                                                    $longdate=date("l",strtotime($datefromnew2));
                                                                     
                                                                    //get the work schedule
                                                                    $stmt = $pdo->prepare("Select * from workdays as a 
																	inner join workschedule as b on a.Schedtime=b.WorkSchedID
																	inner join schedeffectivity as c on a.EFID=c.efids
																	where (((a.empid=:empid) and (a.Day_s=:daylong))
																	and ((:dfrom >= dfrom) and (:dfrom <= dto)))");
                                                                                            
                                                                    $stmt->bindParam(':empid' , $ids);
                                                                    $stmt->bindParam(':daylong' , $longdate);
                                                                    $stmt->bindParam(':dfrom' , $datefromnew2);
                                                                    $stmt->execute();
                                                                    $getsched = $stmt->fetch();
                                                                    $countsched = $stmt->rowCount();

																	//check if holiday
																	$stmhd1 = $pdo->prepare("Select * from holidays where Hdate=:datedtfr");
																	$stmhd1->bindParam(':datedtfr' , $datefromnew2);
																	$stmhd1->execute();
																	$gethd1 = $stmhd1->fetch();
																	$counthdafter = $stmhd1->rowCount();
																
																	if($counthdafter>0){
																// 		$afterHoliday=$dtfrom;
																		//it must return the loop
																	}else{
																		if($getsched['SchedTime']>0){
																			$restdaycheck2=0;
																			$leaveAfter=0;
																			$obAfter=0;
																			$liloAfter=0;
			
																			$stmtOb = $pdo->prepare("Select * from obshbd where (OBDateFrom = :dateFrom) and OBStatus IN ('4') and EmpID=:id");
																			$stmtOb->bindParam(':dateFrom' , $datefromnew2);
																			$stmtOb->bindParam(':id' , $ids);
																			$stmtOb->execute();	
																			$rowOb = $stmtOb->fetch();
																			$rowObCount = $stmtOb->rowCount(); 
			
																			if($rowObCount>0) {
																				$obAfter=$rowOb['OBDuration'];
																					if($obAfter>=$hrsPerDay + 1 ){
																						$obAfter=$hrsPerDay + 1;
																					}
																			}																			
							
																			$stmtLeave= $pdo->prepare("Select * from hleavesbd where (LStart = :dateFrom) and EmpID=:id and LStatus IN ('4') and LType <> 33");
																			$stmtLeave->bindParam(':dateFrom' , $datefromnew2);
																			$stmtLeave->bindParam(':id' , $ids);
																			$stmtLeave->execute();	
																			$rowLeave = $stmtLeave->fetch();
																			$rowLeaveCount = $stmtLeave->rowCount(); 			
			
																			if($rowLeaveCount>0){
																				$leaveAfter=$rowLeave['LDuration'];
																					if($leaveAfter>=$hrsPerDay + 1 ){
																						$leaveAfter=$hrsPerDay + 1;
																					}
																			}				
			
																			$stmlilo = $pdo->prepare("Select * from attendancelog where WSFrom=:dateFrom and EmpID=:id ");
																			$stmlilo->bindParam(':dateFrom' , $datefromnew2);
																			$stmlilo->bindParam(':id' , $ids);
																			$stmlilo->execute();								        
																			$rowLiloCount = $stmlilo->rowCount();
																			$rowLiloBefore = $stmlilo->fetch();
			
																			if($rowLiloCount>0) {
																				######
																					$liloAfter=0;
																				if($rowLiloBefore['durationtime']==0){
																					$timin=$rowLiloBefore['TimeIn'];
																					$dfrom=$rowLiloBefore['WSFrom'];
																					$timeout=$rowLiloBefore['TimeOut'];
																					$fromwcs=$dfrom. ' ' .$tfrom;
																					$dto=$rowLiloBefore['WSTo'];
																					$wto=$dto. ' ' .$tto;
				
																					if ($timin >= $fromwcs){														
																						if(strtotime($timeout) >= strtotime($wto)){
																							$liloAfter= strtotime($wto) - strtotime($timin) ;
																						}else{
																							$liloAfter= strtotime($timeout) - strtotime($timin) ; 																		
																						} 
																							
																							$liloAfter = $liloAfter / 60 ;		
				
																							if($liloAfter > 600){
																								// $liloAfter = 600;
																								$liloAfter = $liloAfter-60;
	
																								// $liloAfter = 600;
																							}
																							elseif($liloAfter > 360){
																								$liloAfter =$liloAfter - 60 ;
																							}
																							elseif($liloAfter > 300){
																								$liloAfter =300;
																							}
																							elseif($liloAfter < 1 || $liloAfter < 0)	
																							{
																								$liloAfter = 0;
																							}	      
																					}else{
																							if(strtotime($timeout) >= strtotime($wto)){
																								$liloAfter= strtotime($wto) - strtotime($fromwcs);
																							}
																							else{
																								$liloAfter= strtotime($timeout)- strtotime($fromwcs); 																							
																							}	
				
																							$liloAfter	 = $liloAfter / 60 ;
				
																							if($liloAfter > 600){
																								$liloAfter = $liloAfter-60;
																							}
																							elseif($liloAfter > 360){
																								$liloAfter =$liloAfter - 60 ;
																							}
																							elseif($liloAfter > 300){
																								$liloAfter =300;
																							}
																							elseif($liloAfter < 1 || $liloAfter < 0)	
																							{
																								$liloAfter = 0;
																							}	      																							
																					}		
																				}else{
																					$liloAfter=$rowLiloBefore['durationtime'];
																				}
																			
																				######
																				if($liloAfter >= 8 ){
																					$liloAfter=$liloAfter;
																				}																
																			}		
																			
																			if(($obAfter + $leaveAfter + $liloAfter) >= 8 ){
																				$logAfter=1;
																			}  
																			
																			//code here
																			if($logAfter==0){
																				// $dtfrom <=$dtto
																				if(( $datefromnew2 >= $dtfr) && ($datefromnew2 <= $dtto)){
																					//do nothing holiday will be tas as paid
																					// $logAfter=1;
																					
																				}else{
																					//set logafter as  1 even if walang pasok
																					 $logAfter=1;
																					 $actions="after";
																					 $status=0;
																					 $dateM=date("d",strtotime($ltype));
																					//set newpadate
																					if($dateM==5){
																						$dd=date("Y-m"."-20", strtotime($ltype));
																					}else{
																					  $dd=date("Y-m"."-5", strtotime($ltype . " + 1 months"));
																					}
																					 
																					$sql = "INSERT INTO tblholidaypayroll (empid,date,pdate,npdate,action,status,holiday_desc,datetimeinputed) VALUES (:id,:hdate,:pdate,:npdate,:act,:status,:hdesc,:dti)";
																					$stmt = $pdo->prepare($sql);
																					$stmt->bindParam(':id' , $ids);
																					$stmt->bindParam(':hdate', $afterHoliday);
																					$stmt->bindParam(':pdate', $ltype);
																					$stmt->bindParam(':npdate', $dd);
																					$stmt->bindParam(':act',  $actions);
																					$stmt->bindParam(':status',  $status);
																					$stmt->bindParam(':hdesc', $holidayDesc);
																					$stmt->bindParam(':dti', $today);
																					$stmt->execute();
																				}
																			}

																		}
																	}
                                                                }
                                                            }
                                                            
                                                            if($logBefore==0 OR $logAfter==0){
                                                                $liloadj=$hrsPerDay;
                                                                $adjmoney=$RatePerMin1 * $liloadj * 60;
                                                                $TypeOff="Holiday - Not paid ";	;
                                                            }else{
                                                                $liloadj=0;
                                                                $adjmoney=0;
                                                                $TypeOff="Holiday  ";	
                                                            }
                                   
                                                            insertdata($pdo,$ids,$TypeOff,$liloadj,$dtfrom,$ltype,$today,$adjmoney);
                                                            $liloadj=0;	
                                                        }	
            										}else{
															//loop through atteandance- adjusment
																$nologs ="1";
																$stmlilo = $pdo->prepare("Select * from attendancelog where WSFrom=:datedtfr and EmpID=:id");
																$stmlilo->bindParam(':datedtfr' , $dtfrom);
																$stmlilo->bindParam(':id' , $ids);
																$stmlilo->execute();								        
																$countlilo = $stmlilo->rowCount();
																$liloadj=0;
																$blhours = 0;
																
																	if($countlilo > 0){
																		$blhours = 0;
																		$hrsSum=0;
																		while ($getlilo = $stmlilo->fetch()) {
																			$hrsSum=0;
																				//recompute data for lilo
																			if($getlilo['durationtime']==0){
																						//initialized data from login
																						//$lid=$getlilo['LogID'];
																						$timin=$getlilo['TimeIn'];
																						$dfrom=$getlilo['WSFrom'];
																						$timeout=$getlilo['TimeOut'];
																						$fromwcs=$dfrom. ' ' .$tfrom;
																						$dto=$getlilo['WSTo'];
																						//$late=$getlilo['MinsLack'];
																						$wto=$dto. ' ' .$tto;
															
																					// $tswto = strtotime($wto);
																					// $timenow = strtotime($today);
				
																					if ($timin >= $fromwcs){														
																						if(strtotime($timeout) >= strtotime($wto)){
																							$hrsSum= strtotime($wto) - strtotime($timin) ;
																							}
																							else{
																								$hrsSum= strtotime($timeout) - strtotime($timin) ; 																		
																							} 
																							
																							$hrsSum = $hrsSum / 60 /60;		
				
																							if($hrsSum > 600){
																								$hrsSum = 600;
																							}
																							elseif($hrsSum > 360){
																								$hrsSum =$hrsSum - 60 ;
																							}
																							elseif($hrsSum > 300){
																								$hrsSum =300;
																							}
																							elseif($hrsSum < 1 || $hrsSum < 0)	
																							{
																								$hrsSum = 0;
																							}	      
																					}else{
																						if(strtotime($timeout) >= strtotime($wto)){
																								$hrsSum= strtotime($wto) - strtotime($fromwcs);
																							}
																							else{
																								$hrsSum= strtotime($timeout)- strtotime($fromwcs); 																							
																							}	
				
																						$hrsSum	 = $hrsSum / 60 /60;
				
																							if($hrsSum > 600){
																								$hrsSum = 600;
																							}
																							elseif($hrsSum > 360){
																								$hrsSum =$hrsSum - 60 ;
																							}
																							elseif($hrsSum > 300){
																								$hrsSum =300;
																							}
																							elseif($hrsSum < 1 || $hrsSum < 0)	
																							{
																								$hrsSum = 0;
																							}	      																							
																					}		
																			}else{
																					$hrsSum	= $getlilo['durationtime'];																	
																			}																
																				$blhours=($blhours + $hrsSum);															
																		}
																	
																			#validate computed hrs
																			//signal bilhours to complete if has approved eo
																		if($haseo==1){
																			$blhours=$hrsPerDay +1;
																			}
																			//validate total render hrs
																		if($blhours >=$hrsPerDay+1){
																			$liloadj=0;
																		}
																		else{																														
																				if($blhours >=$hrsHalf + 1  ){
																					$liloadj=(($hrsPerDay + 1) -$blhours );																		
																				}																
																				else{
																					$liloadj=(($hrsPerDay ) -$blhours );
																					
																				}
																		}
																		#insert adjustment									        									        		
																		$TypeOff="Attendance";
																		$adjmoney=($liloadj*60)*$RatePerMin1;
																		insertdata($pdo,$ids,$TypeOff,$liloadj,$dtfrom,$ltype,$today,$adjmoney);
																		
																		$nologs="0";
																		
																	}else{
																		$nologs="1";
																		$nolilo=1;																									
																	}
				
															//loop through ob -adjustment
															$stmob = $pdo->prepare("Select * from obshbd where OBDateFrom=:datedtfr and EmpID=:id and OBStatus IN ('4') order by OBDateFrom ");
															$stmob->bindParam(':datedtfr' , $dtfrom);
															$stmob->bindParam(':id' , $ids);
															$stmob->execute();								        
															$countob = $stmob->rowCount(); 
															if($blhours >= $hrsPerDay +1){
																$nologs="0";											
															}else{											
																	if($countob> 0){														
																		$OBblhours = 0;
																		$liloadj=0;
																		$blhours1 = 0;
																		$hasob=1;	
																		$nologs="0";
																		//loop ob recordeset
																	
																		while ($rowob = $stmob->fetch()) {
																			$obstart=$rowob['OBDateFrom']." ".$rowob['OBTimeFrom'].":00";
																			$obend=$rowob['OBDateTo']." ".$rowob['OBTimeTo'].":00";
				
																				if ($rowob['OBDuration'] >=$hrsPerDay +1){
																					$OBblhours=$hrsPerDay + 1;
																					$nologs="0";
																						$liloadj=0;
																						$TypeOff="OB";
																						$adjmoney=0;
																						insertdata($pdo,$ids,$TypeOff,$liloadj,$dtfrom,$ltype,$today,$adjmoney);
																				}
																				else{
																					$nologs="0";
																					$stmlilo2 = $pdo->prepare("Select * from attendancelog where WSFrom=:datedtfr and EmpID=:id");
																					$stmlilo2->bindParam(':datedtfr' , $dtfrom);
																					$stmlilo2->bindParam(':id' , $ids);
																					$stmlilo2->execute();				
																					//$getlilo2 = $stmlilo2->fetch();				        
																					$countlilo2 = $stmlilo2->rowCount();													
																						
																					if($countlilo2 > 0 ){
																						//loop lilo recordset
																						$sumhr=0;
																						while ($getlilo2 = $stmlilo2->fetch()) {																					
																							$timein=$getlilo2['TimeIn'];
																							$timeout=$getlilo2['TimeOut'];
																							$wsfrom=$getlilo2['WSFrom']. " " .$getlilo2['wsched'];
																							$wsto=$getlilo2['WSTo']. " " .$getlilo2['wsched2'];
																							
																						if(($obstart <= $timein ) and ($obend <= $timein)){
																								#calculate ob
																								$dteStart = new DateTime($obstart); 
																								$dteEnd   = new DateTime($obend); 
																								$dteDiff  = $dteStart->diff($dteEnd); 
																								$sumhr = $dteDiff->format("%h" ); 
																								$summin = $dteDiff->format("%i" ); 
																								$sumsec = $dteDiff->format("%s" ); 
																								$sumfinal = ($sumhr * 60) + $summin + ($sumsec /60);																		
																							}
																							
																						elseif(($obstart >= $timeout) and ($obend >=$timeout)){
																							// 	#calculate ob
																								$dteStart = new DateTime($obstart); 
																								$dteEnd   = new DateTime($obend); 
																								$dteDiff  = $dteStart->diff($dteEnd); 
																								$sumhr = $dteDiff->format("%h" ); 
																								$summin = $dteDiff->format("%i" ); 
																								$sumsec = $dteDiff->format("%s" ); 
																								$sumfinal = ($sumhr * 60) + $summin + ($sumsec /60);
																								//echo "2ob";
																							}
		
																						elseif(($obstart >=$timein) and ($obend <= $timeout)){
																							//echo "3ob";
																							//$OBblhours=$rowob['OBDuration']  + $OBblhours;
																						}
		
																						elseif(($obstart <= $timein ) and (($obend <= $timeout and $obend >= $timein))){
																						// 	// 	#datediff from obstart to timein
																							$dteStart = new DateTime($obstart); 
																							$dteEnd   = new DateTime($timein);
																							$dteDiff  = $dteStart->diff($dteEnd); 
																							$sumhr = $dteDiff->format("%h" ); 
																							$summin = $dteDiff->format("%i" ); 
																							$sumsec = $dteDiff->format("%s" ); 
																							$sumfinal = ($sumhr * 60) + $summin + ($sumsec /60);
																						}
		
																						elseif(($obstart <= $timein ) and ($obend >= $timeout)){
																							// 	#date diff obstart to timein
		
																							$dteStart = new DateTime($obstart); 
																							$dteEnd   = new DateTime($timein);
																							$dteDiff1  = $dteStart->diff($dteEnd); 
																							$sumhr1 = $dteDiff1->format("%H"); 
																							$summin1 = $dteDiff1->format("%i" ); 
																							$sumsec1 = $dteDiff1->format("%s" ); 
																							$sumfinal1 = ($sumhr1 * 60) + $summin1 + ($sumsec1 /60);
		
																							// 	#date diff Timeout to obend
																							$dteStart1 = new DateTime($timeout); 
																							$dteEnd1   = new DateTime($obend);
																							$dteDiff2  = $dteStart1->diff($dteEnd1); 
																							$sumhr2 = $dteDiff2->format("%H"); 
																							$summin2 = $dteDiff2->format("%i" ); 
																							$sumsec2 = $dteDiff2->format("%s" ); 
																							$sumfinal2 = ($sumhr2 * 60) + $summin2 + ($sumsec2 /60);
		
																							//#sumup
																							$sumfinal=$sumfinal1+$sumfinal2;
																						}
		
																						elseif(($obstart  >= $timein) and (($obstart <= $timeout) and ($obend >=$timeout))){
																							#datediff timeout to obend
																							$dteStart = new DateTime($timeout); 
																							$dteEnd   = new DateTime($obend);
																							$dteDiff  = $dteStart->diff($dteEnd); 
																							$sumhr = $dteDiff->format("%h" ); 
																							$summin = $dteDiff->format("%i" ); 
																							$sumsec = $dteDiff->format("%s" ); 
																							$sumfinal = ($sumhr * 60) + $summin + ($sumsec /60);
																						}
																						else {
																							//echo "7ob";
																							$OBblhours=$rowob['OBDuration']  + $OBblhours;
																						}
																						//echo $OBblhours ;
																						$OBblhours=($OBblhours + $sumfinal) / 60 ;
																					
																						}
																					}else {	
																					}																																	
																				}
																		} 				// /60			
																			//isert lacking mins
																			if($nologs=="1"){ 
																				$TypeOff="Attendance-OB";
																				// /60
																				$liloadj = (($hrsPerDay +1) - ($OBblhours))* 60;
																				$adjmoney=$liloadj*$RatePerMin1;
																				insertdata($pdo,$ids,$TypeOff,$liloadj,$dtfrom,$ltype,$today,$adjmoney);
																		
																			}elseif($nologs=="0"){
																				$typedesc="Attendance";
																				
																					//validate if system found out no schedule but not rest day
																					if($hasob==0 and $nolilo==1){
																						$liloadj=600;
																						$TypeOff="Attendance";
																						$adjmoney=$liloadj*$RatePerMin1;
																						insertdata($pdo,$ids,$TypeOff,$liloadj,$dtfrom,$ltype,$today,$adjmoney);																		
																					}
																				
																						if(($blhours + ($OBblhours))>=($hrsPerDay+1)){
																							$liloadj=0;																																																													
																						}
																						else{
																							// /60																					
																							$liloadj = ((($hrsPerDay+1) - ( $blhours + ($OBblhours))) * 60);
																																																														
																						}
																				$adjmoney=$liloadj*$RatePerMin1;
																				//echo $blhours . "+" .($OBblhours ) ;
																				//update data 	
																				$sql = "UPDATE temp_payroll SET adjustment=:tout,adjmin=:adjmin WHERE EmpID=:id and Date=:date1";
																				$stmt = $pdo->prepare($sql);
																				$stmt->bindParam(':id', $ids);
																				$stmt->bindParam(':tout', $liloadj);
																				$stmt->bindParam(':date1',   $dtfrom );
																				$stmt->bindParam(':adjmin',   $adjmoney );
																				$stmt->execute();
																			}
																	}elseif($nologs<>"0"){
																		$nologs="1";	
																														
																	}
															}	
				
				
															if((($OBblhours) + $blhours) >=$hrsPerDay +1){
																	$nologs ="0";
																	//proceed to next step																				
															}else {
																//fetch data
																	$stmleave= $pdo->prepare("Select * from hleavesbd where LStart=:datedtfr and EmpID=:id and LStatus = 4 and LType IN ('4','1','8','3','7','11','6','27','37','36','35','29','22','30','24')");
																	$stmleave->bindParam(':datedtfr' , $dtfrom);
																	$stmleave->bindParam(':id' , $ids);
																	$stmleave->execute();				
																	//$getlilo2 = $stmlilo2->fetch();				        
																	$countleave = $stmleave->rowCount();	
																	
																		if( $countleave > 0 ){	
																			$leaveduration=0;
																			$nologs ="0";
																			//loop through recordset get approved leave
																				while ($getleave = $stmleave->fetch()) {
																					$leaveduration=$leaveduration + $getleave['LDuration'];
																					//script to update delete the tag leave
																					$stmtdelTag = $pdo->prepare("Delete from tbl where refID=:pdate");
																					$stmtdelTag->bindParam(':pdate' , $getleave['LeaveID']);
																					$stmtdelTag->execute(); 
			
																				}
																					//calculate adjustment
																						if(($blhours + ($OBblhours) + ($leaveduration)/$hrsPerDay)>=($hrsPerDay+1)){
																							$liloadj=0;
																						
																								$TypeOff = "Leave";	
																									insertdata($pdo,$ids,$TypeOff,$liloadj,$dtfrom,$ltype,$today,$adjmoney);
																						}else{
																							if ($OBblhours >= $hrsHalf + 1 ){
																									$hrsPerDay=$hrsPerDay+1;
																								}
																								else{
																										$hrsPerDay=$hrsPerDay;
																									}
																							// /60			
																							$liloadj = ((($hrsPerDay) - ( $blhours + ($OBblhours) + $leaveduration)) * 60);
																						}
																						$leaveapprove=$liloadj;
																						$updateleave=1;																																
																		}else{																			
        																	// 	  $TypeOff = "Leave";	
        																	// 	  $nologs ="1";	
																		}
															}
				
															if($nologs == 1  ) {
																
																//validate if no entry 
																$labelval="";
																$eo="";
																$ob="";
																$leave="";
																$getPendingEO = $pdo->prepare("Select * from earlyout where (DFile= :dateFrom ) and Status IN ('1','2','3','5','8')and EMPID=:id");
																$getPendingEO->bindParam(':id' , $ids);
																$getPendingEO->bindParam(':dateFrom' , $dtfrom);
																//$getPendingEO->bindParam(':dateTo' , $dtto);
																$getPendingEO->execute();		
																$getRowEO = $getPendingEO->fetch();
																$getRowCounteo = $getPendingEO->rowCount();  		
																if($getRowCounteo>0) {
																	$eo="EO Not Paid";	
																}
																//validate pending ob
																$getPendingOb = $pdo->prepare("Select * from obshbd where ((OBDateFrom = :dateFrom) and (OBStatus IN ('1','2','3','5','8')) and (EmpID=:id))");
																$getPendingOb->bindParam(':id' , $ids);
																$getPendingOb->bindParam(':dateFrom' , $dtfrom);
																//$getPendingOb->bindParam(':dateTo' , $dtto);
																$getPendingOb->execute();	
																$getRowOB = $getPendingOb->fetch();
																$getRowCountob = $getPendingOb->rowCount(); 
				
																
																if($getRowCountob>0) {
																	$ob="OB Not Paid";
																
																}
																
																//validate leavepeding
																$getRowpendingL = $pdo->prepare("Select * from hleavesbd where (LStart = :dateFrom) and EmpID=:id " );
																$getRowpendingL->bindParam(':id' , $ids);
																$getRowpendingL->bindParam(':dateFrom' , $dtfrom);
																//$getRowpendingL->bindParam(':dateTo' , $dtto);
																$getRowpendingL->execute();	
																$rowleave = $getRowpendingL->fetch();
																$getRowCountL = $getRowpendingL->rowCount(); 			
				
																if($getRowCountL>0) {
																	if($rowleave['LType']<>33){
																	
																	}else{
																		$leave="Leave Not Paid";
																	}
																}
																
																$labelval = $eo.$ob.$leave;
																if($labelval=="")
																{
																	$TypeOff="No Logs";	
																}else{
																	$TypeOff=$labelval;	
																}
																
																
																	$liloadj = $hrsPerDay;													
																	$adjmoney=($liloadj*60)*$RatePerMin1;
																	insertdata($pdo,$ids,$TypeOff,$liloadj,$dtfrom,$ltype,$today,$adjmoney);
																										
															}
															//update the entry if has leave
				
															$adjmoney=($liloadj*60)*$RatePerMin1;
															if($updateleave==1){
																//update the date adjustment here
																
																$sql = "UPDATE temp_payroll SET adjustment=:tout,adjmin=:adjmin WHERE( EmpID=:id and Date=:date1) ";
																$stmt = $pdo->prepare($sql);
																$stmt->bindParam(':id', $ids);
																$stmt->bindParam(':date1',   $dtfrom ); 	
																$stmt->bindParam(':tout', $leaveapprove);
																$stmt->bindParam(':adjmin', $adjmoney);
																$stmt->execute();		
															}
            										}
            									}
            									//get leave 								
            									$nolilo=0;
            									$liloadj=0;
            									$leaveduration=0; 
            									$updateleave=0;	
            									$haseo=0;
            									$leaveapprove=0;
            
                                        }else{
                                                $ids=$calrow['EmpID'];
                                                $haseoic=0;
                                                $adjmoney=0;
                                                //check eo
                                                $stmteo = $pdo->prepare("Select * from earlyout where DFile=:datedtfr and EMPID=:id and Status='4'");
                                                $stmteo->bindParam(':datedtfr' , $dtfrom);
                                                $stmteo->bindParam(':id' , $ids);
                                                $stmteo->execute();								        
                                                $countlilo = $stmteo->rowCount();
												if($countlilo > 0 ){	
														$haseoic=1;
												}											
												$stmlilo = $pdo->prepare("Select * from attendancelog where WSFrom=:datedtfr and EmpID=:id");
												$stmlilo->bindParam(':datedtfr' , $dtfrom);
												$stmlilo->bindParam(':id' , $ids);
												$stmlilo->execute();								        
												$countlilo = $stmlilo->rowCount();
        
                                                    
                                                if($countlilo > 0){
                                                        $blhours=0;
                                                        $hrsSumic=0;
                                                        $liloadj=0;
                                                    
                                                        while ($getlilo = $stmlilo->fetch()) {
                                                                    //recompute data for lilo
                                                                if($getlilo['durationtime']==0){
                                                                            $timin=$getlilo['TimeIn'];
                                                                            $dfrom=$getlilo['WSFrom'];
                                                                            $timeout=$getlilo['TimeOut'];
                                                                            $fromwcs=$dfrom. ' ' .$tfrom;
                                                                            $dto=$getlilo['WSTo'];
                                                                            $wto=$dto. ' ' .$tto;
        
                                                                        if ($timin >= $fromwcs){														
                                                                            if(strtotime($timeout) >= strtotime($wto)){
                                                                                $hrsSumic= strtotime($wto) - strtotime($timin) ;
                                                                                }
                                                                                else{
                                                                                    $hrsSumic= strtotime($timeout) - strtotime($timin) ; 																		
                                                                                } 
                                                                                $hrsSumic = $hrsSumic / 60 /60;		
        
                                                                                if($hrsSumic > 600){
                                                                                $hrsSumic = 600;
                                                                            }
                                                                            elseif($hrsSumic > 360){
                                                                                $hrsSumic =$hrsSumic - 60 ;
                                                                            }
                                                                            elseif($hrsSumic > 300){
                                                                                $hrsSumic =300;
                                                                            }
                                                                            elseif($hrsSumic < 1 || $hrsSumic < 0)	
                                                                            {
                                                                                $hrsSumic = 0;
                                                                            }	           
                                                                        }else{
                                                                            if(strtotime($timeout) >= strtotime($wto)){
                                                                                    $hrsSumic= strtotime($wto) - strtotime($fromwcs);
                                                                                }
                                                                                else{
                                                                                    $hrsSumic= strtotime($timeout)- strtotime($fromwcs); 																							
                                                                                }	
        
                                                                            $hrsSumic	 = $hrsSumic / 60 /60;
        
                                                                            if($hrsSumic > 600){
                                                                            $hrsSumic = 600;
                                                                        }
                                                                        elseif($hrsSumic > 360){
                                                                            $hrsSumic =$hrsSumic - 60 ;
                                                                        }
                                                                        elseif($hrsSumic > 300){
                                                                            $hrsSumic =300;
                                                                        }
                                                                        elseif($hrsSumic < 1 || $hrsSumic < 0)	
                                                                        {
                                                                            $hrsSumic = 0;
                                                                        }	      																						
                                                                            }		
                                                                }else{
                                                                    $hrsSumic= $getlilo['durationtime'];																	
                                                                }	
                                                                    $blhours=($blhours + $hrsSumic); 	
                                                                                                                
                                                        }
                                                    
														//signal bilhours to complete if has approved eo
														if($haseoic==1){
															$blhours=$hrsPerDay +1;
														}
														$liloadj=$blhours;
													
														//ob sectio ic looping 
														//loop through ob -adjustment
                                                    $stmob = $pdo->prepare("Select * from obshbd where OBDateFrom=:datedtfr and EmpID=:id and OBStatus IN ('4') order by OBDateFrom ");
                                                    $stmob->bindParam(':datedtfr' , $dtfrom);
                                                    $stmob->bindParam(':id' , $ids);
                                                    $stmob->execute();								        
                                                    $countob = $stmob->rowCount(); 
                                                    if($liloadj >= $hrsPerDay +1){																							 
                                                        $liloadj=$hrsPerDay +1;											
                                                    }
                                                    else{
                                                    
                                                            if($countob> 0){														
                                                                $OBblhours = 0;
                                                                
                                                                $blhours1 = 0;
                                                                $hasob=1;	
                                                                $nologs="0";
                                                            
                                                                while ($rowob = $stmob->fetch()) {
                                                                $obstart=$rowob['OBDateFrom']." ".$rowob['OBTimeFrom'].":00";
                                                                $obend=$rowob['OBDateTo']." ".$rowob['OBTimeTo'].":00";
        
                                                                        if ($rowob['OBDuration'] >=$hrsPerDay +1){
                                                                            $liloadj=$hrsPerDay + 1;
                                                                        }
                                                                        else{
                                                                            $stmlilo2 = $pdo->prepare("Select * from attendancelog where WSFrom=:datedtfr and EmpID=:id");
                                                                            $stmlilo2->bindParam(':datedtfr' , $dtfrom);
                                                                            $stmlilo2->bindParam(':id' , $ids);
                                                                            $stmlilo2->execute();				
                                                                            //$getlilo2 = $stmlilo2->fetch();				        
                                                                            $countlilo2 = $stmlilo2->rowCount();													
                                                                                
                                                                                    if($countlilo2 > 0 ){
                                                                                                                                    
                                                                                        //loop lilo recordset
                                                                                        $sumhr=0;
                                                                                        while ($getlilo2 = $stmlilo2->fetch()) {																					
                                                                                            $timein=$getlilo2['TimeIn'];
                                                                                            $timeout=$getlilo2['TimeOut'];
                                                                                            $wsfrom=$getlilo2['WSFrom']. " " .$getlilo2['wsched'];
                                                                                            $wsto=$getlilo2['WSTo']. " " .$getlilo2['wsched2'];
                                                                                            
                                                                                        if(($obstart <= $timein ) and ($obend <= $timein)){
                                                                                                #calculate ob
                                                                                                $dteStart = new DateTime($obstart); 
                                                                                                $dteEnd   = new DateTime($obend); 
                                                                                                $dteDiff  = $dteStart->diff($dteEnd); 
                                                                                                $sumhr = $dteDiff->format("%h" ); 
                                                                                                $summin = $dteDiff->format("%i" ); 
                                                                                                $sumsec = $dteDiff->format("%s" ); 
                                                                                                $sumfinal = ($sumhr * 60) + $summin + ($sumsec /60);																		
                                                                                            }
                                                                                            
                                                                                        elseif(($obstart >= $timeout) and ($obend >=$timeout)){
                                                                                            // 	#calculate ob
                                                                                                $dteStart = new DateTime($obstart); 
                                                                                                $dteEnd   = new DateTime($obend); 
                                                                                                $dteDiff  = $dteStart->diff($dteEnd); 
                                                                                                $sumhr = $dteDiff->format("%h" ); 
                                                                                                $summin = $dteDiff->format("%i" ); 
                                                                                                $sumsec = $dteDiff->format("%s" ); 
                                                                                                $sumfinal = ($sumhr * 60) + $summin + ($sumsec /60);
                                                                                                //echo "2ob";
                                                                                            }
        
                                                                                        elseif(($obstart >=$timein) and ($obend <= $timeout)){
                                                                                            //echo "3ob";
                                                                                            //$sumfinal=$rowob['OBDuration']  + $sumfinal;
                                                                                        }
        
                                                                                        elseif(($obstart <= $timein ) and (($obend <= $timeout and $obend >= $timein))){
                                                                                        // 	// 	#datediff from obstart to timein
                                                                                            $dteStart = new DateTime($obstart); 
                                                                                            $dteEnd   = new DateTime($timein);
                                                                                            $dteDiff  = $dteStart->diff($dteEnd); 
                                                                                            $sumhr = $dteDiff->format("%h" ); 
                                                                                            $summin = $dteDiff->format("%i" ); 
                                                                                            $sumsec = $dteDiff->format("%s" ); 
                                                                                            $sumfinal = ($sumhr * 60) + $summin + ($sumsec /60);
                                                                                        }
        
                                                                                        elseif(($obstart <= $timein ) and ($obend >= $timeout)){
                                                                                            // 	#date diff obstart to timein
        
                                                                                            $dteStart = new DateTime($obstart); 
                                                                                            $dteEnd   = new DateTime($timein);
                                                                                            $dteDiff1  = $dteStart->diff($dteEnd); 
                                                                                            $sumhr1 = $dteDiff1->format("%H"); 
                                                                                            $summin1 = $dteDiff1->format("%i" ); 
                                                                                            $sumsec1 = $dteDiff1->format("%s" ); 
                                                                                            $sumfinal1 = ($sumhr1 * 60) + $summin1 + ($sumsec1 /60);
        
                                                                                            // 	#date diff Timeout to obend
                                                                                            $dteStart1 = new DateTime($timeout); 
                                                                                            $dteEnd1   = new DateTime($obend);
                                                                                            $dteDiff2  = $dteStart1->diff($dteEnd1); 
                                                                                            $sumhr2 = $dteDiff2->format("%H"); 
                                                                                            $summin2 = $dteDiff2->format("%i" ); 
                                                                                            $sumsec2 = $dteDiff2->format("%s" ); 
                                                                                            $sumfinal2 = ($sumhr2 * 60) + $summin2 + ($sumsec2 /60);
        
                                                                                            //#sumup
                                                                                            $sumfinal=$sumfinal1+$sumfinal2;
                                                                                        }
        
                                                                                        elseif(($obstart  >= $timein) and (($obstart <= $timeout) and ($obend >=$timeout))){
                                                                                            #datediff timeout to obend
                                                                                            $dteStart = new DateTime($timeout); 
                                                                                            $dteEnd   = new DateTime($obend);
                                                                                            $dteDiff  = $dteStart->diff($dteEnd); 
                                                                                            $sumhr = $dteDiff->format("%h" ); 
                                                                                            $summin = $dteDiff->format("%i" ); 
                                                                                            $sumsec = $dteDiff->format("%s" ); 
                                                                                            $sumfinal = ($sumhr * 60) + $summin + ($sumsec /60);
                                                                                            
                                                                                        }
                                                                                        else {
                                                                                            //echo "7ob";
                                                                                            $sumfinal=$rowob['OBDuration'] ;
                                                                                        }
                                                                                        //echo $OBblhours ;
                                                                                        $liloadj=($liloadj + $sumfinal) / 60;
                                                                                        // print	$liloadj;
                                                                                        
                                                                                        }
                                                                                    }																																
                                                                            }
                                                                } 																															         
                                                            }
                                                    }
                                                            #insert adjustment									        									        		
                                                            $TypeOff="Attendance";											
                                                            insertdata($pdo,$ids,$TypeOff,$liloadj,$dtfrom,$ltype,$today,$adjmoney);												 
                                                }
                                        }
            								 	
            							$dtfrom=date("Y-m-d", strtotime($dtfrom . " + 1 days"));  	
            					    }
                    			}catch(Exception $e) {
                          echo 'Message: '.$ids .$e->getMessage();
        }
			

					if ($calrow['EmpStatID']==1){
					    
					    // start 06/11/2024
								// $stmleave= $pdo->prepare("Select * from hleavesbd INNER JOIN  where LStart=:datedtfr and EmpID=:id and LStatus = 4 and LType IN ('4','1','8','3','7','11','6')");
								$stmleaveLookBack= $pdo->prepare("Select * from hleavesbd INNER JOIN tbl ON tbl.refID=hleavesbd.LeaveID  where (tbl.refPayDate=:datedtfr)and tbl.refEmpID=:id 
																											and hleavesbd.LStatus = 4 and hleavesbd.LType IN ('27','22','36','35','29','30')");
								$stmleaveLookBack->bindParam(':datedtfr' , $ltype);
								$stmleaveLookBack->bindParam(':id' , $ids);
								$stmleaveLookBack->execute();				  			        
								$countLookbackleave = $stmleaveLookBack->rowCount();	
								
									if( $countLookbackleave > 0 ){	
										$lookBackDur=0;
										$nologs ="0";

										//loop through recordset get approved leave
										while ($getleaveLookback = $stmleaveLookBack->fetch()) {
											$adjDatamin=$adjDatamin + $getleaveLookback['LDuration'];
											$lookBackDur=0;
											$lookBackDur=$getleaveLookback['LDuration'];
											$dtfrom = $getleaveLookback['LStart'];
											$liloadj=0;
											$TypeOff = "Leave";	
											$adjmoney=0;
											insertdata($pdo,$ids,$TypeOff,$liloadj,$dtfrom,$ltype,$today,$adjmoney);
										}																							
									}
								 
							// end 06/12/2024
					    						
							//fetch data

							$stmsumup= $pdo->prepare("Select SUM(adjustment) as Adjustment from temp_payroll where Pdate=:pdate and EmpID=:id ");
							$stmsumup->bindParam(':pdate' , $ltype);
							$stmsumup->bindParam(':id' , $ids);
							$stmsumup->execute();				
							$PYRow = $stmsumup->fetch();				        
							$countleave = $stmsumup->rowCount();
							$TotalAdjMin = $PYRow['Adjustment'];
					
							
							$cntEntry=1;
							
							if($ids=="WeDoinc-012"){
									$TotalAdjMin=0;	
							}else{
								    	//New Validation no in for all cut off 8/13/2021
										$toff="Attendance";
										$checkForIn= $pdo->prepare("Select * from temp_payroll where Pdate=:pdate and EmpID=:id and (TypeOff=:toff or TypeOff='Leave' or TypeOff='Attendance-OB' or TypeOff='OB' )");
										$checkForIn->bindParam(':pdate' , $ltype);
										$checkForIn->bindParam(':id' , $ids);
										$checkForIn->bindParam(':toff' , $toff);
										$checkForIn->execute();		
										$entryRow = $checkForIn->fetch();				        
										$cntEntry = $checkForIn->rowCount();	
										
										
								    
							}
								//get employee minute rate
								$RatePerMin=((($basicwhole * 12)/$avgDaysyear)/ $hrsPerDay)/60;
								//rate per hour
								$RatePerHr=((($basicwhole * 12)/$avgDaysyear)/ $hrsPerDay);
								//adjustment money
								//adjpluss last cut off
								$adjDatamin=$adjDatamin * $RatePerMin;
		
								//new validation 10-3
								if($cntEntry==0){
									$Adjustment=$basichalf;
									$allowancehalf=0;	
								}else{
									$Adjustment= $TotalAdjMin * $RatePerMin;
								}
							
								//get ot
								getot($pdo);
								$ot=$otval;

								//print $ot;
								//13month
								$month13=($basichalf -$Adjustment);
								//gross
								$gross=($basichalf - $Adjustment) + $ot + $adjDatamin;
							
								//get allowance per min
								$allowancePerMin=((($allowancewhole * 12)/$avgDaysyear)/ $hrsPerDay)/60;
								//allowance adjustment
								$allowanceAdjusmentMoney= $TotalAdjMin * $allowancePerMin;

							    $adjustedAllowance=0;
								$adjustedAllowance= $allowancehalf;
								if($allowancehalf > 0){
										$adjustedAllowance = $allowancehalf - $allowanceAdjusmentMoney;
								}else{
									$adjustedAllowance=0;
								}

							getcinfo($pdo,$ltype,$GovDues);
							
							if($GovDues==1)	{
									getprevtax($pdo);//gross
									//get pagibig
									getpagibig($pdo ,$prevGross,$gross,$ids);
									//get philhealth
									getphilh($pdo,$prevGross,$gross,$ids);
									//get sss
									getsss($pdo,$prevGross,$gross,$ids);								
								}else{
									$PIEE=0;$PIER=0;$PHEE=0;$PHER=0;$SSEE=0;$SSER=0;								
								}
                            
							if($tax==1){
								//gettaxincom prev plus present
									getprevtax($pdo);
								//get taxable income
									$taxinc=((($gross-$SSEE)-$PHEE)-$PIEE) + $prevGross;
									
								//get income tax 
									gettaxincome($pdo,$incomeTax);
									$txinc=$incomeTax;
									//print  	$txinc;
									
								}
								else{
										//get current taxable income
										//get taxable income
										$taxinc=((($gross-$SSEE)-$PHEE)-$PIEE);
									}

									
							if($SalaryLoan=1){
								getloan($pdo);
								//get silloan if any
								$sl=$sil;
							}
							else{
								$sl=0;
							}
  
							//signal the system to find and get loan if loan value ==1
							if($Loan==1)	
								{
									getloan($pdo);
									//echo $sss;
									//get ssloan if any
									$sssloan=$sss;
									//get piloan if any
									$piloan=$pi;
								}
								else{
										//set default value of loans if loan==0		
										$sssloan=0;			
										$piloan=0;					
									}	
									//$adj2=0;
									//get adjustment 2;
									$PYOtherAdj2=0;
									getadjustment($pdo);
									$PYOtherAdj2=$adj2;
								    //net pay
								// 	$pnp=$gross-$sssloan-$incomeTax-$SSEE-$PHEE-$PIEE-$piloan-$sl;
										$pnp=$gross-$sssloan-$incomeTax-$SSEE-$PHEE-$PIEE-$piloan;
									$pr=$pnp-$sl + $adjustedAllowance + $PYOtherAdj2;	
									
									$padj=$sl;	
									//insert data
									
									try {
                                            	insertpayrol($pdo,$ids,$ltype,$dtfr,$dtto,$basichalf,$adjustedAllowance,$RatePerHr,
												$tah,$tbh,$gross,$ot,$Adjustment,$TotalAdjMin,$SSEE,$sssloan,$PHEE,
												$PIEE,$piloan,$taxinc,$incomeTax,$padj,$pnp,$pr,$month13,$PYOtherAdj2,
												$SSER,$PHER,$allowanceAdjusmentMoney,$PIER,$adjDatamin);
                                        }
                                        catch (exception $e) {
                                             echo 'Message: '.$ids .$e->getMessage();
                                        }
									
												
										
										$SSER=0;
										$PHER=0;
										$PIER=0;
										$allowanceAdjusmentMoney=0;
										 $adjDatamin=0;
										

						}else{
							//feth the teamp data
							// 			$stmsumup= $pdo->prepare("Select SUM(adjustment) as Adjustment from temp_payroll where Pdate=:pdate and EmpID=:id ");
							// 			$stmsumup->bindParam(':pdate' , $ltype);
							// 			$stmsumup->bindParam(':id' , $ids);
							// 			$stmsumup->execute();				
							// 			$PYRow1 = $stmsumup->fetch();	
							// 			$RatePerMinic=(($perHourPay)/60);
							// 			$RatePerHr=$perHourPay;
							// 			$countentry = $stmsumup->rowCount();
										
							//          $PYOtherAdj2=0;
							// 			getadjustment($pdo);
							// 			$PYOtherAdj2=$adj2;
										
							// 			if($countentry>0){
							// 				print $dtfrom;
							// 				$PYRow1 = $PYRow['Adjustment'];
							// 			}else{
							// 				$PYRow1 = 0;
							// 			}
							// 			if($calrow['EmpStatID']==3 or $calrow['EmpStatID']==4){
							// 				$taxinc =0;
							// 				$incomeTax=0;
							// 				$pr=$PYRow1 * $RatePerMinic + $PYOtherAdj2;

							// 			}	elseif($calrow['EmpStatID']==5){
							// 				//code here
							// 				$akbasicpermin=($basicwhole / 160)/60;
							// 				$taxinc =$PYRow1 * $akbasicpermin;
							// 				$incomeTax=$taxinc * 0.03;
							// 				$pr=$taxinc-$incomeTax + $PYOtherAdj2;
											
							// 			}
										
							// 			else{
							// 				$taxinc =$PYRow1 * $RatePerMinic;
							// 				$incomeTax=$taxinc * 0.03;
							// 				$pr=$taxinc-$incomeTax + $PYOtherAdj2;;
							// 			}

					
							// 			$basichalf=0;$adjustedAllowance=0;$gross=0;$TotalAdjMin=0;$PHEE=0;$PIEE=0;$Adjustment=0;$pnp=0;$SSEE=0;

							// 			insertpayrol($pdo,$ids,$ltype,$dtfr,$dtto,$basichalf,$adjustedAllowance,$RatePerHr,
							// 			$tah,$tbh,$gross,$ot,$Adjustment,$TotalAdjMin,$SSEE,$sssloan,$PHEE,
							// 			$PIEE,$piloan,$taxinc,$incomeTax,$padj,$pnp,$pr,$month13,$PYOtherAdj2,
							// 			$SSER,$PHER,$allowanceAdjusmentMoney,$PIER);	
							// 		$SSER=0;
							// 		$PHER=0;
							// 		$PIER=0;
							// 		$allowanceAdjusmentMoney=0;
							// 		$akbasicpermin=0;

					}
    		}
		}
		
function getot(PDO $pdo){
     
	try {
		global $otval,$ids,$dtfr,$dtto;
		$dtto1=date("Y-m-d", strtotime($dtto . " + 1 days"));  

		$otval=0;
		$sql = "SELECT sum(OTPay) as sumotpay FROM otattendancelog where EmpID=:id and (TimeIn BETWEEN :d1 and :d2) and Status=4";
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(':id' , $ids);
		$stmt->bindParam(':d1' , $dtfr);
		$stmt->bindParam(':d2' , $dtto1);
		$stmt->execute();
		$count = $stmt->rowCount();
		if($count>0){
			$rowdata = $stmt->fetch();
			$otval=number_format((float)$rowdata['sumotpay'], 2, '.', '');
		}
	}

	catch (customException $e) {
	//display custom message
	print $e->errorMessage();
	}
}
 
//function get adjustment
function getadjustment(PDO $pdo){
    global $adj2,$ids,$ltype;
    $adj2=0;
    $sql = "SELECT * FROM adjusment2 where EmpID=:id and pdate=:pdate";
    	$getadj2 = $pdo->prepare($sql);
    	$getadj2->bindParam(':id' , $ids);
    	$getadj2->bindParam(':pdate' , $ltype);
    	$getadj2->execute();
    	$countadj2 = $getadj2->rowCount();
    	if($countadj2>0){
    		while ( $adj2row = $getadj2->fetch()) {
    			$adj2=$adj2+$adj2row['Amount'];
    		}
    	}
}	
//function get loan dynamicaly		
function getloan(PDO $pdo){
	global $sss,$ids,$pi,$sil;

	$sss=0;
	$pi=0;
	$sil=0;
	$statusloan="ACTIVE";	
	$sql = "SELECT * FROM silloan where loanStatus=:statusloan and loanEmpID=:id";
	$stmt = $pdo->prepare($sql);
	$stmt->bindParam(':id' , $ids);
	$stmt->bindParam(':statusloan' , $statusloan);
	$stmt->execute();
	//$loans = $stmt->fetch();
	$countloan = $stmt->rowCount();
	if($countloan>0)
		{
			while ( $loans = $stmt->fetch()) 
				{
					if($loans['LoanType']=="SSS"){
						$sss=$loans['loanAmount'];
					}

					elseif($loans['LoanType']=="PI"){
						$pi=$loans['loanAmount'];
					}
					elseif($loans['LoanType']=="SIL"){
						$sil=$loans['loanAmount'];
					}
					
				}
		}	
}

function gettaxincome(PDO $pdo,$incomeTax){
	global $incomeTax,$ids,$taxdesc,$taxcatid,$taxinc,$basicwhole;
	//$prevTaxdata=0;
	$taxdesc=0;
	$incomeTax=0;
	//$taxinc=0;
	//get tax cat desc

	if ($taxcatid=='0' or $taxcatid==NULL)
	{}
	else
	{
		$sql = "SELECT * FROM taxtablecat where TaxCatID=:taxid ";
		$stmt = $pdo->prepare($sql);
		$stmt->bindParam(':taxid' , $taxcatid);
		$stmt->execute();
		$taxDescRow = $stmt->fetch();
		$countTaxDesc = $stmt->rowCount();

		if($countTaxDesc<>0) {
			$taxdesc=$taxDescRow['TaxCatDesc'];
		   
		}

    }

	//get taxincome
	$sql = "SELECT * FROM taxtable where  (:taxableincome >=RangeFrom and :taxableincome <= RangeTo) and TaxCatDesc=:taxDescCat";
	$stmt = $pdo->prepare($sql);
	$stmt->bindParam(':taxableincome' , $taxinc);
	$stmt->bindParam(':taxDescCat' , $taxdesc);
	$stmt->execute();
	$taxIncomeRow = $stmt->fetch();
	$countTI = $stmt->rowCount();
	if($countTI>0)
	{
		$incomeTax=$taxIncomeRow['WTax']+ ( $taxinc - $taxIncomeRow['RangeFrom'] ) * $taxIncomeRow['Percentage']  ;
		// if($ids=="WeDoinc-012"){

		// }
		
    }
    
    // else{print $taxinc. "-" .$taxdesc. "--" . $taxcatid;}
}
function getprevtax(PDO $pdo){
	global 	$prevGross,$ids;
	$prevGross=0;	
	$sql = "SELECT * FROM payrol where PYEmpID=:id order by PYid desc";
	$stmt = $pdo->prepare($sql);
	$stmt->bindParam(':id' , $ids);
	$stmt->execute();
	$prevtax = $stmt->fetch();
	$countprevtax = $stmt->rowCount();

	if($countprevtax>0)
	{
		$prevGross=$prevtax['PYGross'];
		
    }
}

function insertpayrol(PDO $pdo,$ids,$ltype,$dtfr,$dtto,$basichalf,$adjustedAllowance,$RatePerHr,$tah,$tbh,$gross,$ot,$Adjustment,$TotalAdjMin,$SSEE,
    $sssloan,$PHEE,$PIEE,$piloan,$taxinc,$incomeTax,$padj,$pnp,$pr,$month13,$PYOtherAdj2,$SSER,$PHER,$allowanceAdjusmentMoney,$PIER,$adjDatamin){
	
	global $tah,$tbh,$gross,$ot,$sssloan,$piloan,$taxinc,$padj,$pnp,$pr,$month13,$PYOtherAdj2;
    $sql = "INSERT INTO payrol (PYEmpID,PYDate,PYDateFrom,PYDateTo,PYBasic,PYAllowance,PYHourRate,PYTAH,PYTBH,PYGross,PYOverTime,PYAdj,
                                PYAdjMin,PYSSS,PYSSSLoan,PYPhilHealth,PYPagibig,PYPILoan,PYTaxIncome,PYIncTax,PYOtherAdj,PYNetPay,
                                PYRecivable,13thMon,PYOtherAdj2,PYssser,PYphiler,PYallowadj,PYpier,adjFromPCO) 
								VALUES (:id,:pdate,:dfrom,:dto,:basic,:allowance,:hrate,:tah,:tbh,:gross,
					           :ot,:adjustment,:adjmin,:sss,:sssloan,:phil,:pi,:piloan,
					           :taxinc,:inctax,:padj,:pnp,:pr,:month13,:adj2,:ssser,:philer,:allowadj,:pier,:adjFromPCO)";
       $stmt = $pdo->prepare($sql);
       $stmt->bindParam(':id' , $ids);
       $stmt->bindParam(':pdate', $ltype);
       $stmt->bindParam(':dfrom', $dtfr);
       $stmt->bindParam(':dto', $dtto);
       $stmt->bindParam(':basic', $basichalf);
       $stmt->bindParam(':allowance',$adjustedAllowance);
	   $stmt->bindParam(':hrate', $RatePerHr);
       $stmt->bindParam(':tah', $tah);
       $stmt->bindParam(':tbh', $tbh);
       $stmt->bindParam(':gross',   $gross);
	   $stmt->bindParam(':ot', $ot);
       $stmt->bindParam(':adjustment', $Adjustment);
       $stmt->bindParam(':adjmin',   $TotalAdjMin);   
       $stmt->bindParam(':sss',   $SSEE);
	   $stmt->bindParam(':sssloan', $sssloan);
       $stmt->bindParam(':phil', $PHEE);
       $stmt->bindParam(':pi',   $PIEE);
	   $stmt->bindParam(':piloan', $piloan);               
       $stmt->bindParam(':taxinc',   $taxinc);
       $stmt->bindParam(':inctax',   $incomeTax);
	   $stmt->bindParam(':padj',   $padj);
       $stmt->bindParam(':pnp',   $pnp);
       $stmt->bindParam(':pr',   $pr);
	   $stmt->bindParam(':month13',   $month13);
       $stmt->bindParam(':adj2',   $PYOtherAdj2);
       $stmt->bindParam(':ssser',   $SSER);
       $stmt->bindParam(':philer',   $PHER);
       $stmt->bindParam(':allowadj',   $allowanceAdjusmentMoney);
       $stmt->bindParam(':pier',   $PIER);
       $stmt->bindParam(':adjFromPCO',   $adjDatamin);
       $stmt->execute();
} 

function getcinfo(PDO $pdo,$ltype)
{
	global $SalaryLoan,$GovDues,$tax,$Cstart,$Cend,$PYDate,$Loan;
	$Cstart=0;$Cend=0;$PYDate=0;$Loan=0;$tax=0;
	$dayt=date("d", strtotime($ltype));


	$sql = "SELECT * FROM cutoffinfo where PYDate=:pdate";
	$stmt = $pdo->prepare($sql);
	$stmt->bindParam(':pdate', $dayt);
	$stmt->execute();
	$cirow = $stmt->fetch();
	$countci = $stmt->rowCount();
	if($countci>0)
	{
		
		$Cstart=$cirow['Cstart'];
		$Cend=$cirow['Cend'];
		$PYDate=$cirow['PYDate'];
		$Loan=$cirow['Loan'];
		$tax=$cirow['Tax'];
		$GovDues=$cirow['GovDues'];
		$SalaryLoan=$cirow['SL'];
		
	}

}

 function insertdata(PDO $pdo,$ids,$TypeOff,$liloadj,$dtfrom,$ltype,$today,$adjmoney)
{
  $adj=$liloadj*60;
  $sql = "INSERT INTO temp_payroll (EmpID,TypeOff,adjustment,Date,Pdate,DateImputed,adjmin) VALUES (:id,:is,:lid,:fd,:df,:pur,:adjmin)";
               $stmt = $pdo->prepare($sql);
               $stmt->bindParam(':id' , $ids);
               $stmt->bindParam(':is', $TypeOff);
               $stmt->bindParam(':lid', $adj);
               $stmt->bindParam(':fd', $dtfrom);
               $stmt->bindParam(':df', $ltype);
               $stmt->bindParam(':pur', $today);
			   $stmt->bindParam(':adjmin', $adjmoney);
               $stmt->execute();
}
function getpagibig(PDO $pdo ,$prevGross,$gross,$ids)
{
	global $PIEE,$PIER;
	$PIEE=0;
	$PIER=0;
	
	$forPIGross=$prevGross + $gross;
	
	$sql = "SELECT * FROM pagibig";
	$stmt = $pdo->prepare($sql);
	$stmt->execute();
	$pirow = $stmt->fetch();
	$countpi = $stmt->rowCount();
	
    if($forPIGross>0){
        
    	if($countpi>0){
    	    if($ids=="WeDoinc-012"){
                $PIEE=1000;
    		    $PIER=1000;
    		}else{
    		    $PIEE=$pirow['EE'];
    		    $PIER=$pirow['ER'];	
    		}
    	}
    }else{
	    $PIEE=0;
		$PIER=0;	
	}
	

}
function getphilh(PDO $pdo ,$prevGross,$gross,$ids)
{
	global $PHEE,$PHER,$dd;
	//from gross
	//echo getprevtax($pdo);
    $prevGross= $prevGross + $gross;	
	$PHEE=0;
	$PHER=0;
	// $sql = "SELECT * FROM `philhealth` where :basicpay >=SalaryFrom and :basicpay <= SalaryTo ";
	// $stmt = $pdo->prepare($sql);
	// $stmt->bindParam(':basicpay',$prevGross);
	// $stmt->execute();
	// $PHrow = $stmt->fetch();
	// $countph = $stmt->rowCount();

	// if($countph>0)
	// 	{   update the philhealth .5 * 2024
	        if($prevGross>10000){
	            $PHEE=($prevGross * 0.05)/2;
	             $PHER=($prevGross * 0.05)/2;
	        }elseif($prevGross==0){
	           
	             
	             $PHEE=0;
	             $PHER=0;
	        }else{
	             $PHEE=400/2;
	             $PHER=400/2;
	        }
			 
			
		//}
		

}
function getsss(PDO $pdo ,$prevGross,$gross,$ids)
{
	global $SSEE,$SSER;	
    //from gross
	$forSSGross=$prevGross + $gross;
	$sql = "SELECT * FROM sss where :basicpay >=SalaryFrom and :basicpay <= SalaryTo ";
	$stmt = $pdo->prepare($sql);
	$stmt->bindParam(':basicpay',$forSSGross);
	$stmt->execute();
	$SSrow = $stmt->fetch();
	$countss = $stmt->rowCount();

// 	if($countss>0)
// 		{
// 			 $SSEE=$SSrow['SSEE'];
// 			 $SSER=$SSrow['SSER'];	
// 		}else{
// 			$SSEE=0;
// 			$SSER=0;	
// 		}
		
		if($countss>0)
		{
			 $SSEE=$SSrow['SSEE']+ $SSrow['WISPEE'];
			 $SSER=$SSrow['SSER']+ $SSrow['WISPER'];	
		}else{
			$SSEE=0;
			$SSER=0;	
		}
		


}
	
        
 ?>