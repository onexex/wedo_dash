<?php if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_SESSION['id']) && $_SESSION['id']!="0"){}
  else{ header ('location: login.php'); }
?>
<?php
    include 'w_conn.php';
      date_default_timezone_set("Asia/Manila");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php if ($_SESSION['CompanyName']==""){ echo "Dashboard"; } else{ echo "Payslip Management System"; } ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Functional libs (Bootstrap modals + existing module JS) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.4.0/jquery.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- WeDo design system (loaded AFTER bootstrap so it wins) -->
    <link rel="stylesheet" href="assets/css/wedo-theme.css">

    <script type="text/javascript" src="assets/js/script.js"></script>
    <script type="text/javascript" src="assets/js/payslip.js"></script>

    <script>
        /* IC voucher print: clone #toprint1 into .printme, apply the print-only
           column widths/floats, then write it to a fresh window. The .css() hooks
           below (.inforight/.infoleft/.journal*/.border*) must stay on the voucher. */
        $(function () {
            var myStyle = '<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" />';

            $('#btnprint').on('click', function () {
                var pdates = $('#pdates').val();
                if (pdates === '') { alert('Please specify Pay date.'); return false; }

                $('#toprint1').clone().appendTo('.printme');
                $('#toprint1').hide();
                $('body').addClass('printing');
                var w = window.open(null, 'Print_Page', 'scrollbars=no');

                $('label').css('font-size', '8px');
                $('p').css('font-size', '8px');
                $('h5').css('font-size', '8px');

                // preparation (right column) + payee (left column)
                $('.inforight').css({ 'float': 'left', 'padding-left': '45px', 'width': '45%' });
                $('.infoleft').css({ 'padding-left': '25px', 'float': 'left', 'width': '55%' });

                // particulars / amount split
                $('.particularammount').css({ 'float': 'left', 'width': '50%' });
                $('.border').css({ 'border-right': 'solid', 'border-width': '0.1px' });

                // payment block
                $('.amountcol1').css({ 'float': 'left', 'width': '50%', 'padding-top': '25px', 'padding-left': '25px' });
                $('.amountcol').css('padding-top', '45px');

                // journal columns
                $('.journal').css({ 'float': 'left', 'padding-left': '10px', 'width': '15%' });
                $('.journal1').css({ 'float': 'left', 'width': '35%' });
                $('.journal2').css({ 'float': 'left', 'width': '25%' });
                $('.journal3').css({ 'float': 'left', 'width': '25%' });
                $('.journallast').css({ 'float': 'left', 'width': '100%' });
                $('.borderleft').css({ 'border-left': 'solid', 'border-width': '0.1px' });

                w.document.write(myStyle + jQuery('.printme').html());
                w.document.close();
                w.print();

                $('body').removeClass('printing');
                $('.printme').empty().hide();
                $('#toprint1').show();
            });
        });
    </script>

    <style type="text/css">
        @media print { body { font-family: tahoma; margin: 5%; } }

        p.paycutoff { font-size: 10pt; line-height: 10pt; padding-left: 100px; }
        .amountcol { text-align: right; padding: 0; }

        /* bordered payslip tables (was id="table1", now a class so it can repeat) */
        .table1 > tbody > tr > td, .table1 > tbody > tr > th,
        .table1 > tfoot > tr > td, .table1 > tfoot > tr > th,
        .table1 > thead > tr > td, .table1 > thead > tr > th {
            padding: 5px; font-size: 10px; border: 1px solid !important;
        }
        .table { margin-bottom: 0 !important; }

        /* reskin: controls + on-screen "paper" framing for the two vouchers */
        .ps-toolbar { display:flex; gap:16px; align-items:flex-end; flex-wrap:wrap; padding:20px; }
        .ps-toolbar .wd-field { margin-bottom:0; min-width:220px; }
        .ps-doc {
            background:#fff; border:1px solid var(--border); border-radius:var(--radius);
            box-shadow:var(--shadow); margin:0 20px 20px; padding:28px 32px;
            font-family:Tahoma, Geneva, sans-serif; color:#1a1a1a; overflow-x:auto;
        }
    </style>
