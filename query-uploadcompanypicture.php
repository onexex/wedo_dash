<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); exit; }

// Only a Super User may (re)upload a company logo — company management is admin.
if (($_SESSION['UserType'] ?? null) != 1) { http_response_code(403); echo "0"; exit; }
?>
<?php
// Company id used in the stored filename — hard-sanitize to prevent path traversal
// (was "assets/images/logos/".$_GET['q'].".ext" with q unsanitized → ../ escape).
$id = isset($_GET['q']) ? $_GET['q'] : '';
$id = preg_replace('/[^A-Za-z0-9._-]/', '', basename($id));

$ext = strtolower(pathinfo($_FILES['file']['name'] ?? '', PATHINFO_EXTENSION));
$valid_extensions = array("jpg", "jpeg", "png");

// reject bad id / non-image extension / non-image content
if ($id === '' || !in_array($ext, $valid_extensions, true)) { echo 0; exit; }
if (empty($_FILES['file']['tmp_name']) || !@getimagesize($_FILES['file']['tmp_name'])) { echo 0; exit; }

$location = "assets/images/logos/" . $id . "." . $ext;

if (move_uploaded_file($_FILES['file']['tmp_name'], $location)) {
    echo $location;
} else {
    echo 0;
}
?>
