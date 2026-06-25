<?php
    include 'w_conn.php';

    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
    else{ header ('location: login.php'); }
    
    date_default_timezone_set("Asia/Manila");
    $today = date("Y-m-d H:i:s");
    $today1 = date("Y-m-d");
    
    try{
        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
       }
    
    catch(PDOException $e){
        die("ERROR: Could not connect. " . $e->getMessage());
       }
       //get access rights
   $id=$_SESSION['id'];
   $coeaccess=2;
   $stmt = $pdo->prepare("SELECT * FROM accessrights WHERE EmpID=:id  and coefunction=:coe");
   $stmt->bindParam(':id' , $id);
   $stmt->bindParam(':coe' , $coeaccess);
   $stmt->execute();
   $hasAccess = $stmt->rowCount();

       //get employee list
   if (isset($_GET['getemployeelist'])){
    $empdata=[];
    $empstats=1; 

    if($hasAccess>0){
        $getemployee = $pdo->prepare("Select * from employees where EmpStatusID=:stat and EmpID<>'admin' order by EmpLN asc");
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
    echo json_encode(array("data"=>$empdata,"errcode"=>0));


}
if (isset($_GET['getemplooyeedata'])){
    $id=$_POST['id'];
    $datas=[];
    $stmt = $pdo->prepare(" SELECT * FROM `employees` as a inner JOIN 
                                positions as b on a.PosID=b.PSID INNER JOIN 
                                empdetails as c on a.EmpID=c.EmpID inner join 
                                empdetails2 as d on a.EmpID=d.EmpID INNER join 
                                empprofiles as e on a.EmpID=e.EmpID where a.EmpID=:id");
    $stmt->bindParam(':id' , $id);
    $stmt->execute();
    $count = $stmt->rowCount();
    while ($getrow = $stmt->fetch()) {
        $datas[]=$getrow;
        $sum=$getrow['EmpBasic'];
        $datehired=$getrow['EmpDateHired'];
    }
    $date=date_create($datehired);
    $d=date_format($date,"d F, Y");
    

    
    $num=$sum;

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
// $s= strtoupper(implode(' ', $words).  "and " .$decnum . "/100 Only") . " ( Php ". $nums . " )" ;
$s= strtoupper(implode(' ', $words)) . " ( Php ". $nums . " )" ;
//
           
    //end
    echo json_encode(array("data"=>$datas,"errcode"=>0,"totalPRSUM"=>$s,"dh"=>$d));


}

?>