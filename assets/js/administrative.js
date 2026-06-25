$(document).ready(function(){

 var alasCutoffPassed = false;

 validateTime();
   function validateTime(){

    jQuery.ajax({
        url:'query/administrative.php?validateTime',
        method: 'get',
        data:{},
        cache: false,
        dataType: 'json',
        error: function(xhr, status, error) {
        alert(xhr.responseText);
        },
        success: function(res){
            var resultData=res.errcode;
            if(resultData=='1'){
                alasCutoffPassed = true;
                $("#alassave").hide();
            }
        }
    });
   }

   // File-on-behalf: when a superior picks a team member, load their details.
   // On-behalf filing is exempt from the 8:30 AM cutoff, so re-show the submit button.
   $(document).on('change', '#target_empid', function(){
       var empid = $(this).val();
       var ownId = $("#alas_owner_id").val();

       if (empid !== ownId){
           $("#alassave").show();
       } else if (alasCutoffPassed){
           $("#alassave").hide();
       } else {
           $("#alassave").show();
       }

       $.ajax({
           url:'query/Query-alas-empinfo.php',
           type:'post',
           data:{empid: empid},
           dataType:'json',
           success: function(res){
               if(!res || !res.ok){
                   $('#modalWarning').modal('toggle');
                   $('#modalWarning .alert').html(res && res.msg ? res.msg : "Unable to load employee details.");
                   return;
               }
               $("#f_company").val(res.company);
               $("#f_dept").val(res.department);
               $("#f_designation").val(res.designation);
               $("#leavetype").html(res.leaveOptions);
               $("#lcredit").val(res.credit);
               if (typeof checkSubCategory === 'function'){
                   checkSubCategory(document.getElementById('leavetype'));
               }
           },
           error: function(xhr){
               $('#modalWarning').modal('toggle');
               $('#modalWarning .alert').html("Error loading employee details.");
           }
       });
   });


});