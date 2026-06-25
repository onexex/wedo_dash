<?php 
include 'w_conn.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
else{ header ('location: login.php'); }

date_default_timezone_set("Asia/Manila");
$today = date("Y-m-d H:i:s");
$today1 = date("Y-m-d");
$today3 = date("d F, Y");
$dyear = date("Y");
$strdate= $dyear. "-01-01";

try{
	$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
	$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }

catch(PDOException $e){
	die("ERROR: Could not connect. " . $e->getMessage());
   }

   $paysliptAccess=2;
   $id=$_SESSION['id'];
   


   //get all data link to paydate payroll
   
   if (isset($_GET['getpayrolldata'])){

    $paydata=[];
    $summation=[];
    $debitdata=[];
    $pdate=$_POST['pdate'];
    $empid=$_POST['id'];
    // $mod_date = strtotime($pdate."+ 1 months");
    // $d= date("Y-m-d",$mod_date);        
    if($today1< $pdate)
    {
        print "0";
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM payrol as a
                            INNER JOIN  empprofiles as b ON a.PYEmpID=b.EmpID
                            INNER JOIN  employees as c on b.EmpID=c.EmpID
                            INNER JOIN  positions as d on c.PosID=d.PSID
                            INNER JOIN  departments as e on d.DepartmentID=e.DepartmentID 
                            
                            WHERE  a.PYEmpID=:id and a.PYDate=:pdate");

    $stmt->bindParam(':id' , $empid);
    $stmt->bindParam(':pdate' , $pdate);
    $stmt->execute();
    $count = $stmt->rowCount();
    if($count > 0){
        while ($getrow = $stmt->fetch()) {
            $paydata[]=$getrow; 
            $thirteenmonths=$getrow['13thMon'];
        }
    }

    $stmt = $pdo->prepare("SELECT SUM(PYBasic) as basic,SUM(PYAllowance) as allowance,SUM(PYOverTime) as ot,
                                  SUM(13thMon) as month13,SUM(PYIncTax) as inc, SUM(PYSSS) as sss, SUM(PYAdjMin) as adj,
                                  SUM(PYPhilHealth) as phil,SUM(PYPagibig) as pi,SUM(PYSSSLoan) as sssloan,SUM(PYPILoan) as piloan,
                                  sum(PYssser) as ssser,sum(PYphiler) as pher,sum(PYallowadj) as adjallow,sum(PYpier) as pier,sum(PYOtherAdj) as pyadj1
                                  ,sum(PYOtherAdj2) as adj2 ,sum(PYmfee) as mfee FROM payrol WHERE  PYEmpID=:id and PYDate between :pdate and :pdate2 ");
    $stmt->bindParam(':id' , $empid);
    $stmt->bindParam(':pdate' , $strdate);
    $stmt->bindParam(':pdate2' ,  $pdate);
    $stmt->execute();
    $count = $stmt->rowCount();
    if($count > 0){
        while ($getrow = $stmt->fetch()) {
            $summation[]=$getrow; 
           
        }
    }


    $stmt = $pdo->prepare("SELECT  * FROM debithistory
                            WHERE  payday=:pdate order by sid DESC LIMIT 1");
    $stmt->bindParam(':pdate' , $pdate);
    $stmt->execute();
    $count = $stmt->rowCount();
    if($count > 0){
        while ($getrow = $stmt->fetch()) {
            $debitdata[]=$getrow; 
        }
    }

    $totalPR=0;
    $getTotalPR = $pdo->prepare("Select PYRecivable as prsum from payrol where PYDate=:pdate and PYEmpID=:id");
    $getTotalPR->bindParam(':pdate' , $pdate);
    $getTotalPR->bindParam(':id' , $empid);
    $getTotalPR->execute();
    $getrow = $getTotalPR->fetch();
    $count = $getTotalPR->rowCount();
         if($count > 0){
          $totalPR=$getrow['prsum'];
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
       

        echo json_encode(array("datenows"=>$today3 ,"paydata"=>$paydata,"debitdata"=>$debitdata,"totalPRSUM"=>$s,"thirteen"=> $thirteenmonths,"d"=>$summation));

   
    }
   //get access rights

   $stmt = $pdo->prepare("SELECT * FROM accessrights WHERE EmpID=:id and payslipfunction=:payslip");
   $stmt->bindParam(':id' , $id);
   $stmt->bindParam(':payslip' , $paysliptAccess);
   $stmt->execute();
   $hasAccess = $stmt->rowCount();

   //get employee list
   if (isset($_GET['getemployeelistandpaydates'])){
    $empdata=[];
    $empstats=1; 

    if($hasAccess>0){
        $getemployee = $pdo->prepare("Select * from employees where EmpStatusID=:stat order by EmpLN asc");
        $getemployee->bindParam(':stat' , $empstats);
    }else{
        $getemployee = $pdo->prepare("Select * from employees where EmpStatusID=:stat and EmpID=:id order by EmpLN asc");
        $getemployee->bindParam(':stat' , $empstats);
        $getemployee->bindParam(':id' , $id);
    }

    $getemployee->execute();
    $count = $getemployee->rowCount();
    while ($getrow = $getemployee->fetch()) {
        $empdata[]=$getrow;
    }


    if($count>0){
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
        echo json_encode(array("data"=>$empdata,"errcode"=>0,"pdates"=>$pdates));
    }else{
        $arr = array('data' => 0,"errcode"=>1);
        echo json_encode($arr); 
    }
}
   ?>