<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$q = $_SESSION['id'];
try{

include 'w_conn.php';	

$pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   }
catch(PDOException $e)
   {
die("ERROR: Could not connect. " . $e->getMessage());
   }
  date_default_timezone_set("Asia/Manila"); 

$ltype = $_GET['dte']; 
$filter = $_GET['filter'];
$rd= "Restday";
$be=1;
$ic=2;
if($filter==0){
  $statement = $pdo->prepare("select * from payrol as a
							inner join employees as b on a.PYEmpID=b.EmpID 
          
              where a.PYDate=:date order by b.EmpLN,a.PYDate asc");
  $statement->bindParam(':date' , $ltype);
  $statement->execute(); 
}
elseif($filter==1){
  $statement = $pdo->prepare("select * from payrol as a
							inner join employees as b on a.PYEmpID=b.EmpID 
              inner join empdetails as c on a.PYEmpID=c.EmpID
              where a.PYDate=:date and c.EmpStatID=:stat
              order by b.EmpLN,a.PYDate asc");
  $statement->bindParam(':date' , $ltype);
  $statement->bindParam(':stat' , $be);
  $statement->execute(); 
}
else{
  $statement = $pdo->prepare("select * from payrol as a
							inner join employees as b on a.PYEmpID=b.EmpID 
              inner join empdetails as c on a.PYEmpID=c.EmpID
              where a.PYDate=:date and c.EmpStatID=:stat
              order by b.EmpLN,a.PYDate asc");
  $statement->bindParam(':date' , $ltype);
  $statement->bindParam(':stat' , $ic);
  $statement->execute(); 
}

$count = $statement->rowCount();
                            $totalemp=0;
                            $totalbasic=0;
                            $totalAdj=0;
                            $totalOverTime=0;
                            $totalGross=0;
                            $totalSSS=0;
                            $totalSSSLoan=0;
                            $totalPhilhealth=0;
                            $ttlPIbig=0;
                            $ttlPiloan=0;
                            $ttlTaxIncome=0;
                            $ttlIncTax=0;
                            $ttlNetPay=0;
                            $ttlAllowance=0;
                            $ttlOAdj=0;
                            $ttlPyReciev=0;
                            $totalAdj2=0;
                            $totalpco=0;

?>         
<table class="table" id="reportview" style="overflow: auto;">
                        <thead>            
                          <tr>
                            <th  class="names">Employee Name</th>
                            <th class="valdata">Basic</th>
                            <th class="valdata">AB/TRD</th>                                                     
                            <th class="valdata">OT</th>
                            <th class="valdata">APCO</th>
                            <th class="valdata">Gross Pay</th>
                            <th class="valdata">SSS</th>
                            <th class="valdata">SSS Loan</th>
                            <th class="valdata">PH</th>
                            <th class="valdata">PI</th>
                            <th class="valdata">PI Loan</th>
                            <th class="valdata">Taxable Income</th>
                            <th class="valdata">Tax</th>
                            <th class="valdata">Netpay</th>
                            <th class="valdata">Allowance</th>
                            <th class="valdata">Adjustments</th>
                            <th class="valdata">Adjustment 2</th>
                            <th class="valdata">Pay Receivable</th>
                          </tr>
                        </thead>
                        <tbody>
                          
            <?php

          while ($row=$statement->fetch()) {
            $totalemp++;  $totalbasic= $totalbasic + $row['PYBasic'];
            $totalAdj= $totalAdj+$row['PYAdj'];$totalOverTime=$totalOverTime+$row['PYOverTime']; $totalGross=$totalGross+$row['PYGross'];
            $totalSSS = $totalSSS+ $row['PYSSS'];$totalSSSLoan=$totalSSSLoan+$row['PYSSSLoan']; 
            $totalPhilhealth=$totalPhilhealth+$row['PYPhilHealth'];
            $ttlPIbig=$ttlPIbig+$row['PYPagibig'];
            $ttlPiloan=$ttlPiloan+$row['PYPILoan'];
            $ttlTaxIncome=$ttlTaxIncome+$row['PYTaxIncome'];
            $ttlIncTax=$ttlIncTax+ $row['PYIncTax'];
              $ttlNetPay= $ttlNetPay+ $row['PYNetPay'];
              $ttlAllowance=$ttlAllowance+$row['PYAllowance'];
              $ttlOAdj=$ttlOAdj+$row['PYOtherAdj'];
              $ttlPyReciev=$ttlPyReciev+$row['PYRecivable'];
               $totalAdj2=$totalAdj2+$row['PYOtherAdj2'];
               $totalpco=  $totalpco + $row['adjFromPCO'];
          ?>

          <tr>
          <td class="names"><?php echo $row['EmpLN']. " " . $row['EmpFN']; ?></td>
          <td class="valdata"><?php echo number_format($row['PYBasic'] ,2,".",","); ?></td>
          <td class="valdata"><?php echo number_format($row['PYAdj'],2,".",","); ?></td>
          <td class="valdata"><?php echo number_format($row['PYOverTime'],2,".",","); ?></td>
          <td class="valdata"><?php echo number_format($row['adjFromPCO'],2,".",","); ?></td>
          <td class="valdata"><?php echo number_format($row['PYGross'],2,".",","); ?></td>
          <td class="valdata"><?php echo number_format($row['PYSSS'],2,".",","); ?></td>
          <td class="valdata"><?php echo number_format($row['PYSSSLoan'],2,".",","); ?></td>
          <td class="valdata"><?php echo number_format($row['PYPhilHealth'],2,".",","); ?></td>
          <td class="valdata"><?php echo number_format($row['PYPagibig'],2,".",","); ?></td>
          <td class="valdata"><?php echo number_format($row['PYPILoan'],2,".",","); ?></td>
          <td class="valdata"><?php echo number_format($row['PYTaxIncome'],2,".",","); ?></td>
          <td class="valdata"><?php echo number_format($row['PYIncTax'],2,".",","); ?></td>
          <td class="valdata"><?php echo number_format($row['PYNetPay'],2,".",","); ?></td>  
          <td class="valdata"><?php echo number_format($row['PYAllowance'],2,".",","); ?></td>
          <td class="valdata"><?php echo number_format($row['PYOtherAdj'],2,".",","); ?></td>
          <td class="valdata"><?php echo  preg_replace('/(-)([\d\.\,]+)/ui',  '($2)', number_format($row['PYOtherAdj2'],2,'.',',') ); ?></td>
          <td class="valdata"><?php echo number_format($row['PYRecivable'],2,".",","); ?></td>
          </tr>
        
          <?php

          }
          ?> 
          <!-- total  -->
          <tr>
          <hr>

          <td class="names">Employee Count : <?php echo $count; ?></td>
          <td class="a"> <?php echo  number_format($totalbasic,2,".",","); ?></td>
          <td class="a"> <?php echo  number_format($totalAdj,2,".",","); ?></td>
          <td class="a"> <?php echo  number_format($totalOverTime,2,".",","); ?></td>
          <td class="a"> <?php echo  number_format($totalpco,2,".",","); ?></td>
          <td class="a"> <?php echo  number_format($totalGross,2,".",","); ?></td>
          <td class="a"> <?php echo  number_format($totalSSS,2,".",","); ?></td>
          <td class="a"> <?php echo  number_format($totalSSSLoan,2,".",","); ?></td>
          <td class="a"> <?php echo  number_format($totalPhilhealth,2,".",","); ?></td>
          <td class="a"> <?php echo  number_format($ttlPIbig,2,".",","); ?></td>
          <td class="a"> <?php echo  number_format($ttlPiloan,2,".",","); ?></td>
          <td class="a"> <?php echo  number_format($ttlTaxIncome,2,".",","); ?></td>
          <td class="a"> <?php echo  number_format($ttlIncTax,2,".",","); ?></td>
          <td class="a"> <?php echo  number_format($ttlNetPay,2,".",","); ?></td>
          <td class="a"> <?php echo  number_format($ttlAllowance,2,".",","); ?></td>
          <td class="a"> <?php echo  number_format($ttlOAdj,2,".",","); ?></td>
           <td class="a"> <?php echo  preg_replace('/(-)([\d\.\,]+)/ui',  '($2)', number_format($totalAdj2,2,'.',',') ); ?></td>  
          <td class="a"> <?php echo  number_format($ttlPyReciev,2,".",","); ?></td>
          </tr>
          </tbody>
          </table>
   
                     

            

