<?php
/**
 * Builds the printable "Day Sheet" as a real, styled .xlsx file -- no
 * external library, just PHP's built-in ZipArchive writing the raw Office
 * Open XML parts directly (same dependency-free approach the Kearney Rides
 * import uses in reverse to READ an xlsx).
 *
 * Layout and color rules follow the paper day sheet / Morgan's original
 * spec photos directly:
 *   - Admin section at the top is split into four boxes, same as the
 *     paper form: Reservations/Member/Guest/CX-NCNS/Walk Ins/Total on the
 *     left; a Bus Riders/x2 Trips/x1 Trips box in the middle; an AIS box
 *     on the right; and a Volunteers/Member Money/Guest Money/Total Money
 *     box below the Bus box. Only the counts the system can actually know
 *     ahead of time are filled in (Reservations, Member, Guest, Bus
 *     Riders, Volunteers) -- everything day-of (CX/NCNS, Walk Ins, Total,
 *     trip counts, AIS, money) is left blank for staff to fill by hand.
 *   - Renew cell: orange + "GUEST" for a guest (no renewal date expected);
 *     red + blank for a non-guest member with no renewal date on file
 *     (flags staff to hand them paperwork); red + the date if the
 *     renewal date has already passed; yellow + the date if it expires
 *     within 30 days; plain otherwise.
 *   - Member Name cell: green for a bus rider, else orange for a guest,
 *     else light blue for a volunteer, else plain. (Bus > Guest >
 *     Volunteer when more than one applies to the same person.)
 *
 * $counts is an assoc array with: reservation_count, member_count,
 * guest_count, volunteer_count.
 * $rows is an array of objects/arrays with: renewal_date, last_name,
 * first_name, is_volunteer, is_guest, bus_rider (as returned by the
 * day_sheet DB query).
 * $today is a 'Y-m-d' string used to judge renewal dates against;
 * defaults to the server's current date if omitted (pass the app's
 * central_time()->format('Y-m-d') for Central-time accuracy).
 */
