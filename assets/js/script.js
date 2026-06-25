function startTime() {
  var today = new Date();
  var h = today.getHours();
  var m = today.getMinutes();
  var s = today.getSeconds();
  var am_pm = today.getHours() >= 12 ? "PM" : "AM";
  h = h % 12;
  h = h ? h : 12;
  m = checkTime(m);
  s = checkTime(s);
  document.getElementById('sec').innerHTML =
  ":" + s + " " + am_pm;
   document.getElementById('sec2').innerHTML =
  ":" + s + " " + am_pm;
  document.getElementById('hr-mn').innerHTML =
  h + ":" + m;
  document.getElementById('hr-mn2').innerHTML =
  h + ":" + m;
  var t = setTimeout(startTime, 500);
  var d = new Date();
  var months = ["January","February","March","April","May","June","July","August","September","October","November","December"];
  document.getElementById("dtnow").innerHTML = months[d.getMonth()] + " " + d.getDate() + ", " + d.getFullYear();
}
function checkTime(i) {
  if (i < 10) {i = "0" + i};  // add zero in front of numbers < 10
  return i;
}
$(document).ready(function(){
  startTime();
});
$(document).ready(function(){
    
    
  function go2() { $('.flash').delay(100).fadeOut().fadeIn('slow') }

  setInterval(function () {
    go2();
    },500);
  // startTime();
  $(".collapsed").click(function(){
    if ($(this).children().hasClass("glyphicon-align-justify")){
       $(this).children().addClass("glyphicon-remove");
      $(this).children().removeClass("glyphicon-align-justify");
      
      $(".w-sidebarmenu").toggle("slow");
    }else{
      $(this).children().addClass("glyphicon-align-justify");
      $(this).children().removeClass("glyphicon-remove");
       $(".w-sidebarmenu").toggle("slow");
    }
  });
});
 function btnnavclick(x) {
          x.classList.toggle("change");
        }
