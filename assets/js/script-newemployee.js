  function myrelfunction(c){
             var element = c;
             var element2 = document.getElementById("btnice");

             element2.classList.remove("btn-success");
             element2.classList.add("btn-danger");
             document.querySelectorAll('button').forEach(function(node){
                  node.value = 'Next'
              });

             element.classList.remove("btn-danger");
             element.classList.add("btn-success");

          }  
          function myremovetr(v){
             v.parentNode.parentNode.parentNode.removeChild(v.parentNode.parentNode);
          }
            
          
            
          $(document).ready(function(){
                
                // input change
                $("input").keyup(function(){
                  $(this).css("text-transform", "capitalize");
                });
              //add new department
              $("#depselect").click(function(){
                 if ($("#empcompid option:selected").val()==""){
                      $('#modalWarning').modal('toggle');
                      $('#modalWarning .alert').html("Please Select Company First"); 
                      return false;
                    }else{
                        
                      var toapp = "<option value='" + $("#empcompid option:selected").val() +"'>" + $("#empcompid option:selected").text() + "</option>";
                       
                       $("#compid").empty();
                       $("#compid").append(toapp);
                    }
              });
              $(".btndepartment").click(function(){
                   
                    if ($("#depname").val()==""){
                        $('#modalWarning').modal('toggle');
                        $('#modalWarning .alert').html("Please Fill up required Fields!"); 
                    }
                    else{
                      var cmid = $("#compid option:selected").val();

                       var data=$(".frmdep").serialize();
                         $.ajax({
                          url:'query/query-maintenance.php?newempDepartment', 
                          type:'post',
                          data:data,

                           success:function(data){
                              if (data==1){
                                  $('#modalWarning').modal('toggle');
                                  $('#modalWarning .alert').html("Department is Already in the Database!"); 
                              }else{
                                  $(".DepEmp").empty();
                                  $(".DepEmp").append(data);
                                  $(".DepEmp " + ".dep" + cmid).show();
                                  $('#modaladddep').modal('toggle');
                              }
                            }
                         });
                    }
              });

              //if no depratment
              $(".Pos_Sel").click(function(){
                  if ($(".DepEmp option:selected").val()==""){
                      $('#modalWarning').modal('toggle');
                      $('#modalWarning .alert').html("Please Select Department First"); 
                    
                      return false;
                  }
                  else{
                    var dptoapp = "<option value='" + $(".DepEmp option:selected").val() +"'>" + $(".DepEmp option:selected").text() + "</option>";
                    var cptoapp = "<option value='" + $("#empcompid option:selected").val() +"'>" + $("#empcompid option:selected").text() + "</option>";

                   $("#dep").empty();
                   $("#dep").append(dptoapp);
                   $("#comchange").empty();
                   $("#comchange").append(cptoapp);
                  }
                 
              });
              //add new position
              $(".btnposition").click(function(){
                 //check is have position
                 if ($("#comchange").val() == "" || $("#dep").val() == "" || $("#pos").val() == "" || $("#joblevel").val() == ""){
                    $('#modalWarning').modal('toggle');
                    $('#modalWarning .alert').html("Please Fill up required Fields!"); 
                    return false;
                }
                else{
                     var data=$(".frmpos").serialize();

                        $.ajax({
                          url:'query/query-maintenance.php?newempPosition', 
                          type:'post',
                          data:data,

                           success:function(data){
                                if (data==1){
                                  $('#modalWarning').modal('toggle');
                                  $('#modalWarning .alert').html("Position is already in the Record!"); 
                                  
                                }              
                                else{
                                  $("#idempposition").empty();
                                  $("#idempposition").append(data);
                                  $("#modaladdpos").modal("toggle");
                                }
                            }
                         });
                }


              });

               //add new department
              $(".btndepartment").click(function(){
                 
              });
              
               $('#empcompid').change(function(){
                    var cid = $(this).val();
                   
                    $.ajax({

                                url:'query/query-maintenance.php?empidchanged', 
                                data: {compid : cid},
                                type:'POST',                                  
                                 success:function(data){
                                   $("#empID").val(data);
                                   if (window.neValidateAll) { window.neValidateAll(false); }
                                 }
                      });
               });
              // Work schedule is handled in the dedicated Scheduler module, so the
              // per-day inherit handlers were removed along with that form section.

              $("body").click(function(){
                // var x= event.target.className;
                // alert(event.target);
                // if (x=="col-lg-12 side-drp-menu"){
                //   else{
                //   $(".employee-b-info").css("display", "none");
                // }
              });
             $("#submit-form").click(function(){
   
                      var data=$("#fdata").serialize();

                              $.ajax({

                                url:'query/Query-idchecker.php', 
                                data:data,
                                type:'POST',                                  
                                 success:function(data){
                                     if(data==1)
                                     {
                                       $('#modalWarning').modal('toggle');
                                      $('#modalWarning .alert').html("Employee ID already Exist !"); 
                                    }
                                   else
                                     {
                        /* count empty required fields across every tab + paint
                           the badges/red borders (validation lives in newemployee.php) */
                        var neEmpty = window.neValidateAll ? window.neValidateAll(true) : 0;
                        if (neEmpty > 0) {
                            var t = window.neFirstIncompleteTab && window.neFirstIncompleteTab();
                            if (t) { $('#' + t).trigger('click'); }
                        }

                        if (neEmpty == 0)
                        {
                           

                                var fd = new FormData(); 
                                var files = $('#file')[0].files[0];
                                var empID = $('#empID').val();
                                fd.append('file',files);
                                $.ajax({
                                    url:'uploadpicture.php?q=' + empID,
                                    type:'post',
                                    data:fd,
                                    contentType: false,
                                    processData: false,
                                    success:function(response){  
                                   
                                    }
                                });
                               
                            

                              var lastRowId = $('.tbl-relationship tr:last').attr("id"); /*finds id of the last row inside table*/
                              var name = new Array(); 
                              var a = new Array();
                              var rel = new Array(); 
                              var conno= new Array();
                              var ice= new Array();


                              for ( var i = 1; i <= lastRowId; i++) {
                              name.push($("#"+i+" .name"+i).html()); /*pushing all the names listed in the table*/
                              a.push($("#"+i+" .add"+i).html()); /*pushing all the emails listed in the table*/
                              rel.push($("#"+i+" .rel"+i).html());
                              conno.push($("#"+i+" .con"+i).html());
                              ice.push($("#"+i+" .ice"+i).html());

                              }
                              var sendName = JSON.stringify(name); 
                              var sendAdd = JSON.stringify(a);
                              var sendRel = JSON.stringify(rel); 
                              var sendCon = JSON.stringify(conno);
                              var sendIce = JSON.stringify(ice); 

                              var empID = $('#empID').val(); 

                              $.ajax({
                              url: "insert-fdetails.php",
                              type: "post",
                              data: {name : sendName , a : sendAdd , rel : sendRel , conno : sendCon , ice : sendIce , empID : empID},
                              success : function(data){
                               /* alerts the response from php.*/
                         
                              }
                              });
                                   var data=$("#fdata").serialize();
                                $.ajax({

                                  url:'query/Query-InsertEmployeeInfo.php', 
                                  data:data,
                                  type:'POST',
                                                                       
                                   success:function(data){
                                    // alert(data);
                                     
                                   }
                                });
                                  swal("Message!", "Successfully Saved !");
                                  $("#fdata").trigger("reset");
                                //   document.getElementById("btn-ci").innerHTML = 'General Information (6)';
                                //   document.getElementById("btn-es").innerHTML = 'Employment Information (8)';
                                //   $('#modalWarning2').modal('toggle');
                                //   $('#modalWarning2 .alert').html("Successfully Saved !"); 
                          
                                 
                        }
                        else{
                        swal("Incomplete", "Please fill up the " + neEmpty + " required field(s) highlighted in red.");
                            // $('#modalWarning').modal('toggle');
                            // $('#modalWarning .alert').html("Please Fill up required Fields!"); 
                        }
                      }
                    }
                
               
                });
                });
            $(document).on("click", ".ctzn-a", function(){
            $("#empcitizen").val($(this).text());
            $(".cl-citizen").hide();
          });
          $("#empcitizen").keyup(function(){
    
            if ($(this).val()==""){
                $(".cl-citizen").hide();
            }else{
                 $(".cl-citizen").show();
              var data = $(this).val();
              $.ajax({

                          url:'query/query-maintenance.php?citizenship', 
                          data: { data : data },
                          type:'POST',                                             
                          success:function(data){
                       
                             $(".cl-citizen").empty();
                             $(".cl-citizen").append(data);
                          }
                      });
            }
          });

          $(document).on("click", ".rel-a", function(){

            $("#empreligion").val($(this).text());
            $(".cl-reli").hide();
        
          });
          $("#empreligion").keyup(function(){
    
            if ($(this).val()==""){
                $(".cl-reli").hide();
            }else{
                 $(".cl-reli").show();
              var data = $(this).val();
              $.ajax({

                          url:'query/query-maintenance.php?religion', 
                          data: { data : data },
                          type:'POST',                                             
                          success:function(data){
                       
                             $(".cl-reli").empty();
                             $(".cl-reli").append(data);
                          }
                      });
            }
          });
              
              
    });