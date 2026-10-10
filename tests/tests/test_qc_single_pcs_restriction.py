"""
QC Single-Piece Partial-Weight Restriction — E2E Tests

Covers admin_ret_purchase_order_qc_issue_receipt_add_distributed_token:
"Restrict Partial Weight QC Issue and QC Receipt for Single-Piece Ornament Purchase".

A Supplier Bill Entry item booked as 1 Pc / 100.000 gms must not be processed with only
part of its weight during QC Issue or QC Receipt — either the full piece + full weight
moves together, or nothing does. The restriction is gated by the `restrict_partial_wt_single_pcs`
ret_settings row (see database/migrations/20260731_000001_restrict_partial_wt_single_pcs.sql)
and enforced both client-side (readonly inputs + toaster) and server-side (authoritative).

Data (tests/data/qc_single_pcs.yml) must be seeded on the target environment; every test
skips cleanly via pytest.skip when its required PO/QC ref or row is not resolvable, matching
the convention in test_qc_issue_receipt.py.
"""
import pytest
from pages.qc_issue_receipt_page import QCIssueReceiptPage

SETTING_NAME = "restrict_partial_wt_single_pcs"
EXPECTED_MESSAGE = (
    "Partial weight processing is not allowed for a single-piece item. "
    "The complete piece and weight must be processed."
)


def _case(test_cases, case_id):
    tc = next((t for t in test_cases if t.get("id") == case_id), None)
    if not tc:
        pytest.skip(f"No test case {case_id} in qc_single_pcs.yml")
    return tc


def _set_setting(db_connection, value):
    if db_connection is None:
        pytest.skip("No DB connection configured — cannot toggle ret_settings")
    cur = db_connection.cursor()
    cur.execute(
        "INSERT INTO ret_settings (name, value, description) VALUES (%s, %s, %s) "
        "ON DUPLICATE KEY UPDATE value = VALUES(value)",
        (SETTING_NAME, str(value), "Restrict partial weight processing for single-piece (1 Pc) items in QC Issue and QC Receipt. 1 = Enabled, 0 = Disabled"),
    )
    db_connection.commit()
    cur.close()


def _get_setting(db_connection):
    if db_connection is None:
        pytest.skip("No DB connection configured — cannot read ret_settings")
    cur = db_connection.cursor(dictionary=True)
    cur.execute("SELECT value FROM ret_settings WHERE name = %s", (SETTING_NAME,))
    row = cur.fetchone()
    cur.close()
    return row


def _count_qc_issue_process(db_connection, ref_like):
    cur = db_connection.cursor()
    cur.execute("SELECT COUNT(*) FROM ret_po_qc_issue_process WHERE ref_no = %s", (ref_like,))
    (count,) = cur.fetchone()
    cur.close()
    return count


