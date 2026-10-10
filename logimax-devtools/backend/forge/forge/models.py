"""Data models for training examples."""
from __future__ import annotations
from pydantic import BaseModel, Field
from typing import Optional
from datetime import datetime
from enum import Enum


class Category(str, Enum):
    BUG_FIX = "bug_fix"
    CODE_GENERATION = "code_generation"
    CODE_REVIEW = "code_review"
    REFACTORING = "refactoring"
    ARCHITECTURE = "architecture"
    DEBUGGING = "debugging"
    EXPLANATION = "explanation"
    CONFIGURATION = "configuration"
    TESTING = "testing"
    DOCUMENTATION = "documentation"
    DATABASE = "database"
    UI_FIX = "ui_fix"
    DISCUSSION = "discussion"
    UNKNOWN = "unknown"


class Outcome(str, Enum):
    VERIFIED = "verified_working"
    COMMITTED = "committed"
    APPLIED = "applied"
    REVERTED = "reverted"
    FAILED = "failed"
    UNKNOWN = "unknown"


class Quality(str, Enum):
    HIGH = "high"        # Verified fix, clean interaction
    MEDIUM = "medium"    # Applied but not verified
    LOW = "low"          # Failed, reverted, or noisy
    SKIP = "skip"        # Not useful for training


class TrainingExample(BaseModel):
    """A single training example extracted from a conversation."""
    id: str
    conversation_id: str
    category: Category = Category.UNKNOWN
    quality: Quality = Quality.MEDIUM

    # The training triple
    instruction: str = ""         # What the user asked
    input_context: str = ""       # Relevant code/context shown
    output: str = ""              # What the AI produced (code, explanation)

    # Metadata
    module: Optional[str] = None
    files_modified: list[str] = Field(default_factory=list)
    tools_used: list[str] = Field(default_factory=list)
    outcome: Outcome = Outcome.UNKNOWN
    tags: list[str] = Field(default_factory=list)

    # Source tracking
    step_range: tuple[int, int] = (0, 0)  # start_step, end_step
    extracted_at: str = Field(default_factory=lambda: datetime.now().isoformat())

    # Optional enrichment
    git_diff: Optional[str] = None
    before_code: Optional[str] = None
    after_code: Optional[str] = None


class ConversationMeta(BaseModel):
    """Metadata about a parsed conversation."""
    conversation_id: str
    title: str = ""
    created_at: Optional[str] = None
    total_steps: int = 0
    user_messages: int = 0
    model_responses: int = 0
    tool_calls: int = 0
    files_read: list[str] = Field(default_factory=list)
    files_modified: list[str] = Field(default_factory=list)
    examples_extracted: int = 0
    primary_category: Category = Category.UNKNOWN
    primary_module: Optional[str] = None


class DatasetStats(BaseModel):
    """Statistics about the full dataset."""
    total_conversations: int = 0
    total_examples: int = 0
    by_category: dict[str, int] = Field(default_factory=dict)
    by_quality: dict[str, int] = Field(default_factory=dict)
    by_outcome: dict[str, int] = Field(default_factory=dict)
    by_module: dict[str, int] = Field(default_factory=dict)
    avg_instruction_length: float = 0
    avg_output_length: float = 0
