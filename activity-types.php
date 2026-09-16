<?php
$page_title = 'Activity Types';
$current_page = 'activity-types';
require_once __DIR__ . '/includes/header.php';
?>
<h1>Activity Types</h1>
<div class="card">
    <form id="activityTypeForm">
        <div class="form-group">
            <label for="type_id">Select Activity Type:</label>
            <select name="type_id" id="type_id">
                <option value="">Select a type</option>
            </select>
        </div>

        <div class="loading" id="loadingIndicator" style="display:none;">Loading type data...</div>

        <div class="form-group">
            <label for="activity_type">Activity Type Name:</label>
            <input type="text" name="activity_type" id="activity_type" required>
        </div>

        <div class="button-group">
            <button type="submit" id="updateType" style="display:none;">Update Type</button>
            <button type="button" id="deleteType" class="delete-button" style="display:none;">Delete Type</button>
            <button type="button" id="addType">Add Type</button>
            <button type="button" id="resetForm" class="reset-button">Reset Form</button>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    function refreshDropdown() {
        $.getJSON('api/activity-types.php', { action: 'list' }, function(response) {
            if (response.success) {
                const select = $('#type_id');
                select.empty().append('<option value="">Select a type</option>');
                response.data.forEach(t => select.append(`<option value="${t.id}">${t.activity_type}</option>`));
                clearForm();
            }
        });
    }
    refreshDropdown();

    function updateButtonVisibility() {
        const typeId = $('#type_id').val();
        $('#addType').toggle(!typeId);
        $('#updateType, #deleteType').toggle(!!typeId);
    }

    function clearForm() {
        $('#activity_type, #type_id').val('');
        updateButtonVisibility();
    }

    $('#type_id').on('change', function() {
        const id = $(this).val();
        if (!id) { clearForm(); return; }
        $('#loadingIndicator').show();
        $.getJSON('api/activity-types.php', { action: 'get', id: id }, function(response) {
            if (response.success) {
                $('#activity_type').val(response.data.activity_type);
                updateButtonVisibility();
            }
        }).always(function() { $('#loadingIndicator').hide(); });
    });

    $('#activityTypeForm').on('submit', function(e) {
        e.preventDefault();
        const typeId = $('#type_id').val();
        if (!typeId) return;
        $.post('api/activity-types.php', { action: 'update', id: typeId, activity_type: $('#activity_type').val() }, function(response) {
            if (response.success) { alert('Activity type updated successfully'); refreshDropdown(); }
            else alert((response.data.duplicate ? 'Cannot update activity type: ' : 'Error updating activity type: ') + response.data.message);
        }, 'json');
    });

    $('#addType').on('click', function() {
        $.post('api/activity-types.php', { action: 'add', activity_type: $('#activity_type').val() }, function(response) {
            if (response.success) { alert('Activity type added successfully'); refreshDropdown(); }
            else alert((response.data.duplicate ? 'Cannot add activity type: ' : 'Error adding activity type: ') + response.data.message);
        }, 'json');
    });

    $('#deleteType').on('click', function() {
        const typeId = $('#type_id').val();
        if (!confirm('Are you sure you want to delete this activity type? This cannot be undone.')) return;
        $.post('api/activity-types.php', { action: 'delete', id: typeId }, function(response) {
            if (response.success) { alert('Activity type deleted successfully'); refreshDropdown(); }
            else alert(response.data.message);
        }, 'json');
    });

    $('#resetForm').on('click', function() {
        if (confirm('Are you sure you want to reset the form? All unsaved changes will be lost.')) clearForm();
    });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
