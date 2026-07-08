$(document).ready(function () {

    var allPayees = [];

    /* escape a value for safe HTML insertion */
    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

    /* single reusable flash alert (replaces the four copy-pasted blocks) */
    function showAlert(type, msg) {
        var $r = $('#result');
        var head = (type === 'success') ? 'Message! ' : 'Warning! ';
        $r.stop(true, true)
          .attr('class', 'alert alert-' + type)
          .html('<button type="button" class="close" aria-label="close">&times;</button><strong>' + head + '</strong>' + msg)
          .show().css('opacity', 1);
        clearTimeout($r.data('timer'));
        $r.data('timer', setTimeout(function () {
            $r.fadeTo(400, 0).slideUp(300, function () { $(this).hide().css('opacity', 1); });
        }, 2500));
    }
    $(document).on('click', '#result .close', function () {
        $('#result').stop(true, true).slideUp(200, function () { $(this).hide().css('opacity', 1); });
    });

    /* render the (optionally filtered) list + update the count pill */
    function render() {
        var q = $.trim($('#payeefilter').val()).toLowerCase();
        var rows = '', shown = 0;
        $.each(allPayees, function (i, item) {
            if (q && (item.payee + ' ' + item.can).toLowerCase().indexOf(q) === -1) { return; }
            shown++;
            rows += "<tr>"
                 + "<td class='idBarcode'><b>" + esc(item.payee) + "</b></td>"
                 + "<td>" + esc(item.can) + "</td>"
                 + "<td style='text-align:right'>"
                 +   "<button value='" + esc(item.id) + "' id='delete' class='wd-iconbtn' title='Delete payee'><i class='fa-solid fa-trash'></i></button>"
                 + "</td>"
                 + "</tr>";
        });
        if (!rows) {
            rows = "<tr class='pr-empty'><td colspan='3'>" +
                   (allPayees.length ? "No payees match your filter." : "No payees yet — add one above.") + "</td></tr>";
        }
        $('#tblpayeereg').html(rows);
        $('#payeecount').text(q ? (shown + ' / ' + allPayees.length) : allPayees.length);
    }

    function loaddata() {
        $.ajax({
            url: 'query/payeereg-phpscript.php?getpayeelist',
            type: 'post', cache: false, data: {}, dataType: 'json',
            error: function (xhr) { alert(xhr.responseText); },
            success: function (res) { allPayees = res.payee || []; render(); }
        });
    }

    $(document).on('keyup', '#payeefilter', render);

    /* Enter in either field submits the add form */
    $(document).on('keypress', '#payee, #can', function (e) {
        if (e.which === 13) { e.preventDefault(); $('#store').click(); }
    });

    $(document).on('click', '#store', function () {
        var payee = $.trim($('#payee').val());
        var can = $.trim($('#can').val());
        if (payee === '') { showAlert('danger', 'Payee is required!'); $('#payee').focus(); return; }
        if (can === '') { showAlert('danger', 'Customer Account Number is required!'); $('#can').focus(); return; }

        $.ajax({
            url: 'query/payeereg-phpscript.php?store',
            type: 'post', cache: false, data: { payee: payee, can: can }, dataType: 'json',
            error: function (xhr) { alert(xhr.responseText); },
            success: function (res) {
                if (res.errinsert == 0) { showAlert('danger', 'Something went wrong while saving.'); return; }
                showAlert('success', 'Payee saved successfully!');
                $('#payee').val('');
                $('#can').val('');
                loaddata();
            }
        });
    });

    $(document).on('click', '#delete', function () {
        var id = $(this).val();
        if (!confirm('Are you sure you want to delete this payee? This cannot be undone.')) { return; }
        $.ajax({
            url: 'query/payeereg-phpscript.php?remove',
            type: 'post', cache: false, data: { id: id }, dataType: 'json',
            error: function (xhr) { alert(xhr.responseText); },
            success: function (res) {
                if (res.errinsert == 0) { showAlert('danger', 'Something went wrong while deleting.'); return; }
                showAlert('success', 'Payee removed.');
                loaddata();
            }
        });
    });

    loaddata();
});
