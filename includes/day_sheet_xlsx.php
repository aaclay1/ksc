<?php
/**
 * Builds the printable "Day Sheet" as a real, styled .xlsx file -- no
 * external library, just PHP's built-in ZipArchive writing the raw Office
 * Open XML parts directly (same dependency-free approach the Kearney Rides
 * import uses in reverse to READ an xlsx). Bordered boxes, a bold header
 * row, and light fill colors for volunteer/guest rows make it print the
 * way the paper version does.
 *
 * $counts is an assoc array with: reservation_count, member_count,
 * guest_count, volunteer_count.
 * $rows is an array of objects/arrays with: renewal_date, last_name,
 * first_name, is_volunteer, is_guest, bus_rider (as returned by the
 * day_sheet DB query).
 */
function output_day_sheet_xlsx($date_label, $counts, $rows) {
    $xml = function ($text) {
        return htmlspecialchars((string) $text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    };

    // --- styles.xml ---
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="3">'
        . '<font><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
        . '<font><b/><sz val="18"/><name val="Calibri"/></font>'
        . '</fonts>'
        . '<fills count="5">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFDDEBF7"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFFCE4D6"/><bgColor indexed="64"/></patternFill></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9D9D9"/><bgColor indexed="64"/></patternFill></fill>'
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left style="thin"><color indexed="64"/></left><right style="thin"><color indexed="64"/></right><top style="thin"><color indexed="64"/></top><bottom style="thin"><color indexed="64"/></bottom><diagonal/></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="8">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' // 0 default
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 1 title
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>' // 2 label
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>' // 3 value
        . '<xf numFmtId="0" fontId="1" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>' // 4 header
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>' // 5 data normal
        . '<xf numFmtId="0" fontId="0" fillId="2" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 6 data volunteer
        . '<xf numFmtId="0" fontId="0" fillId="3" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 7 data guest
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    // --- build sheetData rows ---
    $sheetRows = array(); // each: array('cells' => array('A' => array('v'=>.., 's'=>.., 'type'=>'str'|'num')))
    $rowNum = 1;

    $addRow = function ($cells) use (&$sheetRows, &$rowNum) {
        $sheetRows[] = array('row' => $rowNum, 'cells' => $cells);
        $rowNum++;
    };

    $addRow(array('A' => array('v' => $date_label, 's' => 1, 't' => 'str')));
    $titleRow = 1;
    $rowNum++; // blank spacer row

    $labelValue = function ($label, $value) use (&$addRow) {
        $addRow(array(
            'A' => array('v' => $label, 's' => 2, 't' => 'str'),
            'B' => array('v' => $value, 's' => 3, 't' => 'str'),
        ));
    };

    $labelValue('Reservations:', $counts['reservation_count']);
    $labelValue('Member:', $counts['member_count']);
    $labelValue('Guest:', $counts['guest_count']);
    $labelValue('CX/NCNS:', '');
    $labelValue('Walk Ins:', '');
    $labelValue('Total:', '');
    $labelValue('Bus (x2):', '');
    $labelValue('Bus (x1):', '');
    $labelValue('AIS:', '');
    $labelValue('Volunteers:', $counts['volunteer_count']);
    $labelValue('Member Money:', '$');
    $labelValue('Guest Money:', '$');
    $labelValue('Total Money:', '$');

    $rowNum++; // blank spacer row

    $addRow(array(
        'A' => array('v' => 'Renew', 's' => 4, 't' => 'str'),
        'B' => array('v' => 'Member Name', 's' => 4, 't' => 'str'),
        'C' => array('v' => 'Sign', 's' => 4, 't' => 'str'),
        'D' => array('v' => 'CX/NCNS', 's' => 4, 't' => 'str'),
        'E' => array('v' => 'Bus', 's' => 4, 't' => 'str'),
        'F' => array('v' => 'Notes', 's' => 4, 't' => 'str'),
    ));

    foreach ($rows as $r) {
        $is_guest = (is_array($r) ? $r['is_guest'] : $r->is_guest) == 1;
        $is_volunteer = (is_array($r) ? $r['is_volunteer'] : $r->is_volunteer) == 1;
        $renewal_date = is_array($r) ? $r['renewal_date'] : $r->renewal_date;
        $last_name = is_array($r) ? $r['last_name'] : $r->last_name;
        $first_name = is_array($r) ? $r['first_name'] : $r->first_name;
        $bus_rider = (is_array($r) ? $r['bus_rider'] : $r->bus_rider) == 1;

        $style = $is_guest ? 7 : ($is_volunteer ? 6 : 5);

        if ($is_guest) {
            $renew = 'GUEST';
        } elseif ($renewal_date) {
            $renew = date('n/j', strtotime($renewal_date));
        } else {
            $renew = '';
        }

        $name = $last_name . ', ' . $first_name . ($is_volunteer ? ' (VOL)' : '');

        $addRow(array(
            'A' => array('v' => $renew, 's' => $style, 't' => 'str'),
            'B' => array('v' => $name, 's' => $style, 't' => 'str'),
            'C' => array('v' => '', 's' => $style, 't' => 'str'),
            'D' => array('v' => 'CX/NCNS', 's' => $style, 't' => 'str'),
            'E' => array('v' => $bus_rider ? 'Yes' : '', 's' => $style, 't' => 'str'),
            'F' => array('v' => '', 's' => $style, 't' => 'str'),
        ));
    }

    $lastRow = $rowNum - 1;

    // --- sheetData XML ---
    $sheetDataXml = '';
    foreach ($sheetRows as $rowDef) {
        $sheetDataXml .= '<row r="' . $rowDef['row'] . '">';
        foreach ($rowDef['cells'] as $col => $cell) {
            $ref = $col . $rowDef['row'];
            if ($cell['t'] === 'str') {
                $sheetDataXml .= '<c r="' . $ref . '" s="' . $cell['s'] . '" t="inlineStr"><is><t xml:space="preserve">' . $xml($cell['v']) . '</t></is></c>';
            } else {
                $sheetDataXml .= '<c r="' . $ref . '" s="' . $cell['s'] . '"><v>' . $xml($cell['v']) . '</v></c>';
            }
        }
        $sheetDataXml .= '</row>';
    }

    $dimension = 'A1:F' . $lastRow;

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
        . '<dimension ref="' . $dimension . '"/>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . '<cols>'
        . '<col min="1" max="1" width="16" customWidth="1"/>'
        . '<col min="2" max="2" width="28" customWidth="1"/>'
        . '<col min="3" max="3" width="12" customWidth="1"/>'
        . '<col min="4" max="4" width="12" customWidth="1"/>'
        . '<col min="5" max="5" width="8" customWidth="1"/>'
        . '<col min="6" max="6" width="22" customWidth="1"/>'
        . '</cols>'
        . '<sheetData>' . $sheetDataXml . '</sheetData>'
        . '<mergeCells count="1"><mergeCell ref="A' . $titleRow . ':F' . $titleRow . '"/></mergeCells>'
        . '<printOptions horizontalCentered="1"/>'
        . '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
        . '<pageSetup paperSize="1" orientation="portrait" fitToWidth="1" fitToHeight="0"/>'
        . '</worksheet>';

    // --- workbook.xml / rels / content types ---
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Day Sheet" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';

    $rootRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>';

    $tmpFile = tempnam(sys_get_temp_dir(), 'ksc_xlsx_');
    $zip = new ZipArchive();
    $zip->open($tmpFile, ZipArchive::OVERWRITE);
    $zip->addEmptyDir('_rels');
    $zip->addEmptyDir('xl');
    $zip->addEmptyDir('xl/_rels');
    $zip->addEmptyDir('xl/worksheets');
    $zip->addFromString('[Content_Types].xml', $contentTypes);
    $zip->addFromString('_rels/.rels', $rootRels);
    $zip->addFromString('xl/workbook.xml', $workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    $zip->addFromString('xl/styles.xml', $styles);
    $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $zip->close();

    return $tmpFile;
}
