<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
  date_default_timezone_set("Asia/Manila"); 
         $dt1=date('Y-m-d', strtotime('-7 days'));
            $dt2=date('Y-m-d');
                  include '../includes/home-attendancelog.php';
                  ?>