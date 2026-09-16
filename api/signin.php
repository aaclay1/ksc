<?php
require_once __DIR__ . '/../db.php';

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

function check_and_create_walkin_reservation($member_id, $signin_date, $activity_name) {
    $activity_lower = strtolower($activity_name);
    if ($activity_lower !== 'senior center lunch' && $activity_lower !== 'firehouse bus') {
        return;
    }

    $existing = db_get_var(
        "SELECT id FROM lunch_reservations WHERE member_id = ? AND DATE(res_date) = DATE(?)",
        array($member_id, $signin_date)
    );

    if (!$existing) {
        db_insert('lunch_reservations', array(
            'member_id' => $member_id,
            'res_date' => $signin_date,
            'showed' => 1,
            'walkin' => 1,
            'bus_rider' => ($activity_lower === 'firehouse bus' ? 1 : 0),
        ));
    }
}

function check_and_update_bus_reservation($member_id, $signin_date) {
    db_query(
        "UPDATE lunch_reservations SET showed = 1 WHERE member_id = ? AND DATE(res_date) = DATE(?) AND bus_rider = 1",
        array($member_id, $signin_date)
    );
}

function mark_lunch_reservation_showed($member_id, $signin_date) {
    $existing = db_get_var(
        "SELECT id FROM lunch_reservations WHERE member_id = ? AND DATE(res_date) = DATE(?)",
        array($member_id, $signin_date)
    );
    if ($existing) {
        db_query("UPDATE lunch_reservations SET showed = 1 WHERE member_id = ? AND DATE(res_date) = DATE(?)", array($member_id, $signin_date));
    }
}

function get_or_create_senior_citizen_member() {
    $member_id = db_get_var(
        "SELECT id FROM members WHERE first_name = ? AND last_name = ?",
        array('SENIOR', 'CITIZEN')
    );

    if (!$member_id) {
        $member_id = db_get_var(
            "SELECT id FROM members WHERE LOWER(first_name) LIKE ? AND LOWER(last_name) LIKE ?",
            array('%senior%', '%citizen%')
        );
    }

    if (!$member_id) {
        $member_id = db_insert('members', array(
            'first_name' => 'SENIOR',
            'last_name' => 'CITIZEN',
            'email' => 'batch@example.com',
            'date_added' => central_time()->format('Y-m-d H:i:s'),
            'barcode' => 'BATCH' . mt_rand(10000, 99999),
        ));
    }

    return $member_id;
}

