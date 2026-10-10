"""Brain Builder v2.3 — Business Intelligence Analyzer.

Infers business rules, flows, risks, validation inventory, and cross-module
maps from parsed code metadata. Takes a BrainBuildResult (raw extraction)
and produces structured BI objects.

Each inference is tagged with confidence level and [CONFIRM] status
for human review.
"""

from pathlib import Path
from typing import Dict, List, Set, Tuple
from collections import defaultdict

from lca.brain.models import (
    BrainBuildResult, ExtractedMethod,
    BusinessRule, BusinessFlow, BusinessRisk,
)


class BusinessIntelligenceAnalyzer:
    """Infer business rules, flows, and risks from code analysis data."""
    
    def __init__(self, result: BrainBuildResult):
        self.result = result
        self._rule_counter = 0
        self._flow_counter = 0
        self._risk_counter = 0
    
    def _next_rule_id(self) -> str:
        self._rule_counter += 1
        return f"RULE-{self._rule_counter:03d}"
    
    def _next_flow_id(self) -> str:
        self._flow_counter += 1
        return f"FLOW-{self._flow_counter:03d}"
    
    def _next_risk_id(self) -> str:
        self._risk_counter += 1
        return f"RISK-{self._risk_counter:03d}"
    
    def analyze(self) -> BrainBuildResult:
        """Run all BI inference and attach results to the BrainBuildResult."""
        self.result.business_rules = self._infer_rules()
        self.result.business_flows = self._infer_flows()
        self.result.business_risks = self._infer_risks()
        self.result.validation_inventory = self._build_validation_inventory()
        self.result.cross_module_map = self._build_cross_module_map()
        return self.result
    
    # ─────────────────────────────────────────────
    # Rule Inference
    # ─────────────────────────────────────────────
    
    def _infer_rules(self) -> List[BusinessRule]:
        rules = []
        rules.extend(self._rules_from_otp())
        rules.extend(self._rules_from_transactions())
        rules.extend(self._rules_from_status_gates())
        rules.extend(self._rules_from_hard_deletes())
        rules.extend(self._rules_from_bill_type_branching())
        rules.extend(self._rules_from_credit_handling())
        rules.extend(self._rules_from_file_uploads())
        return rules
    
    def _rules_from_otp(self) -> List[BusinessRule]:
        """Methods with OTP = authorization required."""
        rules = []
        otp_methods = [m for m in self.result.methods if m.has_otp]
        
        # Group OTP methods by purpose
        otp_groups = {
            'billing': [], 'cancel': [], 'credit': [], 'transfer': [],
            'order': [], 'approval': [], 'other': []
        }
        for m in otp_methods:
            name = m.name.lower()
            if 'cancel' in name:
                otp_groups['cancel'].append(m)
            elif 'credit' in name:
                otp_groups['credit'].append(m)
            elif 'transfer' in name or 'adtrns' in name:
                otp_groups['transfer'].append(m)
            elif 'order' in name:
                otp_groups['order'].append(m)
            elif 'approval' in name:
                otp_groups['approval'].append(m)
            elif 'billing' in name or 'bill' in name or 'service' in name:
                otp_groups['billing'].append(m)
            elif 'send' in name or 'verify' in name or 'otp' in name:
                continue  # Helper methods, not business rules
            else:
                otp_groups['other'].append(m)
        
        group_titles = {
            'billing': 'Billing Operations Require OTP Authorization',
            'cancel': 'Bill Cancellation Requires OTP Authorization',
            'credit': 'Credit Bill Operations Require OTP Authorization',
            'transfer': 'Advance Transfer Requires OTP Authorization',
            'order': 'Order Operations Require OTP Authorization',
            'approval': 'Admin Approval Requires OTP Verification',
        }
        
        for group, methods in otp_groups.items():
            if not methods or group == 'other':
                continue
            
            all_tables = set()
            for m in methods:
                all_tables.update(m.tables_insert + m.tables_update + m.tables_delete)
            
            rules.append(BusinessRule(
                id=self._next_rule_id(),
                title=group_titles.get(group, f'OTP Required for {group.title()}'),
                category="AUTH",
                evidence_methods=[m.name for m in methods],
                evidence_code="has_otp=True (OTP send/verify pattern detected)",
                tables_affected=sorted(all_tables)[:10],
                confidence="HIGH",
                details=f"{len(methods)} method(s) in this group use OTP: "
                        f"{', '.join(m.name + '()' for m in methods)}. "
                        f"OTP is typically sent via SMS and verified before the DB write is committed."
            ))
        
        # Remaining OTP methods as individual rules
        for m in otp_groups['other']:
            rules.append(BusinessRule(
                id=self._next_rule_id(),
                title=f"`{m.name}()` Requires OTP/Authorization",
                category="AUTH",
                evidence_methods=[m.name],
                evidence_code="has_otp=True",
                tables_affected=sorted(set(m.tables_insert + m.tables_update))[:5],
                confidence="MEDIUM",
                details=f"Method contains OTP-related code patterns. "
                        f"May be a send/verify helper rather than a standalone business rule."
            ))
        
        return rules
    
    def _rules_from_transactions(self) -> List[BusinessRule]:
        """Multi-table writes inside transactions = atomic operation requirement."""
        rules = []
        for m in self.result.methods:
            if not m.has_transaction:
                continue
            all_writes = sorted(set(m.tables_insert + m.tables_update + m.tables_delete))
            if len(all_writes) < 2:
                continue
            
            has_delete = bool(m.tables_delete)
            rules.append(BusinessRule(
                id=self._next_rule_id(),
                title=f"`{m.name}()` is an Atomic Multi-Table Operation ({len(all_writes)} tables)",
                category="DATA_INTEGRITY",
                evidence_methods=[m.name],
                evidence_code="has_transaction=True + multi-table writes",
                tables_affected=all_writes[:10],
                confidence="HIGH",
                details=f"Method writes to {len(all_writes)} tables inside a DB transaction. "
                        f"{'Includes DELETE operations — rollback on failure is critical. ' if has_delete else ''}"
                        f"Tables: {', '.join(all_writes[:8])}"
                        f"{'...' if len(all_writes) > 8 else ''}."
            ))
        return rules
    
    def _rules_from_status_gates(self) -> List[BusinessRule]:
        """Status checks before updates = state machine rules."""
        rules = []
        for m in self.result.methods:
            if not m.tables_update or not m.status_checks:
                continue
            
            # Filter to real status columns (not $type routing)
            real_checks = [c for c in m.status_checks 
                          if not c.startswith('$type=') and not c.startswith('card_type=')]
            if not real_checks:
                continue
            
            # Map status checks to human-readable labels using value_maps
            labeled_checks = []
            for check in real_checks[:6]:
                if '=' in check:
                    col, val = check.split('=', 1)
                    label = self.result.value_maps.get(col, {}).get(val, '')
                    if label:
                        labeled_checks.append(f"{col}={val} ({label})")
                    else:
                        labeled_checks.append(check)
                else:
                    labeled_checks.append(check)
            
            rules.append(BusinessRule(
                id=self._next_rule_id(),
                title=f"`{m.name}()` Enforces Status Prerequisites",
                category="STATUS",
                evidence_methods=[m.name],
                evidence_code=f"Status checks: {', '.join(labeled_checks)}",
                tables_affected=sorted(set(m.tables_update))[:5],
                confidence="MEDIUM",
                details=f"Method checks status values before performing updates: "
                        f"{', '.join(labeled_checks)}. "
                        f"This implies a state machine — certain operations are only valid "
                        f"when the record is in a specific state."
            ))
        return rules
    
    def _rules_from_hard_deletes(self) -> List[BusinessRule]:
        """DELETE without soft-delete pattern = data destruction rule."""
        rules = []
        for m in self.result.methods:
            if not m.tables_delete:
                continue
            
            rules.append(BusinessRule(
                id=self._next_rule_id(),
                title=f"`{m.name}()` Performs Hard Delete on {', '.join(m.tables_delete[:3])}",
                category="DATA_INTEGRITY",
                evidence_methods=[m.name],
                evidence_code=f"tables_delete: {m.tables_delete}",
                tables_affected=m.tables_delete,
                confidence="HIGH",
                details=f"Method permanently deletes data from {len(m.tables_delete)} table(s): "
                        f"{', '.join(m.tables_delete)}. "
                        f"{'Protected by transaction. ' if m.has_transaction else '⚠️ NOT in a transaction — partial failure causes data loss. '}"
                        f"Consider if soft-delete (is_deleted flag) would be safer."
            ))
        return rules
    
    def _rules_from_bill_type_branching(self) -> List[BusinessRule]:
        """Methods that branch on bill_type = type-specific behavior."""
        bill_type_map = self.result.value_maps.get('bill_type', {})
        if not bill_type_map:
            return []
        
        # Find methods that check bill_type
        branching_methods = []
        for m in self.result.methods:
            bt_checks = [c for c in m.status_checks if c.startswith('bill_type=')]
            if len(bt_checks) >= 2:  # At least 2 branches = conditional logic
                branching_methods.append((m, bt_checks))
        
        if not branching_methods:
            return []
        
        # Create one comprehensive rule for bill_type branching
        all_types_used = set()
        for m, checks in branching_methods:
            for c in checks:
                val = c.split('=')[1]
                all_types_used.add(val)
        
        type_labels = []
        for val in sorted(all_types_used, key=lambda x: int(x) if x.isdigit() else 0):
            label = bill_type_map.get(val, '?')
            type_labels.append(f"{val}={label}" if label != '?' else val)
        
        return [BusinessRule(
            id=self._next_rule_id(),
            title=f"Bill Type Determines Business Logic ({len(all_types_used)} types used)",
            category="CONDITIONAL",
            evidence_methods=[m.name for m, _ in branching_methods],
            evidence_code=f"bill_type checks across {len(branching_methods)} methods",
            tables_affected=[],
            confidence="HIGH",
            details=f"The `bill_type` column drives major behavioral branching in "
                    f"{len(branching_methods)} methods: "
                    f"{', '.join(m.name + '()' for m, _ in branching_methods[:8])}. "
                    f"Types in use: {', '.join(type_labels)}. "
                    f"Each type may have different validation, calculation, and status rules."
        )]
    
    def _rules_from_credit_handling(self) -> List[BusinessRule]:
        """Methods checking is_credit = credit vs cash flow divergence."""
        credit_methods = []
        for m in self.result.methods:
            credit_checks = [c for c in m.status_checks if 'credit' in c.lower()]
            if credit_checks:
                credit_methods.append((m, credit_checks))
        
        if not credit_methods:
            return []
        
        return [BusinessRule(
            id=self._next_rule_id(),
            title=f"Credit vs Cash Billing Has Separate Business Rules",
            category="CONDITIONAL",
            evidence_methods=[m.name for m, _ in credit_methods],
            evidence_code=f"is_credit/credit_status checks in {len(credit_methods)} methods",
            tables_affected=[],
            confidence="HIGH",
            details=f"Credit billing is handled differently from cash billing in "
                    f"{len(credit_methods)} methods: "
                    f"{', '.join(m.name + '()' for m, _ in credit_methods[:6])}. "
                    f"Credit bills likely involve: deferred payment, credit limits, "
                    f"collection tracking, and approval workflows."
        )]
    
    def _rules_from_file_uploads(self) -> List[BusinessRule]:
        """Methods with file upload = document management rules."""
        upload_methods = [m for m in self.result.methods if m.has_file_upload]
        if not upload_methods:
            return []
        
        return [BusinessRule(
            id=self._next_rule_id(),
            title=f"File Upload Operations ({len(upload_methods)} methods)",
            category="VALIDATION",
            evidence_methods=[m.name for m in upload_methods],
            evidence_code="has_file_upload=True",
            tables_affected=[],
            confidence="MEDIUM",
            details=f"Methods handling file uploads: "
                    f"{', '.join(m.name + '()' for m in upload_methods)}. "
                    f"Must validate: file type, size, extension on server-side. "
                    f"Check if uploaded files are stored with original names (security risk) "
                    f"or renamed."
        )]
    
    # ─────────────────────────────────────────────
    # Flow Inference
    # ─────────────────────────────────────────────
    
    def _infer_flows(self) -> List[BusinessFlow]:
        flows = []
        flows.extend(self._flows_from_transactions())
        flows.extend(self._flows_from_crud_groups())
        flows.extend(self._flows_from_status_lifecycle())
        return flows
    
    def _flows_from_transactions(self) -> List[BusinessFlow]:
        """Each transaction block = an atomic business flow."""
        flows = []
        for m in sorted(self.result.methods, key=lambda x: x.line_count, reverse=True):
            if not m.has_transaction:
                continue
            all_writes = sorted(set(m.tables_insert + m.tables_update + m.tables_delete))
            all_reads = sorted(set(m.tables_select))
            if len(all_writes) < 2:
                continue
            
            # Infer flow name from method name
            title = self._humanize_method_name(m.name)
            
            # Extract status transitions from this method
            transitions = []
            for check in m.status_checks:
                if not check.startswith('$type=') and not check.startswith('card_type='):
                    col_val = check.split('=', 1)
                    if len(col_val) == 2:
                        col, val = col_val
                        label = self.result.value_maps.get(col, {}).get(val, '')
                        if label:
                            transitions.append(f"{col}: {val} ({label})")
                        else:
                            transitions.append(check)
            
            flows.append(BusinessFlow(
                id=self._next_flow_id(),
                title=title,
                category="TRANSACTION",
                entry_method=m.name,
                entry_file=Path(m.file_path).name,
                line_count=m.line_count,
                table_write_sequence=all_writes,
                table_read_sequence=all_reads[:10],
                has_transaction=True,
                has_otp=m.has_otp,
                has_json_response=m.has_json_response,
                status_transitions=transitions[:8],
                sub_methods=[mc for mc in m.models_called[:8]],
                details=f"Atomic operation: {len(all_writes)} tables written inside transaction. "
                        f"{'OTP authorization required. ' if m.has_otp else ''}"
                        f"{'Returns JSON response. ' if m.has_json_response else ''}"
                        f"Method is {m.line_count} lines — "
                        f"{'⚠️ extremely complex, high bug risk.' if m.line_count > 1000 else 'moderately complex.' if m.line_count > 300 else 'manageable size.'}"
            ))
        return flows
    
    def _flows_from_crud_groups(self) -> List[BusinessFlow]:
        """Group related CRUD methods by table pattern."""
        flows = []
        
        # Find tables that have both INSERT and UPDATE methods
        table_ops = defaultdict(lambda: {'insert': [], 'update': [], 'delete': [], 'select': []})
        for m in self.result.methods:
            for t in m.tables_insert:
                table_ops[t]['insert'].append(m.name)
            for t in m.tables_update:
                table_ops[t]['update'].append(m.name)
            for t in m.tables_delete:
                table_ops[t]['delete'].append(m.name)
        
        # Core billing tables (high-value CRUD flows)
        core_tables = ['ret_billing', 'ret_bill_details', 'ret_issue_receipt', 
                       'ret_service_bill', 'ret_cash_collection', 'ret_advance_transfer']
        
        for table in core_tables:
            if table not in table_ops:
                continue
            ops = table_ops[table]
            if not ops['insert'] and not ops['update']:
                continue
            
            all_methods = set(ops['insert'] + ops['update'] + ops['delete'])
            
            flows.append(BusinessFlow(
                id=self._next_flow_id(),
                title=f"`{table}` Lifecycle",
                category="LIFECYCLE",
                entry_method=', '.join(sorted(ops['insert'])[:3]) if ops['insert'] else 'N/A',
                table_write_sequence=[table],
                has_transaction=any(
                    m.has_transaction for m in self.result.methods 
                    if m.name in all_methods
                ),
                details=f"CREATE: {', '.join(ops['insert'][:5]) or 'none'}. "
                        f"UPDATE: {', '.join(ops['update'][:5]) or 'none'}. "
                        f"DELETE: {', '.join(ops['delete'][:3]) or 'none (soft-delete?)'}."
            ))
        
        return flows
    
    def _flows_from_status_lifecycle(self) -> List[BusinessFlow]:
        """Infer status lifecycle from value maps and method checks."""
        flows = []
        
        # bill_status lifecycle
        bill_status = self.result.value_maps.get('bill_status', {})
        if bill_status:
            transitions = []
            for val in sorted(bill_status.keys(), key=lambda x: int(x) if x.isdigit() else 0):
                label = bill_status[val]
                # Find methods that check this status
                checkers = [m.name for m in self.result.methods 
                           if f'bill_status={val}' in m.status_checks]
                transitions.append(f"{val} ({label}): checked by {', '.join(checkers[:3]) or 'unknown'}")
            
            flows.append(BusinessFlow(
                id=self._next_flow_id(),
                title="Bill Status State Machine",
                category="LIFECYCLE",
                entry_method="billing()",
                table_write_sequence=["ret_billing"],
                status_transitions=transitions,
                details=f"bill_status has {len(bill_status)} known states: "
                        f"{', '.join(f'{v}={l}' for v, l in sorted(bill_status.items(), key=lambda x: int(x[0]) if x[0].isdigit() else 0))}. "
                        f"Transitions are guarded by status checks in controller methods."
            ))
        
        # receipt_type lifecycle
        receipt_type = self.result.value_maps.get('receipt_type', {})
        if receipt_type and len(receipt_type) >= 3:
            flows.append(BusinessFlow(
                id=self._next_flow_id(),
                title="Receipt Type Classification",
                category="LIFECYCLE",
                entry_method="receipt()",
                table_write_sequence=["ret_issue_receipt"],
                status_transitions=[f"{v}={l}" for v, l in sorted(receipt_type.items(), 
                                   key=lambda x: int(x[0]) if x[0].isdigit() else 0)],
                details=f"Receipt operations are classified into {len(receipt_type)} types: "
                        f"{', '.join(f'{v}={l}' for v, l in sorted(receipt_type.items(), key=lambda x: int(x[0]) if x[0].isdigit() else 0))}."
            ))
        
        # issue_type lifecycle
        issue_type = self.result.value_maps.get('issue_type', {})
        if issue_type and len(issue_type) >= 3:
            flows.append(BusinessFlow(
                id=self._next_flow_id(),
                title="Issue Type Classification",
                category="LIFECYCLE",
                entry_method="issue()",
                table_write_sequence=["ret_issue_receipt"],
                status_transitions=[f"{v}={l}" for v, l in sorted(issue_type.items(),
                                   key=lambda x: int(x[0]) if x[0].isdigit() else 0)],
                details=f"Issue operations are classified into {len(issue_type)} types: "
                        f"{', '.join(f'{v}={l}' for v, l in sorted(issue_type.items(), key=lambda x: int(x[0]) if x[0].isdigit() else 0))}."
            ))
        
        return flows
    
    # ─────────────────────────────────────────────
    # Risk Inference
    # ─────────────────────────────────────────────
    
    def _infer_risks(self) -> List[BusinessRisk]:
        risks = []
        risks.extend(self._risks_from_complexity())
        risks.extend(self._risks_from_unprotected_writes())
        risks.extend(self._risks_from_unprotected_deletes())
        risks.extend(self._risks_from_missing_validations())
        risks.extend(self._risks_from_duplicates())
        return risks
    
    def _risks_from_complexity(self) -> List[BusinessRisk]:
        """Methods > 500 lines = complexity/maintainability risk."""
        risks = []
        for m in sorted(self.result.methods, key=lambda x: x.line_count, reverse=True):
            if m.line_count < 300:
                break
            
            severity = "CRITICAL" if m.line_count > 2000 else "HIGH" if m.line_count > 500 else "MEDIUM"
            all_tables = set(m.tables_insert + m.tables_update + m.tables_delete + m.tables_select)
            
            risks.append(BusinessRisk(
                id=self._next_risk_id(),
                severity=severity,
                category="COMPLEXITY",
                method=m.name,
                file=Path(m.file_path).name,
                evidence=f"{m.line_count} lines, touches {len(all_tables)} tables"
                         f"{', uses transaction' if m.has_transaction else ''}"
                         f"{', has OTP' if m.has_otp else ''}",
                impact=f"High bug density — methods this large are impossible to unit test, "
                       f"difficult to review, and prone to side effects. "
                       f"Any bug fix in this method risks breaking other flows.",
                recommendation=f"Refactor into smaller methods. Extract each bill_type branch "
                              f"or $type action into its own method."
            ))
        return risks
    
    def _risks_from_unprotected_writes(self) -> List[BusinessRisk]:
        """Multi-table writes WITHOUT transaction = consistency risk."""
        risks = []
        for m in self.result.methods:
            if m.has_transaction:
                continue
            all_writes = set(m.tables_insert + m.tables_update)
            if len(all_writes) < 3:
                continue
            
            risks.append(BusinessRisk(
                id=self._next_risk_id(),
                severity="HIGH",
                category="CONSISTENCY",
                method=m.name,
                file=Path(m.file_path).name,
                evidence=f"Writes to {len(all_writes)} tables without transaction: "
                         f"{', '.join(sorted(all_writes)[:5])}",
                impact=f"If any write fails, data is left in an inconsistent state. "
                       f"Partial writes across {len(all_writes)} tables cannot be rolled back.",
                recommendation=f"Wrap in $this->db->trans_start() / trans_complete()."
            ))
        return risks
    
    def _risks_from_unprotected_deletes(self) -> List[BusinessRisk]:
        """DELETE without transaction = data loss risk."""
        risks = []
        for m in self.result.methods:
            if not m.tables_delete or m.has_transaction:
                continue
            
            risks.append(BusinessRisk(
                id=self._next_risk_id(),
                severity="CRITICAL",
                category="DATA_LOSS",
                method=m.name,
                file=Path(m.file_path).name,
                evidence=f"DELETE from {', '.join(m.tables_delete)} without transaction",
                impact=f"If subsequent operations fail, deleted data from "
                       f"{', '.join(m.tables_delete)} is permanently lost with no rollback.",
                recommendation=f"Either: (a) wrap in transaction, or (b) use soft-delete "
                              f"(is_deleted flag) instead of hard DELETE."
            ))
        return risks
    
    def _risks_from_missing_validations(self) -> List[BusinessRisk]:
        """Methods with POST input but no validation = input safety risk."""
        risks = []
        for m in self.result.methods:
            if not m.post_fields:
                continue
            if m.validations:
                continue  # Has some validation
            
            all_writes = set(m.tables_insert + m.tables_update)
            if not all_writes:
                continue  # Read-only, less dangerous
            
            if len(m.post_fields) < 3:
                continue  # Minor methods
            
            risks.append(BusinessRisk(
                id=self._next_risk_id(),
                severity="MEDIUM",
                category="INPUT",
                method=m.name,
                file=Path(m.file_path).name,
                evidence=f"Accepts {len(m.post_fields)} POST fields with no visible validation, "
                         f"writes to {', '.join(sorted(all_writes)[:3])}",
                impact=f"Unvalidated user input written directly to database. "
                       f"Risk of invalid data, type mismatches, or injection.",
                recommendation=f"Add CI3 form_validation rules for: "
                              f"{', '.join(m.post_fields[:5])}"
                              f"{'...' if len(m.post_fields) > 5 else ''}."
            ))
        return risks
    
    def _risks_from_duplicates(self) -> List[BusinessRisk]:
        """Duplicate method definitions = PHP override risk."""
        risks = []
        for first, dup in self.result.duplicate_methods:
            risks.append(BusinessRisk(
                id=self._next_risk_id(),
                severity="HIGH",
                category="CONSISTENCY",
                method=dup,
                file=dup.split(':')[0] if ':' in dup else '',
                evidence=f"Duplicate method: first at {first}, redefined at {dup}. "
                         f"PHP uses the LAST definition.",
                impact=f"The first definition at {first} is dead code. "
                       f"Anyone reading/fixing that version is wasting time — "
                       f"the runtime uses {dup} instead.",
                recommendation=f"Remove the dead definition at {first} or merge them."
            ))
        return risks
    
    # ─────────────────────────────────────────────
    # Validation Inventory
    # ─────────────────────────────────────────────
    
    def _build_validation_inventory(self) -> Dict:
        """Build input validation inventory: which methods validate what, and where gaps exist."""
        inventory = {
            'validated_methods': [],     # Methods with validation
            'unvalidated_methods': [],   # Methods with POST but no validation
            'total_post_fields': 0,
            'validated_fields': set(),
            'all_post_fields': set(),
        }
        
        for m in self.result.methods:
            if not m.post_fields:
                continue
            
            inventory['all_post_fields'].update(m.post_fields)
            inventory['total_post_fields'] += len(m.post_fields)
            
            all_writes = set(m.tables_insert + m.tables_update)
            entry = {
                'method': m.name,
                'file': Path(m.file_path).name,
                'post_fields': sorted(m.post_fields),
                'field_count': len(m.post_fields),
                'has_validation': bool(m.validations),
                'validation_rules': m.validations[:10] if m.validations else [],
                'writes_to_db': bool(all_writes),
                'tables_written': sorted(all_writes)[:5],
                'has_file_upload': m.has_file_upload,
            }
            
            if m.validations:
                inventory['validated_methods'].append(entry)
                inventory['validated_fields'].update(m.post_fields)
            else:
                inventory['unvalidated_methods'].append(entry)
        
        # Convert sets to sorted lists for serialization
        inventory['all_post_fields'] = sorted(inventory['all_post_fields'])
        inventory['validated_fields'] = sorted(inventory['validated_fields'])
        inventory['gap_fields'] = sorted(
            set(inventory['all_post_fields']) - set(inventory['validated_fields'])
        )
        
        return inventory
    
    # ─────────────────────────────────────────────
    # Cross-Module Map
    # ─────────────────────────────────────────────
    
    def _build_cross_module_map(self) -> Dict:
        """Build cross-module dependency map from AJAX routes and shared tables."""
        xmod = {
            'outbound_ajax': {},    # This module calls other modules' controllers
            'shared_tables': {},    # Tables this module uses but doesn't own
            'provided_tables': {},  # Tables this module owns that others might use
            'model_dependencies': defaultdict(list),  # External models loaded
        }
        
        # 1. AJAX cross-module calls (from ajax_routes analysis)
        for ctrl, methods in self.result.ajax_routes.items():
            for method_name, status in methods.items():
                if status == 'cross-module':
                    xmod['outbound_ajax'].setdefault(ctrl, []).append(method_name)
        
        # 2. Shared tables — referenced but not owned
        owned = set(self.result.tables_owned)
        referenced = set(self.result.tables_referenced)
        shared_read = referenced - owned
        
        # For each shared table, find which methods read it
        for table in sorted(shared_read):
            readers = []
            for m in self.result.methods:
                if table in m.tables_select:
                    readers.append(m.name)
            if readers:
                xmod['shared_tables'][table] = {
                    'access': 'READ',
                    'methods': sorted(set(readers))[:8],
                }
        
        # Also check for cross-module writes (writing to tables not owned)
        for m in self.result.methods:
            for table in m.tables_insert + m.tables_update:
                if table not in owned and table in referenced:
                    entry = xmod['shared_tables'].setdefault(table, {'access': 'READ', 'methods': []})
                    entry['access'] = 'READ+WRITE'
                    if m.name not in entry['methods']:
                        entry['methods'].append(m.name)
        
        # 3. Provided tables — owned tables with significant data
        for table in sorted(owned):
            writers = []
            for m in self.result.methods:
                if table in m.tables_insert or table in m.tables_update:
                    writers.append(m.name)
            if writers:
                xmod['provided_tables'][table] = sorted(set(writers))[:5]
        
        # 4. Model dependencies (cross-module model loading)
        for m in self.result.methods:
            for model_call in m.models_called:
                # Heuristic: if model name doesn't contain the module name, it's cross-module
                if self.result.module.lower() not in model_call.lower():
                    xmod['model_dependencies'][model_call].append(m.name)
        
        # Deduplicate model dependency method lists
        xmod['model_dependencies'] = {
            k: sorted(set(v))[:5] for k, v in xmod['model_dependencies'].items()
        }
        
        return xmod
    
    # ─────────────────────────────────────────────
    # Helpers
    # ─────────────────────────────────────────────
    
    @staticmethod
    def _humanize_method_name(name: str) -> str:
        """Convert method_name to Human Readable Title."""
        replacements = {
            'ret_': 'Retail ', 'adtrns': 'Advance Transfer ',
            'rcpt': 'Receipt', 'coll': 'Collection',
            'adj': 'Adjustment', 'svc': 'Service',
        }
        result = name
        for abbr, full in replacements.items():
            result = result.replace(abbr, full)
        
        words = result.replace('_', ' ').split()
        return ' '.join(w.capitalize() for w in words)
