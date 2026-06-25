<?php
	
date_default_timezone_set("Asia/Manila");
class PHPCalendar {
	private $weekDayName = array ("MON","TUE","WED","THU","FRI","SAT","SUN");
	private $currentDay = 0;
	private $currentMonth = 0;
	private $currentYear = 0;
	private $currentMonthStart = null;
	private $currentMonthDaysLength = null;	
	
	function __construct() {
		$this->currentYear = date ( "Y", time () );
		$this->currentMonth = date ( "m", time () );
		
		if (! empty ( $_POST ['year'] )) {
			$this->currentYear = $_POST ['year'];
		}
		if (! empty ( $_POST ['month'] )) {
			$this->currentMonth = $_POST ['month'];
		}
		$this->currentMonthStart = $this->currentYear . '-' . $this->currentMonth . '-01';
		$this->currentMonthDaysLength = date ( 't', strtotime ( $this->currentMonthStart ) );
	}
	
	function getCalendarHTML() {
		$calendarHTML = '<div id="calendar-outer">'; 
		$calendarHTML .= '<div class="calendar-nav">' . $this->getCalendarNavigation() . '</div>'; 
		$calendarHTML .= '<ul class="week-name-title">' . $this->getWeekDayName () . '</ul>';
		$calendarHTML .= '<ul class="week-day-cell">' . $this->getWeekDays () . '</ul>';		
		$calendarHTML .= '</div>';
		return $calendarHTML;
	}
	
	function getCalendarNavigation() {
		$prevMonthYear = date ( 'm,Y', strtotime ( $this->currentMonthStart. ' -1 Month'  ) );
		$prevMonthYearArray = explode(",",$prevMonthYear);
		
		$nextMonthYear = date ( 'm,Y', strtotime ( $this->currentMonthStart . ' +1 Month'  ) );
		$nextMonthYearArray = explode(",",$nextMonthYear);
		
		$navigationHTML = '<div class="prev" data-prev-month="' . $prevMonthYearArray[0] . '" data-prev-year = "' . $prevMonthYearArray[1]. '"><</div>'; 
		$navigationHTML .= '<span id="currentMonth">' . date ( 'M', strtotime ( $this->currentMonthStart ) ) . '</span>';
		$navigationHTML .= '<span contenteditable="true" id="currentYear" style="margin-left:5px">'.	date ( 'Y', strtotime ( $this->currentMonthStart ) ) . '</span>';
		$navigationHTML .= '<div class="next" data-next-month="' . $nextMonthYearArray[0] . '" data-next-year = "' . $nextMonthYearArray[1]. '">></div>';
		return $navigationHTML;
	}
	
	function getWeekDayName() {
		$WeekDayName= '';		
		foreach ( $this->weekDayName as $dayname ) {			
			$WeekDayName.= '<li>' . $dayname . '</li>';
		}		
		return $WeekDayName;
	}
	
	function getWeekDays() {
		$weekLength = $this->getWeekLengthByMonth ();
		$firstDayOfTheWeek = date ( 'N', strtotime ( $this->currentMonthStart ) );
		$tdym = date("M");
		$tdyd = date("d");
		$tdyy = date("Y");


		$M = date ( 'M', strtotime ( $this->currentMonthStart ) );
		$mm = date ( 'm', strtotime ( $this->currentMonthStart ) );
		$d = date ( 'd', strtotime ( $this->currentMonthStart ) );
		$Y = date ( 'Y', strtotime ( $this->currentMonthStart ) );
		$weekDays = "";
		for($i = 0; $i < $weekLength; $i ++) {
			for($j = 1; $j <= 7; $j ++) {
				$cellIndex = $i * 7 + $j;
				$cellValue = null;
				if ($cellIndex == $firstDayOfTheWeek) {
					$this->currentDay = 1;
				}
				if (! empty ( $this->currentDay ) && $this->currentDay <= $this->currentMonthDaysLength) {
					$cellValue = $this->currentDay;
					$this->currentDay ++;
				}
				if (isset($_SESSION['CompID'])) {
				   
				}
				else{
					  if (session_status() === PHP_SESSION_NONE) { session_start(); }
				}

				 include 'w_conn.php';
					     try{
			    $pdo = new PDO("mysql:host=$servername;dbname=$db", $username,$password);
			    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			       }
			    catch(PDOException $e)
			       {
			    die("ERROR: Could not connect. " . $e->getMessage());
			       }
			
			       $sql = "SELECT * FROM holidays WHERE MONTH(Hdate)=:dtm and YEAR(Hdate)=:yr and DAY(Hdate)=:dy and HCompID=:cid";
			       $stmt = $pdo->prepare($sql);
		           $stmt->bindParam(':dtm' ,$mm);
		           $stmt->bindParam(':yr' ,$Y);
		           $stmt->bindParam(':dy' ,$cellValue);
		           $stmt->bindParam(':cid' ,$_SESSION['CompID']);
		           $stmt->execute(); 
		           $cnt=$stmt->rowCount();
		           $rw=$stmt->fetch();
				// SELECT create_date FROM table WHERE MONTH(create_date)
		        	$idd=$M . $cellValue .$Y;
		           $cls="";
		           if ($cnt>=1){
		           		$cls="hldyac";
		           		  ?>
		          			<div class="hldviewer <?php echo $idd;  ?>"><?php echo $M  . " " . $cellValue  . " " . $Y . " - " . $rw['Hdescription']; ?></div>
		          <?php
		           }
				if ($tdym==$M && $tdyd==$cellValue && $tdyy==$Y){
					if ($j==7){
						$weekDays .= '<li style="color:red;" id="' . $idd . '" class="clckday crntday ' . $cls .'">' .  $cellValue . '</li>';
					}else{
						$weekDays .= '<li style="" id="' . $idd . '" class="clckday crntday ' . $cls .'">' . $cellValue . '</li>';
					}
				}else{
					if ($j==7){
						$weekDays .= '<li id="' . $idd . '" class="clckday" style="color:red;" class="' . $cls .'">'  . $cellValue . '</li>';
					}else{
						$weekDays .= '<li id="' . $idd . '" class="clckday ' . $cls .'">'  . $cellValue . '</li>';
					}
				}
			}
		}
		return $weekDays;
	}
	
	function getWeekLengthByMonth() {
		$weekLength =  intval ( $this->currentMonthDaysLength / 7 );	
		if($this->currentMonthDaysLength % 7 > 0) {
			$weekLength++;
		}
		$monthStartDay= date ( 'N', strtotime ( $this->currentMonthStart) );		
		$monthEndingDay= date ( 'N', strtotime ( $this->currentYear . '-' . $this->currentMonth . '-' . $this->currentMonthDaysLength) );
		if ($monthEndingDay < $monthStartDay) {			
			$weekLength++;
		}
		
		return $weekLength;
	}
}
?>