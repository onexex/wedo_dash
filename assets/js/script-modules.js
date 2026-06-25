$(document).ready(function(){
    
    $(document).on("click", "#delot", function(e) {
        var id=$(this).val();
        var ele= $(this).closest('tr');
        if(confirm("Are you sure?"))
        {
            $.ajax({
                url: 'query/ot-phpscript.php?delupdateot',
                type: "post",
                cache: false,
                data: {
                    id: id,
                },
                dataType: 'json',
                error: function(xhr, status, error) {
                    alert(xhr.responseText);
                },
                success: function(dataResult) {
                    ele.remove();
                    //alert(id);
                }
            });
        
        }else{
            e.preventDefault();
        }
    });
    
    $(document).on("click", "#saveot", function() {


        if (!$("#otpurpose").val()) {
          
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Message! </strong> Input Purpose!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
            $('.alert .close').on("click", function() {
                $(this).parent().slideUp(500, 0).slideUp(500);
                $(".alert").removeClass();
            });
    
            window.setTimeout(function() {
                $(".alert").fadeTo(500, 0).slideUp(500, function() {
                    $(this).hide();
                    $(".alert").removeClass();
                });
            }, 2000);
        }else if (!$(".datefrom").val()) {

            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Message! </strong> Please Select OT Date From!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
            $('.alert .close').on("click", function() {
                $(this).parent().slideUp(500, 0).slideUp(500);
                $(".alert").removeClass();
            });
            
            window.setTimeout(function() {
                $(".alert").fadeTo(500, 0).slideUp(500, function() {
                    $(this).hide();
                    $(".alert").removeClass();
                });
            }, 2000);

        } else if (!$(".dateto").val()) {
        
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Message! </strong> Please Select OT Date To!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
            $('.alert .close').on("click", function() {
                $(this).parent().slideUp(500, 0).slideUp(500);
                $(".alert").removeClass();
            });
            
            window.setTimeout(function() {
                $(".alert").fadeTo(500, 0).slideUp(500, function() {
                    $(this).hide();
                    $(".alert").removeClass();
                });
            }, 2000);
        } else if (!$(".timefrom").val()) {
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Message! </strong> Please Select OT Time From!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
            $('.alert .close').on("click", function() {
                $(this).parent().slideUp(500, 0).slideUp(500);
                $(".alert").removeClass();
            });
            
            window.setTimeout(function() {
                $(".alert").fadeTo(500, 0).slideUp(500, function() {
                    $(this).hide();
                    $(".alert").removeClass();
                });
            }, 2000);
        } else if (!$(".timeto").val()) {
        
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Message! </strong> Please Select OT Time To!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
            $('.alert .close').on("click", function() {
                $(this).parent().slideUp(500, 0).slideUp(500);
                $(".alert").removeClass();
            });
            
            window.setTimeout(function() {
                $(".alert").fadeTo(500, 0).slideUp(500, function() {
                    $(this).hide();
                    $(".alert").removeClass();
                });
            }, 2000);
        } else if ($(".dateto").val() < $(".datefrom").val()) {
        
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Message! </strong> Invalid Inclusive Date!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
            $('.alert .close').on("click", function() {
            $(this).parent().slideUp(500, 0).slideUp(500);
                $(".alert").removeClass();
            });
            
            window.setTimeout(function() {
                $(".alert").fadeTo(500, 0).slideUp(500, function() {
                  $(this).hide();
                  $(".alert").removeClass();
                });
            }, 2000);
        } else if (($(".dateto").val() == $(".datefrom").val()) && ($(".timeto").val() == $(".timefrom").val())) {
        
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Message! </strong> Invalid Inclusive OT Time!');
            $(".alert").show();
            $(".alert").css("opacity", "100%");
            $('.alert .close').on("click", function() {
                $(this).parent().slideUp(500, 0).slideUp(500);
                $(".alert").removeClass();
            });
            
            window.setTimeout(function() {
                $(".alert").fadeTo(500, 0).slideUp(500, function() {
                    $(this).hide();
                    $(".alert").removeClass();
                });
            }, 2000);
        } 
        
        // else if(($(".timefrom").val() > $(".timeto").val())){
        //     $("#result").addClass("alert alert-danger offset4 span4");
        //     $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Message! </strong> Invalid Inclusive OT Time!');
        //     $(".alert").show();
        //     $(".alert").css("opacity", "100%");
        //     $('.alert .close').on("click", function() {
        //         $(this).parent().slideUp(500, 0).slideUp(500);
        //         $(".alert").removeClass();
        //     });
            
        //     window.setTimeout(function() {
        //         $(".alert").fadeTo(500, 0).slideUp(500, function() {
        //             $(this).hide();
        //             $(".alert").removeClass();
        //         });
        //     }, 2000);
        // }
        //   else if ($(".dateto").val()<$(".fdate").val()){
        //   $('#modalWarning').modal('toggle');
        //   $('#modalWarning .alert').html("System not accepting late filing ! "); 
        // }
        else {
        
            var data = $("#otdata").serialize();
            
            $.ajax({
                url: 'query/Query-insertot.php',
                type: 'post',
                data: data,
                
                success: function(res) {
                
                    $("#result").addClass("alert alert-warning offset4 span4");
                    $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Message! </strong>' + res);
                    $(".alert").show();
                    $(".alert").css("opacity", "100%");
                    $('.alert .close').on("click", function() {
                        $(this).parent().slideUp(500, 0).slideUp(500);
                        $(".alert").removeClass();
                    });
                    
                    window.setTimeout(function() {
                        $(".alert").fadeTo(500, 0).slideUp(500, function() {
                            $(this).hide();
                            $(".alert").removeClass();
                        });
                    }, 7000);
                    
                    var xmlhttp = new XMLHttpRequest();
                    //$("#tbot").empty();
                    xmlhttp.onreadystatechange = function() {
                        if (this.readyState == 4 && this.status == 200) {
                            document.getElementById("tbot").innerHTML = this.responseText;
                        }
                    };
                    xmlhttp.open("GET", "query/Query-otview.php", true);
                    xmlhttp.send();
                    //document.getElementById("otdata").reset();
                    //$('#newform').modal('hide');
                }
            });
        }
    });

    $("#empname").change(function(){
        var idemp = $(this).val();
        $("#empcomp").val("Loading data...");
        $("#empdep").val("Loading data...");
        $("#empdesig").val("Loading data...");
        $.ajax({
            url:'query/Query-SendtoOB.php?id=' + idemp, 
            type:'post',
            success:function(xx){
                $("#empinfo").empty();
                $("#empinfo").append(xx);
            }
        });
    });
    
    $(".btnsendtoob").click(function(){
        if ($("#obdf").val()==$("#obdt").val()){
            if ($("#depart").val()>=$("#return").val()){
                $('#modalWarning').modal('toggle');
                $('#modalWarning .alert').html("Invalid Inclusive Time ! "); 
                return false;
            }
        }
        
        if ($("#empname").val()===""){
            $('#modalWarning').modal('toggle');
            $('#modalWarning .alert h5').html("Please Fill up Required Data!");
            $("#empname").css("border","1px solid red");
        }else  if ($("#obdt").val()==""){
            $('#modalWarning').modal('toggle');
            $('#modalWarning .alert h5').html("Please Fill up Required Data!");
            $("#obdt").css("border","1px solid red");
        }else  if ($("#obdf").val()==""){
            $('#modalWarning').modal('toggle');
            $('#modalWarning .alert h5').html("Please Fill up Required Data!");
            $("#obdf").css("border","1px solid red");
        }else  if ($("#itfrom").val()==""){
            $('#modalWarning').modal('toggle');
            $('#modalWarning .alert h5').html("Please Fill up Required Data!");
            $("#itfrom").css("border","1px solid red");
        }else  if ($("#itto").val()==""){
            $('#modalWarning').modal('toggle');
            $('#modalWarning .alert h5').html("Please Fill up Required Data!");
            $("#itfrom").css("border","1px solid red");
        }else  if ($("#emppurpose").val()==""){
            $('#modalWarning').modal('toggle');
            $('#modalWarning .alert h5').html("Please Fill up Required Data!");
            $("#emppurpose").css("border","1px solid red");
        }else  if ($("#depart").val()==""){
            $('#modalWarning').modal('toggle');
            $('#modalWarning .alert h5').html("Please Fill up Required Data!");
            $("#depart").css("border","1px solid red");
        }else  if ($("#return").val()==""){
            $('#modalWarning').modal('toggle');
            $('#modalWarning .alert h5').html("Please Fill up Required Data!");
            $("#return").css("border","1px solid red");
        }else{
            var data = $(".frmsendtoob").serialize();
            $.ajax({
                url:'query/Query-insertsendtoob.php', 
                type:'post',
                data: data,
                success:function(xx){
                
                    if (xx==2){
                        $('#modalWarning').modal('toggle');
                        $('#modalWarning .alert h5').html("Cant file ! Employee already have an OB on this Inclusive Date Time ! ");
                        $("#return").css("border","1px solid red");
                        return;
                    }else if (xx==3){
                        $('#modalWarning').modal('toggle');
                        $('#modalWarning .alert h5').html("Cant file ! Employee Inclusive Date Time is not within working time !  ");
                        $("#return").css("border","1px solid red");
                        return;
                    }else if (xx==4){
                        $('#modalWarning').modal('toggle');
                        $('#modalWarning .alert h5').html("The OB application must be completed on or before 8:30 AM.");
                        $("#return").css("border","1px solid red");
                        return;
                    }else{
                        $('#modalSuccess .alert').html("Succesfully Saved");   
                    }
                    
                    var xmlhttp = new XMLHttpRequest();
                    $("#tbob").empty();
                    xmlhttp.onreadystatechange = function() {
                        if (this.readyState == 4 && this.status == 200) {
                            document.getElementById("tbob").innerHTML = this.responseText;
                        }
                    };
                    xmlhttp.open("GET", "query/Query-sendtoobsearch.php", true);
                    xmlhttp.send();
                    $('#modalSuccess').modal('toggle');
                    // $('#modalSuccess .alert').html("Succesfully Saved");    
                    $("#obdt").val("");
                    $("#obdf").val("");
                    $("#itfrom").val("");
                    $("#itto").val("");
                    $("#emppurpose").val("");
                    $("#empname").val("");
                    $("#return").val("");
                    $("#depart").val("");
                    $(".frmsendtoob input").css("border","1px solid #ced4da");
                    $(".frmsendtoob").modal('hide');
                    document.getElementById("frmsendtoob").reset();
                }
            });
        }
    });
    
        // 9-11
    $("#leavetype option[value=33]").hide();
    $('#leavetype').val("35");
    
    // $("#leavetype").children('option').show();

    $("#leavepay").change(function(){
    
        if($(this).val()==0){
            $('#leavetype').val("33");
            // $('#leavetype').attr('disabled', true);
            $("#leavetype").children('option').hide();
            $("#leavetype").children("option[value^=" + $("#leavetype").val("33") + "]").show();
        }else{
            $('#leavetype').val("35");
            $("#leavetype").children('option').show();
            $("#leavetype option[value=33]").hide();
            // $('#leavetype').attr('disabled', false);
        }
    });

    //9-11
    $("#lenddate").change(function () {
        var d = new Date();
        var month = d.getMonth() + 1;
        var day = d.getDate();

        if ($("#lstarts").val() > $("#lenddate").val()) {
        $("#modalWarning").modal("toggle");
        $("#modalWarning .alert").html("Invalid Inclusive Dates");
        // $("#lenddate").val($("#lstarts").val());
        $(".dura").val("0");
        $("#exampleCheck1").prop("checked", false);
        $("#Ltimefrom").attr("readonly", true);
        $("#Ltimeto").attr("readonly", true);
        } else {
        $("#exampleCheck1").prop("checked", false);
        $("#Ltimefrom").attr("readonly", true);
        $("#Ltimeto").attr("readonly", true);
        var ds = $("#lstarts").val();
        var de = $(this).val();
        var ltype = $("#leavetype").val(); //2-12-2024
        if (!ds) {
            $(".dura").val("0");
        } else {
            $.ajax({
            url: "query/Query-alascalculateduration.php",
            type: "post",
            data: { dtst: ds, dtend: de, ltype: ltype }, //2-12-2024
            success: function (data) {
                $(".dura").val(data);
            },
            });
        }
        }
    });

    $("#lstarts").change(function () {
        $("#exampleCheck1").prop("checked", false);
        $("#Ltimefrom").attr("readonly", true);
        $("#Ltimeto").attr("readonly", true);
        if (
        $("#lstarts").val() > $("#lenddate").val() &&
        $("#lenddate").val() !== ""
        ) {
        // $("#lenddate").val($("#lstarts").val());
        $(".dura").val("0");
        } else {
        var ds = $(this).val();
        var de = $("#lenddate").val();
        var ltype = $("#leavetype").val(); //2-12-2024
        if (!de) {
            $(".dura").val("0");
        } else {
            $.ajax({
            url: "query/Query-alascalculateduration.php",
            type: "post",
            data: { dtst: ds, dtend: de, ltype: ltype }, //2-12-2024
            success: function (data) {
                $(".dura").val(data);
            },
            });
        }
        }
    });
    
    $("#Ltimeto").change(function(){
        var diff = ( new Date("1970-1-1 " + $("#Ltimeto").val()) - new Date("1970-1-1 " + $("#Ltimefrom").val()) ) / 1000 / 60 / 60;
        $(".dura").val(diff);
    });
     
    $("#Ltimefrom").change(function(){
        var diff = ( new Date("1970-1-1 " + $("#Ltimeto").val()) - new Date("1970-1-1 " + $("#Ltimefrom").val()) ) / 1000 / 60 / 60;
        $(".dura").val(diff);
    });
     
    $("#exampleCheck1").change(function(){
        if ($(this).is(':checked')) {
            if ($("#lstarts").val()==$("#lenddate").val()){
            
                $(".dur-text").text("Duration Time:");
                $(".dura").val(".5");
                var ds = $("#lstarts").val();
                var de = $("#lenddate").val();
                $.ajax({
                    url:'query/Query-alascalculateduration.php', 
                    type:'post',
                    data:{dtst : ds , dtend : de },
                    
                    success:function(data){
                        if (data>=1){
                            $("#Ltimefrom").attr('readonly', false);
                            $("#Ltimeto").attr('readonly', false);
                        }else{
                            $('#modalWarning').modal('toggle');
                            $('#modalWarning .alert').html("Cant File in restday");
                            $(this).prop("checked", false);
                        }
                    }
                });
            }
            else{
                $(".dur-text").text("Duration Days:");
                $('#modalWarning').modal('toggle');
                $('#modalWarning .alert').html("Invalid Inclusive Date for Half Day");
                $(this).prop("checked", false);
            }
        
        }else{
            $(".dur-text").text("Duration Days:");
            $("#Ltimefrom").attr('readonly', true);
            $("#Ltimeto").attr('readonly', true);
            var ds = $("#lstarts").val();
            var de = $("#lenddate").val();
            $.ajax({
                url:'query/Query-alascalculateduration.php', 
                type:'post',
                data:{dtst : ds , dtend : de },
                success:function(data){
                    $(".dura").val(data); 
                    $("#Ltimefrom").val("08:00");
                    $("#Ltimeto").val("19:00");
                }
            });
        }
    });

    $("#alassave").click(function(){
        
        if ($("#exampleCheck1").is(':checked')){
            if ($("#Ltimefrom").val()>$("#Ltimeto").val()){
                $('#modalWarning').modal('toggle');
                $('#modalWarning .alert').html("Invalid Inclusive Time");
                return;
            }else{}
        }
        if (!$("#leavetype").val()){
            $('#modalWarning').modal('toggle');
            $('#modalWarning .alert').html(" Please select Leave Type");
        }else if($("#lstarts").val()>$("#lenddate").val()){
            $('#modalWarning').modal('toggle');
            $('#modalWarning .alert').html("Invalid Inclusive Dates");
        }else if(!$("#purposeofleave").val()){
            $('#modalWarning').modal('toggle');
            $('#modalWarning .alert').html("  Please Input Purpose");
        }else if($("#leavedur").val()=="0"){
            $('#modalWarning').modal('toggle');
            $('#modalWarning .alert').html("You cant leave with 0 Duration.");
        }else{
            var data=$("#alas_data").serialize();
            $.ajax({
                url:'query/Query-insertalas.php', 
                type:'post',
                data:data,
                
                success:function(data){
                
                    if (data==1){
                        $("#newform").modal('hide');
                        var xmlhttp = new XMLHttpRequest();
                        $("#tbalas").empty();
                        xmlhttp.onreadystatechange = function() {
                            if (this.readyState == 4 && this.status == 200) {
                                document.getElementById("tbalas").innerHTML = this.responseText;
                            }
                        };
                        xmlhttp.open("GET", "query/Query-Viewalas.php", true);
                        xmlhttp.send();
                        $('#modalSuccess').modal('toggle');
                        $('#modalSuccess .alert').html("Succesfully Saved");  
                    }else{
                        $('#modalWarning').modal('toggle');
                        $('#modalWarning .alert').html(data);
                    }
                }
            });
        }
    
    });

    $(document).on("click", ".ys_ot", function(){
        var data = $(this).attr("id");
    
        $.ajax({
            url:'query/deleteeo.php?ot', 
            type:'post',
            data:{data: data},
            success:function(res){
                $("#modalWarning").modal('toggle');
                $('#modalWarning .alert').html("Successfully Deleted!"); 
                $(".ob-viewdel").modal('hide');
                $('.modal-backdrop').remove();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        document.getElementById("tbot").innerHTML = this.responseText;
                    }
                };
                
                xmlhttp.open("GET", "query/Query-otview.php", true);
                xmlhttp.send();
            }
        });
    });    
    
    $(document).on("click", ".ys_ob", function(){
        var data = $(this).attr("id");
    
        $.ajax({
            url:'query/deleteeo.php?ob', 
            type:'post',
            data:{data: data},
            success:function(res){
              
                $("#modalWarning").modal('toggle');
                $('#modalWarning .alert').html("Successfully Deleted!"); 
                $(".ob-viewdel").modal('hide');
                $('.modal-backdrop').remove();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        document.getElementById("tbob").innerHTML = this.responseText;
                    }
                };
                xmlhttp.open("GET", "query/Query-obsearch.php", true);
                xmlhttp.send();
            }
        });
    });    
    
    $(document).on("click", ".ys_leave", function(){
        var data = $(this).attr("id");
        
        $.ajax({
            url:'query/deleteeo.php?leave', 
            type:'post',
            data:{data: data},
            success:function(res){
                $("#modalWarning").modal('toggle');
                $('#modalWarning .alert').html("Successfully Deleted!"); 
                $(".ob-viewdel").modal('hide');
                $('.modal-backdrop').remove();
                var xmlhttp = new XMLHttpRequest();
                xmlhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        document.getElementById("tbalas").innerHTML = this.responseText;
                    }
                };
                xmlhttp.open("GET", "query/Query-Viewalas.php", true);
                xmlhttp.send();
            }
        });
    });  
    
    $("#obsave1").click(function(){
        if ($(".obdatefrom").val()==$(".obdateto").val()){
            if ($(".timefrom").val()>=$(".timeto").val()){
                $('#modalWarning').modal('toggle');
                $('#modalWarning .alert').html("Invalid Inclusive Time ! "); 
                return false;
            }
        }
        if (!$(".obdatefrom").val()){
          $('#modalWarning').modal('toggle');
          $('#modalWarning .alert').html("Please Select OB Date From"); 
        }else if (!$(".obdateto").val()){
          $('#modalWarning').modal('toggle');
          $('#modalWarning .alert').html("Please Select OB Date To"); 
        }else if ($(".obdateto").val()<$(".obdatefrom").val()){
          $('#modalWarning').modal('toggle');
          $('#modalWarning .alert').html("Invalid Inclusive Date"); 
        }else if (!$(".ifrom").val()){
          $('#modalWarning').modal('toggle');
          $('#modalWarning .alert').html("Please Input Itinerary From"); 
        }else if (!$(".ito").val()){
          $('#modalWarning').modal('toggle');
          $('#modalWarning .alert').html("Please Input Itinerary To");
        }else if (!$("#purpose").val()){
          $('#modalWarning').modal('toggle');
          $('#modalWarning .alert').html("Please Input Purpose");
        }else if (!$(".timefrom").val()){
          $('#modalWarning').modal('toggle');
          $('#modalWarning .alert').html('Please Input Departure');
        }else if (!$(".timeto").val()){
          $('#modalWarning').modal('toggle');
          $('#modalWarning .alert').html("Please Input Return");
        }else{
            var data=$("#obdata").serialize();
            
            $.ajax({
                url:'query/Query-InsertOB.php', 
                type:'post',
                data:data,
                
                success:function(data){
                
                    if (data==2){
                        $('#modalWarning').modal('toggle');
                        $('#modalWarning .alert').html("You have No Schedule on this Date !"); 
                    }else if (data==3){
                        $('#modalWarning').modal('toggle');
                        $('#modalWarning .alert').html("Invalid Inclusive Time !"); 
                    }else if (data==4){
                        $('#modalWarning').modal('toggle');
                        $('#modalWarning .alert').html("System not accepting same filing date  !"); 
                    }else if (data==5){
                        $('#modalWarning').modal('toggle');
                        $('#modalWarning .alert').html("System not accepting Filing in holiday !"); 
                    }else if (data==6){
                        $('#modalWarning').modal('toggle');
                        $('#modalWarning .alert').html("System not accept OB when you are leave !"); 
                    }else if (data == 1){
                        $('#modalWarning').modal('toggle');
                        $('#modalWarning .alert').html("Filling Error ! ");
                    }else if (data == 7){
                        $('#modalWarning').modal('toggle');
                        $('#modalWarning .alert').html("You can't apply OB for this inclusive date. Payroll is already generated! ");
                    }else{
                        $('#modalSuccess').modal('toggle');
                        $('#modalSuccess .alert').html("Succesfully Saved");
                        var xmlhttp = new XMLHttpRequest();
                        $("#tbob").empty();
                        xmlhttp.onreadystatechange = function() {
                            if (this.readyState == 4 && this.status == 200) {
                                document.getElementById("tbob").innerHTML = this.responseText;
                            }
                        };
                        $("#newform").modal('hide');
                        $('.modal-backdrop').remove();
                        xmlhttp.open("GET", "query/Query-obsearch.php", true);
                        xmlhttp.send();
                    
                        $("#newform").modal('hide');
                        // alert("OB successfully saved!");
                    }
                }
            });
        
        }
    });
    
    //for leave type kind if change 
    $("#leavetype").change(function(){
        var data = $(this).val();
        //if valid filing after leave 
        $.ajax({
            url:'query/Query-Function_Module.php?checkIFleaveafter', 
            type:'post',
            data:{ltid : data},
            success:function(data){
                if (data==1){
                    // var d = new Date();
                    // var n = d.getFullYear();
                    // var x = document.getElementById("lstarts").min = n + "-01-01" ;
                    // var x = document.getElementById("lenddate").min = n + "-01-01" ;
                    // $(".ihd-dis").show();
                }else{
                    // document.getElementById("lstarts").min = data;
                    // document.getElementById("lstarts").value = data;
                    // document.getElementById("lenddate").min = data;
                    // document.getElementById("lenddate").value = data;
                    // $(".ihd-dis").hide();
                }
            }
        });

        //if valid file before  leave 
        $.ajax({
            url:'query/Query-Function_Module.php?checkIFleavebefore', 
            type:'post',
            data:{ltid : data},
            success:function(data){
                if (data==1){
                    // var d = new Date();
                    // var n = d.getFullYear();
                    // var x = document.getElementById("lstarts").max = n + "-12-31" ;
                    // var x = document.getElementById("lenddate").max = n + "-12-31" ;
                    // $(".ihd-dis").show();
                }else{
                    // document.getElementById("lstarts").max = data;
                    // document.getElementById("lenddate").max = data;
                    // $(".ihd-dis").hide();
                }
            }
        });

    });
    
     //udate leave type kind if change
    $('#exampleCheck1').on('change', function() {
        
        if ($(this).is(':checked')) {
            $('#halfDayOptions').show(); 
        } else {
            $('#halfDayOptions').hide(); 
        }
    });


});