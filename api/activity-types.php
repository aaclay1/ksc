<?php
require_once __DIR__ . '/../db.php';

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch ($action) {

    case 'list':
        $rows = db_get_results("SELECT id, activity_type FROM activity_types ORDER BY activity_type");
        if ($rows) {
            json_success($rows);
        } else {
            json_fail('No activity types found');
        }
        break;

    case 'get':
        $id = intval($_GET['id']);
        $row = db_get_row("SELECT activity_type FROM activity_types WHERE id = ?", array($id));
        if ($row) {
            json_success($row);
        } else {
            json_fail('Activity type not found');
        }
        break;

    case 'add':
        $activity_type = strtoupper(trim($_POST['activity_type']));
        $existing = db_get_row("SELECT id FROM activity_types WHERE UPPER(activity_type) = ?", array($activity_type));
        if ($existing) {
            json_fail(array('message' => 'An activity type with this name already exists in the database.', 'duplicate' => true));
        }

        $new_id = db_insert('activity_types', array('activity_type' => $activity_type));
        if ($new_id !== false) {
            json_success(array('message' => 'Activity type added successfully', 'id' => $new_id, 'name' => $activity_type));
        } else {
            json_fail('Error adding activity type');
        }
        break;

    case 'update':
        $id = intval($_POST['id']);
        $activity_type = strtoupper(trim($_POST['activity_type']));

        $existing = db_get_row("SELECT id FROM activity_types WHERE UPPER(activity_type) = ? AND id != ?", array($activity_type, $id));
        if ($existing) {
            json_fail(array('message' => 'An activity type with this name already exists in the database.', 'duplicate' => true));
        }

        $result = db_update('activity_types', array('activity_type' => $activity_type), array('id' => $id));
        if ($result !== false) {
            json_success(array('message' => 'Activity type updated successfully'));
        } else {
            json_fail('Error updating activity type');
        }
        break;

    case 'delete':
        $id = intval($_POST['id']);
        $in_use = db_get_var("SELECT COUNT(*) FROM activities WHERE activity_type_id = ?", array($id));
        if ($in_use > 0) {
            json_fail('Cannot delete: Activity type is in use');
        }

        $result = db_delete('activity_types', array('id' => $id));
        if ($result !== false) {
            json_success(array('message' => 'Activity type deleted successfully'));
        } else {
            json_fail('Error deleting activity type');
        }
        break;

    default:
        json_fail('Unknown action');
}
