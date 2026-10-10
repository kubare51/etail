"""Brain Builder v2.2 — Data models and types.

Shared data classes used across parser, generator, and builder modules.
"""

from typing import Dict, List, Optional, Set, Tuple
from dataclasses import dataclass, field


@dataclass
class ExtractedMethod:
    """A method extracted from a PHP/JS file."""
    name: str
    file_path: str
    start_line: int
    end_line: int
    visibility: str = "public"  # public, private, protected, function
    params: str = ""
    tables_insert: List[str] = field(default_factory=list)
    tables_update: List[str] = field(default_factory=list)
    tables_delete: List[str] = field(default_factory=list)
    tables_select: List[str] = field(default_factory=list)
    models_called: List[str] = field(default_factory=list)
    ajax_urls: List[str] = field(default_factory=list)
    validations: List[str] = field(default_factory=list)
    calculations: List[str] = field(default_factory=list)
    status_checks: List[str] = field(default_factory=list)
    has_transaction: bool = False
    has_json_response: bool = False
    has_otp: bool = False
    has_file_upload: bool = False
    post_fields: List[str] = field(default_factory=list)
    line_count: int = 0


@dataclass
class StatusCodeValue:
    """A status code value found in DB or code."""
    table: str
    column: str
    value: str
    count: int = 0
    meaning: str = ""  # AI interpretation
    confirmed: bool = False  # Human confirmed?
    code_context: str = ""  # Where in code this value is used


@dataclass
class ReviewItem:
    """A single Q&A item for human review."""
    id: str
    category: str  # RULE, STATUS, FLOW, RISK, PATTERN
    code_ref: str  # file:line or method name
    ai_observation: str  # What the code does
    question: str  # What we need human to confirm
    status: str = "PENDING"  # PENDING, CONFIRMED, CORRECTED, SKIPPED
    human_answer: str = ""
    priority: str = "MEDIUM"  # HIGH, MEDIUM, LOW


@dataclass
class BrainBuildResult:
    """Result of brain building for a module."""
    module: str
    methods: List[ExtractedMethod] = field(default_factory=list)
    status_codes: List[StatusCodeValue] = field(default_factory=list)
    review_items: List[ReviewItem] = field(default_factory=list)
    tables_owned: List[str] = field(default_factory=list)
    tables_referenced: List[str] = field(default_factory=list)
    js_files: List[str] = field(default_factory=list)
    view_files: List[str] = field(default_factory=list)
    model_files: List[str] = field(default_factory=list)
    controller_files: List[str] = field(default_factory=list)
    js_data: Dict = field(default_factory=dict)
    duplicate_methods: List[Tuple[str, str]] = field(default_factory=list)
    value_maps: Dict = field(default_factory=dict)
    view_data: Dict = field(default_factory=dict)
    db_row_counts: Dict = field(default_factory=dict)
    db_status_values: Dict = field(default_factory=dict)
    db_discoveries: Dict = field(default_factory=dict)
    ajax_routes: Dict = field(default_factory=dict)
    # v2.3: Business Intelligence
    business_rules: List['BusinessRule'] = field(default_factory=list)
    business_flows: List['BusinessFlow'] = field(default_factory=list)
    business_risks: List['BusinessRisk'] = field(default_factory=list)
    validation_inventory: Dict = field(default_factory=dict)
    cross_module_map: Dict = field(default_factory=dict)


@dataclass
class BusinessRule:
    """An AI-inferred business rule from code patterns."""
    id: str                     # RULE-001
    title: str                  # Human-readable rule name
    category: str               # AUTH, VALIDATION, STATUS, DATA_INTEGRITY, CONDITIONAL, CALCULATION
    evidence_methods: List[str] = field(default_factory=list)
    evidence_code: str = ""     # Code pattern that triggered inference
    tables_affected: List[str] = field(default_factory=list)
    confidence: str = "MEDIUM"  # HIGH, MEDIUM, LOW
    status: str = "AI-INFERRED" # AI-INFERRED, CONFIRMED, CORRECTED
    details: str = ""           # Extended explanation


@dataclass
class BusinessFlow:
    """An AI-inferred business flow from method/table chains."""
    id: str                     # FLOW-001
    title: str
    category: str               # CRUD, TRANSACTION, LIFECYCLE, AUTH
    entry_method: str = ""
    entry_file: str = ""
    line_count: int = 0
    table_write_sequence: List[str] = field(default_factory=list)
    table_read_sequence: List[str] = field(default_factory=list)
    has_transaction: bool = False
    has_otp: bool = False
    has_json_response: bool = False
    status_transitions: List[str] = field(default_factory=list)
    sub_methods: List[str] = field(default_factory=list)
    details: str = ""


@dataclass
class BusinessRisk:
    """An AI-inferred risk from code smells and danger patterns."""
    id: str                     # RISK-001
    severity: str               # CRITICAL, HIGH, MEDIUM, LOW
    category: str               # DATA_LOSS, CONSISTENCY, COMPLEXITY, INPUT, MAGIC_VALUE, SECURITY
    method: str = ""
    file: str = ""
    evidence: str = ""
    impact: str = ""
    recommendation: str = ""

