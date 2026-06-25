<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
?>

<?php
include 'w_conn.php';
$q = intval($_GET['q']);
$result = mysqli_query($con, "select * from empe201files where EMPID='" . $_GET['q'] . "'");
if (mysqli_num_rows($result)<1){
?>
	<h4> Empty </h4>
<?php
}
else{
	$r=1;
while($row=mysqli_fetch_array($result)){
?>
	<tr>
		<td><?php echo $r; ?></td>
		<td><?php echo $row['EmpfileN']; ?></td>
		<td><?php echo $row['EmpProFPath']; ?></td>

		
	</tr>
<?php
$r++;
}
}
?>