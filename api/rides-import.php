<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!isset($_FILES['file'])) {
    json_fail('No file uploaded');
}

$file = $_FILES['file'];
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

try {
    if ($file_ext === 'xlsx') {
        if (!class_exists('ZipArchive')) {
            throw new Exception('The PHP zip extension is not available on this server');
        }

        $zip = new ZipArchive;
        if ($zip->open($file['tmp_name']) === true) {
            $strings_xml = $zip->getFromName('xl/sharedStrings.xml');
            $worksheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();

            if (!$strings_xml || !$worksheet_xml) {
                throw new Exception('Could not read XLSX content');
            }

            $strings_xml = simplexml_load_string($strings_xml);
            $worksheet_xml = simplexml_load_string($worksheet_xml);

            if (!$strings_xml || !$worksheet_xml) {
                throw new Exception('Could not parse XLSX content');
            }

            $shared_strings = array();
            foreach ($strings_xml->si as $si) {
                $shared_strings[] = (string) $si->t;
            }

            $data = array();
            foreach ($worksheet_xml->sheetData->row as $row) {
                $row_data = array();
                foreach ($row->c as $cell) {
                    $value = (string) $cell->v;
                    if ((string) $cell['t'] === 's') {
                        $value = $shared_strings[(int) $value];
                    }
                    $row_data[] = $value;
                }
                if (!empty($row_data)) {
                    $data[] = $row_data;
                }
            }
        } else {
            throw new Exception('Could not open XLSX file');
        }
    } else {
        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            throw new Exception('Could not read file');
        }
        $data = array_map('str_getcsv', explode("\n", $content));
    }

    $activity_id = db_get_var("SELECT id FROM activities WHERE activity_name = ?", array('KEARNEY RIDES'));
    if (!$activity_id) {
        throw new Exception('Kearney Rides activity not found');
    }

    $current_date = central_time()->format('Y-m-d H:i:s');
    $success_count = 0;
    $error_count = 0;

    $headers = array_map('trim', $data[0]);
    $name_index = array_search('Name', $headers);
    if ($name_index === false) {
        throw new Exception('Name column not found in file');
    }

    for ($i = 1; $i < count($data); $i++) {
        $row = $data[$i];
        if (empty($row[$name_index])) continue;

        $full_name = trim($row[$name_index]);
        $name_parts = explode(' ', $full_name);
        $last_name = array_pop($name_parts);
        $first_name = implode(' ', $name_parts);

        $member = db_get_row(
            "SELECT id FROM members WHERE first_name = ? AND last_name = ?",
            array($first_name, $last_name)
        );

        if ($member) {
            $id = db_insert('member_signin', array(
                'member_id' => $member->id,
                'activity_id' => $activity_id,
                'signin_date' => $current_date,
                'volunteer_hours' => 0,
            ));
            $id !== false ? $success_count++ : $error_count++;
        } else {
            $error_count++;
        }
    }

    json_success(array(
        'message' => sprintf('Import complete. Successfully imported %d records. Failed to import %d records.', $success_count, $error_count),
    ));

} catch (Exception $e) {
    json_fail('Import error: ' . $e->getMessage());
}