function output_day_sheet_xlsx($date_label, $counts, $rows, $today = null) {
    if (!$today) {
        $today = date('Y-m-d');
    }
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
        . '<fills count="8">'
        . '<fill><patternFill patternType="none"/></fill>'
        . '<fill><patternFill patternType="gray125"/></fill>'
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFFCE4D6"/><bgColor indexed="64"/></patternFill></fill>' // 2 light orange (guest)
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFD9D9D9"/><bgColor indexed="64"/></patternFill></fill>' // 3 light gray (table header)
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFC6EFCE"/><bgColor indexed="64"/></patternFill></fill>' // 4 green (bus rider)
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFDDEBF7"/><bgColor indexed="64"/></patternFill></fill>' // 5 light blue (volunteer)
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFC7CE"/><bgColor indexed="64"/></patternFill></fill>' // 6 red (expired / needs paperwork)
        . '<fill><patternFill patternType="solid"><fgColor rgb="FFFFEB9C"/><bgColor indexed="64"/></patternFill></fill>' // 7 yellow (expiring soon)
        . '</fills>'
        . '<borders count="2">'
        . '<border><left/><right/><top/><bottom/><diagonal/></border>'
        . '<border><left style="thin"><color indexed="64"/></left><right style="thin"><color indexed="64"/></right><top style="thin"><color indexed="64"/></top><bottom style="thin"><color indexed="64"/></bottom><diagonal/></border>'
        . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="12">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' // 0 default
        . '<xf numFmtId="0" fontId="2" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' // 1 title (boxed)
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="1" xfId="0" applyFont="1" applyBorder="1"/>' // 2 label
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>' // 3 value
        . '<xf numFmtId="0" fontId="1" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center"/></xf>' // 4 header
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>' // 5 data normal
        . '<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 6 name: bus rider (green)
        . '<xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 7 name: volunteer (light blue)
        . '<xf numFmtId="0" fontId="0" fillId="2" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 8 guest (orange) -- used for both Renew and Name
        . '<xf numFmtId="0" fontId="0" fillId="6" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 9 renew: expired / needs paperwork (red)
        . '<xf numFmtId="0" fontId="0" fillId="7" borderId="1" xfId="0" applyFill="1" applyBorder="1"/>' // 10 renew: expiring soon (yellow)
        . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>' // 11 bold, no border (section captions)
        . '</cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
        . '</styleSheet>';

    // --- sparse grid: $grid[row][col] = array('v'=>.., 's'=>.., 't'=>'str') ---
    $grid = array();
    $maxRow = 0;
    $maxCol = 1; // 1=A

    $colIndex = array('A' => 1, 'B' => 2, 'C' => 3, 'D' => 4, 'E' => 5, 'F' => 6, 'G' => 7);

    $setCell = function ($row, $col, $value, $style) use (&$grid, &$maxRow, &$maxCol, $colIndex) {
        $grid[$row][$col] = array('v' => $value, 's' => $style, 't' => 'str');
        if ($row > $maxRow) { $maxRow = $row; }
        if ($colIndex[$col] > $maxCol) { $maxCol = $colIndex[$col]; }
    };

    // --- title (boxed, merged across the full width) ---
    $titleRow = 1;
    $setCell($titleRow, 'A', $date_label, 1);

    // --- bus rider count (computable ahead of time, like reservations) ---
    $bus_count = 0;
    foreach ($rows as $r) {
        if ((is_array($r) ? $r['bus_rider'] : $r->bus_rider) == 1) { $bus_count++; }
    }

    // --- admin section: four boxes, matching the paper form's layout ---
    // Left box: Reservations / Member / Guest / CX-NCNS / Walk Ins / Total
    $leftRow = 3;
    $leftPairs = array(
        array('Reservations:', $counts['reservation_count']),
        array('Member:', $counts['member_count']),
        array('Guest:', $counts['guest_count']),
        array('CX/NCNS:', ''),
        array('Walk Ins:', ''),
        array('Total:', ''),
    );
    foreach ($leftPairs as $pair) {
        $setCell($leftRow, 'A', $pair[0], 2);
        $setCell($leftRow, 'B', $pair[1], 3);
        $leftRow++;
    }

    // Middle box: Bus Riders / x2 Trips / x1 Trips
    $busRow = 3;
    $busPairs = array(
        array('Bus Riders:', $bus_count),
        array('x2 Trips:', ''),
        array('x1 Trips:', ''),
    );
    foreach ($busPairs as $pair) {
        $setCell($busRow, 'D', $pair[0], 2);
        $setCell($busRow, 'E', $pair[1], 3);
        $busRow++;
    }

    // Right box: AIS
    $setCell(3, 'F', 'AIS:', 2);
    $setCell(3, 'G', '', 3);

    // Bottom-middle box: Volunteers / Member Money / Guest Money / Total Money
    $moneyRow = 7;
    $moneyPairs = array(
        array('Volunteers:', $counts['volunteer_count']),
        array('Member Money:', '$'),
        array('Guest Money:', '$'),
        array('Total Money:', '$'),
    );
    foreach ($moneyPairs as $pair) {
        $setCell($moneyRow, 'D', $pair[0], 2);
        $setCell($moneyRow, 'E', $pair[1], 3);
        $moneyRow++;
    }

    // --- roster table ---
    $headerRow = 11;
    $setCell($headerRow, 'A', 'RENEW', 4);
    $setCell($headerRow, 'B', 'MEMBER NAME', 4);
    $setCell($headerRow, 'C', 'SIGN', 4);
    $setCell($headerRow, 'D', 'CX/NCNS', 4);
    $setCell($headerRow, 'E', 'BUS', 4);
    $setCell($headerRow, 'F', 'NOTES', 4);

    $thirtyDays = 30 * 86400;
    $todayTs = strtotime($today);

    $dataRow = $headerRow + 1;
    foreach ($rows as $r) {
        $is_guest = (is_array($r) ? $r['is_guest'] : $r->is_guest) == 1;
        $is_volunteer = (is_array($r) ? $r['is_volunteer'] : $r->is_volunteer) == 1;
        $renewal_date = is_array($r) ? $r['renewal_date'] : $r->renewal_date;
        $last_name = is_array($r) ? $r['last_name'] : $r->last_name;
        $first_name = is_array($r) ? $r['first_name'] : $r->first_name;
        $bus_rider = (is_array($r) ? $r['bus_rider'] : $r->bus_rider) == 1;

        // Renew cell.
        if ($is_guest) {
            $renew = 'GUEST';
            $renewStyle = 8;
        } elseif (!$renewal_date) {
            // Not flagged a guest, but no renewal date on file -- a new
            // member who hasn't filled out paperwork yet. Flag in red,
            // leave blank (not "GUEST") so staff know to hand them a form.
            $renew = '';
            $renewStyle = 9;
        } else {
            $renewTs = strtotime($renewal_date);
            $renew = date('n/j', $renewTs);
            if ($renewTs < $todayTs) {
                $renewStyle = 9; // expired -> red
            } elseif (($renewTs - $todayTs) <= $thirtyDays) {
                $renewStyle = 10; // expiring within a month -> yellow
            } else {
                $renewStyle = 5; // plain
            }
        }

        // Member Name cell: bus (green) > guest (orange) > volunteer (blue).
        if ($bus_rider) {
            $nameStyle = 6;
        } elseif ($is_guest) {
            $nameStyle = 8;
        } elseif ($is_volunteer) {
            $nameStyle = 7;
        } else {
            $nameStyle = 5;
        }

        $name = $last_name . ', ' . $first_name . ($is_volunteer ? ' (VOL)' : '');

        $setCell($dataRow, 'A', $renew, $renewStyle);
        $setCell($dataRow, 'B', $name, $nameStyle);
        $setCell($dataRow, 'C', '', 5);
        $setCell($dataRow, 'D', 'CX/NCNS', 5);
        $setCell($dataRow, 'E', $bus_rider ? 'Yes' : '', 5);
        $setCell($dataRow, 'F', '', 5);
        $dataRow++;
    }

    $lastRow = max($maxRow, $dataRow - 1);
    $lastColLetter = array_search($maxCol, $colIndex) ?: 'F';

    // --- sheetData XML ---
    $sheetDataXml = '';
    ksort($grid);
    foreach ($grid as $rowNum => $cells) {
        $sheetDataXml .= '<row r="' . $rowNum . '">';
        // Emit columns in sheet order (A..G) regardless of insertion order.
        foreach (array('A', 'B', 'C', 'D', 'E', 'F', 'G') as $col) {
            if (!isset($cells[$col])) { continue; }
            $cell = $cells[$col];
            $ref = $col . $rowNum;
            $sheetDataXml .= '<c r="' . $ref . '" s="' . $cell['s'] . '" t="inlineStr"><is><t xml:space="preserve">' . $xml($cell['v']) . '</t></is></c>';
        }
        $sheetDataXml .= '</row>';
    }

    $dimension = 'A1:' . $lastColLetter . $lastRow;

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
        . '<dimension ref="' . $dimension . '"/>'
        . '<sheetFormatPr defaultRowHeight="15"/>'
        . '<cols>'
        . '<col min="1" max="1" width="16" customWidth="1"/>'
        . '<col min="2" max="2" width="28" customWidth="1"/>'
        . '<col min="3" max="3" width="12" customWidth="1"/>'
        . '<col min="4" max="4" width="14" customWidth="1"/>'
        . '<col min="5" max="5" width="10" customWidth="1"/>'
        . '<col min="6" max="6" width="18" customWidth="1"/>'
        . '<col min="7" max="7" width="10" customWidth="1"/>'
        . '</cols>'
        . '<sheetData>' . $sheetDataXml . '</sheetData>'
        . '<mergeCells count="1"><mergeCell ref="A' . $titleRow . ':' . $lastColLetter . $titleRow . '"/></mergeCells>'
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
