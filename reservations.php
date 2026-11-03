<?php
$page_title = 'Reservations';
$current_page = 'reservations';
require_once __DIR__ . '/includes/header.php';
?>
<h1>Lunch Reservations</h1>

<div class="card">
    <h2>Add a Reservation</h2>
    <form id="reservationForm">
        <div class="form-group">
            <label for="r_member_id">Select Member:</label>
            <select name="member_id" id="r_member_id" required><option value="">Select a member</option></select>
        </div>
        <div id="dateContainer">
            <div class="date-entry" style="margin-bottom:10px;">
                <div class="form-group">
                    <label>Reservation Date:</label>
                    <input type="date" class="res-date" name="res_date[]" required>
                </div>
                <div class="checkbox-group">
                    <label><input type="checkbox" class="bus-rider" name="bus_rider[]"> Bus Rider</label>
                    <label><input type="checkbox" class="eat-lunch" name="eat_lunch[]"> Eat Lunch</label>
                </div>
            </div>
        </div>
        <div class="button-group">
            <button type="button" id="addDateRow" class="secondary">+ Add Another Date</button>
            <button type="submit" class="set-button">Set Reservation(s)</button>
            <button type="button" id="cancelReservations" class="delete-button" style="display:none;">Cancel Reservation(s)</button>
            <button type="button" id="deleteReservations" class="delete-button">Delete Reservation(s)</button>
        </div>
    </form>
</div>

<hr class="section-divider">

<div class="card">
    <h2>Search Reservations</h2>

    <div class="button-group" style="margin-bottom:15px;">
        <button type="button" id="daySheetToggle">Print Day Sheet</button>
    </div>
    <div class="form-group" id="daySheetFieldWrap" style="display:none;">
        <label for="daySheetDate">Date:</label>
        <input type="date" id="daySheetDate">
        <button type="button" id="daySheetGenerate">Print</button>
    </div>

    <form id="searchForm">
        <div class="form-group">
            <label for="s_member_id">Member:</label>
            <select name="member_id" id="s_member_id"><option value="">Select a member</option></select>
        </div>
        <div class="form-group">
            <label for="s_res_date">Reservation Date:</label>
            <input type="date" name="res_date" id="s_res_date">
        </div>
        <div class="form-group">
            <label for="s_bus_rider">Bus Rider:</label>
            <select name="bus_rider" id="s_bus_rider"><option value="">All</option><option value="1">Yes</option><option value="0">No</option></select>
        </div>
        <div class="form-group">
            <label for="s_eat_lunch">Eat Lunch:</label>
            <select name="eat_lunch" id="s_eat_lunch"><option value="">All</option><option value="1">Yes</option><option value="0">No</option></select>
        </div>
        <div class="form-group">
            <label for="s_showed">Showed:</label>
            <select name="showed" id="s_showed"><option value="">All</option><option value="1">Yes</option><option value="0">No</option></select>
        </div>
        <div class="button-group">
            <button type="submit">Search</button>
            <button type="button" id="resetButton" class="reset-button">Reset</button>
            <button type="button" id="printButton" style="display:none;">Print Results</button>
            <button type="button" id="exportButton" style="display:none;">Export to CSV</button>
        </div>
    </form>

    <div id="recordCount"></div>
    <table id="resultsTable" style="display:none;">
        <thead><tr><th>Member Name</th><th>Date</th><th>Bus Rider</th><th>Eat Lunch</th><th>Showed</th><th>Walk-in</th><th>Cancelled</th></tr></thead>
        <tbody></tbody>
    </table>
    <div id="noResults" style="display:none;">No reservations found.</div>
</div>

<hr class="section-divider">

<div class="card">
    <h2>Log a Cancellation / No-Show</h2>
    <form id="cancellationLogForm">
        <div class="form-group">
            <label for="cl_member_id">Select Member:</label>
            <select name="member_id" id="cl_member_id" required><option value="">Select a member</option></select>
        </div>
        <div class="form-group">
            <label for="cl_date">Date of Cancellation:</label>
            <input type="date" name="cancel_date" id="cl_date" required>
        </div>
        <div class="form-group">
            <label for="cl_type">CX/NCNS:</label>
            <select name="type" id="cl_type" required>
                <option value="CX">CX (Cancel)</option>
                <option value="NCNS">NCNS (No Call No Show)</option>
            </select>
        </div>
        <div class="form-group">
            <label for="cl_notes">Notes:</label>
            <textarea name="notes" id="cl_notes" rows="2" style="width:100%;"></textarea>
        </div>
        <div class="checkbox-group">
            <label><input type="checkbox" name="excused" id="cl_excused" value="1"> Excused</label>
        </div>
        <div class="button-group">
            <button type="submit">Log Cancellation</button>
        </div>
    </form>
