<?php
require_once __DIR__ . '/db.php';

$activities = db_get_results("SELECT id, activity_name, barcode FROM activities ORDER BY activity_name");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Activity Barcodes</title>
    <style>
        .barcode-container { display: flex; flex-wrap: wrap; gap: 20px; padding: 20px; }
        .barcode-item { border: 1px solid #ccc; padding: 15px; text-align: center; width: 200px; }
        .barcode-item img { max-width: 100%; height: auto; }
        @media print { button.no-print { display: none; } }
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
</head>
<body>
    <button onclick="window.print();" class="no-print">Print Barcodes</button>
    <div class="barcode-container">
        <?php foreach ($activities as $activity): ?>
            <div class="barcode-item">
                <h3><?php echo htmlspecialchars($activity->activity_name); ?></h3>
                <svg class="barcode"
                     jsbarcode-format="code128"
                     jsbarcode-value="<?php echo htmlspecialchars($activity->barcode); ?>"
                     jsbarcode-width="2"
                     jsbarcode-height="100"
                     jsbarcode-fontsize="12">
                </svg>
            </div>
        <?php endforeach; ?>
    </div>
    <script>JsBarcode(".barcode").init();</script>
</body>
</html>
