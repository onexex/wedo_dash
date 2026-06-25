$(document).ready(function(){
     // for settings
              //save ca
              
              function clear(){
                   
                     $("#fname").val('');
                     $("#initial").val('');
                     $("#lname").val('');
                     $("#position").val('');
                     $("#branch").val('');
                     $("#cityaddress").val('');
                     $("#otherbranch").val('');
                  
              }
        $(document).on('click', '#saveca', function(e){
            
            var cnumber= $("#caNumber").val();
            if($("#caNumber").val()){
                
            }else{alert("Invalid Input!");
                return false;}
            var id=$(this).val();
                    jQuery.ajax({
            url:'query/debitPhpScript.php?savecaaccount', 
            method: 'POST',
            data:{id:id,cnumber:cnumber},
            cache: false,
            dataType: 'json',
            error: function(xhr, status, error) {
        alert(xhr.responseText);
            },
            success: function(resultData){
                var data=resultData.data;
                var tbl='';
                var i=1;
                $(data).each(function (index, item) {                
                    tbl+="<tr>"
                              +"<td class='i'>"+i++ +"</td>"
                              +"<td class='i' id='name"+item.id+"' style='text-transform: capitalize;'>"+item.CA_Number+"</td>"
                              +"<td style='margin-left:5%'>"
                                         +"<button type='button' value ='"+item.id+"' id='dellaccount' class='btn btn-danger btn-sm'><i class='fa fa-ban'></i>  </button>"
                              "</td>"
                              tbl+="</tr>";
                 })
                  $("#tblca").empty().append(tbl);
            }
        });
        });
    //update acoount number
        $(document).on('click', '#dellaccount', function(e){
           var id=$(this).val();
           var contactid= $("#saveca").val();

            jQuery.ajax({
            url:'query/debitPhpScript.php?deleteaccounts', 
            method: 'POST',
            data:{id:id,contactid:contactid},
            cache: false,
            dataType: 'json',
            error: function(xhr, status, error) {
        alert(xhr.responseText);
            },
            success: function(resultData){
                console.log(resultData);
                var data=resultData.data;
                var tbl='';
                var i=1;
                $(data).each(function (index, item) {                
                    tbl+="<tr>"
                              +"<td class='i'>"+i++ +"</td>"
                              +"<td class='i' id='name"+item.id+"' style='text-transform: capitalize;'>"+item.CA_Number+"</td>"
                              +"<td style='margin-left:5%'>"
                                         +"<button type='button' value ='"+item.id+"' id='dellaccount' class='btn btn-danger btn-sm'><i class='fa fa-ban'></i>  </button>"
                              "</td>"
                              tbl+="</tr>";
                 })
                  $("#tblca").empty().append(tbl);
            }
        });
        });
        //load bank data to table
    $(document).on('click', '#addCA', function(e){
       var id=$(this).val();
       $("#saveca").val(id);
        jQuery.ajax({
            url:'query/debitPhpScript.php?loadaccounts', 
            method: 'POST',
            data:{id:id},
            cache: false,
            dataType: 'json',
            error: function(xhr, status, error) {
        alert(xhr.responseText);
            },
            success: function(resultData){
                var data=resultData.data;
                var tbl='';
                var i=1;
                $(data).each(function (index, item) {                
                    tbl+="<tr>"
                              +"<td class='i'>"+i++ +"</td>"
                              +"<td class='i' id='name"+item.id+"' style='text-transform: capitalize;'>"+item.CA_Number+"</td>"
                              +"<td style='margin-left:5%'>"
                                         +"<button type='button' value ='"+item.id+"' id='dellaccount' class='btn btn-danger btn-sm'><i class='fa fa-ban'></i>  </button>"
                              "</td>"
                              tbl+="</tr>";
                 })
                  $("#tblca").empty().append(tbl);
            }
        });
    });
     //copy name on click ca\
     
           $(document).on('click', '#addCA', function(e){
               var id=$(this).val();
               var name=$("#name"+id).text();
                $(".caname").empty().append("CA/SA link to:  " + name);
               
           });
               
     //btnRegisterUpdate
            $(document).on('click', '#btnRegisterUpdate', function(e){
            var id=$(this).val();
            var salutation= $("#salutationUpdate").val();
            var fname= $("#fnameUpdate").val();
            var initial= $("#initialUpdate").val();
            var lname= $("#lnameUpdate").val();
            var position= $("#positionUpdate").val();
            var branch= $("#branchUpdate").val();
            var cityaddress= $("#cityaddressUpdate").val();
            var otherbranch= $("#otherbranchUpdate").val();
            
        if(otherbranch===""){
            $("#resultUpdate").addClass("alert alert-danger offset4 span4");
            $("#resultUpdate").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Please Complete required field!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
            });
            return false;
        }

        if(cityaddress===""){
            $("#resultUpdate").addClass("alert alert-danger offset4 span4");
            $("#resultUpdate").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Please Complete required field!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
            });
            return false;
        }

        if(branch===""){
            $("#resultUpdate").addClass("alert alert-danger offset4 span4");
            $("#resultUpdate").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Please Complete required field!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
            });
            return false;
        }


        if(position===""){
            $("#resultUpdate").addClass("alert alert-danger offset4 span4");
            $("#resultUpdate").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Please Complete required field!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
            });
            return false;
        }

        if(lname===""){
            $("#resultUpdate").addClass("alert alert-danger offset4 span4");
            $("#resultUpdate").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Please Complete required field!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
            });
            return false;
        }

        if(initial===""){
            $("#resultUpdate").addClass("alert alert-danger offset4 span4");
            $("#resultUpdate").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Please Complete required field!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
            });
            return false;
        }

        if(salutation===""){
            $("#resultUpdate").addClass("alert alert-danger offset4 span4");
            $("#resultUpdate").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Please Complete required field!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
            });
            return false;
        }
        if(fname===""){
            $("#resultUpdate").addClass("alert alert-danger offset4 span4");
            $("#resultUpdate").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> FPlease Complete required field!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
            });
            return false;
        }
 
                    jQuery.ajax({
            url:'query/debitPhpScript.php?update', 
            method: 'POST',
             data:{id:id,salutation:salutation,
                fname:fname,initial:initial,lname:lname,
                position:position,branch:branch,cityaddress:cityaddress,otherbranch:otherbranch,},
            cache: false,
            dataType: 'json',
            error: function(xhr, status, error) {
        alert(xhr.responseText);
            },
            success: function(resultData){
                var data=resultData.data;
                if(data==1){
                    
                    $("#resultUpdate").addClass("alert alert-info offset4 span4");
                    $("#resultUpdate").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong>Data has been successfully Updated!');
                    $(".alert").show();
                    $(".alert").css("opacity", "100%");
                     $('.alert .close').on("click", function () {
                        $(this).parent().slideUp(500, 0).slideUp(500);
                    }); 
                   
                    $("#fnameUpdate").val('');
                    $("#initialUpdate").val('');
                    $("#lnameUpdate").val('');
                    $("#positionUpdate").val('');
                    $("#branchUpdate").val('');
                    $("#cityaddressUpdate").val('');
                    $("#otherbranchUpdate").val('');
                    
                    $("#refreshData").click();
                }
                else
                {
                    $("#resultUpdate").addClass("alert alert-info offset4 span4");
                    $("#resultUpdate").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong>Something went wrong!');
                    $(".alert").show();
                    $(".alert").css("opacity", "100%");
                     $('.alert .close').on("click", function () {
                        $(this).parent().slideUp(500, 0).slideUp(500);
                    });
                }
                   
            }
        });
    });
      
     
     //update information updateCA
        $(document).on('click', '#updateCA', function(e){
            var id=$(this).val();
                      $("#btnRegisterUpdate").val(id);
            
                    jQuery.ajax({
            url:'query/debitPhpScript.php?getdata', 
            method: 'POST',
            data:{id:id,},
            cache: false,
            dataType: 'json',
            error: function(xhr, status, error) {
        alert(xhr.responseText);
            },
            success: function(resultData){
                var data=resultData.data;
                
                $(data).each(function (index, item) {      
                $("#salutationUpdate").val(item.conSalutation);
                $("#fnameUpdate").val(item.conFName);
                $("#initialUpdate").val(item.conMInitial);
                $("#lnameUpdate").val(item.conLName);
                $("#positionUpdate").val(item.conPosition);
                $("#branchUpdate").val(item.conBranch);
                $("#cityaddressUpdate").val(item.conCity);
                $("#otherbranchUpdate").val(item.conOthers);
                })
            }
        });
    });
     
    //update deactive data
        $(document).on('click', '#delCA', function(e){
            var id=$(this).val();
                    jQuery.ajax({
            url:'query/debitPhpScript.php?delete', 
            method: 'POST',
            data:{id:id,},
            cache: false,
            dataType: 'json',
            error: function(xhr, status, error) {
        alert(xhr.responseText);
            },
            success: function(resultData){
                var data=resultData.data;
                  $("#refreshData").click();

            }
        });
        });
    
   //load bank data to table
    $(document).on('click', '#refreshData', function(e){
       
        jQuery.ajax({
            url:'query/debitPhpScript.php?loadBankInformation', 
            method: 'POST',
            data:{},
            cache: false,
            dataType: 'json',
            error: function(xhr, status, error) {
        alert(xhr.responseText);
            },
            success: function(resultData){
                var data=resultData.data;
                var tbl='';
                var i=1;
                $(data).each(function (index, item) {                
                    tbl+="<tr>"
                              +"<td class='i'>"+i++ +"</td>"
                              +"<td class='i' id='name"+item.sid+"' style='text-transform: capitalize;'>"+item.conSalutation+ " " + item.conFName+ " " + item.conMInitial+ ". " + item.conLName + " </td>"
                              +"<td style='text-align:left'>"+item.conPosition+"</td>"
                              +"<td style='text-align:left'>"+item.conBranch+"</td>"
                              +"<td style='text-align:left'>"
                                    +"<button  value ='"+item.sid+"'style='margin-right: 5px;' id='addCA' data-toggle='modal' data-target='#myModalCA' class='btn btn-primary btn-sm'><i class='fa fa-plus'></i> CA/SA </button></td>"
                              
                              +"<td style='margin-left:5%'>"
                                         +"<button  value ='"+item.sid+"'style='margin-right: 5px;' id='updateCA' data-toggle='modal' data-target='#myModalUpdate' class='btn btn-primary btn-sm'><i class='fa fa-edit'></i>  </button>"
                                        
                                         +"<button  value ='"+item.sid+"' id='delCA' class='btn btn-danger btn-sm'><i class='fa fa-ban'></i>  </button>"
                              "</td>"
                              tbl+="</tr>";
                 })
                  $("#tblBank").empty().append(tbl);
            }
        });
    });
    //register bank contact information
    $(document).on('click', '#btnRegister', function(e){
       
        var salutation= $("#salutation").val();
        var fname= $("#fname").val();
        var initial= $("#initial").val();
        var lname= $("#lname").val();
        var position= $("#position").val();
        var branch= $("#branch").val();
        var cityaddress= $("#cityaddress").val();
        var otherbranch= $("#otherbranch").val();
        
        
//         //Lowercase
// $('jqueryselector').val($(this).val().toLowerCase());
 
// //Uppercase
// $('jqueryselector').val($(this).val().toUpperCase());

        if(otherbranch===""){
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Please Complete required field!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
                 $(".alert").removeClass();
            });
            return false;
        }

        if(cityaddress===""){
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Please Complete required field!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
                 $(".alert").removeClass();
            });
            return false;
        }

        if(branch===""){
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Please Complete required field!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
                 $(".alert").removeClass();
            });
            return false;
        }


        if(position===""){
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Please Complete required field!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
                 $(".alert").removeClass();
            });
            return false;
        }

        if(lname===""){
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Please Complete required field!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
                 $(".alert").removeClass();
            });
            return false;
        }

        if(initial===""){
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Please Complete required field!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
                 $(".alert").removeClass();
            });
            return false;
        }

        if(salutation===""){
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Please Complete required field!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
                 $(".alert").removeClass();
            });
            return false;
        }
        if(fname===""){
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> FPlease Complete required field!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
                 $(".alert").removeClass();
            });
            return false;
        }

        jQuery.ajax({
            url:'query/debitPhpScript.php?register', 
            method: 'POST',
            data:{salutation:salutation,
                fname:fname,initial:initial,lname:lname,
                position:position,branch:branch,cityaddress:cityaddress,otherbranch:otherbranch,},
            cache: false,
            dataType: 'json',
            error: function(xhr, status, error) {
            alert(xhr.responseText);
            },
            success: function(res){
            var dataResult=res.inserterr;
            
            if (dataResult==1){
                    $("#result").addClass("alert alert-success offset4 span4");
                    $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING! </strong> Successfuly save!!');
                    $(".alert").show();
                     $(".alert").css("opacity", "100%");
                    $('.alert .close').on("click", function () {
                    $(this).parent().slideUp(500, 0).slideUp(500);
                     $(".alert").removeClass();
            });
            clear();
            $("#refreshData").click();
            }
            else{
                $("#result").addClass("alert alert-danger offset4 span4");
                    $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING! </strong> Something went wrong!');
                    $(".alert").show();
                     $(".alert").css("opacity", "100%");
                    $('.alert .close').on("click", function () {
                    $(this).parent().slideUp(500, 0).slideUp(500);
                     $(".alert").removeClass();
            });
            }
            }
        });
      
    }); 
    $("#toprint").toggle();
    $("#toprint1").toggle();
    
    $(document).on('click', '#doc1', function(e){
        //$("#toprint").toggle();
        $("#toprint1").css("display" , "none");
        $("#toprint").css("display" , "block");
      
    });
    $(document).on('change', '#cainfo', function(e){
        //$("#toprint").toggle();
        var selText=$( "#cainfo option:selected" ).text();
        $("#ca").empty().append(selText);
        
    });
    

    $(document).on('change', '#bankinfo', function(e){
        var bankinid= $(this).val();
        jQuery.ajax({
            url:'query/debitPhpScript.php?loadcainfo', 
            method: 'POST',
            data:{ bankinid:bankinid},
            cache: false,
            dataType: 'json',
            error: function(xhr, status, error) {
            alert(xhr.responseText);
            },
            success: function(resultData){
                console.log(resultData);
                var cadata=resultData.cainfo;
                var cainfo='';
                var fname='';
                var branch='';
                var pos='';
                var city='';
                var others='';
                var lname='';
                var salut='';
                cainfo+="<option value='-'>-</option>"
             $(cadata).each(function (index, row) {                
                cainfo+="<option value="+row.id+">"+row.CA_Number+"</option>";    
                 salut=row.conSalutation;
                fname= salut + " " + row.conFName + " " + row.conMInitial + " " +row.conLName;
                branch=row.conBranch;
                pos=row.conPosition;
                city=row.conCity;
                others=row.conOthers;
                lname=salut + " " + row.conLName;
               
         })     
                $("#cainfo").empty().append(cainfo);
                $("#conName").empty().append( fname);
                $("#conPosition").empty().append(pos);
                $("#conBranch").empty().append(branch);
                $("#conCity").empty().append(city);
                $("#conDear").empty().append(lname);
                $("#bank").empty().append(others);
                $("#bank1").empty().append(others);
                //$("#conSalut").empty().append(salut);
    
            }
        });
      
    });
    
  
    $(document).on('click', '#doc2', function(e){
        $("#toprint").css("display" , "none");
        $("#toprint1").css("display" , "block");
        var rldt= $("#pdates").val();

        jQuery.ajax({
            url:'query/debitPhpScript.php?gettotalpr', 
            method: 'POST',
            data:{rldt:rldt},
            cache: false,
            dataType: 'json',
            error: function(xhr, status, error) {
            alert(xhr.responseText);
            },
            success: function(res){
            // console.log(res.totalPRSUM);    
            var resultData=res.totalPRSUM;   

          $("#wordsInPr").empty();
          $("#wordsInPr").append(resultData);
            }
        });

    });

    $(document).on('click', '#loadPayCardInfo', function(e){
          var pdates=$("#pdates").val();
          
          if(pdates==""){

            $("#result").addClass("alert alert-info offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>WARNING!</strong> Invalid Inclusive date!');
            $(".alert").show();           
            $(".alert").css("opacity", "100%");
             $('.alert .close').on("click", function () {
                $(this).parent().slideUp(500, 0).slideUp(500);
            });
            document.getElementById("btngen").disabled = false;
          }         
    });
    
    $(document).on('change', '#dtpReleaseDate', function(e){ //capture the change event on date picker
        var realeaseDate=$(this).val(); //get the value of datepicker
        $(".release").empty();          //clear the element 
        $(".release").append(realeaseDate); //set the value to the element
        var rldt= $("#pdates").val();

        jQuery.ajax({
            url:'query/debitPhpScript.php?loadPaydata', 
            method: 'POST',
            data:{rldt:rldt},
            cache: false,
            dataType: 'json',
            error: function(xhr, status, error) {
            alert(xhr.responseText);
            },
            success: function(res){
                // console.log(res);
                var resultData=res.empData; 
                var sum =res.sum;   
                var emp = ''; 
                var i=1;   
                             
                $(resultData).each(function (index, item) {    
                     var x= parseFloat(item.PYRecivable);
                      x=x.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                      emp+="<tr>"
                      emp+="<td>"+ i++ +"</td>"
                      +"<td class='idBarcode'>"+item.EmpLN+ ", "+item.EmpFN+" "+item.EmpMN+ "</td>"
                      +"<td class='idBarcode'>"+item.card_number+ "</td>"
                      +"<td class='idBarcode'>"+x+ "</td>"
                    //   +"<td class='idBarcode'>-</td>"
                      emp+="</tr>";
                    
               })      
               emp+="<tr>"
               emp+="<td></td>"
               +"<td class='idBarcode'></td>"
               +"<td class='idBarcode'>Total:</td>"
               +"<td class='idBarcode'>"+sum+"</td>"
               +"<td class='idBarcode'></td>"
               emp+="</tr>";
               
          $("#tblPaydata").empty();
          $("#tblPaydata").append(emp);
            }
        });
      
    });
    
    

  jQuery.ajax({
    url:'query/debitPhpScript.php?loadpaydates', 
    method: 'POST',
    data:{},
    cache: false,
    dataType: 'json',
    error: function(xhr, status, error) {
   alert(xhr.responseText);
    },
    success: function(resultData){
        
        console.log(resultData);
        var res = resultData.paydate;
        var bankinfo='';
        var bankdata=resultData.bankinfo;
        var emp='';
        $(res).each(function (index, row) {                
            emp+="<option value="+row.PYDate+">"+row.PYDate+"</option>"
     })     
             $("#pdates").empty();
        $("#pdates").append(emp);
        
     bankinfo+="<option value='-'>-</option>"
     $(bankdata).each(function (index, row) {                
        bankinfo+="<option value="+row.sid+">"+row.conFName+ " " +row.conLName+"</option>";
 })     
   
        $("#bankinfo").empty();
        $("#bankinfo").append(bankinfo);

    }
});

 });