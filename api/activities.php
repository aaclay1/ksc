<?php
require_once __DIR__ . '/../db.php';

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch ($action) {

    case 'list':
        $rows = db_get_results("SELECT id, activity_name, activity_type_id FROM activities ORDER BY activity_name");
        if ($rows) {
            json_success($rows);
        } else {
            json_fail('No activities found');
        }
        break;

    case 'get':
        $id = intval($_GET['id']);
        $row = db_get_row("SELECT activity_name, activity_type_id, barcode FROM activities WHERE id = ?", array($id));
        if ($row) {
            json_success($row);
        } else {
            json_fail('Record not found');
        }
        break;

    case 'types':
        $rows = db_get_results("SELECT id, activity_type FROM activity_types ORDER BY activity_type");
        if ($rows) {
            json_success($rows);
        } else {
            json_fail('No activity types found');
        }
        break;

    case 'add':
        $activity_name = strtoupper(trim($_POST['activity_name']));

        $existing = db_get_row("SELECT id FROM activities WHERE UPPER(activity_name) = ?", array($activity_name));
        if ($existing) {
            json_fail(array('message' => 'An activity with this name already exists in the database.', 'duplicate' => true));
        }

        $activity_type_id = intval($_POST['activity_type_id']);
        $barcode = generate_unique_barcode('activities');

        $new_id = db_insert('activities', array(
            'activity_name' => $activity_name,
            'activity_type_id' => $activity_type_id,
            'barcode' => $barcode,
        ));

        if ($new_id !== false) {
            json_success(array('message' => 'Activity added successfully', 'id' => $new_id, 'name' => $activity_name, 'barcode' => $barcode));
        } else {
            json_fail('Error adding activity');
        }
        break;

    case 'update':
        $id = intval($_POST['id']);
        $activity_name = strtoupper(trim($_POST['activity_name']));
        $activity_type_id = intval($_POST['activity_type_id']);

        $existing = db_get_row("SELECT id FROM activities WHERE UPPER(activity_name) = ? AND id != ?", array($activity_name, $id));
        if ($existing) {
            json_fail(array('message' => 'An activity with this name already exists in the database.', 'duplicate' => true));
        }

        $result = db_update('activities', array(
            'activity_name' => $activity_name,
            'activity_type_id' => $activity_type_id,
        ), array('id' => $id));

        if ($result !== false) {
            json_success(array('message' => 'Activity updated successfully'));
        } else {
            json_fail('Error updating activity');
        }
        break;

    case 'delete':
        $id = intval($_POST['id']);
        $result = db_delete('activities', array('id' => $id));
        if ($result !== false) {
            json_success(array('message' => 'Activity deleted successfully'));
        } else {
            json_fail('Error deleting activity');
        }
        break;

    default:
        json_fail('Unknown action');
}
