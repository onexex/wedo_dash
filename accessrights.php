<?php
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
    else{ header ('location: login.php'); }
    include 'w_conn.php';
?>

<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <!--  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.8.2/css/all.css" integrity="sha384-oS3vJWv+0UjzBfQzYUhtDYW+Pj2yciDJxpsK1OYPAYjqT085Qq/1cq5FLXAZQ7Ay" crossorigin="anonymous"> -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <!--  <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js" integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous"></script> -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <script src="assets/js/script.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
    
    <link rel="stylesheet" type="text/css" href="assets/css/style.css">
    <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
	<title>Access Rights</title>
	
	<script type="text/javascript">
	
        $(document).ready(function(){
            
            $('#txtemp').on("keyup input", function(){
                /* Get input value on change */
		        var inputVal = $(this).val();
		        var resultDropdown = $("#empdetails");
		        if(inputVal.length){
		            $.get("query/searchemp.php", {term: inputVal}).done(function(data){
		                // Display the returned data in browser
		                resultDropdown.html(data);
		            });
		        }else{
                    resultDropdown.empty();
		        }
            });
        
            $(document).on("click", "#empdetails a", function(){
                $("#empdetails").empty();
                $("#txtemp").val("");
                $(".empaccessr").html("Access Rights of " + $(this).text());
                $("#accr").empty();
                var idname = $(this).attr("id");
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        document.getElementById("accr").innerHTML = this.responseText;
                    }
                };
                xmlhttp.open("GET", "searchaccessrights.php?q=" + idname, true);
                xmlhttp.send();
          		$("#empidar").val(idname);
            });

            $(document).on("click", "#accr button", function(){
                var o = $(this).text();
            	if (o=="OFF"){
            		$(".modal-title").html("Are you sure you want to ON his/her Access?");
            		$("#emponoff").val("ON");
            	}else{
            		$(".modal-title").html("Are you sure you want to OFF his/her Access?");
            		$("#emponoff").val("OFF");
            	}
            	$(".btnacyes").attr("id", $(this).attr("id"));
            });

            $(document).on("click", ".modal-footer .btnacyes", function(){
                var inputVal = $(this).attr("id");
                var resultDropdown = $("#empdetails");
                var mpid = $("#empidar").val();
                var of = $("#emponoff").val();
                if(inputVal.length){
                    $.get("updateaccess.php?empid=" + mpid + "&mponoff=" + of, {term: inputVal}).done(function(data){
                        // Display the returned data in browser
                        resultDropdown.empty();
                        var idname = $(this).attr("id");
                        var xmlhttp = new XMLHttpRequest();
                        xmlhttp.onreadystatechange = function() {
                            if (this.readyState == 4 && this.status == 200) {
                                document.getElementById("accr").innerHTML = this.responseText;
    				        }
    				    };
                        xmlhttp.open("GET", "searchaccessrights.php?q=" + mpid, true);
                        xmlhttp.send();
                        $("#acchange").modal("hide");
                    });
    	        } else{
                    resultDropdown.empty();
    	        }
    	        
             });

        });
	</script>
	
	<style type="text/css">
        html, body{
            font-family: Tahoma !important;
        }
		#empdetails a:hover{
			background-color: red;
			color: #fff;
			cursor: pointer;
		}
	</style>
</head>
<body>
    <?php
        include 'includes/header.php';
    ?>
    <div class="w-container">
        <div class="row">
            <div class="col-lg-3"></div>
            <!-- website content -->
            <div class="col-lg-9">
                
                <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">Access Rights </h4>
                
         		<div class="row">
                    <div class="col-lg-6">
                        <div class="srch">
                            <div class="form-group" >
                                <label for="fname">Search Employee Lastname: </label>
                                <input type="text" class="form-control" name="txtempname"  id="txtemp" required="required" placeholder="Search Employee">
                            </div>
                            <div id="empdetails"></div>
                        </div>
                    </div>
                </div>

             	<h5 class="empaccessr"></h5	>
             	<input type="hidden" id="empidar" name="empidar">
             	<input type="hidden" id="emponoff" name="emponoff">
             	
				<table class="table">
					<thead>
						<tr>
						    <th>Modules</th>
						    <th>Access</th>
						</tr>
					</thead>
					<tbody id="accr">
						 <!-- The Modal-->
    					<div class="modal" id="acchange">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <button type="button" class="close" data-dismiss="modal">&times;</button>
                                    </div>
                                    
                                    <!-- Modal body -->
                                    <div class="modal-body">
                                        <h6 class="modal-title">Are you sure you want</h6>
                                    </div>
                              
                                    <!-- Modal footer -->
                                    <div class="modal-footer">
                                      <button type="button" id="" class="btn btn-success btnacyes">Yes</button>
                                      <button type="button" class="btn btn-danger" data-dismiss="modal">No</button>
                                    </div>
                                </div>
                            </div>
                        </div>
					</tbody>
				</table>
				
            </div>
         </div>
    </div>
</body>
</html>