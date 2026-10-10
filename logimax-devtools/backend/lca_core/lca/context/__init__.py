"""
LCA Context Assembly Engine.

Given a natural language bug description, automatically assembles a rich
context document with:
  1. Relevant code chunks (via RAG semantic search)
  2. Module brain sections (business rules, data flows, danger zones)
  3. System brain cross-module info (shared tables, dependencies)
  4. Matching bug fix recipes (from central repo)
  5. Related controller/model maps

Output: Structured context.md ready for the Bug Fix Engine.
"""
