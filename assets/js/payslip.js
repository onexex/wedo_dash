$(document).ready(function() {

    $("#d").css("display", "none");
    $(document).on('click', '#btnviewpaydata', function(e) {
        //$("#toprint").toggle();
        $("#be").css("display", "none");
        $("#ic").css("display", "block");

    });

    $(document).on('click', '#btnviewbe', function(e) {
        //$("#toprint").toggle();
        var pdate = $("#pdates").val();
        var id = $("#employeelist").val();
        $("#ic").css("display", "none");
        $("#be").css("display", "block");
        $.ajax({
            url: 'query/payslip-phpscript.php?getpayrolldata',
            type: 'POST',
            cache: false,
            data: { pdate: pdate, id: id },
            dataType: 'json',
            success: function(dataresult) {
                console.log(dataresult);

                var resultdata = dataresult.paydata;
                var dataresult2 = dataresult.debitdata;
                var thirteenmonths = dataresult.thirteen;
                var datenow = dataresult.datenows;


                var ytd = dataresult.d;
                var earningytd = '';
                var totalearningcurrent = '';
                var bftaxcurrent = '';
                var bftaxytd = '';
                var aftax = '';
                var aftaxytd = '';
                var ercurrent = '';
                var erytd = '';
                var prcurrent = '';
                var prytd = '';
                var current1 = '';
                var current2 = '';
                var current3 = '';
                var ytd1 = '';
                var ytd2 = '';
                var ytd3 = '';
                var convertmintohrs = '';
                var adj2data = '';
                var adj2ytd = '';
                var newafyax = '';
                var aftaxytdnew = '';

                $("#datenow").empty().append(datenow);



                $(dataresult2).each(function(index, item) {
                    // debtAdDte = item.debitdate;
                    // branch = item.debitbranch + " - " + item.debitcity;
                    $("#advicedate").empty().append(item.debitdate);

                })

                $(resultdata).each(function(index, item) {
                    if (item.PYOtherAdj2 < 0) {
                        adj2data = "(" + (parseFloat(item.PYOtherAdj2) * -1).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",") + ")";

                    } else {
                        adj2data = parseFloat(item.PYOtherAdj2).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",");
                    }

                    totalearningcurrent = parseFloat(item.PYBasic) + parseFloat(item.PYAllowance) + parseFloat(item.PYOverTime) + parseFloat(thirteenmonths);
                    bftaxcurrent = parseFloat(item.PYAdj) + parseFloat(item.PYmfee);
                    aftax = parseFloat(item.PYSSS) + parseFloat(item.PYPhilHealth) + parseFloat(item.PYPagibig) + parseFloat(item.PYSSSLoan) + parseFloat(item.PYPILoan) + parseFloat(item.PYallowadj) + parseFloat(item.PYOtherAdj) + parseFloat(item.PYOtherAdj2);

                    if (aftax < 0) {
                        aftax = aftax * -1;
                        newafyax = aftax;
                        aftax = "(" + parseFloat(aftax).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",") + ")";

                    } else {
                        newafyax = aftax;
                        aftax = parseFloat(aftax).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",");
                    }

                    ercurrent = parseFloat(item.PYssser) + parseFloat(item.PYpier) + parseFloat(item.PYphiler);
                    current1 = parseFloat(item.PYBasic) + parseFloat(item.PYAllowance) + parseFloat(item.PYOverTime);
                    current2 = parseFloat(item.PYIncTax);
                    convertmintohrs = parseFloat(item.PYAdjMin) / 60;

                    $("#namebe").empty().append(item.EmpFN + " " + item.EmpMN + " " + item.EmpLN + " " + item.EmpSuffix);
                    $("#namerecieved").empty().append(item.EmpFN + " " + item.EmpMN + " " + item.EmpLN + " " + item.EmpSuffix);
                    $("#addressbe").empty().append(item.EmpAddress1 + ", " + item.EmpAddDis + ", " + item.EmpAddCity);
                    $("#designation").empty().append(item.PositionDesc);
                    $("#position").empty().append(item.PositionDesc);
                    $("#department").empty().append(item.DepartmentDesc);
                    $("#begindate").empty().append(item.PYDateFrom);
                    $("#enddate").empty().append(item.PYDateTo);
                    $("#basiccurrent").empty().append(parseFloat(item.PYBasic).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#witholdingcurrent").empty().append(parseFloat(item.PYIncTax).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#allowancecurrent").empty().append(parseFloat(item.PYAllowance).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#otcurrent").empty().append(parseFloat(item.PYOverTime).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#monthpaycurrent").empty().append(parseFloat(thirteenmonths).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#earningstotalcurrent").empty().append(parseFloat(totalearningcurrent).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#taxestotalcurrent").empty().append(parseFloat(item.PYIncTax).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#absenceshrs").empty().append((convertmintohrs).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#absencescurrent").empty().append(parseFloat(item.PYAdj).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#ssscurrent").empty().append(parseFloat(item.PYSSS).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#sssercurrent").empty().append(parseFloat(item.PYssser).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#picurrent").empty().append(parseFloat(item.PYPagibig).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#piercurrent").empty().append(parseFloat(item.PYpier).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#philcurrent").empty().append(parseFloat(item.PYPhilHealth).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#philercurrent").empty().append(parseFloat(item.PYphiler).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#sssloancurrent").empty().append(parseFloat(item.PYSSSLoan).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#piloancurrent").empty().append(parseFloat(item.PYPILoan).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#absencesallowcurrent").empty().append(parseFloat(item.PYallowadj).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#adj1current").empty().append(parseFloat(item.PYOtherAdj).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#adj2current").empty().append(adj2data);
                    $("#bftaxcurrent").empty().append(parseFloat(bftaxcurrent).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#managementcurrent").empty().append(parseFloat(item.PYmfee).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#aftaxcurrent").empty().append(aftax);
                    $("#epbcurrent").empty().append(parseFloat(ercurrent).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#totalgrosscurrent").empty().append((parseFloat(item.PYBasic) + parseFloat(item.PYAllowance) + parseFloat(item.PYOverTime)).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));

                    $("#totaltaxescurrent").empty().append(parseFloat(item.PYIncTax).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));

                })

                $(ytd).each(function(index, item) {

                    if (item.adj2 < 0) {
                        adj2ytd = "(" + (parseFloat(item.adj2) * -1).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",") + ")";
                    } else {
                        adj2ytd = parseFloat(item.adj2).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",");
                    }
                    parseFloat(item.adj2).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                    earningytd = parseFloat(item.month13) + parseFloat(item.ot) + parseFloat(item.allowance) + parseFloat(item.basic);
                    bftaxytd = parseFloat(item.adj) + parseFloat(item.mfee);
                    aftaxytd = parseFloat(item.sss) + parseFloat(item.phil) + parseFloat(item.pi) + parseFloat(item.sssloan) + parseFloat(item.piloan) + parseFloat(item.adjallow) + parseFloat(item.pyadj1) + parseFloat(item.adj2);
                    erytd = parseFloat(item.ssser) + parseFloat(item.pier) + parseFloat(item.pher);
                    current3 = bftaxcurrent + newafyax;
                    ytd1 = parseFloat(item.basic) + parseFloat(item.allowance) + parseFloat(item.ot);
                    ytd2 = parseFloat(item.inc);
                    ytd3 = bftaxytd + aftaxytd;


                    if (aftaxytd < 0) {
                        aftaxytd = aftaxytd * -1;
                        aftaxytdnew = aftaxytd;
                        aftaxytd = "(" + parseFloat(aftaxytd).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",") + ")";

                    } else {
                        aftaxytdnew = aftaxytd;
                        aftaxytd = parseFloat(aftaxytd).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",");
                    }


                    $("#earningstotalytd").empty().append(earningytd.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#monthpayytd").empty().append(parseFloat(item.month13).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#otytd").empty().append(parseFloat(item.ot).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#allowanceytd").empty().append(parseFloat(item.allowance).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#basicytd").empty().append(parseFloat(item.basic).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#witholdingytd").empty().append(parseFloat(item.inc).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#taxestotalytd").empty().append(parseFloat(item.inc).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#absencesytd").empty().append(parseFloat(item.adj).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#sssytd").empty().append(parseFloat(item.sss).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#ssserytd").empty().append(parseFloat(item.ssser).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#piytd").empty().append(parseFloat(item.pi).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#pierytd").empty().append(parseFloat(item.pier).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#philytd").empty().append(parseFloat(item.phil).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#philerytd").empty().append(parseFloat(item.pher).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#sssloanytd").empty().append(parseFloat(item.sssloan).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#piloanytd").empty().append(parseFloat(item.piloan).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#absencesallowytd").empty().append(parseFloat(item.adjallow).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#adj1ytd").empty().append(parseFloat(item.pyadj1).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#adj2ytd").empty().append(adj2ytd);
                    $("#btaxytd").empty().append(parseFloat(bftaxytd).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#managementytd").empty().append(parseFloat(item.mfee).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#aftaxafter").empty().append(parseFloat(aftaxytdnew).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#epbytd").empty().append(parseFloat(erytd).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#totalgrossytd").empty().append((parseFloat(item.basic) + parseFloat(item.allowance) + parseFloat(item.ot)).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));

                    $("#totaltaxesytd").empty().append(parseFloat(item.inc).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#totaldeductioncurrent").empty().append(parseFloat(bftaxcurrent + newafyax).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#totaldeductionytd").empty().append(parseFloat(bftaxytd + aftaxytdnew).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));

                    prcurrent = current1 - current2 - current3;
                    prytd = ytd1 - ytd2 - ytd3;
                    $("#recievablecurrent").empty().append(parseFloat(prcurrent).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#recievableytd").empty().append(parseFloat(prytd).toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));






                })


            }
        });

    });

    $(document).on('click', '#btnprintbe', function(e) {
        var printContents = document.getElementById('printbe').innerHTML;
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = printContents;

        window.print();
        // $('#payeefilter :selected').text(dd);
        document.body.innerHTML = originalContents;

    });

    $(document).on('click', '#btnviewpaydata', function(e) {

        var pdate = $("#pdates").val();
        var id = $("#employeelist").val();

        jQuery.ajax({
            url: 'query/payslip-phpscript.php?getpayrolldata',
            method: 'POST',
            data: { pdate: pdate, id: id },
            cache: false,
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(error);
            },
            success: function(resultData) {
                console.log(resultData);
                if (resultData == 0) {
                    // alert("Viewing is not allowed! " + "Data will be available  on or after " + pdate + " !");

                    $("#result1").addClass("alert alert-info offset4 span4");
                    $("#result1").html('<button type="button" class="close" aria-label="close">×</button><strong>Message!</strong> <br> Payslip data will be made available for viewing and printing on <strong>' + pdate + '</strong> !');
                    $(".alert").show();
                    $(".alert").css("opacity", "100%");
                    $('.alert .close').on("click", function() {
                        $(this).parent().slideUp(500, 0).slideUp(500);
                    });
                    return false;
                }
                var dataresult = resultData.paydata;
                var dataresult2 = resultData.debitdata;
                var dataresult3 = resultData.totalPRSUM;

                var name = 'sdsd';
                var address = "ramon";
                var appForRel = "ramon";
                var debtAdDte = "ramon";
                var branch = "";
                var city = "";
                var cutoff = "";
                var pr = "";
                var totalpr = "";
                var basic = "";
                var allowance = "";
                var ot = "";
                var adjtard = "";
                var adj1 = "";
                var adj2 = "";
                var phil = "";
                var sss = "";
                var pagibig = "";
                var sssloan = "";
                var pagibigloan = "";
                var tax = "";
                var debittotal = '';
                var credittotal = "";
                var adjt1 = "";
                var adjt2 = "";
                var creditadj1 = 0;
                var creditadj2 = 0;
                var debitadj1 = 0;
                var debitadj2 = 0;
                var desc = "";
                var adj2a = 0;
                var finalAdj1 = 0;
                var finalAdj2 = 0;

                $(dataresult).each(function(index, item) {
                    adj1 = '';
                    adj2 = '';
                    adj2a = '';
                    adjtt2 = '';
                    adjtt1 = '';
                    adjt1 = item.PYOtherAdj;
                    adjt2 = item.PYOtherAdj2;
                    adjtt1 = parseFloat(adjt1) * -1;
                    adjtt2 = parseFloat(adjt2) * -1;
                    finalAdj1 = parseFloat(adjt1);
                    finalAdj2 = parseFloat(adjt2);



                    if (adjt1 < 0) {
                        //credit
                        creditadj1 = adjtt1;

                    } else {
                        //debit
                        debitadj1 = parseFloat(adjtt1);
                    }

                    if (adjt2 < 0) {
                        //credit
                        creditadj2 = adjtt2;
                    } else {
                        //debit
                        debitadj2 = parseFloat(adjt2);
                    }

                    name = item.EmpFN + " " + item.EmpMN + " " + item.EmpLN + " " + item.EmpSuffix;
                    address = item.EmpAddress1 + ", " + item.EmpAddDis + ", " + item.EmpAddCity;
                    appForRel = item.approvedate;
                    cutoff = item.PYDateFrom + " to " + item.PYDateTo;
                    desc = "BACK-END OFFICE PAYROLL (" + item.PYDateFrom + " - " + item.PYDateTo + " )";
                    //pr
                    pr = parseFloat(item.PYRecivable);
                    pr = pr.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                        //total pr
                    totalpr = parseFloat(item.PYRecivable);
                    totalpr = totalpr.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                        //basic
                    basic = parseFloat(item.PYBasic);
                    basic = basic.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                        //allowance
                    allowance = parseFloat(item.PYAllowance);
                    allowance = allowance.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                        //ot
                    ot = parseFloat(item.PYOverTime);
                    ot = ot.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                        //adj1
                    adj1 = parseFloat(adjtt1);
                    adj1 = adj1.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                        //adj2
                    adj2 = parseFloat(adjtt2);
                    adj2 = adj2.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                        //tardines
                    adjtard = parseFloat(item.PYAdj);
                    adjtard = adjtard.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                        //sss
                    sss = parseFloat(item.PYSSS);
                    sss = sss.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                        //phil
                    phil = parseFloat(item.PYPhilHealth);
                    phil = phil.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                        //pagibig
                    pagibig = parseFloat(item.PYPagibig);
                    pagibig = pagibig.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                        //sssloan
                    sssloan = parseFloat(item.PYSSSLoan);
                    sssloan = sssloan.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                        //pagibigloan
                    pagibigloan = parseFloat(item.PYPILoan);
                    pagibigloan = pagibigloan.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                        //tax
                    tax = parseFloat(item.PYIncTax);
                    tax = tax.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                        //debit total
                    debittotal = parseFloat(item.PYBasic) +
                        parseFloat(item.PYAllowance) +
                        parseFloat(debitadj1) +
                        parseFloat(debitadj2);
                    debittotal = debittotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                        //credit total coloumn
                    credittotal = parseFloat(item.PYOverTime) +
                        parseFloat(creditadj1) +
                        parseFloat(creditadj2) +
                        parseFloat(item.PYAdj) +
                        parseFloat(item.PYSSS) +
                        parseFloat(item.PYPhilHealth) +
                        parseFloat(item.PYPagibig) +
                        parseFloat(item.PYSSSLoan) +
                        parseFloat(item.PYPILoan) +
                        parseFloat(item.PYIncTax) +
                        parseFloat(item.PYRecivable);
                    credittotal = credittotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")


                })

                $(dataresult2).each(function(index, item) {
                    debtAdDte = item.debitdate;
                    branch = item.debitbranch + " - " + item.debitcity;

                })


                $("#description").empty().append(desc);
                $("#bankinfo").empty().append(branch);
                $("#credittotal").empty().append(credittotal);
                $("#debittotal").empty().append(debittotal);
                $("#creditcash").empty().append(pr);
                $("#credittax").empty().append(tax);
                $("#creditpagibigloan").empty().append(pagibigloan);
                $("#creditsssloan").empty().append(sssloan);
                $("#creditpagibig").empty().append(pagibig);
                $("#creditphilhealth").empty().append(phil);
                $("#creditsss").empty().append(sss);
                $("#credittardiness").empty().append(adjtard);

                // $("#debitadjustment1").empty();
                // $("#debitadjustment2").empty();
                // $("#creditadjustment1").empty();
                // $("#creditadjustment2").empty();

                //debitadjustment1  creditadjustment1
                //debitadjustment2  creditadjustment2

                // finalAdj1=parseFloat(adjt1);
                // finalAdj2=parseFloat(adjt2);
                if (finalAdj1 < 0) {
                    //  alert("adj1 found less 0 " + finalAdj1 );
                    $("#creditadjustment1").empty().append(adjtt1);
                    $("#debitadjustment1").empty().append("0.00");
                } else if (finalAdj1 == 0) {
                    // alert(" adj1 found value = 0 " + finalAdj1);
                    $("#debitadjustment1").empty().append("0.00");
                    $("#creditadjustment1").empty().append("0.00");
                } else {
                    //alert(" adj1 value is greater than 0 " + finalAdj1);
                    $("#debitadjustment1").empty().append(finalAdj1);
                    $("#creditadjustment1").empty().append("0.00");
                }

                if (finalAdj2 < 0) {
                    // alert("adj2 found less 0 " + finalAdj2);
                    $("#creditadjustment2").empty().append(adjtt2 = adjtt2.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#debitadjustment2").empty().append("0.00");
                } else if (finalAdj2 == 0) {
                    // alert("adj2 found value = 0 " + finalAdj2);
                    $("#debitadjustment2").empty().append("0.00");
                    $("#creditadjustment2").empty().append("0.00");
                } else {
                    // alert(" adj2 value is greater than 0 " + finalAdj2);
                    $("#debitadjustment2").empty().append(finalAdj2 = finalAdj2.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ","));
                    $("#creditadjustment2").empty().append("0.00");
                }


                $("#creditovertime").empty().append(ot);
                $("#debitallowance").empty().append(allowance);
                $("#debitservice").empty().append(basic);
                $("#moneytotal").empty().append(totalpr);
                $("#moneypayfor").empty().append(pr);
                $("#cutoffdates").empty().append(cutoff);
                $("#debitdate").empty().append(debtAdDte);
                $("#releasedata").empty().append(appForRel);
                $("#name").empty().append(name);
                $("#address").empty().append(address);
                $("#totaltowords").empty().append(dataresult3);


            }
        });
    });
    $.ajax({
        url: 'query/payslip-phpscript.php?getemployeelistandpaydates',
        type: 'POST',
        cache: false,
        data: {},
        dataType: 'json',
        success: function(dataresult) {
            //console.log(dataresult);
            var resultData = dataresult.data;
            var pdate = dataresult.pdates;
            var errcode = dataresult.errcode;
            if (errcode == 0) {
                var emp = '';
                var pd = '';

                $(resultData).each(function(index, item) {
                    emp += ("<option value=" + item.EmpID + ">" + item.EmpLN + " " + item.EmpFN + "</option>");
                })
                pd += "<option value='2021-09-05'>2021-09-05</option>"
                $(pdate).each(function(index, item) {
                    pd += "<option value=" + item.PYDate + ">" + item.PYDate + "</option>"
                })
                $("#pdates").empty().append(pd);
                $("#employeelist").empty();
                $("#employeelist").append(emp);

            }
        }
    });
});