<?php
class ReportController {
	private $conn;

	function __construct() {
		$this->conn = $this->connectDB();
	}
	function connectDB() {
		// Centralized, environment-aware DB config (single source of truth).
		require __DIR__ . '/w_conn.php';   // sets $servername/$username/$password/$db + $con
		return $con;
	}
	function runQuery($query) {
		$result = mysqli_query($this->conn,$query);
		while($row=mysqli_fetch_assoc($result)) {
		$resultset[] = $row;
		}
		if(!empty($resultset))
		return $resultset;
	}
}
?>
