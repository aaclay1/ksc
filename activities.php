<?php
$page_title = 'Activities';
$current_page = 'activities';
require_once __DIR__ . '/includes/header.php';
?>
<h1>Activities</h1>
<p><a class="button-link" href="barcodes.php" target="_blank">Print All Activity Barcodes</a></p>
<div class="card">
    <form id="activityForm">
        <div class="form-group">
            <label for="activity_id">Select Activity:</label>
            <select name="activity_id" id="activity_id">
                <option value="">Select an activity</option>
            </select>
        </div>

        <div class="loading" id="loadingIndicator" style="display:none;">Loading activity data...</div>

        <div class="form-group">
            <label for="activity_name">Activity Name:</label>
            <input type="text" name="activity_name" id="activity_name" required>
        </div>

        <div class="form-group">
            <label for="activity_type_id">Activity Type:</label>
            <select name="activity_type_id" id="activity_type_id" required>
                <option value="">Select a type</option>
            </select>
        </div>

        <div class="button-group">
            <button type="submit" id="updateActivity" style="display:none;">Update Activity</button>
            <button type="button" id="deleteActivity" class="delete-button" style="display:none;">Delete Activity</button>
            <button type="button" id="addActivity">Add Activity</button>
            <button type="button" id="resetForm" class="reset-button">Reset Form</button>
        </div>

        <div class="barcode-container">
            <svg id="barcodeDisplay"></svg>
            <div id="barcodeNumber"></div>
            <button type="button" id="printBarcode" style="display:none;">Print Barcode</button>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    function fetchActivityTypes() {
        $.getJSON('api/activities.php', { action: 'types' }, function(response) {
            if (response.success) {
                const select = $('#activity_type_id');
                select.empty().append('<option value="">Select a type</option>');
                response.data.forEach(t => select.append(`<option value="${t.id}">${t.activity_type}</option>`));
            }
        });
    }
    fetchActivityTypes();

    function refreshDropdown() {
        $.getJSON('api/activities.php', { action: 'list' }, function(response) {
            if (response.success) {
                const select = $('#activity_id');
                select.empty().append('<option value="">Select an activity</option>');
                response.data.forEach(a => select.append(`<option value="${a.id}">${a.activity_name}</option>`));
                clearForm();
            }
        });
    }
    refreshDropdown();

    function updateButtonVisibility() {
        const activityId = $('#activity_id').val();
        $('#addActivity').toggle(!activityId);
        $('#updateActivity, #deleteActivity').toggle(!!activityId);
    }

    function clearForm() {
        $('#activity_name, #activity_type_id, #activity_id').val('');
        $('#barcodeDisplay').empty();
        $('#barcodeNumber').text('');
        $('#printBarcode').hide();
        updateButtonVisibility();
    }

    function displayBarcode(barcode) {
        if (!barcode) return;
        $('#barcodeDisplay').empty();
        JsBarcode('#barcodeDisplay', barcode, { format: 'CODE128', width: 2, height: 100, displayValue: true });
        $('#barcodeNumber').text(barcode);
        $('#printBarcode').show();
    }

    $('#activity_id').on('change', function() {
        const id = $(this).val();
        if (!id) { clearForm(); return; }
        $('#loadingIndicator').show();
        $.getJSON('api/activities.php', { action: 'get', id: id }, function(response) {
            if (response.success) {
                $('#activity_name').val(response.data.activity_name);
                $('#activity_type_id').val(response.data.activity_type_id);
                displayBarcode(response.data.barcode);
                updateButtonVisibility();
            }
        }).always(function() { $('#loadingIndicator').hide(); });
    });

    $('#activityForm').on('submit', function(e) {
        e.preventDefault();
        const activityId = $('#activity_id').val();
        if (!activityId) return;
        $.post('api/activities.php', {
            action: 'update', id: activityId,
            activity_name: $('#activity_name').val(),
            activity_type_id: $('#activity_type_id').val(),
        }, function(response) {
            if (response.success) { alert('Activity updated successfully'); refreshDropdown(); clearForm(); }
            else alert((response.data.duplicate ? 'Cannot update activity: ' : 'Error updating activity: ') + response.data.message);
        }, 'json');
    });

    $('#addActivity').on('click', function() {
        $.post('api/activities.php', {
            action: 'add',
            activity_name: $('#activity_name').val(),
            activity_type_id: $('#activity_type_id').val(),
        }, function(response) {
            if (response.success) {
                displayBarcode(response.data.barcode);
                alert('Activity added successfully');
                refreshDropdown();
                clearForm();
            } else {
                alert((response.data.duplicate ? 'Cannot add activity: ' : 'Error adding activity: ') + response.data.message);
            }
        }, 'json');
    });

    $('#deleteActivity').on('click', function() {
        const activityId = $('#activity_id').val();
        if (!confirm('Are you sure you want to delete this activity? This cannot be undone.')) return;
        $.post('api/activities.php', { action: 'delete', id: activityId }, function(response) {
            if (response.success) { alert('Activity deleted successfully'); refreshDropdown(); clearForm(); }
            else alert(response.data.message);
        }, 'json');
    });

    $('#resetForm').on('click', function() {
        if (confirm('Are you sure you want to reset the form? All unsaved changes will be lost.')) clearForm();
    });

    $('#printBarcode').on('click', function() {
        const activityName = $('#activity_name').val();
        const barcode = $('#barcodeNumber').text();
        const printWindow = window.open('', '', 'width=400,height=300');
        printWindow.document.write(`
            <html><head><title>Activity Barcode</title><style>
                .barcode-container{text-align:center;margin:20px;}
                .activity-name{font-size:16px;margin:10px 0;} .barcode-number{font-size:12px;margin-top:5px;}
            </style></head><body>
                <div class="barcode-container">
                    <div class="activity-name">${activityName}</div>
                    <svg id="printBarcode"></svg>
                    <div class="barcode-number">${barcode}</div>
                </div>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"><\/script>
                <script>
                    JsBarcode("#printBarcode", "${barcode}", { format:"CODE128", width:2, height:100, displayValue:true });
                    window.onload = function(){ window.print(); window.onafterprint = function(){ window.close(); }; };
                <\/script>
            </body></html>`);
        printWindow.document.close();
    });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
