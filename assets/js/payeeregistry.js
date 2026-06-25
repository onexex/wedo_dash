$(document).ready(function() {
    loaddata();

    function loaddata() {
        $.ajax({
            url: 'query/payeereg-phpscript.php?getpayeelist',
            type: "post",
            cache: false,
            data: {},
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText);
            },
            success: function(dataResult) {
                var resultData = dataResult.payee;
                var emp = '';
                $(resultData).each(function(index, item) {
                    emp += "<tr>" +
                        "<td class='idBarcode'>" + item.payee + "</td>" +
                        "<td style='text-align:left'>" + item.can + "</td>" +
                        "<td style='margin-left:5%'>" +
                        "<button  value ='" + item.id + "' id='delete' class='btn btn-danger btn-sm'><i class='fa fa-ban'></i> </button>"
                    "</td>"
                    emp += "</tr>";
                })
                $("#tblpayeereg").empty();
                $("#tblpayeereg").append(emp);
            }
        });
    }

    function clear() {
        $("#payee").val('');
        $("#can").val('');
    }

    $(document).on('click', '#store', function(e) {
        var payee = $("#payee").val();
        var can = $("#can").val();

        if (payee === "") {
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Payee is required!');
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
            return false;

        }
        if (can === "") {
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Customer Account Nummber is required!');
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
            return false;

        }


        $.ajax({
            url: 'query/payeereg-phpscript.php?store',
            type: "post",
            cache: false,
            data: { payee: payee, can: can },
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText);
            },
            success: function(dataResult) {
                var errcode = dataResult.errinsert;
                if (errcode == 0) {

                    $("#result").addClass("alert alert-danger offset4 span4");
                    $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Something went wrong');
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
                    return false;

                } else {
                    //save

                    $("#result").addClass("alert alert-success offset4 span4");
                    $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Message! </strong> Data successfully save!');
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

                    loaddata();
                    clear();
                }
            }
        });
    });

    $(document).on('click', '#delete', function(e) {
        var id = $(this).val();

        if (confirm("Are you sure to delete? This event cannot be undone!")) {
            $.ajax({
                url: 'query/payeereg-phpscript.php?remove',
                type: "post",
                cache: false,
                data: { id: id },
                dataType: 'json',
                error: function(xhr, status, error) {
                    alert(xhr.responseText);
                },
                success: function(dataResult) {
                    var errinsert = dataResult.errinsert;

                    if (errinsert == 0) {

                        $("#result").addClass("alert alert-danger offset4 span4");
                        $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Something went wrong');
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
                        return false;

                    } else {

                        loaddata();
                    }
                }
            });
        }

    });

});