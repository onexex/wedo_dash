// 1. Digital Clock Logic
function startTime() {
    var today = new Date();
    var h = today.getHours();
    var m = today.getMinutes();
    var s = today.getSeconds();
    var am_pm = today.getHours() >= 12 ? "PM" : "AM";
    
    // Convert to 12-hour format
    h = h % 12;
    h = h ? h : 12; 
    
    m = checkTime(m);
    s = checkTime(s);
    
    // Target the IDs in your new modern layout
    if(document.getElementById("sec")) {
        document.getElementById("sec").innerHTML = ":" + s + " " + am_pm;
    }
    if(document.getElementById("hr-mn")) {
        document.getElementById("hr-mn").innerHTML = h + ":" + m;
    }
    
    // Set Date
    var months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
    if(document.getElementById("dtnow")) {
        document.getElementById("dtnow").innerHTML = months[today.getMonth()] + " " + today.getDate() + ", " + today.getFullYear();
    }
    
    setTimeout(startTime, 1000);
}

function checkTime(i) {
    if (i < 10) { i = "0" + i; }
    return i;
}

// 2. Toggle Password Visibility
function myFunction() {
    var x = document.getElementById("pass");
    if (x.type === "password") {
        x.type = "text";
    } else {
        x.type = "password";
    }
}

// 3. Login Ajax Logic
$(document).ready(function () {
    // Trigger login on button click
    $(".btnsubmit").click(function () {
        performLogin();
    });

    // Trigger login on "Enter" key
    $("#uname, #pass").keypress(function (event) {
        if (event.keyCode == 13) {
            performLogin();
        }
    });

    function performLogin() {
        var uname = $("#uname").val();
        var pass = $("#pass").val();
        var errorMsg = $("#error-msg");

        if (uname == "" || pass == "") {
            errorMsg.text("Please enter both username and password.");
            errorMsg.fadeIn();
            return false;
        }

        // Use the serialized form data
        var data = $(".loginform").serialize();

        $.ajax({
            url: "query/query-login.php",
            data: data,
            type: "POST",
            beforeSend: function() {
                $(".btnsubmit").text("Verifying...").prop("disabled", true);
            },
            success: function (data) {
                $(".btnsubmit").text("Sign In").prop("disabled", false);
                
                if (data == 0) {
                    errorMsg.text("Incorrect Username!");
                    errorMsg.fadeIn();
                } else if (data == 1) {
                    errorMsg.text("Incorrect Password!");
                    errorMsg.fadeIn();
                } else if (data == 7) {
                    location.replace("questionnaire.php");
                } else {
                    // Success - reload to go to index.php
                     location.reload(true);

                }
            },
            error: function() {
                $(".btnsubmit").text("Sign In").prop("disabled", false);
                errorMsg.text("Server Error. Please try again.");
                errorMsg.fadeIn();
            }
        });
    }
});