<?php
   $lid= $_POST['ltid'];
   if (session_status() === PHP_SESSION_NONE) { session_start(); }
   try{
      include 'w_conn.php';
      $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
         }
      catch(PDOException $e)
         {
      die("ERROR: Could not connect. " . $e->getMessage());
         }
         date_default_timezone_set('Asia/Manila');

    //ifvalid after leave     
   if (isset($_GET['checkIFleaveafter']))
   {
      $statement = $pdo->prepare("select * from leaves_validation where sid = :idn");
      $statement->bindParam(':idn' , $lid);
      $statement->execute();
      $rw=$statement->rowCount();
      $row=$statement->fetch();
      if ($row['leave_file_after']==1){
         echo 1;
         //means yes before
      }else{
         if ($_SESSION['UserType']==3){
            if ($row['val_user_bl_vac']<>0){
               $ctr=0;
               $tdy=date("Y-m-d");
               while($ctr<$row['val_user_bl_vac']){
                  $dyd=date("l", strtotime($tdy . "+ 1 days"));
                  $statement = $pdo->prepare("select * from workdays where empid = :idn and Day_s=:daydesc and SchedTime<>0");
                  $statement->bindParam(':idn' , $_SESSION['id']);
                  $statement->bindParam(':daydesc' , $dyd);
                  $statement->execute();
                  $nrows=$statement->rowCount();
                  $tdy = date("Y-m-d", strtotime($tdy . "+ 1 days"));
                  if ($nrows>0){
                     $ctr=$ctr+1;
                  }
               }
               echo $tdy;

            }else{
               echo date("Y-m-d");
            }
         }
         elseif($_SESSION['UserType']==2){
            if ($row['val_is_bl_vac']<>0){
               $ctr=0;
               $tdy=date("Y-m-d");
               while($ctr<$row['val_user_bl_vac']){
                  $dyd=date("l", strtotime($tdy . "+ 1 days"));
                  $statement = $pdo->prepare("select * from workdays where empid = :idn and Day_s=:daydesc and SchedTime<>0");
                  $statement->bindParam(':idn' , $_SESSION['id']);
                  $statement->bindParam(':daydesc' , $dyd);
                  $statement->execute();
                  $nrows=$statement->rowCount();
                  $tdy = date("Y-m-d", strtotime($tdy . "+ 1 days"));
                  if ($nrows>0){
                     $ctr=$ctr+1;
                  }
               }
               echo $tdy;

            }else{
               echo date("Y-m-d");
            }
         }
         else{
            echo date("Y-m-d");
         }
         //means no before
      }
   }

   //leave before filing
   if (isset($_GET['checkIFleavebefore']))
   {
      $statement = $pdo->prepare("select * from leaves_validation where sid = :idn");
      $statement->bindParam(':idn' , $lid);
      $statement->execute();
      $rw=$statement->rowCount();
      $row=$statement->fetch();
      if ($row['leave_before']==1){
         echo 1;
         //means yes before
      }else{
         echo date("Y-m-d");
         //means no before
      }
   }
   

         
         