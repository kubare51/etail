# LCA Improvement Roadmap

> Created: 2026-06-23 | Status: PLANNED (post-SDLC v4.3 deployment)

These documents capture the analysis and strategy from the SDLC pipeline hardening session. Execute after current SDLC version is stable in production.

## Documents

| # | Document | Summary | Effort | Priority |
|---|---|---|---|---|
| 00 | [Cashbook Fix Analysis](00-cashbook-fix-analysis.md) | Post-mortem of the 4-hour cashbook fix — 73% wasted on infra | Reference | — |
| 01 | [Storage Strategy](01-storage-strategy.md) | SQLite vs JSON — unified architecture, kill 4 duplicate stores | ~7 hours | 🔴 HIGH |
| 02 | [Knowledge Strategy](02-knowledge-strategy.md) | LCA + RAG + Module Brains unification — enable LCA MCP, slim brains | ~5 hours | 🔴 HIGH |
| 03 | [BI Extraction Strategy](03-bi-extraction-strategy.md) | Business intelligence from code — fix MCP BI layer, magic number registry | ~12 hours | 🟡 MED |

## Execution Order

```
Phase 1: Fix MCP BI loader (json_store.py — ALREADY DONE, needs server restart)
Phase 2: Storage migration — SQLite as query engine (01)
Phase 3: Enable LCA MCP in subagent prompts (02 — ALREADY DONE)
Phase 4: Magic number registry + formula interpreter (03)
Phase 5: Auto-refresh brain staleness detection (02)
```

## Already Completed (this session)

- ✅ Fixed `json_store.py` to load formulas/conditionals/magic_numbers/accumulators
- ✅ Added `{php_path}` placeholder to all code prompts
- ✅ Rewrote mutation test protocol (file-level backup, no git ops)
- ✅ Injected conftest fixture docs into code prompts
- ✅ Added LCA MCP tool instructions to code/code_express prompts
- ✅ Added `environment` section to `.sdlc/config.json`