switch ($action) {

    case 'members':
        $rows = db_get_results("SELECT id, first_name, last_name FROM members ORDER BY last_name, first_name");
        $rows ? json_success($rows) : json_fail('No members found');
        break;

    case 'activities':
        $rows = db_get_results("SELECT id, activity_name FROM activities ORDER BY activity_name");
        $rows ? json_success($rows) : json_fail('No activities found');
        break;

    case 'name_from_barcode':
        $barcode = trim($_POST['barcode']);
        $type = $_POST['type'];
        if ($type === 'member') {
            $result = db_get_var("SELECT CONCAT(last_name, ', ', first_name) FROM members WHERE barcode = ?", array($barcode));
        } else {
            $result = db_get_var("SELECT activity_name FROM activities WHERE barcode = ?", array($barcode));
        }
        $result ? json_success(array('name' => $result)) : json_fail('Invalid barcode');
        break;

    // Manual sign-in: member + activity dropdowns, a date, and hours.
    case 'manual':
        $member_id = intval($_POST['member_id']);
        $activity_id = intval($_POST['activity_id']);
        $volunteer_hours = floatval($_POST['volunteer_hours']);
        $signin_date = trim($_POST['signin_date']);

        if (!$member_id || !$activity_id || !$signin_date) {
            json_fail('Invalid input data provided');
        }

        $existing = db_get_var(
            "SELECT COUNT(*) FROM member_signin WHERE member_id = ? AND activity_id = ? AND DATE(signin_date) = DATE(?)",
            array($member_id, $activity_id, $signin_date)
        );
        if ($existing > 0) {
            json_fail('This member is already signed in for this activity on this date');
        }

        $central = central_time($signin_date, true);
        $formatted = $central->format('Y-m-d H:i:s');

        mark_lunch_reservation_showed($member_id, $signin_date);
        check_and_update_bus_reservation($member_id, $signin_date);

        $insert_id = db_insert('member_signin', array(
            'member_id' => $member_id,
            'activity_id' => $activity_id,
            'volunteer_hours' => $volunteer_hours,
            'signin_date' => $formatted,
        ));

        if ($insert_id !== false) {
            $activity_name = db_get_var("SELECT activity_name FROM activities WHERE id = ?", array($activity_id));
            check_and_create_walkin_reservation($member_id, $signin_date, $activity_name);
            json_success(array('message' => 'Signed in successfully', 'signin_date' => $central->format('Y-m-d')));
        } else {
            json_fail('Error recording sign-in');
        }
        break;

    // Barcode sign-in: member barcode + activity barcode scanned, always "now".
    case 'barcode':
        $member_barcode = trim($_POST['member_barcode']);
        $activity_barcode = trim($_POST['activity_barcode']);
        $volunteer_hours = isset($_POST['volunteer_hours']) ? floatval($_POST['volunteer_hours']) : 0;

        $central = central_time('now');
        $signin_date = $central->format('Y-m-d');
        $formatted = $central->format('Y-m-d H:i:s');

        $member = db_get_row(
            "SELECT id, CONCAT(last_name, ', ', first_name) as full_name FROM members WHERE barcode = ?",
            array($member_barcode)
        );
        $activity = db_get_row("SELECT id, activity_name FROM activities WHERE barcode = ?", array($activity_barcode));

        if (!$member || !$activity) {
            json_fail('Invalid barcode(s)');
        }

        $existing = db_get_var(
            "SELECT COUNT(*) FROM member_signin WHERE member_id = ? AND activity_id = ? AND DATE(signin_date) = DATE(?)",
            array($member->id, $activity->id, $signin_date)
        );
        if ($existing > 0) {
            json_fail('This member is already signed in for this activity today');
        }

        mark_lunch_reservation_showed($member->id, $signin_date);
        check_and_update_bus_reservation($member->id, $signin_date);

        $insert_id = db_insert('member_signin', array(
            'member_id' => $member->id,
            'activity_id' => $activity->id,
            'volunteer_hours' => $volunteer_hours,
            'signin_date' => $formatted,
        ));

        if ($insert_id !== false) {
            check_and_create_walkin_reservation($member->id, $signin_date, $activity->activity_name);
            json_success(array(
                'message' => "{$member->full_name} signed in to {$activity->activity_name} on " . $central->format('Y-m-d') . ' at ' . $central->format('g:i A'),
                'member_name' => $member->full_name,
                'activity_name' => $activity->activity_name,
                'signin_time' => $central->format('g:i A'),
                'signin_date' => $central->format('Y-m-d'),
            ));
        } else {
            json_fail('Error recording sign-in');
        }
        break;

    // Group sign-in: sign in N generic "Senior Citizen" attendees to one activity/date at once.
    case 'batch':
        $activity_id = intval($_POST['activity_id']);
        $signin_date = trim($_POST['signin_date']);
        $amount = intval($_POST['amount']);

        if ($amount <= 0 || $amount > 100) {
            json_fail('Invalid amount. Please enter a number between 1 and 100.');
        }

        $member_id = get_or_create_senior_citizen_member();
        if (!$member_id) {
            json_fail('Error finding or creating the Senior Citizen member.');
        }

        $activity_name = db_get_var("SELECT activity_name FROM activities WHERE id = ?", array($activity_id));
        if (!$activity_name) {
            json_fail('Invalid activity selected.');
        }

        $central = central_time($signin_date, true);
        $formatted = $central->format('Y-m-d H:i:s');

        $success_count = 0;
        for ($i = 0; $i < $amount; $i++) {
            $insert_id = db_insert('member_signin', array(
                'member_id' => $member_id,
                'activity_id' => $activity_id,
                'volunteer_hours' => 0,
                'signin_date' => $formatted,
            ));
            if ($insert_id !== false) {
                $success_count++;
                if ($i === 0) {
                    check_and_create_walkin_reservation($member_id, $signin_date, $activity_name);
                }
            }
        }

        if ($success_count > 0) {
            json_success(array(
                'message' => "Successfully signed in {$success_count} Senior Citizen(s) to {$activity_name} on " . $central->format('Y-m-d') . ' at ' . $central->format('g:i A'),
                'success_count' => $success_count,
                'signin_date' => $central->format('Y-m-d'),
            ));
        } else {
            json_fail('Error recording batch sign-ins');
        }
        break;

    // Search existing sign-ins by member / activity / date.
    case 'search':
        $where = array();
        $params = array();

        if (!empty($_GET['member_id'])) {
            $where[] = 's.member_id = ?';
            $params[] = intval($_GET['member_id']);
        }
        if (!empty($_GET['activity_id'])) {
            $where[] = 's.activity_id = ?';
            $params[] = intval($_GET['activity_id']);
        }
        if (!empty($_GET['signin_date'])) {
            $where[] = 'DATE(s.signin_date) = ?';
            $params[] = trim($_GET['signin_date']);
        }

        $sql = "SELECT s.*, m.first_name, m.last_name, a.activity_name
                FROM member_signin s
                JOIN members m ON s.member_id = m.id
                JOIN activities a ON s.activity_id = a.id";
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY m.last_name, s.signin_date DESC';

        $results = db_get_results($sql, $params);
        $results ? json_success($results) : json_fail('No sign-ins found');
        break;

    default:
        json_fail('Unknown action');
}