$(document).ready(function(){
        $(".btnminclock").click(function(){ 
            $(".thisclockhide").slideToggle( "slow" );
               $(".btnmaxclock").show();
            $(".btnminclock").hide(1000);
             $(".clckmin").show();
        });
         $(".btnmaxclock").click(function(){ 
             $(".thisclockhide").slideToggle( "slow" );
               $(".btnmaxclock").hide();
                $(".btnminclock").show(1000);
                 $(".clckmin").hide();
        });
        // notification
          function loadlink(){
              //notifications
              var idname = $(this).attr("id");
              var xmlhttp = new XMLHttpRequest();
              xmlhttp.onreadystatechange = function() {
                  if (this.readyState == 4 && this.status == 200) {
                    document.getElementById("faid-bell").innerHTML = this.responseText;
                }
              };
                    xmlhttp.open("GET", "query/Query-Notifications.php", true);
                    xmlhttp.send();
        }

        // loadlink(); // This will run on page load
        // setInterval(function(){
        //        loadlink() // this will run after every 5 seconds
        // }, 3000);
        // end

        $("#vlilo").click(function(){ 
  
                               var xmlhttp = new XMLHttpRequest();                           
                                $("#tab").empty();
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                document.getElementById("tab").innerHTML = this.responseText;
                                  }
                                  };
                                xmlhttp.open("GET", "Query-LiloView", true);
                                xmlhttp.send();

         });
    // new employee
    //jquery display departments in position
      $( "#empcompid" ).change(function() {
        var sval = $( "#empcompid" ).val();
        $("#idempposition option").hide();
        $("#empdepartment option").hide();
        $("#idempposition").val("");
        $("#empdepartment").val("");
        $("#empdepartment " + ".dep" + sval).show();

      });

      
      $( "#empdepartment" ).change(function() {
        var sval = $( "#empdepartment" ).val();
           $("#empis option").hide();
        $("#idempposition option").hide();
        $("#idempposition").val("");
        $("#idempposition " + ".pos" + sval).show();
        $("#empis " + ".is" + sval).show();
      });
       $( "#empdepartment1" ).change(function() {
        var sval = $( "#empdepartment1" ).val();
           $("#empis option").hide();
        $("#idempposition option").hide();
        $("#idempposition").val("");
        $("#idempposition " + ".pos" + sval).show();
        $("#empis " + ".is" + sval).show();
      });
    //new employee
    $( "#sndmessage" ).click(function() {
      $(".com-container").slideToggle();

    });
     $( "#sndm" ).click(function() {
      $(".com-container").slideToggle();

    });
 		$( ".btn-click button" ).click(function() {
          var vl = $(this).attr('id');
         
          if (vl=="btn-ci"){
          	$(".wd-login form>div").css("display", "none");
          	$(".wd-login form>.frm-ci").css("display", "flex");

          	$(".frm-title").text("General Information");
            $(this).addClass("btn-active");
            $("#btn-es").removeClass("btn-active");
            $("#btn-cdd").removeClass("btn-active");
            $("#btn-ed").removeClass("btn-active");
            $("#btn-prf").removeClass("btn-active");
            $("#btn-ws").removeClass("btn-active");
            $("#btn-fd").removeClass("btn-active");
            $("#btn-eb").removeClass("btn-active");
            $("#btn-eb").removeClass("btn-active");
          }
            if (vl=="btn-eb"){
              $(".wd-login form>div").css("display", "none");
              $(".wd-login form>.frm-eb").css("display", "flex");
              $(".frm-title").text("Educational Background");
              $(this).addClass("btn-active");
              $("#btn-ci").removeClass("btn-active");
              $("#btn-cdd").removeClass("btn-active");
              $("#btn-ed").removeClass("btn-active");
              $("#btn-prf").removeClass("btn-active");
              $("#btn-fd").removeClass("btn-active");
              $("#btn-ws").removeClass("btn-active");
              $("#btn-es").removeClass("btn-active");
          }
          if (vl=="btn-es"){
          	 	$(".wd-login form>div").css("display", "none");
          	 	$(".wd-login form>.frm-es").css("display", "flex");
          	 	$(".frm-title").text("Employment Information");
              $(this).addClass("btn-active");
              $("#btn-ci").removeClass("btn-active");
              $("#btn-cdd").removeClass("btn-active");
              $("#btn-ed").removeClass("btn-active");
              $("#btn-prf").removeClass("btn-active");
              $("#btn-fd").removeClass("btn-active");
              $("#btn-ws").removeClass("btn-active");
              $("#btn-eb").removeClass("btn-active");
          }
          if (vl=="btn-cdd"){
          	 	$(".wd-login form>div").css("display", "none");
          	 	$(".wd-login form>.frm-cdd").css("display", "flex");
          	 	$(".frm-title").text("Compliance Document Data");
              $(this).addClass("btn-active");
              $("#btn-ci").removeClass("btn-active");
              $("#btn-es").removeClass("btn-active");
              $("#btn-ed").removeClass("btn-active");
              $("#btn-prf").removeClass("btn-active");
              $("#btn-fd").removeClass("btn-active");
              $("#btn-ws").removeClass("btn-active");
              $("#btn-eb").removeClass("btn-active");
          }
          if (vl=="btn-ed"){
          	 	$(".wd-login form>div").css("display", "none");
          	 	$(".wd-login form>.frm-ed").css("display", "flex");
          	 	$(".frm-title").text("Employment Details");
               $(this).addClass("btn-active");
              $("#btn-ci").removeClass("btn-active");
              $("#btn-es").removeClass("btn-active");
              $("#btn-cdd").removeClass("btn-active");
              $("#btn-prf").removeClass("btn-active");
              $("#btn-fd").removeClass("btn-active");
              $("#btn-ws").removeClass("btn-active");
              $("#btn-eb").removeClass("btn-active");
          }
            if (vl=="btn-e201f"){
            	 $(".wd-login form>div").css("display", "none");
            	 $(".wd-login form>.frm-e201f").css("display", "flex");
          	 	$(".frm-title").text("Electronic 201 Files");

          }
           if (vl=="btn-prf"){
            	 $(".wd-login form>div").css("display", "none");
            	 $(".wd-login form>.frm-prf").css("display", "flex");
          	 	$(".frm-title").text("Upload Profile Picture");
               $(this).addClass("btn-active");
              $("#btn-ci").removeClass("btn-active");
              $("#btn-es").removeClass("btn-active");
              $("#btn-ed").removeClass("btn-active");
              $("#btn-cdd").removeClass("btn-active");
              $("#btn-fd").removeClass("btn-active");
              $("#btn-ws").removeClass("btn-active");
              $("#btn-eb").removeClass("btn-active");
          }
           if (vl=="btn-fd"){
               $(".wd-login form>div").css("display", "none");
               $(".wd-login form>.frm-fd").css("display", "flex");
              $(".frm-title").text("Family Details");
               $(this).addClass("btn-active");
              $("#btn-ci").removeClass("btn-active");
              $("#btn-es").removeClass("btn-active");
              $("#btn-ed").removeClass("btn-active");
              $("#btn-prf").removeClass("btn-active");
              $("#btn-cdd").removeClass("btn-active");
              $("#btn-ws").removeClass("btn-active");
              $("#btn-eb").removeClass("btn-active");
          }
           if (vl=="btn-ws"){
               $(".wd-login form>div").css("display", "none");
               $(".wd-login form>.frm-ws").css("display", "flex");
              $(".frm-title").text("Work Schedules");
               $(this).addClass("btn-active");
              $("#btn-ci").removeClass("btn-active");
              $("#btn-es").removeClass("btn-active");
              $("#btn-fd").removeClass("btn-active");
              $("#btn-ed").removeClass("btn-active");
              $("#btn-prf").removeClass("btn-active");
              $("#btn-cdd").removeClass("btn-active");
              $("#btn-eb").removeClass("btn-active");
          }
        });


    function readURL2(input) {
		  if (input.files && input.files[0]) {
     
		    var reader = new FileReader();
		   
		    reader.onload = function(e) {
         
		      $('.prof-pic').css('background-image', "url('" + e.target.result + "')");
		    }
		    
		    reader.readAsDataURL(input.files[0]);
		  }
		}

		$("#file").change(function() {
		  readURL2(this);
		});

      function readURL(input) {
      if (input.files && input.files[0]) {
          $(".comcolorlogo").css("display","block");
        var reader = new FileReader();
       
        reader.onload = function(e) {
         
          $('.comlogopic').css('background-image', "url('" + e.target.result + "')");
        }
        
        reader.readAsDataURL(input.files[0]);
      }
    }

    $("#comlogopath").change(function() {
      readURL(this);
    });

    $('.btn-rel').click(function(){
       
var id =$("#rnum").val();
    id++;

       var l = $('.relchk').prop('checked');
       var ic = $('.relice').prop('checked');
        if (l==true){
          var rel = $('#relinput').val();
        }
        else{
          var rel = $('#rel').children("option:selected").text();
        }
        if (ic==true){
          var iceyn = "Yes";
        }else{
            var iceyn = "No";
        }
        var name = $("#famname").val();
        var add = $("#famadd").val();
        var cn = $("#famnumber").val();
        if (rel == "" || name =="" || add=="" || cn==""){
             $('#modalWarning').modal('toggle');
            $('#modalWarning .alert').html("Please Fill up Empty Fields!"); 
        }else{
          
            var newid = id++;
          $("#rnum").val(newid);
          $('.tbl-relationship tbody').append('<tr id="'+newid+'"> \n\
            <td class="name'+newid+'" >' + name +'</td> \n\
            <td class="add'+newid+'"  >' + add +'</td>\n\
            <td class="rel'+newid+'"  >' + rel +'</td>\n\
            <td class="con'+newid+'"  >' + cn +'</td>\n\
            <td class="ice'+newid+'"  >'+ iceyn +'</td>\n\
            <td><button class="btn btn-link" onclick="myremovetr(this)">Remove</button></td></tr>');

          // $("#famname").val("");;
          // $("#famadd").val("");
          // $("#famnumber").val("");
        }

    });
     $(".btn-ice").click(function(){
     
     });
    $(".relchk").click(function(){
        var l = $('.relchk').prop('checked');
        if (l==true){
          $("#relinput").css("display", "block")
          $("#rel").css("display", "none")
        }
        else{
          $("#rel").css("display", "block")
          $("#relinput").css("display", "none")
        }

    });
});



 $(document).ready(function(){
        $( ".dropdwn-title" ).click(function() {
          $(this).next().next( ".aaaa" ).slideToggle(500);
          $(this).children().removeClass('fa-angle-down');
          $(this).children().addClass('fa-angle-up');
        });
        $( ".dropdwn-title" ).click(function() {
          $(this).next().next( ".tbl-hide" ).slideToggle(500);
            $(this).children().removeClass('fa-angle-down');
          $(this).children().addClass('fa-angle-up');
        });
        $(document).ready(function(){
          $('[data-toggle="popover"]').popover();   
        });
         $( ".toggle-header" ).click(function() {
         
          var tfl = $(this).next('ul').is(":visible"); 
          if (tfl==true){
                $(this).next('ul').slideToggle(500);
          }
          else{
             $('.hdr-tgl').show();
             $( ".toggle-header-main" ).next("ul").hide();
              $(".toggle-header").next('ul').hide("slow");
              $(this).next('ul').slideToggle(500);

          }

        });


          $( ".toggle-header-main" ).click(function() {
               var tfl = $(this).next('ul').is(":visible"); 
                if (tfl==true){
                  $(this).next('ul').slideToggle(500);
                  $(this).prevAll('li').show("slow");
                }else{
                  $(this).prevAll('li').hide("slow");
                  $(this).next('ul').slideToggle(500);
                }
          });

        $( ".toggle-header-r" ).click(function() {

          $(this).next('ul').slideToggle(500);
        });
        
          $(document).ready(function(){
            $('[data-toggle="tooltip"]').tooltip(); 
          });


        $( ".sign-out" ).click(function() {
          $('.employee-b-info').slideToggle(500);
        });

         $( ".bsc-show" ).click(function() {
          $('.bsc-hide').slideToggle(200);
        });
             $(".vw-pass").click(function(){
          if ($(this).text()=="View Password"){
            $("#npass").attr('type', 'text');
            $("#cnpass").attr('type', 'text');

            $(this).text("Hide Password"); 
          }else{
            $(this).text("View Password");
            $("#npass").attr('type', 'password');
            $("#cnpass").attr('type', 'password'); 
          }
        });
        
         $("#npass").focusout(function(){
            var decimal=  /^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^a-zA-Z0-9])(?!.*\s).{8,15}$/;
            var np = $(this).val();
            if ($(this).val()==""){
              $(".wrningpass").text("Please Input Data !");
              $(".wrningpass").show();
              $(".validpass").hide();

            }
            else if (!np.match(decimal)){
            //   $(".wrningpass").text("Please input password with 8 to 15 characters which contain at least one lowercase letter, one uppercase letter, one numeric digit, and one special character ");
              $(".wrningpass").show();
              $(".validpass").show();
              $("#npass").css("border", "1px solid red");
              $("#cnpass").css("border", "1px solid red");
            }
        });
         $(".btnchangepass").click(function(){
            var curp = $("#cupass").val();
            var np = $("#npass").val();
            var cnp = $("#cnpass").val();
            var myLength = $("#npass").val().length;
            
            
          var decimal=  /^(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^a-zA-Z0-9])(?!.*\s).{8,15}$/;
         
      
            if (curp == "" || np == "" || cnp == ""){
              $(".wrningpass").text("Please Input Data !");
              $(".wrningpass").show();
              $(".validpass").hide();
            }
            else if (!np.match(decimal)){
              // $(".wrningpass").text("Please input password with 8 to 15 characters which contain at least one lowercase letter, one uppercase letter, one numeric digit, and one special character ");
              $(".wrningpass").show();
                  $(".validpass").show();
              $("#npass").css("border", "1px solid red");
              $("#cnpass").css("border", "1px solid red");
            }
            else if (np!=cnp){
              $(".wrningpass").text("New Password Doesnt Match!");
              $(".wrningpass").show();
              $(".validpass").hide();
              $("#npass").css("border", "1px solid red");
              $("#cnpass").css("border", "1px solid red");
              $(".validpass").hide();
            }
            else{
                var data=$(".frmchangepass").serialize();
                 $.ajax({

                          url:'query/Query-newpass.php', 
                          data:data,
                          type:'POST',
                                                               
                           success:function(data){

                            if (data==0){
                              $(".wrningpass").text("Password Changed !");
                              $(".wrningpass").show();
                              $("#cupass").val("");
                              $("#npass").val("");
                              $("#cnpass").val("");
                              $("#npass").css("border", "1px solid #ced4da");
                              $("#cnpass").css("border", "1px solid #ced4da");
                              $("#npass").focus();
                              $("#cupass").css("border", "1px solid #ced4da");
                              $("#changepass").modal('hide');
                              $("#passchanged").modal('show');
                                    $(".wrningpass").hide();
                                       $(".validpass").hide();
                            }
                            else{
                              $("#cupass").css("border", "1px solid red");
                              $("#cupass").css("box-shadow", "0 0 0 0.1rem red");
                              $("#npass").css("border", "1px solid #ced4da");
                              $("#cnpass").css("border", "1px solid #ced4da");
                              $("#cupass").focus();
                              $(".wrningpass").text("Current Password Doesnt Matched !");
                              $(".wrningpass").show();
                                 $(".validpass").hide();
                            }
                           }
                        });
            }
         });


    });



$(document).ready(function() {
    $( ".clrdata" ).click(function() {
        var comid = $(this).attr("id");
        // $.ajax({
        //         url:'query/Query-DeleteAllData.php', 
        //         data:data : comid,
        //         type:'POST',
        //           success:function(data){
                           
        //           }
        // });
    });
    $(window).resize(function(){
            if ($(window).width() > 600){
                $(".w-sidebarmenu").show();
            }
            else{
                $(".w-sidebarmenu").hide();
            }
    });
     // $(".w-sidebarmenu").toggle("slow");
    setInterval(function() {
          
         }, 1000);

    $( window ).scroll(function() {
      var scrollHeight = $(document).scrollTop();
      if ($(window).width() <= 600){
          if (scrollHeight>=100){
            $(".wd-search").hide();
          }else{
            $(".wd-search").show();
          }
      }
    
    }); 
});
