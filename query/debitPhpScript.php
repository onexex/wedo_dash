<?php 

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
   //save data to debit history for payslip
   if (isset($_GET['savehistorydebit'])){
      $debitDate=$_POST['debitdate'];
      $branch=$_POST['branch'];
      $city=$_POST['city'];
      $pdate=$_POST['paydate'];

      $sql = "INSERT INTO debithistory (debitdate,debitbranch,debitcity,payday,dtpinput) 
                VALUES (:debitdate,:debitbranch,:debitcity,:payday,:dtpinput)";
               $stmt = $pdo->prepare($sql);
               $stmt->bindParam(':debitdate' , $debitDate);
               $stmt->bindParam(':debitbranch' , $branch);
               $stmt->bindParam(':debitcity' , $city);
               $stmt->bindParam(':payday' , $pdate);
               $stmt->bindParam(':dtpinput' , $today);

               if($stmt->execute()){
                  $errinsert=1;              
               }
               echo json_encode(array("inserterr"=>$errinsert));
   }
   //save ca accunt numbers
         if (isset($_GET['savecaaccount'])){

        $id=$_POST['id'];
         $cnumber=$_POST['cnumber'];
             $sql = "INSERT INTO condebitca (conID,CA_Number) 
                VALUES (:cid,:cnum)";
               $stmt = $pdo->prepare($sql);
               $stmt->bindParam(':cid' , $id);
               $stmt->bindParam(':cnum' , $cnumber);
               $stmt->execute();
               
            $data=[];
            $stmt = $pdo->prepare("Select * from condebitca where CA_Status=1 and conID=:id");
            $stmt->bindParam(':id' , $id);
            $stmt->execute();
            $count = $stmt->rowCount();
           if($count > 0){
            while ($getrow = $stmt->fetch()) {
               $data[]=$getrow;
           }
        }
           echo json_encode(array("data"=>$data));
   }
   //delere accunts
      if (isset($_GET['deleteaccounts'])){
        $err=0;
        $id=$_POST['id'];
        $contactid=$_POST['contactid'];
         $datas=[];
        
        $stmt = $pdo->prepare("Update condebitca set CA_Status='0' where id=:id");
        $stmt->bindParam(':id' , $id);
        if($stmt->execute()){
             $err=1;
        
            $stmt1 = $pdo->prepare("Select * from condebitca where CA_Status='1' and conID=:id ");
           $stmt1->bindParam(':id', $contactid);
            $stmt1->execute();
            $count1 = $stmt1->rowCount();
           if($count1 > 0){
                while ($getrow1 = $stmt1->fetch()) {
                    $datas[]=$getrow1;
                }
             }
        }     
           echo json_encode(array("data"=>$datas,"fd"=>$count1));
   }
   
   //load ca accounts
    if (isset($_GET['loadaccounts'])){
        $data=[];
          $id=$_POST['id'];
            $stmt = $pdo->prepare("Select * from condebitca where CA_Status=1 and conID=:id");
            $stmt->bindParam(':id' , $id);
            $stmt->execute();
            $count = $stmt->rowCount();
           if($count > 0){
            while ($getrow = $stmt->fetch()) {
               $data[]=$getrow;
           }
        }
           echo json_encode(array("data"=>$data));
   }
   //update info
      if (isset($_GET['update'])){
      $updateInsert=0;
      $id=$_POST['id'];
      $salutation=$_POST['salutation'];
      $fname=$_POST['fname'];
      $initial=$_POST['initial'];
      $lname=$_POST['lname'];
      $position=$_POST['position'];
      $branch=$_POST['branch'];
      $cityaddress=$_POST['cityaddress'];
      $otherbranch=$_POST['otherbranch'];
      $errinsert=0;
      $sql = "Update debitcontact set conSalutation=:salut,conFName=:fname,conMInitial=:mname,conLName=:lname,conPosition=:pos,conBranch=:branch,conCity=:city,conOthers=:others  where sid=:id";
               $stmt = $pdo->prepare($sql);
               $stmt->bindParam(':salut' , $salutation);
               $stmt->bindParam(':fname' , $fname);
               $stmt->bindParam(':mname' , $initial);
               $stmt->bindParam(':lname' , $lname);
               $stmt->bindParam(':pos' , $position);
               $stmt->bindParam(':branch' , $branch);
               $stmt->bindParam(':city' , $cityaddress);
               $stmt->bindParam(':others' , $otherbranch);
               $stmt->bindParam(':id' , $id);
               if($stmt->execute()){
                  $updateInsert=1;              
               }
               echo json_encode(array("data"=>$updateInsert));
   }
   //load data
    if (isset($_GET['getdata'])){
        $id=$_POST['id'];
        $data=[];
            $stmt = $pdo->prepare("Select * from debitcontact where sid=:id");
            $stmt->bindParam(':id' , $id);
            $stmt->execute();
            $count = $stmt->rowCount();
           if($count > 0){
            while ($getrow = $stmt->fetch()) {
               $data[]=$getrow;
           }
        }
           echo json_encode(array("data"=>$data));
   }
       //load delete
   if (isset($_GET['delete'])){
       $err=0;
        $id=$_POST['id'];
        $stmt = $pdo->prepare("Update debitcontact set conStatus='0' where sid=:id");
        $stmt->bindParam(':id' , $id);
        if($stmt->execute()){
             $err=1;
        }
      
           echo json_encode(array("data"=>$err));
   }
   
    //load loadBankInformation
   if (isset($_GET['loadBankInformation'])){
      $allBankInoEntry=[];
      $stat=1;
      $stmt = $pdo->prepare("Select * from debitcontact where conStatus=:stat");
        $stmt->bindParam(':stat' , $stat);
      $stmt->execute();
      $count = $stmt->rowCount();
           if($count > 0){
            while ($getrow = $stmt->fetch()) {
               $allBankInoEntry[]=$getrow;
           }
           
           }
           echo json_encode(array("data"=>$allBankInoEntry));
   }
      //for debit setting
   if (isset($_GET['register'])){
      $salutation=$_POST['salutation'];
      $fname=$_POST['fname'];
      $initial=$_POST['initial'];
      $lname=$_POST['lname'];
      $position=$_POST['position'];
      $branch=$_POST['branch'];
      $cityaddress=$_POST['cityaddress'];
      $otherbranch=$_POST['otherbranch'];
      $errinsert=0;
      $sql = "INSERT INTO debitcontact (conSalutation,conFName,conMInitial,conLName,conPosition,conBranch,conCity,conOthers) 
                VALUES (:salut,:fname,:mname,:lname,:pos,:branch,:city,:others)";
               $stmt = $pdo->prepare($sql);
               $stmt->bindParam(':salut' , $salutation);
               $stmt->bindParam(':fname' , $fname);
               $stmt->bindParam(':mname' , $initial);
               $stmt->bindParam(':lname' , $lname);
               $stmt->bindParam(':pos' , $position);
               $stmt->bindParam(':branch' , $branch);
               $stmt->bindParam(':city' , $cityaddress);
               $stmt->bindParam(':others' , $otherbranch);
               if($stmt->execute()){
                  $errinsert=1;              
               }
               echo json_encode(array("inserterr"=>$errinsert));
   }
   
   $x=0;
   
   if(isset($_GET['loadcainfo'])){
      $bankid=$_POST['bankinid'];
      $ca=[];
      $getca = $pdo->prepare("Select * from debitcontact as a
                                 LEFT JOIN condebitca as b ON a.sid=b.conID
                                 where CA_Status=1 and a.sid=:id");
      $getca->bindParam(':id' , $bankid);
      $getca->execute();
     // $getrow = $getca->fetch();
      $count = $getca->rowCount();
           if($count > 0){
            while ($getcas = $getca->fetch()) {
               $ca[]=$getcas;
           }
           
           }
           echo json_encode(array("cainfo"=>$ca)); 
   }


   if(isset($_GET['gettotalpr'])){
      $pdate=$_POST['rldt'];
      $totalPR=0;
      $getTotalPR = $pdo->prepare("Select sum(PYRecivable) as ramonsum from payrol INNER JOIN empprofiles ON payrol.PYEmpID=empprofiles.EmpID
                                 INNER JOIN employees on payrol.PYEmpID=employees.EmpID
                                  where PYDate=:pdate and PYRecivable > 0 and card_number <> 0  order by EmpLN asc");
      
      $getTotalPR->bindParam(':pdate' , $pdate);
      $getTotalPR->execute();
      $getrow = $getTotalPR->fetch();
      $count = $getTotalPR->rowCount();
           if($count > 0){
            $totalPR=$getrow['ramonsum'];
           }
           
$num=$totalPR;
$xs=$num;
$nums = number_format($num,2,".",","); 
$num_arr = explode(".",$nums); 

$decnum = $num_arr[1] ;



$num = str_replace(array(',', ' '), '' , trim($num));
if(! $num) {
    return false;
}

$num = (int) $num;


$words = array();
//$list=array('',);
$list1 = array('', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven',
    'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'
);
$list2 = array('', 'ten', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety', 'hundred');
$list3 = array('', 'thousand', 'million', 'billion', 'trillion', 'quadrillion', 'quintillion', 'sextillion', 'septillion',
    'octillion', 'nonillion', 'decillion', 'undecillion', 'duodecillion', 'tredecillion', 'quattuordecillion',
    'quindecillion', 'sexdecillion', 'septendecillion', 'octodecillion', 'novemdecillion', 'vigintillion'
);
$num_length = strlen($num);
$levels = (int) (($num_length + 2) / 3);
$max_length = $levels * 3;
$num = substr('00' . $num, -$max_length);
$num_levels = str_split($num, 3);
for ($i = 0; $i < count($num_levels); $i++) {
    $levels--;
    $hundreds = (int) ($num_levels[$i] / 100);
    $hundreds = ($hundreds ? ' ' . $list1[$hundreds] . ' hundred' . ' ' : '');
    $tens = (int) ($num_levels[$i] % 100);
    $singles = '';
    if ( $tens < 20 ) {
        $tens = ($tens ? ' ' . $list1[$tens] . ' ' : '' );
    } else {
        $tens = (int)($tens / 10);
        $tens = ' ' . $list2[$tens] . ' ';
        $singles = (int) ($num_levels[$i] % 10);
        $singles = ' ' . $list1[$singles] . ' ';
    }
    $words[] = $hundreds . $tens . $singles . ( ( $levels && ( int ) ( $num_levels[$i] ) ) ? ' ' . $list3[$levels] . ' ' : '' );

} //end for loop
$rettxt='';
if($decnum > 0){
   $rettxt .= " and ";
   if($decnum < 10)
   {
      $rettxt .= $list1[trim($decnum,'0')];
   }
   elseif($decnum < 20)
   {
    $rettxt .= $list1[$decnum];
   }
   elseif($decnum < 100)
   {
      $rettxt .= $list2[substr($decnum,0,1)];
      $rettxt .= " ".$list1[substr($decnum,1,1)];
   }

}
$commas = count($words);
if ($commas > 1) {
    $commas = $commas - 1;
}
$s= strtoupper("Pesos " . implode(' ', $words).  "and " .$decnum . "/100 Only") . " ( Php ". $nums . " )" ;
//
           echo json_encode(array("totalPRSUM"=>$s)); 
      } 


   if(isset($_GET['loadpaydates'])){

   $pdates=[];
   
  $va=1;
  $getPdates = $pdo->prepare("Select * from payrol where hrApproved=:va group by PYDate order by PYDate desc ");
  $getPdates->bindParam(':va' , $va);
  $getPdates->execute();
  $count = $getPdates->rowCount();
        if($count > 0){
         while ($getrow1 = $getPdates->fetch()) {
            $pdates[]=$getrow1; }
        }
   $binfo=[];     
  $getbankinfo = $pdo->prepare("Select * from debitcontact where conStatus=1");
  $getbankinfo->execute();
  $count1 = $getbankinfo->rowCount();
  //$rowbank = $getbankinfo->fetch();
         if($count1 > 0){
          while ($rowbank = $getbankinfo->fetch()) {
             $binfo[]= $rowbank;
             }
         }
         
        echo json_encode(array("paydate"=>$pdates,"bankinfo"=>$binfo,"NT"=>$count1));
   }    

   if(isset($_GET['loadPaydata'])){
      $empDatas=[];
      $pdate=$_POST['rldt'];
      $getinfoEmp = $pdo->prepare("Select * from payrol INNER JOIN empprofiles ON payrol.PYEmpID=empprofiles.EmpID
                                 INNER JOIN employees on payrol.PYEmpID=employees.EmpID
                                  where PYDate=:pdate  and PYRecivable > 0 and card_number <> 0  order by EmpLN asc");
      $getinfoEmp->bindParam(':pdate' , $pdate);
      $getinfoEmp->execute();
      //$getrow = $getinfoEmp->fetch();
      $count = $getinfoEmp->rowCount();
           if($count > 0){
                  while ($getrow = $getinfoEmp->fetch()) {
                     $empDatas[]=$getrow;
                     $x=$x+$getrow['PYRecivable'];
                  }
             }


//

           echo json_encode(array("empData"=>$empDatas,"sum"=>number_format($x,2))); 
      } 
  
   ?>   