# Knowledge Strategy: LCA + RAG + Module Brains

## The Uncomfortable Truth

We have **3 knowledge systems** that don't talk to each other, and 2 of them are **disabled**.

| System | Status | Data Volume | What It Knows |
|---|---|---|---|
| **LCA Index** (`.lca/`) | ⚠️ **DISABLED** in config | 133 MB, 44,634 functions | Code structure, call graphs, DB ops, formulas |
| **RAG** (semantic search) | ⚠️ **DISABLED** in config | Unknown (embeddings) | Natural language → code concept matching |
| **Module Brains** (`knowledge_brain/`) | ✅ Active | ~50 KB per module | Business rules, gotchas, danger zones |

> [!CAUTION]
> LCA and RAG are disabled in `.sdlc/config.json` (`"lca": false, "rag": false`). We're doing bug investigation with one arm tied behind our back.

---

## What LCA Already Has (and we're ignoring)

The 133 MB `index.json` contains:

| Data Type | Count | What It Answers |
|---|---|---|
| **Functions** | 44,634 | "What functions exist in billing model?" → instant structured answer |
| **Call graph** | 226,430 edges | "Who calls `get_billing_list()`?" → direct lookup |
| **DB operations** | 14,112 | "Which functions write to `ret_billing` table?" → filtered query |
| **Formulas** | 22,042 | "What calculations happen in this function?" → extracted SQL/PHP |
| **Conditionals** | 57,704 | "What if/switch logic exists?" → branching analysis |
| **Magic numbers** | 82,457 | "What hardcoded values exist?" → risk identification |
| **Level2 graph** | 19,740 nodes | "Full 2-hop call chain from function X" → impact analysis |

### LCA MCP Tools (already built, ready to use):

| Tool | What It Does | Use Case |
|---|---|---|
| `lca_search` (functions) | Exact name lookup in index | "Find all functions named *opening*" |
| `lca_search` (semantic) | AI embedding search | "Find code related to discount calculation" |
| `lca_investigate` (callers) | Who calls function X | Impact analysis |
| `lca_investigate` (callees) | What does function X call | Data flow tracing |
| `lca_investigate` (table_usage) | All functions touching table Y | Cross-module dependency |
| `lca_investigate` (formulas) | Math/SQL in function X | Calculation verification |
| `lca_investigate` (ajax_endpoints) | All AJAX routes | Test URL discovery |
| `lca_investigate` (explain) | Business flow narrative | Plain-English explanation |
| `lca_pipeline` (gen_test) | Auto-generate regression test | Test creation |
| `lca_pipeline` (review) | Code review with brain checks | Quality gate |
| `lca_pipeline` (risk_score) | Change risk assessment | Prioritization |

---

## What Module Brains Add (that LCA can't)

LCA knows **structure**. Brains know **meaning**.

| Question | LCA Answer | Brain Answer |
|---|---|---|
| "What functions exist in billing?" | ✅ 1,290 functions listed | ❌ Not its job |
| "Which functions are high-risk?" | ⚠️ Can flag by complexity | ✅ Knows from experience |
| "What's the business rule for GST?" | ❌ Doesn't understand business | ✅ Documents the rule |
| "Why was this done this way?" | ❌ No historical context | ✅ Has the rationale |
| "What's the login selector?" | ❌ Doesn't parse HTML/views | ❌ Not documented either |
| "What test data works?" | ❌ No test awareness | ❌ Not documented either |

---

## What's Missing From Both

Neither LCA nor Brains have:

1. **UI Selectors** — `#billing_search`, `#branch_id`, etc. (needed for Playwright tests)
2. **Test Data** — known-good records, valid date ranges, expected values
3. **AJAX Request/Response Schemas** — what params go in, what JSON comes back
4. **Page-to-Controller Mapping** — which URL loads which controller method

---

## The Unified Strategy

### Step 1: Enable LCA and RAG (5 minutes)

```json
// .sdlc/config.json
"discovery_sources": {
  "lca": true,      // ← ENABLE
  "rag": true,      // ← ENABLE
  "recipes": true,
  "call_tree": true,
  "knowledge_brain": true
}
```