</div>

<hr class="section-divider">

<div class="card">
    <h2>Cancellation History</h2>
    <form id="cancellationSearchForm">
        <div class="form-group">
            <label for="ch_member_id">Member:</label>
            <select name="member_id" id="ch_member_id"><option value="">All</option></select>
        </div>
        <div class="form-group">
            <label for="ch_date">Date:</label>
            <input type="date" name="cancel_date" id="ch_date">
        </div>
        <div class="button-group">
            <button type="submit">Search</button>
            <button type="button" id="cancellationResetButton" class="reset-button">Reset</button>
        </div>
    </form>

    <div id="cancellationRecordCount"></div>
    <table id="cancellationResultsTable" style="display:none;">
        <thead><tr><th>Member Name</th><th>Date</th><th>CX/NCNS</th><th>Notes</th><th>CX Total</th><th>NCNS Total</th><th>Total</th></tr></thead>
        <tbody></tbody>
    </table>
    <div id="cancellationNoResults" style="display:none;">No cancellation records found.</div>
</div>

<script>
jQuery(document).ready(function($) {
    // Format a plain "YYYY-MM-DD" (or "YYYY-MM-DD HH:MM:SS") date string for
    // display WITHOUT going through the JS Date/timezone machinery. Using
    // `new Date(dateString)` on a date-only string parses it as UTC midnight,
    // then `.toLocaleDateString()` renders it in the browser's local timezone
    // — which rolls it back a day for any US timezone. This avoids that.
    function formatDateStr(dateStr) {
        if (!dateStr) return '';
        const datePart = String(dateStr).split(' ')[0].split('T')[0];
        const parts = datePart.split('-');
        if (parts.length !== 3) return datePart;
        const [y, m, d] = parts;
        return `${parseInt(m, 10)}/${parseInt(d, 10)}/${y}`;
    }

    function loadMembers(selectEl) {
        $.getJSON('api/reservations.php', { action: 'members' }, function(response) {
            if (response.success) {
                const select = $(selectEl);
                const placeholder = select.find('option').first();
                select.empty().append(placeholder);
                response.data.forEach(m => select.append(`<option value="${m.id}">${m.last_name}, ${m.first_name}</option>`));
            }
        });
    }
    loadMembers('#r_member_id');
    loadMembers('#s_member_id');
    loadMembers('#cl_member_id');
    loadMembers('#ch_member_id');

    // --- Print Day Sheet ---
    $('#daySheetToggle').on('click', function() {
        $('#daySheetFieldWrap').toggle();
        if ($('#daySheetFieldWrap').is(':visible') && !$('#daySheetDate').val()) {
            const now = new Date();
            $('#daySheetDate').val(now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0'));
        }
    });

    // Converts a plain "YYYY-MM-DD" renewal date to "MM/YY" (renewals only
    // track by month and year) without going through the Date/timezone
    // machinery (same reasoning as formatDateStr).
    function renewMonthYear(dateStr) {
        const parts = String(dateStr).split('-');
        if (parts.length !== 3) return dateStr;
        return `${parts[1]}/${parts[0].slice(-2)}`;
    }

    $('#daySheetGenerate').on('click', function() {
        const date = $('#daySheetDate').val();
        if (!date) { alert('Please select a date'); return; }

        $.getJSON('api/reservations.php', { action: 'day_sheet', res_date: date }, function(response) {
            if (!response.success) { alert('Error: ' + response.data.message); return; }
            const d = response.data;

            const now = new Date();
            const today = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            const todayTs = new Date(today + 'T00:00:00').getTime();
            const THIRTY_DAYS_MS = 30 * 24 * 60 * 60 * 1000;

            // Mirrors the paper form: Renew is orange+"GUEST" for a guest,
            // red+blank for a member with no renewal date on file (needs
            // paperwork), red with the date if it's expired, yellow if it
            // expires within 30 days, plain otherwise. Member Name is green
            // for a bus rider, else orange for a guest, else light blue for
            // a volunteer. Only the Reservations count up top is filled in
            // -- everything else is left blank for staff to fill by hand.
            let rowsHtml = '';
            d.rows.forEach(r => {
                let renew, renewClass;
                if (r.is_guest == 1) {
                    renew = 'GUEST'; renewClass = 'bg-orange';
                } else if (!r.renewal_date) {
                    renew = ''; renewClass = 'bg-red';
                } else {
                    renew = renewMonthYear(r.renewal_date);
                    const renewTs = new Date(r.renewal_date + 'T00:00:00').getTime();
                    if (renewTs < todayTs) { renewClass = 'bg-red'; }
                    else if ((renewTs - todayTs) <= THIRTY_DAYS_MS) { renewClass = 'bg-yellow'; }
                    else { renewClass = ''; }
                }

                let nameClass;
                if (r.bus_rider == 1) { nameClass = 'bg-green'; }
                else if (r.is_guest == 1) { nameClass = 'bg-orange'; }
                else if (r.is_volunteer == 1) { nameClass = 'bg-blue'; }
                else { nameClass = ''; }

                const name = `${r.last_name}, ${r.first_name}` + (r.is_volunteer == 1 ? ' (VOL)' : '');

                rowsHtml += `<tr>
                    <td class="${renewClass}">${renew}</td>
                    <td class="${nameClass}">${name}</td>
                    <td></td>
                    <td>CX/NCNS</td>
                    <td>${r.bus_rider == 1 ? 'Yes' : ''}</td>
                    <td></td>
                </tr>`;
            });

            let walkInsHtml = '';
            for (let i = 1; i <= 6; i++) {
                walkInsHtml += `<tr><td class="num">${i}</td><td class="name"></td></tr>`;
            }

            const printWindow = window.open('', '_blank');
            printWindow.document.write(`<html><head><title>Day Sheet - ${d.date_label}</title><style>
                /* Browsers drop background colors when printing by default
                   (to save ink) unless told otherwise -- without this, every
                   color-coding rule below would print as plain white. */
                * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                body{font-family:Arial,sans-serif;padding:20px;}
                h1{text-align:center;border:2px solid #000;padding:10px;margin:0 0 20px;font-size:22px;}
                table{border-collapse:collapse;}
                td,th{border:1px solid #000;padding:4px 10px;}
                .label{font-weight:bold;}
                .admin-row{display:flex;gap:25px;align-items:flex-start;margin-bottom:15px;}
                .admin-col table + table{margin-top:10px;}
                /* These value cells are blank on purpose -- staff fill them
                   in by hand on the printed sheet -- so without an explicit
                   width they shrink to almost nothing since there's no text
                   to size against. */
                .admin-row td:not(.label){min-width:60px;}
                /* table-layout:fixed plus explicit widths on the first
                   two columns (summing to 330px, same as the Walk Ins
                   table's num+name columns below) makes the right edge
                   of Member Name line up with the right edge of the
                   Walk Ins table's Name column. */
                .roster{width:100%;table-layout:fixed;}
                .roster th:nth-child(1), .roster td:nth-child(1){width:50px;}
                .roster th:nth-child(2), .roster td:nth-child(2){width:280px;word-wrap:break-word;}
                .roster th:nth-child(3), .roster td:nth-child(3){width:60px;}
                .roster th:nth-child(4), .roster td:nth-child(4){width:90px;}
                .roster th:nth-child(5), .roster td:nth-child(5){width:50px;}
                .roster th{background:#d9d9d9;}
                .walkins{margin-top:20px;}
                .walkins th{background:#d9d9d9;}
                .walkins td.num{text-align:center;width:30px;}
                .walkins td.name{width:300px;}
                .bg-green{background:#c6efce;}
                .bg-blue{background:#ddebf7;}
                .bg-orange{background:#fce4d6;}
                .bg-red{background:#ffc7ce;}
                .bg-yellow{background:#ffeb9c;}
                @media print { body{padding:0;} }
            </style></head><body>
                <h1>${d.date_label}</h1>
                <div class="admin-row">
                    <table>
                        <tr><td class="label">Reservations:</td><td>${d.reservation_count}</td></tr>
                        <tr><td class="label">Member:</td><td></td></tr>
                        <tr><td class="label">Guest:</td><td></td></tr>
                        <tr><td class="label">CX/NCNS:</td><td></td></tr>
                        <tr><td class="label">Walk Ins:</td><td></td></tr>
                        <tr><td class="label">Total:</td><td></td></tr>
                    </table>
                    <div class="admin-col">
                        <table>
                            <tr><td class="label">Bus Riders:</td><td></td></tr>
                            <tr><td class="label">x2 Trips:</td><td></td></tr>
                            <tr><td class="label">x1 Trips:</td><td></td></tr>
                        </table>
                        <table>
                            <tr><td class="label">Volunteers:</td><td></td></tr>
                            <tr><td class="label">Member Money:</td><td>$</td></tr>
                            <tr><td class="label">Guest Money:</td><td>$</td></tr>
                            <tr><td class="label">Total Money:</td><td>$</td></tr>
                        </table>
                    </div>
                    <table>
                        <tr><td class="label">AIS:</td><td></td></tr>
                    </table>
                </div>
                <table class="roster">
                    <thead><tr><th>Renew</th><th>Member Name</th><th>Sign</th><th>CX/NCNS</th><th>Bus</th><th>Notes</th></tr></thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
                <table class="walkins">
                    <thead><tr><th colspan="2">Walk Ins</th></tr></thead>
                    <tbody>${walkInsHtml}</tbody>
                </table>
            </body></html>`);
            printWindow.document.close();
            printWindow.onload = function() { printWindow.print(); printWindow.onafterprint = function() { printWindow.close(); }; };
        }, 'json');
    });

    $('#addDateRow').on('click', function() {
        const row = $('<div class="date-entry" style="margin-bottom:10px;">' +
            '<div class="form-group"><label>Reservation Date:</label><input type="date" class="res-date" name="res_date[]" required></div>' +
            '<div class="checkbox-group"><label><input type="checkbox" class="bus-rider" name="bus_rider[]"> Bus Rider</label>' +
            '<label><input type="checkbox" class="eat-lunch" name="eat_lunch[]"> Eat Lunch</label></div></div>');
        $('#dateContainer').append(row);
    });

    // --- Edit-in-place: when a member + date already has a reservation on
    // file, pull its Bus Rider / Eat Lunch values in so staff are editing
    // the existing record instead of unknowingly starting from blank
    // checkboxes. Re-submitting via "Set Reservation(s)" then updates that
    // record (see the 'add' action on the server, which upserts).
    function checkExistingReservation(dateEntry) {
        const member_id = $('#r_member_id').val();
        const date = dateEntry.find('.res-date').val();
        const busCheckbox = dateEntry.find('.bus-rider');
        const lunchCheckbox = dateEntry.find('.eat-lunch');
        if (!member_id || !date) return;

        $.getJSON('api/reservations.php', { action: 'get', member_id: member_id, res_date: date }, function(response) {
            if (response.success) {
                busCheckbox.prop('checked', response.data.bus_rider == 1);
                lunchCheckbox.prop('checked', response.data.eat_lunch == 1);
            } else {
                busCheckbox.prop('checked', false);
                lunchCheckbox.prop('checked', false);
            }
        });
    }

    // Before letting staff proceed with a member selected for a new
    // reservation, check whether they're currently serving an
    // unexcused-cancellation/no-show ban. If so, block it with a dialog
    // and clear the selection rather than letting a reservation be set.
    $('#r_member_id').on('change', function() {
        const member_id = $(this).val();
        if (!member_id) {
            $('.date-entry').each(function() { checkExistingReservation($(this)); });
            return;
        }
        const memberName = $('#r_member_id option:selected').text();
        $.getJSON('api/cancellations.php', { action: 'check_ban', member_id: member_id }, function(response) {
            if (response.success && response.data.banned) {
                alert(`Reservation can not be made. ${memberName} has ${response.data.count} unexcused cancellation(s) or no-show(s); they will be eligible again on ${formatDateStr(response.data.eligible_date)}.`);
                $('#r_member_id').val('');
                return;
            }
            $('.date-entry').each(function() { checkExistingReservation($(this)); });
        }, 'json').fail(function() {
            // Don't fail silently: if the ban check itself couldn't run
            // (e.g. the cancellation_log table is missing, or a server
            // error), say so loudly rather than quietly letting the
            // reservation through unchecked.
            alert('Could not check cancellation/no-show history for ' + memberName + ' -- the reservation cannot be verified right now. Please try again, or check the Cancellation History section manually before proceeding.');
            $('#r_member_id').val('');
        });
    });

    $(document).on('change', '.res-date', function() {
        checkExistingReservation($(this).closest('.date-entry'));
    });

    $('#reservationForm').on('submit', function(e) {
        e.preventDefault();
        const member_id = $('#r_member_id').val();
        if (!member_id) { alert('Please select a member'); return; }

        const dates = $('.res-date').map(function() { return $(this).val(); }).get().filter(d => d);
        if (dates.length === 0) { alert('Please select at least one date'); return; }
        if (new Set(dates).size !== dates.length) { alert('You have selected the same date more than once. Please remove duplicates.'); return; }

        const busRiders = $('.bus-rider').map(function() { return $(this).prop('checked') ? 1 : 0; }).get();
        const eatLunches = $('.eat-lunch').map(function() { return $(this).prop('checked') ? 1 : 0; }).get();

        let completed = 0, success = 0, errors = [];
        dates.forEach((date, i) => {
            $.post('api/reservations.php', {
                action: 'add', member_id: member_id, res_date: date, bus_rider: busRiders[i], eat_lunch: eatLunches[i],
            }, function(response) {
                completed++;
                if (response.success) success++; else errors.push(response.data.message + ' (' + date + ')');
                finish();
            }, 'json').fail(function() { completed++; errors.push('Server error for date ' + date); finish(); });
        });

        function finish() {
            if (completed !== dates.length) return;
            let msg = '';
            if (success > 0) msg += success + ' reservation(s) saved successfully';
            if (errors.length > 0) msg += (msg ? '\n\n' : '') + 'Issues:\n' + errors.join('\n');
            if (msg) {
                alert(msg);
                $('#reservationForm')[0].reset();
                $('#dateContainer').html($('#dateContainer .date-entry').first().clone());
            }
        }
    });

    // Cancel marks the reservation(s) cancelled (kept on record for
    // cancellation tracking, and left off the printable Day Sheet) rather
    // than removing them. This is different from Delete, which removes the
    // record outright and is not counted as a cancellation.
    $('#cancelReservations').on('click', function() {
        const member_id = $('#r_member_id').val();
        const dates = $('.res-date').map(function() { return $(this).val(); }).get().filter(d => d);
        if (!member_id || dates.length === 0) { alert('Please select a member and at least one date'); return; }
        if (!confirm('Mark these reservation(s) as cancelled? They will stay on record for tracking but will no longer appear on the printable Day Sheet.')) return;

        let completed = 0, success = 0, errors = [];
        dates.forEach(date => {
            $.post('api/reservations.php', { action: 'cancel', member_id: member_id, res_date: date }, function(response) {
                completed++;
                if (response.success) success++; else errors.push(response.data.message + ' (' + date + ')');
                finish();
            }, 'json').fail(function() { completed++; errors.push('Server error for date ' + date); finish(); });
        });

        function finish() {
            if (completed !== dates.length) return;
            let msg = '';
            if (success > 0) msg += success + ' reservation(s) marked cancelled';
            if (errors.length > 0) msg += (msg ? '\n\n' : '') + 'Issues:\n' + errors.join('\n');
            if (msg) {
                alert(msg);
                $('#reservationForm')[0].reset();
                $('#dateContainer').html($('#dateContainer .date-entry').first().clone());
            }
        }
    });

    $('#deleteReservations').on('click', function() {
        const member_id = $('#r_member_id').val();
        const dates = $('.res-date').map(function() { return $(this).val(); }).get().filter(d => d);
        if (!member_id || dates.length === 0) { alert('Please select a member and at least one date'); return; }
        if (!confirm('Are you sure you want to delete these reservations? This permanently removes them and does not count as a cancellation.')) return;

        let completed = 0;
        dates.forEach(date => {
            $.post('api/reservations.php', { action: 'delete', member_id: member_id, res_date: date }, function() {
                completed++;
                if (completed === dates.length) {
                    alert('Reservations deleted successfully');
                    $('#reservationForm')[0].reset();
                }
            }, 'json');
        });
    });

    // --- Search ---
    function performSearch() {
        $.get('api/reservations.php', $('#searchForm').serialize() + '&action=search', function(response) {
            if (response.success && response.data.length > 0) displayResults(response.data);
            else {
                $('#resultsTable').hide(); $('#noResults').show();
                $('#recordCount').text('Records found: 0');
                $('#printButton, #exportButton').hide();
            }
        }, 'json');
    }

    function displayResults(results) {
        const tbody = $('#resultsTable tbody');
        tbody.empty();
        $('#recordCount').text(`Records found: ${results.length}`);
        $('#printButton, #exportButton').show();
        results.forEach(row => {
            tbody.append(`<tr>
                <td>${row.last_name}, ${row.first_name}</td>
                <td>${formatDateStr(row.res_date)}</td>
                <td style="text-align:center">${row.bus_rider == 1 ? 'Yes' : 'No'}</td>
                <td style="text-align:center">${row.eat_lunch == 1 ? 'Yes' : 'No'}</td>
                <td style="text-align:center">${row.showed == 1 ? 'Yes' : 'No'}</td>
                <td style="text-align:center">${row.walkin == 1 ? 'Yes' : 'No'}</td>
                <td style="text-align:center">${row.cancelled == 1 ? 'Yes' : 'No'}</td>
            </tr>`);
        });
        $('#resultsTable').show();
        $('#noResults').hide();
    }

    $('#searchForm').on('submit', function(e) { e.preventDefault(); performSearch(); });
    $('#resetButton').on('click', function() {
        $('#searchForm')[0].reset();
        $('#resultsTable, #printButton, #exportButton').hide();
        $('#noResults').hide();
        $('#recordCount').text('');
    });

    $('#printButton').on('click', function() {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`<html><head><title>Lunch Reservations Report</title><style>
            body{font-family:Arial,sans-serif;padding:20px;} table{width:100%;border-collapse:collapse;margin-top:20px;}
            th,td{border:1px solid #000;padding:8px;text-align:left;} th{background:#f0f0f0;}
        </style></head><body><h2>Lunch Reservations Report</h2><div>${$('#recordCount').text()}</div>${$('#resultsTable')[0].outerHTML}</body></html>`);
        printWindow.document.close();
        printWindow.onload = function() { printWindow.print(); printWindow.onafterprint = function() { printWindow.close(); }; };
    });

    $('#exportButton').on('click', function() {
        const table = document.getElementById('resultsTable');
        let csv = [];
        const headers = [];
        table.querySelectorAll('thead th').forEach(c => headers.push('"' + c.textContent.trim() + '"'));
        csv.push(headers.join(','));
        table.querySelectorAll('tbody tr').forEach(row => {
            const rowData = [];
            row.querySelectorAll('td').forEach(c => rowData.push('"' + c.textContent.trim() + '"'));
            csv.push(rowData.join(','));
        });
        const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'lunch_reservations.csv';
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    // --- Log a Cancellation / No-Show ---
    $('#cancellationLogForm').on('submit', function(e) {
        e.preventDefault();
        const member_id = $('#cl_member_id').val();
        const cancel_date = $('#cl_date').val();
        if (!member_id) { alert('Please select a member'); return; }
        if (!cancel_date) { alert('Please select a date'); return; }

        $.post('api/cancellations.php', $(this).serialize() + '&action=add', function(response) {
            if (response.success) {
                alert('Cancellation logged successfully');
                $('#cancellationLogForm')[0].reset();
            } else {
                alert('Error: ' + response.data.message);
            }
        }, 'json').fail(function() { alert('Server error logging cancellation'); });
    });

    // --- Cancellation History search ---
    function performCancellationSearch() {
        $.get('api/cancellations.php', $('#cancellationSearchForm').serialize() + '&action=search', function(response) {
            if (response.success && response.data.length > 0) displayCancellationResults(response.data);
            else {
                $('#cancellationResultsTable').hide(); $('#cancellationNoResults').show();
                $('#cancellationRecordCount').text('Records found: 0');
            }
        }, 'json');
    }

    function displayCancellationResults(results) {
        const tbody = $('#cancellationResultsTable tbody');
        tbody.empty();
        $('#cancellationRecordCount').text(`Records found: ${results.length}`);
        results.forEach(row => {
            tbody.append(`<tr>
                <td>${row.last_name}, ${row.first_name}</td>
                <td>${formatDateStr(row.cancel_date)}</td>
                <td>${row.type === 'CX' ? 'Cancel' : 'NCNS'}</td>
                <td>${row.notes ? row.notes : ''}</td>
                <td style="text-align:center">${row.cx_total}</td>
                <td style="text-align:center">${row.ncns_total}</td>
                <td style="text-align:center">${row.total}</td>
            </tr>`);
        });
        $('#cancellationResultsTable').show();
        $('#cancellationNoResults').hide();
    }

    $('#cancellationSearchForm').on('submit', function(e) { e.preventDefault(); performCancellationSearch(); });
    $('#cancellationResetButton').on('click', function() {
        $('#cancellationSearchForm')[0].reset();
        $('#cancellationResultsTable').hide();
        $('#cancellationNoResults').hide();
        $('#cancellationRecordCount').text('');
    });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
