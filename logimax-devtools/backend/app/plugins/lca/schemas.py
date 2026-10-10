"""
LCA Plugin - Pydantic Schemas
Request/Response models for API validation
"""
from enum import Enum
from pydantic import BaseModel, Field
from typing import Optional


# ---------- Enums ----------

class TaskType(str, Enum):
    BUGFIX = "BUGFIX"
    CR = "CR"
    NR = "NR"

class TaskPriority(str, Enum):
    LOW = "LOW"
    MEDIUM = "MEDIUM"
    HIGH = "HIGH"
    CRITICAL = "CRITICAL"

class ChangeType(str, Enum):
    BUGFIX = "BUGFIX"
    CR = "CR"
    NR = "NR"


# ---------- Existing Request Schemas ----------

class IndexRequest(BaseModel):
    path: str = Field(..., description="Path to directory to index")
    name: str = Field(..., description="Name for the index")
    exclude_patterns: list[str] = Field(default=[], description="Patterns to exclude")


class SearchRequest(BaseModel):
    query: str = Field(..., min_length=1, description="Search query")
    limit: int = Field(default=20, ge=1, le=100, description="Max results")


class ImpactRequest(BaseModel):
    function: str = Field(..., description="Function to analyze")
    depth: int = Field(default=2, ge=1, le=5, description="Analysis depth")


class GraphRequest(BaseModel):
    function: str = Field(..., description="Center function")
    depth: int = Field(default=2, ge=1, le=5, description="Graph depth")
    direction: str = Field(default="both", description="Direction: callers, callees, both")


# ---------- Pipeline Request Schemas ----------

class CreatePipelineTaskRequest(BaseModel):
    """POST /pipeline/task — Create a new development task"""
    title: str = Field(default="Untitled Task", min_length=1, max_length=200)
    type: TaskType = Field(default=TaskType.BUGFIX)
    description: str = Field(default="")
    index_name: str = Field(default="etail-admin")
    target_files: list[str] = Field(default=[])
    target_functions: list[str] = Field(default=[])
    assigned_to: Optional[str] = None
    priority: TaskPriority = Field(default=TaskPriority.MEDIUM)


class ApprovalRequest(BaseModel):
    """POST /pipeline/{task_id}/approve-plan, /approve-commit — Approval action"""
    approved_by: str = Field(default="system", min_length=1, max_length=100)
    comments: Optional[str] = None


class RejectionRequest(BaseModel):
    """POST /pipeline/{task_id}/reject-plan — Rejection action"""
    rejected_by: str = Field(default="system", min_length=1, max_length=100)
    comments: str = Field(default="")


class CompleteImplementationRequest(BaseModel):
    """POST /pipeline/{task_id}/complete — Mark implementation complete"""
    changed_files: list[str] = Field(default=[], description="List of file paths changed")


class CreateCommitRequest(BaseModel):
    """POST /pipeline/{task_id}/commit — Create local commit"""
    message: Optional[str] = Field(default=None, description="Custom commit message")


class MergeRequest(BaseModel):
    """POST /pipeline/{task_id}/merge — Mark PR as merged"""
    merged_by: str = Field(default="system", min_length=1, max_length=100)


# ---------- Analytics Request Schemas ----------

class BatchImpactRequest(BaseModel):
    """POST /analytics/batch-impact/{index_name} — Multi-function blast radius"""
    functions: list[str] = Field(..., min_length=1, description="Functions to analyze")
    depth: int = Field(default=2, ge=1, le=5)


# ---------- Tools Request Schemas ----------

class QualityGateRequest(BaseModel):
    """POST /quality/gate — Run quality gate evaluation"""
    type: ChangeType = Field(default=ChangeType.BUGFIX)
    files: list[str] = Field(default=[], description="File paths to evaluate")
    index_name: str = Field(default="default")


class WebhookMockRequest(BaseModel):
    """POST /webhook-mock — Simulate AI webhook for testing"""
    code: str = Field(default="", description="Code to analyze")
    context: str = Field(default="", description="Function name or context label")
    impact: dict = Field(default={}, description="Pre-calculated impact data")


# ---------- Response Schemas ----------

class FunctionResponse(BaseModel):
    name: str
    qualified_name: str
    file: str
    line: int
    end_line: Optional[int] = None
    class_name: Optional[str] = None


class FunctionDetailResponse(FunctionResponse):
    callers: list[str] = []
    callees: list[str] = []
    caller_count: int = 0
    callee_count: int = 0
    code: Optional[str] = None


class IndexStatusResponse(BaseModel):
    exists: bool
    name: Optional[str] = None
    path: Optional[str] = None
    functions: int = 0
    calls: int = 0


class SearchResponse(BaseModel):
    results: list[FunctionResponse]
    total: int


class CallInfoResponse(BaseModel):
    target: str
    line: int
    call_type: str


class Level2DepsResponse(BaseModel):
    calls: list[str] = []
    called_by: list[str] = []


class FunctionInfoResponse(BaseModel):
    file: str
    line: int
    end_line: Optional[int] = None
    class_name: Optional[str] = None


class ImpactResponse(BaseModel):
    function: str
    function_info: FunctionInfoResponse = Field(default_factory=dict)
    calls: list[CallInfoResponse]
    level2: dict[str, Level2DepsResponse]
    risk_level: str
    total_impact: int


class GraphNodeResponse(BaseModel):
    id: str
    label: str
    type: str  # center, caller, callee


class GraphEdgeResponse(BaseModel):
    source: str
    target: str


class GraphResponse(BaseModel):
    nodes: list[GraphNodeResponse]
    edges: list[GraphEdgeResponse]


class ExplanationResponse(BaseModel):
    function: str
    explanation: str
    is_mock: bool

class IndexProgressResponse(BaseModel):
    total_files: int
    processed_files: int
    current_file: str
    progress: float
    is_complete: bool
    errors: list[str] = []
