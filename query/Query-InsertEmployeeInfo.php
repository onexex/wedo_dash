<?php 
include 'w_conn.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
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
// insert into employees

$sql = "INSERT INTO employees (EmpID,EmpSuffix,EmpFN,EmpMN,EmpLN,PosID,EmployeeIDNumber) VALUES (:empidn,:empsu,:empfn,:empmn,:empln,:empposition,:empcid)";
   $stmt = $pdo->prepare($sql);
   $stmt->bindParam(':empidn', $_POST['empidn']);
   $stmt->bindParam(':empfn', $_POST['empfn']);
   $stmt->bindParam(':empmn', $_POST['empmn']);
   $stmt->bindParam(':empln', $_POST['empln']);
   $stmt->bindParam(':empsu', $_POST['empsuff']);
   $stmt->bindParam(':empposition', $_POST['empposition']);
   $stmt->bindParam(':empcid', $_POST['EmployeeIDNumber']);
   $stmt->execute(); 
  $pr="Primary";
   //insert educ background primary
      $sql = "INSERT INTO empeducationalbackground (EmpID,Name_of_School,Program,Year_Started,Year_End,School_Address) VALUES (:empidn,:emppnos,:prog,:yrst,:yrgrd,:schlad)";
   $stmt = $pdo->prepare($sql);
   $stmt->bindParam(':empidn', $_POST['empidn']);
   $stmt->bindParam(':emppnos', $_POST['p_nameofschool']);
   $stmt->bindParam(':prog', $pr);
   $stmt->bindParam(':yrst', $_POST['p_yearstarted']);
   $stmt->bindParam(':yrgrd', $_POST['p_yeargraduated']);
   $stmt->bindParam(':schlad', $_POST['p_schooladdress']);
   $stmt->execute(); 
   $pr="Secondary";
    //insert educ background secondary
      $sql = "INSERT INTO empeducationalbackground (EmpID,Name_of_School,Program,Year_Started,Year_End,School_Address) VALUES (:empidn,:emppnos,:prog,:yrst,:yrgrd,:schlad)";
   $stmt = $pdo->prepare($sql);
   $stmt->bindParam(':empidn', $_POST['empidn']);
   $stmt->bindParam(':emppnos', $_POST['s_nameofschool']);
   $stmt->bindParam(':prog', $pr);
   $stmt->bindParam(':yrst', $_POST['s_yearstarted']);
   $stmt->bindParam(':yrgrd', $_POST['s_yeargraduated']);
   $stmt->bindParam(':schlad', $_POST['s_schooladdress']);
   $stmt->execute(); 
   $pr="Tertiary";
    //insert educ background tertiary
      $sql = "INSERT INTO empeducationalbackground (EmpID,Name_of_School,Program,Year_Started,Year_End,School_Address) VALUES (:empidn,:emppnos,:prog,:yrst,:yrgrd,:schlad)";
   $stmt = $pdo->prepare($sql);
   $stmt->bindParam(':empidn', $_POST['empidn']);
   $stmt->bindParam(':emppnos', $_POST['t_nameofschool']);
   $stmt->bindParam(':prog', $pr);
   $stmt->bindParam(':yrst', $_POST['t_yearstarted']);
   $stmt->bindParam(':yrgrd', $_POST['t_yeargraduated']);
   $stmt->bindParam(':schlad', $_POST['t_schooladdress']);
   $stmt->execute(); 
   //

  $sql = "INSERT INTO empprofiles (EmpID,EmpAddress1,EmpAddDis,EmpAddCity,EmpAddProv,EmpAddZip,EmpAddCountry,EmpPhone,EmpDOB,EmpGender,EmpCS,EmpMobile,EmpEmail,EmpPPNo,EmpPPED,EmpPPIA,EmpSSS,EmpTIN,EmpHMONumber,EmpPP,EmpPPSD,EmpPPDept,EmpPPPos,EmpPINo,EmpPHNo,EmpUMIDNo,EmpPPath,EmpCitezen,EmpReligion) VALUES (:empidn,:pempadd,:pempadddis,:pempaddcity,:pempaddprov,:pempaddzip,:pempaddcountry,:pempno,:pempdob,:pempgender,:pempcs,:emphomenumber,:pempeadd,:pempppno,:pemppped,:pempppia,:pempsss,:pemptin,:hmono,:pemppp,:pemppsd,:pemppdept,:pempppd,:pemppagibig,:pempphno,:pempumid,:pathpic,:pempcitizen,:pempReligion)";

