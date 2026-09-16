<?php
require_once __DIR__ . '/../db.php';

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch ($action) {

    case 'members':
        $rows = db_get_results(
            "SELECT DISTINCT m.id, m.first_name, m.last_name
             FROM members m
             JOIN member_signin s ON s.member_id = m.id
             WHERE s.volunteer_hours > 0
             ORDER BY m.last_name, m.first_name"
        );
        $rows ? json_success($rows) : json_fail('No members found');
        break;

    case 'activities':
        $rows = db_get_results(
            "SELECT DISTINCT a.id, a.activity_name
             FROM activities a
             JOIN member_signin s ON s.activity_id = a.id
             WHERE s.volunteer_hours > 0
             ORDER BY a.activity_name"
        );
        $rows ? json_success($rows) : json_fail('No activities found');
        break;

    case 'search':
        $where = array('s.volunteer_hours > 0');
        $params = array();

        if (!empty($_GET['member_id'])) { $where[] = 's.member_id = ?'; $params[] = intval($_GET['member_id']); }
        if (!empty($_GET['activity_id'])) { $where[] = 's.activity_id = ?'; $params[] = intval($_GET['activity_id']); }
        if (!empty($_GET['signin_date'])) { $where[] = 'DATE(s.signin_date) = ?'; $params[] = trim($_GET['signin_date']); }

        $sql = "SELECT m.id AS member_id, m.last_name, m.first_name, a.activity_name,
                       DATE(s.signin_date) AS log_date, SUM(s.volunteer_hours) AS total_hours
                FROM member_signin s
                JOIN members m ON s.member_id = m.id
                JOIN activities a ON s.activity_id = a.id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY s.member_id, s.activity_id, DATE(s.signin_date)
                ORDER BY m.last_name, m.first_name, log_date DESC";

        $results = db_get_results($sql, $params);
        $results ? json_success($results) : json_fail('No volunteer hours found');
        break;

    default:
        json_fail('Unknown action');
}
