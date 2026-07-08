<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); exit; }
?>
<?php
	date_default_timezone_set('Asia/Manila');

	// Debit Advise Settings is reskinned: it renders its own themed shell
	// (wd-header/wd-footer via wedo-theme) and stops before the legacy
	// maintenance layout below. It uses AJAX (debitPhpScript.php), so it does
	// not need the form-POST handlers in Query-updatemaintenance.php.
	if (isset($_GET['debitsetting'])) {
		include 'includes/debitsetting.php';
		exit;
	}

	//this include is the SQL of UPDATE (runs the per-row edit form POSTs, which
	//redirect via header() — must stay before any output below).
	include 'query/Query-updatemaintenance.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<title>Maintenance</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link rel="icon" href="assets/images/logos/WeDo.png" type="image/x-icon">

  <!-- Functional libs (Bootstrap modals + existing maintenance JS) -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
  <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <!-- v4 shims so the legacy `fa fa-pencil` icons in the maintenance sub-pages
       (and the AJAX-refreshed rows) still render under Font Awesome 6 -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/v4-shims.min.css">

  <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
  <link rel="stylesheet" type="text/css" href="assets/css/wedo-theme.css">

  <script type="text/javascript" src="assets/js/script.js"></script>
  <script type="text/javascript" src="assets/js/script-maintenance.js"></script>
  <script type="text/javascript" src="assets/js/script-updatemain.js"></script>

  <script type="text/javascript">
  // Company logo live preview (company.php upload field)
  function chng(ina){
    if (ina.files && ina.files[0]) {
      var reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById("ucomplogodiv").style.backgroundImage = "url('" + e.target.result + "')";
      }
    }
  }
  </script>

  <style type="text/css">
  /* ======================================================================
     Maintenance bridge — maps the legacy maintenance sub-page markup
     (.w-container/.col-lg-9/.page-title/.container-format/.table/.td-dar/
     .darth/.btn-*/.modal) onto the wedo-theme tokens. One place themes all
     ~25 sub-pages AND the AJAX-refreshed rows (query-searchmaintenance.php),
     which reuse the exact same classes.
     ====================================================================== */

  /* --- layout: drop the empty 3-col spacer, run content full width.
     (the sub-page's .w-container sits inside .wd-content-inner, and only the
     TOP-LEVEL .w-container>.row carries the page grid — modal .row's are nested
     deeper so they keep their Bootstrap gutters) --- */
  .wd-content .w-container{width:100%;max-width:100%;padding:0;margin:0}
  .wd-content .w-container > .row{margin-left:0;margin-right:0}
  .wd-content .w-container > .row > .col-lg-3{display:none}
  .wd-content .w-container > .row > .col-lg-9{width:100%;float:none;padding:0}

  /* --- page title (was inline company-color) --- */
  .wd-content .page-title{font-family:var(--font-head);font-weight:700;font-size:24px;
    color:var(--text)!important;margin:0 0 18px;letter-spacing:-.2px}
  .wd-content .company-name{color:var(--text)!important;border-bottom:1px solid var(--border)!important;
    font-family:var(--font-head);font-weight:600;font-size:15px;padding-bottom:10px!important}

  /* --- table framed as a card --- */
  .wd-content .container-format{overflow-x:auto;margin-bottom:22px}
  .wd-content .table{width:100%;border-collapse:collapse;font-size:13px;background:var(--surface);
    border:1px solid var(--border);border-radius:var(--radius-lg);box-shadow:var(--shadow-sm);
    margin-bottom:22px;overflow:hidden}
  .wd-content .container-format .table{margin-bottom:0}
  .wd-content .table thead th,
  .wd-content .table th.darth{background:var(--surface-2);color:var(--text-3);font-family:var(--font-head);
    font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;padding:12px 18px;
    border:none;border-bottom:1px solid var(--border);text-align:left;white-space:nowrap;vertical-align:middle}
  .wd-content .table td,
  .wd-content .table td.td-dar{padding:12px 18px;border:none;border-top:1px solid var(--border);
    color:var(--text-2);vertical-align:middle}
  .wd-content .table tbody tr:first-child td{border-top:none}
  .wd-content .table.table-bordered td,
  .wd-content .table.table-bordered th{border:1px solid var(--border)}
  .wd-content .table tbody tr:hover{background:var(--surface-2)}

  /* --- buttons: theme the Bootstrap variants onto wedo tokens --- */
  .wd-content .btn,.modal .btn{border:none;border-radius:var(--radius);font-family:var(--font-head);
    font-weight:600;font-size:13px;padding:9px 15px;box-shadow:none;transition:.15s;line-height:1.4}
  .wd-content .btn-primary,.modal .btn-primary{background:var(--brand);color:#fff}
  .wd-content .btn-primary:hover,.modal .btn-primary:hover{background:var(--brand-600);color:#fff}
  .wd-content .btn-success,.modal .btn-success{background:var(--ok-text);color:#fff}
  .wd-content .btn-danger,.modal .btn-danger{background:var(--danger-text);color:#fff}
  .wd-content .btn-warning,.modal .btn-warning{background:var(--warn-text);color:#fff}
  .wd-content .btn-default,.modal .btn-default{background:var(--surface-2);color:var(--text);border:1px solid var(--border-2)}
  .wd-content .btn-success:hover,.modal .btn-success:hover,
  .wd-content .btn-danger:hover,.modal .btn-danger:hover,
  .wd-content .btn-warning:hover,.modal .btn-warning:hover{filter:brightness(.93);color:#fff}
  .wd-content .btn-default:hover,.modal .btn-default:hover{background:var(--border)}
  /* compact icon action buttons in table rows (edit / view / delete) */
  .wd-content .table .btn-info,.modal .btn-info{background:var(--brand-tint);color:var(--brand)}
  .wd-content .table .btn-info:hover,.modal .btn-info:hover{background:var(--brand);color:#fff}
  .wd-content .table .btn{padding:7px 11px}

  /* --- inline form fields outside modals (search boxes etc.) --- */
  .wd-content .form-control{border-radius:var(--radius);border:1px solid var(--border-2);box-shadow:none;
    height:auto;padding:10px 12px;font-family:var(--font-body);font-size:14px;color:var(--text)}
  .wd-content .form-control:focus{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-tint)}
  .wd-content label{font-size:12.5px;font-weight:600;color:var(--text-2)}

  /* --- misc legacy widgets --- */
  .ths a{display:block;padding:10px;cursor:pointer}
  #dep option{display:none}
  .comlogod{height:100px;width:100%;background-position:center;background-repeat:no-repeat;background-size:contain}
  /* User Role live-search results dropdown (#empdetails a) */
  #empdetails{position:relative}
  #empdetails a{display:block;padding:9px 13px;border:1px solid var(--border);border-top:none;
    background:var(--surface);color:var(--text);cursor:pointer;font-size:13px}
  #empdetails a:first-child{border-top:1px solid var(--border);border-radius:var(--radius) var(--radius) 0 0}
  #empdetails a:last-child{border-radius:0 0 var(--radius) var(--radius)}
  #empdetails a:hover{background:var(--brand);color:#fff}
  /* Select-company dropdown (otfsm) */
  .wd-content .dropdown-menu{border:1px solid var(--border);border-radius:var(--radius);box-shadow:var(--shadow);padding:6px}
  .wd-content .dropdown-menu>li>a,.wd-content .dropdown-item{padding:8px 12px;border-radius:8px;color:var(--text)}
  .wd-content .dropdown-menu>li>a:hover,.wd-content .dropdown-item:hover{background:var(--surface-2)}

  /* Warning modal (script-maintenance.js) */
  #modalWarning .modal-body{text-align:center}
  #modalWarning i{font-size:50px;margin-bottom:10px;color:var(--warn-text)}
  #modalWarning .alert{margin-bottom:0}
  </style>

  <script type="text/javascript">
  // ---- User Role page (maintenance?userrole) live search + role toggle ----
  $(document).ready(function(){
    $('#txtemp').on("keyup input", function(){
      var inputVal = $(this).val();
      var resultDropdown = $("#empdetails");
      if(inputVal.length){
        $.get("query/Query-searchemprole.php", {term: inputVal}).done(function(data){
          resultDropdown.html(data);
        });
      } else {
        resultDropdown.empty();
      }
    });
    $(document).on("click", "#empdetails a", function(){
      $("#empdetails").empty();
      $("#txtemp").val("");
      $("#accr").empty();
      var idname = $(this).attr("id");
      var xmlhttp = new XMLHttpRequest();
      xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          document.getElementById("accr").innerHTML = this.responseText;
        }
      };
      xmlhttp.open("GET", "query/Query-searchurole.php?q=" + idname, true);
      xmlhttp.send();
      $("#empidar").val(idname);
    });

    $(document).on("click", ".changeurole", function(){
      var idd = this.id;
      var rl = $("#urole"+ this.id).val();
      $.ajax({
        url:'query/query-maintenance.php?userrole',
        type:'post',
        data: { empid : idd, usrole : rl },
        success:function(data){
          alert("Successfully Updated !");
        }
      });
      $('#myview').modal('hide');
      $("#accr").empty();
      var idname = $(this).attr("id");
      var xmlhttp = new XMLHttpRequest();
      xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          document.getElementById("accr").innerHTML = this.responseText;
        }
      };
      xmlhttp.open("GET", "query/Query-searchurole.php?q=" + idname, true);
      xmlhttp.send();
    });
  });
  </script>
</head>
<body>
	<?php
		// Tell the sidebar which Maintenance sub-page is open so it highlights the
		// exact item (and lights up the "Maintenance" section header) — otherwise
		// the user has no "you are here" cue in the menu.
		$wd_active = 'maintenance';
		foreach (['agency','classification','company','department','employeestatus','eovalidation',
		          'parentalfamilydetails','hmo','holiday','joblevel','leavevalidation','lilovalidation',
		          'obvalidation','otfsm','pagibig','philhealth','position','relationship','silloan','sss',
		          'typesofleave','userrole','worktime','workdays','gpreiod'] as $wdk) {
			if (isset($_GET[$wdk])) { $wd_active = $wdk; break; }
		}
		include 'includes/wd-header.php';

		// Some legacy sub-pages (e.g. includes/company.php) reference an ambient
		// $pdo that the OLD includes/header.php used to create before the router.
		// wd-header.php builds $wdpdo instead, so re-provide $pdo here to keep those
		// includes working. ($servername/$db/$username/$password come from the
		// w_conn.php that wd-header.php just included.)
		try {
			$pdo = new PDO("mysql:host=$servername;dbname=$db", $username, $password);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		} catch (PDOException $e) {
			die("ERROR: Could not connect. " . $e->getMessage());
		}
	?>
	<?php

		if (isset($_GET['agency'])){
			// if the url is maintenance.php/agency
			// it includes the php file from includes folder
			// the agency.php file is HTML only
			include_once 'includes/agency.php';
		}
		else if (isset($_GET['company'])){
			// if the url is maintenance.php/company
			// it includes the php file from includes folder
			// the company.php file is HTML only
			include_once 'includes/company.php';
		}
		else if (isset($_GET['department'])){

			include_once 'includes/department.php';
		}
		else if (isset($_GET['hmo'])){
				include_once 'includes/HMO.php';
		}
		else if (isset($_GET['position'])){
				include_once 'includes/position.php';
		}
		else if (isset($_GET['joblevel'])){
				include_once 'includes/JobLevel.php';
		}
		else if (isset($_GET['employeestatus'])){
				include_once 'includes/empStatus.php';
		}
		else if (isset($_GET['relationship'])){
				include_once 'includes/relationship.php';
		}
		else if (isset($_GET['classification'])){
				include_once 'includes/classification.php';
		}
		else if (isset($_GET['worktime'])){
				include_once 'includes/worktime.php';
		}
		else if (isset($_GET['workdays'])){
				include_once 'includes/workdays.php';
		}
		else if (isset($_GET['userrole'])){
				include_once 'includes/userrole.php';
		}
		else if (isset($_GET['leavevalidation'])){
				include_once 'includes/leavevalidation.php';
		}
		else if (isset($_GET['typesofleave'])){
				include_once 'includes/typesofleave.php';
		}
		else if (isset($_GET['otfsm'])){
				include_once 'includes/otfsm.php';
		}
		else if (isset($_GET['holiday'])){
				include_once 'includes/holiday.php';
		}
		else if (isset($_GET['gpreiod'])){
				include_once 'includes/gperiod.php';
		}
		else if (isset($_GET['lilovalidation'])){
				include_once 'includes/lilovalidation.php';
		}
		else if (isset($_GET['obvalidation'])){
				include_once 'includes/obvalidation.php';
		}
		else if (isset($_GET['eovalidation'])){
				include_once 'includes/eovalidation.php';
		}
		else if (isset($_GET['sss'])){
			include_once 'includes/sss.php';
		}
		else if (isset($_GET['pagibig'])){
			include_once 'includes/pagibig.php';
		}
		else if (isset($_GET['philhealth'])){
			include_once 'includes/philhealth.php';
		}
		else if (isset($_GET['parentalfamilydetails'])){
			include_once 'includes/parentalfamilydetails.php';
		}
		else if (isset($_GET['silloan'])){
			include_once 'includes/silloan.php';
		}
		else{
				include_once 'includes/errorpage.php';
		}
	?>

	   <!-- Warning / success dialog (toggled by assets/js/script-maintenance.js) -->
	<div class="modal" id="modalWarning">
	  <div class="modal-dialog">
	    <div class="modal-content">
	      <div class="modal-header" style="padding: 7px 8px;">
	        <button type="button" class="close" data-dismiss="modal">&times;</button>
	      </div>
	      <div class="modal-body">
	        <i class="fa fa-exclamation-circle" aria-hidden="true"></i>
	        <div class="alert alert-danger"></div>
	      </div>
	    </div>
	  </div>
	</div>
	<!-- modal end -->

	<?php include 'includes/wd-footer.php'; ?>
</body>
</html>
