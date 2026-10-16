<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/day_sheet_xlsx.php';

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch ($action) {

    case 'members':
        $rows = db_get_results("SELECT id, first_name, last_name FROM members ORDER BY last_name, first_name");
        $rows ? json_success($rows) : json_fail('No members found');
        break;

    // Upserts: if this member already has a reservation on this date (the
    // table has a UNIQUE KEY on member_id+res_date), this edits it in place
    // -- updating Bus Rider / Eat Lunch and clearing any prior cancellation,
    // since actively setting it again means it's back on. Otherwise it
    // inserts a new reservation as before.
    case 'add':
        $member_id = intval($_POST['member_id']);
        $bus_rider = !empty($_POST['bus_rider']) ? 1 : 0;
        $eat_lunch = !empty($_POST['eat_lunch']) ? 1 : 0;
        $res_date = trim($_POST['res_date']);

        db_query(
            "INSERT INTO lunch_reservations (member_id, res_date, bus_rider, eat_lunch, cancelled, created_at)
             VALUES (?, ?, ?, ?, 0, ?)
             ON DUPLICATE KEY UPDATE bus_rider = VALUES(bus_rider), eat_lunch = VALUES(eat_lunch), cancelled = 0",
            array($member_id, $res_date, $bus_rider, $eat_lunch, central_time()->format('Y-m-d H:i:s'))
        );

        json_success(array('message' => 'Reservation saved successfully'));
        break;

    // Looks up an existing reservation for a member + date so the "Add a
    // Reservation" form can pre-fill Bus Rider / Eat Lunch when staff are
    // actually editing one that's already on file.
    case 'get':
        $member_id = intval($_GET['member_id']);
        $res_date = trim($_GET['res_date']);
        if (!$member_id || !$res_date) {
            json_fail('Member and date are required');
        }

        $row = db_get_row(
            "SELECT bus_rider, eat_lunch, cancelled FROM lunch_reservations WHERE member_id = ? AND res_date = ?",
            array($member_id, $res_date)
        );
        $row ? json_success($row) : json_fail('No existing reservation');
        break;

    // Marks a reservation cancelled instead of deleting it, so it stays on
    // record for cancellation tracking. Cancelled reservations are excluded
    // from the printable Day Sheet. This is distinct from Delete, which
    // removes the row outright and is never counted as a cancellation.
    case 'cancel':
        $member_id = intval($_POST['member_id']);
        $res_date = trim($_POST['res_date']);
        $result = db_update('lunch_reservations', array('cancelled' => 1), array('member_id' => $member_id, 'res_date' => $res_date));
        $result !== false ? json_success(array('message' => 'Reservation cancelled successfully')) : json_fail('Error cancelling reservation');
        break;

    case 'delete':
        $member_id = intval($_POST['member_id']);
        $res_date = trim($_POST['res_date']);
        $result = db_delete('lunch_reservations', array('member_id' => $member_id, 'res_date' => $res_date));
        $result !== false ? json_success(array('message' => 'Reservation deleted successfully')) : json_fail('Error deleting reservation');
        break;

    case 'search':
        $where = array();
        $params = array();

        if (!empty($_GET['member_id'])) { $where[] = 'r.member_id = ?'; $params[] = intval($_GET['member_id']); }
        if (isset($_GET['bus_rider']) && $_GET['bus_rider'] !== '') { $where[] = 'r.bus_rider = ?'; $params[] = intval($_GET['bus_rider']); }
        if (isset($_GET['eat_lunch']) && $_GET['eat_lunch'] !== '') { $where[] = 'r.eat_lunch = ?'; $params[] = intval($_GET['eat_lunch']); }
        if (isset($_GET['showed']) && $_GET['showed'] !== '') { $where[] = 'r.showed = ?'; $params[] = intval($_GET['showed']); }
        if (!empty($_GET['res_date'])) { $where[] = 'DATE(r.res_date) = ?'; $params[] = trim($_GET['res_date']); }

        $sql = "SELECT r.*, m.first_name, m.last_name FROM lunch_reservations r JOIN members m ON r.member_id = m.id";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY m.last_name';

        $results = db_get_results($sql, $params);
        $results ? json_success($results) : json_fail('No reservations found');
        break;

    // Printable "day sheet" roster for a single date: everyone with a lunch
    // reservation that day, plus the counts needed to fill in the admin box
    // at the top of the printed sheet. Fields the system has no way to know
    // in advance (CX/NCNS, walk-ins, bus trip breakdown, money) are left for
    // staff to fill in by hand on the printed sheet -- only what's knowable
    // ahead of time (reservation/member/guest/volunteer counts) is filled in.
    case 'day_sheet':
        $res_date = trim($_GET['res_date']);
        if (!$res_date) {
            json_fail('A date is required');
        }

        $date_label = central_time($res_date, false)->format('l, F jS');

        $rows = db_get_results(
            "SELECT m.first_name, m.last_name, m.renewal_date, m.is_volunteer, m.is_guest, r.bus_rider
             FROM lunch_reservations r
             JOIN members m ON r.member_id = m.id
             WHERE DATE(r.res_date) = ? AND r.cancelled = 0
             ORDER BY m.last_name, m.first_name",
            array($res_date)
        );
        $rows = $rows ?: array();

        $member_count = 0;
        $guest_count = 0;
        $volunteer_count = 0;
        foreach ($rows as $row) {
            if ($row->is_guest == 1) { $guest_count++; } else { $member_count++; }
            if ($row->is_volunteer == 1) { $volunteer_count++; }
        }

        $counts = array(
            'reservation_count' => count($rows),
            'member_count' => $member_count,
            'guest_count' => $guest_count,
            'volunteer_count' => $volunteer_count,
        );

        // format=xlsx streams a styled, print-ready spreadsheet straight to
        // the browser as a file download; otherwise fall back to the plain
        // JSON shape (kept in case anything else ever needs the raw data).
        if (isset($_GET['format']) && $_GET['format'] === 'xlsx') {
            $tmpFile = output_day_sheet_xlsx($date_label, $counts, $rows, central_time()->format('Y-m-d'));
            $filename = 'day_sheet_' . $res_date . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($tmpFile));
            readfile($tmpFile);
            unlink($tmpFile);
            exit;
        }

        json_success(array_merge($counts, array(
            'date_label' => $date_label,
            'rows' => $rows,
        )));
        break;

    default:
        json_fail('Unknown action');
}
