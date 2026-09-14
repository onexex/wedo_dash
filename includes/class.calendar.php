<?php

date_default_timezone_set("Asia/Manila");

/**
 * Month calendar widget used by corner.php (initial render) and
 * includes/calendar-ajax.php (month navigation).
 *
 * Markup contract used by the JS in corner.php:
 *   .prev / .next / .today       -> data-month / data-year of the month to load
 *   #currentMonth / #currentYear -> <select>s; change loads that month
 *   li.clckday.is-holiday        -> data-day; click highlights .wd-cal__hitem[data-day]
 */
class PHPCalendar {
	private $weekDayName = array ("MON","TUE","WED","THU","FRI","SAT","SUN");
	private $monthNames  = array ('01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June',
	                             '07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December');
	private $currentDay = 0;
	private $currentMonth = 0;
	private $currentYear = 0;
	private $currentMonthStart = null;
	private $currentMonthDaysLength = null;
	private $holidays = null; // [day => [desc, ...]] for the displayed month

	function __construct() {
		$this->currentYear = date ( "Y", time () );
		$this->currentMonth = date ( "m", time () );

		// Only accept sane values from POST; anything else falls back to today.
		if (! empty ( $_POST ['year'] ) && preg_match('/^\d{4}$/', $_POST['year'])) {
			$this->currentYear = $_POST ['year'];
		}
		if (! empty ( $_POST ['month'] )) {
			$m = (int) $_POST ['month'];
			if ($m >= 1 && $m <= 12) { $this->currentMonth = str_pad($m, 2, '0', STR_PAD_LEFT); }
		}
		$this->currentMonthStart = $this->currentYear . '-' . $this->currentMonth . '-01';
		$this->currentMonthDaysLength = date ( 't', strtotime ( $this->currentMonthStart ) );
	}

	function getCalendarHTML() {
		$this->loadHolidays();
		$calendarHTML  = '<div id="calendar-outer" class="wd-cal">';
		$calendarHTML .= '<div class="calendar-nav wd-cal__nav">' . $this->getCalendarNavigation() . '</div>';
		$calendarHTML .= '<ul class="week-name-title">' . $this->getWeekDayName () . '</ul>';
		$calendarHTML .= '<ul class="week-day-cell">' . $this->getWeekDays () . '</ul>';
		$calendarHTML .= $this->getCalendarFooter();
		$calendarHTML .= $this->getDayDetail();
		$calendarHTML .= $this->getHolidayList();
		$calendarHTML .= '</div>';
		return $calendarHTML;
	}

	function getCalendarNavigation() {
		$prevMonthYearArray = explode(",", date ( 'm,Y', strtotime ( $this->currentMonthStart . ' -1 Month' ) ));
		$nextMonthYearArray = explode(",", date ( 'm,Y', strtotime ( $this->currentMonthStart . ' +1 Month' ) ));

		$navigationHTML  = '<button type="button" class="wd-cal__btn prev" title="Previous month" aria-label="Previous month"'
		                 . ' data-month="' . $prevMonthYearArray[0] . '" data-year="' . $prevMonthYearArray[1] . '"'
		                 . ' data-prev-month="' . $prevMonthYearArray[0] . '" data-prev-year="' . $prevMonthYearArray[1] . '">'
		                 . '<i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>';

		$navigationHTML .= '<div class="wd-cal__title">';
		$navigationHTML .= '<select id="currentMonth" class="wd-cal__select wd-cal__select--month" aria-label="Month">';
		foreach ($this->monthNames as $num => $name) {
			$sel = ($num === $this->currentMonth) ? ' selected' : '';
			$navigationHTML .= '<option value="' . $num . '"' . $sel . '>' . $name . '</option>';
		}
		$navigationHTML .= '</select>';

		$thisYear = (int) date('Y');
		$minYear = min($thisYear - 5, (int) $this->currentYear);
		$maxYear = max($thisYear + 5, (int) $this->currentYear);
		$navigationHTML .= '<select id="currentYear" class="wd-cal__select wd-cal__select--year" aria-label="Year">';
		for ($y = $minYear; $y <= $maxYear; $y++) {
			$sel = ($y === (int) $this->currentYear) ? ' selected' : '';
			$navigationHTML .= '<option value="' . $y . '"' . $sel . '>' . $y . '</option>';
		}
		$navigationHTML .= '</select>';
		$navigationHTML .= '</div>';

		$navigationHTML .= '<button type="button" class="wd-cal__btn next" title="Next month" aria-label="Next month"'
		                 . ' data-month="' . $nextMonthYearArray[0] . '" data-year="' . $nextMonthYearArray[1] . '"'
		                 . ' data-next-month="' . $nextMonthYearArray[0] . '" data-next-year="' . $nextMonthYearArray[1] . '">'
		                 . '<i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>';
		return $navigationHTML;
	}

