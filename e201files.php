<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
  if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
?>
<?php
  include_once ('w_conn.php');
  if (isset($_GET['addfiles'])){
    $pth=$_FILES['file']['tmp_name'];
    $nm=$_FILES['file']['name'];
    $td=date('mdyGis');
    $id=$_POST['txtcount'];
    $filename=$_POST['txtfilename'];
    $newpath="assets/pdf/" . $filename . "_" . $id . "_" . $td . ".pdf";
    $pdfname=$filename . "_" . $id . "_" . $td . ".pdf";
    move_uploaded_file($pth, "assets/pdf/" . $pdfname);
    $res=mysqli_query($con,"insert into empe201files (EMPID,EmpfileN,EmpProFPath) values ('$id','$pdfname','$newpath')");  
   
    header("location: e201files.php?emp=" . $id);
  }
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
  
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
  <script type="text/javascript" src="assets/js/script.js"></script>
  <link rel="stylesheet" type="text/css" href="assets/css/style.css">
  <link rel="stylesheet" type="text/css" href="assets/css/responsive.css">
  <title>E 201 Files </title>
  <script type="text/javascript">
    $(document).ready(function(){
       $(document).ready(function(){
            $('[data-toggle="tooltip"]').tooltip(); 
          });
        $( ".sign-out" ).click(function() {
          $('.employee-b-info').slideToggle(500);
        });

        $('#txtemp').on("keyup input", function(){
    
        /* Get input value on change */
        var inputVal = $(this).val();
        var resultDropdown = $("#empdetails");
        if(inputVal.length){
    
            $.get("query/searchemp.php", {term: inputVal}).done(function(data){
                // Display the returned data in browser
                resultDropdown.html(data);

            });
        } else{
            resultDropdown.empty();
        }
        });


        // Set search input value on click of result item
          $(document).on("click", "#empdetails a", function(){
              
                var idname = $(this).attr('id');  

                $("#employeeeid").val(idname);  
           
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function() {
              if (this.readyState == 4 && this.status == 200) {
                document.getElementById("tbd-files").innerHTML = this.responseText;
            }
          };
                xmlhttp.open("GET", "query/searchpdffiles.php?q=" + idname, true);
                xmlhttp.send();
                $(".efiles").css("display", "block");
                $(this).parents(".srch").find('input[type="text"]').val($(this).text());
                $(this).parent("#empdetails").empty();          
          });   

             $("#uploadpdfa").click(function(){
//var files = document.getElementById("file").files[0];

                  var fd = new FormData($("#up").get(0));
       
                  var empID = $('#employeeeid').val();
                  var empname = $('#pdffilename').val();
                  
       
                  $.ajax({
                      url:'query/uploadpdf.php?q=' + empID + "&w=" +  empname,
                      type:'post',
                      data:fd,
                      contentType: false,
                      processData: false,
                      success:function(response){  
                      alert("Succesfully Added!");

                        var idname = $("#employeeeid").val();  
                          $("#employeeeid").val(idname);  
                        $('#mdluploadfile').modal('toggle');
                          var xmlhttp = new XMLHttpRequest();
                          xmlhttp.onreadystatechange = function() {
                        if (this.readyState == 4 && this.status == 200) {
                          document.getElementById("tbd-files").innerHTML = this.responseText;
                      }
                    };
                          xmlhttp.open("GET", "query/searchpdffiles.php?q=" + idname, true);
                          xmlhttp.send();
                           
                      }
                  });
              });
    
  });
 
      
  </script>
  <style type="text/css">
     .btn-name{
          border: 1px solid #ddd;
          margin-bottom: 5px;
          cursor: pointer;
     }
     .btn-name:hover{
        background-color: red;
        color: #fff !important;
     }
     .efiles{
      display: none;
     }
  </style>
