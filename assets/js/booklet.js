$(document).ready(function() {
    loaddata();

    function loaddata() {
        $.ajax({
            url: 'query/bookletreg-phpscript.php?loadbankinfo',
            type: "post",
            cache: false,
            data: {},
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText);
            },
            success: function(dataResult) {
                var resultData = dataResult.bankinfo;
                var emp = '';
                $(resultData).each(function(index, item) {
                    emp += "<tr>" +
                        "<td class='idBarcode'>" + item.bankname + "</td>" +

                        "<td style='margin-left:5%'>" +
                        "<button  value ='" + item.id + "' id='view'  data-toggle='modal' data-target='#exampleModalCenter' class='btn btn-primary btn-sm'><i class='fa fa-edit'></i>  </button>" +
                        "</td>" +

                        "<td style='margin-left:5%'>" +
                        "<button  value ='" + item.id + "' id='delete'  class='btn btn-danger btn-sm'><i class='fa fa-ban'></i> </button>" +
                        "</td>"
                    emp += "</tr>";
                })
                $("#tblbankbookle").empty();
                $("#tblbankbookle").append(emp);
            }
        });
    }

    function loadbookletnumber(val) {
        $.ajax({
            url: 'query/bookletreg-phpscript.php?loadbooklet',
            type: "post",
            cache: false,
            data: { val: val },
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText);
            },
            success: function(dataResult) {
                console.log(dataResult);
                var resultData = dataResult.booklet;
                var emp = '';
                $(resultData).each(function(index, item) {
                    emp += "<tr>"
                    emp += "<td class='idBarcode'>" + item.bookletfrom + "</td>"
                    emp += "<td class='idBarcode'>" + item.bookleto + "</td>"

                    emp += "<td style='margin-left:5%'>";
                    if (item.status == "1") {
                        emp += "<button id='status' value='" + item.id + "' type='button' class='btn btn-success btn-sm'> Active</button>"
                    } else {
                        emp += "<button id='status' value ='" + item.id + "' type='button' class='btn btn-secondary btn-sm'> Inactive</button>"
                    }
                    emp += "</td>"
                    emp += "<td style='margin-left:5%'>"
                    emp += "<button  value ='" + item.id + "' id='delcheckbook'  class='btn btn-danger btn-sm'><i class='fa fa-ban'></i> </button>"
                    emp += "</td>"
                    emp += "</tr>";
                })
                $("#tblpayeereg").empty();
                $("#tblpayeereg").append(emp);

            }
        });
    }
    $(document).on('click', '#delete', function(e) {
        var id = $(this).val();
        dialog.confirm({
            message: "Are you sure to delete this Bank ?",
            callback: function(response) {
                if (response) {
                    $.ajax({
                        url: 'query/bookletreg-phpscript.php?delbankinfo',
                        type: "post",
                        cache: false,
                        data: { id: id },

                        error: function(xhr, status, error) {
                            alert(xhr.responseText);
                        },
                        success: function(dataResult) {
                            loaddata();
                        }
                    });

                }

            }
        });
    });

    $(document).on('click', '#delcheckbook', function(e) {
        var bankid = $("#addnew").val();
        var val = $(this).val();

        dialog.confirm({
            message: "Are you sure to removed this booklet ?",
            callback: function(response) {
                if (response) {
                    //1
                    $.ajax({
                        url: 'query/bookletreg-phpscript.php?delserries',
                        type: "post",
                        cache: false,
                        data: { val: val },

                        error: function(xhr, status, error) {
                            alert(xhr.responseText);
                        },
                        success: function(dataResult) {}
                    });
                    loadbookletnumber(bankid);
                    //1

                }
            }
        });
    });


    $(document).on('click', '#status', function(e) {
        var bookletid = $(this).val();
        var bankid = $("#addnew").val();

        $.ajax({
            url: 'query/bookletreg-phpscript.php?updatebooklet',
            type: "post",
            cache: false,
            data: { bankid: bankid, bookletid: bookletid },
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText);
            },
            success: function(dataResult) {
                var err = dataResult.errinsert;

                if (err == 0) {
                    $("#result1").addClass("alert alert-danger offset4 span4");
                    $("#result1").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Something went wrong!');
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
                    // $("#result1").addClass("alert alert-success offset4 span4");
                    // $("#result1").html('<button type="button" class="close" aria-label="close">×</button><strong>Message! </strong> Serries Activated!');
                    // $(".alert").show();
                    // $(".alert").css("opacity", "100%");
                    // $('.alert .close').on("click", function() {
                    //     $(this).parent().slideUp(500, 0).slideUp(500);
                    //     $(".alert").removeClass();
                    // });

                    // window.setTimeout(function() {
                    //     $(".alert").fadeTo(500, 0).slideUp(500, function() {
                    //         $(this).hide();
                    //         $(".alert").removeClass();
                    //     });
                    // }, 2000);
                    dialog.alert({
                        message: "Updated booklet."
                    });
                    loadbookletnumber(bankid);
                }
            }
        });



    });

    $(document).on('click', '#store', function(e) {
        var bankname = $("#bankname").val();
        if (bankname === "") {
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Bankname is required!');
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

            $.ajax({
                url: 'query/bookletreg-phpscript.php?store',
                type: "post",
                cache: false,
                data: { bankname: bankname },
                dataType: 'json',
                error: function(xhr, status, error) {
                    alert(error);
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

                    }
                }
            });
        }


    });

    $(document).on('click', '#view', function(e) {
        var val = $(this).val();
        $("#addnew").val(val);
        loadbookletnumber(val);

    });

    $(document).on('click', '#addnew', function(e) {

        var start = $("#start").val();
        var end = $("#end").val();
        var id = $(this).val();

        if (start > end) {
            dialog.alert({
                message: "Invalid inclusive numbers!",
                button: "Close"
            });
            return false;
        }

        if (start === "") {
            $("#result1").addClass("alert alert-danger offset4 span4");
            $("#result1").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Starting serries no. is required!');
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

        if (end === "") {
            $("#result1").addClass("alert alert-danger offset4 span4");
            $("#result1").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Ending serries no. is required!');
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
            url: 'query/bookletreg-phpscript.php?addbooklet',
            type: "post",
            cache: false,
            data: { start: start, end: end, id: id },
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText);
            },
            success: function(dataResult) {
                // console.log(dataResult);

                var olfn = '';
                var olln = '';
                var errcode = dataResult.errinsert;
                var data = dataResult.olnum;

                $.each(data, function(index, item) {
                    olfn = item.bookletfrom;
                    olln = item.bookleto;
                })

                if (errcode == 0) {

                    $("#result1").addClass("alert alert-danger offset4 span4");
                    $("#result1").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Something went wrong');
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

                } else if (errcode == 20) {

                    $("#result1").addClass("alert alert-danger offset4 span4");
                    $("#result1").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> <br> Overlapping number detected <br> ' + "( " + olfn + " - " + olln + " ). <br> Please contact your system administrator!");
                    $(".alert").show();
                    $(".alert").css("opacity", "100%");
                    $('.alert .close').on("click", function() {
                        $(".alert").removeClass();
                        $(this).parent().slideUp(500, 0).slideUp(500);

                    });

                    // window.setTimeout(function() {
                    //     $(".alert").fadeTo(1000, 0).slideUp(1000, function() {
                    //         $(this).hide();
                    //         $(".alert").removeClass();
                    //     });
                    // }, 2000);
                    return false;
                } else {
                    //save

                    $("#result1").addClass("alert alert-success offset4 span4");
                    $("#result1").html('<button type="button" class="close" aria-label="close">×</button><strong>Message! </strong> Data successfully save!');
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

                    // loaddata();
                    $("#start").val('');
                    $("#end").val('');

                    loadbookletnumber(id);
                }
            }
        })

    });

});