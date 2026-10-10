"""
LCA Plugin - FastAPI Router (Delegator)
Delegates to domain-specific sub-routers in the routers/ package.

Previous monolithic 710-line router has been split into:
  routers/index_router.py      - Index management (5 endpoints)
  routers/search_router.py     - Search & function details (4 endpoints)
  routers/impact_router.py     - Impact analysis & AI explain (3 endpoints)
  routers/workflow_router.py   - Change request lifecycle (8 endpoints)
  routers/pipeline_router.py   - Dev pipeline 10-stage workflow (13 endpoints)
  routers/git_router.py        - Git operations (3 endpoints)
  routers/analytics_router.py  - Codebase analytics (10 endpoints)
  routers/tools_router.py      - Test gen, quality gate, mock (3 endpoints)
"""
from .routers import create_router

__all__ = ["create_router"]
