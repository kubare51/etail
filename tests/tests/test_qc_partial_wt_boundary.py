"""
QC Piece/Weight Boundary Rule (generalized) — E2E Tests

Extends test_qc_single_pcs_restriction.py's literal-1-Pc coverage. A QA tester reproduced
a live bug: a QC-issued line of 2 Pcs / 100.000 gms was received with all 2 pieces rejected
but only 25.000 gms (partial weight) — accepted, producing an orphan passed_pcs=0/passed_gwt=75
record. The single-piece guard only fired at issue_pcs===1; this generalizes it to ANY piece
count reaching the same all-or-nothing boundary (0 pieces or the full available/issued count),
while genuine partial splits (neither side at 0) remain unrestricted.

Also covers the user's own worked multi-round example: a 4 Pcs / 100.000 gms PO item drawn
down via a first round of 3 Pcs / 70.000 gms, leaving a 1 Pc / 30.000 gms balance that must
then be taken (or rejected) whole — proving the cumulative, cross-round balance tracking
(getQcIssueItemBalance) correctly narrows to the literal single-piece guard on the final
piece, regardless of the PO item's original size.

Data (tests/data/qc_partial_wt_boundary.yml) must be seeded on the target environment; every
test skips cleanly via pytest.skip when its required PO/QC ref or row is not resolvable,
matching the convention in test_qc_single_pcs_restriction.py.
"""
import pytest
from pages.qc_issue_receipt_page import QCIssueReceiptPage

BOUNDARY_MESSAGE = (
    "Pieces and weight must match at the boundary: processing all pieces requires the full "
    "weight, and processing zero pieces requires zero weight. Partial weight is allowed only "
    "for a partial piece count."
)
SINGLE_PCS_MESSAGE = (
    "Partial weight processing is not allowed for a single-piece item. "
    "The complete piece and weight must be processed."
)


def _case(test_cases, case_id):
    tc = next((t for t in test_cases if t.get("id") == case_id), None)
    if not tc:
        pytest.skip(f"No test case {case_id} in qc_partial_wt_boundary.yml")
    return tc


