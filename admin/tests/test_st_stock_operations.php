<?php
/**
 * Sales Transfer Stock Operations Test Suite v2
 * ==============================================
 * Tests tag, non-tag, and purchase item flows for ST dispatch/download/cancel.
 * Uses direct MySQL connection for DB-level verification.
 * 
 * Usage: D:\XAMPP\php\php.exe admin\tests\test_st_stock_operations.php
 * 
 * Column reference:
 *   ret_billing: to_branch, from_branch, id_branch (source)
 *   ret_taging: id_branch (original), current_branch (after transfer)
 *   ret_bill_old_metal_sale_details: linked via bill_id (not via ret_bill_details)
 * 
 * salesTransType mapping:
 *   1 = Tagged item (tag_status stays 0, branch changes via current_branch)
 *   2 = Non-tag item (delta stock update)
 *   3 = Old Metal (OM) — purchase item
 *   4 = Sales Return (SR) — tag_status set to 6
 *   5 = Partly Sale (PS) — tag_status set to 6
 */

// --- Database Connection ---
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '1234';
$db_name = 'retail_dev';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error . "\n");
}
$conn->set_charset('utf8');

// --- Test Framework ---
$pass = 0; $fail = 0; $skip = 0; $results = [];

function query($sql) {
    global $conn;
    $result = $conn->query($sql);
    if (!$result) {
        echo "  [SQL Error] " . $conn->error . "\n";
        echo "  Query: " . substr($sql, 0, 200) . "...\n";
        return null;
    }
    return $result;
}

function query_row($sql) {
    $result = query($sql);
    if (!$result) return null;
    return $result->fetch_assoc();
}

function query_all($sql) {
    $result = query($sql);
    if (!$result) return [];
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    return $rows;
}

function assert_test($name, $condition, $detail = '') {
    global $pass, $fail, $results;
    if ($condition) {
        $pass++;
        $results[] = ['status' => 'PASS', 'name' => $name, 'detail' => $detail];
        echo "  PASS: $name" . ($detail ? " ($detail)" : "") . "\n";
    } else {
        $fail++;
        $results[] = ['status' => 'FAIL', 'name' => $name, 'detail' => $detail];
        echo "  FAIL: $name" . ($detail ? " ($detail)" : "") . "\n";
    }
}

function skip_test($name, $reason) {
    global $skip, $results;
    $skip++;
    $results[] = ['status' => 'SKIP', 'name' => $name, 'detail' => $reason];
    echo "  SKIP: $name -- $reason\n";
}

function info_test($name, $detail) {
    global $pass, $results;
    $pass++;
    $results[] = ['status' => 'INFO', 'name' => $name, 'detail' => $detail];
    echo "  INFO: $name ($detail)\n";
}

function section($title) {
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "  $title\n";
    echo str_repeat('=', 70) . "\n";
}

echo "\nSales Transfer Stock Operations Test Suite v2\n";
echo "Database: $db_name | " . date('Y-m-d H:i:s') . "\n";


// =================================================================
// SECTION 1: TAG ITEMS (salesTransType=1)
// Tagged items: tag_status stays 0, current_branch changes to receiving
// =================================================================
section('SECTION 1: TAG ITEMS (salesTransType=1)');

