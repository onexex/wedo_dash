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

    // a Turnstile answer is single-use: get a fresh one after a failed attempt
    function resetHumanCheck() {
        if (window.turnstile) { try { window.turnstile.reset(); } catch (e) { /* widget not on the page */ } }
    }

    /* Locked by the server (too many failed sign-ins): the Sign in button stays off with a
       countdown while that username is in the box. The server refuses every request anyway
       (no password is checked while locked) — this just stops pointless clicking. Typing a
       different username lets the server decide again. */
    var lock = null;   // { user, scope: 'user' (this account) | 'ip' (this network), until (ms), timer }
    function lockedNow() {
        if (!lock || Date.now() >= lock.until) { return false; }
        return lock.scope === 'ip' || $.trim($("#uname").val()).toLowerCase() === lock.user;
    }
    function paintLock() {
        var btn = $(".btnsubmit"), errorMsg = $("#error-msg");
        if (!lockedNow()) {
            if (lock && Date.now() >= lock.until) { clearInterval(lock.timer); lock = null; errorMsg.fadeOut(); }
            btn.prop("disabled", false).text("Sign In");
            return;
        }
        var left = Math.ceil((lock.until - Date.now()) / 1000), m = Math.floor(left / 60), s = left % 60;
        btn.prop("disabled", true).text("Try again in " + m + ":" + (s < 10 ? "0" : "") + s);
        errorMsg.text(lock.scope === 'ip'
            ? "Too many failed sign-ins from this network. Please wait, then try again."
            : "Too many failed sign-ins for this account. Please wait, then try again.").show();
    }
    function startLock(seconds, scope) {
        if (lock) { clearInterval(lock.timer); }
        lock = { user: $.trim($("#uname").val()).toLowerCase(), scope: scope === 'ip' ? 'ip' : 'user',
                 until: Date.now() + seconds * 1000 };
        lock.timer = setInterval(paintLock, 1000);
        paintLock();
    }
    $("#uname").on("input", function () { if (lock) { paintLock(); } });

    function performLogin() {
        var uname = $("#uname").val();
        var pass = $("#pass").val();
        var errorMsg = $("#error-msg");
        if (lockedNow()) { paintLock(); return false; }

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
                
                var answer = $.trim(data);
                if (answer === '0' || answer === '1') {
                    // one message for both: a bot can't tell which usernames exist
                    errorMsg.text("Incorrect username or password.");
                    errorMsg.fadeIn();
                    resetHumanCheck();
                } else if (answer.indexOf('429|') === 0) {
                    var parts = answer.split('|');
                    var secs = parseInt(parts[2], 10) || (parseInt(parts[1], 10) || 15) * 60;
                    resetHumanCheck();
                    startLock(secs, parts[3]);
                } else if (answer === '428') {
                    errorMsg.text('Please complete the "I am human" check, then sign in.');
                    errorMsg.fadeIn();
                    resetHumanCheck();
                } else if (answer === '0101') {
                    errorMsg.text("This account is no longer active. Please contact HR.");
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