	function getCalendarFooter() {
		$isCurrentMonth = (date('Y-m') === $this->currentYear . '-' . $this->currentMonth);
		$n = count($this->holidays);
		$html  = '<div class="wd-cal__foot">';
		$html .= '<span class="wd-cal__count">' . $n . ' holiday' . ($n == 1 ? '' : 's') . ' this month</span>';
		$html .= '<button type="button" class="wd-cal__today today" data-month="' . date('m') . '" data-year="' . date('Y') . '"'
		       . ($isCurrentMonth ? ' disabled' : '') . '>'
		       . '<i class="fa-regular fa-calendar-check" aria-hidden="true"></i> Today</button>';
		$html .= '</div>';
		return $html;
	}

	/* Day detail panel. Pre-filled with today when viewing the current month;
	   the JS in corner.php refills it from the clicked cell's data-* attributes. */
	function getDayDetail() {
		$isThisMonth = (date('Y-m') === $this->currentYear . '-' . $this->currentMonth);
		$html = '<div class="wd-cal__detail" id="calDayDetail" aria-live="polite">';
		if ($isThisMonth) {
			$d = (int) date('j');
			$descs = isset($this->holidays[$d]) ? $this->holidays[$d] : array();
			$isSunday = (date('N') == 7);
			$html .= '<div class="wd-cal__detail-date">' . date('l, F j, Y') . '</div>';
			$html .= '<div class="wd-cal__detail-tags">';
			$html .= '<span class="wd-cal__tag wd-cal__tag--today">Today</span>';
			if ($descs)    { $html .= '<span class="wd-cal__tag wd-cal__tag--holiday">Holiday</span>'; }
			if ($isSunday) { $html .= '<span class="wd-cal__tag wd-cal__tag--rest">Sunday</span>'; }
			$html .= '</div>';
			$html .= '<div class="wd-cal__detail-body' . ($descs ? ' is-holiday' : '') . '">'
			       . ($descs ? htmlspecialchars(implode(' / ', $descs)) : 'No holiday on this day.') . '</div>';
		} else {
			$html .= '<div class="wd-cal__detail-empty"><i class="fa-regular fa-hand-pointer" aria-hidden="true"></i> Select a date to see its details.</div>';
		}
		$html .= '</div>';
		return $html;
	}

	function getHolidayList() {
		$html  = '<div class="wd-cal__holidays">';
		$html .= '<h4>Holidays &middot; ' . $this->monthNames[$this->currentMonth] . ' ' . $this->currentYear . '</h4>';
		if (empty($this->holidays)) {
			$html .= '<p class="wd-cal__hempty">No holidays this month.</p>';
		} else {
			$M = date ( 'M', strtotime ( $this->currentMonthStart ) );
			foreach ($this->holidays as $day => $descs) {
				$dow = date('D', strtotime($this->currentYear . '-' . $this->currentMonth . '-' . str_pad($day, 2, '0', STR_PAD_LEFT)));
				$html .= '<div class="wd-cal__hitem hldviewer" data-day="' . (int) $day . '">'
				       . '<b>' . $M . ' ' . (int) $day . '</b>'
				       . '<span><em class="wd-cal__hdow">' . $dow . '</em>' . htmlspecialchars(implode(' / ', $descs)) . '</span>'
				       . '</div>';
			}
		}
		$html .= '</div>';
		return $html;
	}

