  function printDiv() 
{

        // alert("a");
        // var f = document.getElementById("tab_filter");
        // var x = document.getElementById("tab_wrapper");
        // f.style.display = "none";
        var pagetitle = "(" + document.title + ")";
        // x.style.display = "none";
        var print_div = document.getElementById("tblprint");
        var print_nn = document.getElementById("tab_info");
        var print_nb = document.getElementById("tab_paginate");
        var print_logo = document.getElementById("lg-hide-logo");
        var y = document.getElementsByClassName('dt-buttons');
        var aNode = y[0];
        aNode.style.display= "none";
        print_nn.style.display= "none";
        print_nb.style.display= "none";
        document.getElementsByTagName("table")[0].removeAttribute("id");
        var print_area = window.open();
        print_area.document.write('<html><head><link rel="stylesheet" type="text/css" href="assets/css/style-reports.css"> <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.5.6/css/buttons.dataTables.min.css"> <link rel="stylesheet" href="https://cdn.datatables.net/1.10.19/css/jquery.dataTables.min.css"><link rel="stylesheet" type="text/css" href="assets/css/newstyle.css"></head><body>');
        print_area.document.write(print_logo.innerHTML + " " + pagetitle + "<br>");
        print_area.document.write(print_div.innerHTML );
        print_area.document.write('</body></html>');
        // print_area.document.close();
        print_area.focus();
        print_area.print();
        x.style.display = "inline-block";
        f.style.display = "inline-block";
        print_area.close();
// This is the code print a particular div element
    }

$(document).ready(function(){
  
  $("#empfdet").change(function(){
      var empid = $(this).val();
       $.ajax({
                            url:'query/Query-searchdarreports.php?fdetails',
                            type:'post',
                            data: { Eid : empid },
                            success:function(res){
                              $("#darviewer").empty();
                              $("#darviewer").append(res);
                            }
        });
  });

   $(".refreshdar").click(function(){ 
 
   	  var vl = ($("#empcompid").val());
      var dtfr = ($("#datefrom").val()); 
      var dtto = ($("#dateto").val()); 
            $("#tab_paginate").empty();
            $("#tab_info").empty();
            $("#darviewer").text("Loading Data...");
            $("#modalWarning").toggle();
         $.ajax({
                            url:'query/Query-searchdarreports.php?srchdar',
                            type:'post',
                            data: { Eid : vl , dtfrom : dtfr, dtto : dtto },
                            success:function(res){
                               $("#modalWarning").toggle();  
                             $("#darviewer").empty();
                              $("#darviewer").append(res);
                            }
        });
         
 });
 $("#viewalasv").click(function(){ 
          var vl = ($("#empcompid").val());
          var dtfr = ($("#dtp1").val()); 
          var dtto = ($("#dtp2").val()); 

         $.ajax({
                            url:'query/Query-searchdarreports.php?srchalsv',
                            type:'post',
                            data: { Eid : vl , dtfrom : dtfr, dtto : dtto },
                            success:function(res){
                              $("#darviewer").empty();
                              $("#darviewer").append(res);
                            }
        });



 });
 $("#comcoda").click(function(){ 
          var vl = ($("#empcompid").val());
          var dtfr = ($("#dtp1").val()); 
          var dtto = ($("#dtp2").val()); 

          $.ajax({
                            url:'query/Query-searchdarreports.php?srchcomdata',
                            type:'post',
                            data: { Eid : vl , dtfrom : dtfr, dtto : dtto },
                            success:function(res){

                              $("#darviewer").empty();
                              $("#darviewer").append(res);
                            }
          });
 });


  $("#obvi").click(function(){ 
           var vl = ($("#empcompid").val());
      var dtfr = ($("#dtp1").val()); 
      var dtto = ($("#dtp2").val()); 

         $.ajax({
                            url:'query/Query-searchdarreports.php?srchobret',
                            type:'post',
                            data: { Eid : vl , dtfrom : dtfr, dtto : dtto },
                            success:function(res){
                                // alert(res);

                              $("#darviewer").empty();
                              $("#darviewer").append(res);
                            }
        });

  });

   $(".btnovb").click(function(){ 
           var vl = ($("#empcompid").val());
      var dtfr = ($("#dtp1").val()); 
      var dtto = ($("#dtp2").val()); 

         $.ajax({
                            url:'query/Query-searchdarreports.php?srchovertimt',
                            type:'post',
                            data: { Eid : vl , dtfrom : dtfr, dtto : dtto },
                            success:function(res){

                              $("#darviewer").empty();
                              $("#darviewer").append(res);
                            }
        });

  });



        $("#viewlilo").click(function(){ 
                 var modal = document.getElementById("thislilomodal");
            //    modal.style.display = "block"; 
                var vl = ($("#empcompid").val());
                var dtfr = ($("#dtp1").val()); 
                var dtto = ($("#dtp2").val()); 
               
                  
                       $.ajax({
                            url:'Query-LiloView',
                            type:'post',
                            data: { Eid : vl , dtfrom : dtfr, dtto : dtto },
                            success:function(res){
                                // console.log(res);
                                // return false;
                                modal.style.display = "none"; 
                             $("#tab").empty();
                              $("#tab").append(res);
                                                            var xmlhttp = new XMLHttpRequest();            
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                document.getElementById("tab").innerHTML = this.responseText;
                                     modal.style.display = "none";
                                  }
                                  };
                                xmlhttp.open("GET", "Query-LiloView?eid=" + vl + "&dfrom=" + dtfr + "&dto=" + dtto, true);
                                xmlhttp.send();
                            }
                         });
                 
                     

                          
                               

         });

