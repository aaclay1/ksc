<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

$using_defaults = false;
if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
    $start_month = trim($_GET['start_date']);
    $end_month = trim($_GET['end_date']);
} else {
    $end_month = date('Y-m');
    $start = new DateTime();
    $start->modify('-11 months');
    $start_month = $start->format('Y-m');
    $using_defaults = true;
}

$months = array();
$month_dates = array();
$current = new DateTime($start_month . '-01');
$last = new DateTime($end_month . '-01');

while ($current <= $last) {
    $key = $current->format('M Y');
    $months[] = $key;
    $month_dates[$key] = array('start' => $current->format('Y-m-01'), 'end' => $current->format('Y-m-t'));
    $current->modify('+1 month');
}

$metrics = array();
foreach ($month_dates as $month => $dates) {
    $start = $dates['start'];
    $end = $dates['end'];

    $metrics[$month] = array(
        'Total Visits' => (int) db_get_var("SELECT COUNT(*) FROM member_signin WHERE DATE(signin_date) BETWEEN ? AND ?", array($start, $end)),
        'Visits 60+' => (int) db_get_var("SELECT COUNT(*) FROM member_signin ms JOIN members m ON ms.member_id = m.id WHERE m.is_60_plus = 1 AND DATE(ms.signin_date) BETWEEN ? AND ?", array($start, $end)),
        'Visits 60+ (CLAY)' => (int) db_get_var("SELECT COUNT(*) FROM member_signin ms JOIN members m ON ms.member_id = m.id WHERE m.is_60_plus = 1 AND m.is_clay_resident = 1 AND DATE(ms.signin_date) BETWEEN ? AND ?", array($start, $end)),
        'Unduplicated' => (int) db_get_var("SELECT COUNT(DISTINCT member_id) FROM member_signin WHERE DATE(signin_date) BETWEEN ? AND ?", array($start, $end)),
        'New Members' => (int) db_get_var("SELECT COUNT(*) FROM members WHERE DATE(date_added) BETWEEN ? AND ?", array($start, $end)),
        'New Members 60+' => (int) db_get_var("SELECT COUNT(*) FROM members WHERE is_60_plus = 1 AND DATE(date_added) BETWEEN ? AND ?", array($start, $end)),
        'Volunteers' => (int) db_get_var("SELECT COUNT(DISTINCT member_id) FROM member_signin WHERE volunteer_hours > 0 AND DATE(signin_date) BETWEEN ? AND ?", array($start, $end)),
        'Volunteers Hours' => (float) db_get_var("SELECT COALESCE(SUM(volunteer_hours), 0) FROM member_signin WHERE DATE(signin_date) BETWEEN ? AND ?", array($start, $end)),
        'Volunteers 60+' => (int) db_get_var("SELECT COUNT(DISTINCT ms.member_id) FROM member_signin ms JOIN members m ON ms.member_id = m.id WHERE m.is_60_plus = 1 AND ms.volunteer_hours > 0 AND DATE(ms.signin_date) BETWEEN ? AND ?", array($start, $end)),
        'Volunteers 60+ Hours' => (float) db_get_var("SELECT COALESCE(SUM(ms.volunteer_hours), 0) FROM member_signin ms JOIN members m ON ms.member_id = m.id WHERE m.is_60_plus = 1 AND DATE(ms.signin_date) BETWEEN ? AND ?", array($start, $end)),
    );
}

$activities_by_type = array();
$activity_types = db_get_results(
    "SELECT at.activity_type, a.activity_name, a.id
     FROM activity_types at
     JOIN activities a ON at.id = a.activity_type_id
     ORDER BY at.activity_type, a.activity_name"
);

foreach ($activity_types as $activity) {
    if (!isset($activities_by_type[$activity->activity_type])) {
        $activities_by_type[$activity->activity_type] = array();
    }
    $activities_by_type[$activity->activity_type][$activity->activity_name] = array();

    foreach ($month_dates as $month => $dates) {
        $start_time = $dates['start'] . ' 00:00:00';
        $end_time = date('Y-m-d', strtotime($dates['end'] . ' +1 day')) . ' 00:00:00';

        $count = db_get_var(
            "SELECT COUNT(*) FROM member_signin WHERE activity_id = ? AND signin_date >= ? AND signin_date < ?",
            array($activity->id, $start_time, $end_time)
        );
        $activities_by_type[$activity->activity_type][$activity->activity_name][$month] = (int) $count;
    }
}

echo json_encode(array(
    'success' => true,
    'data' => array(
        'months' => $months,
        'metrics' => $metrics,
        'activities_by_type' => $activities_by_type,
        'using_defaults' => $using_defaults,
        'start_month' => $start_month,
        'end_month' => $end_month,
    ),
));
