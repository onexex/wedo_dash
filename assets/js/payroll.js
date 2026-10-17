$(document).ready(function(){
     $(document).on('click', '#btnApprovedPayroll', function(e){
    var pdate = ($("#pdate").val());
    
   if(pdate==""){ //if value is null 
    $("#result").addClass("alert alert-info offset4 span4");
    $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Invalid inclusive date!');
    $(".alert").show();
    $(".alert").css("opacity", "100%");
     $('.alert .close').on("click", function () {
        $(this).parent().slideUp(500, 0).slideUp(500);
    });
      return false;
   } 
   else{
    jQuery.ajax({
      url:'query/query-payrollscript.php?approvedPayroll', 
      method: 'POST',
      data: {pdate:pdate},
      cache: false,
      dataType: 'json',
      error: function(xhr, status, error) {
        alert(xhr.responseText );
    },
      success: function(dataResult){
     var err=dataResult.error;
     if (err==0){
      $("#result").addClass("alert alert-danger offset4 span4");
      $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Payday Not found');
      $(".alert").show();
      $(".alert").css("opacity", "100%");
       $('.alert .close').on("click", function () {
          $(this).parent().slideUp(500, 0).slideUp(500);
      });
  
       return false;
     }

     if (err==1){
      $("#result").addClass("alert alert-info offset4 span4");
      $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Selected date was already approved for release.');
      $(".alert").show();
      $(".alert").css("opacity", "100%");
       $('.alert .close').on("click", function () {
          $(this).parent().slideUp(500, 0).slideUp(500);
      });
  
       return false;
     }

     if (err==2){
      $("#result").addClass("alert alert-success offset4 span4");
      $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Succesfully Approved Payroll!');
      $(".alert").show();
      $(".alert").css("opacity", "100%");
       $('.alert .close').on("click", function () {
          $(this).parent().slideUp(500, 0).slideUp(500);
      });
  
       return false;
     }
        
      }
  });

  }

   
});
    
    
  $("#btngen").click(function(){
    var filter = ($(".payrolfilater").val());
        var vl = ($("#pdate").val());
        var dtfr = ($("#cutOFd1").val()); 
        var dtto = ($("#cutOFd2").val()); 

        if(( dtfr=="")|| (vl=="") || (dtto==""))
        {
          alert("Invalid inclusive dates!");
        }
        else
        {
          $("#tbldata").empty();
          $("#tbldata").append("Loading ..");
          $("#btngen").text("Generating....");
          $(document).on({
            ajaxStart: function(){
                $("body").addClass("loading"); 
            },
            ajaxStop: function(){ 
                $("body").removeClass("loading"); 
            }    
        }); 
        document.getElementById("btngen").disabled = true;
      $.ajax({
              url:'query/Query-payroll.php', 
              type:'post',
              data:{vl : vl,dtfr : dtfr,dtto : dtto},

              success:function(res){
               if(!/^\s*1000[0-5]\s*$/.test(res)) { alert(res); }
               if(res==10004)   {
                  $("#result").addClass("alert alert-danger offset4 span4");
                  $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong>Re-generating payroll is not allowed. Selected date was already approved and released!');
                  $(".alert").show();
                  $(".alert").css("opacity", "100%");
                   $('.alert .close').on("click", function () {
                      $(this).parent().slideUp(500, 0).slideUp(500);
                  });
                  document.getElementById("btngen").disabled = false;
                } 
                  else if(/^\s*1000[0-35]\s*$/.test(res))   {
                    showPendingFilings(parseInt(res, 10), vl, dtfr, dtto);
                    document.getElementById("btngen").disabled = false;
                  } 
                  else{                 
                    document.getElementById("btngen").disabled = false;

                      var xmlhttp = new XMLHttpRequest();            
                      xmlhttp.onreadystatechange = function() {
                      if (this.readyState == 4 && this.status == 200) {
                      document.getElementById("reportview").innerHTML = this.responseText;
                          //modal.style.display = "none";
                        }
                        };
                      xmlhttp.open("GET", "query-searchpayroll.php?dte=" + vl +  "& filter=" + filter, true);
                      xmlhttp.send();
                      document.getElementById("btngen").disabled = false;
                      $("#btngen").text("Generate");
                  }              
                  document.getElementById("btngen").disabled = false;
                  $("#btngen").text("Generate");
              }
      });

    }

 
  });
    
  $("#inputAdj").click(function(){
    var vl = ($("#pdate").val());
    jQuery.ajax({
      url:'query/query-payrollscript.php?getadjustment', 
      method: 'POST',
      data: {vl:vl},
      cache: false,
      dataType: 'json',
      success: function(res){
        var resultData=res.data;          
        var errcode = res.errcode;
        var i=1;
       if(errcode==0){
        var emp = '';
                
                $(resultData).each(function (index, item) {                
                      emp+="<tr>"
                      emp+="<td>"+ i++ +"</td>"
                      +"<td class='idBarcode'>"+item.EmpLN+ " "+item.EmpFN+ "</td>"
                      +"<td style='text-align:left'>"+item.Amount+"</td>"
                      +"<td style='text-align:left'>"+item.pdate+"</td>"
                      +"<td style='text-align:left'>"
                      +"<button  value ='"+item.id+"' id='updateAdjusment' class='btn btn-primary btn-sm'><i class='fa fa-pencil'></i></button>"
                      +"<button  value ='"+item.id+"' id='deleteAdjusment' class='btn btn-danger btn-sm'><i class='fa fa-ban'></i></button>"
                      "</td>"
                      emp+="</tr>";
               })              
          $("#adjustment2").empty();
          $("#adjustment2").append(emp);
          
       }
            
      }
  });
});

      $("#pdate").change(function(){
          var vl = ($("#pdate").val());
          jQuery.ajax({
            url:'query/query-payrollscript.php?cutoffdates', 
            method: 'POST',
            data: {vl:vl},
            cache: false,
            dataType: 'json',
            error: function(xhr, status, error) {
              alert(xhr.responseText);
          },
            success: function(dataResult){
              
              var dataerr=dataResult.errcode;
              var Scutoff=dataResult.cut1;
              var Ecutoff=dataResult.cut2;
            
              if(dataerr==0){
                alert("Paydate not found!")
                $( "#btngen" ).prop( "disabled", true );
                
              }
              else{              
                $('#cutOFd1').val(Scutoff);   
                $('#cutOFd2').val(Ecutoff);  
                $( "#btngen" ).prop( "disabled", false );         
              }         
            }
        });
      });

      
//       $("#addAdjusment").click(function(){
//       if( $('#adjamount').val() ) {
//           $('#adjamount').css('border-color', '');
//           //alert();
          
//         }
//         else if( $('#employeedata').val() ) {
//           $('#employeedata').css('border-color', '');
//           //alert();
          
//         }

//         if( !$('#adjamount').val() ) {
//           $('#adjamount').css('border-color', 'red');
//           alert("Amount is required!");
          
//         }
//         else if( !$('#employeedata').val() ) {
//           $('#employeedata').css('border-color', 'red');
//           alert("Employee is required!");
          
//         }
//         else{
//           $.ajax({
//             url:'query/query-payrollscript.php?addadjustment', 
//             type:'post',
//             data:{
//               emp:$('#employeedata').val(),
//               amount:$('#adjamount').val(),
//               pdate:$("#pdate").val()
//             },
//             dataType: 'json',
//             success:function(res){
//               console.log(res);
//               if(res.statuscode=="200"){
              
//               }
//               else{
//                 alert("Something went wrong!");
//               }
//             }
//           });
//         }

//     //get adjusment
//     var vl = ($("#pdate").val());
//     jQuery.ajax({
//       url:'query/query-payrollscript.php?getadjustment', 
//       method: 'POST',
//       data: {vl:vl},
//       cache: false,
//       dataType: 'json',
//       success: function(res){
//         console.log(res);
//         var resultData=res.data;          
//         var errcode = res.errcode;
//         var i=1;
//         var emp = '';
                
//                 $(resultData).each(function (index, item) {                
//                       emp+="<tr>"
//                       emp+="<td>"+ i++ +"</td>"
//                       +"<td class='idBarcode'>"+item.EmpLN+ " "+item.EmpFN+ "</td>"
//                       +"<td style='text-align:left'>"+item.Amount+"</td>"
//                       +"<td style='text-align:left'>"+item.pdate+"</td>"
//                       +"<td style='text-align:left'>"
//                       +"<button  value ='"+item.id+"' id='updateAdjusment' class='btn btn-primary btn-sm'><i class='fa fa-pencil'></i></button>"
//                       +"<button  value ='"+item.id+"' id='deleteAdjusment' class='btn btn-danger btn-sm'><i class='fa fa-ban'></i></button>"
//                       "</td>"
//                       emp+="</tr>";
//               })              
//           $("#adjustment2").empty();
//           $("#adjustment2").append(emp);
        
       
            
//       }
//     });
//   });
  $("#addAdjusment").click(function(e){

            var xc=$("#pdate").val();

      if(xc=="") {
        $("#result1").addClass("alert alert-danger offset4 span4");
        $("#result1").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Please specify Payroll Date!');
        $(".alert").show();
        
        $(".alert").css("opacity", "100%");

         $('.alert .close').on("click", function () {
            $(this).parent().slideUp(500, 0).slideUp(500);
        });
        return false;
      
      }else{
        $('#pdate').css('border-color', '');
      }

      if( $('#adjamount').val() ) {
          $('#adjamount').css('border-color', '');
          //alert();
          
        }
        else if( $('#employeedata').val() ) {
          $('#employeedata').css('border-color', '');
          //alert();
          
        }

        if( !$('#adjamount').val() ) {
          $('#adjamount').css('border-color', 'red');
          alert("Amount is required!");
          
        }
        else if( !$('#employeedata').val() ) {
          $('#employeedata').css('border-color', 'red');
          alert("Employee is required!");
          
        }
        else{
          e.preventDefault();
          $.ajaxSetup({
             headers: {
                 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
             }
         });

          $.ajax({
            url:'query/query-payrollscript.php?addadjustment', 
            type:'post',
            data:{
              emp:$('#employeedata').val(),
              amount:$('#adjamount').val(),
              pdate:$("#pdate").val()
            },
            dataType: 'json',
            success:function(res){
              console.log(res);
              if(res.statuscode=="200"){
                alert("Data Succesfully Save!");
                $("#adjamount").val("");

                
    //get adjusment
    var vl = ($("#pdate").val());
    jQuery.ajax({
      url:'query/query-payrollscript.php?getadjustment', 
      method: 'POST',
      data: {vl:vl},
      cache: false,
      dataType: 'json',
      success: function(res){
        console.log(res);
        var resultData=res.data;          
        var errcode = res.errcode;
        var i=1;
        var emp1 = '';
        $("#adjustment2").empty();
                $(resultData).each(function (index, item) {                
                      emp1+="<tr>"
                      emp1+="<td>"+ i++ +"</td>"
                      +"<td class='idBarcode'>"+item.EmpLN+ " "+item.EmpFN+ "</td>"
                      +"<td style='text-align:left'>"+item.Amount+"</td>"
                      +"<td style='text-align:left'>"+item.pdate+"</td>"
                      +"<td style='text-align:left'>"
                      +"<button  value ='"+item.id+"' id='updateAdjusment' class='btn btn-primary btn-sm'><i class='fa fa-pencil'></i></button>"
                      +"<button  value ='"+item.id+"' id='deleteAdjusment' class='btn btn-danger btn-sm'><i class='fa fa-ban'></i></button>"
                      "</td>"
                      emp1+="</tr>";
               })              
              
              $("#adjustment2").append(emp1);
        
       
            
      }
    });
              }
              else{
                alert("Something went wrong!");
              }
            }
          });
        }

  });
  
 
      $(".btnviewrp").click(function(){
    
        var vl = ($("#pdate").val());
        var filter = ($(".payrolfilater").val());
    
            var xmlhttp = new XMLHttpRequest();            
            xmlhttp.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
            document.getElementById("reportview").innerHTML = this.responseText;

            if(filter=="2"){
              $("#adjmin").html("Total Min");
              $("#adjmoney").html("Total ");            
            }else{
              $("#adjmin").html("Adjustment(min)");
              $("#adjmoney").html("Adjustment(money)");  
            }
                 // modal.style.display = "none";
           
              }
              };
            xmlhttp.open("GET", "query-searchpayroll.php?dte=" + vl +  "& filter=" + filter, true);
            xmlhttp.send();
                     
            $(".pyrlfilt").html("Payroll Attachment Summary");
            $(".prprdby").html("Dashboard System");   
            
        
      });

      $('#btnview').click(function(){
          
            var vl = ($("#pdate").val());
            var filter = ($(".payrolfilater").val());
              var xmlhttp = new XMLHttpRequest();            
              xmlhttp.onreadystatechange = function() {
              if (this.readyState == 4 && this.status == 200) {
              document.getElementById("reportview").innerHTML = this.responseText;
                  //  modal.style.display = "none";
                }
                };
                xmlhttp.open("GET", "Query-searchpaysrollmain.php?dte=" + vl +  "& filter=" + filter, true);
                              xmlhttp.send();
        $(".pyrlfilt").html("WeDo Metro Payroll Report");
      });
            
      $("#updateAdjusment").click(function(){
        alert("Update");
      });

      $(document).on('click', '#deleteAdjusment', function(e){
        var $ele = $(this).parent().parent();
        var id=$(this).val();
        jQuery.ajax({
          url:'query/query-payrollscript.php?delAdjustment', 
          method: 'POST',
          data: {id:id},
          cache: false,
          dataType: 'json',
          error: function(xhr, status, error) {
            alert(xhr.responseText);
        },
          success: function(res){         
            var errcode = res.statuscode;
           if(errcode==200){
            $ele.fadeOut().remove();
            
           }
                
          }
        });

      });

      




});

