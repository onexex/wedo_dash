<?php
    include 'w_conn.php';
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
    else{ header ('location: login.php'); }
    date_default_timezone_set("Asia/Manila");
    $today = date("Y-m-d H:i:s");
    
    try{
        $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }catch(PDOException $e){
        die("ERROR: Could not connect. " . $e->getMessage());
    }

    if (isset($_GET['viewSummary'])){

        try{
            $id=$_POST['ids']; 
            $dfrom=$_POST['dfrom']; 
            $dto=$_POST['dto']; 
            $empdata=[];
    
            if($id=="all"){
                $getemployee = $pdo->prepare("SELECT a.EmpLN as EmpLN, SUM(PYBasic) as PYBasic,SUM(PYAdj)  as PYAdj,SUM(PYOverTime)  as PYOverTime,
                 SUM(PYGross)  as PYGross,SUM(PYSSS)  as PYSSS,SUM(PYSSSLoan)  as PYSSSLoan,SUM(PYPhilHealth)  as PYPhilHealth,SUM(PYPagibig)  as PYPagibig,SUM(PYPILoan)  as PYPILoan,
                 SUM(PYTaxIncome)  as PYTaxIncome,SUM(PYIncTax)  as PYIncTax,SUM(PYNetPay)  as PYNetPay,SUM(PYAllowance)  as PYAllowance,SUM(PYOtherAdj)  as PYOtherAdj,SUM(PYallowadj)  as PYallowadj
                 ,SUM(PYRecivable)  as PYRecivable,SUM(13thMon)  as th
                 FROM employees AS a INNER JOIN payrol AS b ON a.EmpID=b.PYEmpID WHERE (b.PYDate BETWEEN :dfrom AND :dto) group by a.EmpLN ORDER BY a.EmpLN");
                $getemployee->bindParam(':dfrom' , $dfrom);
                $getemployee->bindParam(':dto' , $dto);
            }else{

                $getemployee = $pdo->prepare("SELECT a.EmpLN as EmpLN, SUM(PYBasic) as PYBasic,SUM(PYAdj)  as PYAdj,SUM(PYOverTime)  as PYOverTime,
                SUM(PYGross)  as PYGross,SUM(PYSSS)  as PYSSS,SUM(PYSSSLoan)  as PYSSSLoan,SUM(PYPhilHealth)  as PYPhilHealth,SUM(PYPagibig)  as PYPagibig,SUM(PYPILoan)  as PYPILoan,
                SUM(PYTaxIncome)  as PYTaxIncome,SUM(PYIncTax)  as PYIncTax,SUM(PYNetPay)  as PYNetPay,SUM(PYAllowance)  as PYAllowance,SUM(PYOtherAdj)  as PYOtherAdj,SUM(PYallowadj)  as PYallowadj
                ,SUM(PYRecivable)  as PYRecivable,SUM(13thMon)  as th
                FROM employees AS a INNER JOIN payrol AS b ON a.EmpID=b.PYEmpID WHERE (b.PYDate BETWEEN :dfrom AND :dto)  AND b.PYEmpID =:id group by a.EmpLN ORDER BY a.EmpLN");
                // $getemployee = $pdo->prepare("SELECT * FROM employees AS a INNER JOIN payrol AS b ON a.EmpID=b.PYEmpID WHERE (b.PYDate BETWEEN :dfrom AND :dto) AND b.PYEmpID =:id");
                $getemployee->bindParam(':id' , $id);
                $getemployee->bindParam(':dfrom' , $dfrom);
                $getemployee->bindParam(':dto' , $dto);
            }
            $getemployee->execute();
            $count = $getemployee->rowCount();
            while ($getrow = $getemployee->fetch()) {
                $empdata[]=$getrow;
            }
            if($count>0){
                 echo json_encode(array("data"=> $empdata,"errcode"=>0));
            }else{
                echo json_encode(array("errcode"=>  $dfrom));
            }
        }catch(Exception $e) {
            echo 'Message: ' .$e->getMessage();
          }
      
    }
    // attachment
    if (isset($_GET['viewSummaryAtt'])){

        try{
            $id=$_POST['ids']; 
            $dfrom=$_POST['dfrom']; 
            $dto=$_POST['dto']; 
            $empdata=[];
    
            if($id=="all"){
                $getemployee = $pdo->prepare("SELECT a.EmpLN as EmpLN, PYid  as PYid ,PYEmpID as PYEmpID, PYBasic as PYBasic,PYAdj  as PYAdj,PYOverTime  as PYOverTime,PYDate  as PYDate,
                PYGross  as PYGross,PYSSS as PYSSS,PYSSSLoan  as PYSSSLoan,PYPhilHealth  as PYPhilHealth,PYPagibig  as PYPagibig,PYPILoan  as PYPILoan,
                 PYTaxIncome as PYTaxIncome,PYIncTax  as PYIncTax,PYNetPay  as PYNetPay,PYAllowance  as PYAllowance,PYOtherAdj as PYOtherAdj,PYallowadj  as PYallowadj
                 ,PYRecivable  as PYRecivable,13thMon  as th
                 FROM employees AS a INNER JOIN payrol AS b ON a.EmpID=b.PYEmpID WHERE (b.PYDate BETWEEN :dfrom AND :dto)   ORDER BY a.EmpLN , b.PYDate");
                $getemployee->bindParam(':dfrom' , $dfrom);
                $getemployee->bindParam(':dto' , $dto);
            }else{

                $getemployee = $pdo->prepare("SELECT a.EmpLN as EmpLN,PYEmpID as PYEmpID, PYBasic as PYBasic,PYAdj  as PYAdj,PYOverTime  as PYOverTime,PYDate  as PYDate,
                PYGross  as PYGross,PYSSS as PYSSS,PYSSSLoan  as PYSSSLoan,PYPhilHealth  as PYPhilHealth,PYPagibig  as PYPagibig,PYPILoan  as PYPILoan,
                 PYTaxIncome as PYTaxIncome,PYIncTax  as PYIncTax,PYNetPay  as PYNetPay,PYAllowance  as PYAllowance,PYOtherAdj as PYOtherAdj,PYallowadj  as PYallowadj
                 ,PYRecivable  as PYRecivable,13thMon  as th
                FROM employees AS a INNER JOIN payrol AS b ON a.EmpID=b.PYEmpID WHERE (b.PYDate BETWEEN :dfrom AND :dto)  AND b.PYEmpID =:id   ORDER BY a.EmpLN ,b.PYDate");
                // $getemployee = $pdo->prepare("SELECT * FROM employees AS a INNER JOIN payrol AS b ON a.EmpID=b.PYEmpID WHERE (b.PYDate BETWEEN :dfrom AND :dto) AND b.PYEmpID =:id");
                $getemployee->bindParam(':id' , $id);
                $getemployee->bindParam(':dfrom' , $dfrom);
                $getemployee->bindParam(':dto' , $dto);
            }
            $getemployee->execute();
            $count = $getemployee->rowCount();
            while ($getrow = $getemployee->fetch()) {
                $empdata[]=$getrow;
            }
            if($count>0){
                 echo json_encode(array("data"=> $empdata,"errcode"=>0));
            }else{
                echo json_encode(array("errcode"=>  $dfrom));
            }
        }catch(Exception $e) {
            echo 'Message: ' .$e->getMessage();
          }
      
    }
?>