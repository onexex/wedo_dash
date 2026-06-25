$(document).ready(function(){
   // viewrecord
   $(document).on('click', '#viewrecord', function(e){
        
    var id = $(this).val();
    e.preventDefault();
    $.ajaxSetup({
      headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
  });

  jQuery.ajax({
        url:'query/ams-phpscript.php?amview', 
        method: 'get',
        data:{id:id},
        cache: false,
        dataType: 'json',
        error: function(xhr, status, error) {
        alert(xhr.responseText);
        },
        success: function(res){
            // console.log(res);
            var resultData=res.data;
            $("#update").val(id);
            $(resultData).each(function (index, item) {               
                $("#fname1").val(item.fname);
                $("#lname1").val(item.lname);
                $("#pos1").val(item.pos);
                $("#dfrom1").val(item.empdatesfrom);
                $("#dto1").val(item.empdatesto);
                $("#status1").val(item.employmentstatus);
                $("#clerance1").val(item.clearance);
                $("#reason1").val(item.reasonforleaving);
                $("#derogatory1").val(item.derogatoryrecords);
                $("#salary1").val(item.salary);
                $("#resignation1").val(item.pedngingresignation);
                $("#addrem1").val(item.addremarks);
                $("#addver1").val(item.verifiedby);    
            }) 
        }
    });
  
});




  $(document).on('click', '#viewData', function(e){
        

       e.preventDefault();
       $.ajaxSetup({
         headers: {
             'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
         }
     });

     jQuery.ajax({
             url:'query/ams-phpscript.php?viewall', 
             method: 'get',
             data:{ },
             cache: false,
             dataType: 'json',
             error: function(xhr, status, error) {;
             alert(xhr.responseText);
             },
             success: function(res){
                 console.log(res);
                var resultData=res.data;          
                var errcode = res.errcode;
           
               if(errcode==0){
                var emp = '';                      
                        $(resultData).each(function (index, item) {                
                              emp+="<tr>"
                              +"<td class='idBarcode'>"+item.lname+ ", "+item.fname+ "</td>"
                              +"<td style='text-align:left'>"+item.pos+"</td>"
                              +"<td style='text-align:left'>"+item.employmentstatus+"</td>"
                                +"<td style='text-align:left'>"+item.EmpLN+"</td>"
                              +"<td class='hide_cell' style='margin-left:5%'>"
                                         +"<button  value ='"+item.id+"' id='viewrecord' data-toggle='modal' data-target='#amsshow' class='btn btn-primary btn-sm'><i class='fa fa-eye'></i> View</button>"
                              "</td>"
                              emp+="</tr>";

                             // +"<button  value ='"+item.id+"' id='updaterecord' class='btn btn-primary btn-sm'><i class='fa fa-pencil'></i></button>"
                  
                       })              
                  $("#amstable").empty();
                  $("#amstable").append(emp);
                  
               }
               
            }
        });
    });
    
  

    $(document).on('keyup', '#amssearch', function(e){
        
       var query = $(this).val();
       e.preventDefault();
       $.ajaxSetup({
         headers: {
             'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
         }
     });

     jQuery.ajax({
             url:'query/ams-phpscript.php?amssearch', 
             method: 'get',
             data:{query:query},
             cache: false,
             dataType: 'json',
             error: function(xhr, status, error) {;
             alert(xhr.responseText);
             },
             success: function(res){
                 console.log(res);
                var resultData=res.data;          
                var errcode = res.errcode;
                if(query===""){
                    $("#amstable").empty();
                }else{
                   if(errcode==0){
                        var emp = '';                      
                            $(resultData).each(function (index, item) {                
                                  emp+="<tr>"
                                  +"<td class='idBarcode'>"+item.lname+ " ,"+item.fname+ "</td>"
                                  +"<td style='text-align:left'>"+item.pos+"</td>"
                                  +"<td style='text-align:left'>"+item.employmentstatus+"</td>"
                                  +"<td style='text-align:left'>"+item.EmpLN+"</td>"
                                  +"<td style='margin-left:5%'>"
                                             +"<button  value ='"+item.id+"' id='viewrecord' data-toggle='modal' data-target='#amsshow' class='btn btn-primary btn-sm'><i class='fa fa-eye'></i> View</button>"
                                  "</td>"
                                  emp+="</tr>";
    
                                 // +"<button  value ='"+item.id+"' id='updaterecord' class='btn btn-primary btn-sm'><i class='fa fa-pencil'></i></button>"
                      
                           })              
                          $("#amstable").empty();
                          $("#amstable").append(emp);
                      
                   }
               }
            }
        });
    });

    //save data
    $(document).on('click', '#save', function(e){
        
       var listner = 0;
       
        if($('#fname').val()==""){
            $('#lblfname').show();
           
           listner=1;
        }
        else{
           $('#lblfname').hide();
           listner=0;
        }
        if($('#lname').val()==""){
            $('#lbllname').show();
           listner=1;
        } else{
           $('#lbllname').hide();
           listner=0;
        }        
        if($('#pos').val()=="Choose..."){
            $('#lblpos').show();
           listner=1;
        } else{
           $('#lblpos').hide();
           listner=0;
        }        
        
    if(listner==1)
    {alert();
        return false;
    }
        e.preventDefault();
        $.ajaxSetup({
          headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          }
      });
 
      jQuery.ajax({
              url:'query/ams-phpscript.php?save', 
              method: 'get',
              data:{
                  fname:$("#fname").val(),
                  lname:$("#lname").val(),
                  empfrom:$("#dfrom").val(),
                  empto:$("#dto").val(),
                  stat:$("#status").val(),
                  clearance:$("#clerance").val(),
                  reason:$("#reason").val(),
                  derogatory:$("#derogatory").val(),
                  salary:$("#salary").val(),
                  resignation:$("#resignation").val(),
                  addrem:$("#addrem").val(),
                  addver:$("#addver").val(),
                  pos:$("#pos").val()

                },
              cache: false,
              dataType: 'json',
              error: function(xhr, status, error) {
              alert(xhr.responseText);
              },
              success: function(dataResult){
                  var dataerr=dataResult.errcode;
                  if(dataerr==0){
                    $("#result").addClass("alert alert-success offset4 span4");
                    $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Success!</strong> Data was save successfully!');
                    $(".alert").show();
                    
                    $(".alert").css("opacity", "100%");

                     $('.alert .close').on("click", function () {
                        $(this).parent().slideUp(500, 0).slideUp(500);
                    });
                     $('#amsid')[0].reset();
                    
                  }
                  else{              
                    $("#result").addClass("alert alert-info offset4 span4");
                    $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong>Someting went wrong!');
                    $(".alert").show();
                    
                    $(".alert").css("opacity", "100%");
                     $('.alert .close').on("click", function () {
                        $(this).parent().slideUp(500, 0).slideUp(500);
                    });
                  }        
             }
         });
     });
    //update data
    $(document).on('click', '#update', function(e){
        
             var listner = 0;
       
        if($('#fname1').val()==""){
            $('#lblfname1').show();
           
           listner=1;
        }
        else{
           $('#lblfname1').hide();
           listner=0;
        }
        if($('#lname1').val()==""){
            $('#lbllname1').show();
           listner=1;
        } else{
           $('#lbllname1').hide();
           listner=0;
        }        
        if($('#pos1').val()=="Choose..."){
            $('#lblpos1').show();
           listner=1;
        } else{
           $('#lblpos1').hide();
           listner=0;
        }        
        
    if(listner==1)
    {
        return false;
    }
       
        var id = $(this).val();
        e.preventDefault();
        $.ajaxSetup({
          headers: {
              'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
          }
      });
 
      jQuery.ajax({
              url:'query/ams-phpscript.php?update', 
              method: 'get',
              data:{id:id,
                  fname:$("#fname1").val(),
                  lname:$("#lname1").val(),
                  empfrom:$("#dfrom1").val(),
                  empto:$("#dto1").val(),
                  stat:$("#status1").val(),
                  clearance:$("#clerance1").val(),
                  reason:$("#reason1").val(),
                  derogatory:$("#derogatory1").val(),
                  salary:$("#salary1").val(),
                  resignation:$("#resignation1").val(),
                  addrem:$("#addrem1").val(),
                  addver:$("#addver1").val(),
                  pos:$("#pos1").val()

                },
              cache: false,
              dataType: 'json',
              error: function(xhr, status, error) {
              alert(xhr.responseText);
              },
              success: function(dataResult){
                  console.log(dataResult);
                  var dataerr=dataResult.errcode;
                //   alert(dataerr);   
                  if(dataerr==0){
                    $("#result1").addClass("alert alert-success offset4 span4");
                    $("#result1").html('<button type="button" class="close" aria-label="close">×</button><strong>Success!</strong> Data was save successfully!');
                    $(".alert").show();
                    
                    $(".alert").css("opacity", "100%");

                     $('.alert .close').on("click", function () {
                        $(this).parent().slideUp(500, 0).slideUp(500);
                    });

                    var query = $("#amssearch").val();
  
                    e.preventDefault();
                    $.ajaxSetup({
                      headers: {
                          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                      }
                  });
                     $('#amsidu')[0].reset();
             
                  jQuery.ajax({
                          url:'query/ams-phpscript.php?amssearch', 
                          method: 'get',
                          data:{query:query},
                          cache: false,
                          dataType: 'json',
                          error: function(xhr, status, error) {;
                          alert(xhr.responseText);
                          },
                          success: function(res){
                              console.log(res);
                             var resultData=res.data;          
                             var errcode = res.errcode;
                             if(query===""){
                                 $("#amstable").empty();
                                }
                                else{
                            if(errcode==0){
                             var emp = '';                      
                                     $(resultData).each(function (index, item) {                
                                           emp+="<tr>"
                                           +"<td class='idBarcode'>"+item.lname+ ","+item.fname+ "</td>"
                                           +"<td style='text-align:left'>"+item.pos+"</td>"
                                           +"<td style='text-align:left'>"+item.employmentstatus+"</td>"
                                                     +"<td style='text-align:left'>"+item.EmpLN+"</td>"
                                           +"<td style='margin-left:5%'>"
                                                      +"<button  value ='"+item.id+"' id='viewrecord' data-toggle='modal' data-target='#amsshow' class='btn btn-primary btn-sm'><i class='fa fa-eye'></i> View</button>"
                                           "</td>"
                                           emp+="</tr>";
             
                                          // +"<button  value ='"+item.id+"' id='updaterecord' class='btn btn-primary btn-sm'><i class='fa fa-pencil'></i></button>"
                               
                                    })              
                               $("#amstable").empty();
                               $("#amstable").append(emp);
                               
                            }
                            }
                         }
                     });
                    
                  }
                  else{   
                              
                    $("#result1").addClass("alert alert-info offset4 span4");
                    $("#result1").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong>Someting went wrong!');
                    $(".alert").show();
                    
                    $(".alert").css("opacity", "100%");
                     $('.alert .close').on("click", function () {
                        $(this).parent().slideUp(500, 0).slideUp(500);
                    });
                  }        
             }
         });
     });

});