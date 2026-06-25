$(document).ready(function() {
    var generatedChekno = 0;
    var idbookletid = 0;;
    jQuery.fn.capitalize = function() {
        $(this[0]).keyup(function(event) {
            var box = event.target;
            var txt = $(this).val();
            var stringStart = box.selectionStart;
            var stringEnd = box.selectionEnd;
            $(this).val(txt.replace(/^(.)|(\s|\-)(.)/g, function($word) {
                return $word.toUpperCase();
            }));
            box.setSelectionRange(stringStart, stringEnd);
        });

        return this;
    }
    loaddata();
    loadbankinfo();
    //load data

    function loadbankinfo() {
        $.ajax({
            url: 'query/check-phpscript.php?loadbankinfo',
            type: "post",
            cache: false,
            data: {},
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText);
            },
            success: function(dataResult) {
                var resultData = dataResult.bankinfo;
                var date = dataResult.date;
                var bankinfo = '';
                bankinfo += ("<option value=''></option>");
                $(resultData).each(function(index, item) {
                    bankinfo += ("<option value=" + item.id + ">" + item.bankname + "</option>");
                })
                $("#bankinfo").empty();
                $("#bankinfo").append(bankinfo);
                $("#checkdate").empty().val(date);

            }
        });
    }

    function loaddata() {
        //load bankinfo and payee
        $.ajax({
            url: 'query/check-phpscript.php?getpayeeandbankinfo',
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
                var payee = dataResult.payee;
                var bankinfo = dataResult.bankinfo;
                var bankinfodata = '';
                var payeedata = '';
                payeedata += ("<option value='0' >-</option>");
                $.each(payee, function(index, item) {
                    payeedata += ("<option value=" + item.payee + ">" + item.payee + "</option>");
                })
                bankinfodata += ("<option value='0' >-</option>");
                $.each(bankinfo, function(index, item) {
                    bankinfodata += ("<option value=" + item.bankname + ">" + item.bankname + "</option>");

                })
                $("#payeefilter").empty().append(payeedata);
                $("#bankfilter").empty().append(bankinfodata);

            }
        });
    }
    $(document).on("change", "#bankinfo", function(e) {
        var bankid = $(this).val();
        $.ajax({
            url: 'query/check-phpscript.php?genchkno',
            type: "post",
            cache: false,
            data: {
                bankid: bankid
            },
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText);
            },
            success: function(dataResult) {
                console.log(dataResult);
                var chkno = dataResult.checkno;
                var idbooklet = dataResult.idbooklet;

                var err = dataResult.err;
                generatedChekno = chkno;
                idbookletid = idbooklet;
                if (bankid == 0) {
                    $("#checknumber").empty().val('');
                } else {
                    if (err == 1) {
                        dialog.alert({
                            message: "Unable to generate Check No. No booklet found."
                        });
                        $("#checknumber").empty().val('');
                    } else if (err == 11) {
                        dialog.alert({
                            message: "Out of booklet"
                        });
                    } else {
                        $("#checknumber").empty().val(chkno);
                    }
                }


            }
        });
    });

    $(document).on("click", "#print", function(e) {
        //.print();
        e.preventDefault();
        var payee = $("#payeefilter").find(":selected").text();
        var bankinfo = $("#bankfilter").find(":selected").text();
        var dfrom = $("#dfrom").val();
        var to = $("#dto").val();

        $(".subtitle").show();
        $(".actions").hide();

        $(".thisdate").html("For the period of " + dfrom + " - " + to);

        var printContents = document.getElementById('forprinting').innerHTML;
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = printContents;

        window.print();
        // $('#payeefilter :selected').text(dd);
        document.body.innerHTML = originalContents;
        $(".subtitle").hide();
        $(".actions").show();


    });

    $(document).on("click", "#cancel", function() {
        var id = $(this).val();
        $.ajax({
            url: 'query/check-phpscript.php?ban',
            type: "post",
            cache: false,
            data: {
                id: id

            },
            //dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText);
            },
            success: function(dataResult) {
                console.log(dataResult);
                $("#refresh").click();

            }
        });


    });
    $(document).on("click", "#refresh", function() {
        var from = $("#dfrom").val();
        var to = $("#dto").val();
        var orderfilter = $("#orderfilter").val();
        var sortby = $("#sortby").val();


        var payeefilter = $("#payeefilter").val();
        var bankfilter = $("#bankfilter").val();

        // if (from > to) {
        //     alert("Invalid inclsive date");
        //     return false;
        // }
        // if (from === "" || to === "") {
        //     alert("Invalid inclsive date");
        //     return false;
        // }

        $.ajax({
            url: 'query/check-phpscript.php?gethistory',
            type: "post",
            cache: false,
            data: {
                from: from,
                to: to,
                payeefilter: payeefilter,
                bankfilter: bankfilter,
                orderfilter: orderfilter,
                sortby: sortby,

            },
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText);
            },
            success: function(dataResult) {
                console.log(dataResult);
                var payee = dataResult.data;
                var payeedata = '';
                var error = dataResult.err;
                var i = 1;
                if (error > 0) {
                    $.each(payee, function(index, item) {
                        basic = parseFloat(item.checkamount);
                        basic = basic.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                        payeedata += "<tr>" +
                            "<td  >" + i++ + "</td>" +
                            "<td  >" + item.payee + "</td>" +
                            "<td  >" + item.bankinfo + "</td>" +
                            "<td  >" + item.checkno + "</td>" +
                            "<td  >" + basic + "</td>" +
                            "<td  >" + item.checkdate + "</td>" +
                            "<td  >" + item.StatusDesc + "</td>" +
                            "<td  class='actions'>";
                        if (item.status == 7) {
                            payeedata += "<button  value ='" + item.id + "' disabled id='cancel' data-toggle='modal' data-target='#amsshow' class='btn btn-secondary btn-sm'><i class='fa fa-ban'></i> </button>"
                            "</td>";
                        } else {
                            payeedata += "<button  value ='" + item.id + "'  id='cancel' data-toggle='modal' data-target='#amsshow' class='btn btn-danger btn-sm'><i class='fa fa-ban'></i> </button>"
                            "</td>";
                        }

                        payeedata += "</tr>";
                    })

                    $("#historydata").empty().append(payeedata);
                } else {
                    $("#historydata").empty();
                }



            }
        });


    });

    $(document).on("click", ".printthis", function() {
        //.print();
        var printContents = document.getElementById('forprint').innerHTML;
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;


    });

    $(document).on("click", ".save", function(e) {
        var payee = $("#payee").val();
        //var bankinfo = $("#bankinfo selected").val();
        var newid = idbookletid;
        var bankinfo = $("#bankinfo").find("option:selected").text();
        var checkno = $("#checknumber").val();
        var checkdate = $("#checkdate").val();
        var checkamount = $("#checkamount").val();
        var remarks = $("#remark").val();
        var basic = '';
        basic = parseFloat(checkamount);
        basic = basic.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")

        e.preventDefault();
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
        if (payee === "") {
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Payee is Required!');
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
        if (bankinfo === "") {
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Bank Info is Required!');
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
        if (checkno === "") {
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Checkno is Required!');
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
        if (checkdate === "") {
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Check date is Required!');
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
        if (checkamount === "") {
            $("#result").addClass("alert alert-danger offset4 span4");
            $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Check Amount is Required!');
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
        // if (remarks === "") {
        //     $("#result").addClass("alert alert-danger offset4 span4");
        //     $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Warning! </strong> Remarks is Required!');
        //     $(".alert").show();
        //     $(".alert").css("opacity", "100%");
        //     $('.alert .close').on("click", function() {
        //         $(this).parent().slideUp(500, 0).slideUp(500);
        //         $(".alert").removeClass();
        //     });

        //     window.setTimeout(function() {
        //         $(".alert").fadeTo(500, 0).slideUp(500, function() {
        //             $(this).hide();
        //         });
        //     }, 2000);
        //     return false;
        // }

        dialog.confirm({

            message: "Please verify if check details are correct.",
            callback: function(response) {
                if (response) {
                    $.ajax({
                        url: 'query/check-phpscript.php?store',
                        type: 'POST',
                        cache: false,
                        data: { newid: newid, payee: payee, bankinfo: bankinfo, checkno: checkno, checkdate: checkdate, checkamount: checkamount, remarks: remarks },
                        dataType: 'json',
                        success: function(dataresult) {
                            console.log(dataresult);
                            var resultdata = dataresult.inserterr;
                            var dat = dataresult.dat;
                            var dword = dataresult.totalPRSUM;


                            if (resultdata == 1) {

                                $("#result").addClass("alert alert-success offset4 span4");
                                $("#result").html('<button type="button" class="close" aria-label="close">×</button><strong>Message! </strong> Info is confirmed and saved!');
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

                                $(".l1").empty().text(dat);
                                $(".l2a").empty().text(payee);
                                $(".l2b").empty().text(basic);
                                $(".l3").empty().text(dword);
                                $(".l4").empty().text(remarks);

                                $("#payee").val('');
                                $("#bankinfo").val('');
                                $("#checknumber").val('');
                                $("#checkdate").val('');
                                $("#checkamount").val('');
                                $("#remark").val('');
                                loaddata();
                                loadbankinfo();
                                $("#refresh").click();

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
                } else {
                    e.preventDefault();
                }

            }
        });

    });

    //live search for establishment
    $(document).on('keyup', '#payee', function() {
        $("#payee").capitalize();
        var inputVal = $(this).val();
        var resultDropdown = $(".dv-livesearch");


        if (inputVal.length) {
            $.ajax({
                url: 'query/check-phpscript.php?search',
                type: "post",
                cache: false,
                data: {
                    inputVal: inputVal,
                },
                dataType: 'json',
                error: function(xhr, status, error) {
                    alert(xhr.responseText);
                },
                success: function(dataResult) {
                    console.log(dataResult);
                    var dataResult = dataResult.data;
                    var bodyData = '';

                    $.each(dataResult, function(index, row) {
                        bodyData += ("<a id=" + row.id + "  class='btn'> " + row.payee + "\n");
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
        var idname = $(this).attr('id');
        var textdata = $(this).text();
        $(this).parent(".dv-livesearch").empty();

        $.ajax({
            url: 'query/check-phpscript.php?getdata',
            type: "post",
            cache: false,
            data: {
                idname: idname,
            },
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText);
            },
            success: function(dataResult) {
                console.log(dataResult);
                var dataResult = dataResult.data;
                var bodyData = '';

                $.each(dataResult, function(index, item) {
                    $("#payee").empty().val(item.payee);
                    // $("#bankinfo").empty().val(item.bankinfo);
                    // $("#checknumber").empty();
                    // $("#checkdate").empty();
                    // $("#checkamount").empty();
                    $("#remark").empty().val(item.can);
                })

            }
        });

    });

});