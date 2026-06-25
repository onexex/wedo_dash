      
        function fnctshow(){
         var c = document.getElementsByClassName("bsc-hide");
         var d = document.getElementsByClassName("bsc-hide1");
         var e = document.getElementsByClassName("bsc-hide2");

           if (c[0].style.display === "none") {
              c[0].style.display = "table-row";
            } else {
              c[0].style.display = "none";
            }
            if (d[0].style.display === "none") {
              d[0].style.display = "table-row";
            } else {
              d[0].style.display = "none";
            }
            if (e[0].style.display === "none") {
              e[0].style.display = "table-row";
            } else {
              e[0].style.display = "none";
            }
         
        }
$(document).ready(function(){
          // display message
        setInterval(function() {
               
                                        var xmlhttp = new XMLHttpRequest();
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                    $("#com-messages").empty();
                                    document.getElementById("com-messages").innerHTML = this.responseText;
                                }
                                };
                                    xmlhttp.open("GET", "query/Query-LoadMessages.php?lde201", true);
                                    xmlhttp.send();
        }, 1000); 

           $(document).on("click", ".btnyespass", function(){
           
              var empidd = $(this).attr("id");
                     $.ajax({
                                  url:'query/query-resetpassword.php', 
                                  data:{data : empidd},
                                  type:'POST',
                                                                       
                                   success:function(data){
                                        $('#modalWarning').modal('toggle');
                             $('.mdlsc').modal('toggle');
                                        $('#modalWarning .alert').html("Succesfully Updated !"); 
                                  }
                                    
                            });
           });

      $("#btnsend").click(function(){
           var ms = $("#mssg").val();
                  if (ms.trim()==""){
                      $('#modalWarning').modal('toggle');
                      $('#modalWarning .alert').html("Please Input Message First !"); 

                  }
                    else{
                    var msg = ms.trim();
                     $.ajax({
                                  url:'query/query-NewMessage.php', 
                                  data:{data : msg},
                                  type:'POST',
                                                                       
                                   success:function(data){
                                       
                                         $("#mssg").val("");
                                        
                                        var xmlhttp = new XMLHttpRequest();
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                    $("#com-messages").empty();
                                    document.getElementById("com-messages").innerHTML = this.responseText;
                                }
                                };
                                    xmlhttp.open("GET", "query/Query-LoadMessages.php?lde201", true);
                                    xmlhttp.send();
                                    
                                   }
                            });
                  }
      });
     $('#mssg').keyup(function(e){
          if(e.keyCode == 13)
          {
                var ms = $("#mssg").val();
                  if (ms.trim()==""){
                            $('#modalWarning').modal('toggle');
                  $('#modalWarning .alert').html("Please Input Message First !"); 
                  }
                    else{
                    var msg = ms.trim();
                     $.ajax({
                                  url:'query/query-NewMessage.php', 
                                  data:{data : msg},
                                  type:'POST',
                                                                       
                                   success:function(data){
                                       
                                         $("#mssg").val("");
                                        
                                        var xmlhttp = new XMLHttpRequest();
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                    $("#com-messages").empty();
                                    document.getElementById("com-messages").innerHTML = this.responseText;
                                }
                                };
                                    xmlhttp.open("GET", "query/Query-LoadMessages.php?lde201", true);
                                    xmlhttp.send();
                                    
                                   }
                            });
                  }
          }
     });

    $('.search-box').on("keyup input", function(){
 
        /* Get input value on change */
        var inputVal = $(this).val();
        var resultDropdown = $(".dv-livesearch");
        if(inputVal.length){

            $.get("query/Query-LiveSearch.php", {term: inputVal}).done(function(data){
                // Display the returned data in browser
                   // alert("data1");
                resultDropdown.html(data);

            });
        } else{
            resultDropdown.empty();
               
        }
    });
    
    // Set search input value on click of result item
    $(document).on("click", ".dv-livesearch a", function(){
      
          var idname = $(this).attr('id');    
          var xmlhttp = new XMLHttpRequest();
          xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {
          $("#e201").empty();
          document.getElementById("e201").innerHTML = this.responseText;
      }
    };
          xmlhttp.open("GET", "query/Query-e201.php?q=" + idname, true);
          xmlhttp.send();
          $(this).parents(".wd-search").find('input[type="text"]').val($(this).text());
          $(this).parent(".dv-livesearch").empty(); 
          $(".search-box").val(""); 
          $(".search-box").attr("placeholder", "Search");        
    });   
});
    $(document).ready(function(){
        $(document).on("click", ".ListJD .fa-check-circle", function(){
          var jdid = $(this).attr("id");
          var empid = $(".empidjd").html();
          
          // Query-SearchJobDesc.php

           $.ajax({
                            url:'query/Query-InsertDeleteJJD.php?insert',
                            type:'post',
                            data:{JobId : jdid , EmIDJD : empid},
                             success:function(response){  
                              if (response==2){
                                  $('#modalWarning').modal('toggle');
                                  $('#modalWarning .alert').html("You have Already this Job Description"); 
                              }else{
                                var xmlhttp = new XMLHttpRequest();
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                    $("#EmpListJD").empty();
                                    document.getElementById("EmpListJD").innerHTML = this.responseText;
                                }
                                };
                                    xmlhttp.open("GET", "query/Query-SearchJobDesc.php?delete=" + empid, true);
                                    xmlhttp.send();
                                 var xmlhttp = new XMLHttpRequest();
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                    $("#jdview").empty();
                                    document.getElementById("jdview").innerHTML = this.responseText;
                                }
                                };
                                    xmlhttp.open("GET", "query/Query-SearchJobDesc.php?jd=" + empid, true);
                                    xmlhttp.send();
                  
                            }
                            
                          }
                        });
        });
        
          $(document).ready(function(){
            $('#e201 [data-toggle="tooltip"]').tooltip(); 
          });

          $("#addnewJD").click(function(){
             if ($(".txtnewjd").val()==""){
                  $('#modalWarning').modal('toggle');
                  $('#modalWarning .alert').html("Please Input Data in Job Description"); 
             }
             else{
                var jd=$(".txtnewjd").val();

                 $.ajax({
                            url:'query/Query-InsertDeleteJJD.php?insertnewjd',
                            type:'post',
                            data:{JobId : jd},
                             success:function(response){ 
                                // display all job description
                                var xmlhttp = new XMLHttpRequest();
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                    $("#ListJD").empty();
                                    document.getElementById("ListJD").innerHTML = this.responseText;
                                }
                                };
                                    xmlhttp.open("GET", "query/Query-SearchJobDesc.php?displayjd=", true);
                                    xmlhttp.send();
                                $(".txtnewjd").val(" ");
                             }
                });             
             }
          });
 

          $(document).on("keyup", ".txtsjdesc", function(){
              var jd = $(this).val();
              var jd1 = $.trim(jd)
                 $("#ListJD").empty();
               $.ajax({
                            url:'query/Query-SearchJobDesc.php?srchjd',
                            type:'post',
                            data:{JobId : jd1},
                            success:function(response){ 
                                // display all job description
                                $("#ListJD").append(response);
                             }
                });      
          });

          $(document).on("click", ".EmpListJD .fa-times", function(){
            var jdid = $(this).attr("id");
            var empid = $(".empidjd").html();
            $.ajax({
                            url:'query/Query-InsertDeleteJJD.php?delete',
                            type:'post',
                            data:{JobId : jdid},
                            success:function(response){  
                                var xmlhttp = new XMLHttpRequest();
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                    $("#EmpListJD").empty();
                                    document.getElementById("EmpListJD").innerHTML = this.responseText;
                                }
                                };
                                    xmlhttp.open("GET", "query/Query-SearchJobDesc.php?delete=" + empid, true);
                                    xmlhttp.send();

                                 xmlhttp.open("GET", "query/Query-SearchJobDesc.php?delete=" + empid, true);
                                    xmlhttp.send();
                                 var xmlhttp = new XMLHttpRequest();
                                xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                    $("#jdview").empty();
                                    document.getElementById("jdview").innerHTML = this.responseText;
                                }
                                };
                                    xmlhttp.open("GET", "query/Query-SearchJobDesc.php?jd=" + empid, true);
                                    xmlhttp.send();    
                  
                            }
                  });
          });

    });

           

  function printthisDiv(){
        try {
       
       
                             var divToPrint=document.getElementById('e201');
          // var divtoEdet=document.getElementById('emp-detailscdc');
          // // var profname=document.getElementById('profname');
          // var edtoh=document.getElementById('ed-to-hide');
          

          var x = document.getElementById("cdc-hide");
          var y = document.getElementById("prof-img");
          var z = document.getElementById("btnprint");
          var a = document.getElementById("msg");
          var e = document.getElementById("emp-pos");
          var d = document.getElementById("emp-company");
          var c = document.getElementById("emp-name");
          var b = document.getElementById("cinfo-title-fd");
          var f = document.getElementById("Updateinfo");
          // var g = document.getElementById("sndmessage");
          // var h = document.getElementById("btnp");
          

          y.style.cssFloat = "right";
          // divtoEdet.style.display = "block";
          x.style.display = "none";
          y.style.display = "inline-block";
          c.style.display = "inline-block";
          z.style.display = "none";
          a.style.display = "none";
          f.style.display = "none";
          // g.style.display = "none";
          y.style.height = '250px';
          y.style.width = '250px';
        var newWin=window.open('','Print-Window');

        newWin.document.open();

        newWin.document.write('<html><head><link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">');
        newWin.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">');
        newWin.document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>');
        newWin.document.write('<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>');
        newWin.document.write('<script src="assets/js/script.js"></script>');
        newWin.document.write('<script src="assets/js/script-e201.js"></script>');
        newWin.document.write('<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>');
        newWin.document.write('<link rel="stylesheet" type="text/css" href="assets/css/style.css">');
        newWin.document.write('</head><body onload="window.print()">'
          + divToPrint.innerHTML + 
          '</body></html>');

        newWin.document.close();

        setTimeout(function(){newWin.close();},10);
        x.style.display = "inline-block";
        z.style.display = "inline-block";
        a.style.display = "inline-block";
        f.style.display = "inline-block";
        // g.style.display = "inline-block";
             // divtoEdet.style.marginTop = "0px";
            y.style.display = "none";
           c.style.marginLeft = "0px";

            }
        catch(err) {
          alert(err.message);
        }
  }
  function printDiv() 
  
  {
    var divToPrint=document.getElementById('e201');
    var divtoEdet=document.getElementById('emp-detailscdc');
    // var profname=document.getElementById('profname');
    var edtoh=document.getElementById('ed-to-hide');
    var pinfo=document.getElementById('p-info');
    var pdnone=document.getElementById('pd-none');
    var pdnone2=document.getElementById('pd-none2');
// changepasskey
    var x = document.getElementById("cdc-hide");
    var y = document.getElementById("prof-img");
    var z = document.getElementById("btnprint");
    var i = document.getElementById("changepasskey");
    var a = document.getElementById("msg");
    var e = document.getElementById("emp-pos");
    var d = document.getElementById("emp-company");
    var c = document.getElementById("emp-name");
    var b = document.getElementById("cinfo-title-fd");
    var f = document.getElementById("Updateinfo");
    var g = document.getElementById("sndmessage");
    var h = document.getElementById("btnp");
    

    y.style.cssFloat = "right";
    divtoEdet.style.display = "block";
    x.style.display = "none";
    i.style.display = "none";
    y.style.display = "inline-block";
    c.style.display = "inline-block";
    pinfo.style.display = "inline-block";
    pdnone.style.display = "inline-block";
    z.style.display = "none";
    a.style.display = "none";
    f.style.display = "none";
    g.style.display = "none";
    y.style.height = '200px';
    y.style.top = '2px';
    pinfo.style.width = "300px";
    pdnone.style.width = "300px";

    y.style.width = '200px';
    var newWin=window.open('','Print-Window');

    newWin.document.open();

    newWin.document.write('<html><head><link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">');
    newWin.document.write('<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">');
    newWin.document.write('<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js" integrity="sha384-UO2eT0CpHqdSJQ6hJty5KVphtPhzWj9WO1clHTMGa3JDZwrnQq4sF86dIHNDz0W1" crossorigin="anonymous"></script>');
    newWin.document.write('<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>');
    newWin.document.write('<script src="assets/js/script.js"></script>');
    newWin.document.write('<script src="assets/js/script-e201.js"></script>');
    newWin.document.write('<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js" integrity="sha384-JjSmVgyd0p3pXB1rRibZUAYoIIy6OrQ6VrjIEaFf/nJGzIxFDsf4x0xIM+B07jRM" crossorigin="anonymous"></script>');
    newWin.document.write('<link rel="stylesheet" type="text/css" href="assets/css/style.css">');
    newWin.document.write('</head><body onload="window.print()">'
     + divToPrint.innerHTML + 
      '</body></html>');

    newWin.document.close();

    // setTimeout(function(){newWin.close();},10);
    x.style.display = "inline-block";
    z.style.display = "inline-block";
    a.style.display = "inline-block";
    f.style.display = "inline-block";
    g.style.display = "inline-block";
    i.style.display = "inline-block";
    divtoEdet.style.marginTop = "0px";
    y.style.display = "none";
    c.style.marginLeft = "0px";

}