@pytest.mark.p1
@pytest.mark.purchase
class TestQCPartialWeightBoundary:
    """Generalized piece/weight boundary rule — any piece count, both QC Issue and QC Receipt."""

    # ── 1. The exact QA-reported bug: 2 Pcs all rejected, partial weight ────────

    def test_receipt_all_pcs_rejected_partial_weight_blocked(self, auth_page, load_test_data, base_url):
        tc = _case(load_test_data("qc_partial_wt_boundary"), "TC_QC_BOUNDARY_001")

        qc = QCIssueReceiptPage(auth_page)
        item = qc.api_get_receipt_item(tc["two_pcs_qc_ref_no"])
        if not item:
            pytest.skip(f"No pending QC-Receipt line for {tc['two_pcs_qc_ref_no']} — seed test data first")

        payload = {
            "order[po_ref_no]": str(tc["two_pcs_qc_ref_no"]),
            "order_items[id_qc_issue_details][0]": str(item["id_qc_issue_details"]),
            "order_items[failed_pcs][0]": str(item["issue_pcs"]),  # all pieces rejected
            "order_items[failed_gwt][0]": str(tc["full_reject_partial_gwt"]),  # only partial weight
            "order_items[failed_lwt][0]": "0",
            "order_items[failed_nwt][0]": "0",
            "order_items[qc_acc_pcs][0]": "0",
            "order_items[qc_acc_gwt][0]": str(float(item["issue_gwt"]) - float(tc["full_reject_partial_gwt"])),
            "order_items[qc_acc_lwt][0]": "0",
            "order_items[qc_acc_nwt][0]": str(float(item["issue_gwt"]) - float(tc["full_reject_partial_gwt"])),
            "order_items[stone_details][0]": "",
        }
        result = qc.api_update_qc_status(payload)

        assert result.get("status") is False, f"All-pcs-rejected/partial-weight should be blocked, got: {result}"
        assert result.get("message") == tc["expected_message"]

    def test_receipt_all_pcs_rejected_full_weight_accepted(self, auth_page, load_test_data, base_url):
        tc = _case(load_test_data("qc_partial_wt_boundary"), "TC_QC_BOUNDARY_001")

        qc = QCIssueReceiptPage(auth_page)
        item = qc.api_get_receipt_item(tc["two_pcs_qc_ref_no"])
        if not item:
            pytest.skip(f"No pending QC-Receipt line for {tc['two_pcs_qc_ref_no']} — seed test data first")

        payload = {
            "order[po_ref_no]": str(tc["two_pcs_qc_ref_no"]),
            "order_items[id_qc_issue_details][0]": str(item["id_qc_issue_details"]),
            "order_items[failed_pcs][0]": str(item["issue_pcs"]),
            "order_items[failed_gwt][0]": str(item["issue_gwt"]),  # full weight rejected too
            "order_items[failed_lwt][0]": "0",
            "order_items[failed_nwt][0]": "0",
            "order_items[qc_acc_pcs][0]": "0",
            "order_items[qc_acc_gwt][0]": "0",
            "order_items[qc_acc_lwt][0]": "0",
            "order_items[qc_acc_nwt][0]": "0",
            "order_items[stone_details][0]": "",
        }
        result = qc.api_update_qc_status(payload)

        assert result.get("status") is True, f"Full reject (all pcs + all weight) should be accepted, got: {result}"

    # ── 2. QC Issue: taking every remaining piece but only partial weight ──────

    def test_issue_all_pcs_partial_weight_blocked(self, auth_page, load_test_data, base_url):
        tc = _case(load_test_data("qc_partial_wt_boundary"), "TC_QC_BOUNDARY_002")

        qc = QCIssueReceiptPage(auth_page)
        po = qc.api_resolve_po_id(tc["five_pcs_po_ref_no"])
        if not po:
            pytest.skip(f"PO {tc['five_pcs_po_ref_no']} not resolvable — seed test data first")
        item = qc.api_get_issue_item(po["po_id"], po.get("fin_year_code", ""))
        if not item:
            pytest.skip(f"No pending QC-Issue balance for PO {tc['five_pcs_po_ref_no']}")

        payload = {
            "id_employee": "1",
            "total_pcs": str(tc["full_pcs"]),
            "total_gwt": str(tc["partial_gwt"]),
            "total_lwt": "0",
            "total_nwt": str(tc["partial_gwt"]),
            "req_data[0][po_item_id]": str(item["po_item_id"]),
            "req_data[0][qc_issue_pcs]": str(item["no_of_pcs"]),  # every remaining piece
            "req_data[0][qc_issue_gross_wt]": str(tc["partial_gwt"]),  # only partial weight
            "req_data[0][qc_issue_less_wt]": "0",
            "req_data[0][qc_issue_net_wt]": str(tc["partial_gwt"]),
            "req_data[0][stone_details]": "",
        }
        result = qc.api_update_qc_issue(payload)

        assert result.get("status") is False, f"All-pcs/partial-weight issue should be blocked, got: {result}"
        assert result.get("message") == tc["expected_message"]

    def test_issue_all_pcs_full_weight_accepted(self, auth_page, load_test_data, base_url):
        tc = _case(load_test_data("qc_partial_wt_boundary"), "TC_QC_BOUNDARY_002")

        qc = QCIssueReceiptPage(auth_page)
        po = qc.api_resolve_po_id(tc["five_pcs_po_ref_no"])
        if not po:
            pytest.skip(f"PO {tc['five_pcs_po_ref_no']} not resolvable — seed test data first")
        item = qc.api_get_issue_item(po["po_id"], po.get("fin_year_code", ""))
        if not item:
            pytest.skip(f"No pending QC-Issue balance for PO {tc['five_pcs_po_ref_no']}")

        payload = {
            "id_employee": "1",
            "total_pcs": str(item["no_of_pcs"]),
            "total_gwt": str(item["gross_wt"]),
            "total_lwt": "0",
            "total_nwt": str(item["gross_wt"]),
            "req_data[0][po_item_id]": str(item["po_item_id"]),
            "req_data[0][qc_issue_pcs]": str(item["no_of_pcs"]),
            "req_data[0][qc_issue_gross_wt]": str(item["gross_wt"]),
            "req_data[0][qc_issue_less_wt]": "0",
            "req_data[0][qc_issue_net_wt]": str(item["gross_wt"]),
            "req_data[0][stone_details]": "",
        }
        result = qc.api_update_qc_issue(payload)

        assert result.get("status") is True, f"Full pcs/full weight issue should be accepted, got: {result}"

    # ── 3. Multi-round drawdown — the user's own worked example ─────────────────

    def test_multi_round_drawdown_to_last_piece(self, auth_page, load_test_data, base_url):
        """4 Pcs/100g -> round 1 issues 3 Pcs/70g -> the remaining 1 Pc/30g balance must
        be taken whole; a partial (1 Pc/20g) against that last piece is blocked."""
        tc = _case(load_test_data("qc_partial_wt_boundary"), "TC_QC_BOUNDARY_003")

        qc = QCIssueReceiptPage(auth_page)
        po = qc.api_resolve_po_id(tc["four_pcs_po_ref_no"])
        if not po:
            pytest.skip(f"PO {tc['four_pcs_po_ref_no']} not resolvable — seed test data first")
        item = qc.api_get_issue_item(po["po_id"], po.get("fin_year_code", ""))
        if not item:
            pytest.skip(f"No pending QC-Issue balance for PO {tc['four_pcs_po_ref_no']}")

        # Round 1: issue 3 Pcs / 70g, if the full balance hasn't already been drawn down by a
        # previous test run. Skip round 1 if the balance is already at the expected 1 Pc/30g.
        if int(item["no_of_pcs"]) > int(tc["round2_full_pcs"]):
            round1_payload = {
                "id_employee": "1",
                "total_pcs": str(tc["round1_pcs"]),
                "total_gwt": str(tc["round1_gwt"]),
                "total_lwt": "0",
                "total_nwt": str(tc["round1_gwt"]),
                "req_data[0][po_item_id]": str(item["po_item_id"]),
                "req_data[0][qc_issue_pcs]": str(tc["round1_pcs"]),
                "req_data[0][qc_issue_gross_wt]": str(tc["round1_gwt"]),
                "req_data[0][qc_issue_less_wt]": "0",
                "req_data[0][qc_issue_net_wt]": str(tc["round1_gwt"]),
                "req_data[0][stone_details]": "",
            }
            round1_result = qc.api_update_qc_issue(round1_payload)
            assert round1_result.get("status") is True, (
                f"Round 1 (3 Pcs/70g, not yet at the boundary) should be accepted, got: {round1_result}"
            )

        # Balance should now be down to 1 Pc / 30g — re-fetch and confirm.
        item_after_round1 = qc.api_get_issue_item(po["po_id"], po.get("fin_year_code", ""))
        assert item_after_round1, "PO item should still have a pending balance after round 1"
        assert int(item_after_round1["no_of_pcs"]) == int(tc["round2_full_pcs"]), (
            f"Expected balance to be drawn down to {tc['round2_full_pcs']} Pc, "
            f"got {item_after_round1['no_of_pcs']}"
        )

        # Round 2: the last 1 Pc must not be split — partial weight rejected.
        round2_partial_payload = {
            "id_employee": "1",
            "total_pcs": str(tc["round2_full_pcs"]),
            "total_gwt": str(tc["round2_partial_gwt"]),
            "total_lwt": "0",
            "total_nwt": str(tc["round2_partial_gwt"]),
            "req_data[0][po_item_id]": str(item_after_round1["po_item_id"]),
            "req_data[0][qc_issue_pcs]": str(tc["round2_full_pcs"]),
            "req_data[0][qc_issue_gross_wt]": str(tc["round2_partial_gwt"]),
            "req_data[0][qc_issue_less_wt]": "0",
            "req_data[0][qc_issue_net_wt]": str(tc["round2_partial_gwt"]),
            "req_data[0][stone_details]": "",
        }
        round2_result = qc.api_update_qc_issue(round2_partial_payload)

        assert round2_result.get("status") is False, (
            f"Last-piece partial weight (1 Pc/{tc['round2_partial_gwt']}g of "
            f"{tc['round2_full_gwt']}g) should be blocked, got: {round2_result}"
        )
        assert round2_result.get("message") == tc["expected_message"]

        # The full remaining balance (1 Pc / 30g) must be accepted.
        round2_full_payload = dict(round2_partial_payload)
        round2_full_payload["req_data[0][qc_issue_gross_wt]"] = str(tc["round2_full_gwt"])
        round2_full_payload["req_data[0][qc_issue_net_wt]"] = str(tc["round2_full_gwt"])
        round2_full_payload["total_gwt"] = str(tc["round2_full_gwt"])
        round2_full_payload["total_nwt"] = str(tc["round2_full_gwt"])
        round2_full_result = qc.api_update_qc_issue(round2_full_payload)

        assert round2_full_result.get("status") is True, (
            f"Last piece taken whole (1 Pc/{tc['round2_full_gwt']}g) should be accepted, "
            f"got: {round2_full_result}"
        )

    # ── 4. Regression: genuine partial splits remain unrestricted at any piece count ──

    def test_multi_pcs_genuine_partial_split_still_allowed(self, auth_page, load_test_data, base_url):
        tc = _case(load_test_data("qc_partial_wt_boundary"), "TC_QC_BOUNDARY_004")

        qc = QCIssueReceiptPage(auth_page)

        po = qc.api_resolve_po_id(tc["five_pcs_po_ref_no"])
        if po:
            item = qc.api_get_issue_item(po["po_id"], po.get("fin_year_code", ""))
            if item and int(item["no_of_pcs"]) > int(tc["partial_issue_pcs"]):
                payload = {
                    "id_employee": "1",
                    "total_pcs": str(tc["partial_issue_pcs"]),
                    "total_gwt": str(tc["partial_issue_gwt"]),
                    "total_lwt": "0",
                    "total_nwt": str(tc["partial_issue_gwt"]),
                    "req_data[0][po_item_id]": str(item["po_item_id"]),
                    "req_data[0][qc_issue_pcs]": str(tc["partial_issue_pcs"]),
                    "req_data[0][qc_issue_gross_wt]": str(tc["partial_issue_gwt"]),
                    "req_data[0][qc_issue_less_wt]": "0",
                    "req_data[0][qc_issue_net_wt]": str(tc["partial_issue_gwt"]),
                    "req_data[0][stone_details]": "",
                }
                result = qc.api_update_qc_issue(payload)
                assert result.get("status") is True, (
                    f"Genuine partial issue split (2 of 5 pcs) must remain allowed, got: {result}"
                )

        receipt_item = qc.api_get_receipt_item(tc["five_pcs_qc_ref_no"])
        if not receipt_item:
            pytest.skip(f"No pending QC-Receipt line for {tc['five_pcs_qc_ref_no']} — seed test data first")

        receipt_payload = {
            "order[po_ref_no]": str(tc["five_pcs_qc_ref_no"]),
            "order_items[id_qc_issue_details][0]": str(receipt_item["id_qc_issue_details"]),
            "order_items[failed_pcs][0]": str(tc["partial_reject_pcs"]),
            "order_items[failed_gwt][0]": str(tc["partial_reject_gwt"]),
            "order_items[failed_lwt][0]": "0",
            "order_items[failed_nwt][0]": "0",
            "order_items[qc_acc_pcs][0]": str(int(receipt_item["issue_pcs"]) - int(tc["partial_reject_pcs"])),
            "order_items[qc_acc_gwt][0]": str(float(receipt_item["issue_gwt"]) - float(tc["partial_reject_gwt"])),
            "order_items[qc_acc_lwt][0]": "0",
            "order_items[qc_acc_nwt][0]": str(float(receipt_item["issue_gwt"]) - float(tc["partial_reject_gwt"])),
            "order_items[stone_details][0]": "",
        }
        receipt_result = qc.api_update_qc_status(receipt_payload)

        assert receipt_result.get("status") is True, (
            f"Genuine partial receipt split (1 of {receipt_item['issue_pcs']} pcs) must remain allowed, "
            f"got: {receipt_result}"
        )

    # ── 5. Restriction disabled restores legacy behaviour for the new boundary case too ──

    def test_restriction_disabled_allows_all_pcs_partial_weight(self, auth_page, load_test_data, base_url, db_connection):
        tc = _case(load_test_data("qc_partial_wt_boundary"), "TC_QC_BOUNDARY_001")
        if db_connection is None:
            pytest.skip("No DB connection configured — cannot toggle ret_settings")

        cur = db_connection.cursor()
        try:
            cur.execute(
                "UPDATE ret_settings SET value = '0' WHERE name = 'restrict_partial_wt_single_pcs'"
            )
            db_connection.commit()

            qc = QCIssueReceiptPage(auth_page)
            item = qc.api_get_receipt_item(tc["two_pcs_qc_ref_no"])
            if not item:
                pytest.skip(f"No pending QC-Receipt line for {tc['two_pcs_qc_ref_no']} — seed test data first")

            payload = {
                "order[po_ref_no]": str(tc["two_pcs_qc_ref_no"]),
                "order_items[id_qc_issue_details][0]": str(item["id_qc_issue_details"]),
                "order_items[failed_pcs][0]": str(item["issue_pcs"]),
                "order_items[failed_gwt][0]": str(tc["full_reject_partial_gwt"]),
                "order_items[failed_lwt][0]": "0",
                "order_items[failed_nwt][0]": "0",
                "order_items[qc_acc_pcs][0]": "0",
                "order_items[qc_acc_gwt][0]": str(float(item["issue_gwt"]) - float(tc["full_reject_partial_gwt"])),
                "order_items[qc_acc_lwt][0]": "0",
                "order_items[qc_acc_nwt][0]": str(float(item["issue_gwt"]) - float(tc["full_reject_partial_gwt"])),
                "order_items[stone_details][0]": "",
            }
            result = qc.api_update_qc_status(payload)

            assert result.get("status") is True, (
                f"With the setting disabled, the boundary case must be accepted (legacy behaviour), got: {result}"
            )
        finally:
            cur.execute("UPDATE ret_settings SET value = '1' WHERE name = 'restrict_partial_wt_single_pcs'")
            db_connection.commit()
            cur.close()

    # ── Restriction scoped to Ornament (cat_type=1) only — regression for non-Ornament items ──

    def test_bullion_category_all_pcs_partial_weight_not_restricted(self, auth_page, load_test_data, base_url):
        """The same 'all pcs rejected, partial weight' shape as the QA-reported bug, but on a
        line booked against a Bullion category (cat_type=2) — must remain allowed, since the
        boundary rule only applies to Ornament (cat_type=1) items."""
        tc = _case(load_test_data("qc_partial_wt_boundary"), "TC_QC_BOUNDARY_005")

        qc = QCIssueReceiptPage(auth_page)
        item = qc.api_get_receipt_item(tc["bullion_two_pcs_qc_ref_no"])
        if not item:
            pytest.skip(
                f"No pending QC-Receipt line for {tc['bullion_two_pcs_qc_ref_no']} — seed test data first"
            )

        payload = {
            "order[po_ref_no]": str(tc["bullion_two_pcs_qc_ref_no"]),
            "order_items[id_qc_issue_details][0]": str(item["id_qc_issue_details"]),
            "order_items[failed_pcs][0]": str(item["issue_pcs"]),
            "order_items[failed_gwt][0]": str(tc["full_reject_partial_gwt"]),
            "order_items[failed_lwt][0]": "0",
            "order_items[failed_nwt][0]": "0",
            "order_items[qc_acc_pcs][0]": "0",
            "order_items[qc_acc_gwt][0]": str(float(item["issue_gwt"]) - float(tc["full_reject_partial_gwt"])),
            "order_items[qc_acc_lwt][0]": "0",
            "order_items[qc_acc_nwt][0]": str(float(item["issue_gwt"]) - float(tc["full_reject_partial_gwt"])),
            "order_items[stone_details][0]": "",
        }
        result = qc.api_update_qc_status(payload)

        assert result.get("status") is True, (
            f"All-pcs-rejected/partial-weight on a non-Ornament (Bullion) item must remain allowed, got: {result}"
        )
