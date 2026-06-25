<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
?>
<?php
    include 'w_conn.php';
      date_default_timezone_set("Asia/Manila"); 
?>

<!DOCTYPE html>
<html>
<head>
  
     <title><?php  if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo "AMS"; } ?></title> 
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
	  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
	  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
	  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script> 
	  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
	  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
	  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
	  <script type="text/javascript" src="assets/js/script.js"></script>
	  <script src="assets/js/script-reports.js"></script>
	  <script type="text/javascript" src="assets/js/script-modules.js"></script>
	  <link rel="stylesheet" type="text/css" href="assets/css/style.css">
  <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
    <script type="text/javascript" src="assets/js/ams.js"></script>
      <script type="text/javascript"> 
        $(document).ready(function() {
        
            // $(".buttons-html5").remove("span");
            // $('#tab').DataTable( {
            //     dom: 'Bfrtip',
            //     buttons: [
            //         'excelHtml5',
            //         'csvHtml5'
            //     ]
            // } );

        $(document).on('click', '#btnprint', function(e) {
            var css = '@page { size: portrait; }',
            head = document.head || document.getElementsByTagName('head')[0],
            style = document.createElement('style');

            style.type = 'text/css';
            style.media = 'print';

            if (style.styleSheet){
                style.styleSheet.cssText = css;
            } else {
                style.appendChild(document.createTextNode(css));
            }
            head.appendChild(style);    
            var originalContents = document.body.innerHTML;
            $("#amstable td").css("padding", "9px");
            $("#amstable td").css("text-align", "left");
            $("#amstable td").css("font-size", "10px");
            $("#tabth th").css("font-size", "10px");
             $(".hide_cell").removeClass("d-block").addClass("d-none");

            
            $("#tab").css({
                overflow: 'hidden',
                height: '100%'
                });

                $("#tab").css({
                overflow: 'auto',
                height: 'auto'
                });
            $(".captionText").css("font-size","12px");
            $(".captionText").removeClass("d-none").addClass("d-block");
            $("#captionText").html($("#empcompid option:selected").text());
            $("#dateRange").html($("#dtp1").val() + " To : " + $("#dtp2").val());

            var printContents = document.getElementById('tblprint').innerHTML;
            
            document.body.innerHTML = printContents;
            window.print();
            document.body.innerHTML = originalContents;
            $(".hide_cell").removeClass("d-none").addClass("d-block");


            });
        } );
    </script>

<style>
  html body{
		font-family: Tahoma !important;
	}
    .formlabel{
        color:red !important;
        display:none;
    }
    .overlay{
        display: none;
        position: fixed;
        width: 100%;
        height: 100%;
        top: 0;
        left: 0;
        z-index: 999;
        background: rgba(255,255,255,0.8) url("assets/images/01.gif") center no-repeat;
    }
    
    /* Turn off scrollbar when body element has the loading class */
    body.loading{
        overflow: hidden;   
    }
    /* Make spinner image visible when body element has the loading class */
    body.loading .overlay{
        display: block;
    }
