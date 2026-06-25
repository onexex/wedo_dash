<?php
include 'w_conn.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
else{ header ('location: login.php'); }

date_default_timezone_set("Asia/Manila");
$today = date("Y-m-d H:i:s");
$today1 = date("Y-m-d");
$today3 = date("Y-m-d");

try{
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }

catch(PDOException $e){
    die("ERROR: Could not connect. " . $e->getMessage());
   }
   //get access rights
$id=$_SESSION['id'];
$errinsert=0;

global $generatedChekno;

if (isset($_GET['genchkno'])){
    $bankinfo=[]; 
    $err=0;
    $newChkno=0;
    $generatedChekno=0;
    $bklid=0;
        $id=$_POST['bankid'];
        $sql = "Select b.id as bklid,a.id as bnkid,b.bookletfrom as bklfrom,b.bookleto as bklto,a.*,b.*  
                                from bookletbankinfo  as a INNER JOIN 
                                bookletinfo as b ON (a.id=b.bankinfoid) 
                                where b.bankinfoid='$id' and  b.status='1'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $count = $stmt->rowCount();
        if($count > 0){
            
            $getdata = $stmt->fetch();
            $bklid= $getdata['bklid'];
            $bankid= $getdata['bnkid'];
            $bklfrom= $getdata['bklfrom'];
            $bklto= $getdata['bklto'];


            $sql = "Select * from checkregister where bklid=$bklid order by  checkno desc limit 1 ";            
            $stmt1 = $pdo->prepare($sql);
            $stmt1->execute();
            $countnew = $stmt1->rowCount();
            $getdatanew = $stmt1->fetch();
            if($countnew > 0){
                    $newChkno= $getdatanew['checkno'] + 1;
                         $newChkno= '000'.$newChkno;
                        //validate the generated number if in between
                        $sql = "Select * from bookletinfo where id=$bklid and bookleto >= $newChkno ";            
                        $stmt1 = $pdo->prepare($sql);
                        $stmt1->execute();
                        $countnew = $stmt1->rowCount();
                        $getdatanew = $stmt1->fetch();
                        if($countnew > 0){
                            
                        }else{
                            $err=11; 
                            $newChkno='';                           
                        }
                }
                else{
                    $newChkno = $bklfrom; 
                     
                } 
         
    }else{$err=1;}

    echo json_encode(array("err"=>$err,"checkno"=>$newChkno,"idbooklet"=>$bklid)); 
}
if (isset($_GET['loadbankinfo'])){
    $bankinfo=[]; 
    $sql = "Select * from bookletbankinfo where status='1'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $count = $stmt->rowCount();
    if($count > 0){
    while ($getdata = $stmt->fetch()) {
        $bankinfo[]=$getdata;
    }
           
}


echo json_encode(array("bankinfo"=>$bankinfo,"date"=>$today3)); 
}
if (isset($_GET['ban'])){
  
    $id=$_POST['id'];
    $sql = "Update checkregister set status=7 where id=:id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id' , $id);
    $stmt->execute();
               
}

