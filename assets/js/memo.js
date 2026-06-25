$(document).ready(function() {

    // $(document).on('click', '.clearform', function(e) {
    //     clear();
    //     getdatenow();

    // });

    function clear() {
        $("#to").val('');
        $("#from").val('');
        $("#datememo").val('');
        $("#subject").val('');
        $("#body").val('');

    }

    function getdatenow() {
        $.ajax({
            url: 'query/memo-phpscript.php?getdatenow',
            type: "post",
            cache: false,
            data: {

            },
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText);
            },
            success: function(dataResult) {
                console.log(dataResult);
                var dataResult1 = dataResult.data;
                var newIDS = dataResult.newID;
                $("#datememo").empty().val(dataResult1);
                $("#memoid").empty().text(newIDS);
                // alert();

            }
        });
    }
    getdatenow();
    // viewrecord
    $(document).on('click', '.printmemo', function(e) {
        e.preventDefault();
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        var data_to = $("#to").val();
        var data_from = $("#from").val();
        var data_date = $("#datememo").val();
        var data_subject = $("#subject").val();
        var data_body = $("#body").val();
        var memoid = $("#memoid").text();
       //  var selecteddata = $('#multidd').val();

        // if (data_to === "") {
        //     $("#result").addClass("alert alert-danger offset4 span4");
        //     $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> To is required!!');
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
        //     return false;
        // }
        // if (data_from === "") {
        //     $("#result").addClass("alert alert-danger offset4 span4");
        //     $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> From is required!!');
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
        //     return false;
        // }
        // if (data_subject === "") {
        //     $("#result").addClass("alert alert-danger offset4 span4");
        //     $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Subject is required!!');
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
        //     return false;
        // }
        // if (data_body === "") {
        //     $("#result").addClass("alert alert-danger offset4 span4");
        //     $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Body is required!!');
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
        //     return false;
        // }

        $.ajax({
            url: 'query/memo-phpscript.php?store',
            type: "post",
            cache: false,

            data: {
                data_to: data_to,
                data_from: data_from,
                data_date: data_date,
                data_subject: data_subject,
                data_body: data_body,
                memoid: memoid,
            },
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText);
            },
            success: function(dataResult) {
                console.log(dataResult);
                var dataResultdata = dataResult.data;
                if (dataResultdata == 1) {

                    $(".subtitle").show();
                    $("#printMemoId").empty().text(memoid);
                    $("#printmemodate").empty().text(data_date);
                    $("#printto").empty().text(data_to);
                    $("#printsubject").empty().text(data_subject);
                    $("#printfrom").empty().text(data_from);
                    $("#bodyprint").empty().text(data_body);



                    var printContents = document.getElementById('forprint').innerHTML;
                    var originalContents = document.body.innerHTML;
                    document.body.innerHTML = printContents;
                    window.print();
                    document.body.innerHTML = originalContents;
                    $(".subtitle").hide();

                    clear();
                    getdatenow();



                } else {
                    $("#result").addClass("alert alert-danger offset4 span4");
                    $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Error! </strong> Something went wrong during !');
                    $(".alert").show();
                    $(".alert").css("opacity", "100%");
                    $('.alert .close').on("click", function() {
                        $(this).parent().slideUp(500, 0).slideUp(500);
                        $(".alert").removeClass();
                    });

                    window.setTimeout(function() {
                        $(".alert").fadeTo(500, 0).slideUp(500, function() {
                            $(this).hide();
                        });
                    }, 2000);
                    return false;
                }


            }
        });
        //clear();

    });


    //live search for establishment
    $(document).on('keyup', '#searchWord', function() {

        var inputVal = $(this).val();
        var resultDropdown = $(".dv-livesearch");


        if (inputVal.length) {
            $.ajax({
                url: 'query/memo-phpscript.php?findWord',
                type: "post",
                cache: false,
                data: {inputVal: inputVal},
                dataType: 'json',
                error: function(xhr, status, error) {
                alert(error);
                },
                success: function(dataResult) {
                    console.log(dataResult);
                    var dataResult = dataResult.data;
                    
                    var bodyData = '';

                    $.each(dataResult, function(index, row) {
                        bodyData += ("<a id=" + row.id + "  class='btn'> " + row.memoid + " " + row.subject + "\n");
                    })
                    resultDropdown.empty();
                    resultDropdown.html(bodyData);
                }
            });

        } else {
            resultDropdown.empty();
        }
    });

    // // Set search input value on click of result item
    $(document).on("click", ".dv-livesearch a", function() {
        var searchID = $(this).attr('id');
        var textdata = $(this).text();
        $(this).parent(".dv-livesearch").empty();
        $("#searchWord").val('');
        
        // Disable element
        $( "#to" ).prop( "disabled", true );
        $( "#from" ).prop( "disabled", true );
        $( "#subject" ).prop( "disabled", true );
        $( "#body" ).prop( "disabled", true );


        $.ajax({
            url: 'query/memo-phpscript.php?loadDatasearchID',
            type: "post",
            cache: false,
            data: {
                searchID: searchID,
            },
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText);
            },
            success: function(dataResult1) {
                console.log(dataResult);
                var dataResult = dataResult1.data;
                var datedata = dataResult1.date;
                var bodyData = '';
                $.each(dataResult, function(index, item) {
                    $("#to").empty().val(item.memoto);
                    $("#from").empty().val(item.memofrom);

                    $("#subject").empty().val(item.subject);
                    $("#body").empty().val(item.body);
                    $("#memoid").empty().text(item.memoid);
                })
                $("#datememo").empty().val(datedata);
                $(".reprint").show();
                $(".printmemo").hide();



            }
        });

    });
    $(document).on("click", ".reprint", function() {
        var data_to = $("#to").val();
        var data_from = $("#from").val();
        var data_date = $("#datememo").val();
        var data_subject = $("#subject").val();
        var data_body = $("#body").val();
        var memoid = $("#memoid").text();

        $(".subtitle").show();
        $("#printMemoId").empty().text(memoid);
        $("#printmemodate").empty().text(data_date);
        $("#printto").empty().text(data_to);
        $("#printsubject").empty().text(data_subject);
        $("#printfrom").empty().text(data_from);
        $("#bodyprint").empty().text(data_body);




        var printContents = document.getElementById('forprint').innerHTML;
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;
        $(".subtitle").hide();

        clear();
        getdatenow();
        $(".reprint").hide();
        $(".printmemo").show();
        
         $( "#to" ).prop( "disabled", false );
        $( "#from" ).prop( "disabled", false );
        $( "#subject" ).prop( "disabled", false );
        $( "#body" ).prop( "disabled", false );

    });

});