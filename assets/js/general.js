
$(document).ready(function(){ 
  
   
    
    $(document).on('click', '#chk13', function(e){
        
        if($('input[name="chk13"]').is(':checked')){
            $('.not_13month_data').hide();
        }else{
            $('.not_13month_data').show();
        }
    });

    $(document).on('click', '#btnView', function(e){
       
        var ids = $("#selEmp").val();   
        var dfrom = $("#cutOFd1").val();
        var dto = $("#cutOFd2").val();  
      
        e.preventDefault();
        $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
        });  

        // ***************
        jQuery.ajax({
            url:'query/general-phpscript.php?viewSummary', 
            method: 'POST',
            data: {ids:ids,dfrom:dfrom, dto: dto},
            cache: false,
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText + "df");
                },
            success: function(res){
                console.log(res);
                var resultData=res.data;          
                var errcode = res.errcode;
                var total=0;
                var allowance_13month=0;
                
               if(errcode==0){
                var emp = '';                      
                        $(resultData).each(function (index, item) {     
                              total=0;
                              allowance_13month=0;
                              total=parseFloat(item.th)/12;
                              allowance_13month= total + (parseFloat(item.PYAllowance)/12);
                              emp+="<tr>"                          
                              +"<td style='text-align:right'>"+item.EmpLN+"</td>"
                              +"<td style='text-align:left'>"+item.PYBasic.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td style='text-align:left'>"+item.PYAdj.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td  class='not_13month_data' style='text-align:left'>"+item.PYOverTime.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td style='text-align:left'>"+item.PYGross.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td  class='not_13month_data' style='text-align:left'>"+item.PYSSS.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td  class='not_13month_data' style='text-align:left'>"+item.PYSSSLoan.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td  class='not_13month_data' style='text-align:left'>"+item.PYPhilHealth.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td  class='not_13month_data' style='text-align:left'>"+item.PYPagibig.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td  class='not_13month_data' style='text-align:left'>"+item.PYPILoan.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td  class='not_13month_data' style='text-align:left'>"+item.PYTaxIncome.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td  class='not_13month_data' style='text-align:left'>"+item.PYIncTax.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td  class='not_13month_data' style='text-align:left'>"+item.PYNetPay.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td style='text-align:left'>"+item.PYAllowance.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td  class='not_13month_data' style='text-align:left'>"+item.PYOtherAdj.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td  class='not_13month_data' style='text-align:left'>"+item.PYallowadj.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td  class='not_13month_data' style='text-align:left'>"+item.PYRecivable.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td style='text-align:left'>"+total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                              +"<td style='text-align:left'>"+allowance_13month.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                             
                              emp+="</tr>";
                  
                       })              
                  $("#data").empty();
                  $("#data").append(emp);
                  
               }
               
               
            }
            });  
        // ***************


    }); 

    // attachment *********************************
    $(document).on('click', '#btnViewAtt', function(e){
       
        var ids = $("#selEmpAtt").val();   
        var dfrom = $("#cutOFd1").val();
        var dto = $("#cutOFd2").val();  
      
        e.preventDefault();
        $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
        });  

        // ***************
        jQuery.ajax({
            url:'query/general-phpscript.php?viewSummaryAtt', 
            method: 'POST',
            data: {ids:ids,dfrom:dfrom, dto: dto},
            cache: false,
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText + "df");
                },
            success: function(res){
                console.log(res);
                var resultData=res.data;          
                var errcode = res.errcode;
                var total=0;
                
               if(errcode==0){
                var emp = ''; 
                var lasteid=0; 
                var total13month=0;  
                var oldid="WeDoinc-013";
                var newid=0; 
                var allowance_13month2=0; 

                    $(resultData).each(function (index, item) {  

                        total=0;
                        allowance_13month2=0; 
                        total=parseFloat(item.PYGross)/12;
                        allowance_13month2=total + (parseFloat(item.PYAllowance)/12); 
                            emp+="<tr>"                          
                                +"<td style='text-align:right'>"+item.EmpLN+"</td>"
                                +"<td style='text-align:left'>"+item.PYDate+"</td>"
                                +"<td style='text-align:left'>"+item.PYGross.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                                +"<td  class='not_13month_data' style='text-align:left'>"+item.PYAllowance.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                                +"<td style='text-align:left'>"+total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"
                                +"<td style='text-align:left'>"+allowance_13month2.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")+"</td>"

                            emp+="</tr>";

                    })              
                  $("#dataAtt").empty();
                  $("#dataAtt").append(emp);
                  
               }
               
               
            }
            });  
        // ***************


    });
});
