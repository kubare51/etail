"""
Data models for RAG chunks and metadata.
"""
from __future__ import annotations
from pydantic import BaseModel, Field
from enum import Enum
from typing import Optional


class ChunkType(str, Enum):
    """Type of code chunk."""
    CONTROLLER_METHOD = "controller_method"
    MODEL_METHOD = "model_method"
    HELPER_FUNCTION = "helper_function"
    LIBRARY_METHOD = "library_method"
    VIEW_FILE = "view_file"
    JS_FUNCTION = "js_function"
    JS_EVENT_HANDLER = "js_event_handler"
    JS_AJAX_CALL = "js_ajax_call"
    CSS_SECTION = "css_section"
    CONFIG_FILE = "config_file"
    SQL_MIGRATION = "sql_migration"
    DOCUMENTATION = "documentation"
    BRAIN_DOCUMENT = "brain_document"
    CLASS_DEFINITION = "class_definition"
    WHOLE_FILE = "whole_file"


class CodeChunk(BaseModel):
    """A single chunk of code ready for embedding."""

    # Identity
    id: str = Field(description="Unique ID: file_path::method_name or file_path::section")
    chunk_type: ChunkType

    # Content
    text: str = Field(description="The actual code/text content")
    summary: str = Field(default="", description="One-line summary of what this chunk does")

    # Location
    file_path: str = Field(description="Relative path from project root")
    start_line: int = Field(default=0)
    end_line: int = Field(default=0)

    # Code metadata
    module: str = Field(default="unknown", description="Business module: billing, customer, estimation, etc.")
    class_name: Optional[str] = Field(default=None)
    method_name: Optional[str] = Field(default=None)
    visibility: Optional[str] = Field(default=None, description="public/private/protected")

    # Dependencies (enriched by LCA or static analysis)
    models_loaded: list[str] = Field(default_factory=list, description="CI3 models loaded in this chunk")
    views_rendered: list[str] = Field(default_factory=list, description="Views loaded/rendered")
    tables_touched: list[str] = Field(default_factory=list, description="DB tables referenced")
    functions_called: list[str] = Field(default_factory=list, description="Functions/methods called")
    ajax_endpoints: list[str] = Field(default_factory=list, description="AJAX URLs called (JS chunks)")

    # Business context (enriched from module brains)
    business_rules: list[str] = Field(default_factory=list, description="Related business rules from brain")
    tags: list[str] = Field(default_factory=list, description="Searchable tags")

    def to_embedding_text(self) -> str:
        """Generate the text that will be embedded for vector search.

        Combines summary, code, and metadata into a single searchable string.
        """
        parts = []

        if self.summary:
            parts.append(f"Summary: {self.summary}")

        parts.append(f"File: {self.file_path}")
        parts.append(f"Type: {self.chunk_type.value}")
        parts.append(f"Module: {self.module}")

        if self.class_name and self.method_name:
            parts.append(f"Method: {self.class_name}::{self.method_name}")
        elif self.method_name:
            parts.append(f"Function: {self.method_name}")

        if self.tables_touched:
            parts.append(f"Tables: {', '.join(self.tables_touched)}")

        if self.business_rules:
            parts.append(f"Business rules: {'; '.join(self.business_rules)}")

        if self.tags:
            parts.append(f"Tags: {', '.join(self.tags)}")

        # Append the actual code (truncated for embedding if very long)
        code_text = self.text
        if len(code_text) > 4000:
            code_text = code_text[:4000] + "\n... (truncated)"

        parts.append(f"\nCode:\n{code_text}")

        return "\n".join(parts)

    def to_metadata_dict(self) -> dict:
        """Generate metadata dict for ChromaDB storage."""
        meta = {
            "chunk_type": self.chunk_type.value,
            "file_path": self.file_path,
            "module": self.module,
            "start_line": self.start_line,
            "end_line": self.end_line,
        }
        if self.class_name:
            meta["class_name"] = self.class_name
        if self.method_name:
            meta["method_name"] = self.method_name
        if self.visibility:
            meta["visibility"] = self.visibility
        if self.tables_touched:
            meta["tables"] = ",".join(self.tables_touched)
        if self.models_loaded:
            meta["models"] = ",".join(self.models_loaded)
        if self.tags:
            meta["tags"] = ",".join(self.tags)
        if self.summary:
            meta["summary"] = self.summary[:200]
        return meta


class IngestStats(BaseModel):
    """Statistics from an ingest run."""
    total_files: int = 0
    total_chunks: int = 0
    by_type: dict[str, int] = Field(default_factory=dict)
    by_module: dict[str, int] = Field(default_factory=dict)
    skipped_files: int = 0
    errors: list[str] = Field(default_factory=list)