</head>
<body>
    <?php $wd_active = 'payslip'; include 'includes/wd-header.php'; ?>

    <div class="wd-pagehead">
        <div>
            <h1>Payslip</h1>
            <p>Select an employee and pay date, then view or print the Incentive Cheque voucher or Bank Endorsement payslip.</p>
        </div>
    </div>

    <section class="wd-card">
        <div class="wd-card__head">
            <h3>Select payslip</h3>
        </div>

        <div class="ps-toolbar">
            <div class="wd-field">
                <label for="employeelist">Select employee</label>
                <select class="wd-select" id="employeelist"></select>
            </div>
            <div class="wd-field">
                <label for="pdates">Select pay date</label>
                <select class="wd-select" id="pdates"></select>
            </div>
            <button id="btnviewpaydata" type="button" class="wd-btn wd-btn--info"><i class="fa-solid fa-eye"></i> View IC</button>
            <button id="btnviewbe" type="button" class="wd-btn wd-btn--info"><i class="fa-solid fa-eye"></i> View BE</button>
        </div>

        <div style="padding:0 20px 20px">
            <div id="result1" class="alert" style="display:none"></div>
        </div>
    </section>

    <!-- ================= Incentive Cheque (IC) voucher ================= -->
    <div id="ic" style="display:none">
        <section class="wd-card" style="margin-top:20px">
            <div class="wd-card__head">
                <h3>Incentive Cheque Voucher</h3>
                <button id="btnprint" type="button" class="wd-btn wd-btn--success"><i class="fa-solid fa-print"></i> Print</button>
            </div>

            <div class="ps-doc">
                <!-- #toprint1 is the print source (cloned into .printme). Do not rename
                     the .inforight/.infoleft/.journal*/.border* classes or the #id hooks. -->
                <div id="toprint1">
                    <div class="col">
                        <p style="font-size: 10pt; line-height: 10pt;">WeDo BPO Inc.</p>
                        <p style="font-size: 10pt; line-height: 10pt;">Unit 1901 Antel Global Corporate Center</p>
                        <p style="font-size: 10pt; line-height: 10pt;"># 3 Julia Vargas Ave, Ortigas Business District</p>
                        <p style="font-size: 10pt; line-height: 10pt;">Pasig City 1605</p>
                    </div>

                    <div class="row" style="margin-top:30px; padding-right:15px; padding-left:15px">
                        <div class="col-lg-12" style="border-style: solid;border-width: 0.2px; padding-left:0px;">
                            <h5 style="text-align: center;">PAY VOUCHER</h5>
                        </div>
                    </div>

                    <div class="row" style="margin-top:5px; padding-right:15px; padding-left:15px;">
                        <div class="col-lg-12 dd" style="border-style: solid;border-width: 0.2px; padding-left:0px;">
                        <div class="row">
                            <div class="col-lg-6 infoleft" style="margin-top:10px; padding-left:0px;">
                                <div class="col-lg-12">
                                    <label class="labelnameadd" style="margin-right:25px;width:45px;font-weight: normal;">Payee:</label>
                                    <label id="name" style="font-weight: normal;"> </label>
                                </div>
                                <div class="col-lg-12">
                                    <label class="labelnameadd" style="margin-right:25px;width:45px;font-weight: normal;">Address:</label>
                                    <label id="address" style="font-weight: normal;"> </label>
                                </div>
                            </div>

                            <div class="col-lg-6 inforight" style="margin-top:10px; padding-left:0px;">
                                <div class="col-lg-12">
                                    <label style="margin-right:25px;width:110px;font-weight: normal;">Preparation Date:</label>
                                    <label id="debitdate" style="font-weight: normal;"> </label>
                                </div>
                                <div class="col-lg-12">
                                    <label style="margin-right:25px;width:110px;font-weight: normal;">Approval Date:</label>
                                    <label id="releasedata" style="font-weight: normal;"> </label>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>

                    <div class="row" style="margin-top:5px; padding-right:15px; padding-left:15px">
                        <div class="col-lg-12" style="border-style: solid;border-width: 0.2px; padding-left:0px;padding-right:0px">
                        <div class="row">
                            <div class="col-lg-6" style="border-right: solid;border-width: 0.2px;">
                                <div class="col-lg-12 particularammount border">
                                    <h5 style="text-align: center;">PARTICULARS</h5>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="col-lg-12 particularammount">
                                    <h5 style="text-align: center;">AMOUNT</h5>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>

                    <div class="row" style="padding-right:15px; padding-left:15px">
                        <div class="col-lg-12" style="border-style: solid; margin-bottom:0px;border-width: 0.2px;border-top:none; padding-left:0px;padding-right:0px;">
                        <div class="row amountcolrow">
                            <div class="col-lg-6" style="border-right: solid;border-width: 0.2px;">
                                <div class="col-lg-12 amountcol1 border" style="padding-left:0px;">
                                    <label>IN PAYMENT FOR:</label>
                                    <p class="paycutoff">BACK-END OFFICE PAYROLL</p>
                                    <p class="paycutoff" id="cutoffdates"> </p>
                                    <p class="amountcol2" style="float:right">TOTAL</p>
                                </div>
                            </div>
                            <div class="col-lg-6" style="float:right">
                                <div class="col-lg-12 amountcol amountcol1">
                                    <label>  </label>
                                    <p class="paycutoff" id="moneypayfor"> -</p>
                                    <p class="paycutoff"> - </p>
                                    <p id="moneytotal">-</p>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>

                    <div class="row" style="padding-right:15px; padding-left:15px">
                        <div class="col-lg-12" style="border-style: solid;border-width: 0.2px; border-top:none;  padding-left:0px;padding-right:0px">
                        <div class="row">
                            <div class="col-lg-6" style="border-right: solid;border-width: 0.2px;">
                                <div class="col-lg-12 particularammount border">
                                    <h5 style="text-align: center;">JOURNAL ENTRY</h5>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="col-lg-12 particularammount">
                                    <h5 style="text-align: center;"></h5>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>

                    <div class="row" style="padding-right:15px; padding-left:15px">
                        <div class="col-lg-12" style="border-style: solid;border-width: 0.2px; border-top:none;  padding-left:0px;padding-right:0px">
                        <div class="row">
                            <div class="col-lg-2" style="border-right: solid;border-width: 0.2px;">
                                <div class="col-lg-12 journal border">
                                    <p style="font-size: 10pt; line-height: 10pt; padding-top:10px; text-align:left">Code</p>
                                </div>
                            </div>
                            <div class="col-lg-4" style="border-right: solid;border-width: 0.2px;">
                                <div class="col-lg-12 journal1 border">
                                    <p style="font-size: 10pt; line-height: 10pt; padding-top:10px;text-align:left">Code</p>
                                </div>
                            </div>
                            <div class="col-lg-3" style="border-right: solid;border-width: 0.2px;">
                                <div class="col-lg-12 journal2 border">
                                    <p style="font-size: 10pt; line-height: 10pt; padding-top:10px; text-align:center">Debit</p>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="col-lg-12 journal3">
                                    <p style="font-size: 10pt; line-height: 10pt; padding-top:10px; text-align:center">Credit</p>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>

                    <div class="row" style="padding-right:15px; padding-left:15px">
                        <div class="col-lg-12" style="border-style: solid;border-width: 0.2px; border-top:none;  padding-left:0px;padding-right:0px">
                        <div class="row">
                            <div class="col-lg-2" style="border-right: solid;border-width: 0.2px;">
                                <div class="col-lg-12 journal border">
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">-</p>
                                </div>
                            </div>
                            <div class="col-lg-4" style="border-right: solid;border-width: 0.2px;">
                                <div class="col-lg-12 journal1 border">
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">Service</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">Allowance</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">Overtime</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">Adjustment 1</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">Adjustment 2</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">Absences/Tardiness</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">SSS Payable</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">Philhealth Payable</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">Pag-Ibig Payable</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">SSS Loan Payable</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">Pag-Ibig Loans Payable</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">W/Taxes Payable</p>
                                    <p style="font-size: 10pt; line-height: 10pt;text-align:left">CASH IN BANK</p>
                                </div>
                            </div>
                            <div class="col-lg-3" style="border-right: solid;border-width: 0.2px;">
                                <div class="col-lg-12 journal2 border">
                                    <p id="debitservice" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="debitallowance" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="debitovertime" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="debitadjustment1" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="debitadjustment2" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="debittardiness" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="debitsss" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="debitphilhealth" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="debitpagibig" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="debitsssloan" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="debitpagibigloan" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="debittax" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="debitcash" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="col-lg-12 journal3">
                                    <p id="creditservice" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="creditallowance" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="creditovertime" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="creditadjustment1" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="creditadjustment2" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="credittardiness" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="creditsss" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="creditphilhealth" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="creditpagibig" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="creditsssloan" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="creditpagibigloan" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="credittax" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                    <p id="creditcash" style="font-size: 10pt; line-height: 10pt;text-align:right">-</p>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>

                    <div class="row" style="padding-right:15px; padding-left:15px">
                        <div class="col-lg-12" style="border-style: solid;border-width: 0.2px; border-top:none;  padding-left:0px;padding-right:0px">
                        <div class="row">
                            <div class="col-lg-2">
                                <div class="col-lg-12 journal">
                                    <p style="font-size: 10pt; line-height: 10pt; padding-top:10px; text-align:left"></p>
                                </div>
                            </div>
                            <div class="col-lg-4" style="border-right: solid;border-width: 0.2px;">
                                <div class="col-lg-12 journal1">
                                    <p style="font-size: 10pt; line-height: 10pt; padding-top:10px;text-align:left"></p>
                                </div>
                            </div>
                            <div class="col-lg-3" style="border-right: solid;border-width: 0.2px;">
                                <div class="col-lg-12 journal2 borderleft border">
                                    <p id="debittotal" style="font-size: 10pt; line-height: 10pt; padding-top:10px; text-align:right">-</p>
                                </div>
                            </div>
                            <div class="col-lg-3">
                                <div class="col-lg-12 journal3">
                                    <p id="credittotal" style="font-size: 10pt; line-height: 10pt; padding-top:10px; text-align:right">-</p>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>

                    <div class="row" style="padding-right:15px; padding-left:15px">
                        <div class="col-lg-12" style="border-style: solid;border-width: 0.2px; border-top:none;  padding-left:0px;padding-right:0px">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="col-lg-12 journallast">
                                    <label style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left;padding-left:110px; width: 233px;">Amount in Words:</label> <label id="totaltowords" style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left;padding-left:50px;">-</label> <br>
                                    <label style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left;padding-left:110px; width: 233px;">Bank:</label> <label id="bankinfo" style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left;padding-left:50px;">-</label> <br>
                                    <label style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left;padding-left:110px; width: 233px;">Check Number:</label> <label id="checknumber" style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left;padding-left:50px;">DA/CASH</label> <br>
                                    <label style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left;padding-left:110px; width: 233px;">Expense Desciption:</label> <label id="description" style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left;padding-left: 50px;">-</label> <br>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>

                    <div class="row" style="padding-top:15px">
                        <div class="col-lg-12">
                        <div class="row">
                            <div class="col-lg-12">
                                <div class="col-lg-12 journallast">
                                    <label style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left">System-generated payslip</label> <br>
                                    <label style="font-size: 10pt; font-weight:normal; line-height: 10pt;text-align:left">Approved by the Office of the General Manager</label> <br>
                                </div>
                            </div>
                        </div>
                        </div>
                    </div>

                    <br><br><br><br>
                </div>
            </div>
        </section>
    </div>

    <!-- ================= Bank Endorsement (BE) payslip ================= -->
    <div id="be" style="display:none">
        <section class="wd-card" style="margin-top:20px">
            <div class="wd-card__head">
                <h3>Bank Endorsement Payslip</h3>
                <button id="btnprintbe" type="button" class="wd-btn wd-btn--success"><i class="fa-solid fa-print"></i> Print</button>
            </div>

            <div class="ps-doc">
                <!-- #printbe is the print source (body-swapped by payslip.js). -->
                <div id="printbe">
                    <div>
                        <img src="assets/images/logos/WeDo.png" style="width:100px" valign="middle" vspace="6" hspace="6"/> <br>
                    </div>
                    <div class="col">
                        <p style="font-size: 10pt; line-height: 10pt;">WeDo BPO Inc.</p>
                        <p style="font-size: 10pt; line-height: 10pt;">Unit 1901 Antel Global Corporate Center ,# 3 Julia Vargas Ave, Ortigas Business District Pasig City 1605</p>
                    </div>
                    <br>
                    <br>
                    <div class="col">
                        <p id="namebe" style="font-size: 10pt; line-height: 10pt;"></p>
                        <p id="addressbe" style="font-size: 10pt; line-height: 10pt;"></p>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                        <table class="table table1" width="100%">
                        <thead>
                            <tr>
                                <th style="border:none !important" width="20%"></th>
                                <th style="border:none !important" width="30%"></th>
                                <th style="border:none !important" width="10%"></th>
                                <th style="border:none !important" width="20%"></th>
                                <th style="border:none !important" width="20%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td scope="row" width="20%">Designation</td>
                                <td id="designation" colspan="2"></td>
                                <td>Pay Begin Date:</td>
                                <td id="begindate"></td>
                            </tr>
                            <tr>
                                <td scope="row" width="20%">Position:</td>
                                <td id="position" colspan="2"></td>
                                <td>Pay End Date: </td>
                                <td id="enddate"></td>
                            </tr>
                            <tr style="border-bottom:solid 1px">
                                <td style="border-bottom:solid 1px" scope="row" width="20%">Department:</td>
                                <td id="department" style="border-bottom:solid 1px" colspan="2"></td>
                                <td style="border-bottom:solid 1px">Advice Date:</td>
                                <td id="advicedate" style="border-bottom:solid 1px"></td>
                            </tr>
                        </tbody>
                        </table>

                        <br>
                        <table class="table table1" width="100%">
                        <thead>
                            <tr>
                                <th style="border:none !important" width="20%"></th>
                                <th style="border:none !important" width="10%"></th>
                                <th style="border:none !important" width="15%">EARNINGS</th>
                                <th style="border:none !important" width="15%"></th>
                                <th style="border:none !important" width="20%"></th>
                                <th style="border:none !important" width="10%">TAXES</th>
                                <th style="border:none !important" width="10%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th style="text-align:center" scope="row">Description</th>
                                <th style="text-align:center" scope="row">Hrs</th>
                                <th style="text-align:center" scope="row">Current</th>
                                <th style="text-align:center" scope="row">YTD</th>
                                <th style="text-align:center" scope="row">Description</th>
                                <th style="text-align:center" scope="row">Current</th>
                                <th style="text-align:center" scope="row">YTD</th>
                            </tr>
                            <tr>
                                <td scope="row">Basic Pay</td>
                                <td scope="row"></td>
                                <td style="text-align: right" id="basiccurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="basicytd" scope="row">0.00</td>
                                <td scope="row">Withholding:</td>
                                <td style="text-align: right" id="witholdingcurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="witholdingytd" scope="row">0.00</td>
                            </tr>
                            <tr>
                                <td scope="row">Allowance</td>
                                <td scope="row"></td>
                                <td style="text-align: right" id="allowancecurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="allowanceytd" scope="row">0.00</td>
                                <th scope="row"></th>
                                <td scope="row"></td>
                                <td scope="row"></td>
                            </tr>
                            <tr>
                                <td scope="row">OT/Holiday Pay</td>
                                <td style="text-align: right" id="othrs" scope="row">0</td>
                                <td style="text-align: right" id="otcurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="otytd" scope="row">0.00</td>
                                <th scope="row"></th>
                                <td scope="row"></td>
                                <td scope="row"></td>
                            </tr>
                            <tr>
                                <td>Accumulated 13 Month Pay</td>
                                <td scope="row"></td>
                                <td style="text-align: right" id="monthpaycurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="monthpayytd" scope="row">0.00</td>
                                <th scope="row"></th>
                                <td scope="row"></td>
                                <td scope="row"></td>
                            </tr>
                            <tr>
                                <th scope="row">Total</th>
                                <td scope="row"></td>
                                <td style="text-align: right" id="earningstotalcurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="earningstotalytd" scope="row">0.00</td>
                                <th scope="row">Total</th>
                                <td style="text-align: right" id="taxestotalcurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="taxestotalytd" scope="row">0.00</td>
                            </tr>
                        </tbody>
                        </table>

                        <table class="table table1" width="100%" style="margin-top:0px">
                        <thead style="display:none">
                            <tr>
                                <th style="border:none !important" width="12%"></th>
                                <th style="border:none !important" width="7%"></th>
                                <th style="border:none !important" width="7%"></th>
                                <th style="border:none !important" width="10%"></th>
                                <th style="border:none !important" width="12%"></th>
                                <th style="border:none !important" width="7%"></th>
                                <th style="border:none !important" width="10%"></th>
                                <th style="border:none !important" width="12%"></th>
                                <th style="border:none !important" width="7%"></th>
                                <th style="border:none !important" width="10%"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th style="border:none !important; text-align:center" colspan="4" scope="row">BEFORE-TAX DEDUCTIONS</th>
                                <th style="border:none !important; text-align:center" colspan="3" scope="row">AFTER-TAX DEDUCTIONS</th>
                                <th style="border:none !important; text-align:center" colspan="3" scope="row">EMPLOYER PAID BENEFITS</th>
                            </tr>
                            <tr>
                                <th style="text-align:center" scope="row">Description</th>
                                <th style="text-align:center" scope="row">Hrs</th>
                                <th style="text-align:center" scope="row">Current</th>
                                <th style="text-align:center" scope="row">YTD</th>
                                <th style="text-align:center" scope="row">Description</th>
                                <th style="text-align:center" scope="row">Current</th>
                                <th style="text-align:center" scope="row">YTD</th>
                                <th style="text-align:center" scope="row">Description</th>
                                <th style="text-align:center" scope="row">Current</th>
                                <th style="text-align:center" scope="row">YTD</th>
                            </tr>
                            <tr>
                                <td scope="row">Absences (Basic)</td>
                                <td style="text-align: right" id="absenceshrs" scope="row">0.00</td>
                                <td style="text-align: right" id="absencescurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="absencesytd" scope="row">0.00</td>
                                <td scope="row">SSS Contri</td>
                                <td style="text-align: right" id="ssscurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="sssytd" scope="row">0.00</td>
                                <td scope="row">SSS-ER</td>
                                <td style="text-align: right" id="sssercurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="ssserytd" scope="row">0.00</td>
                            </tr>
                            <tr>
                                <td scope="row">Management Fee</td>
                                <td scope="row"></td>
                                <td style="text-align: right" id="managementcurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="managementytd" scope="row">0.00</td>
                                <td scope="row">Philhealth Contri</td>
                                <td style="text-align: right" id="philcurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="philytd" scope="row">0.00</td>
                                <td scope="row">Philhealth ER</td>
                                <td style="text-align: right" id="philercurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="philerytd" scope="row">0.00</td>
                            </tr>
                            <tr>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row">Pagibig Contri</td>
                                <td style="text-align: right" id="picurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="piytd" scope="row">0.00</td>
                                <td scope="row">Pagibig ER</td>
                                <td style="text-align: right" id="piercurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="pierytd" scope="row">0.00</td>
                            </tr>
                            <tr>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row">SSS Loan </td>
                                <td style="text-align: right" id="sssloancurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="sssloanytd" scope="row">0.00</td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                            </tr>
                            <tr>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row">Pagibig Loan </td>
                                <td style="text-align: right" id="piloancurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="piloanytd" scope="row">0.00</td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                            </tr>
                            <tr>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row">Absences(Allowance)</td>
                                <td style="text-align: right" id="absencesallowcurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="absencesallowytd" scope="row">0.00</td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                            </tr>
                            <tr>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row">Advances/Adjustment 1</td>
                                <td style="text-align: right" id="adj1current" scope="row">0.00</td>
                                <td style="text-align: right" id="adj1ytd" scope="row">0.00</td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                            </tr>
                            <tr>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row">Advances/Adjustment 2</td>
                                <td style="text-align: right" id="adj2current" scope="row">0.00</td>
                                <td style="text-align: right" id="adj2ytd" scope="row">0.00</td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                                <td scope="row"></td>
                            </tr>
                            <tr>
                                <th style="border:none !important" scope="row">Total</th>
                                <td style="border:none !important" scope="row"></td>
                                <td id="bftaxcurrent" style="border:none !important;text-align: right" scope="row">0.00</td>
                                <td id="btaxytd" style="border:none !important ;text-align: right" scope="row">0.00</td>
                                <th style="border:none !important" scope="row">Total</th>
                                <td id="aftaxcurrent" style="border:none !important;text-align: right" scope="row">0.00</td>
                                <td id="aftaxafter" style="border:none !important ;text-align: right" scope="row">0.00</td>
                                <th style="border:none !important" scope="row">Total</th>
                                <td id="epbcurrent" style="border:none !important ;text-align: right" scope="row">0.00</td>
                                <td id="epbytd" style="border:none !important ;text-align: right" scope="row">0.00</td>
                            </tr>
                        </tbody>
                        </table>

                        <br>
                        <table class="table table1" width="100%">
                        <thead>
                            <tr>
                                <th width="20%"></th>
                                <th style="text-align:center" width="20%">TOTAL GROSS</th>
                                <th style="text-align:center" width="20%">TOTAL TAXES</th>
                                <th style="text-align:center" width="20%">TOTAL DEDUCTIONS</th>
                                <th style="text-align:center" width="20%">PAY RECEIVABLE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr style="border: 1px solid">
                                <th scope="row">Current</th>
                                <td style="text-align: right" id="totalgrosscurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="totaltaxescurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="totaldeductioncurrent" scope="row">0.00</td>
                                <td style="text-align: right" id="recievablecurrent" scope="row">0.00</td>
                            </tr>
                            <tr style="border: 1px solid">
                                <th scope="row">YTD</th>
                                <td style="text-align: right" id="totalgrossytd" scope="row">0.00</td>
                                <td style="text-align: right" id="totaltaxesytd" scope="row">0.00</td>
                                <td style="text-align: right" id="totaldeductionytd" scope="row">0.00</td>
                                <td style="text-align: right" id="recievableytd" scope="row">0.00</td>
                            </tr>
                            <tr>
                                <th style="border:none !important" scope="row"></th>
                                <td style="border:none !important" scope="row"></td>
                                <td style="border:none !important" scope="row"></td>
                                <td style="border:none !important" scope="row"></td>
                                <td style="border:none !important" scope="row"></td>
                            </tr>
                            <tr>
                                <th style="border:none !important" scope="row"></th>
                                <td style="border:none  !important" scope="row"></td>
                                <td style="border:none  !important" scope="row"></td>
                                <td style="border:none  !important" scope="row"></td>
                                <td style="border:none  !important" scope="row"></td>
                            </tr>
                            <tr>
                                <th style="border:none  !important" colspan="3" scope="row">Received by: <label id="namerecieved"></label></th>
                                <td style="border:none  !important" scope="row"></td>
                                <th style="border:none  !important" scope="row">Date: <label id="datenow"></label></th>
                            </tr>
                        </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- IC print clone target (populated then emptied by the head print handler) -->
    <div class="printme" style="display:none"></div>

    <?php include 'includes/wd-footer.php'; ?>
</body>
</html>