$(".eorefresh").click(function(){ 

                  var vl = ($("#empcompid").val());
                  var dtfr = ($("#dtp1").val()); 
                      var dtto = ($("#dtp2").val()); 
               
                     $.ajax({
                            url:'query/Query-searchdarreports.php?srcheo',
                            type:'post',
                            data: { Eid : vl , dtfrom : dtfr, dtto : dtto },
                            success:function(res){

                              $("#eovw").empty();
                              $("#eovw").append(res);
                      }
        });

         });

// Query-searchdarreports.php eorefresh
$("#alashistory").click(function(){ 

                  var dtfr = ($("#dtp1").val()); 
                  var dtto = ($("#dtp2").val()); 

                               var xmlhttp = new XMLHttpRequest();                           
                                $("#tbalas").empty();
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                document.getElementById("tbalas").innerHTML = this.responseText;
                                  }
                                  };
                           
                                xmlhttp.open("GET", "modulehistory.php?alash" + "&dfrom=" + dtfr + "&dto=" + dtto, true);
                                xmlhttp.send();

         });
//query view ob
$("#obhistory").click(function(){ 

                  var dtfr = ($("#dtp1").val()); 
                  var dtto = ($("#dtp2").val()); 

                               var xmlhttp = new XMLHttpRequest();                           
                                $("#tbob").empty();
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                document.getElementById("tbob").innerHTML = this.responseText;
                                  }
                                  };
                           
                                xmlhttp.open("GET", "modulehistory.php?obh" + "&dfrom=" + dtfr + "&dto=" + dtto, true);
                                xmlhttp.send();

         });
//query view ob
$(document).on("click", "#ot", function() {    
                  var dtfr = ($("#dtp1").val()); 
                  var dtto = ($("#dtp2").val()); 
               $("#tbot").empty();
                     $.ajax({
                            url:'modulehistory.php?oth',
                            type:'post',
                            data: { dtfrom : dtfr, dtto : dtto },
                            success:function(res){
                              
                              // $("#eovw").empty();
                               $("#tbot").append(res);
                             
                      }
        });
                               // var xmlhttp = new XMLHttpRequest();                           
                               //  $("#tbot").empty();
                               //  xmlhttp.onreadystatechange = function() {
                               //  if (this.readyState == 4 && this.status == 200) {
                               //  document.getElementById("tbot").innerHTML = this.responseText;
                               //    }
                               //    };
                           
                               //  xmlhttp.open("GET", "modulehistory.php?oth" + "&dfrom=" + dtfr + "&dto=" + dtto, true);
                               //  xmlhttp.send();

         });
//query view oe
$("#eohistory").click(function(){ 

                  var dtfr = ($("#dtp1").val()); 
                  var dtto = ($("#dtp2").val()); 

                               var xmlhttp = new XMLHttpRequest();                           
                                $("#tbeodata").empty();
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                document.getElementById("tbeodata").innerHTML = this.responseText;
                                  }
                                  };
                           
                                xmlhttp.open("GET", "modulehistory.php?eoh" + "&dfrom=" + dtfr + "&dto=" + dtto, true);
                                xmlhttp.send();

         });
//query view oe
$("#sob").click(function(){ 

                  var dtfr = ($("#dtp1").val()); 
                  var dtto = ($("#dtp2").val()); 

                               var xmlhttp = new XMLHttpRequest();                           
                                $("#tbsob").empty();
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                document.getElementById("tbsob").innerHTML = this.responseText;
                                  }
                                  };
                           
                                xmlhttp.open("GET", "modulehistory.php?sob" + "&dfrom=" + dtfr + "&dto=" + dtto, true);
                                xmlhttp.send();

         });

});