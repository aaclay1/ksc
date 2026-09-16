<?php
$page_title = 'Report';
$current_page = 'report';
require_once __DIR__ . '/includes/header.php';
?>
<h1>Activity Report</h1>

<div class="card">
    <form id="dateRangeForm" style="display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap;">
        <div class="form-group" style="margin-bottom:0;">
            <label for="start_date">From:</label>
            <input type="month" name="start_date" id="start_date" required>
        </div>
        <div class="form-group" style="margin-bottom:0;">
            <label for="end_date">To:</label>
            <input type="month" name="end_date" id="end_date" required>
        </div>
        <button type="submit">Update Report</button>
        <button type="button" id="exportCsv" class="secondary">Export CSV</button>
    </form>
    <p id="rangeLabel" style="color:#666;"></p>

    <div style="overflow-x:auto;">
        <table id="reportTable"></table>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function loadReport(startDate, endDate) {
        const params = {};
        if (startDate && endDate) { params.start_date = startDate; params.end_date = endDate; }
        $.getJSON('api/report.php', params, function(response) {
            if (!response.success) return;
            const d = response.data;
            $('#start_date').val(d.start_month);
            $('#end_date').val(d.end_month);
            $('#rangeLabel').text(d.using_defaults ? 'Default: Last 12 Months' : `Custom Range: ${d.start_month} to ${d.end_month}`);
            renderTable(d);
        });
    }

    function renderTable(d) {
        const table = $('#reportTable');
        table.empty();

        let thead = '<thead><tr><th>Activity</th>';
        d.months.forEach(m => thead += `<th>${m}</th>`);
        thead += '</tr></thead>';
        table.append(thead);

        let tbody = '<tbody>';
        tbody += `<tr class="section-header"><td colspan="${d.months.length + 1}"><strong>VISITS</strong></td></tr>`;

        const metricOrder = ['Total Visits', 'Visits 60+', 'Visits 60+ (CLAY)', 'Unduplicated', 'New Members', 'New Members 60+', 'Volunteers', 'Volunteers Hours', 'Volunteers 60+', 'Volunteers 60+ Hours'];
        metricOrder.forEach(metric => {
            tbody += `<tr><td>${metric}</td>`;
            d.months.forEach(m => tbody += `<td>${(d.metrics[m] && d.metrics[m][metric] !== undefined) ? d.metrics[m][metric] : 0}</td>`);
            tbody += '</tr>';
        });

        Object.keys(d.activities_by_type).forEach(type => {
            tbody += `<tr class="section-header"><td colspan="${d.months.length + 1}"><strong>${type}</strong></td></tr>`;
            const activities = d.activities_by_type[type];
            Object.keys(activities).forEach(activity => {
                tbody += `<tr><td>${activity}</td>`;
                d.months.forEach(m => tbody += `<td>${activities[activity][m] !== undefined ? activities[activity][m] : 0}</td>`);
                tbody += '</tr>';
            });
        });

        tbody += '</tbody>';
        table.append(tbody);
    }

    $('#dateRangeForm').on('submit', function(e) {
        e.preventDefault();
        loadReport($('#start_date').val(), $('#end_date').val());
    });

    $('#exportCsv').on('click', function() {
        let csv = [];
        document.querySelectorAll('#reportTable tr').forEach(row => {
            const cols = row.querySelectorAll('td, th');
            const rowData = [];
            cols.forEach(c => rowData.push('"' + c.innerText.replace(/"/g, '""') + '"'));
            csv.push(rowData.join(','));
        });
        const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        const now = new Date();
        link.download = `activity_report_${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}-${String(now.getDate()).padStart(2,'0')}.csv`;
        link.style.display = 'none';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });

    loadReport();
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
