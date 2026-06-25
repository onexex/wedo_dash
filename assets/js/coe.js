$(document).ready(function() {

    $(document).on("click", ".printthis", function() {
        //.print();
        var printContents = document.getElementById('forprint').innerHTML;
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;


    });

    $(document).on("change", "#employeelist", function() {
        var id = $("#employeelist").val();
        $.ajax({
            url: 'query/coe-phpscript.php?getemplooyeedata',
            type: 'POST',
            cache: false,
            data: { id: id },
            dataType: 'json',
            error: function(xhr, status, error) {
                alert(xhr.responseText);
            },
            success: function(dataresult) {
                console.log(dataresult);
                var resultData = dataresult.data;

                var fullname = '';
                var position = '';
                var lastname = '';
                var datehired = dataresult.dh;
                var basic = '';
                var basicinword = '';
                var gender = '';
                var salute = "Ms. ";
                var selfintro = "her";
                var hheshe = "she"
                $(resultData).each(function(index, item) {
                    fullname = item.EmpFN + " " + item.EmpMN + " " + item.EmpLN;
                    position = item.PositionDesc;
                    lastname = item.EmpLN;
                    // datehired = item.EmpDateHired;

                    basic = parseFloat(item.EmpBasic);
                    basic = basic.toFixed(2).replace(/\B(?=(\d{3})+(?!\d)\.?)/g, ",")
                    gender = item.EmpGender;
                })
                if (gender == "Male") {
                    salute = "Mr. ";
                    selfintro = "his";
                    hheshe = "he";
                }
                basicinword = dataresult.totalPRSUM;
                $(".Salutation").empty().append(salute + " ");
                $(".selfintro").empty().append(selfintro);
                $(".heshe").empty().append(hheshe);
                $(".fullname").empty().append(fullname);
                $(".position").empty().append(position);
                $(".lastname").empty().append(lastname);
                $(".datehired").empty().append(datehired);
                $(".basic").empty().append(basicinword);

            }
        });
    });


    $.ajax({
        url: 'query/coe-phpscript.php?getemployeelist',
        type: 'POST',
        cache: false,
        data: {},
        dataType: 'json',
        success: function(dataresult) {
            console.log(dataresult);
            var resultData = dataresult.data;
            var errcode = dataresult.errcode;
            if (errcode == 0) {
                var emp = '';
                var pd = '';

                $(resultData).each(function(index, item) {
                    emp += ("<option value=" + item.EmpID + ">" + item.EmpLN + " " + item.EmpFN + "</option>");
                })


                $("#employeelist").empty();
                $("#employeelist").append(emp);

            }
        }
    });
});