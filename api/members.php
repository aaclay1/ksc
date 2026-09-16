<?php
require_once __DIR__ . '/../db.php';

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch ($action) {

    case 'list':
        $rows = db_get_results("SELECT id, first_name, last_name FROM members ORDER BY last_name, first_name");
        if ($rows) {
            json_success($rows);
        } else {
            json_fail('No members found');
        }
        break;

    case 'get':
        $id = intval($_GET['id']);
        $row = db_get_row(
            "SELECT first_name, last_name, phone_number, email, is_60_plus, is_clay_resident, barcode FROM members WHERE id = ?",
            array($id)
        );
        if ($row) {
            json_success($row);
        } else {
            json_fail('Record not found');
        }
        break;

    case 'add':
        $firstName = strtoupper(trim($_POST['firstName']));
        $lastName  = strtoupper(trim($_POST['lastName']));

        $existing = db_get_row(
            "SELECT id FROM members WHERE UPPER(first_name) = ? AND UPPER(last_name) = ?",
            array($firstName, $lastName)
        );
        if ($existing) {
            json_fail(array('message' => 'A member with this name already exists in the database.', 'duplicate' => true));
        }

        $phone = trim($_POST['phoneNumber']);
        $email = strtoupper(trim($_POST['email']));
        $is_60_plus = !empty($_POST['is_60_plus']) ? 1 : 0;
        $is_clay_resident = !empty($_POST['is_clay_resident']) ? 1 : 0;
        $barcode = generate_unique_barcode('members');

        $new_id = db_insert('members', array(
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone_number' => $phone,
            'email' => $email,
            'is_60_plus' => $is_60_plus,
            'is_clay_resident' => $is_clay_resident,
            'barcode' => $barcode,
            'date_added' => central_time()->format('Y-m-d H:i:s'),
        ));

        if ($new_id !== false) {
            json_success(array(
                'message' => 'Record added successfully',
                'id' => $new_id,
                'name' => $lastName . ', ' . $firstName,
                'barcode' => $barcode,
            ));
        } else {
            json_fail('Error adding record');
        }
        break;

    case 'update':
        $id = intval($_POST['id']);
        $firstName = strtoupper(trim($_POST['firstName']));
        $lastName  = strtoupper(trim($_POST['lastName']));

        $existing = db_get_row(
            "SELECT id FROM members WHERE UPPER(first_name) = ? AND UPPER(last_name) = ? AND id != ?",
            array($firstName, $lastName, $id)
        );
        if ($existing) {
            json_fail(array('message' => 'A member with this name already exists in the database.', 'duplicate' => true));
        }

        $phone = trim($_POST['phoneNumber']);
        $email = strtoupper(trim($_POST['email']));
        $is_60_plus = !empty($_POST['is_60_plus']) ? 1 : 0;
        $is_clay_resident = !empty($_POST['is_clay_resident']) ? 1 : 0;

        $result = db_update('members', array(
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone_number' => $phone,
            'email' => $email,
            'is_60_plus' => $is_60_plus,
            'is_clay_resident' => $is_clay_resident,
        ), array('id' => $id));

        if ($result !== false) {
            json_success(array('message' => 'Record updated successfully'));
        } else {
            json_fail('Error updating record');
        }
        break;

    case 'delete':
        $id = intval($_POST['id']);
        $result = db_delete('members', array('id' => $id));
        if ($result !== false) {
            json_success(array('message' => 'Record deleted successfully'));
        } else {
            json_fail('Error deleting record');
        }
        break;

    default:
        json_fail('Unknown action');
}
