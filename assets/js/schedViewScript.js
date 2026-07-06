$(document).ready(function () {

    // Refresh the schedule table for the chosen employee + date range.
    // Query-SchedView returns a fresh <thead> + <tbody id="darviewer">, which
    // replaces the inner HTML of the #tab table.
    $(document).on('click', '#viewsched', function () {
        var emp   = $('#empcompid').val();
        var date1 = $('#dtp1').val();
        var date2 = $('#dtp2').val();

        $.ajax({
            url: 'Query-SchedView',
            type: 'POST',
            data: { emp: emp, date1: date1, date2: date2 },
            beforeSend: function () {
                $('#viewsched').prop('disabled', true);
                $('#darviewer').html('<tr><td colspan="6" class="text-center" style="padding:18px">Loading&hellip;</td></tr>');
            },
            success: function (res) {
                $('#tab').html(res);
            },
            error: function (xhr, status, err) {
                $('#darviewer').html('<tr><td colspan="6" class="text-center" style="padding:18px">Unable to load schedules.</td></tr>');
                alert('Unable to load schedules: ' + (err || status));
            },
            complete: function () {
                $('#viewsched').prop('disabled', false);
            }
        });
    });

});