$targetPath="assets/images/profiles/" . $_POST['empidn'] . ".jpg" ;

   $stmt = $pdo->prepare($sql);
   $stmt->bindParam(':empidn', $_POST['empidn']);
   $stmt->bindParam(':pempadd', $_POST['pempstreetno']);
   $stmt->bindParam(':pempadddis', $_POST['pempdistrict']);
   $stmt->bindParam(':pempaddcity', $_POST['pempcity']);
   $stmt->bindParam(':pempaddprov', $_POST['pempprovince']);
   $stmt->bindParam(':pempaddzip', $_POST['pempzipcode']);
   $stmt->bindParam(':pempaddcountry', $_POST['pempcountry']);
   $stmt->bindParam(':pempno', $_POST['pempn0']);
   $stmt->bindParam(':pempdob', $_POST['pempdob']);
   $stmt->bindParam(':pempgender', $_POST['pempgender']);
   $stmt->bindParam(':pempcs', $_POST['pempcs']);
   $stmt->bindParam(':emphomenumber', $_POST['emphomenumber']);
   $stmt->bindParam(':pempeadd', $_POST['pempeadd']);
   $stmt->bindParam(':pempppno', $_POST['pempppno']);
   $stmt->bindParam(':pemppped', $_POST['pemppped']);
   $stmt->bindParam(':pempppia', $_POST['pempppia']);
   $stmt->bindParam(':pempsss', $_POST['pempsss']);
   $stmt->bindParam(':pemptin', $_POST['pemptin']);
   $stmt->bindParam(':hmono', $_POST['emphmo']);
   $stmt->bindParam(':pemppp', $_POST['pemppp']);
   $stmt->bindParam(':pemppsd', $_POST['pemppsd']);
   $stmt->bindParam(':pemppdept', $_POST['pemppdept']);
   $stmt->bindParam(':pempppd', $_POST['pempppd']);
   $stmt->bindParam(':pemppagibig', $_POST['pemppagibig']);
   $stmt->bindParam(':pempphno', $_POST['pempphno']);
   $stmt->bindParam(':pempumid', $_POST['pempumid']);
   $stmt->bindParam(':pathpic', $targetPath);
   $stmt->bindParam(':pempcitizen', $_POST['empcitizen']);
   $stmt->bindParam(':pempReligion', $_POST['empreligion']);
   $stmt->execute();


  
   
      $sql = "INSERT INTO empdetails2 (EmpID,EmpBasic,EmpAllowance,EmpHRate) VALUES (:empide,:empbasic,:empallow,:emphrate)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide', $_POST['empidn']);
      $stmt->bindParam(':empbasic', $_POST['empbasic']);
      $stmt->bindParam(':empallow', $_POST['empallowance']);
      $stmt->bindParam(':emphrate', $_POST['emphourlyrate']);
      $stmt->execute();     
                        
      $sql = "INSERT INTO accessrights (EmpID) VALUES (:empide2)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $_POST['empidn']);
      $stmt->execute();
   
      $roleid="3"; 
      $statID="2";
      $us = ucfirst($_POST['empfn'][0]) . ucfirst($_POST['empln']);
      $pass=ucfirst("p@zzword");
      $epass=password_hash($pass, PASSWORD_DEFAULT);

      $sql = "INSERT INTO empdetails (EmpID,EmpUN,EmpPW,EmpRoleID,EmpISID,EmpdepID,EmpCompID,EmpDateHired,EmpDateResigned,EmpStatID,AgencyID,HMO_ID) VALUES (:empid,:empun,:emppw,:id,:empis,:emddid,:empcompid,:empdth,:empdtr,:empclassification,:agency,:hmo)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empid', $_POST['empidn']);
      $stmt->bindParam(':empun', $_POST['uname']);
      $stmt->bindParam(':emppw', $epass);
      $stmt->bindParam(':id', $roleid); 
      $stmt->bindParam(':empis', $_POST['empis']); 
      $stmt->bindParam(':emddid', $_POST['empdep']);
      $stmt->bindParam(':empcompid', $_POST['empcompany']);
      $stmt->bindParam(':empdth', $_POST['empdatehired']);
      $stmt->bindParam(':empdtr', $_POST['empdateresigned']);
      $stmt->bindParam(':empclassification', $_POST['empclassification']);
      $stmt->bindParam(':agency', $_POST['empagency']);
      $stmt->bindParam(':hmo', $_POST['emphmoprovider']);
      // $stmt->bindParam(':username', $_POST['uname']);
      $stmt->execute();  

      $mn="Monday";
      $sql = "INSERT INTO workdays (empid,Day_s,SchedTime) VALUES (:empide2,:empday,:empmon)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $_POST['empidn']);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschmon']);
      $stmt->execute();

      $mn="Tuesday";
      $sql = "INSERT INTO workdays (empid,Day_s,SchedTime) VALUES (:empide2,:empday,:empmon)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $_POST['empidn']);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschtues']);
      $stmt->execute();

      $mn="Wednesday";
      $sql = "INSERT INTO workdays (empid,Day_s,SchedTime) VALUES (:empide2,:empday,:empmon)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $_POST['empidn']);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschwed']);
      $stmt->execute();

      $mn="Thursday";
      $sql = "INSERT INTO workdays (empid,Day_s,SchedTime) VALUES (:empide2,:empday,:empmon)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $_POST['empidn']);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschthu']);
      $stmt->execute();

      $mn="Friday";
      $sql = "INSERT INTO workdays (empid,Day_s,SchedTime) VALUES (:empide2,:empday,:empmon)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $_POST['empidn']);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschfri']);
      $stmt->execute();

      $mn="Saturday";
      $sql = "INSERT INTO workdays (empid,Day_s,SchedTime) VALUES (:empide2,:empday,:empmon)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $_POST['empidn']);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschsat']);
      $stmt->execute();

      $mn="Sunday";
      $sql = "INSERT INTO workdays (empid,Day_s,SchedTime) VALUES (:empide2,:empday,:empmon)";
      $stmt = $pdo->prepare($sql);
      $stmt->bindParam(':empide2', $_POST['empidn']);
      $stmt->bindParam(':empday',  $mn);
      $stmt->bindParam(':empmon', $_POST['wrkschsun']);
      $stmt->execute();
   print 201;
       $id=$_SESSION['id'];
         $ch="Enroll Employee : " .  $_POST['empfn'] . " " . $_POST['empln'] ;
      // insert into dars
         $sql = "INSERT INTO dars (EmpID,EmpActivity) VALUES (:id,:empact)";
         $stmt = $pdo->prepare($sql);
         $stmt->bindParam(':id' , $id);
         $stmt->bindParam(':empact', $ch);
         $stmt->execute(); 
         
 ?>