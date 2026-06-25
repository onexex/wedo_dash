$(document).ready(function(){ 
    
    $(document).on('click', '#updateEffecDate1', function(e){
        var id = $(this).val();   
        var dfrom = $("#efdfrom").val();
        var dto = $("#efdto").val();  
        var team_nga_nga='';
        e.preventDefault();
        $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
        });  
        jQuery.ajax({
            url:'query/scheduler-phpscript.php?updateEffectivity', 
            method: 'POST',
            data: {id:id,dfrom:dfrom,dto:dto,},
            cache: false,
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText);
                },
            success: function(res){
                console.log(res); 
                var errcode = res.errcode;
 
                if(errcode==0){                
                $("#from"+id).empty();
                $("#from"+id).append(dfrom);   
                $("#to"+id).empty();
                $("#to"+id).append(dto); 
                    alert("Data successfully Updated!");
            }
                else{
                    //do nothing
                }
            }
        });       

    }); 
    $(document).on('click', '#updateEffecDate', function(e){
        var id = $(this).val();   
        var tfrom = $("#efdfrom").val();
        var tto = $("#efdto").val();  
        e.preventDefault();
        $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
        });      
        jQuery.ajax({
        url:'query/scheduler-phpscript.php?setEffectivity', 
        method: 'POST',
        data: {id:id},
        cache: false,
        dataType: 'json',
        error: function(xhr, status, error) {
            alert(xhr.responseText);
            },
        success: function(res){
            console.log(res);  
            var resultData=res.data;          
            var errcode = res.errcode;
 
           if(errcode==0){
                $(resultData).each(function (index, x) {                                        
                    $("#efdfrom").val(x.dfrom);
                    $("#efdto").val(x.dto);
                }) 
                $("#updateEffecDate1").val(id);  
                 
           }else{
            alert("Something went wrong!");         
           }     
        }
    });
    }); 

    $(document).on('click', '#updatetimesched', function(e){
        var id = $(this).val();
        var timeid = $("#timedata").val();
        var me =$("#timedata option:selected").text();
        if(timeid=="1"){
            alert("Please choose schedule!");
        }
        else{
                $("#"+id).empty();
                $("#"+id).append(me); 
                e.preventDefault();
                $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
                });
                
                jQuery.ajax({
                url:'query/scheduler-phpscript.php?updateTimeNow', 
                method: 'POST',
                                data: {id:id,timeid:timeid,me:me},

                cache: false,
                dataType: 'json',
                error: function(xhr, status, error) {
                    alert(xhr.responseText);
                    },
                success: function(res){
                    console.log(res);  
                    var resultData=res.data;          
                    var errcode = res.errcode;
                   if(errcode==0){
                      alert("Succesfully Updated!");                         
                   }else{
                    alert("Something went wrong!");         
                   }     
                }
            });      
        }                 
    });
    
    $(document).on('click', '#updaterecord', function(e){
        var id = $(this).val();
        e.preventDefault();
        $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
        });
        jQuery.ajax({
        url:'query/scheduler-phpscript.php?setupdatesched', 
        method: 'POST',
        data: {id:id,},
        cache: false,
        dataType: 'json',
        error: function(xhr, status, error) {
            alert(xhr.responseText);
            },
        success: function(res){
            console.log(res);
            var resultData=res.data;          
            var errcode = res.errcode;
           if(errcode==0){
            var sched = '';                      
                                   
                        sched+="<option value='1'>Choose..</option>";
                        
                            $(resultData).each(function (index, x) {                                        
                            sched+="<option value="+x.WorkSchedID+">"+x.TimeFrom+ " " +x.TimeTo+ "</option>";
 
                   })  
                   $("#timedata").empty();
                   $("#timedata").append(sched); 
                   $("#updatetimesched").val(id);
                                        
           }       
        }
    });       
    });

    $(document).on('click', '#viewrecord', function(e){
        var id = $(this).val();
        e.preventDefault();
        $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
        });
        jQuery.ajax({
        url:'query/scheduler-phpscript.php?viewsched', 
        method: 'POST',
        data: {id:id,},
        cache: false,
        dataType: 'json',
        error: function(xhr, status, error) {
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
                          +"<td style='text-align:left'>"+item.Day_s+"</td>"
                          +"<td id='"+item.WID+"' style='text-align:left'>"+item.TimeFrom+ " " +item.TimeTo+ "</td>"     
                          +"<td style='text-align:left'>"   
                          
                          +"<button  value ='"+item.WID+"' id='updaterecord' data-toggle='modal' data-target='#updatetime' class='btn btn-primary btn-sm'> <i class='fa fa-pencil'></i> Update</button>" 
                          "</td>"   
                          emp+="</tr>";

                         // +"<button  value ='"+item.id+"' id='updaterecord' class='btn btn-primary btn-sm'><i class='fa fa-pencil'></i></button>"
              
                   })              
              $("#daytime").empty();
              $("#daytime").append(emp);
              
           }
           
                
        }
    });       
    });
        $(document).on('click', '#newschedreg', function(e){
                    e.preventDefault();
                    $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
                });
                jQuery.ajax({
                url:'query/scheduler-phpscript.php?loaddata', 
                method: 'POST',
                data: {},
                cache: false,
                dataType: 'json',
                success: function(res){
                    console.log(res);
                        var resultData=res.data;   
                        var schedresult=res.data2;       
                        var errcode = res.errcode;
                        var i=1;
                    if(errcode==0){
                        var emp = ''; var sched ='';                 
                        emp+="<option value='0'>Choose..</option>";
                                $(resultData).each(function (index, item) {                                        
                                    emp+="<option value="+item.EmpID+">"+item.EmpLN+ " " +item.EmpFN+ "</option>";
                            })                              
                            sched+="<option value='1'>Choose..</option>";
                            sched+="<option value='0'>Restday</option>";
                                $(schedresult).each(function (index, x) {                                        
                                sched+="<option value="+x.WorkSchedID+">"+x.TimeFrom+ " " +x.TimeTo+ "</option>";
                        })  
                        $("#Monday").empty();$("#Monday").append(sched);
                        $("#Tuesday").empty();$("#Tuesday").append(sched);
                        $("#Wednesday").empty();$("#Wednesday").append(sched);
                        $("#Thursday").empty();$("#Thursday").append(sched);
                        $("#Friday").empty();$("#Friday").append(sched);
                        $("#Saturday").empty();$("#Saturday").append(sched);
                        $("#Sunday").empty();$("#Sunday").append(sched);
                        $("#employee").empty();$("#employee").append(emp);                        
                    }               
                }
            });       
    });

    $(document).on('click', '#save', function(e){
        // validation for empty element
        var listner = 0;
        if($('#employee').val()==0){$('#lblemployee').show();listner=1;}else{$('#lblemployee').hide(); }
        if($('#dfrom').val()==0){$('#lbldfrom').show();listner=1;}else{$('#lbldfrom').hide();}
        if($('#dto').val()==0){$('#lbldto').show();listner=1;}else{$('#lbldto').hide(); }
        if($('#Monday').val()==1){$('#lblmonday').show();listner=1;}else{$('#lblmonday').hide(); }
        if($('#Tuesday').val()==1){$('#lbltuesday').show(); listner=1;}else{$('#lbltuesday').hide(); }
        if($('#Wednesday').val()==1){$('#lblwednesday').show(); listner=1;}else{$('#lblwednesday').hide(); }
        if($('#Thursday').val()==1){$('#lblthursday').show(); listner=1;}else{$('#lblthursday').hide(); }
        if($('#Friday').val()==1){$('#lblfriday').show(); listner=1;}else{$('#lblfriday').hide(); }
        if($('#Saturday').val()==1){$('#lblsaturday').show(); listner=1;}else{$('#lblsaturday').hide(); }
        if($('#Sunday').val()==1){$('#lblsunday').show(); listner=1;}else{$('#lblsunday').hide(); }

        if(listner==1){return false;}
        else{    
                 
                e.preventDefault();
                $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });
                jQuery.ajax({
                url:'query/scheduler-phpscript.php?save', 
                method: 'POST',
                data: { employee:$('#employee').val(),
                        dfrom:$('#dfrom').val(),
                        dto:$('#dto').val(),
                        monday:$('#Monday').val(),
                        tuesday:$('#Tuesday').val(),
                        wednesday:$('#Wednesday').val(),
                        thursday:$('#Thursday').val(),
                        friday:$('#Friday').val(),
                        saturday:$('#Saturday').val(),
                        sunday:$('#Sunday').val()
                    },
                cache: false,
                dataType: 'json',

                error: function(xhr, status, error) {
                    alert(xhr.responseText);
                    },
                success: function(res){
                    console.log(res);
                    document.getElementById("entersched").reset();
                                
                }
            });
        }
});