if (isset($_GET['gethistory'])){
    $from=$_POST['from'];
    $to=$_POST['to'];
    $payeefilter=$_POST['payeefilter'];
    $bankinfo=$_POST['bankfilter'];
    $orderfilter=$_POST['orderfilter'];
    $sortby=$_POST['sortby'];
    
    
    
    $dto=date("Y-m-d", strtotime($to . " + 1 days"));  	

 
    if($payeefilter==0 and $bankinfo==0){
        $stmt = $pdo->prepare("Select * from checkregister as a 
                                INNER JOIN status as b on a.status=b.StatusID
                                where a.checkdate between '$from' and '$dto' order by $orderfilter $sortby ");
    }

    elseif($payeefilter==0 and $bankinfo<>0){
       
        $stmt = $pdo->prepare("Select * from checkregister as a 
                                INNER JOIN status as b on a.status=b.StatusID
                                where a.bankinfo ='$bankinfo' order by $orderfilter $sortby ");
    }

    elseif($payeefilter<>0 and $bankinfo==0){
        
        $stmt = $pdo->prepare("Select * from checkregister as a 
                                INNER JOIN status as b on a.status=b.StatusID
                                where a.payee ='$payeefilter' order by $orderfilter $sortby ");
    }
        
    
    else{
        $stmt = $pdo->prepare("Select * from checkregister as a 
                                INNER JOIN status as b on a.status=b.StatusID
                                where a.payee ='$payeefilter'and a.bankinfo ='$bankinfo' order by $orderfilter $sortby ");
    }
   
    $data=[];
    $stmt->execute();
    $count = $stmt->rowCount();
      if($count > 0){
       while ($getdata = $stmt->fetch()) {
          $data[]=$getdata;
      }
     
      
      } 
      echo json_encode(array("data"=>$data,"err"=>$count));     
}

if (isset($_GET['getpayeeandbankinfo'])){
  
    $payee=[];
    $bankinfo=[];

    $stmt = $pdo->prepare("Select payee  from listofpayee  group by payee order by payee asc");
    $stmt->execute();

    $count = $stmt->rowCount();
      if($count > 0){
       while ($getdata = $stmt->fetch()) {
          $payee[]=$getdata;
      }
      
      }
      $stmt = $pdo->prepare("Select *  from bookletbankinfo  ");
      $stmt->execute();
  
      $count = $stmt->rowCount();
        if($count > 0){
         while ($getdata = $stmt->fetch()) {
            $bankinfo[]=$getdata;
        }
        
        }
      echo json_encode(array("payee"=>$payee,"bankinfo"=>$bankinfo)); 


}

if (isset($_GET['getdata'])){
    $id=$_POST['idname'];
    $data=[];
    $stmt = $pdo->prepare("Select * from listofpayee where id=$id  ");
    $stmt->execute();

    $count = $stmt->rowCount();
      if($count > 0){
       while ($getdata = $stmt->fetch()) {
          $data[]=$getdata;
      }
      
      }
      echo json_encode(array("data"=>$data)); 

      
}

if (isset($_GET['store'])){
    $payee=$_POST['payee'];
    $bankinfo=$_POST['bankinfo'];
    $checkno=$_POST['checkno'];
    $checkdate=$_POST['checkdate'];
    $checkamount=$_POST['checkamount'];
    $newid=$_POST['newid'];
    
    $remarks=$_POST['remarks'];
    $errinsert=0; 

    $date=date_create($checkdate);
    $d=date_format($date,"m d Y");
    
    //insert 
    $sql = "INSERT INTO checkregister (payee,bankinfo,checkno,checkdate,checkamount,remarks,dti,bklid) 
    VALUES (:payee,:bankinfo,:checkno,:checkdate,:checkamount,:remarks,:dti,:bklid)";
   $stmt = $pdo->prepare($sql);
   $stmt->bindParam(':payee' , $payee);
   $stmt->bindParam(':bankinfo' , $bankinfo);
   $stmt->bindParam(':checkno' , $checkno);
   $stmt->bindParam(':checkdate' , $checkdate);
   $stmt->bindParam(':checkamount' , $checkamount);
   $stmt->bindParam(':remarks' , $remarks);
   $stmt->bindParam(':dti' , $today);
   $stmt->bindParam(':bklid' , $newid);
   if($stmt->execute()){
      $errinsert=1;              
   }

$num=$checkamount;

if($num>0){

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
//$s= strtoupper(implode(' ', $words).  "and " .$decnum . "/100 Only") . " ( Php ". $nums . " )" ;
$s= strtoupper(implode(' ', $words).  "and " .$decnum . "/100 Only")  ;
//$s= strtoupper(implode(' ', $words)) . " ( Php ". $nums . " )" ;

}
else{
    $s="zero";
}
    
echo json_encode(array("totalPRSUM"=>$s,"inserterr"=>$errinsert,"dat"=>$d));

}
if (isset($_GET['search'])){
    $payee=$_POST['inputVal'];
    $data=[];
    $getca = $pdo->prepare("Select * from listofpayee where payee LIKE '%$payee%'  order by payee  limit 3");
  //  $getca->bindParam(':value' , $payee);
    $getca->execute();

    $count = $getca->rowCount();
      if($count > 0){
       while ($getdata = $getca->fetch()) {
          $data[]=$getdata;
      }
      
      }
      echo json_encode(array("data"=>$data)); 

}





?>