// Generate was refused (10000 EO, 10001 OB, 10002 Leave, 10003 OT, 10005 unapproved
// payroll). Query-payroll.php only returns the code, so ask payroll-pending.php who
// is holding it up and list them in the warning.
function showPendingFilings(code, paydate, dtfr, dtto) {
  var esc = function (v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  };
  var headline = {
    10000: 'Pending EO application/s for the period specified.',
    10001: 'Pending OB application/s for the period specified.',
    10002: 'Pending Leave application/s for the period specified.',
    10003: 'Pending OT application/s for the period specified.',
    10005: 'System detected pending payroll report status.'
  }[code];
  var close = '<button type="button" class="close" aria-label="close">×</button>';
  var show = function (body) {
    $("#result").addClass("alert alert-danger offset4 span4");
    $("#result").html(close + '<strong>WARNING!</strong> ' + esc(headline) +
      (code == 10005 ? '' : ' Cannot continue with generation of payroll.') + body);
    $(".alert").show();
    $(".alert").css("opacity", "100%");
    $('.alert .close').on("click", function () {
      $(this).parent().slideUp(500, 0).slideUp(500);
    });
  };
  show('<div class="pp-note">Checking who is pending…</div>');

  $.ajax({
    url: 'query/payroll-pending.php',
    type: 'post',
    dataType: 'json',
    data: { dtfr: dtfr, dtto: dtto },
    success: function (r) {
      if (!r || r.status !== 'ok') { show(''); return; }
      var html = '';
      if (code == 10005) {
        var dates = $.grep(r.unapproved_payroll || [], function (d) { return d != paydate; });
        if (dates.length) {
          html += '<div class="pp-note">Approve or remove the payroll generated for <b>' +
            $.map(dates, esc).join(', ') + '</b> first.</div>';
        }
      } else if (r.total > 0) {
        html += '<div class="pp-note">' + r.total + ' filing' + (r.total == 1 ? '' : 's') +
          ' in this cut-off still need approval:</div>';
        $.each(r.groups, function (_, g) {
          if (!g.items.length) { return; }
          html += '<div class="pp-group"><div class="pp-title">' + esc(g.label) + ' (' + g.items.length + ')</div>' +
            '<div class="wd-tablewrap"><table class="pp-table"><thead><tr><th>Employee</th><th>Date</th>' +
            '<th>Details</th><th>Waiting for</th></tr></thead><tbody>';
          $.each(g.items, function (_, it) {
            html += '<tr><td>' + esc(it.name) + '<small>' + esc(it.empid) + '</small></td>' +
              '<td>' + esc(it.date) + '</td>' +
              '<td>' + esc(it.detail) + (it.purpose ? '<small>' + esc(it.purpose) + '</small>' : '') + '</td>' +
              '<td>' + esc(it.waiting) + (it.approver ? '<small>' + esc(it.approver) + '</small>' : '') + '</td></tr>';
          });
          html += '</tbody></table></div></div>';
        });
      }
      show(html);
    },
    error: function () { show(''); }
  });
}
