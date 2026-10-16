<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../includes/day_sheet_xlsx.php';

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch ($action) {

    case 'members':
        $rows = db_get_results("SELECT id, first_name, last_name FROM members ORDER BY last_name, first_name");
        $rows ? json_success($rows) : json_fail('No members found');
        break;

    case 'add':
        $member_id = intval($_POST['member_id']);
        $bus_rider = !empty($_POST['bus_rider']) ? 1 : 0;
        $eat_lunch = !empty($_POST['eat_lunch']) ? 1 : 0;
        $res_date = trim($_POST['res_date']);

        $existing = db_get_var(
            "SELECT COUNT(*) FROM lunch_reservations WHERE member_id = ? AND res_date = ?",
            array($member_id, $res_date)
        );
        if ($existing > 0) {
            json_fail('A reservation already exists for this member on this date');
        }

        $id = db_insert('lunch_reservations', array(
            'member_id' => $member_id,
            'bus_rider' => $bus_rider,
            'eat_lunch' => $eat_lunch,
            'res_date' => $res_date,
            'created_at' => central_time()->format('Y-m-d H:i:s'),
        ));

        $id !== false ? json_success(array('message' => 'Reservation added successfully')) : json_fail('Error adding reservation');
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
             WHERE DATE(r.res_date) = ?
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
