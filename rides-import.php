<?php
$page_title = 'Kearney Rides Import';
$current_page = 'rides-import';
require_once __DIR__ . '/includes/header.php';
?>
<h1>Kearney Rides Import</h1>
<div class="card">
    <p>Browse for a xlsx or csv file.</p>
    <p>This file must contain a column heading of 'Name'.</p>
    <p>The names must match a member name already in this site.</p>

    <form id="rideForm" method="post" enctype="multipart/form-data">
        <div class="form-group">
            <label for="file_input">File:</label>
            <input type="file" id="file_input" name="file" accept=".csv,.xlsx">
        </div>
        <div class="button-group">
            <button type="submit" id="importButton">Import</button>
        </div>
        <div id="importStatus" class="import-status"></div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('#rideForm').on('submit', function(e) {
        e.preventDefault();
        if (!$('#file_input').val()) { alert('Please select a file to import'); return; }

        const formData = new FormData(this);
        $('#importStatus').removeClass('success error').html('Importing...');
        $('#importButton').prop('disabled', true);

        $.ajax({
            url: 'api/rides-import.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#importStatus').html(response.data.message).addClass('success').removeClass('error');
                } else {
                    $('#importStatus').html(response.data.message).addClass('error').removeClass('success');
                }
            },
            error: function(xhr, status, error) {
                $('#importStatus').html('Error during import: ' + error).addClass('error').removeClass('success');
            },
            complete: function() {
                $('#importButton').prop('disabled', false);
            }
        });
    });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
