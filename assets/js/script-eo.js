
   
 
   $(document).ready(function(){
      $("#eosave").click(function(){ 
        var d = new Date();
        var day = d.getDate();
        var now = new Date();
         $('#myModalnew').modal('toggle');
        var data=$("#eodata").serialize();
                
                      $.ajax({
                           url:'query/Query-inserteo.php', 
                           type:'post',
                           data:data,

                            success:function(res){
                            
                              $.trim(res);
                              if (res==1)
                              { 
                                 $('#modalWarning').modal('toggle');
                                $('#modalWarning .alert').html("Exceed on IsBeforeDay Filing."); 
                             
                              }
                             else if (res==101)
                              { 
                                $('#modalWarning').modal('toggle');
                                $('#modalWarning .alert').html("Exceed on IsAfterDay Filing."); 
                             
                              }
                                else if (res==102)
                              { 
                                $('#modalWarning').modal('toggle');
                                $('#modalWarning .alert').html("You already filed this EO."); 
                             
                              }
                               else if (res==103)
                              { 
                                $('#modalWarning').modal('toggle');
                                $('#modalWarning .alert').html("You have no login yet."); 
                             
                              }
                               else if (res==104)
                              { 
                                $('#modalWarning').modal('toggle');
                                $('#modalWarning .alert').html("Cannot proceed with Early Out application, you have not reached the minimum time requirement!"); 
                             
                              }
                              else if (res==2)
                              {
                                $('#modalWarning').modal('toggle');
                                $('#modalWarning .alert').html("Earlyout cannot be completed as requested. System detected that you are tardy for today " + now + "!" ); 
                               
                              }
                              else if (res==3)
                              {
                                    $('#modalWarning').modal('toggle');
                                $('#modalWarning .alert').html("EO Late filing not allowed!."); 
                             
                              }
                              else if (res==4)
                              {
                                $('#modalWarning').modal('toggle');
                                $('#modalWarning .alert').html("EO Advance filing not allowed!."); 
                                
                              }
                              else if (res==6)
                              {
                                $('#modalWarning').modal('toggle');
                                $('#modalWarning .alert').html("Filling Denied for NO LOGIN"); 
                                
                              }
                              else if(res=="7" || res==7)
                              {
                                $('#modalWarning').modal('toggle');
                                $('#modalWarning .alert').html("Error Saving. Please specify purpose."); 
                                
                              }     
                              else
                              {
                                var xmlhttp = new XMLHttpRequest();
                                $("#tbeodata").empty();
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                document.getElementById("tbeodata").innerHTML = this.responseText;
                                  }
                                  };
                                xmlhttp.open("GET", "query/Query-vieweo.php", true);
                                xmlhttp.send();

                                //logout
                                // var xmlhttp = new XMLHttpRequest();
                                // xmlhttp.open("GET", "query/Query-insertlilo.php", true);
                                // xmlhttp.send();
                                $("#newform").modal('hide');
                                $('.modal-backdrop').remove();
                                $('#modalWarning').modal('toggle');
                                $('#modalWarning .alert').html("Successfully Applied for EO!"); 
                         

                            }
                          }
                          
                        });

   


  });
                        //            $.ajax({
                        //    url:'query/Query-GetAutoLogout', 
                        //    type:'get',
                        //    data:data,

                        //     success:function(data){
                        //       if( data == 1)
                        //         {
                        //             alert("Auto Logout Enable");
                        //         }
                        //   }
                          
                        // });  
                        
    $(document).on("click", ".ys_eo", function(){
       var data = $(this).attr("id");
        $.ajax({
            url:'query/deleteeo.php?eo', 
            type:'post',
            data:{data: data},
            success:function(res){
                    $('#modalWarning').modal('toggle');
                    $('#modalWarning .alert').html("Successfully Deleted!"); 
                    $(".eo-delete").modal('hide');
                    $('.modal-backdrop').remove();
                    var xmlhttp = new XMLHttpRequest();
                    $("#tbeodata").empty();
                    xmlhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                    document.getElementById("tbeodata").innerHTML = this.responseText;
                    }
                    };
                    xmlhttp.open("GET", "query/Query-vieweo.php", true);
                    xmlhttp.send();
            }
        });
    });                    
 });