	function getWeekDayName() {
		$WeekDayName = '';
		foreach ( $this->weekDayName as $dayname ) {
			$WeekDayName .= '<li>' . $dayname . '</li>';
		}
		return $WeekDayName;
	}

	/* One query for the whole month instead of one per cell. */
	function loadHolidays() {
		if ($this->holidays !== null) { return; }
		$this->holidays = array();

		if (session_status() === PHP_SESSION_NONE) { session_start(); }
		if (empty($_SESSION['CompID'])) { return; }

		try {
			include 'w_conn.php';
			$pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$stmt = $pdo->prepare("SELECT DAY(Hdate) AS d, Hdescription FROM holidays
			                       WHERE MONTH(Hdate)=:dtm AND YEAR(Hdate)=:yr AND HCompID=:cid
			                       ORDER BY Hdate");
			$stmt->execute(array(':dtm' => $this->currentMonth, ':yr' => $this->currentYear, ':cid' => $_SESSION['CompID']));
			while ($rw = $stmt->fetch(PDO::FETCH_ASSOC)) {
				$this->holidays[(int) $rw['d']][] = $rw['Hdescription'];
			}
		} catch (Exception $e) {
			/* calendar still renders, just without holiday markers */
		}
	}

	function getWeekDays() {
		$this->loadHolidays();
		$weekLength = $this->getWeekLengthByMonth ();
		$firstDayOfTheWeek = date ( 'N', strtotime ( $this->currentMonthStart ) );
		$isThisMonth = (date('Y-m') === $this->currentYear . '-' . $this->currentMonth);
		$today = (int) date('j');

		$M = date ( 'M', strtotime ( $this->currentMonthStart ) );
		$Y = $this->currentYear;
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

				if ($cellValue === null) {
					$weekDays .= '<li class="is-empty" aria-hidden="true"></li>';
					continue;
				}

				$idd = $M . $cellValue . $Y;
				$ymd = $Y . '-' . $this->currentMonth . '-' . str_pad($cellValue, 2, '0', STR_PAD_LEFT);
				$cls = array('clckday');
				$holidayAttr = '';
				if ($j == 7) { $cls[] = 'is-sunday'; }
				if (isset($this->holidays[$cellValue])) {
					$cls[] = 'is-holiday'; $cls[] = 'hldyac';
					$holidayAttr = ' data-holiday="' . htmlspecialchars(implode(' / ', $this->holidays[$cellValue])) . '"';
				}
				$isToday = ($isThisMonth && $cellValue == $today);
				if ($isToday) { $cls[] = 'is-today'; $cls[] = 'crntday'; $cls[] = 'is-active'; }

				$weekDays .= '<li id="' . $idd . '" class="' . implode(' ', $cls) . '" role="button" tabindex="0"'
				           . ' data-day="' . $cellValue . '" data-date="' . $ymd . '"'
				           . ' data-label="' . date('l, F j, Y', strtotime($ymd)) . '"' . $holidayAttr
				           . ' title="' . date('l, F j, Y', strtotime($ymd)) . '">'
				           . '<span>' . $cellValue . '</span></li>';
			}
		}
		return $weekDays;
	}

	function getWeekLengthByMonth() {
		$weekLength = intval ( $this->currentMonthDaysLength / 7 );
		if ($this->currentMonthDaysLength % 7 > 0) {
			$weekLength++;
		}
		$monthStartDay = date ( 'N', strtotime ( $this->currentMonthStart ) );
		$monthEndingDay = date ( 'N', strtotime ( $this->currentYear . '-' . $this->currentMonth . '-' . $this->currentMonthDaysLength ) );
		if ($monthEndingDay < $monthStartDay) {
			$weekLength++;
		}
		return $weekLength;
	}
}
?>
