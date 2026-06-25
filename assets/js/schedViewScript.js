$(document).ready(function(){ 

    $(document).on('click', '#viewsched', function(e){
        var date1 = $('#dtp1').val();
        var date2 = $('#dtp2').val();
        var emp = $('#empcompid').val();

        $.ajax({
            url:'Query-SchedView',
            type:'POST',
            data: { emp : emp , date1 : date1, date2 : date2 },
            success:function(res){
                $("#tab").empty();
                $("#tab").append(res);
                var xmlhttp = new XMLHttpRequest();            
                xmlhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && this.status == 200) {
                        document.getElementById("tab").innerHTML = this.responseText;
                        // modal.style.display = "none";
                    }
                };
            },
            error: function(err, msg) {
                alert (msg);
            }
        });

        // alert(emp);

    });


    // $("#viewlilo").click(function(){ 
    //     var modal = document.getElementById("thislilomodal");
    //     //    modal.style.display = "block"; 
    //     var vl = ($("#empcompid").val());
    //     var dtfr = ($("#dtp1").val()); 
    //     var dtto = ($("#dtp2").val()); 


    //     $.ajax({
    //         url:'Query-LiloView',
    //         type:'post',
    //         data: { Eid : vl , dtfrom : dtfr, dtto : dtto },
    //         success:function(res){
    //             // console.log(res);
    //             // return false;
    //             modal.style.display = "none"; 
    //             $("#tab").empty();
    //             $("#tab").append(res);
    //             var xmlhttp = new XMLHttpRequest();            
    //             xmlhttp.onreadystatechange = function() {
    //                 if (this.readyState == 4 && this.status == 200) {
    //                     document.getElementById("tab").innerHTML = this.responseText;
    //                     modal.style.display = "none";
    //                 }
    //             };
    //             xmlhttp.open("GET", "Query-LiloView?eid=" + vl + "&dfrom=" + dtfr + "&dto=" + dtto, true);
    //             xmlhttp.send();
    //         }
    //     });
    // });
})