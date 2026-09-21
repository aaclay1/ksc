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
            <button type="button" id="deleteReservations" class="delete-button">Delete Reservation(s)</button>
        </div>
    </form>
</div>

<hr class="section-divider">

<div class="card">
    <h2>Search Reservations</h2>
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
        <thead><tr><th>Member Name</th><th>Date</th><th>Bus Rider</th><th>Eat Lunch</th><th>Showed</th><th>Walk-in</th></tr></thead>
        <tbody></tbody>
    </table>
    <div id="noResults" style="display:none;">No reservations found.</div>
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

    $('#addDateRow').on('click', function() {
        const row = $('<div class="date-entry" style="margin-bottom:10px;">' +
            '<div class="form-group"><label>Reservation Date:</label><input type="date" class="res-date" name="res_date[]" required></div>' +
            '<div class="checkbox-group"><label><input type="checkbox" class="bus-rider" name="bus_rider[]"> Bus Rider</label>' +
            '<label><input type="checkbox" class="eat-lunch" name="eat_lunch[]"> Eat Lunch</label></div></div>');
        $('#dateContainer').append(row);
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
            if (success > 0) msg += success + ' reservation(s) added successfully';
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
        if (!confirm('Are you sure you want to delete these reservations?')) return;

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
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
