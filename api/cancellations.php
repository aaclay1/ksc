<?php
require_once __DIR__ . '/../db.php';

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Ban tiers, keyed by the unexcused CX/NCNS count within the trailing
// 3-month window (see 'check_ban' below). Below 3, never banned.
function cancellation_ban_tier($count) {
    if ($count >= 6) return array('months' => 1, 'label' => '1 month');
    if ($count >= 5) return array('weeks' => 2, 'label' => '2 weeks');
    if ($count >= 3) return array('weeks' => 1, 'label' => '1 week');
    return null;
}

switch ($action) {

    // Logs a single cancellation / no-show incident for a member. This is
    // a standalone incident log -- independent of whether there's an
    // actual lunch_reservations row for that member/date -- used to drive
    // the ban-eligibility check below.
    case 'add':
        $member_id = intval($_POST['member_id']);
        $cancel_date = trim($_POST['cancel_date']);
        $type = trim($_POST['type']);
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
        $excused = !empty($_POST['excused']) ? 1 : 0;

        if (!$member_id || !$cancel_date || !in_array($type, array('CX', 'NCNS'), true)) {
            json_fail('Member, date, and CX/NCNS are required');
        }

        $id = db_insert('cancellation_log', array(
            'member_id'   => $member_id,
            'cancel_date' => $cancel_date,
            'type'        => $type,
            'notes'       => $notes,
            'excused'     => $excused,
            'created_at'  => central_time()->format('Y-m-d H:i:s'),
        ));

        $id !== false ? json_success(array('message' => 'Cancellation logged successfully')) : json_fail('Error logging cancellation');
        break;

    // Search by member and/or date. Alongside each matching entry, also
    // returns that member's all-time CX / NCNS / combined totals (every
    // logged entry, excused or not -- a plain running count, separate
    // from the excused-only ban calculation in 'check_ban').
    case 'search':
        $where = array();
        $params = array();

        if (!empty($_GET['member_id'])) { $where[] = 'c.member_id = ?'; $params[] = intval($_GET['member_id']); }
        if (!empty($_GET['cancel_date'])) { $where[] = 'c.cancel_date = ?'; $params[] = trim($_GET['cancel_date']); }

        $sql = "SELECT c.*, m.first_name, m.last_name FROM cancellation_log c JOIN members m ON c.member_id = m.id";
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY c.cancel_date DESC, m.last_name, m.first_name';

        $results = db_get_results($sql, $params);
        if (!$results) {
            json_fail('No cancellation records found');
        }

        $member_ids = array_values(array_unique(array_map(function ($r) { return $r->member_id; }, $results)));
        $totals = array();
        if ($member_ids) {
            $placeholders = implode(',', array_fill(0, count($member_ids), '?'));
            $totalRows = db_get_results(
                "SELECT member_id,
                        SUM(type = 'CX') AS cx_total,
                        SUM(type = 'NCNS') AS ncns_total,
                        COUNT(*) AS total
                 FROM cancellation_log
                 WHERE member_id IN ($placeholders)
                 GROUP BY member_id",
                $member_ids
            );
            foreach (($totalRows ?: array()) as $t) {
                $totals[$t->member_id] = $t;
            }
        }

        foreach ($results as $r) {
            $t = isset($totals[$r->member_id]) ? $totals[$r->member_id] : null;
            $r->cx_total = $t ? intval($t->cx_total) : 0;
            $r->ncns_total = $t ? intval($t->ncns_total) : 0;
            $r->total = $t ? intval($t->total) : 0;
        }

        json_success($results);
        break;

    // Checks whether a member is currently banned from new reservations
    // because of unexcused cancellations/no-shows.
    //
    // "Unexcused count" is a rolling trailing-3-month window measured
    // from today: a member with zero unexcused entries in the last 3
    // months is back to a clean slate no matter how much history they
    // have further back. The ban itself is a fixed window (1 week / 2
    // weeks / 1 month, chosen by how many strikes fall in that 3-month
    // window) starting from their most recent unexcused entry -- once
    // that fixed window passes they're eligible again even if the strike
    // count hasn't dropped below 3 yet (the strike still counts toward
    // the tier if they pick up a NEW unexcused entry before it ages out).
    case 'check_ban':
        $member_id = intval($_GET['member_id']);
        if (!$member_id) {
            json_fail('Member is required');
        }

        $count = intval(db_get_var(
            "SELECT COUNT(*) FROM cancellation_log
             WHERE member_id = ? AND excused = 0 AND cancel_date >= (CURDATE() - INTERVAL 3 MONTH)",
            array($member_id)
        ));

        $tier = cancellation_ban_tier($count);
        if (!$tier) {
            json_success(array('banned' => false, 'count' => $count));
        }

        $lastUnexcused = db_get_var(
            "SELECT MAX(cancel_date) FROM cancellation_log WHERE member_id = ? AND excused = 0",
            array($member_id)
        );

        $eligible = new DateTime($lastUnexcused);
        if (isset($tier['months'])) {
            $eligible->modify('+' . $tier['months'] . ' month');
        } else {
            $eligible->modify('+' . ($tier['weeks'] * 7) . ' day');
        }
        $eligibleStr = $eligible->format('Y-m-d');

        if ($eligibleStr <= central_time()->format('Y-m-d')) {
            // The timed ban itself has already run its course, even
            // though the strike is still inside the 3-month window.
            json_success(array('banned' => false, 'count' => $count));
        }

        json_success(array(
            'banned'       => true,
            'count'        => $count,
            'eligible_date' => $eligibleStr,
            'ban_label'    => $tier['label'],
        ));
        break;

    default:
        json_fail('Unknown action');
}