</head>
<body style="background-image: none">
   <?php 
    include 'includes/header.php';
  ?>
     <div class="w-container">
        <div class="row">
          <div class="col-lg-3"></div>
         <!-- website content -->
         <div class="col-lg-9 wd-login">

            <h4 class="page-title" style="<?php echo "color: " . $_SESSION['CompanyColor']; ?>">E-201 Files </h4>
             <div class="row">
                <div class="col-lg-6">
                       <div class="srch">
                  <div class="form-group" >
                        <label for="fname">Search Employee Lastname: </label>
                        <?php
                          if (isset($_GET['emp'])){
                              $res=mysqli_query($con,"select * from employees where EmpID='$_GET[emp]'");
                              $row=mysqli_fetch_array($res);
                            ?>
                             <input type="text" class="form-control" name="txtempname" value="<?php echo $row['EmpLN'] . " " . $row['EmpFN']; ?>"  id="txtemp" required="required" placeholder="Search Employee">
                            <?php
                          }else{
                            ?>
                             <input type="text" class="form-control" name="txtempname"  id="txtemp" required="required" placeholder="Search Employee">
                            <?php
                          }

                        ?>
                       
                  </div>
                  <div id="empdetails">
                      
                  </div>
             </div>
                </div>
             </div>
              <?php
                  if (isset($_GET['emp'])){
              ?>
                  <div class="efiless">
               
            <table class="table tblfile">
                  <button class="btn btn-success" data-toggle="modal" data-target="#mdluploadfile">+ Add New</button>
                  <thead>
                    <tr><th>No</th><th>Filename</th><th>Filepath</th></tr>
                  </thead>
                  <tbody id="tbd-files">
                     <?php
                          $result = mysqli_query($con, "select * from empe201files where EMPID='" . $_GET['emp'] . "'");
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
                     
                  </tbody>
                </table>



            <div class="modal" id="mdluploadfile">
                          <div class="modal-dialog">
                            <div class="modal-content">
                         
                                 <div class="modal-header">
                                    <h4 class="modal-title">PDF UPLOAD</h4>
                                   <button type="button" class="close" data-dismiss="modal">&times;</button>
                                  </div>
                              <!-- Modal body -->
                              <div class="modal-body">
                                   <form id="up" method="post" action="e201files.php?addfiles" class="frmuploadpdf" enctype="multipart/form-data">
                                    <input type="hidden" name="empid">
                                          <h6 class="wrn" style="display: none; color: red;">Warning</h6>
                                          <div class="form-group" style="display: block;">
                                           <input type="hidden" value="<?php echo $_GET['emp']; ?>" name="txtcount" id="employeeeid">
                                            <label for="fname">File Name: </label>
                                            <input type="text" class="form-control" name="txtfilename"  id="pdffilename" required="required" placeholder="PDF Name">
                                        </div>

                                        <input type="file" name="file" id="file" accept="application/pdf">
                                        <br>
                                         <button class="btn btn-success btn-block" type="submit" id="uploadpdf">Upload</button>

                                </form>

                              </div>
                            
        
                          </div>
                        </div>
                      </div>

             </div>
              <?php
                  }
              ?>



             <div class="efiles">
               
            <table class="table tblfile">
                  <button class="btn btn-success" data-toggle="modal" data-target="#mdluploadfile">+ Add New</button>
                  <thead>
                    <tr><th>No</th><th>Filename</th><th>Filepath</th></tr>
                  </thead>
                  <tbody id="tbd-files">
                     
                     
                  </tbody>
                </table>



            <div class="modal" id="mdluploadfile">
                          <div class="modal-dialog">
                            <div class="modal-content">
                         
                                 <div class="modal-header">
                                    <h4 class="modal-title">PDF UPLOAD</h4>
                                   <button type="button" class="close" data-dismiss="modal">&times;</button>
                                  </div>
                              <!-- Modal body -->
                              <div class="modal-body">
                                   <form id="up" method="post" action="e201files.php?addfiles" class="frmuploadpdf" enctype="multipart/form-data">
                                    <input type="hidden" name="empid">
                                          <h6 class="wrn" style="display: none; color: red;">Warning</h6>
                                          <div class="form-group" style="display: block;">
                                           <input type="hidden" name="txtcount" id="employeeeid">
                                            <label for="fname">File Name: </label>
                                            <input type="text" class="form-control" name="txtfilename"  id="pdffilename" required="required" placeholder="PDF Name">
                                        </div>

                                        <input type="file" name="file" id="file" accept="application/pdf">
                                        <br>
                                         <button class="btn btn-success btn-block" type="submit" id="uploadpdf">Upload</button>

                                </form>

                              </div>
                            
        
                          </div>
                        </div>
                      </div>

             </div>
         </div>
       </div>
     </div>
        <!-- The Modal
                   -->      <div class="modal" id="myModal">
                          <div class="modal-dialog">
                            <div class="modal-content">
                            
                            
                               <div class="modal-header">
                                    <h4 class="modal-title">Warning</h4>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                  </div>
                              <!-- Modal body -->
                              <div class="modal-body">
                                Under Construction......
                              </div>
                              
                            <!-- Modal footer -->
                            <div class="modal-footer">
                              <button type="button" class="btn btn-danger" data-dismiss="modal">Close</button>
                            </div>
                            
                          </div>
                        </div>
                      </div>
   </body>
   </html>
