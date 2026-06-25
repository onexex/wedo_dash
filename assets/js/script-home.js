 $(document).ready(function(){
       function check_session()
       {
          $.ajax({
            url:"query/sessionex_.php",
            method:"POST",
            success:function(data)
            {
              if(data == '2' || data == 2)
              {
                alert('Your session has expired! You Need to log in again.');  
               window.location.href="login.php";
              }
            }
          })
       }

      $('#newformd').on('shown.bs.modal', function () {
            $('#daract').focus();
        }) ; 
      $("#v_session").click(function(){
            check_session();  
      });
        $("#v_logins").click(function(){
           check_session();  
      });
      $("#obsave").click(function(){   
            var data=$("#gdata").serialize();
            var outputstr = $("#daract").val().replace(/'/g, '');
         var dartext = $( "#daract" ).val(); 
            var trimStr = $.trim(outputstr);
            
         if(trimStr==='')
         {
              $('#modalWarning').modal('toggle');
              $('#modalWarning .alert').html("Invalid Input"); 
         }
           else
           {
                   document.getElementById("obsave").disabled = true;
                    $("#obsave").text("Data Saving....");
                    
                          
                        
                        $.ajax({
                          url:'query/Query-InsertDar.php', 
                          type:'post',
                          data: {dreact : outputstr},

                           success:function(data){
   
                               var xmlhttp = new XMLHttpRequest();
                                var idname1 = "63"; 
                                $("#adddar").text("Loading Data .....");
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                document.getElementById("adddar").innerHTML = this.responseText;
                                  }
                                  };
                                xmlhttp.open("GET", "query/query-searchdar.php?q=" + idname1, true);
                                xmlhttp.send();
                                $("#obsave").text("UPDATE DAR");
                                 document.getElementById("obsave").disabled = false;
                                 $('#daract').val("");
                                 $('#newformd').modal('toggle');
                                 $('#modalWarning').modal('toggle');
                                  $('#modalWarning .alert').html("Succesfully Added to DAR! "); 
                                   

                           }
                        });}
                                });
                                
       
                    var data=$("#lilo").serialize();
                    data="data";
                    var loggs = $(".lilosave").attr("id");
                        $.ajax({
                          url:'query/Query-checkinout.php', 
                          type:'post',
                          data:data,

                           success:function(data){
                          
                            if (data==1){

                                $(".lilosave").attr("id", "1");
                                $(".lilosave").text("Login to Cenar");
                            }
                            else{
                              
                                 $(".lilosave").attr("id", "2");
                                $(".lilosave").text("Logout from Cenar");
                            }
                          
                           }
                        });
});

    $(document).ready(function(){
         $("#lgyesf").click(function(){
         var str=$(this).attr("id");
         $.ajax({
                          url:'query/Query-CenarLogout.php', 
                          type:'post',
                          data:{data :str },

                           success:function(rss){
                                $('#LoginEOUnder').modal('toggle');
                                $('#modalWarning').modal('toggle');
                                $('#modalWarning .alert').html("You have Succesfully Logout!");
                                var xmlhttp = new XMLHttpRequest();
                                $("#addlilo").text("Loading Data .....");
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                document.getElementById("addlilo").innerHTML = this.responseText;
                                  }
                                  };
                                xmlhttp.open("GET", "query/Query-searchlilo.php", true);
                                xmlhttp.send();
                           }
                });
      });    
        
      $(".btn-login").click(function(){
 
            var str=$(".lilosave").attr("id");
            var txt;
            var data1=$("#lilo").serialize();
            $(".lg-buttons").hide();
            $(".loadd").show();
             
         $.ajax({
                          url:'query/Query-insertlilo.php', 
                          type:'post',
                          data:data1,

                           success:function(rss){

                            // Login absence gate: payload contains "108|YYYY-MM-DD,..."
                            // (a stray "2" may precede it, so match the marker anywhere)
                            var gateMark = String(rss).indexOf("108|");
                            if (gateMark !== -1) {
                              var gateDates = String(rss).substring(gateMark + 4);
                              $('#modalWarning').modal('toggle');
                              $('#modalWarning .alert').html("Clock-in blocked: unaccounted absence on <b>" + gateDates + "</b>.<br><br>Please have your immediate superior file a Leave or OB for that date before you can log in to Cenar.");
                              $('#LoginWarning').modal('toggle');
                              return false;
                            }

                            if(rss==105){
                              $('#modalWarning').modal('toggle');
                              $('#modalWarning .alert').html(" You are attempting to log-in beyond your call time. <br> You can no longer log-in via the Dashboard.  <br>  <br> Please obtain clearance from your department head and the OGM.  <br>  <br>Thank you.");
                              $('#LoginWarning').modal('toggle');
                              return false;
                            }else  if(rss==100)
                              {
                                    $('#modalWarning').modal('toggle');
                                   $('#modalWarning .alert').html("Oops, we couldn't find your Schedule for Today!");
                                   $('#LoginWarning').modal('toggle');
                           
                              }
                              else if (rss == 1000) {
                                      $("#modalWarning").modal("toggle");
                                      $("#modalWarning .alert").html(
                                        "Login denied. You have an approved whole-day leave today.",
                                      );
                                      $("#LoginWarning").modal("toggle");
                                    } else if (rss == 1001) {
                                      $("#modalWarning").modal("toggle");
                                      $("#modalWarning .alert").html(
                                        "Login denied. You are on leave this morning. Please log in after 1:00 PM.",
                                      );
                                      $("#LoginWarning").modal("toggle");
                                    } else if (rss == 1002) {
                                      $("#modalWarning").modal("toggle");
                                      $("#modalWarning .alert").html(
                                        "Login denied. You are on leave this afternoon.",
                                      );
                                      $("#LoginWarning").modal("toggle");
                                    }
                              else if(rss==101){
                                $('#LoginWarning').modal('toggle');
                                $('#LoginEOUnder .lg-question h3').text("Your EO application today is not yet approve. Are you sure you want to Continue?");
                                $('#LoginEOUnder .lg-question h4').html("<i class='fa fa-exclamation-triangle' style='color:#e3c80b; margin-right:5px;' aria-hidden='true'></i>By continuing,  you agree to let the system tag your LILO today, as not paid.");
                                   // $('#modalWarning .alert').html("Oops, we couldn't find your Schedule for Today!"); And your EO will be Disapproved by Dashboard.
                                $('#LoginEOUnder').modal('toggle');
                              }
                               else if(rss==102){
                                 $('#LoginWarning').modal('toggle');
                                    $('#LoginEOUnder .lg-question h3').text("You have a canceled or disapproved Early Out Application and Your Attendance today is not yet complete. Are You sure you want to Logout ?");
                                      $('#LoginEOUnder .lg-question h4').html("<i class='fa fa-exclamation-triangle' style='color:#e3c80b; margin-right:5px;' aria-hidden='true'></i>By continuing,  you agree to let the system tag your LILO today, as not paid.");
                                 // $('#modalWarning .alert').html("Oops, we couldn't find your Schedule for Today!");
                                   $('#LoginEOUnder').modal('toggle');
                              }
                               else if(rss==103){
                                 $('#LoginWarning').modal('toggle');
                                    $('#LoginEOUnder .lg-question h3').text("Your Attendance today is not yet complete. Are you sure you want to Continue?");
                                    $('#LoginEOUnder .lg-question h4').html("<i class='fa fa-exclamation-triangle' style='color:#e3c80b; margin-right:5px;' aria-hidden='true'></i>By continuing,  you agree to let the system tag your LILO today, as not paid.");
                                    // $('#modalWarning .alert').html("Oops, we couldn't find your Schedule for Today!");
                                    $('#LoginEOUnder').modal('toggle');
                              }
                            else
                            {
                               
                                var xmlhttp = new XMLHttpRequest();
                                $("#addlilo").text("Loading Data .....");
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                document.getElementById("addlilo").innerHTML = this.responseText;
                                  }
                                  };
                                xmlhttp.open("GET", "query/Query-searchlilo.php", true);
                                xmlhttp.send();
                                var dt =$(".lilosave").attr("id");
                               if(dt==1)
                                { 
                                    // $(".lilosave").attr("id", "2");
                                    // $('#LoginWarning').modal('toggle');
                                    // $('#modalWarning').modal('toggle');
                                    // $('#modalWarning .alert').html("You have Succesfully Login!");
                                    // $(".lilosave").text("Logout from Cenar");
                                    
                                     $('#LoginWarning').modal('toggle');
                                      $('#modalWarning').modal('toggle');
                                      $('#modalWarning .alert').html("You have Succesfully Login!");
                                      // $('#LoginWarning').modal('toggle');
                                    $(".lilosave").attr("id", "2");
                                    $(".lilosave").text("Logout from Cenar");
                                }

                                else{
                                    $('#LoginWarning').modal('toggle');
                                    $('#modalWarning').modal('toggle');
                                    $('#modalWarning .alert').html("You have Succesfully Logout!");
                                    $(".lilosave").attr("id", "1");
                                    $(".lilosave").text("Login to Cenar");
                                  
                               }
                                 var xmlhttp = new XMLHttpRequest();
                                var idname1 = "63"; 
                                $("#adddar").text("Loading Data .....");
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                document.getElementById("adddar").innerHTML = this.responseText;
                                  }
                                  };
                                xmlhttp.open("GET", "query/query-searchdar.php?q=" + idname1, true);
                                xmlhttp.send();
                                 $('#daract').val("");
                                    $('#modalWarning').modal('toggle');
                                   $('#modalWarning .alert').html("Succesfully Added to DAR ! "); 
                               

                                  
                           }
                            $(".lg-buttons").show();
                            $(".loadd").hide();
                         }
                           });
      });

      $(".lilosave").click(function(){ 
    
        
        if ($(this).attr("id")==2){
                  $(".lg-question h5").html("You are about to logout.Continue?");
                  
        }
        else{
             $(".lg-question h5").html("You are about to login.Continue?");
        }
      });

       $(".btnref2").click(function(){
           if ($("#dpfrom").val() == "" || $("#dpto").val() == ""){
          alert("Please Select date paramaeter ! ");
        }
        else{
            

            var xmlhttp = new XMLHttpRequest();
            $("#addlilo").text("Loading Data....");
            $(".loadingarea h2").text("Loading Attendance");
            $("#LoadingIndexViewer").toggle();
            xmlhttp.onreadystatechange = function() {
              if (this.readyState == 4 && this.status == 200) {
                document.getElementById("addlilo").innerHTML = this.responseText;
                $("#LoadingIndexViewer").toggle();
              }
            };
              xmlhttp.open("GET", "query/Query-SDarLilo.php?lilo&dtf=" + $("#dpfrom").val() +  "&dtt=" + $("#dpto").val(), true);
              xmlhttp.send();  
        }
       });

      $(".btnref").click(function(){
       
        if ($("#dpfrom").val() == "" || $("#dpto").val() == ""){
          alert("Please Select date paramaeter ! ");
        }
        else{
              var xmlhttp = new XMLHttpRequest();
              $("#adddar").text("Loading Data....");
              $(".loadingarea h2").text("Loading DAR");
              $("#LoadingIndexViewer").toggle();
              xmlhttp.onreadystatechange = function() {
              if (this.readyState == 4 && this.status == 200) {
                document.getElementById("adddar").innerHTML = this.responseText;
                  $("#LoadingIndexViewer").toggle();
              }
              };
              xmlhttp.open("GET", "query/Query-SDarLilo.php?dar&dtf=" + $("#dpfrom").val() +  "&dtt=" + $("#dpto").val(), true);
              xmlhttp.send();  
        }

      });

  });
