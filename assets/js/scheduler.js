$(document).ready(function () {

    var DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /* escape a value for safe insertion as HTML text */
    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

    function csrf() {
        $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') } });
    }

    /* show the result banner inside the create modal */
    function showResult(type, msg) {
        $('#schedresult')
            .removeClass('alert-success alert-danger alert-warning')
            .addClass('alert-' + type)
            .html(msg)
            .show();
    }

    /* refresh the count of ticked employees in the picker */
    function updateEmpCount() {
        $('#empselcount').text($('#employee .sch-empcb:checked').length);
    }

    /* ------------------------------------------------------------------ *
     *  Create modal — load employees + schedule options
     * ------------------------------------------------------------------ */
    $(document).on('click', '#newschedreg', function () {
        csrf();
        jQuery.ajax({
            url: 'query/scheduler-phpscript.php?loaddata',
            method: 'POST',
            data: {},
            cache: false,
            dataType: 'json',
            error: function (xhr) { alert(xhr.responseText); },
            success: function (res) {
                if (res.errcode !== 0) {
                    $('#employee').html("<div class='sch-empempty'>No active employees found.</div>");
                    return;
                }

                /* employee checkbox list */
                var emp = '';
                $(res.data).each(function (index, item) {
                    emp += "<label class='sch-empitem'>"
                         + "<input type='checkbox' class='sch-empcb' value='" + esc(item.EmpID) + "'>"
                         + "<span>" + esc(item.EmpLN) + ", " + esc(item.EmpFN) + "</span>"
                         + "</label>";
                });
                $('#employee').html(emp || "<div class='sch-empempty'>No active employees found.</div>");

                /* shift options shared by every day select */
                var sched = "<option value='1'>Choose&hellip;</option>";
                sched += "<option value='0'>Rest Day</option>";
                $(res.data2).each(function (index, x) {
                    if (String(x.WorkSchedID) === '0') { return; }   // skip the built-in Rest/Day row (added above)
                    sched += "<option value='" + esc(x.WorkSchedID) + "'>" + esc(x.TimeFrom) + " " + esc(x.TimeTo) + "</option>";
                });
                $.each(DAYS, function (i, d) { $('#' + d).html(sched); });

                /* quick-fill select: same shifts, but its own placeholder */
                var quick = "<option value=''>&mdash; choose a shift &mdash;</option>";
                quick += "<option value='0'>Rest Day</option>";
                $(res.data2).each(function (index, x) {
                    if (String(x.WorkSchedID) === '0') { return; }
                    quick += "<option value='" + esc(x.WorkSchedID) + "'>" + esc(x.TimeFrom) + " " + esc(x.TimeTo) + "</option>";
                });
                $('#setalldays').html(quick);

                /* reset picker chrome */
                $('#empfilter').val('');
                $('#empselectall').prop('checked', false);
                $('#employee .sch-empitem').removeClass('is-hidden');
                updateEmpCount();
            }
        });
    });

    /* filter the employee list */
    $(document).on('keyup', '#empfilter', function () {
        var q = $(this).val().toLowerCase();
        $('#employee .sch-empitem').each(function () {
            var hit = $(this).text().toLowerCase().indexOf(q) !== -1;
            $(this).toggleClass('is-hidden', !(q === '' || hit));
        });
    });

    /* select-all applies only to currently visible (unfiltered) rows */
    $(document).on('change', '#empselectall', function () {
        var on = $(this).prop('checked');
        $('#employee .sch-empitem:not(.is-hidden) .sch-empcb').prop('checked', on);
        updateEmpCount();
    });

    $(document).on('change', '.sch-empcb', updateEmpCount);

    /* quick fill: set every day select to the chosen shift */
    $(document).on('change', '#setalldays', function () {
        var v = $(this).val();
        if (v === '') { return; }
        $('.sch-dayselect').val(v);
        $('.formlabel').hide();   // clears any per-day required hints
    });

    /* ------------------------------------------------------------------ *
     *  Save schedule (one or many employees, same weekly schedule)
     * ------------------------------------------------------------------ */
    $(document).on('click', '#save', function (e) {
        e.preventDefault();

        var employees = $('#employee .sch-empcb:checked').map(function () { return this.value; }).get();
        var dfrom = $('#dfrom').val();
        var dto = $('#dto').val();
        var listner = 0;

        if (employees.length === 0) { $('#lblemployee').show(); listner = 1; } else { $('#lblemployee').hide(); }
        if (!dfrom) { $('#lbldfrom').show(); listner = 1; } else { $('#lbldfrom').hide(); }
        if (!dto) { $('#lbldto').show(); listner = 1; } else { $('#lbldto').hide(); }
        $.each(DAYS, function (i, d) {
            var lbl = '#lbl' + d.toLowerCase();
            if ($('#' + d).val() === '1' || $('#' + d).val() === null) { $(lbl).show(); listner = 1; }
            else { $(lbl).hide(); }
        });
        if (dfrom && dto && dto < dfrom) {
            showResult('danger', 'Effectivity "To" date cannot be earlier than the "From" date.');
            return false;
        }
        if (listner === 1) { return false; }

        var $btn = $(this).prop('disabled', true);
        csrf();
        jQuery.ajax({
            url: 'query/scheduler-phpscript.php?save',
            method: 'POST',
            data: {
                employee: employees,
                dfrom: dfrom,
                dto: dto,
                monday: $('#Monday').val(),
                tuesday: $('#Tuesday').val(),
                wednesday: $('#Wednesday').val(),
                thursday: $('#Thursday').val(),
                friday: $('#Friday').val(),
                saturday: $('#Saturday').val(),
                sunday: $('#Sunday').val()
            },
            cache: false,
            dataType: 'json',
            error: function (xhr) { $btn.prop('disabled', false); alert(xhr.responseText); },
            success: function (res) {
                $btn.prop('disabled', false);
                if (res.errcode === 0) {
                    var n = res.count || employees.length;
                    showResult('success', '<i class="fa-solid fa-circle-check"></i> Schedule saved for <b>' + n + '</b> employee(s).');
                    var form = document.getElementById('entersched');
                    if (form) { form.reset(); }
                    $('#empselectall').prop('checked', false);
                    $('#employee .sch-empitem').removeClass('is-hidden');
                    updateEmpCount();
                    loadSchedules();   // refresh the list so the new schedule appears
                    setTimeout(function () {
                        $('#schedresult').hide();
                        $('#scheduleradd').modal('hide');
                    }, 1400);
                } else {
                    showResult('danger', res.message || 'Something went wrong while saving.');
                }
            }
        });
    });

    /* ------------------------------------------------------------------ *
     *  Filter + list existing schedules
     *  (employee last name + effectivity date range + show N entries)
     * ------------------------------------------------------------------ */
    function loadSchedules() {
        csrf();
        jQuery.ajax({
            url: 'query/scheduler-phpscript.php?filter',
            method: 'POST',
            data: {
                name: $('#name').val(),
                datefrom: $('#fdatefrom').val(),
                dateto: $('#fdateto').val(),
                limit: $('#limit').val()
            },
            cache: false,
            dataType: 'json',
            error: function (xhr) { alert(xhr.responseText); },
            success: function (res) {
                if (res.errcode === 0 && res.data && res.data.length) {
                    var emp = '';
                    $(res.data).each(function (index, item) {
                        emp += "<tr>"
                             + "<td id='" + esc(item.efids) + "' class='idBarcode'><b>" + esc(item.EmpLN) + ", " + esc(item.EmpFN) + "</b></td>"
                             + "<td id='from" + esc(item.efids) + "'>" + esc(item.dfrom) + "</td>"
                             + "<td id='to" + esc(item.efids) + "'>" + esc(item.dto) + "</td>"
                             + "<td>"
                             +   "<button value='" + esc(item.EFID) + "' id='viewrecord' data-toggle='modal' data-target='#viewsched' class='wd-btn wd-btn--ghost wd-btn--sm'><i class='fa-solid fa-eye'></i> View</button>"
                             +   "<button value='" + esc(item.efids) + "' id='updateEffecDate' data-toggle='modal' data-target='#updateeffective' class='wd-btn wd-btn--primary wd-btn--sm'><i class='fa-solid fa-pen'></i> Update Effectivity</button>"
                             + "</td>"
                             + "</tr>";
                    });
                    $('#scheduler').html(emp);
                    $('#schedcount').text(res.count);
                } else {
                    $('#scheduler').html("<tr><td colspan='4' style='text-align:center;color:var(--text-3)'>No schedules match your filters.</td></tr>");
                    $('#schedcount').text('0');
                }
            }
        });
    }

    var schedTimer = null;
    $(document).on('keyup', '#name', function () {
        clearTimeout(schedTimer);
        schedTimer = setTimeout(loadSchedules, 300);   // debounce typing
    });
    $(document).on('change', '#fdatefrom, #fdateto, #limit', loadSchedules);
    $(document).on('click', '#applyfilter', loadSchedules);
    $(document).on('click', '#clearfilter', function () {
        $('#name').val('');
        $('#fdatefrom').val('');
        $('#fdateto').val('');
        $('#limit').val('10');
        loadSchedules();
    });

    loadSchedules();   // initial list on page load

    /* ------------------------------------------------------------------ *
     *  View one schedule's per-day breakdown
     * ------------------------------------------------------------------ */
    $(document).on('click', '#viewrecord', function (e) {
        var id = $(this).val();
        e.preventDefault();
        csrf();
        jQuery.ajax({
            url: 'query/scheduler-phpscript.php?viewsched',
            method: 'POST',
            data: { id: id },
            cache: false,
            dataType: 'json',
            error: function (xhr) { alert(xhr.responseText); },
            success: function (res) {
                var emp = '';
                if (res.errcode === 0) {
                    $(res.data).each(function (index, item) {
                        emp += "<tr>"
                             + "<td><b>" + esc(item.Day_s) + "</b></td>"
                             + "<td id='" + esc(item.WID) + "'>" + esc(item.TimeFrom) + " " + esc(item.TimeTo) + "</td>"
                             + "<td><button value='" + esc(item.WID) + "' id='updaterecord' data-toggle='modal' data-target='#updatetime' class='wd-btn wd-btn--primary wd-btn--sm'><i class='fa-solid fa-pen'></i> Update</button></td>"
                             + "</tr>";
                    });
                } else {
                    emp = "<tr><td colspan='3' style='text-align:center;color:var(--text-3)'>No day records found.</td></tr>";
                }
                $('#daytime').html(emp);
            }
        });
    });

    /* ------------------------------------------------------------------ *
     *  Update one day's time — load the shift options
     * ------------------------------------------------------------------ */
    $(document).on('click', '#updaterecord', function (e) {
        var id = $(this).val();
        e.preventDefault();
        csrf();
        jQuery.ajax({
            url: 'query/scheduler-phpscript.php?setupdatesched',
            method: 'POST',
            data: { id: id },
            cache: false,
            dataType: 'json',
            error: function (xhr) { alert(xhr.responseText); },
            success: function (res) {
                if (res.errcode === 0) {
                    var sched = "<option value='1'>Choose&hellip;</option>";
                    $(res.data).each(function (index, x) {
                        sched += "<option value='" + esc(x.WorkSchedID) + "'>" + esc(x.TimeFrom) + " " + esc(x.TimeTo) + "</option>";
                    });
                    $('#timedata').html(sched);
                    $('#updatetimesched').val(id);
                }
            }
        });
    });

    /* commit the new time for that day */
    $(document).on('click', '#updatetimesched', function (e) {
        var id = $(this).val();
        var timeid = $('#timedata').val();
        var me = $('#timedata option:selected').text();
        e.preventDefault();
        if (timeid === '1') { alert('Please choose a schedule!'); return; }

        csrf();
        jQuery.ajax({
            url: 'query/scheduler-phpscript.php?updateTimeNow',
            method: 'POST',
            data: { id: id, timeid: timeid, me: me },
            cache: false,
            dataType: 'json',
            error: function (xhr) { alert(xhr.responseText); },
            success: function (res) {
                if (res.errcode === 0) {
                    $('#' + id).empty().append(me);
                    $('#updatetime').modal('hide');
                    alert('Successfully updated!');
                } else {
                    alert('Something went wrong!');
                }
            }
        });
    });

    /* ------------------------------------------------------------------ *
     *  Update effectivity dates — prefill then save
     * ------------------------------------------------------------------ */
    $(document).on('click', '#updateEffecDate', function (e) {
        var id = $(this).val();
        e.preventDefault();
        csrf();
        jQuery.ajax({
            url: 'query/scheduler-phpscript.php?setEffectivity',
            method: 'POST',
            data: { id: id },
            cache: false,
            dataType: 'json',
            error: function (xhr) { alert(xhr.responseText); },
            success: function (res) {
                if (res.errcode === 0) {
                    $(res.data).each(function (index, x) {
                        $('#efdfrom').val(x.dfrom);
                        $('#efdto').val(x.dto);
                    });
                    $('#updateEffecDate1').val(id);
                    $('.formlabel').hide();
                } else {
                    alert('Something went wrong!');
                }
            }
        });
    });

    $(document).on('click', '#updateEffecDate1', function (e) {
        var id = $(this).val();
        var dfrom = $('#efdfrom').val();
        var dto = $('#efdto').val();
        var listner = 0;
        e.preventDefault();

        if (!dfrom) { $('#lblefdfrom').show(); listner = 1; } else { $('#lblefdfrom').hide(); }
        if (!dto) { $('#lblefdto').show(); listner = 1; } else { $('#lblefdto').hide(); }
        if (dfrom && dto && dto < dfrom) { alert('"Date To" cannot be earlier than "Date From".'); return; }
        if (listner === 1) { return false; }

        csrf();
        jQuery.ajax({
            url: 'query/scheduler-phpscript.php?updateEffectivity',
            method: 'POST',
            data: { id: id, dfrom: dfrom, dto: dto },
            cache: false,
            dataType: 'json',
            error: function (xhr) { alert(xhr.responseText); },
            success: function (res) {
                if (res.errcode === 0) {
                    $('#from' + id).empty().append(dfrom);
                    $('#to' + id).empty().append(dto);
                    $('#updateeffective').modal('hide');
                    alert('Effectivity successfully updated!');
                }
            }
        });
    });

});
