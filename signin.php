<?php
$page_title = 'Sign In';
$current_page = 'signin';
require_once __DIR__ . '/includes/header.php';
?>
<h1>Member Sign-In</h1>

<div class="card">
    <h2>Manual Sign-In</h2>
    <form id="manualForm">
        <div class="form-group">
            <label for="m_member_id">Member:</label>
            <select name="member_id" id="m_member_id" required><option value="">Select a member</option></select>
        </div>
        <div class="form-group">
            <label for="m_activity_id">Activity:</label>
            <select name="activity_id" id="m_activity_id" required><option value="">Select an activity</option></select>
        </div>
        <div class="form-group">
            <label for="m_volunteer_hours">Volunteer Hours:</label>
            <input type="number" step="0.25" min="0" name="volunteer_hours" id="m_volunteer_hours" value="0">
        </div>
        <div class="form-group">
            <label for="m_signin_date">Date:</label>
            <input type="date" name="signin_date" id="m_signin_date" required>
        </div>
        <div class="button-group">
            <button type="submit">Sign In</button>
        </div>
    </form>
</div>

<hr class="section-divider">

<div class="card">
    <h2>Barcode Scanner</h2>
    <form id="barcodeForm">
        <div class="form-group">
            <label for="b_member_barcode">Member Barcode:</label>
            <input type="text" name="member_barcode" id="b_member_barcode" autocomplete="off">
            <div id="b_member_name" class="loading"></div>
        </div>
        <div class="form-group">
            <label for="b_activity_barcode">Activity Barcode:</label>
            <input type="text" name="activity_barcode" id="b_activity_barcode" autocomplete="off">
            <div id="b_activity_name" class="loading"></div>
        </div>
        <div class="form-group">
            <label for="b_volunteer_hours">Volunteer Hours:</label>
            <input type="number" step="0.25" min="0" name="volunteer_hours" id="b_volunteer_hours" value="0">
        </div>
        <div class="button-group">
            <button type="submit">Sign In</button>
        </div>
    </form>
</div>

<hr class="section-divider">

<div class="card">
    <h2>Group Sign-In</h2>
    <p>Signs in the given number of attendees as a generic "Senior Citizen" record (no volunteer hours).</p>
    <form id="groupForm">
        <div class="form-group">
            <label for="g_amount">Number of attendees to sign in at once:</label>
            <input type="number" min="1" max="100" name="amount" id="g_amount" value="1" required>
        </div>
        <div class="form-group">
            <label for="g_activity_id">Activity:</label>
            <select name="activity_id" id="g_activity_id" required><option value="">Select an activity</option></select>
        </div>
        <div class="form-group">
            <label for="g_signin_date">Date:</label>
            <input type="date" name="signin_date" id="g_signin_date" required>
        </div>
        <div class="button-group">
            <button type="submit">Sign In Group</button>
        </div>
    </form>
</div>

<hr class="section-divider">

<div class="card">
    <h2>Search Sign-Ins</h2>
    <form id="searchForm">
        <div class="form-group">
            <label for="s_member_id">Member:</label>
            <select name="member_id" id="s_member_id"><option value="">Select a member</option></select>
        </div>
        <div class="form-group">
            <label for="s_activity_id">Activity:</label>
            <select name="activity_id" id="s_activity_id"><option value="">Select an activity</option></select>
        </div>
        <div class="form-group">
            <label for="s_signin_date">Sign-in Date:</label>
            <input type="date" name="signin_date" id="s_signin_date">
        </div>
        <div class="button-group">
            <button type="submit" id="searchButton">Search</button>
            <button type="button" id="resetButton" class="reset-button">Reset</button>
            <button type="button" id="printButton" style="display:none;">Print Results</button>
            <button type="button" id="exportButton" style="display:none;">Export to CSV</button>
        </div>
    </form>

    <div id="recordCount"></div>
    <table id="resultsTable" style="display:none;">
        <thead><tr><th>Member Name</th><th>Activity</th><th>Sign-in Date</th></tr></thead>
        <tbody></tbody>
    </table>
    <div id="noResults" style="display:none;">No sign-ins found.</div>
</div>