$(document).on('keyup', '#name', function(e){
        
    var query = $(this).val();
    e.preventDefault();
    $.ajaxSetup({
      headers: {
          'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
      }
  });

  jQuery.ajax({
            url:'query/scheduler-phpscript.php?search', 
          method: 'get',
          data:{query:query},
          cache: false,
          dataType: 'json',
          error: function(xhr, status, error) {;
          alert(xhr.responseText);
          },
          success: function(res){
              
             var resultData=res.data;          
             var errcode = res.errcode;
             if(query===""){
                 $("#scheduler").empty();
                }
                else{
                    if(errcode==0){
                            var emp = '';                      
                            $(resultData).each(function (index, item) {                
                                emp+="<tr>"
                                +"<td id='"+item.efids+"' class='idBarcode'>"+item.EmpLN+ " "+item.EmpFN+ "</td>"
                                +"<td id='from"+item.efids+"' style='text-align:left'>"+item.dfrom+"</td>"
                                +"<td id='to"+item.efids+"' style='text-align:left'>"+item.dto+"</td>"
                                +"<td style='margin-left:5%'>"
                                                +"<button style='margin:5px;'  value ='"+item.EFID+"' id='viewrecord' data-toggle='modal' data-target='#viewsched' class='btn btn-primary btn-sm'> <i class='fa fa-eye'></i> View</button>"
                                                +"<button  value ='"+item.efids+"' id='updateEffecDate' data-toggle='modal' data-target='#updateeffective' class='btn btn-success btn-sm'> <i class='fa fa-pencil'></i> Update Effectivity</button>"                                                                     
                                            "</td>"
                                emp+="</tr>";                               
                            })              
                    $("#scheduler").empty();
                    $("#scheduler").append(emp);
                    
                    }
            }
         }
     });
 });

});