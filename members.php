<?php
$page_title = 'Members';
$current_page = 'members';
require_once __DIR__ . '/includes/header.php';
?>
<h1>Members</h1>
<div class="card">
    <form id="updateForm">
        <div class="form-group">
            <label for="record_id">Select Record:</label>
            <select name="record_id" id="record_id">
                <option value="">Select a record</option>
            </select>
        </div>

        <div class="loading" id="loadingIndicator" style="display:none;">Loading record data...</div>

        <div class="form-group">
            <label for="firstName">First Name:</label>
            <input type="text" name="firstName" id="firstName" required>
        </div>

        <div class="form-group">
            <label for="lastName">Last Name:</label>
            <input type="text" name="lastName" id="lastName" required>
        </div>

        <div class="form-group">
            <label for="phoneNumber">Phone:</label>
            <input type="text" name="phoneNumber" id="phoneNumber" class="phone-input" pattern="\d{3}-\d{3}-\d{4}" placeholder="123-456-7890">
        </div>

        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" name="email" id="email" placeholder="example@domain.com">
        </div>

        <div class="form-group">
            <div class="checkbox-wrapper">
                <label for="is_60_plus">Age 60+</label>
                <input type="checkbox" name="is_60_plus" id="is_60_plus">
            </div>
            <div class="checkbox-wrapper">
                <label for="is_clay_resident">Clay County Resident</label>
                <input type="checkbox" name="is_clay_resident" id="is_clay_resident">
            </div>
        </div>

        <div class="button-group">
            <button type="submit" id="updateRecord" style="display:none;">Update Record</button>
            <button type="button" id="deleteRecord" class="delete-button" style="display:none;">Delete Record</button>
            <button type="button" id="addRecord">Add Record</button>
            <button type="button" id="resetForm" class="reset-button">Reset Form</button>
            <button type="button" id="printAllCards" class="secondary">Print All Cards</button>
        </div>

        <div class="barcode-container">
            <svg id="barcodeDisplay"></svg>
            <div id="barcodeNumber"></div>
            <button type="button" id="printCard" style="display:none;">Print Card</button>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    function refreshDropdown() {
        $.getJSON('api/members.php', { action: 'list' }, function(response) {
            if (response.success) {
                const select = $('#record_id');
                const currentValue = select.val();
                select.empty().append('<option value="">Select a record</option>');
                response.data.forEach(m => {
                    select.append(`<option value="${m.id}">${m.last_name}, ${m.first_name}</option>`);
                });
                if (!currentValue) clearForm();
            }
        });
    }
    refreshDropdown();
    $('#deleteRecord').hide();
    updateButtonVisibility();

    function formatPhoneNumber(phoneNumber) {
        const cleaned = phoneNumber.replace(/\D/g, '');
        if (cleaned.length >= 10) return cleaned.replace(/(\d{3})(\d{3})(\d{4})/, '$1-$2-$3');
        if (cleaned.length > 3 && cleaned.length <= 6) return cleaned.replace(/(\d{3})(\d{1,3})/, '$1-$2');
        if (cleaned.length > 6) return cleaned.replace(/(\d{3})(\d{3})(\d{1,4})/, '$1-$2-$3');
        return cleaned;
    }

    function displayBarcode(barcode) {
        if (!barcode) return;
        $('#barcodeDisplay').empty();
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        $('#barcodeDisplay').append(svg);
        JsBarcode(svg, barcode, { format: 'CODE128', width: 2, height: 100, displayValue: true });
        $('#barcodeNumber').text(barcode);
        $('#printCard').show();
    }

    function updateButtonVisibility() {
        const recordId = $('#record_id').val();
        $('#addRecord').toggle(!recordId);
        $('#updateRecord, #deleteRecord').toggle(!!recordId);
    }

    function clearForm() {
        $('#firstName, #lastName, #phoneNumber, #email, #record_id').val('');
        $('#is_60_plus, #is_clay_resident').prop('checked', false);
        $('#barcodeDisplay').empty();
        $('#barcodeNumber').text('');
        $('#printCard').hide();
        updateButtonVisibility();
    }

    $('#phoneNumber').on('input', function() {
        const input = $(this);
        let cursorPosition = input[0].selectionStart;
        const previousValue = input.val();
        const formatted = formatPhoneNumber(previousValue);
        input.val(formatted);
        if (formatted.length > previousValue.length && (cursorPosition === 3 || cursorPosition === 7)) cursorPosition++;
        input[0].setSelectionRange(cursorPosition, cursorPosition);
    });

    $('#record_id').on('change', function() {
        updateButtonVisibility();
        const id = $(this).val();
        if (!id) { clearForm(); return; }
        $('#loadingIndicator').show();
        $.getJSON('api/members.php', { action: 'get', id: id }, function(response) {
            if (response.success) {
                $('#firstName').val(response.data.first_name);
                $('#lastName').val(response.data.last_name);
                $('#phoneNumber').val(response.data.phone_number);
                $('#email').val(response.data.email);
                $('#is_60_plus').prop('checked', response.data.is_60_plus == 1);
                $('#is_clay_resident').prop('checked', response.data.is_clay_resident == 1);
                displayBarcode(response.data.barcode);
            }
        }).always(function() { $('#loadingIndicator').hide(); });
    });

    $('#updateForm').on('submit', function(e) {
        e.preventDefault();
        if (!$('#record_id').val()) return;
        $.post('api/members.php', {
            action: 'update',
            id: $('#record_id').val(),
            firstName: $('#firstName').val(),
            lastName: $('#lastName').val(),
            phoneNumber: $('#phoneNumber').val(),
            email: $('#email').val(),
            is_60_plus: $('#is_60_plus').prop('checked') ? 1 : 0,
            is_clay_resident: $('#is_clay_resident').prop('checked') ? 1 : 0,
        }, function(response) {
            if (response.success) {
                alert('Record updated successfully');
                clearForm();
                refreshDropdown();
            } else {
                alert((response.data.duplicate ? 'Cannot update record: ' : 'Error updating record: ') + response.data.message);
            }
        }, 'json');
    });

    $('#deleteRecord').on('click', function() {
        const id = $('#record_id').val();
        if (!id) return;
        if (!confirm('Are you sure you want to delete this record? This action cannot be undone.')) return;
        $.post('api/members.php', { action: 'delete', id: id }, function(response) {
            if (response.success) {
                clearForm();
                refreshDropdown();
                alert('Record deleted successfully');
            } else {
                alert('Error deleting record: ' + response.data.message);
            }
        }, 'json');
    });

    $('#addRecord').on('click', function() {
        $.post('api/members.php', {
            action: 'add',
            firstName: $('#firstName').val(),
            lastName: $('#lastName').val(),
            phoneNumber: $('#phoneNumber').val(),
            email: $('#email').val(),
            is_60_plus: $('#is_60_plus').prop('checked') ? 1 : 0,
            is_clay_resident: $('#is_clay_resident').prop('checked') ? 1 : 0,
        }, function(response) {
            if (response.success) {
                alert('Record added successfully');
                clearForm();
                refreshDropdown();
            } else {
                alert((response.data.duplicate ? 'Cannot add record: ' : 'Error adding record: ') + response.data.message);
            }
        }, 'json');
    });

    $('#resetForm').on('click', function() {
        if (confirm('Are you sure you want to reset the form? All unsaved changes will be lost.')) clearForm();
    });

    $('#printCard').on('click', function() {
        const firstName = $('#firstName').val();
        const lastName = $('#lastName').val();
        const barcode = $('#barcodeNumber').text();
        const printWindow = window.open('', '', 'width=400,height=300');
        printWindow.document.write(`
            <html><head><title>Membership Card</title><style>
                .card-border{border:2px solid #000;width:3.375in;height:2.125in;padding:0.125in;margin:0 auto;background:#fff;}
                .membership-card{text-align:center;} .membership-card h1{font-size:18px;margin:10px 0;color:#000;}
                .member-name{font-size:16px;margin:10px 0;color:#000;} .card-barcode{margin-top:15px;}
                .barcode-number{font-size:12px;margin-top:5px;color:#000;}
            </style></head><body>
                <div class="card-border"><div class="membership-card">
                    <h1>Kearney Senior Center</h1>
                    <div class="member-name">${firstName} ${lastName}</div>
                    <div class="card-barcode"><svg id="printBarcode"></svg><div class="barcode-number">${barcode}</div></div>
                </div></div>
                <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"><\/script>
                <script>
                    JsBarcode("#printBarcode", "${barcode}", { format:"CODE128", width:2, height:60, displayValue:false });
                    window.onload = function(){ window.print(); window.onafterprint = function(){ window.close(); }; };
                <\/script>
            </body></html>`);
        printWindow.document.close();
    });

    $('#printAllCards').on('click', function() {
        const printWindow = window.open('', '_blank', 'width=800,height=600');
        $.getJSON('api/members.php', { action: 'list' }, function(response) {
            if (!response.success) return;
            const promises = response.data.map(m => $.getJSON('api/members.php', { action: 'get', id: m.id }));
            Promise.all(promises).then(results => {
                const members = results.map(r => r.data);
                printWindow.document.write(`
                    <html><head><title>All Membership Cards</title><style>
                        @media print { .card-border { break-inside: avoid; } }
                        .cards-container{display:grid;grid-template-columns:repeat(2,1fr);gap:0.25in;padding:0.25in;}
                        .card-border{border:2px solid #000;width:3.375in;height:2.125in;padding:0.125in;margin:0;background:#fff;}
                        .membership-card{text-align:center;} .membership-card h1{font-size:18px;margin:10px 0;color:#000;}
                        .member-name{font-size:16px;margin:10px 0;color:#000;} .card-barcode{margin-top:15px;}
                        .barcode-number{font-size:12px;margin-top:5px;color:#000;} @page{margin:0;}
                    </style></head><body><div class="cards-container">
                    ${members.map(m => `
                        <div class="card-border"><div class="membership-card">
                            <h1>Kearney Senior Center</h1>
                            <div class="member-name">${m.last_name}, ${m.first_name}</div>
                            <div class="card-barcode"><svg id="barcode-${m.barcode}"></svg><div class="barcode-number">${m.barcode}</div></div>
                        </div></div>`).join('')}
                    </div>
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"><\/script>
                    <script>
                        window.onload = function() {
                            ${members.map(m => `JsBarcode("#barcode-${m.barcode}", "${m.barcode}", { format:"CODE128", width:2, height:60, displayValue:false });`).join('')}
                            window.print();
                            window.onafterprint = function(){ window.close(); };
                        };
                    <\/script></body></html>`);
                printWindow.document.close();
            });
        });
    });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
