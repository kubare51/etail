"""Pydantic models for index data structures."""

from datetime import datetime
from pydantic import BaseModel, Field


class FunctionDef(BaseModel):
    """A function definition in the codebase."""
    name: str
    file: str
    line: int
    end_line: int | None = None
    params: list[str] = Field(default_factory=list)
    language: str = "javascript"  # or "php"
    metrics: dict | None = None  # Complexity metrics from analysis engine


class FunctionCall(BaseModel):
    """A function call relationship."""
    source: str  # Caller function name
    target: str  # Called function name
    line: int
    file: str
    call_type: str = "calls"  # "calls", "load_model", "load_view", "model_call", "db_read", "db_write", etc.
    target_file: str | None = None  # File where target function is defined
    metadata: dict | None = None  # Extra info (e.g. http_method, payload_keys for AJAX calls)


# ── Deep Analysis Models ───────────────────────────────────────────────

class FormulaExpr(BaseModel):
    """An arithmetic formula extracted from function body.

    Captures assignments like:
      JS:  var taxable_amount = items.item_cost - items.item_total_tax;
      PHP: $closing = $opening + $inward - $deposit;
    """
    variable: str              # LHS — e.g., 'taxable_amount'
    expression: str            # RHS — e.g., 'items.item_cost - items.item_total_tax'
    operator: str              # Primary op: 'subtraction', 'addition', 'compound', 'division', 'multiplication'
    line: int
    function: str              # Enclosing function name
    file: str
    precision: str | None = None  # e.g., 'toFixed(2)' if chained
    language: str = 'javascript'


class Accumulator(BaseModel):
    """A running total pattern: X += parseFloat(Y).

    Captures patterns like:
      JS:  total_amt += parseFloat(items.item_cost);
      PHP: $total += (float)$row->amount;
    """
    variable: str              # e.g., 'total_amt'
    source_field: str          # e.g., 'items.item_cost'
    wrapper: str | None = None # e.g., 'parseFloat' if wrapped
    line: int
    function: str
    file: str
    direction: str = 'add'     # 'add' (+=) or 'subtract' (-=)
    language: str = 'javascript'


class ConditionalBranch(BaseModel):
    """A dispatch table entry from if/else/switch.

    Captures patterns like:
      JS:  if (item.payment_mode == 'Cash') { cash_payment += ... }
      PHP: if ($type == 1) { ... }
    """
    variable: str              # Tested variable — e.g., 'item.payment_mode'
    value: str                 # Compared value — e.g., "'Cash'"
    operator: str = '=='       # Comparison operator
    action_summary: str        # First statement in body — e.g., 'cash_payment += parseFloat(...)'
    line: int
    function: str
    file: str
    branch_type: str = 'if'    # 'if', 'else_if', 'switch_case'
    language: str = 'javascript'


class MagicNumber(BaseModel):
    """A hardcoded constant in a comparison.

    Captures patterns like:
      JS:  if (stone_type == 2) { ... }
      PHP: if ($uom == 6) { ... }
    """
    value: str                 # The literal — e.g., '2', "'Cash'"
    variable: str              # The tested var — e.g., 'stone_type'
    operator: str              # e.g., '==', '!=', '==='
    line: int
    function: str
    file: str
    context: str = ''          # Surrounding code snippet for human readability
    language: str = 'javascript'



class UIMatch(BaseModel):
    """A string reference/UI match in code."""
    func: str = "GLOBAL"
    line: int
    context: str
    file: str


class Level2Deps(BaseModel):
    """Level 2 dependencies for a function."""
    calls: list[str] = Field(default_factory=list)
    called_by: list[str] = Field(default_factory=list)


class ModuleIndex(BaseModel):
    """Complete index for a module."""
    module: str
    version: str = "2.0.0"
    indexed_at: str = Field(default_factory=lambda: datetime.now().isoformat())
    functions: list[FunctionDef] = Field(default_factory=list)
    calls: list[FunctionCall] = Field(default_factory=list)
    files: list[dict] = Field(default_factory=list)  # List of {path: str}
    ui_matches: list[UIMatch] = Field(default_factory=list)
    level2: dict[str, Level2Deps] = Field(default_factory=dict)
    # Deep analysis (populated when deep_analyze=True during indexing)
    formulas: list[FormulaExpr] = Field(default_factory=list)
    accumulators: list[Accumulator] = Field(default_factory=list)
    conditionals: list[ConditionalBranch] = Field(default_factory=list)
    magic_numbers: list[MagicNumber] = Field(default_factory=list)
    
    def to_kb_format(self) -> dict:
        """Convert to Knowledge Base compatible JSON format."""
        return {
            "module": self.module,
            "version": self.version,
            "indexed_at": self.indexed_at,
            "functions": [f.model_dump() for f in self.functions],
            "calls": [
                {
                    "source": c.source,
                    "target": c.target,
                    "line": c.line,
                    "file": c.file,
                    "type": c.call_type,
                    "target_file": c.target_file,
                    **({"metadata": c.metadata} if c.metadata else {}),
                }
                for c in self.calls
            ],
            "files": self.files,
            "ui_matches": [m.model_dump() for m in self.ui_matches],
            "level2": {
                name: deps.model_dump() 
                for name, deps in self.level2.items()
            },
            # Deep analysis data
            "formulas": [f.model_dump() for f in self.formulas],
            "accumulators": [a.model_dump() for a in self.accumulators],
            "conditionals": [c.model_dump() for c in self.conditionals],
            "magic_numbers": [m.model_dump() for m in self.magic_numbers],
        }


class ImpactResult(BaseModel):
    """Result of an impact analysis query."""
    term: str
    functions: list[FunctionDef] = Field(default_factory=list)
    calls: list[dict] = Field(default_factory=list)  # With type: calls/called_by
    ui_matches: list[UIMatch] = Field(default_factory=list)
    level2: dict[str, Level2Deps] = Field(default_factory=dict)
    risk_level: str = "Low"  # Low, Medium, High
