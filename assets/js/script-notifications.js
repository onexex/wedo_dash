$(document).ready(function () {
  $(".btnrefreshnotif").click(function () {
    if ($("#fromdp").val() == "") {
      alert("Please Insert Date Parameters From");
    } else if ($("#todp").val() == "") {
      alert("Please Insert Date Parameters To");
    } else {
      // Query-NotifDateParam.php

      var dtfr = $("#fromdp").val();
      var dttp = $("#todp").val();
      //  $.ajax({
      //                       url:'query/Query-NotifDateParam.php',
      //                       type:'post',
      //                       data: { frd : dtfr , tdp : dttp},
      //                       success:function(data){

      //                         // $("#addob").empty();
      //                         // $("#addob").append(data);

      //                       }
      // });
      //         alert(dtfr);
      //   return false;
      var xmlhttp = new XMLHttpRequest();
      $("#addob").empty();
      xmlhttp.onreadystatechange = function () {
        if (this.readyState == 4 && this.status == 200) {
          document.getElementById("addob").innerHTML = this.responseText;
        }
      };
      xmlhttp.open(
        "GET",
        "query/Query-NotifDateParam.php?dtfrm=" + dtfr + "&dtto=" + dttp,
        true
      );
      xmlhttp.send();
    }
  });

  var dis = 1;
  $(document).on("click", ".btn-warning", function () {
    $(".btn-danger").show();
    $(".rsn ").hide();
    dis = 1;
  });

  $(document).on("click", ".btn-info", function () {
    $(".btn-danger").hide();
    var str = $(this).attr("id");
    var res = str.split("-");
    if (dis == 1) {
      var reason = $("#rs" + str).val();
      $(".rsn" + str).css("display", "block");
      dis = 2;
    } else {
      var reason = $("#rs" + str).val();
      $(".rsn" + str).css("display", "block");
      if (reason == "") {
        alert("Please input reason.");
      } else {
        if (res[0] == "EO") {
          $.ajax({
            url: "query/Query-UpdateAppDis.php?ntype=EO&id=" + res[1],
            type: "post",
            data: { reas: reason },
            success: function (data) {
              $("#modalWarning").modal("toggle");
              $("#modalWarning .alert").html("Successfully DisApproved");
              location.reload(true);
            },
          });
        }
        if (res[0] == "OB") {
          $.ajax({
            url: "query/Query-UpdateAppDis.php?ntype=OB&id=" + res[1],
            type: "post",
            data: { reas: reason },
            success: function (data) {
              $("#modalWarning").modal("toggle");
              $("#modalWarning .alert").html("Successfully DisApproved");
              location.reload(true);
              // $("#addob").empty();
              // xmlhttp.onreadystatechange = function() {
              // if (this.readyState == 4 && this.status == 200) {
              // document.getElementById("addob").innerHTML = this.responseText;
              //   }
              //   };
              // xmlhttp.open("GET", "query/Query-SearchOBEOOV.php", true);
              // xmlhttp.send();
            },
          });
        }
        if (res[0] == "HL") {
          $.ajax({
            url: "query/Query-UpdateAppDis.php?ntype=HL&id=" + res[1],
       type: "post",
            data: { reas: reason },
            success: function (data) {
                console.log(data);
            //   var text = "";

            //   if (data.uid == 1) {
            //     if (data.dd == "35") {
            //       text =
            //         "Successfully approved leave application!.\\n \\n Note!\\n \\n System will automatically over-ride status \\n once it detects the lack of leave credits for the specific employee.";
            //     } else {
            //       text = "Successfully approved leave application!";
            //     }

            //     $("#modalWarning").modal("toggle");
            //     $(this).css("background-color", "green");
            //     $("#modalWarning .alert").html(text.replace(/\\n/g, "<br>"));
            //     window.setTimeout(function () {
            //       location.reload(true);
            //     }, 6000);
            //   } else {
            //     $("#modalWarning").modal("toggle");
            //     $("#modalWarning .alert").html("Successfully Approved. ");
            //     window.setTimeout(function () {
            //       location.reload(true);
            //     }, 6000);
            //   }
            
             $("#modalWarning").modal("toggle");
              $("#modalWarning .alert").html("Successfully DisApproved");
              window.setTimeout(function () {
                location.reload(true);
              }, 2000);
            },
          });
        }
        if (res[0] == "OT") {
          $.ajax({
            url: "query/Query-UpdateAppDis.php?ntype=OT&id=" + res[1],
            type: "post",
            data: { reas: reason },
            success: function (data) {
              $("#modalWarning").modal("toggle");
              $("#modalWarning .alert").html("Successfully DisApproved");
              location.reload(true);
            },
          });
        }
        dis = 1;
      }
    }
  });

  $(document).on("click", ".btn-danger", function () {
    var str = $(this).attr("id");
    var res = str.split("-");
    if (res[0] == "EO") {
      $.ajax({
        url: "query/Query-UpdateApp.php?ntype=EO&id=" + res[1],
        type: "post",
        success: function (data) {
          $("#modalWarning").modal("toggle");
          $("#modalWarning .alert").html("Successfully Approved");
          location.reload(true);
        },
      });
    }
    if (res[0] == "OB") {
      $.ajax({
        url: "query/Query-UpdateApp.php?ntype=OB&id=" + res[1],
        type: "post",
        success: function (data) {
          alert("Successfully Approved");
          location.reload(true);
          // $("#addob").empty();
          // xmlhttp.onreadystatechange = function() {
          // if (this.readyState == 4 && this.status == 200) {
          // document.getElementById("addob").innerHTML = this.responseText;
          //   }
          //   };
          // xmlhttp.open("GET", "query/Query-SearchOBEOOV.php", true);
          // xmlhttp.send();
        },
      });
    }
     if (res[0] == "HL") {
      $(this).prop("disabled", true);
      $.ajax({
        url: "query/Query-UpdateApp.php?ntype=HL&id=" + res[1],
        method: "post",
        cache: false,
        dataType: 'json',
        success: function (data) {
            // alert(data);
            // return false;
            
          var text = "";
          if (data.uid == 1) {
            if (data.dd == "35") {
              text =
                "Successfully approved leave application!.\\n \\n Note!\\n \\n System will automatically over-ride status \\n once it detects the lack of leave credits for the specific employee.";
            } else {
              text = "Successfully approved leave application!";
            }

            $("#modalWarning").modal("toggle");
            $(this).css("background-color", "green");
            $("#modalWarning .alert").html(text.replace(/\\n/g, "<br>"));
            window.setTimeout(function () {
              location.reload(true);
            }, 6000);
          } else {
            $("#modalWarning").modal("toggle");
            $("#modalWarning .alert").html("Successfully Approved. ");
            window.setTimeout(function () {
              location.reload(true);
            }, 6000);
          }
        },
      });
    }
    if (res[0] == "OT") {
      $.ajax({
        url: "query/Query-UpdateApp.php?ntype=OT&id=" + res[1],
        type: "post",
        success: function (data) {
          $("#modalWarning").modal("toggle");
          $("#modalWarning .alert").html("Successfully Approved");

          location.reload(true);
        },
      });
    }
  });
});
