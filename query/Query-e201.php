<?php
/* Live-search result for e201.php — returns another employee's 201 profile,
   injected into #e201. Markup lives in includes/e201-profile.php so it matches
   the page's own profile exactly. */
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") { http_response_code(401); exit; }

include_once ("w_conn.php");

$q = isset($_GET['q']) ? $_GET['q'] : '';

$pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$statement = $pdo->prepare("select * from accessrights where EmpID = :id");
$statement->execute([':id' => $_SESSION['id']]);
$rowbtn = $statement->fetch();

// Messages / Message IS shortcuts only on your own profile
$isSelf = false;
$mnum   = 0;

include __DIR__ . '/../includes/e201-profile.php';
