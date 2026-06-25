
   $(document).ready(function(){
      


      $('.emp-id').on("keyup input", function(){
         /* Get input value on change */
        var inputVal = $(this).val();
        var resultDropdown = $(".dv-s-m");
        if(inputVal.length){
    
            $.get("query/Query-MessageEmp.php", {term: inputVal}).done(function(data){
                // Display the returned data in browser
                resultDropdown.html(data);

            });
        } else{
            resultDropdown.empty();
        }

      });

      var scrolled = false;
      // setInterval(updateScroll,1000);
    function updateScroll(){

        if(!scrolled){
               alert(scrolled);
            var element = document.getElementById("msgc");
            element.scrollTop = element.scrollHeight;
        }
    }

    $("#msgc").on('scroll', function(){
        scrolled=true;

    });
      //send message
        $('.btnsendm').on("click button", function(){

          var ms = $(".msend").val();
          if (ms.trim()==""){
              $('#modalWarning').modal('toggle');
                  $('#modalWarning .alert').html("Please Input Message First !"); 
          }
          else if ($(".r-name").text()==""){
              $('#modalWarning').modal('toggle');
                  $('#modalWarning .alert').html("Please Search Employee to message"); 
         
          }
          else{
            var msg = ms.trim();
             $.ajax({
                          url:'query/query-NewMessage.php', 
                          data:{data : msg},
                          type:'POST',
                                                               
                           success:function(data){
                            if (data==1){
                              
                              var xmlhttp = new XMLHttpRequest();

                              xmlhttp.onreadystatechange = function() {
                                if (this.readyState == 4 && this.status == 200) {
                                  $("#msgc").empty();
                                  document.getElementById("msgc").innerHTML = this.responseText;
                                }
                              };
                                xmlhttp.open("GET", "query/Query-UpdateMessageContent.php", true);
                                xmlhttp.send();

                                   

                                          

                                $(".msend").val("");
                                var element = document.getElementById("msgc");
                                element.scrollTop = element.scrollHeight;
                            }else{
                              alert(data);

                            }
                           }
                    });
          }

        });



        $('.msend').keyup(function(e){
            if(e.keyCode == 13)
            {
                  var ms = $(".msend").val();
                  if (ms.trim()==""){
                    $('#modalWarning').modal('toggle');
                  $('#modalWarning .alert').html("Please Input Message First !"); 
                  }
                  else if ($(".r-name").text()==""){
                      $('#modalWarning').modal('toggle');
                  $('#modalWarning .alert').html("Please Search Employee to message"); 
                  }
                  else{
                    var msg = ms.trim();
                     $.ajax({
                                  url:'query/query-NewMessage.php', 
                                  data:{data : msg},
                                  type:'POST',
                                                                       
                                   success:function(data){
                                    if (data==1){
                                      
                                      var xmlhttp = new XMLHttpRequest();

                                      xmlhttp.onreadystatechange = function() {
                                        if (this.readyState == 4 && this.status == 200) {
                                          $("#msgc").empty();
                                          document.getElementById("msgc").innerHTML = this.responseText;
                                        }
                                      };
                                        xmlhttp.open("GET", "query/Query-UpdateMessageContent.php", true);
                                        xmlhttp.send();


                                      


                                        $(".msend").val("");
                                    }else{
                                      alert(data);

                                    }
                                   }
                            });
                  }
            }
        });


        // display message
        setInterval(function() {
              var xmlhttp = new XMLHttpRequest();
              xmlhttp.onreadystatechange = function() {
                  if (this.readyState == 4 && this.status == 200) {
                    $("#msgc").empty();
                    document.getElementById("msgc").innerHTML = this.responseText;
                  }
               
              };
                  xmlhttp.open("GET", "query/Query-UpdateMessageContent.php", true);
                  xmlhttp.send();
        }, 1000);


       // Set search input value on click of result item
    $(document).on("click", ".dv-s-m a", function(){
          
       

      
        $("#msgc").animate({ scrollTop: $('#msgc').prop("scrollHeight")}, 1000);
      	
        $(".dv-s-m a").removeClass("btnam");
      	$(this).addClass("btnam");

        var idname = $(this).attr('id'); 
        // var xmlhttp = new XMLHttpRequest();

        // xmlhttp.onreadystatechange = function() {
        // if (this.readyState == 4 && this.status == 200) {
       
        //   document.getElementById("msgc").innerHTML = this.responseText;
        // }
        // };
        // xmlhttp.open("GET", "query/Query-SearchMessages.php?q=" + idname, true);
        // xmlhttp.send();

        var msg = "a";
         $("#msgc").empty();
        $.ajax({
              url:'query/Query-SearchMessages.php?q=' + idname, 
              data:msg,
              type:'POST',
                success:function(data){
                  $("#msgc").append(data);
                }
               }); 

         $.ajax({
              url:'query/Query-Srname.php', 
              data:{term: idname},
              type:'POST',
                success:function(data){
                 $(".r-name").text(data);
                }
          });


          $("#empmess").empty();
          $.ajax({
              url:'query/Query-LoadMessages.php?q=' + idname, 
              data:msg,
              type:'POST',
                success:function(data){
                  $("#empmess").append(data);
                }
               }); 

        //   xmlhttp.onreadystatechange = function() {
        // if (this.readyState == 4 && this.status == 200) {
       
        //   document.getElementById("empmess").innerHTML = this.responseText;
        // }
        // };
        //   xmlhttp.open("GET", "query/Query-LoadMessages.php?q=" + idname, true);
        //   xmlhttp.send();


          //   $("#empmess").empty();
          //   xmlhttp.onreadystatechange = function() {
          // if (this.readyState == 4 && this.status == 200) {
          //   $("#empmess").empty();
          //   document.getElementById("empmess").innerHTML = this.responseText;
          // }
          // };
          //   xmlhttp.open("GET", "query/Query-SearchEmployeeM.php", true);
          //   xmlhttp.send();

           // $.get("query/Query-Srname.php", {term: idname}).done(function(data){
           //      // Display the returned data in browser
            
           //      $(".r-name").text("data");
           //      $(".emp-id").val("");
                
           //  });
    });
      $( window ).unload(function() {
  alert("a");
});



       });

