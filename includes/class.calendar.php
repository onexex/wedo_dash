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
 *   .is-special (with is-holiday) -> special holiday (Htype 2, blue); without it -> regular (red)
 *   li.clckday.is-birthday       -> an active co-worker's birthday (.is-mybday = the viewer's own)
 *   template.wd-cal__tpl[data-day] -> ready-made day detail; the JS copies it into #calDayDetail
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
	private $htype = array();  // [day => 1 regular | 2 special]; regular wins if a day has both
	private $birthdays = null; // [day => [['name'=>..., 'me'=>bool], ...]] for the displayed month
	const CAKE = '<svg class="wd-cal__cake" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21h16M5 21v-7a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v7"/><path d="M5 16.5c1.5 1 3 1 4.5 0s3-1 4.5 0 3 1 4.5 0"/><path d="M12 12V8.5"/><path d="M12 6c.8 0 1.3-.6 1.3-1.3C13.3 3.8 12 2.5 12 2.5s-1.3 1.3-1.3 2.2c0 .7.5 1.3 1.3 1.3z"/></svg>';

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
		$this->loadBirthdays();
		$calendarHTML  = '<div id="calendar-outer" class="wd-cal">';
		$calendarHTML .= '<div class="calendar-nav wd-cal__nav">' . $this->getCalendarNavigation() . '</div>';
		$calendarHTML .= '<ul class="week-name-title">' . $this->getWeekDayName () . '</ul>';
		$calendarHTML .= '<ul class="week-day-cell">' . $this->getWeekDays () . '</ul>';
		$calendarHTML .= $this->getCalendarFooter();
		$calendarHTML .= $this->getDayDetail();
		$calendarHTML .= $this->getHolidayList();
		$calendarHTML .= $this->getBirthdayList();
		$calendarHTML .= $this->getDayTemplates();
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
			// PHP turns the keys '10'-'12' into ints, so compare as strings
			$sel = (str_pad($num, 2, '0', STR_PAD_LEFT) === $this->currentMonth) ? ' selected' : '';
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
		$b = 0;
		foreach ($this->birthdays as $people) { $b += count($people); }
		$html  = '<div class="wd-cal__foot">';
		$html .= '<span class="wd-cal__count">' . $n . ' holiday' . ($n == 1 ? '' : 's')
		       . ($b ? ' &middot; ' . $b . ' birthday' . ($b == 1 ? '' : 's') : '') . ' this month</span>';
		$html .= '<button type="button" class="wd-cal__today today" data-month="' . date('m') . '" data-year="' . date('Y') . '"'
		       . ($isCurrentMonth ? ' disabled' : '') . '>'
		       . '<i class="fa-regular fa-calendar-check" aria-hidden="true"></i> Today</button>';
		$html .= '</div>';
		return $html;
	}

	/* Day detail panel. Pre-filled with today when viewing the current month;
	   clicking a day copies that day's template (getDayTemplates) into it. */
	function getDayDetail() {
		$isThisMonth = (date('Y-m') === $this->currentYear . '-' . $this->currentMonth);
		$html = '<div class="wd-cal__detail" id="calDayDetail" aria-live="polite">';
		if ($isThisMonth) {
			$html .= $this->dayDetailHTML((int) date('j'));
		} else {
			$html .= '<div class="wd-cal__detail-empty"><i class="fa-regular fa-hand-pointer" aria-hidden="true"></i> Select a date to see its details.</div>';
		}
		$html .= '</div>';
		return $html;
	}

	/* The detail for one day: date, tags, holiday line, birthdays. */
	function dayDetailHTML($d) {
		$ymd = $this->currentYear . '-' . $this->currentMonth . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
		$ts = strtotime($ymd);
		$descs = isset($this->holidays[$d]) ? $this->holidays[$d] : array();
		$bdays = isset($this->birthdays[$d]) ? $this->birthdays[$d] : array();
		$mine = false;
		foreach ($bdays as $p) { if ($p['me']) { $mine = true; } }

		$html  = '<div class="wd-cal__detail-date">' . date('l, F j, Y', $ts) . '</div>';
		$html .= '<div class="wd-cal__detail-tags">';
		if ($ymd === date('Y-m-d')) { $html .= '<span class="wd-cal__tag wd-cal__tag--today">Today</span>'; }
		if ($descs) { $html .= $this->holidayTag($d); }
		if ($bdays) { $html .= '<span class="wd-cal__tag wd-cal__tag--bday">' . ($mine ? 'Your birthday' : 'Birthday') . '</span>'; }
		if (date('N', $ts) == 7) { $html .= '<span class="wd-cal__tag wd-cal__tag--rest">Sunday</span>'; }
		$html .= '</div>';
		$html .= '<div class="wd-cal__detail-body' . ($descs ? ' is-holiday' . ($this->isSpecial($d) ? ' is-special' : '') : '') . '">'
		       . ($descs ? htmlspecialchars(implode(' / ', $descs)) : 'No holiday on this day.') . '</div>';
		if ($bdays) {
			$html .= '<ul class="wd-cal__bdays">';
			foreach ($bdays as $p) {
				$html .= '<li' . ($p['me'] ? ' class="is-me"' : '') . '>' . self::CAKE
				       . ($p['me'] ? '<span><b>Happy birthday to you!</b></span>'
				                   : '<span><b>' . htmlspecialchars($p['name']) . '</b>&rsquo;s birthday</span>')
				       . '</li>';
			}
			$html .= '</ul>';
		}
		return $html;
	}

	/* One inert <template> per day, so the JS on corner.php and in the Corner
	   bubble only copies markup instead of each rebuilding the detail. */
	function getDayTemplates() {
		$html = '';
		for ($d = 1; $d <= $this->currentMonthDaysLength; $d++) {
			$html .= '<template class="wd-cal__tpl" data-day="' . $d . '">' . $this->dayDetailHTML($d) . '</template>';
		}
		return $html;
	}

	function getBirthdayList() {
		if (empty($this->birthdays)) { return ''; }
		$M = date ( 'M', strtotime ( $this->currentMonthStart ) );
		$html  = '<div class="wd-cal__holidays wd-cal__birthdays">';
		$html .= '<h4>Birthdays &middot; ' . $this->monthNames[$this->currentMonth] . ' ' . $this->currentYear . '</h4>';
		foreach ($this->birthdays as $day => $people) {
			$names = array();
			$mine = false;
			foreach ($people as $p) {
				$names[] = $p['me'] ? '<b class="wd-cal__you">You</b>' : htmlspecialchars($p['name']);
				if ($p['me']) { $mine = true; }
			}
			$dow = date('D', strtotime($this->currentYear . '-' . $this->currentMonth . '-' . str_pad($day, 2, '0', STR_PAD_LEFT)));
			$html .= '<div class="wd-cal__hitem wd-cal__bitem' . ($mine ? ' is-me' : '') . '" data-day="' . (int) $day . '">'
			       . '<b>' . $M . ' ' . (int) $day . '</b>'
			       . '<span><em class="wd-cal__hdow">' . $dow . '</em>' . implode(', ', $names) . '</span>'
			       . self::CAKE
			       . '</div>';
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
				$html .= '<div class="wd-cal__hitem hldviewer' . ($this->isSpecial($day) ? ' is-special' : '') . '" data-day="' . (int) $day . '">'
				       . '<b>' . $M . ' ' . (int) $day . '</b>'
				       . '<span><em class="wd-cal__hdow">' . $dow . '</em>' . htmlspecialchars(implode(' / ', $descs))
				       . ' <em class="wd-cal__htype">' . ($this->isSpecial($day) ? 'Special' : 'Regular') . '</em></span>'
				       . '</div>';
			}
		}
		$html .= '</div>';
		return $html;
	}

	function isSpecial($day) {
		return isset($this->htype[(int) $day]) && $this->htype[(int) $day] === 2;
	}

	function holidayTag($day) {
		return $this->isSpecial($day)
			? '<span class="wd-cal__tag wd-cal__tag--special">Special holiday</span>'
			: '<span class="wd-cal__tag wd-cal__tag--holiday">Regular holiday</span>';
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
			$stmt = $pdo->prepare("SELECT DAY(Hdate) AS d, Hdescription, Htype FROM holidays
			                       WHERE MONTH(Hdate)=:dtm AND YEAR(Hdate)=:yr AND HCompID=:cid
			                       ORDER BY Hdate");
			$stmt->execute(array(':dtm' => $this->currentMonth, ':yr' => $this->currentYear, ':cid' => $_SESSION['CompID']));
			while ($rw = $stmt->fetch(PDO::FETCH_ASSOC)) {
				$d = (int) $rw['d'];
				$this->holidays[$d][] = $rw['Hdescription'];
				$t = ((int) $rw['Htype'] === 1) ? 1 : 2;
				$this->htype[$d] = isset($this->htype[$d]) ? min($this->htype[$d], $t) : $t;
			}
		} catch (Exception $e) {
			/* calendar still renders, just without holiday markers */
		}
	}

	/* Birthdays of active employees (EmpStatusID 1, the same rule the birthday
	   announcements in query/query-login.php use) in the viewer's company. Only
	   the day is used: no birth year or age is ever shown. Feb 29 birthdays fall
	   on Mar 1 in non-leap years, like the announcements. */
	function loadBirthdays() {
		if ($this->birthdays !== null) { return; }
		$this->birthdays = array();

		if (session_status() === PHP_SESSION_NONE) { session_start(); }
		if (empty($_SESSION['CompID'])) { return; }
		$me = isset($_SESSION['id']) ? $_SESSION['id'] : '';
		$m = (int) $this->currentMonth;
		$leap = checkdate(2, 29, (int) $this->currentYear);

		try {
			include 'w_conn.php';
			$pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$stmt = $pdo->prepare("SELECT e.EmpID, e.EmpFN, e.EmpLN, MONTH(p.EmpDOB) AS bm, DAY(p.EmpDOB) AS bd
			                       FROM employees e
			                       INNER JOIN empdetails d ON d.EmpID = e.EmpID
			                       INNER JOIN empprofiles p ON p.EmpID = e.EmpID
			                       WHERE e.EmpStatusID = 1 AND d.EmpCompID = :cid
			                         AND (MONTH(p.EmpDOB) = :m OR (:m2 = 3 AND MONTH(p.EmpDOB) = 2 AND DAY(p.EmpDOB) = 29))
			                       ORDER BY DAY(p.EmpDOB), e.EmpFN, e.EmpLN");
			$stmt->execute(array(':cid' => $_SESSION['CompID'], ':m' => $m, ':m2' => $m));
			while ($rw = $stmt->fetch(PDO::FETCH_ASSOC)) {
				$bm = (int) $rw['bm']; $bd = (int) $rw['bd'];
				if ($bm === 2 && $bd === 29 && !$leap) { $bm = 3; $bd = 1; }
				if ($bm !== $m || $bd < 1) { continue; }
				$this->birthdays[$bd][] = array(
					'name' => trim($rw['EmpFN'] . ' ' . $rw['EmpLN']),
					'me'   => ($rw['EmpID'] === $me),
				);
			}
			ksort($this->birthdays);
		} catch (Exception $e) {
			/* calendar still renders, just without birthdays */
		}
	}

	function getWeekDays() {
		$this->loadHolidays();
		$this->loadBirthdays();
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
					if ($this->isSpecial($cellValue)) { $cls[] = 'is-special'; }
					$holidayAttr = ' data-holiday="' . htmlspecialchars(implode(' / ', $this->holidays[$cellValue])) . '"';
				}
				$title = date('l, F j, Y', strtotime($ymd));
				$cake = '';
				if (isset($this->birthdays[$cellValue])) {
					$cls[] = 'is-birthday';
					$who = array();
					foreach ($this->birthdays[$cellValue] as $p) {
						if ($p['me']) { $cls[] = 'is-mybday'; }
						$who[] = $p['me'] ? 'your birthday' : $p['name'] . "'s birthday";
					}
					$title .= ' - ' . implode(', ', $who);
					$cake = self::CAKE;
				}
				$isToday = ($isThisMonth && $cellValue == $today);
				if ($isToday) { $cls[] = 'is-today'; $cls[] = 'crntday'; $cls[] = 'is-active'; }

				$weekDays .= '<li id="' . $idd . '" class="' . implode(' ', $cls) . '" role="button" tabindex="0"'
				           . ' data-day="' . $cellValue . '" data-date="' . $ymd . '"'
				           . ' data-label="' . date('l, F j, Y', strtotime($ymd)) . '"' . $holidayAttr
				           . ' title="' . htmlspecialchars($title) . '">'
				           . '<span>' . $cellValue . '</span>' . $cake . '</li>';
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
