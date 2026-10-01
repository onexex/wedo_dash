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

    // 201 files → shared PDF viewer (delegated: tiles are re-rendered by live search).
    // The PDF only loads on click, and is unloaded on close.
    $(document).on("click", ".e2-file", function(e){
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.which === 2) { return; } // let new-tab clicks through
        e.preventDefault();
        var src = $(this).data("pdf");
        var $v  = $("#e201PdfViewer");
        $v.find(".e201-pdf-title").text($(this).data("title") || "Document");
        $v.find(".e201-pdf-open").attr("href", src);
        $v.find(".e201-pdf-frame").attr("src", src);
        $v.modal("show");
    });
    $("#e201PdfViewer").on("hidden.bs.modal", function(){
        $(this).find(".e201-pdf-frame").attr("src", "about:blank");
    });

    $(document).on("click", ".e2-salary__toggle", function(){
        $(this).closest(".e2-salary").toggleClass("is-revealed");
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
          $('#e201 [data-toggle="tooltip"]').tooltip();
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

           

  function printDiv() {
    // Prints #e201 from a hidden, same-page iframe. (A window.open('') pop-up +
    // document.write left a stray tab behind on Cancel that showed the main
    // page's URL over a static snapshot — reloading it broke.)
    var src = document.getElementById('e201');
    if (!src) { return; }

    var old = document.getElementById('e201PrintFrame');
    if (old) { old.parentNode.removeChild(old); }

    // reuse the page's own (cache-busted) stylesheet URLs
    var cssHref = function (needle, fallback) {
      var l = document.querySelector('link[href*="' + needle + '"]');
      return l ? l.href : fallback;
    };
    var esc = function (s) { return String(s).replace(/"/g, '&quot;'); };
    var txt = function (s) { return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;'); };

    // document letterhead + footer (print only)
    var company = (document.querySelector('.wd-brand__tag') || {}).textContent || 'WeDo BPO Inc';
    var nameEl  = src.querySelector('.e2-name');
    var printedOn = new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    var printHead =
      '<header class="e2-doc-head">' +
        '<img src="assets/images/logos/wedo-logo.png" alt="">' +
        '<div><strong>Electronic 201 File</strong><span>' + txt(company.trim()) + '</span></div>' +
        '<div class="e2-doc-head__meta"><b>Confidential</b><span>Printed ' + txt(printedOn) + '</span></div>' +
      '</header>';
    var printFoot =
      '<footer class="e2-doc-foot">' +
        '<span>' + txt(nameEl ? nameEl.textContent.trim() : '') + ' &middot; 201 File</span>' +
        '<span>This document contains confidential employee information.</span>' +
      '</footer>';

    var frame = document.createElement('iframe');
    frame.id = 'e201PrintFrame';
    frame.setAttribute('aria-hidden', 'true');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;visibility:hidden';
    document.body.appendChild(frame);

    var doc = frame.contentWindow.document;
    doc.open();
    doc.write(
      '<!DOCTYPE html><html><head><meta charset="utf-8"><title>201 File</title>' +
      '<base href="' + esc(document.baseURI) + '">' +
      // no Bootstrap here: its @media print block strips all backgrounds, forces
      // black text and appends "(url)" after every link — the profile doesn't need it
      '<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">' +
      '<link rel="stylesheet" href="' + esc(cssHref('wedo-theme.css', 'assets/css/wedo-theme.css')) + '">' +
      '<link rel="stylesheet" href="' + esc(cssHref('e201.css', 'assets/css/e201.css')) + '">' +
      '<style>html,body{background:#fff}body{padding:0}</style>' +
      '</head><body>' +
      '<div class="e201-scope e201-print">' + printHead + src.innerHTML + printFoot + '</div>' +
      '</body></html>'
    );
    doc.close();

    var printed = false;
    var go = function () {
      if (printed) { return; }
      printed = true;
      var w = frame.contentWindow;
      var fire = function () {
        w.focus();
        w.print();   // blocks until the dialog closes (Print or Cancel)
        setTimeout(function () { if (frame.parentNode) { frame.parentNode.removeChild(frame); } }, 500);
      };
      // Raleway/Biryani come from Google Fonts — wait so the print isn't in a fallback font
      if (w.document.fonts && w.document.fonts.ready) {
        w.document.fonts.ready.then(fire, fire);
      } else { fire(); }
    };
    // wait for stylesheets / photo, but don't hang if a CDN is slow
    frame.onload = function () { setTimeout(go, 150); };
    setTimeout(go, 3000);
  }