This alone makes the `discover` step dramatically better — it was already generating call trees but not using LCA's 44K function index or semantic search.

### Step 2: Use LCA as MODULE_INDEX.json (0 effort)

Instead of building `MODULE_INDEX.json` from scratch (my earlier proposal), we **query LCA on-demand**:

```
Agent needs billing functions → lca_search(action="functions", query="ret_billing", module="billing")
Agent needs table dependencies → lca_investigate(action="table_usage", table_name="ret_billing")  
Agent needs call chain → lca_investigate(action="chain", function_name="save_billing")
```

No new JSON file needed. LCA already has this data indexed and queryable via MCP.

### Step 3: Keep Module Brains for Business Context Only

Slim down brains to what LCA CAN'T provide:

```
knowledge_brain/Billing/
  MODULE_BRAIN.md        ← Business rules, gotchas, danger zones (KEEP)
  UI_SELECTORS.json      ← NEW: page element IDs for tests
  TEST_DATA.yml          ← NEW: known-good test data
```

Remove from brains anything LCA already knows:
- ~~Function lists~~ (LCA has 44,634)
- ~~Table schemas~~ (LCA has 14,112 DB ops)
- ~~Call graphs~~ (LCA has 226,430 edges)
- ~~Formula extractions~~ (LCA has 22,042)

### Step 4: Wire LCA Into Subagent Prompts

Update `code_express.md` and `code.md`:

```markdown
## Code Intelligence — Use LCA MCP Tools
Before modifying any function, query LCA:
- `lca_investigate(action="callers", function_name="X")` — check who calls it
- `lca_investigate(action="formulas", function_name="X")` — check calculations  
- `lca_search(action="semantic", query="what you're fixing")` — find related code
```

### Step 5: Auto-Refresh Brain Staleness

LCA indexes have timestamps (`indexed_at: 2026-06-18`). Module Brains have no timestamps. Add a check:

```python
# cli.py: brain-check command
# Compare: LCA last indexed vs. Brain last modified
# If LCA found new functions in billing since brain was written → flag stale
```

---

## Before vs. After

| Scenario | Today (LCA disabled) | After (LCA enabled + slim brains) |
|---|---|---|
| Bug in billing model | Read 500-line MODULE_BRAIN.md, grep source files | `lca_search` → instant function list, `lca_investigate` → call chain |
| Impact analysis | Manually grep 15 controllers | `lca_investigate(callers)` → complete caller list in seconds |
| "Which modules use this table?" | Read 5 brain files | `lca_investigate(table_usage)` → all functions touching the table |
| Generate test | Guess selectors, discover data | `UI_SELECTORS.json` + `TEST_DATA.yml` → correct first time |
| "Is this brain outdated?" | No way to know | Compare LCA timestamp vs brain timestamp |

---

## Action Plan

| Step | What | Effort | Impact |
|---|---|---|---|
| 1 | Enable `lca: true` and `rag: true` in config.json | 1 min | Discovery step gets 44K functions + semantic search |
| 2 | Re-index LCA (it's 5 days old) | 5 min | Fresh data |
| 3 | Add LCA tool instructions to code/verify prompts | 15 min | Subagents use LCA instead of blind grepping |
| 4 | Build `UI_SELECTORS.json` for P0 modules | 2 hours | Tests get correct selectors |
| 5 | Build `TEST_DATA.yml` for P0 modules | 30 min | Tests get valid data |
| 6 | Slim down MODULE_BRAIN.md (remove LCA-duplicated content) | 1 hour | Brains become focused and maintainable |

> [!IMPORTANT]
> **Step 1 is a 1-minute config change that immediately improves every future bug fix.** The other steps are optimizations on top of that.

## Open Questions

1. **Why were LCA and RAG disabled?** Was there a reliability issue, a performance problem, or was it just never enabled? This matters because if they were disabled for a reason, we need to fix that reason before enabling.
2. **Is the RAG embedding store populated?** LCA has data, but the semantic search needs embeddings. Are they built?
3. **Do you want me to enable them now and run a test discovery?**