<script>
jQuery(document).ready(function($) {
    const today = new Date().toISOString().split('T')[0];
    $('#m_signin_date, #g_signin_date').val(today);

    function populateSelect(url, params, selectEl, valueKey, labelFn) {
        $.getJSON(url, params, function(response) {
            if (response.success) {
                const select = $(selectEl);
                const placeholder = select.find('option').first();
                select.empty().append(placeholder);
                response.data.forEach(item => select.append(`<option value="${item[valueKey]}">${labelFn(item)}</option>`));
            }
        });
    }

    function loadMembers(selectEl) {
        populateSelect('api/signin.php', { action: 'members' }, selectEl, 'id', m => `${m.last_name}, ${m.first_name}`);
    }
    function loadActivities(selectEl) {
        populateSelect('api/signin.php', { action: 'activities' }, selectEl, 'id', a => a.activity_name);
    }

    loadMembers('#m_member_id');
    loadActivities('#m_activity_id');
    loadActivities('#g_activity_id');
    loadMembers('#s_member_id');
    loadActivities('#s_activity_id');

    // --- Manual sign-in ---
    $('#manualForm').on('submit', function(e) {
        e.preventDefault();
        $.post('api/signin.php', {
            action: 'manual',
            member_id: $('#m_member_id').val(),
            activity_id: $('#m_activity_id').val(),
            volunteer_hours: $('#m_volunteer_hours').val(),
            signin_date: $('#m_signin_date').val(),
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                $('#m_member_id').val('');
                $('#m_volunteer_hours').val(0);
            } else {
                alert('Error: ' + response.data.message);
            }
        }, 'json');
    });

    // --- Barcode sign-in ---
    function lookupBarcodeName(barcode, type, targetEl) {
        if (!barcode) { $(targetEl).text(''); return; }
        $.post('api/signin.php', { action: 'name_from_barcode', barcode: barcode, type: type }, function(response) {
            $(targetEl).text(response.success ? response.data.name : 'Not found');
        }, 'json');
    }
    $('#b_member_barcode').on('change', function() { lookupBarcodeName($(this).val(), 'member', '#b_member_name'); });
    $('#b_activity_barcode').on('change', function() { lookupBarcodeName($(this).val(), 'activity', '#b_activity_name'); });

    $('#barcodeForm').on('submit', function(e) {
        e.preventDefault();
        $.post('api/signin.php', {
            action: 'barcode',
            member_barcode: $('#b_member_barcode').val(),
            activity_barcode: $('#b_activity_barcode').val(),
            volunteer_hours: $('#b_volunteer_hours').val(),
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                $('#b_member_barcode, #b_activity_barcode').val('');
                $('#b_member_name, #b_activity_name').text('');
                $('#b_volunteer_hours').val(0);
                $('#b_member_barcode').focus();
            } else {
                alert('Error: ' + response.data.message);
            }
        }, 'json');
    });

    // --- Group sign-in ---
    $('#groupForm').on('submit', function(e) {
        e.preventDefault();
        $.post('api/signin.php', {
            action: 'batch',
            amount: $('#g_amount').val(),
            activity_id: $('#g_activity_id').val(),
            signin_date: $('#g_signin_date').val(),
        }, function(response) {
            if (response.success) {
                alert(response.data.message);
                $('#g_amount').val(1);
            } else {
                alert('Error: ' + response.data.message);
            }
        }, 'json');
    });

    // --- Search ---
    function performSearch() {
        const formData = $('#searchForm').serialize();
        $.get('api/signin.php', formData + '&action=search', function(response) {
            if (response.success && response.data.length > 0) {
                displayResults(response.data);
            } else {
                $('#resultsTable').hide();
                $('#noResults').show();
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
            tbody.append(`<tr><td>${row.last_name}, ${row.first_name}</td><td>${row.activity_name}</td><td>${new Date(row.signin_date).toLocaleDateString()}</td></tr>`);
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
        printWindow.document.write(`<html><head><title>Member Sign-in Report</title><style>
            body{font-family:Arial,sans-serif;padding:20px;} table{width:100%;border-collapse:collapse;margin-top:20px;}
            th,td{border:1px solid #000;padding:8px;text-align:left;} th{background:#f0f0f0;}
        </style></head><body><h2>Member Sign-in Report</h2><div>${$('#recordCount').text()}</div>${$('#resultsTable')[0].outerHTML}</body></html>`);
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
        link.download = 'member_signins.csv';
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
