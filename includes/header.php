<?php
require_once __DIR__ . '/../db.php';

$current_page = isset($current_page) ? $current_page : basename($_SERVER['PHP_SELF'], '.php');

$nav_items = array(
    'signin'          => array('label' => 'SignIn',              'href' => 'signin.php'),
    'members'         => array('label' => 'Members',              'href' => 'members.php'),
    'activities'      => array('label' => 'Activities',           'href' => 'activities.php'),
    'activity-types'  => array('label' => 'Activity Types',       'href' => 'activity-types.php'),
    'report'          => array('label' => 'Report',               'href' => 'report.php'),
    'rides-import'    => array('label' => 'Kearney Rides Import', 'href' => 'rides-import.php'),
    'reservations'    => array('label' => 'Reservations',         'href' => 'reservations.php'),
    'volunteers'      => array('label' => 'Volunteers',           'href' => 'volunteers.php'),
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars(SITE_NAME); ?><?php echo isset($page_title) ? ' - ' . htmlspecialchars($page_title) : ''; ?></title>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
<link rel="stylesheet" href="css/app.css">
</head>
<body>
<header class="site-header">
    <div class="site-title"><?php echo htmlspecialchars(SITE_NAME); ?></div>
    <nav class="site-nav">
        <?php foreach ($nav_items as $key => $item): ?>
            <a href="<?php echo htmlspecialchars($item['href']); ?>"
               class="<?php echo $current_page === $key ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($item['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>
</header>
<main class="site-main">
