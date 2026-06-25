<?php 
if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
?>
<?php
	date_default_timezone_set('Asia/Manila');
	include 'query/Query-updatemaintenance.php';
?>

<!DOCTYPE html>
<html>
<head>
	<title>Maintenance</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">

	<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
	 <!--  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/all.css" integrity="sha384-oS3vJWv+0UjzBfQzYUhtDYW+Pj2yciDJxpsK1OYPAYjqT085Qq/1cq5FLXAZQ7Ay" crossorigin="anonymous"> -->
	<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	<!-- <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>--> 
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
	  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

    <!-- <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script> -->
	<script type="text/javascript" src="assets/js/script.js"></script>
	<script type="text/javascript" src="assets/js/script-maintenance.js"></script>
	<script type="text/javascript" src="assets/js/script-updatemain.js"></script>
	<link rel="stylesheet" type="text/css" href="assets/css/style.css">

    <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
	<script type="text/javascript">
	 
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
	  	.ths a{
	  		display: block;
	  		padding: 10px;
	  		cursor: pointer;
	  	}
	  	@media (min-width: 1200px){
				.container {
     width: 970px; 
		}
	}
	  	#dep option{
	  		display: none;
	  	}
	  	.comlogod{
	  		height: 100px;
	  		width: 100%;
	  		background-position: center;
		    background-repeat: no-repeat;
		    background-size: contain;
	  	}
	  </style>
	<script type="text/javascript">
		    $(document).ready(function(){
        $('#txtemp').on("keyup input", function(){
    
        /* Get input value on change */
		        var inputVal = $(this).val();
		        var resultDropdown = $("#empdetails");
		        if(inputVal.length){
		    
		            $.get("query/Query-searchemprole.php", {term: inputVal}).done(function(data){
		                // Display the returned data in browser
		                resultDropdown.html(data);

		            });
		        } else{
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
	<style type="text/css">
		#empdetails a:hover{
			background-color: red;
			color: #fff;
			cursor: pointer;
		}
		#modalWarning .modal-body{
			text-align: center;
			
		}
		#modalWarning i{
			font-size: 50px;
		    margin-bottom: 10px;
		    color: #d1c156;
		}
	</style>
</head>
<body style="background-image: none">
	<?php
		include 'includes/header.php';
	?>
	<?php
		if (isset($_GET['agency'])){
			include_once 'includes/agency.php';
		}
		else if (isset($_GET['company'])){
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
		else{
				include_once 'includes/errorpage.php';
		}
	?>

	   <!-- The Modal -->
        <div class="modal" id="modalWarning">
          <div class="modal-dialog">
            <div class="modal-content">
            
              <!-- Modal Header --> 
              <div class="modal-header" style="padding: 7px 8px;">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
              </div>
              
              <!-- Modal body -->
              <div class="modal-body">
              	<i class="fa fa-exclamation-circle" aria-hidden="true"></i>
                <div class="alert alert-danger">
            
            </div>
              </div>
              
              <!-- Modal footer -->
           
              
            </div>
          </div>
        </div>
        <!-- modal end -->  
</body>
</html>