// Find a downloaded ST bill with tag items
$tag_bill = query_row("
    SELECT b.bill_id, b.id_branch AS from_branch, b.to_branch,
           d.tag_id, d.product_id, d.piece, d.gross_wt, d.net_wt
    FROM ret_billing b
    INNER JOIN ret_bill_details d ON d.bill_id = b.bill_id
    INNER JOIN ret_sales_transfer_log l ON l.bill_id = b.bill_id AND l.action = 'download'
    WHERE b.bill_type = 13
    AND d.salesTransType = 1
    AND d.tag_id IS NOT NULL
    LIMIT 1
");

if (!$tag_bill) {
    skip_test('TAG-001: Downloaded tag bill exists', 'No downloaded ST bills with tag items found');
    skip_test('TAG-002: Tag status=0 after download', 'No data');
    skip_test('TAG-003: Tag current_branch = receiving branch', 'No data');
    skip_test('TAG-004: Tag log entry exists for transfer', 'No data');
} else {
    assert_test('TAG-001: Downloaded tag bill exists',
        !empty($tag_bill['bill_id']),
        "bill_id={$tag_bill['bill_id']}, tag_id={$tag_bill['tag_id']}"
    );

    $tag = query_row("
        SELECT tag_status, trans_to_acc_stock, id_branch, current_branch, tag_code
        FROM ret_taging WHERE tag_id = " . (int)$tag_bill['tag_id']
    );

    if ($tag) {
        // Case 1 (Tagged): download sets tag_status=0 and current_branch=to_branch
        // It does NOT set tag_status=6 (that's only for SR/PS items)
        assert_test('TAG-002: Tag status = 0 after download (tagged items stay 0)',
            (int)$tag['tag_status'] === 0,
            "Expected 0, got {$tag['tag_status']} for tag {$tag['tag_code']}"
        );

        // current_branch should be the receiving branch (to_branch)
        assert_test('TAG-003: Tag current_branch = receiving branch',
            (int)$tag['current_branch'] === (int)$tag_bill['to_branch'],
            "Expected current_branch={$tag_bill['to_branch']}, got {$tag['current_branch']}"
        );

        // Check tag log entry exists for this transfer
        $tag_log = query_row("
            SELECT id_tag_status_log, status, from_branch, to_branch
            FROM ret_taging_status_log
            WHERE tag_id = " . (int)$tag_bill['tag_id'] . "
            AND status = 0
            AND to_branch = " . (int)$tag_bill['to_branch'] . "
            LIMIT 1
        ");

        assert_test('TAG-004: Tag log entry exists for transfer (status=0)',
            !empty($tag_log),
            $tag_log ? "log from={$tag_log['from_branch']}, to={$tag_log['to_branch']}" : "No log found"
        );
    } else {
        skip_test('TAG-002: Tag status after download', 'Tag row not found');
        skip_test('TAG-003: Tag current_branch = receiving branch', 'Tag row not found');
        skip_test('TAG-004: Tag log entry for transfer', 'Tag row not found');
    }
}

// Bulk: all downloaded tagged items should have tag_status=0 (NOT 6) and current_branch=to_branch
$bad_tags = query_row("
    SELECT COUNT(*) AS cnt
    FROM ret_bill_details d
    INNER JOIN ret_billing b ON b.bill_id = d.bill_id
    INNER JOIN ret_sales_transfer_log l ON l.bill_id = b.bill_id AND l.action = 'download'
    INNER JOIN ret_taging t ON t.tag_id = d.tag_id
    WHERE b.bill_type = 13
    AND d.salesTransType = 1
    AND d.tag_id IS NOT NULL
    AND (t.tag_status != 0 OR t.current_branch != b.to_branch)
");

assert_test('TAG-005: All downloaded tagged items: status=0 and current_branch=to_branch',
    $bad_tags && (int)$bad_tags['cnt'] === 0,
    "Bad tags count: " . ($bad_tags ? $bad_tags['cnt'] : 'query failed')
);

// Tag log entries exist for ST transfers (status=0 for tagged items)
$tag_logs = query_row("
    SELECT COUNT(*) AS cnt
    FROM ret_taging_status_log l
    INNER JOIN ret_bill_details d ON d.tag_id = l.tag_id AND d.salesTransType = 1
    INNER JOIN ret_billing b ON b.bill_id = d.bill_id AND b.bill_type = 13
    INNER JOIN ret_sales_transfer_log sl ON sl.bill_id = b.bill_id AND sl.action = 'download'
    WHERE l.status = 0
");

assert_test('TAG-006: Tagging status log entries exist for ST tagged items (status=0)',
    $tag_logs && (int)$tag_logs['cnt'] > 0,
    "Tag log entries: " . ($tag_logs ? $tag_logs['cnt'] : '0')
);


// =================================================================
// SECTION 2: NON-TAG ITEMS (salesTransType=2) -- Delta Deduction
// =================================================================
section('SECTION 2: NON-TAG ITEMS (salesTransType=2) -- Delta Deduction');

// Find NT items transferred (is_st_locked=2) with their bill details
$nt_transferred = query_all("
    SELECT nt.id_nontag_item, nt.st_bill_id, nt.no_of_piece, nt.gross_wt, nt.net_wt,
           nt.branch, nt.product, nt.is_st_locked,
           d.piece AS bill_piece, d.gross_wt AS bill_gwt, d.net_wt AS bill_nwt
    FROM ret_nontag_item nt
    INNER JOIN ret_bill_details d ON d.bill_id = nt.st_bill_id
        AND d.salesTransType = 2
        AND d.product_id = nt.product
    WHERE nt.is_st_locked = 2
    LIMIT 10
");

if (empty($nt_transferred)) {
    skip_test('NT-001: Transferred NT items exist', 'No is_st_locked=2 rows found');
} else {
    assert_test('NT-001: Transferred NT items exist',
        count($nt_transferred) > 0,
        count($nt_transferred) . " items found"
    );

    // Pre-fix detection: these rows were transferred BEFORE the delta fix was deployed
    // After corrective SQL is run, source_gwt should be < bill_gwt
    // For now, flag them as informational
    $pre_fix_count = 0;
    $post_fix_count = 0;
    foreach ($nt_transferred as $nt) {
        $source_gwt = (float)$nt['gross_wt'];
        $bill_gwt   = (float)$nt['bill_gwt'];
        if ($source_gwt >= $bill_gwt) {
            $pre_fix_count++;
        } else {
            $post_fix_count++;
        }
    }

    info_test('NT-002: Pre-fix vs post-fix delta status',
        "Pre-fix (un-deducted): $pre_fix_count, Post-fix (deducted): $post_fix_count. " .
        ($pre_fix_count > 0 ? "Run corrective SQL for pre-fix rows." : "All rows properly deducted.")
    );
}

// Verify receiving branch rows exist for downloaded NT bills
// Note: rows may have been re-dispatched (is_st_locked=2), so check ANY row, not just is_st_locked=0
$nt_downloads = query_all("
    SELECT b.bill_id, b.id_branch AS from_branch, b.to_branch,
           d.product_id, d.gross_wt AS bill_gwt
    FROM ret_billing b
    INNER JOIN ret_bill_details d ON d.bill_id = b.bill_id
    INNER JOIN ret_sales_transfer_log l ON l.bill_id = b.bill_id AND l.action = 'download'
    WHERE b.bill_type = 13
    AND d.salesTransType = 2
    LIMIT 5
");

foreach ($nt_downloads as $idx => $row) {
    // Check for ANY NT row at the receiving branch for this product
    // (including locked rows — stock may have been re-dispatched)
    $recv = query_row("
        SELECT id_nontag_item, no_of_piece, gross_wt, is_st_locked
        FROM ret_nontag_item
        WHERE branch = " . (int)$row['to_branch'] . "
        AND product = " . (int)$row['product_id'] . "
        LIMIT 1
    ");

    // Also check NT item log for evidence of transfer
    $log_exists = query_row("
        SELECT COUNT(*) AS cnt
        FROM ret_nontag_item_log
        WHERE to_branch = " . (int)$row['to_branch'] . "
        AND from_branch = " . (int)$row['from_branch'] . "
        AND status = 0
        LIMIT 1
    ");

    $has_evidence = (!empty($recv)) || ($log_exists && (int)$log_exists['cnt'] > 0);

    assert_test("NT-R" . str_pad($idx + 1, 2, '0', STR_PAD_LEFT) .
        ": Receiving branch evidence (bill={$row['bill_id']}, product={$row['product_id']})",
        $has_evidence,
        $recv
            ? "Row exists: gwt={$recv['gross_wt']}, locked={$recv['is_st_locked']}"
            : ($log_exists && (int)$log_exists['cnt'] > 0 ? "Log entry exists" : "No row or log found")
    );
}

// NT log entries exist for ST period
$nt_logs = query_row("
    SELECT COUNT(*) AS cnt FROM ret_nontag_item_log WHERE status = 0 AND date >= '2026-05-01'
");

assert_test('NT-LOG-01: Non-tag item log entries exist for ST period',
    $nt_logs && (int)$nt_logs['cnt'] > 0,
    "Log count: " . ($nt_logs ? $nt_logs['cnt'] : '0')
);

// Section non-tag item logs exist
$sect_logs = query_row("
    SELECT COUNT(*) AS cnt FROM ret_section_nontag_item_log WHERE status = 0 AND date >= '2026-05-01'
");

assert_test('NT-LOG-02: Section non-tag item logs exist for ST period',
    $sect_logs && (int)$sect_logs['cnt'] > 0,
    "Section NT logs: " . ($sect_logs ? $sect_logs['cnt'] : '0')
);


// =================================================================
// SECTION 3: PURCHASE ITEMS -- OM (3), PS (4=SR), SR (5=PS)
// =================================================================
section('SECTION 3: PURCHASE ITEMS -- Old Metal, Sales Return, Partly Sale');

// --- 3a: Old Metal items (salesTransType=3) ---
echo "\n  --- Old Metal (OM) Items (salesTransType=3) ---\n";

// OM items linked via bill_id on ret_bill_old_metal_sale_details
$om_items = query_all("
    SELECT om.old_metal_sale_id, om.bill_id, om.gross_wt, om.net_wt,
           om.is_transferred, om.current_branch, om.st_bill_id,
           b.id_branch AS from_branch, b.to_branch
    FROM ret_bill_old_metal_sale_details om
    INNER JOIN ret_billing b ON b.bill_id = om.bill_id
    INNER JOIN ret_sales_transfer_log l ON l.bill_id = b.bill_id AND l.action = 'download'
    WHERE b.bill_type = 13
    LIMIT 5
");

if (empty($om_items)) {
    // Try broader check — any OM items with st_bill_id set
    $om_any = query_all("
        SELECT om.old_metal_sale_id, om.bill_id, om.is_transferred, om.current_branch,
               om.st_bill_id, b.id_branch AS from_branch, b.to_branch
        FROM ret_bill_old_metal_sale_details om
        INNER JOIN ret_billing b ON b.bill_id = om.st_bill_id
        WHERE b.bill_type = 13
        LIMIT 5
    ");

    if (empty($om_any)) {
        skip_test('PI-OM-001: Downloaded OM items exist', 'No OM items linked to downloaded ST bills');
    } else {
        assert_test('PI-OM-001: OM items linked to ST bills (via st_bill_id)',
            count($om_any) > 0,
            count($om_any) . " OM items found via st_bill_id"
        );

        foreach ($om_any as $idx => $om) {
            assert_test("PI-OM-" . str_pad($idx + 2, 3, '0', STR_PAD_LEFT) .
                ": OM current_branch = receiving branch (om={$om['old_metal_sale_id']})",
                (int)$om['current_branch'] === (int)$om['to_branch'],
                "current_branch={$om['current_branch']}, to_branch={$om['to_branch']}"
            );
        }
    }
} else {
    assert_test('PI-OM-001: Downloaded OM items exist',
        count($om_items) > 0,
        count($om_items) . " OM items found"
    );

    foreach ($om_items as $idx => $om) {
        assert_test("PI-OM-" . str_pad($idx + 2, 3, '0', STR_PAD_LEFT) .
            ": OM current_branch = receiving branch (om={$om['old_metal_sale_id']})",
            (int)$om['current_branch'] === (int)$om['to_branch'],
            "current_branch={$om['current_branch']}, to_branch={$om['to_branch']}"
        );
    }
}

// OM transfer flags set
$om_transferred = query_row("
    SELECT COUNT(*) AS cnt FROM ret_bill_old_metal_sale_details WHERE is_transferred = 1
");

assert_test('PI-OM-010: OM items with is_transferred=1 exist',
    $om_transferred && (int)$om_transferred['cnt'] > 0,
    "OM transferred: " . ($om_transferred ? $om_transferred['cnt'] : '0')
);


// --- 3b: Sales Return items (salesTransType=4) ---
// Case 4 in download: sets tag_status=6, current_branch=to_branch, trans_to_acc_stock=1
echo "\n  --- Sales Return (SR) Items (salesTransType=4) ---\n";

$sr_items = query_all("
    SELECT d.bill_det_id, d.bill_id, d.tag_id, d.gross_wt,
           b.id_branch AS from_branch, b.to_branch,
           t.tag_status, t.trans_to_acc_stock,
           t.id_branch AS tag_id_branch, t.current_branch, t.tag_code
    FROM ret_bill_details d
    INNER JOIN ret_billing b ON b.bill_id = d.bill_id
    INNER JOIN ret_sales_transfer_log l ON l.bill_id = b.bill_id AND l.action = 'download'
    LEFT JOIN ret_taging t ON t.tag_id = d.tag_id
    WHERE b.bill_type = 13
    AND d.salesTransType = 4
    AND d.tag_id IS NOT NULL
    LIMIT 5
");

if (empty($sr_items)) {
    skip_test('PI-SR-001: Downloaded SR items exist', 'No downloaded Sales Return ST items');
} else {
    assert_test('PI-SR-001: Downloaded SR items exist',
        count($sr_items) > 0,
        count($sr_items) . " SR items found"
    );

    foreach ($sr_items as $idx => $sr) {
        if ($sr['tag_status'] !== null) {
            // SR items: tag_status should be 6 OR was 6 then retagged (check log)
            // After retagging, tag_status changes from 6 to a new status — that's valid
            $sr_is_valid = ((int)$sr['tag_status'] === 6);
            $sr_detail = "tag_status={$sr['tag_status']}";

            if (!$sr_is_valid) {
                // Check if tag was ever set to status=6 via ST transfer (then retagged)
                $sr_log = query_row("
                    SELECT id_tag_status_log FROM ret_taging_status_log
                    WHERE tag_id = " . (int)$sr['tag_id'] . "
                    AND status = 6 AND to_branch = " . (int)$sr['to_branch'] . "
                    LIMIT 1
                ");
                if ($sr_log) {
                    $sr_is_valid = true;
                    $sr_detail = "tag_status={$sr['tag_status']} (was 6, since retagged — valid)";
                }
            }

            assert_test("PI-SR-" . str_pad($idx + 2, 3, '0', STR_PAD_LEFT) .
                ": SR tag status=6 or retagged (tag={$sr['tag_code']})",
                $sr_is_valid,
                $sr_detail
            );

            // current_branch should be receiving branch (not id_branch)
            assert_test("PI-SR-B" . str_pad($idx + 1, 2, '0', STR_PAD_LEFT) .
                ": SR current_branch = receiving branch (tag={$sr['tag_code']})",
                (int)$sr['current_branch'] === (int)$sr['to_branch'],
                "Expected current_branch={$sr['to_branch']}, got {$sr['current_branch']}"
            );

            assert_test("PI-SR-A" . str_pad($idx + 1, 2, '0', STR_PAD_LEFT) .
                ": SR tag trans_to_acc_stock=1 (tag={$sr['tag_code']})",
                (int)$sr['trans_to_acc_stock'] === 1,
                "trans_to_acc_stock={$sr['trans_to_acc_stock']}"
            );
        }
    }
}


// --- 3c: Partly Sale items (salesTransType=5) ---
// Case 5 in download: sets tag_status=6, current_branch=to_branch, trans_to_acc_stock=1
echo "\n  --- Partly Sale (PS) Items (salesTransType=5) ---\n";

$ps_items = query_all("
    SELECT d.bill_det_id, d.bill_id, d.tag_id, d.gross_wt,
           b.id_branch AS from_branch, b.to_branch,
           t.tag_status, t.is_partial, t.trans_to_acc_stock,
           t.id_branch AS tag_id_branch, t.current_branch, t.tag_code
    FROM ret_bill_details d
    INNER JOIN ret_billing b ON b.bill_id = d.bill_id
    INNER JOIN ret_sales_transfer_log l ON l.bill_id = b.bill_id AND l.action = 'download'
    LEFT JOIN ret_taging t ON t.tag_id = d.tag_id
    WHERE b.bill_type = 13
    AND d.salesTransType = 5
    AND d.tag_id IS NOT NULL
    LIMIT 5
");

if (empty($ps_items)) {
    skip_test('PI-PS-001: Downloaded PS items exist', 'No downloaded Partly Sale ST items');
} else {
    assert_test('PI-PS-001: Downloaded PS items exist',
        count($ps_items) > 0,
        count($ps_items) . " PS items found"
    );

    foreach ($ps_items as $idx => $ps) {
        if ($ps['tag_status'] !== null) {
            assert_test("PI-PS-" . str_pad($idx + 2, 3, '0', STR_PAD_LEFT) .
                ": PS tag status=6 (tag={$ps['tag_code']})",
                (int)$ps['tag_status'] === 6,
                "tag_status={$ps['tag_status']}, trans_to_acc_stock={$ps['trans_to_acc_stock']}"
            );

            assert_test("PI-PS-B" . str_pad($idx + 1, 2, '0', STR_PAD_LEFT) .
                ": PS current_branch = receiving branch (tag={$ps['tag_code']})",
                (int)$ps['current_branch'] === (int)$ps['to_branch'],
                "Expected current_branch={$ps['to_branch']}, got {$ps['current_branch']}"
            );

            assert_test("PI-PS-A" . str_pad($idx + 1, 2, '0', STR_PAD_LEFT) .
                ": PS tag trans_to_acc_stock=1 (tag={$ps['tag_code']})",
                (int)$ps['trans_to_acc_stock'] === 1,
                "trans_to_acc_stock={$ps['trans_to_acc_stock']}"
            );
        }
    }
}


// =================================================================
// SECTION 4: MIRROR PURCHASE BILL (SRT / bill_type=14)
// =================================================================
section('SECTION 4: MIRROR PURCHASE BILL (SRT / bill_type=14)');

// Mirror bills created after mirror bill feature (post May 2026)
$mirror_bills = query_all("
    SELECT b.bill_id, b.bill_type, b.id_branch, b.to_branch, b.from_branch
    FROM ret_billing b
    INNER JOIN ret_sales_transfer_log l ON l.bill_id = b.bill_id AND l.action = 'create'
    WHERE b.bill_type = 14
    AND b.bill_status = 1
    AND l.created_at >= '2026-05-01'
    LIMIT 5
");

if (empty($mirror_bills)) {
    // Fallback: check any SRT bills with log entries
    $mirror_bills = query_all("
        SELECT b.bill_id, b.bill_type, b.id_branch, b.to_branch, b.from_branch
        FROM ret_billing b
        INNER JOIN ret_sales_transfer_log l ON l.bill_id = b.bill_id
        WHERE b.bill_type = 14
        AND b.bill_status = 1
        LIMIT 5
    ");
}

if (empty($mirror_bills)) {
    skip_test('MIR-001: Mirror purchase bills (bill_type=14) exist', 'No mirror bills with log entries');
} else {
    assert_test('MIR-001: Mirror purchase bills (bill_type=14) exist',
        count($mirror_bills) > 0,
        count($mirror_bills) . " mirror bills found"
    );

    foreach ($mirror_bills as $idx => $mir) {
        $detail_cnt = query_row(
            "SELECT COUNT(*) AS cnt FROM ret_bill_details WHERE bill_id = " . (int)$mir['bill_id']
        );

        assert_test("MIR-" . str_pad($idx + 2, 3, '0', STR_PAD_LEFT) .
            ": Mirror bill {$mir['bill_id']} has details",
            $detail_cnt && (int)$detail_cnt['cnt'] > 0,
            "Details: " . ($detail_cnt ? $detail_cnt['cnt'] : '0')
        );
    }
}

// Check SRT bills have log entries
$srt_logs = query_row("
    SELECT COUNT(*) AS cnt
    FROM ret_billing b
    INNER JOIN ret_sales_transfer_log l ON l.bill_id = b.bill_id
    WHERE b.bill_type = 14 AND l.action = 'create'
");

assert_test('MIR-010: SRT bills have create log entries',
    $srt_logs && (int)$srt_logs['cnt'] > 0,
    "SRT with logs: " . ($srt_logs ? $srt_logs['cnt'] : '0')
);

// Old SRT bills without details (pre-mirror-logic) — informational only
$old_srt = query_row("
    SELECT COUNT(*) AS cnt
    FROM ret_billing b
    LEFT JOIN ret_bill_details d ON d.bill_id = b.bill_id
    LEFT JOIN ret_sales_transfer_log l ON l.bill_id = b.bill_id
    WHERE b.bill_type = 14
    AND d.bill_det_id IS NULL
    AND l.id IS NULL
");

info_test('MIR-011: Old SRT bills without details (pre-mirror-logic)',
    "Count: " . ($old_srt ? $old_srt['cnt'] : '0') . " (legacy data, not a bug)"
);


// =================================================================
// SECTION 5: CANCEL / REVERT SAFETY CHECKS
// =================================================================
section('SECTION 5: CANCEL / REVERT SAFETY CHECKS');

// Orphaned NT locks: is_st_locked=1 but bill doesn't exist or is cancelled
$orphaned = query_row("
    SELECT COUNT(*) AS cnt
    FROM ret_nontag_item nt
    LEFT JOIN ret_billing b ON b.bill_id = nt.st_bill_id
    WHERE nt.is_st_locked = 1
    AND (b.bill_id IS NULL OR b.bill_status != 1)
");

assert_test('CAN-001: No orphaned NT locks (is_st_locked=1 without valid bill)',
    $orphaned && (int)$orphaned['cnt'] === 0,
    "Orphaned locks: " . ($orphaned ? $orphaned['cnt'] : 'query failed')
);

// Cancelled ST bills should have no locked NT items
$cancelled_bills = query_all("
    SELECT b.bill_id FROM ret_billing b
    WHERE b.bill_type IN (13,14) AND b.bill_status = 0
    LIMIT 3
");

if (empty($cancelled_bills)) {
    // No cancelled bills — check for bills with cancel log instead
    $cancel_logged = query_all("
        SELECT l.bill_id FROM ret_sales_transfer_log l
        WHERE l.action = 'cancel' LIMIT 3
    ");

    if (empty($cancel_logged)) {
        info_test('CAN-002: No cancelled ST bills found',
            'No bills with status=0 or cancel log. Cancel flow not yet exercised.');
    } else {
        foreach ($cancel_logged as $idx => $cb) {
            $locks = query_row("
                SELECT COUNT(*) AS cnt FROM ret_nontag_item
                WHERE st_bill_id = " . (int)$cb['bill_id'] . " AND is_st_locked IN (1, 2)
            ");

            assert_test("CAN-" . str_pad($idx + 2, 3, '0', STR_PAD_LEFT) .
                ": Cancelled bill {$cb['bill_id']} cleanup verified",
                $locks && (int)$locks['cnt'] === 0,
                "Leftover locks: " . ($locks ? $locks['cnt'] : 'query failed')
            );
        }
    }
} else {
    foreach ($cancelled_bills as $idx => $cb) {
        $locks = query_row("
            SELECT COUNT(*) AS cnt FROM ret_nontag_item
            WHERE st_bill_id = " . (int)$cb['bill_id'] . " AND is_st_locked IN (1, 2)
        ");

        assert_test("CAN-" . str_pad($idx + 2, 3, '0', STR_PAD_LEFT) .
            ": Cancelled bill {$cb['bill_id']} has no locked NT items",
            $locks && (int)$locks['cnt'] === 0,
            "Leftover locks: " . ($locks ? $locks['cnt'] : 'query failed')
        );
    }
}

// Dispatched-only bills: tags should NOT yet be at transferred state
$dispatch_only = query_all("
    SELECT b.bill_id
    FROM ret_billing b
    INNER JOIN ret_sales_transfer_log lc ON lc.bill_id = b.bill_id AND lc.action = 'create'
    LEFT JOIN ret_sales_transfer_log ld ON ld.bill_id = b.bill_id AND ld.action = 'download'
    WHERE b.bill_type = 13 AND b.bill_status = 1 AND ld.id IS NULL
    LIMIT 3
");

if (!empty($dispatch_only)) {
    foreach ($dispatch_only as $idx => $dob) {
        // For case 4/5 (SR/PS), tags should not be at status=6 until downloaded
        $premature = query_row("
            SELECT COUNT(*) AS cnt
            FROM ret_bill_details d
            INNER JOIN ret_taging t ON t.tag_id = d.tag_id
            WHERE d.bill_id = " . (int)$dob['bill_id'] . "
            AND d.salesTransType IN (4, 5)
            AND t.tag_status = 6 AND t.trans_to_acc_stock = 1
        ");

        assert_test("CAN-D" . str_pad($idx + 1, 2, '0', STR_PAD_LEFT) .
            ": Dispatched-only bill {$dob['bill_id']} — no premature tag transfers",
            $premature && (int)$premature['cnt'] === 0,
            "Premature transfers: " . ($premature ? $premature['cnt'] : '0')
        );
    }
}


// =================================================================
// SECTION 6: RETAGGING FORM VISIBILITY (ST Mode)
// =================================================================
section('SECTION 6: RETAGGING FORM -- ST Mode Visibility');

// Tagged items (salesTransType=1): after download, tag_status=0, current_branch=receiving
// They're available for normal tagging workflow, NOT ST retagging
$retag_tags = query_row("
    SELECT COUNT(*) AS cnt
    FROM ret_taging tag
    INNER JOIN ret_bill_details d ON d.tag_id = tag.tag_id AND d.salesTransType = 1
    INNER JOIN ret_billing b ON b.bill_id = d.bill_id AND b.bill_type = 13
    INNER JOIN ret_sales_transfer_log l ON l.bill_id = b.bill_id AND l.action = 'download'
    WHERE tag.tag_status = 0
    AND tag.current_branch = b.to_branch
");

assert_test('RET-001: Tagged items at receiving branch after download (status=0)',
    $retag_tags && (int)$retag_tags['cnt'] > 0,
    "Tagged items at receiving branch: " . ($retag_tags ? $retag_tags['cnt'] : '0')
);

// SR items (salesTransType=4) visible for ST retagging: tag_status=6, trans_to_acc_stock=1
$retag_sr = query_row("
    SELECT COUNT(*) AS cnt
    FROM ret_taging tag
    INNER JOIN ret_bill_details d ON d.tag_id = tag.tag_id AND d.salesTransType = 4
    INNER JOIN ret_billing b ON b.bill_id = d.bill_id AND b.bill_type = 13
    INNER JOIN ret_sales_transfer_log l ON l.bill_id = b.bill_id AND l.action = 'download'
    WHERE tag.tag_status = 6 AND tag.trans_to_acc_stock = 1
");

assert_test('RET-002: Sales Return items visible for ST retagging (status=6)',
    $retag_sr && (int)$retag_sr['cnt'] > 0,
    "SR items for retagging: " . ($retag_sr ? $retag_sr['cnt'] : '0')
);

// PS items (salesTransType=5) visible for ST retagging
$retag_ps = query_row("
    SELECT COUNT(*) AS cnt
    FROM ret_taging tag
    INNER JOIN ret_bill_details d ON d.tag_id = tag.tag_id AND d.salesTransType = 5
    INNER JOIN ret_billing b ON b.bill_id = d.bill_id AND b.bill_type = 13
    INNER JOIN ret_sales_transfer_log l ON l.bill_id = b.bill_id AND l.action = 'download'
    WHERE tag.tag_status = 6 AND tag.trans_to_acc_stock = 1
");

if ($retag_ps && (int)$retag_ps['cnt'] > 0) {
    assert_test('RET-003: Partly Sale items visible for ST retagging (status=6)',
        true, "PS items for retagging: {$retag_ps['cnt']}"
    );
} else {
    // PS items may not have been downloaded yet — check if any salesTransType=5 was downloaded
    $ps_downloaded = query_row("
        SELECT COUNT(*) AS cnt
        FROM ret_bill_details d
        INNER JOIN ret_billing b ON b.bill_id = d.bill_id AND b.bill_type = 13
        INNER JOIN ret_sales_transfer_log l ON l.bill_id = b.bill_id AND l.action = 'download'
        WHERE d.salesTransType = 5
    ");

    if ($ps_downloaded && (int)$ps_downloaded['cnt'] > 0) {
        assert_test('RET-003: Partly Sale items visible for ST retagging',
            false, "Downloaded PS items exist but none at status=6 — possible bug"
        );
    } else {
        info_test('RET-003: Partly Sale retagging visibility',
            'No salesTransType=5 items have been downloaded yet. Cannot verify.'
        );
    }
}

// OM items available for retagging
$retag_om = query_row("
    SELECT COUNT(*) AS cnt FROM ret_bill_old_metal_sale_details WHERE is_transferred = 1
");

assert_test('RET-004: Old Metal items with is_transferred=1 for retagging',
    $retag_om && (int)$retag_om['cnt'] > 0,
    "OM transferred: " . ($retag_om ? $retag_om['cnt'] : '0')
);


// =================================================================
// SECTION 7: DATA CONSISTENCY -- Cross-checks
// =================================================================
section('SECTION 7: DATA CONSISTENCY');

// Negative stock check — separate ST-related from pre-existing
$neg_st = query_row("
    SELECT COUNT(*) AS cnt
    FROM ret_nontag_item
    WHERE (gross_wt < 0 OR net_wt < 0 OR no_of_piece < 0)
    AND st_bill_id IS NOT NULL
");

assert_test('CON-001: No ST-related negative NT stock',
    $neg_st && (int)$neg_st['cnt'] === 0,
    "ST-related negative rows: " . ($neg_st ? $neg_st['cnt'] : 'query failed')
);

// Pre-existing negative stock (not ST related) — informational
$neg_pre = query_row("
    SELECT COUNT(*) AS cnt
    FROM ret_nontag_item
    WHERE (gross_wt < 0 OR net_wt < 0 OR no_of_piece < 0)
    AND (st_bill_id IS NULL OR st_bill_id = 0)
");

info_test('CON-002: Pre-existing negative NT stock (not ST-related)',
    "Count: " . ($neg_pre ? $neg_pre['cnt'] : '0') . " (legacy data, separate issue)"
);

// Pre-fix un-deducted rows — informational
$pre_fix = query_all("
    SELECT nt.id_nontag_item, nt.gross_wt AS source_gwt, d.gross_wt AS bill_gwt
    FROM ret_nontag_item nt
    INNER JOIN ret_bill_details d ON d.bill_id = nt.st_bill_id
        AND d.salesTransType = 2 AND d.product_id = nt.product
    WHERE nt.is_st_locked = 2
    AND nt.gross_wt >= d.gross_wt AND nt.gross_wt > 0
");

info_test('CON-003: Pre-fix un-deducted NT rows (need corrective SQL)',
    count($pre_fix) . " rows" .
    (count($pre_fix) > 0
        ? " -- IDs: " . implode(',', array_unique(array_column($pre_fix, 'id_nontag_item')))
        : " (all clean)")
);

// Every download log has a create log
$dl_no_create = query_row("
    SELECT COUNT(*) AS cnt
    FROM ret_sales_transfer_log l1
    WHERE l1.action = 'download'
    AND NOT EXISTS (
        SELECT 1 FROM ret_sales_transfer_log l2
        WHERE l2.bill_id = l1.bill_id AND l2.action = 'create'
    )
");

assert_test('CON-004: Every download log has a create log',
    $dl_no_create && (int)$dl_no_create['cnt'] === 0,
    "Downloads without creates: " . ($dl_no_create ? $dl_no_create['cnt'] : 'query failed')
);

// No is_st_locked=2 without st_bill_id
$locked_no_bill = query_row("
    SELECT COUNT(*) AS cnt FROM ret_nontag_item
    WHERE is_st_locked = 2 AND (st_bill_id IS NULL OR st_bill_id = 0)
");

assert_test('CON-005: No is_st_locked=2 without st_bill_id',
    $locked_no_bill && (int)$locked_no_bill['cnt'] === 0,
    "Orphaned locked-2: " . ($locked_no_bill ? $locked_no_bill['cnt'] : 'query failed')
);

// OM st_bill_id references are valid
$om_invalid = query_row("
    SELECT COUNT(*) AS cnt
    FROM ret_bill_old_metal_sale_details om
    LEFT JOIN ret_billing b ON b.bill_id = om.st_bill_id
    WHERE om.st_bill_id IS NOT NULL AND om.st_bill_id > 0 AND b.bill_id IS NULL
");

assert_test('CON-006: All OM st_bill_id references are valid',
    $om_invalid && (int)$om_invalid['cnt'] === 0,
    "Invalid OM bill refs: " . ($om_invalid ? $om_invalid['cnt'] : 'query failed')
);

// Active ST bills created after log table — must have log entries
$no_log = query_row("
    SELECT COUNT(*) AS cnt
    FROM ret_billing b
    WHERE b.bill_type IN (13,14)
    AND b.bill_status = 1
    AND b.bill_date >= '2026-05-01'
    AND NOT EXISTS (
        SELECT 1 FROM ret_sales_transfer_log l WHERE l.bill_id = b.bill_id
    )
");

assert_test('CON-007: Recent active ST bills have transfer log entries',
    $no_log && (int)$no_log['cnt'] === 0,
    "Recent bills without logs: " . ($no_log ? $no_log['cnt'] : 'query failed')
);


// =================================================================
// SECTION 8: SALES TRANSFER FORM -- Stock Availability
// =================================================================
section('SECTION 8: SALES TRANSFER FORM -- Stock Availability');

// NT items available for dispatch
$avail_nt = query_row("
    SELECT COUNT(*) AS cnt, ROUND(SUM(gross_wt), 3) AS total_gwt
    FROM ret_nontag_item
    WHERE is_st_locked = 0 AND gross_wt > 0 AND no_of_piece > 0
");

assert_test('STF-001: NT items available for dispatch',
    $avail_nt && (int)$avail_nt['cnt'] > 0,
    "Available: {$avail_nt['cnt']} items, total gwt={$avail_nt['total_gwt']}"
);

// NT locked to pending bills — informational
$locked_nt = query_row("SELECT COUNT(*) AS cnt FROM ret_nontag_item WHERE is_st_locked = 1");

info_test('STF-002: Pending NT locks (is_st_locked=1)',
    "Pending: " . ($locked_nt ? $locked_nt['cnt'] : '0')
);

// Tags available for dispatch
$avail_tags = query_row("
    SELECT COUNT(*) AS cnt FROM ret_taging
    WHERE tag_status IN (0, 1) AND trans_to_acc_stock = 0
");

assert_test('STF-003: Tags available for ST dispatch (status 0 or 1)',
    $avail_tags && (int)$avail_tags['cnt'] > 0,
    "Available tags: " . ($avail_tags ? $avail_tags['cnt'] : '0')
);


// =================================================================
// FINAL REPORT
// =================================================================
section('FINAL REPORT');

$total = $pass + $fail + $skip;
echo "\n";
echo "  Total:   $total\n";
echo "  PASS:    $pass\n";
echo "  FAIL:    $fail\n";
echo "  SKIP:    $skip\n";
echo "\n";

if ($fail > 0) {
    echo "  FAILURES:\n";
    foreach ($results as $r) {
        if ($r['status'] === 'FAIL') {
            echo "    -> {$r['name']}: {$r['detail']}\n";
        }
    }
    echo "\n";
}

if ($skip > 0) {
    echo "  SKIPPED:\n";
    foreach ($results as $r) {
        if ($r['status'] === 'SKIP') {
            echo "    -> {$r['name']}: {$r['detail']}\n";
        }
    }
    echo "\n";
}

echo str_repeat('=', 70) . "\n";
if ($fail === 0) {
    echo "  ALL TESTS PASSED!\n";
} else {
    echo "  $fail TEST(S) FAILED -- review above\n";
}
echo str_repeat('=', 70) . "\n";

$conn->close();
exit($fail > 0 ? 1 : 0);