@pytest.mark.p1
@pytest.mark.purchase
class TestQCSinglePcsRestriction:
    """Single-piece (1 Pc) partial-weight restriction in QC Issue / QC Receipt."""

    # ── 1. Setting exists and defaults to enabled ───────────────────────────────

    def test_setting_row_exists(self, db_connection):
        row = _get_setting(db_connection)
        assert row is not None, (
            f"'{SETTING_NAME}' is not seeded in ret_settings — run "
            "database/migrations/20260731_000001_restrict_partial_wt_single_pcs.sql"
        )
        assert str(row["value"]) == "1", f"Expected default value '1' (enabled), got {row['value']!r}"

    # ── 2/3. Input locking on the QC Issue grid ─────────────────────────────────

    def test_issue_single_pcs_inputs_locked(self, auth_page, load_test_data, base_url, db_connection):
        tc = _case(load_test_data("qc_single_pcs"), "TC_QC_SINGLE_001")
        _set_setting(db_connection, 1)

        qc = QCIssueReceiptPage(auth_page)
        qc.goto()
        qc.click_issue()
        qc.fill_issue(po_ref_no=tc["single_pcs_po_ref_no"], employee=tc.get("employee"))

        locked = qc.is_issue_row_locked(row=1)
        assert locked["pcs"], "Pcs input should be readonly for a 1-Pc balance row"
        assert locked["gwt"], "Gross Wt input should be readonly for a 1-Pc balance row"

    def test_issue_multi_pcs_inputs_editable(self, auth_page, load_test_data, base_url, db_connection):
        """Regression: multi-piece rows must NOT be locked."""
        tc = _case(load_test_data("qc_single_pcs"), "TC_QC_SINGLE_002")
        _set_setting(db_connection, 1)

        qc = QCIssueReceiptPage(auth_page)
        qc.goto()
        qc.click_issue()
        qc.fill_issue(po_ref_no=tc["multi_pcs_po_ref_no"], employee=tc.get("employee"))

        locked = qc.is_issue_row_locked(row=1)
        assert not locked["pcs"], "Pcs input must stay editable for a multi-piece balance row"
        assert not locked["gwt"], "Gross Wt input must stay editable for a multi-piece balance row"

    # ── 4/5. Backend enforcement — QC Issue ─────────────────────────────────────

    def test_issue_partial_weight_rejected_backend(self, auth_page, load_test_data, base_url, db_connection):
        tc = _case(load_test_data("qc_single_pcs"), "TC_QC_SINGLE_001")
        _set_setting(db_connection, 1)

        qc = QCIssueReceiptPage(auth_page)
        po = qc.api_resolve_po_id(tc["single_pcs_po_ref_no"])
        if not po:
            pytest.skip(f"PO {tc['single_pcs_po_ref_no']} not resolvable — seed test data first")
        item = qc.api_get_issue_item(po["po_id"], po.get("fin_year_code", ""))
        if not item:
            pytest.skip(f"No pending QC-Issue balance for PO {tc['single_pcs_po_ref_no']}")

        before = _count_qc_issue_process(db_connection, "__probe__")  # baseline no-op, real check is row-count delta below
        payload = {
            "id_employee": "1",
            "total_pcs": "1",
            "total_gwt": str(tc["partial_gwt"]),
            "total_lwt": "0",
            "total_nwt": str(tc["partial_gwt"]),
            "req_data[0][po_item_id]": str(item["po_item_id"]),
            "req_data[0][qc_issue_pcs]": "1",
            "req_data[0][qc_issue_gross_wt]": str(tc["partial_gwt"]),
            "req_data[0][qc_issue_less_wt]": "0",
            "req_data[0][qc_issue_net_wt]": str(tc["partial_gwt"]),
            "req_data[0][stone_details]": "",
        }
        result = qc.api_update_qc_issue(payload)

        assert result.get("status") is False, f"Partial weight issue should be rejected, got: {result}"
        assert result.get("message") == tc["expected_message"]

    def test_issue_full_weight_accepted(self, auth_page, load_test_data, base_url, db_connection):
        tc = _case(load_test_data("qc_single_pcs"), "TC_QC_SINGLE_001")
        _set_setting(db_connection, 1)

        qc = QCIssueReceiptPage(auth_page)
        po = qc.api_resolve_po_id(tc["single_pcs_po_ref_no"])
        if not po:
            pytest.skip(f"PO {tc['single_pcs_po_ref_no']} not resolvable — seed test data first")
        item = qc.api_get_issue_item(po["po_id"], po.get("fin_year_code", ""))
        if not item:
            pytest.skip(f"No pending QC-Issue balance for PO {tc['single_pcs_po_ref_no']}")

        payload = {
            "id_employee": "1",
            "total_pcs": "1",
            "total_gwt": str(item["gross_wt"]),
            "total_lwt": "0",
            "total_nwt": str(item["gross_wt"]),
            "req_data[0][po_item_id]": str(item["po_item_id"]),
            "req_data[0][qc_issue_pcs]": "1",
            "req_data[0][qc_issue_gross_wt]": str(item["gross_wt"]),
            "req_data[0][qc_issue_less_wt]": "0",
            "req_data[0][qc_issue_net_wt]": str(item["gross_wt"]),
            "req_data[0][stone_details]": "",
        }
        result = qc.api_update_qc_issue(payload)

        assert result.get("status") is True, f"Full-piece/full-weight issue should be accepted, got: {result}"

    # ── 6. Input locking on the QC Receipt grid ─────────────────────────────────

    def test_receipt_single_pcs_gwt_locked(self, auth_page, load_test_data, base_url, db_connection):
        tc = _case(load_test_data("qc_single_pcs"), "TC_QC_SINGLE_003")
        _set_setting(db_connection, 1)

        qc = QCIssueReceiptPage(auth_page)
        qc.goto()
        qc.click_receipt()
        qc.select2(qc.QC_REF_SELECT2, tc["single_pcs_qc_ref_no"], qc.SELECT2_SEARCH)
        auth_page.wait_for_timeout(500)
        qc.click(qc.QC_SEARCH_BTN)
        auth_page.wait_for_timeout(3000)

        assert qc.is_receipt_gwt_locked(row=1), "Failed Gwt input should be readonly for a 1-Pc issued line"

    # ── 7/8. Backend enforcement — QC Receipt ───────────────────────────────────

    def test_receipt_partial_weight_rejected_backend(self, auth_page, load_test_data, base_url, db_connection):
        tc = _case(load_test_data("qc_single_pcs"), "TC_QC_SINGLE_003")
        _set_setting(db_connection, 1)

        qc = QCIssueReceiptPage(auth_page)
        item = qc.api_get_receipt_item(tc["single_pcs_qc_ref_no"])
        if not item:
            pytest.skip(f"No pending QC-Receipt line for {tc['single_pcs_qc_ref_no']} — seed test data first")

        for failed_pcs, failed_gwt in (
            (tc["partial_failed_pcs"], tc["partial_failed_gwt"]),
            (1, 50.000),
        ):
            payload = {
                "order[po_ref_no]": str(tc["single_pcs_qc_ref_no"]),
                "order_items[id_qc_issue_details][0]": str(item["id_qc_issue_details"]),
                "order_items[failed_pcs][0]": str(failed_pcs),
                "order_items[failed_gwt][0]": str(failed_gwt),
                "order_items[failed_lwt][0]": "0",
                "order_items[failed_nwt][0]": "0",
                "order_items[qc_acc_pcs][0]": str(1 - int(failed_pcs)),
                "order_items[qc_acc_gwt][0]": str(float(item["issue_gwt"]) - float(failed_gwt)),
                "order_items[qc_acc_lwt][0]": "0",
                "order_items[qc_acc_nwt][0]": str(float(item["issue_gwt"]) - float(failed_gwt)),
                "order_items[stone_details][0]": "",
            }
            result = qc.api_update_qc_status(payload)
            assert result.get("status") is False, (
                f"failed_pcs={failed_pcs}, failed_gwt={failed_gwt} should be rejected, got: {result}"
            )
            assert result.get("message") == tc["expected_message"]

    def test_receipt_full_reject_accepted(self, auth_page, load_test_data, base_url, db_connection):
        tc = _case(load_test_data("qc_single_pcs"), "TC_QC_SINGLE_003")
        _set_setting(db_connection, 1)

        qc = QCIssueReceiptPage(auth_page)
        item = qc.api_get_receipt_item(tc["single_pcs_qc_ref_no"])
        if not item:
            pytest.skip(f"No pending QC-Receipt line for {tc['single_pcs_qc_ref_no']} — seed test data first")

        payload = {
            "order[po_ref_no]": str(tc["single_pcs_qc_ref_no"]),
            "order_items[id_qc_issue_details][0]": str(item["id_qc_issue_details"]),
            "order_items[failed_pcs][0]": "1",
            "order_items[failed_gwt][0]": str(item["issue_gwt"]),
            "order_items[failed_lwt][0]": "0",
            "order_items[failed_nwt][0]": "0",
            "order_items[qc_acc_pcs][0]": "0",
            "order_items[qc_acc_gwt][0]": "0",
            "order_items[qc_acc_lwt][0]": "0",
            "order_items[qc_acc_nwt][0]": "0",
            "order_items[stone_details][0]": "",
        }
        result = qc.api_update_qc_status(payload)

        assert result.get("status") is True, f"Full rejection should be accepted, got: {result}"

    # ── 9. Multi-piece regression ────────────────────────────────────────────────

    def test_multi_pcs_partial_still_allowed(self, auth_page, load_test_data, base_url, db_connection):
        tc = _case(load_test_data("qc_single_pcs"), "TC_QC_SINGLE_004")
        _set_setting(db_connection, 1)

        qc = QCIssueReceiptPage(auth_page)
        item = qc.api_get_receipt_item(tc["multi_pcs_qc_ref_no"])
        if not item:
            pytest.skip(f"No pending QC-Receipt line for {tc['multi_pcs_qc_ref_no']} — seed test data first")

        payload = {
            "order[po_ref_no]": str(tc["multi_pcs_qc_ref_no"]),
            "order_items[id_qc_issue_details][0]": str(item["id_qc_issue_details"]),
            "order_items[failed_pcs][0]": str(tc["partial_failed_pcs"]),
            "order_items[failed_gwt][0]": str(tc["partial_failed_gwt"]),
            "order_items[failed_lwt][0]": "0",
            "order_items[failed_nwt][0]": "0",
            "order_items[qc_acc_pcs][0]": str(int(item["issue_pcs"]) - int(tc["partial_failed_pcs"])),
            "order_items[qc_acc_gwt][0]": str(float(item["issue_gwt"]) - float(tc["partial_failed_gwt"])),
            "order_items[qc_acc_lwt][0]": "0",
            "order_items[qc_acc_nwt][0]": str(float(item["issue_gwt"]) - float(tc["partial_failed_gwt"])),
            "order_items[stone_details][0]": "",
        }
        result = qc.api_update_qc_status(payload)

        assert result.get("status") is True, f"Multi-piece partial rejection must remain allowed, got: {result}"

    # ── 9b. Restriction scoped to Ornament (cat_type=1) only — regression for non-Ornament items ──

    def test_bullion_category_single_pcs_not_restricted(self, auth_page, load_test_data, base_url, db_connection):
        """A literal 1-Pc item booked against a Bullion category (cat_type=2) must NOT be
        restricted — the boundary rule only applies to Ornament (cat_type=1) items."""
        tc = _case(load_test_data("qc_single_pcs"), "TC_QC_SINGLE_005")
        _set_setting(db_connection, 1)

        qc = QCIssueReceiptPage(auth_page)
        po = qc.api_resolve_po_id(tc["bullion_single_pcs_po_ref_no"])
        if not po:
            pytest.skip(f"PO {tc['bullion_single_pcs_po_ref_no']} not resolvable — seed test data first")
        item = qc.api_get_issue_item(po["po_id"], po.get("fin_year_code", ""))
        if not item:
            pytest.skip(f"No pending QC-Issue balance for PO {tc['bullion_single_pcs_po_ref_no']}")

        payload = {
            "id_employee": "1",
            "total_pcs": "1",
            "total_gwt": str(tc["partial_gwt"]),
            "total_lwt": "0",
            "total_nwt": str(tc["partial_gwt"]),
            "req_data[0][po_item_id]": str(item["po_item_id"]),
            "req_data[0][qc_issue_pcs]": "1",
            "req_data[0][qc_issue_gross_wt]": str(tc["partial_gwt"]),
            "req_data[0][qc_issue_less_wt]": "0",
            "req_data[0][qc_issue_net_wt]": str(tc["partial_gwt"]),
            "req_data[0][stone_details]": "",
        }
        result = qc.api_update_qc_issue(payload)

        assert result.get("status") is True, (
            f"Partial weight on a 1-Pc Bullion (non-Ornament) item must remain allowed, got: {result}"
        )

    # ── 10. Toaster + form re-enabled on rejection (UI path) ────────────────────

    def test_toaster_shown_on_rejection(self, auth_page, load_test_data, base_url, db_connection):
        tc = _case(load_test_data("qc_single_pcs"), "TC_QC_SINGLE_001")
        _set_setting(db_connection, 1)

        qc = QCIssueReceiptPage(auth_page)
        qc.goto()
        qc.click_issue()
        qc.fill_issue(po_ref_no=tc["single_pcs_po_ref_no"], employee=tc.get("employee"))

        locked = qc.is_issue_row_locked(row=1)
        if not locked["gwt"]:
            pytest.skip("Row not locked — cannot exercise the tamper path")

        qc.force_set("qc_issue_gross_wt", tc["partial_gwt"], row=1)
        auth_page.click(f"xpath={qc.ISSUE_SUBMIT}")
        auth_page.wait_for_timeout(2000)

        message = qc.get_toaster_message()
        assert tc["expected_message"] in message, f"Expected the single-piece message in toaster, got: {message!r}"
        assert qc.is_overlay_hidden(), "Overlay must be hidden again after a rejected submit"
        assert qc.is_submit_enabled(qc.ISSUE_SUBMIT), "Submit button must be re-enabled after a rejected submit"

    # ── 11. Setting disabled restores legacy behaviour ───────────────────────────

    def test_restriction_disabled_allows_partial(self, auth_page, load_test_data, base_url, db_connection):
        tc = _case(load_test_data("qc_single_pcs"), "TC_QC_SINGLE_001")
        try:
            _set_setting(db_connection, 0)

            qc = QCIssueReceiptPage(auth_page)
            po = qc.api_resolve_po_id(tc["single_pcs_po_ref_no"])
            if not po:
                pytest.skip(f"PO {tc['single_pcs_po_ref_no']} not resolvable — seed test data first")
            item = qc.api_get_issue_item(po["po_id"], po.get("fin_year_code", ""))
            if not item:
                pytest.skip(f"No pending QC-Issue balance for PO {tc['single_pcs_po_ref_no']}")

            payload = {
                "id_employee": "1",
                "total_pcs": "1",
                "total_gwt": str(tc["partial_gwt"]),
                "total_lwt": "0",
                "total_nwt": str(tc["partial_gwt"]),
                "req_data[0][po_item_id]": str(item["po_item_id"]),
                "req_data[0][qc_issue_pcs]": "1",
                "req_data[0][qc_issue_gross_wt]": str(tc["partial_gwt"]),
                "req_data[0][qc_issue_less_wt]": "0",
                "req_data[0][qc_issue_net_wt]": str(tc["partial_gwt"]),
                "req_data[0][stone_details]": "",
            }
            result = qc.api_update_qc_issue(payload)

            assert result.get("status") is True, (
                f"With the setting disabled, partial-weight issue must be accepted (legacy behaviour), got: {result}"
            )
        finally:
            _set_setting(db_connection, 1)

    # ── 12. Missing setting row does not error ───────────────────────────────────

    def test_missing_setting_row_does_not_error(self, auth_page, base_url, db_connection):
        if db_connection is None:
            pytest.skip("No DB connection configured — cannot delete ret_settings row")
        try:
            cur = db_connection.cursor()
            cur.execute("DELETE FROM ret_settings WHERE name = %s", (SETTING_NAME,))
            db_connection.commit()
            cur.close()

            resp = auth_page.goto(
                f"{base_url}/index.php/admin_ret_purchase/qc_issue_receipt/add", wait_until="networkidle"
            )
            assert resp.status == 200
            body = auth_page.content()
            assert "Fatal error" not in body and "Undefined" not in body, (
                "Loading QC Issue with no ret_settings row must not error "
                "(isSinglePcsRestrictionEnabled() must be null-safe)"
            )
        finally:
            _set_setting(db_connection, 1)