</style>
</head>
<body>
<?php  include 'includes/header.php'; ?>
<div class="w-container">
        <div class="row">
            <div class="col-lg-3"></div>
            <div class="col-lg-9 module-content">
       			 <h4 >Archive Management System</h4>
                    <div class="row">
                        <div class="col-lg-12">
                            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#amsview">+ Register Employee</button>
                            <button type="button" class="btn btn-primary" id="viewData">Show All</button>
                            <button type="button" class="btn btn-danger" id="btnprint">Print</button>
                        </div>                       
                        <div class="col-lg-12">
                        <hr>
                            <input type="text" class="form-control" id="amssearch" placeholder="Search Last Name" />
                            <hr>
                        </div> 
                        <div class="col-lg-12" id="tblprint">
                        <br>                         
                            <table class="table table-hover"  id="tab" >
                                <thead class="table-dark" id="tabth">
                                <tr>
                                    <th>Name</th>
                                    <th>Position/Title/Level</th>
                                    <th>Status</th>
                                    <th>Verified By</th>
                                    <th class="hide_cell" >Action</th>
                                </tr>
                                </thead>
                                <tbody id="amstable">
                                
                                </tbody>
                            </table>
                        </div>                          
                    </div>
            </div>
        </div>
            <!-- //modal register -->
            <div class="modal" id="amsview" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Registration Form</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form  id="amsid">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="fname">First Name</label>
                                    <input type="text" class="form-control" id="fname" placeholder="First Name">
                                    <small id="lblfname" class="form-text text-muted formlabel">This is a required Field!</small>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="lname">Last Name</label>
                                    <input type="text" class="form-control" id="lname" placeholder="Last Name">
                                    <small id="lbllname" class="form-text text-muted formlabel">This is a required Field!</small>
                                </div>

                            </div>
                            <div class="form-row">                           
                                <div class="form-group col-md-12">
                                <label for="Position">Position</label>
                                <select id="pos" class="form-control">
                                    <option selected>Choose...</option>
                                    <option>Programmer</option>
                                    <option>IT Supervisor</option>
                                    <option>Outbound Sales Agent</option>
                                     <option>Outbound Specialist</option>
                                       <option>Inbound Sales Agent</option>
                                       <option>Inbound Technical Support Personnel</option>
                                         <option>CSR</option> 
                                         <option>Encoder</option> 
                                           <option>TSR</option> 
                                           <option>Graphic Designer</option> 
                                            <option>Staff-OGM</option> 
                                             <option>Quality Auditor</option> 
                                         <option>Research Analyst</option>
                                           <option>Sales Specialist</option>
                                           <option>Database Administrator</option>
                                            <option>Team Supervisor</option>
                                             <option>Security Personnel</option>
                                    <option>IT Support Specialist</option>
                                    <option>Graphic Designer</option>
                                    <option>Project Manager</option>
                                    <option>General Manager</option>
                                     <option>Operation Manager</option>
                                    <option>SNS Administrator</option>
                                    <option>Utility Personnel</option>
                                    <option>Team Leader</option>
                                    <option>Company Driver</option>
                                    <option>Admin Staff</option>
                                    <option>Admin and Finance Manager</option>
                                    <option>Remote Contact Center Associates</option>
                                    <option>Outbound Telemarketing Specialist</option>
                                </select>
                                <small id="lblpos" class="form-text text-muted formlabel">This is a required Field!</small>
                            </div>
                            </div>
                            <label for="empdate">Employment Date</label>
                            <div class="form-row">                           
                                <div class="form-group col-md-6">
                                    <label for="dfrom">From:</label>
                                    <input type="date" class="form-control" id="dfrom" >
                                    <small id="lblemploymentfrom" class="form-text text-muted formlabel">This is a required Field!</small>
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="dto">To:</label>
                                    <input type="date" class="form-control" id="dto" >
                                    <small id="lblemploymentto" class="form-text text-muted formlabel">This is a required Field!</small>
                                </div>   
                                
                            </div>

                        <div class="form-row">                           
                            <div class="form-group col-md-6">
                                <label for="Employment Status">Employment Classification </label>
                                <select id="status" class="form-control">
                                    <!--<option selected>Choose...</option>-->
                                    <option>Back End</option>
                                    <option>IC</option>
                                        <option>TR</option>
                                        <option>Probationary</option>
                                </select>
                                <small id="lblempstatus" class="form-text text-muted formlabel">This is a required Field!</small>
                            </div>

                            <div class="form-group col-md-6">
                                <label for="dto">Clearance</label>
                                <!--<input type="text" class="form-control" id="clerance" >-->
                               <select id="clerance" name=""  class="form-control">
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>

                                </select>
                                <small id="lblclearance" class="form-text text-muted formlabel">This is a required Field!</small>
                            </div>                           
                        </div>

                            <div class="form-row">                          
                                <div class="form-group col-md-12">
                                    <label for="reason">Reason for Leaving</label>                                   
                                    <input type="text" id="reason" name="reason" class="form-control"> </input>
                                    <small id="lblreason" class="form-text text-muted formlabel">This is a required Field!</small>
                                </div>                               
                            </div>

                            <div class="form-row">                          
                                <div class="form-group col-md-12">
                                    <label for="derogatory">Derogatory Records</label>                                   
                                    <!--<input type="text" class="form-control"id="derogatory"  > </input>-->
                                     <textarea id="derogatory" name="w3review" class="form-control" rows="4" cols="50">
                                    </textarea>
                                    <small id="lblderogatory" class="form-text text-muted formlabel">This is a required Field!</small>
                                </div>                               
                            </div>

                        <div class="form-row d-none">                           
                            <div class="form-group col-md-6">
                                <label for="Salary">Salary</label>
                                <input type="number" value="n/a" class="form-control" id="salary" >
                                <small id="lblsalary" class="form-text text-muted formlabel">This is a required Field!</small>
                                
                            </div>
                            <div class="form-group col-md-6">
                                <label for="resignation">Pending Resignation</label>
                                <input type="text" value="n/a class="form-control" id="resignation" >
                                <small id="lblesignation" class="form-text text-muted formlabel">This is a required Field!</small>
                            </div>                           
                        </div>

                            <div class="form-row">                          
                                <div class="form-group col-md-12">
                                    <label for="addrem">Additional Remarks</label>                                   
                                    <textarea id="addrem" name="adrem" row="2" col="50" class="form-control"> </textarea>
                                    <small id="lbladdrem" class="form-text text-muted formlabel">This is a required Field!</small>
                                    
                                    <label class="d-none" for="veri">Verified By</label>                                   
                                    <input class="d-none" id="addver" value=" <?php echo $_SESSION['id'] ?>" name="addver" class="form-control"/>
                                    <small class="d-none" id="lbladdver" class="form-text text-muted formlabel">This is a required Field!</small>
                                </div>                               
                            </div>

                        </form>
                        <div  id="result" class="alert alert-success" style="display:none"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="save">Submit</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                    </div>
                </div>
                </div>
            <!-- //modal view end-->

                        <!-- //modal view -->
            <div class="modal" id="amsshow" tabindex="-1" role="dialog">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Employee Update</h4>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="amsidu">
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="fname">First Name</label>
                                    <input type="text" class="form-control" id="fname1" placeholder="First Name">
                                      <small id="lblfname1" class="form-text text-muted formlabel">This is a required Field!</small>
                                    
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="lname">Last Name</label>
                                    <input type="text" class="form-control" id="lname1" placeholder="Last Name">
                                      <small id="lbllname" class="form-text text-muted formlabel">This is a required Field!</small>
                                </div>

                            </div>
                            <div class="form-row">                           
                                <div class="form-group col-md-12">
                                <label for="Position">Position</label>
                                <select id="pos1" class="form-control">
                                    <option selected>Choose...</option>
                                    <option>Programmer</option>
                                    <option>IT Supervisor</option>
                                    <option>Outbound Sales Agent</option>
                                     <option>Outbound Specialist</option>
                                       <option>Inbound Sales Agent</option>
                                       <option>Inbound Technical Support Personnel</option>
                                         <option>CSR</option>
                                         <option>Encoder</option> 
                                             <option>TSR</option> 
                                               <option>Staff-OGM</option> 
                                                 <option>Quality Auditor</option> 
                                           <option>Research Analyst</option>
                                            <option>Sales Specialist</option>
                                               <option>Operation Manager</option>
                                               <option>Database Administrator</option>
                                                   <option>Team Supervisor</option>
                                                      <option>Security Personnel</option>
                                                         <option>Graphic Designer</option> 
                                    <option>IT Support Specialist</option>
                                    <option>Graphic Designer</option>
                                    <option>Project Manager</option>
                                    <option>General Manager</option>
                                    <option>SNS Administrator</option>
                                    <option>Utility Personnel</option>
                                    <option>Team Leader</option>
                                    <option>Company Driver</option>
                                    <option>Admin Staff</option>
                                    <option>Admin and Finance Manager</option>
                                    <option>Remote Contact Center Associates</option>
                                     <option>Outbound Telemarketing Specialist</option>
                                    
                                </select>
                                <small id="lblpos1" class="form-text text-muted formlabel">This is a required Field!</small>
                            </div>
                            </div>
                            <label for="empdate">Employment Date</label>
                            <div class="form-row">
                            
                                <div class="form-group col-md-6">
                                    <label for="dfrom">From:</label>
                                    <input type="date" class="form-control" id="dfrom1" >
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="dto">To:</label>
                                    <input type="date" class="form-control" id="dto1" >
                                </div>
                                
                            </div>

                        <div class="form-row">                           
                            <div class="form-group col-md-6">
                                <label for="Employment Status">Employment Status</label>
                                <select id="status1" class="form-control">
                                    <option selected>Choose...</option>
                                    <option>Back End</option>
                                    <option>IC</option>
                                    <option>TR</option>
                                    <option>Probationary</option>
                                </select>
                            </div>


                            <div class="form-group col-md-6">
                                <label for="dto">Clearance</label>
                                <!--<input type="text" class="form-control" id="clerance1" >-->
                                <select id="clerance1" name="" class="form-control">
                                    <option value="Yes">Yes</option>
                                    <option value="No">No</option>

                                </select>
                            </div>                           
                        </div>


                            <div class="form-row">                          
                                <div class="form-group col-md-12">
                                    <label for="reason">Reason for Leaving</label>                                   
                                    <input type="text" id="reason1" name="reason" class="form-control"> </input>
                                </div>                               
                            </div>

                            <div class="form-row">                          
                                <div class="form-group col-md-12">
                                    <label for="derogatory">Derogatory Records</label>                                   
                                    <!--<input type="text" class="form-control"id="derogatory1"  > </input>-->
                                     <textarea id="derogatory1" name="w3review" class="form-control" rows="4" cols="50">
                                    </textarea>
                                </div>                               
                            </div>

                        <div class="form-row d-none">                           
                            <div class="form-group col-md-6">
                                <label for="Salary">Salary</label>
                                <input type="number" value="0" class="form-control" id="salary1" >
                            </div>
                            <div class="form-group col-md-6">
                                <label for="resignation">Pending Resignation</label>
                                <input type="text" value="n/a"  class="form-control" id="resignation1" >
                            </div>                           
                        </div>

                            <div class="form-row ">                          
                                <div class="form-group col-md-12">
                                    <label for="addrem">Additional Remarks</label>                                   
                                    <textarea id="addrem1" name="adrem" row="2" col="50" class="form-control"> </textarea>

                                    <div class="d-none">
                                      <label class="d-none" for="veri">Verified By</label>                                   
                                    <input id="addver1" value=" <?php echo $_SESSION['id'] ?>" name="addver1" class="form-control d-none" /> </div>
                                  
                                </div>                               
                            </div>

                        </form>
                        <div  id="result1" class="alert alert-success" style="display:none"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-primary" id="update">Save changes</button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    </div>
                    </div>
                </div>
                </div>
            <!-- //modal view end-->


        </div>

</body>
</html>