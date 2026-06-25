<?php 
include 'w_conn.php';
// $conn = new mysqli($servername, $username, $password, $db);
// $name=$_POST['name'];

// $sql="INSERT INTO `test` (`nm`) VALUES ('$name')";
// if ($conn->query($sql) === TRUE) {
//     echo "data inserted";
try{
$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }
catch(PDOException $e)
   {
die("ERROR: Could not connect. " . $e->getMessage());
   }

try {
  
   
   $id=$_POST['empidn'];
   $fn=$_POST['empfn'];
   $mn=$_POST['empmn'];
   $ln=$_POST['empln'];
   $suff=$_POST['empsuff'];
   $pos=$_POST['empposition'];
   $stats=$_POST['empst'];
   $cid=$_POST['empcid'];
   
   $dor=$_POST['dorInput'];

   $sql = "UPDATE employees SET EmpFN='$fn',EmpMN='$mn',EmpSuffix='$suff',EmpLN='$ln',PosID='$pos',EmpStatusID='$stats',EmployeeIDNumber='$cid' WHERE EmpID='$id'";
   $stmt = $pdo->prepare($sql);
   $stmt->execute(); 

   	$add1=$_POST['pempstreetno'];
   	$dis=$_POST['pempdistrict'];
   	$ct=$_POST['pempcity'];
   	$prov=$_POST['pempprovince'];
   	$zip=$_POST['pempzipcode'];
   	$ctry=$_POST['pempcountry'];
   	$pnum=$_POST['pempn0'];
   	$dob=$_POST['pempdob'];
   	$gen=$_POST['pempgender'];
   	$empcs=$_POST['pempcs'];
   	$phnum=$_POST['emphomenumber'];
   	$eadd=$_POST['pempeadd'];
   	$ppno=$_POST['pempppno'];
   	$ped=$_POST['pemppped'];
   	$pia=$_POST['pempppia'];
  	   $sss=$_POST['pempsss'];
   	$tin=$_POST['pemptin'];
   	$hmonum=$_POST['emphmono'];
   	$emppp=$_POST['pemppp'];
   	$psd=$_POST['pemppsd'];
   	$dept=$_POST['pemppdept'];
   	$empppd=$_POST['pempppd'];
   	$empag=$_POST['pemppagibig'];
   	$phno=$_POST['pempphno'];
   	$umid=$_POST['pempumid'];
   	$targetPath="assets/images/profiles/" . $_POST['empidn'] . ".jpg" ;
   	$cit=$_POST['empcitizen'];
   	$rel=$_POST['empreligion'];

   	$sql = "UPDATE empprofiles SET EmpAddress1='$add1',EmpAddDis='$dis',EmpAddCity='$ct',EmpAddProv='$prov',EmpAddZip='$zip',EmpAddCountry='$ctry',EmpPhone='$pnum',EmpDOB='$dob',EmpGender='$gen',EmpCS='$empcs',EmpMobile='$phnum',EmpEmail='$eadd',EmpPPNo='$ppno',EmpPPED='$ped',EmpPPIA='$pia',EmpSSS='$sss',EmpTIN='$tin',EmpHMONumber='$hmonum',EmpPP='$emppp',EmpPPSD='$psd',EmpPPDept='$dept',EmpPPPos='$empppd',EmpPINo='$empag',EmpPHNo='$phno',EmpUMIDNo='$umid',EmpPPath='$targetPath',EmpCitezen='$cit',EmpReligion='$rel' WHERE EmpID='$id'";
   $stmt = $pdo->prepare($sql);
   $stmt->execute(); 

  
   $statID="2";
   $us = ucfirst($_POST['empfn'][0]) . ucfirst($_POST['empln']);
   $pass=ucfirst($_POST['empln']);
   $epass=password_hash($pass, PASSWORD_DEFAULT);
  

   $pr="Primary";
   //update educ background primary
   $sql = "UPDATE empeducationalbackground SET Name_of_School=:emppnos,Year_Started=:yrst,Year_End=:yrgrd,School_Address=:schlad WHERE Program='Primary' and EmpID=:empidn";
   $stmt = $pdo->prepare($sql);
   $stmt->bindParam(':empidn', $_POST['empidn']);
   $stmt->bindParam(':emppnos', $_POST['p_nameofschool']);
   $stmt->bindParam(':yrst', $_POST['p_yearstarted']);
   $stmt->bindParam(':yrgrd', $_POST['p_yeargraduated']);
   $stmt->bindParam(':schlad', $_POST['p_schooladdress']);
   $stmt->execute(); 

   $pr="Primary";
   //update educ background secondary
   $sql = "UPDATE empeducationalbackground SET Name_of_School=:emppnos,Year_Started=:yrst,Year_End=:yrgrd,School_Address=:schlad WHERE Program='Secondary' and EmpID=:empidn";
   $stmt = $pdo->prepare($sql);
   $stmt->bindParam(':empidn', $_POST['empidn']);
   $stmt->bindParam(':emppnos', $_POST['s_nameofschool']);
   $stmt->bindParam(':yrst', $_POST['s_yearstarted']);
   $stmt->bindParam(':yrgrd', $_POST['s_yeargraduated']);
   $stmt->bindParam(':schlad', $_POST['s_schooladdress']);
   $stmt->execute(); 

   $pr="Primary";
   //update educ background secondary
   $sql = "UPDATE empeducationalbackground SET Name_of_School=:emppnos,Year_Started=:yrst,Year_End=:yrgrd,School_Address=:schlad WHERE Program='Tertiary' and EmpID=:empidn";
   $stmt = $pdo->prepare($sql);
   $stmt->bindParam(':empidn', $_POST['empidn']);
   $stmt->bindParam(':emppnos', $_POST['t_nameofschool']);
   $stmt->bindParam(':yrst', $_POST['t_yearstarted']);
   $stmt->bindParam(':yrgrd', $_POST['t_yeargraduated']);
   $stmt->bindParam(':schlad', $_POST['t_schooladdress']);
   $stmt->execute(); 

   $EmpID=$_POST['empidn'];
   $empis=$_POST['empis'];
   $empcom=$_POST['empcompany'];
   $empdh=$_POST['empdatehired'];
   $dr=$_POST['empdateresigned'];
   $empclass=$_POST['empclassification'];
   $empage=$_POST['empagency'];
	$emphmo=$_POST['emphmo'];
   $EmpdepID=$_POST['empdep'];
   
   
    // $sql="UPDATE empdetails SET EmpISID='$empis',EmpRoleID='$roleid',EmpdepID='$EmpdepID',EmpCompID='$empcom',EmpDateHired='$empdh',EmpDateResigned='$dr',EmpStatID='$empclass',AgencyID='$empage',HMO_ID='$emphmo',EmpDOR='$dor' WHERE EmpID='$EmpID'";
    $sql="UPDATE empdetails SET EmpDOR='$dor', EmpISID='$empis',EmpdepID='$EmpdepID',EmpCompID='$empcom',EmpDateHired='$empdh',EmpDateResigned='$dr',EmpStatID='$empclass',AgencyID='$empage',HMO_ID='$emphmo' WHERE EmpID='$EmpID'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(); 

   	$basic=$_POST['empbasic'];
   	$allow=$_POST['empallowance'];
   	$hourlyr=$_POST['emphourlyrate'];

    $sql="UPDATE empdetails2 SET EmpBasic='$basic',EmpAllowance='$allow',EmpHRate='$hourlyr' where EmpID='$EmpID'";
   	$stmt = $pdo->prepare($sql);
   	$stmt->execute(); 
      $mn="Monday";
      $sql = "select * from workdays WHERE  empid=:empide2 and Day_s=:empday";
      $stmtmon = $pdo->prepare($sql);
      $stmtmon->bindParam(':empide2', $EmpID);
      $stmtmon->bindParam(':empday',  $mn);
      $stmtmon->execute();
      $rowCountmon=$stmtmon->rowCount();
        if ($rowCountmon>=1){
               $mn="Monday";
      $sql = "UPDATE workdays SET SchedTime=:empmon WHERE  empid=:empide2 and Day_s=:empday";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $EmpID);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschmon']);
      $stmt->execute();
        }
        else{
            
      $mn="Monday";
      $sql = "INSERT INTO workdays (empid,Day_s,SchedTime) VALUES (:empide2,:empday,:empmon)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $_POST['empidn']);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschmon']);
      $stmt->execute();
        }

             $mn="Monday";
          $sql = "select * from workdays WHERE  empid=:empide2 and Day_s=:empday";
      $stmtmon = $pdo->prepare($sql);
      $stmtmon->bindParam(':empide2', $EmpID);
      $stmtmon->bindParam(':empday',  $mn);
      $stmtmon->execute();
      $rowCountmon=$stmtmon->rowCount();
        if ($rowCountmon>=1){
     
      $sql = "UPDATE workdays SET SchedTime=:empmon WHERE  empid=:empide2 and Day_s=:empday";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $EmpID);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschmon']);
      $stmt->execute();
        }
        else{
            
      $mn="Monday";
      $sql = "INSERT INTO workdays (empid,Day_s,SchedTime) VALUES (:empide2,:empday,:empmon)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $_POST['empidn']);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschmon']);
      $stmt->execute();
        }






      $mn="Tuesday";
             $sql = "select * from workdays WHERE  empid=:empide2 and Day_s=:empday";
      $stmtmon = $pdo->prepare($sql);
      $stmtmon->bindParam(':empide2', $EmpID);
      $stmtmon->bindParam(':empday',  $mn);
      $stmtmon->execute();
      $rowCountmon=$stmtmon->rowCount();
        if ($rowCountmon>=1){
     
     $sql = "UPDATE workdays SET SchedTime=:empmon WHERE  empid=:empide2 and Day_s=:empday";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $EmpID);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschtues']);
      $stmt->execute();

        }
        else{
            
      $sql = "INSERT INTO workdays (empid,Day_s,SchedTime) VALUES (:empide2,:empday,:empmon)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $_POST['empidn']);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschtues']);
      $stmt->execute();
        }
    
   
      $mn="Wednesday";
               $sql = "select * from workdays WHERE  empid=:empide2 and Day_s=:empday";
      $stmtmon = $pdo->prepare($sql);
      $stmtmon->bindParam(':empide2', $EmpID);
      $stmtmon->bindParam(':empday',  $mn);
      $stmtmon->execute();
      $rowCountmon=$stmtmon->rowCount();
        if ($rowCountmon>=1){
     
     $sql = "UPDATE workdays SET SchedTime=:empmon WHERE  empid=:empide2 and Day_s=:empday";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $EmpID);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschwed']);
      $stmt->execute();

        }
        else{
            
      $sql = "INSERT INTO workdays (empid,Day_s,SchedTime) VALUES (:empide2,:empday,:empmon)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $_POST['empidn']);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschwed']);
      $stmt->execute();
        }
      

      $mn="Thursday";
                    $sql = "select * from workdays WHERE  empid=:empide2 and Day_s=:empday";
      $stmtmon = $pdo->prepare($sql);
      $stmtmon->bindParam(':empide2', $EmpID);
      $stmtmon->bindParam(':empday',  $mn);
      $stmtmon->execute();
      $rowCountmon=$stmtmon->rowCount();
        if ($rowCountmon>=1){
     
     $sql = "UPDATE workdays SET SchedTime=:empmon WHERE  empid=:empide2 and Day_s=:empday";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $EmpID);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschthu']);
      $stmt->execute();

        }
        else{
            
      $sql = "INSERT INTO workdays (empid,Day_s,SchedTime) VALUES (:empide2,:empday,:empmon)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $_POST['empidn']);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschthu']);
      $stmt->execute();
        }
      


      $mn="Friday";
                       $sql = "select * from workdays WHERE  empid=:empide2 and Day_s=:empday";
      $stmtmon = $pdo->prepare($sql);
      $stmtmon->bindParam(':empide2', $EmpID);
      $stmtmon->bindParam(':empday',  $mn);
      $stmtmon->execute();
      $rowCountmon=$stmtmon->rowCount();
        if ($rowCountmon>=1){
     
     $sql = "UPDATE workdays SET SchedTime=:empmon WHERE  empid=:empide2 and Day_s=:empday";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $EmpID);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschfri']);
      $stmt->execute();

        }
        else{
            
      $sql = "INSERT INTO workdays (empid,Day_s,SchedTime) VALUES (:empide2,:empday,:empmon)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $_POST['empidn']);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschfri']);
      $stmt->execute();
        }
 

      $mn="Saturday";
                      $sql = "select * from workdays WHERE  empid=:empide2 and Day_s=:empday";
      $stmtmon = $pdo->prepare($sql);
      $stmtmon->bindParam(':empide2', $EmpID);
      $stmtmon->bindParam(':empday',  $mn);
      $stmtmon->execute();
      $rowCountmon=$stmtmon->rowCount();
        if ($rowCountmon>=1){
     
     $sql = "UPDATE workdays SET SchedTime=:empmon WHERE  empid=:empide2 and Day_s=:empday";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $EmpID);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschsat']);
      $stmt->execute();

        }
        else{
            
      $sql = "INSERT INTO workdays (empid,Day_s,SchedTime) VALUES (:empide2,:empday,:empmon)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $_POST['empidn']);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschsat']);
      $stmt->execute();
        }
      
 

      $mn="Sunday";
                            $sql = "select * from workdays WHERE  empid=:empide2 and Day_s=:empday";
      $stmtmon = $pdo->prepare($sql);
      $stmtmon->bindParam(':empide2', $EmpID);
      $stmtmon->bindParam(':empday',  $mn);
      $stmtmon->execute();
      $rowCountmon=$stmtmon->rowCount();
        if ($rowCountmon>=1){
     
     $sql = "UPDATE workdays SET SchedTime=:empmon WHERE  empid=:empide2 and Day_s=:empday";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $EmpID);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschsun']);
      $stmt->execute();

        }
        else{
            
      $sql = "INSERT INTO workdays (empid,Day_s,SchedTime) VALUES (:empide2,:empday,:empmon)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $_POST['empidn']);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschsun']);
      $stmt->execute();
        }
      
 

                     $id=$_SESSION['id'];
                       $ch="Maintenance : Updated Employee Information";
                  // insert into dars
                      $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
                     $stmt = $pdo->prepare($sql);
                     $stmt->bindParam(':id' , $EmpID);
                     $stmt->bindParam(':empact', $ch);
                     $stmt->execute(); 

} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}                      
                     
   	
?>