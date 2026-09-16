<?php
$page_title = 'Volunteers';
$current_page = 'volunteers';
require_once __DIR__ . '/includes/header.php';
?>
<h1>Volunteer Hours Report</h1>
<div class="card">
    <form id="volunteerReportForm">
        <div class="form-group">
            <label for="vhr_member_id">Member:</label>
            <select name="member_id" id="vhr_member_id"><option value="">All Members</option></select>
        </div>
        <div class="form-group">
            <label for="vhr_activity_id">Activity:</label>
            <select name="activity_id" id="vhr_activity_id"><option value="">All Activities</option></select>
        </div>
        <div class="form-group">
            <label for="vhr_signin_date">Date:</label>
            <input type="date" name="signin_date" id="vhr_signin_date">
        </div>
        <div class="button-group">
            <button type="submit" id="vhrSearchButton">Search</button>
            <button type="button" id="vhrResetButton" class="reset-button">Reset</button>
            <button type="button" id="vhrPrintButton" style="display:none;">Print Results</button>
            <button type="button" id="vhrExportButton" style="display:none;">Export to CSV</button>
        </div>
    </form>

    <div id="vhrRecordCount"></div>
    <table id="vhrResultsTable" style="display:none;">
        <thead><tr><th>Member Name</th><th>Activity</th><th>Date</th><th>Total Hours</th></tr></thead>
        <tbody></tbody>
    </table>
    <div id="vhrGrandTotal" class="grand-total" style="display:none;"></div>
    <div id="vhrNoResults" style="display:none;">No volunteer hours found.</div>
</div>

<script>
jQuery(document).ready(function($) {
    function loadOptions(url, params, selectEl, valueKey, labelFn) {
        $.getJSON(url, params, function(response) {
            if (response.success) {
                const select = $(selectEl);
                const placeholder = select.find('option').first();
                select.empty().append(placeholder);
                response.data.forEach(item => select.append(`<option value="${item[valueKey]}">${labelFn(item)}</option>`));
            }
        });
    }
    loadOptions('api/volunteers.php', { action: 'members' }, '#vhr_member_id', 'id', m => `${m.last_name}, ${m.first_name}`);
    loadOptions('api/volunteers.php', { action: 'activities' }, '#vhr_activity_id', 'id', a => a.activity_name);

    function performSearch() {
        $.get('api/volunteers.php', $('#volunteerReportForm').serialize() + '&action=search', function(response) {
            if (response.success && response.data.length > 0) {
                displayResults(response.data);
            } else {
                $('#vhrResultsTable, #vhrGrandTotal').hide();
                $('#vhrNoResults').show();
                $('#vhrRecordCount').text('Records found: 0');
                $('#vhrPrintButton, #vhrExportButton').hide();
            }
        }, 'json');
    }

    function displayResults(results) {
        const tbody = $('#vhrResultsTable tbody');
        tbody.empty();
        $('#vhrRecordCount').text(`Records found: ${results.length}`);
        $('#vhrPrintButton, #vhrExportButton').show();

        let grandTotal = 0;
        results.forEach(row => {
            const hours = parseFloat(row.total_hours);
            grandTotal += hours;
            tbody.append(`<tr><td>${row.last_name}, ${row.first_name}</td><td>${row.activity_name}</td><td>${new Date(row.log_date).toLocaleDateString()}</td><td>${hours}</td></tr>`);
        });

        $('#vhrResultsTable').show();
        $('#vhrGrandTotal').text(`Total Volunteer Hours: ${grandTotal}`).show();
        $('#vhrNoResults').hide();
    }

    $('#volunteerReportForm').on('submit', function(e) { e.preventDefault(); performSearch(); });

    $('#vhrResetButton').on('click', function() {
        $('#volunteerReportForm')[0].reset();
        $('#vhrResultsTable, #vhrGrandTotal, #vhrPrintButton, #vhrExportButton').hide();
        $('#vhrNoResults').hide();
        $('#vhrRecordCount').text('');
    });

    $('#vhrPrintButton').on('click', function() {
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`<html><head><title>Volunteer Hours Report</title><style>
            body{font-family:Arial,sans-serif;padding:20px;} table{width:100%;border-collapse:collapse;margin-top:20px;}
            th,td{border:1px solid #000;padding:8px;text-align:left;} th{background:#f0f0f0;}
        </style></head><body><h2>Volunteer Hours Report</h2><div>${$('#vhrRecordCount').text()}</div><div>${$('#vhrGrandTotal').text()}</div>${$('#vhrResultsTable')[0].outerHTML}</body></html>`);
        printWindow.document.close();
        printWindow.onload = function() { printWindow.print(); printWindow.onafterprint = function() { printWindow.close(); }; };
    });

    $('#vhrExportButton').on('click', function() {
        const table = document.getElementById('vhrResultsTable');
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
        const now = new Date();
        link.download = `volunteer_hours_report_${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}.csv`;
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    performSearch();
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
