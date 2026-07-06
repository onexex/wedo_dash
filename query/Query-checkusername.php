<?php
/* Resolves a unique login username for the enrollment form.
   POST fn+ln  → generate a unique username from a name.
   POST u      → check a typed username; echoes a free variant if it's taken. */
include 'w_conn.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['id']) || $_SESSION['id'] == "0") {
    http_response_code(403);
    echo json_encode(["error" => "auth"]);
    exit;
}
require __DIR__ . '/../includes/username.php';
header('Content-Type: application/json');

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(["error" => "db"]);
    exit;
}

if (isset($_POST['u']) && trim($_POST['u']) !== '') {
    $u     = trim($_POST['u']);
    $taken = wd_username_taken($pdo, $u);
    echo json_encode([
        "username" => $taken ? wd_free_username($pdo, $u) : $u,
        "taken"    => $taken,
    ]);
} else {
    $u = wd_unique_username($pdo, isset($_POST['fn']) ? $_POST['fn'] : '', isset($_POST['ln']) ? $_POST['ln'] : '');
    echo json_encode(["username" => $u, "taken" => false]);
}
