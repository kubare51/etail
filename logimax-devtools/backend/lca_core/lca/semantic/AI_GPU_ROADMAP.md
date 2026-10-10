# AI GPU Server Roadmap — Logimax

> **Hardware**: RunPod RTX A5000 (24 GB VRAM, $0.27/hr)
> **Storage**: 30 GB Network Volume ($2.10/month)
> **Budget**: ~₹300-500/month total
> **Last Updated**: 2026-06-11

---

## Infrastructure Setup

### RunPod Configuration

| Setting | Value |
|---------|-------|
| Provider | [RunPod](https://console.runpod.io) |
| GPU | RTX A5000 (24 GB VRAM) |
| Template | Runpod Pytorch 2.8.0 |
| Container Disk | 20 GB |
| Network Volume | 30 GB ($0.07/GB/month = $2.10/month) |
| Region | Any (cheapest available) |
| Billing | Pay-per-second (no subscription) |
| SSH | Required — key-based access |

### Cost Structure

| Component | Cost | Frequency |
|-----------|------|-----------|
| Network volume (30 GB) | $2.10/month (~₹180) | Always (even when pod stopped) |
| GPU pod (A5000) | $0.27/hr | Only when running |
| Stopped pod | $0.014/hr | When paused (keeps container disk) |

### Monthly Estimate by Usage

| Usage Level | GPU Hours | GPU Cost | Volume | Total/Month |
|-------------|-----------|----------|--------|-------------|
| Light (embedding only) | 2 hrs | $0.54 | $2.10 | $2.64 (~₹225) |
| Medium (embed + fine-tune) | 6 hrs | $1.62 | $2.10 | $3.72 (~₹320) |
| Heavy (embed + fine-tune + LLM) | 15 hrs | $4.05 | $2.10 | $6.15 (~₹525) |

---

## Roadmap Phases

### Phase 1: Code Embeddings (Week 1) ⭐ PRIORITY

**Goal**: Build semantic search index for LCA tool in minutes instead of hours.

| Item | Details |
|------|---------|
| Model | `nomic-ai/nomic-embed-code` (7B params, 768 dimensions) |
| Input | 60,502 code chunks from etail_v3 codebase |
| Output | ChromaDB vector index (~500 MB) |
| Time | ~5 min on A5000 |
| Cost | ~$0.03 per rebuild |
| Frequency | Weekly, or after major code changes |

**Scripts ready**: `runpod/setup_pod.sh`, `runpod/embed_remote.py`, `runpod/chunk_export.py`

**Workflow**:
```
LOCAL:  python chunk_export.py → chunks.jsonl (90 MB)
UPLOAD: scp chunks.jsonl → RunPod
GPU:    python embed_remote.py --rebuild (5 min)
DOWNLOAD: scp chroma_db/ → laptop (~500 MB)
```

**Quality vs Alternatives**:

| Backend | Model | Quality | Speed | Cost | Offline Query |
|---------|-------|---------|-------|------|---------------|
| Local CPU | nomic-v1.5 | ⭐⭐⭐ | 21 hrs | $0 | ✅ |
| Gemini API | gemini-embedding-001 | ⭐⭐⭐ | 2.7 hrs | $0 | ❌ |
| **RunPod GPU** | **nomic-embed-code** | **⭐⭐⭐⭐⭐** | **5 min** | **$0.03** | **✅** |

---

### Phase 2: ONNX Export (Week 2)

**Goal**: Convert embedding model to ONNX for 3x faster local CPU queries.

| Item | Details |
|------|---------|
| Input | nomic-embed-code PyTorch model |
| Output | ONNX model file (~500 MB) |
| Tool | `optimum` library (HuggingFace) |
| Time | 10 min on GPU |
| Cost | $0.05 (one-time) |
| Benefit | Query speed on i5 laptop: ~0.5s → ~0.15s |

**Script** (run on RunPod):
```python
from optimum.onnxruntime import ORTModelForFeatureExtraction
model = ORTModelForFeatureExtraction.from_pretrained(
    "nomic-ai/nomic-embed-code",
    export=True,
    provider="CUDAExecutionProvider"
)
model.save_pretrained("./nomic-embed-code-onnx")
```

**After export**: Download ONNX file to laptop, update `store.py` to use ONNX runtime for queries.

---

### Phase 3: Fine-Tune Embedding Model (Month 2)

**Goal**: Train the embedding model on Logimax-specific code patterns for dramatically better search accuracy.

| Item | Details |
|------|---------|
| Method | LoRA fine-tuning (4-bit quantized) |
| Base Model | nomic-embed-code (7B) |
| Training Data | ~500 query-code pairs from knowledge brain |
| Output | LoRA adapter (~50-100 MB) |
| Time | 2-3 hrs on A5000 |
| Cost | $0.80 |
| Quality Boost | Search accuracy ~70% → ~90%+ |

**Why it matters**:
```
Generic model doesn't understand:
  "estimation" → pre-billing quote (not math estimation)
  "godown" → warehouse location (not action)
  "scheme" → discount plan (not database schema)
  "MRP" → maximum retail price (not manufacturing resource planning)
  "$this->db->where()" → CodeIgniter active record query

Fine-tuned model learns YOUR domain vocabulary.
```

**Training Data Sources**:
- Module brain documents (query → relevant code mappings)
- Bug fix recipes (symptom → code location)
- MCP tool usage logs (user queries → matched results)

**Storage**: Push LoRA adapter to HuggingFace private repo (free, permanent).

---

### Phase 4: Custom Code Copilot (Month 3)

**Goal**: Fine-tune a code LLM that writes code in your CodeIgniter 3 style.

| Item | Details |
|------|---------|
| Method | QLoRA fine-tuning |
| Base Model | DeepSeek Coder V2 Lite (16B, 4-bit) or CodeLlama 7B |
| Training Data | Your existing models, controllers, bug fixes |
| Output | LoRA adapter (~100-200 MB) |
| Time | 4-6 hrs on A5000 |
| Cost | $1.60 |
| VRAM Usage | ~20 GB (fits in A5000's 24 GB) |

**Use Cases**:
- "Write a billing model method for GST calculation"
- "Generate the controller for stock transfer between godowns"
- "Create the AJAX handler for estimation item delete"
- Auto-generate code review suggestions in your project's style

**Training Data**:
```
Input:  "Write a model method to get customer discount by scheme"
Output: function get_scheme_discount($customer_id, $scheme_id) {
            $this->db->select('discount_percent, discount_amount');
            $this->db->from('ret_scheme_customer');
            $this->db->where('customer_id', $customer_id);
            ...
        }
```

---

### Phase 5: Auto Module Brain Builder (Month 3-4)

**Goal**: Use a code LLM to automatically generate/update module brain documents from source code.

| Item | Details |
|------|---------|
| Model | DeepSeek Coder V2 Lite or Phi-3 (3.8B) |
| Input | Source code files + existing brain templates |
| Output | MODULE_BRAIN.md, ACTIVE_BUGS.md, etc. |
| Time | ~1 hr per module |
| Cost | $0.27 per module |

**Workflow**:
```
1. Feed model: all controllers + models + views for a module
2. Prompt: "Generate MODULE_BRAIN.md following this template: ..."
3. Output: structured documentation with business rules, table maps, etc.
4. Human review: verify accuracy, fill gaps
```

**ROI**: Currently takes 2-4 hours to manually build a module brain. Auto-generation cuts it to 20 min of review.

---

### Phase 6: Bug Prediction Model (Month 4-5)

**Goal**: Predict which files are likely to have bugs based on code patterns.

| Item | Details |
|------|---------|
| Method | Classification model (BERT or gradient boosting) |
| Features | Code complexity, change frequency, test coverage, dependency count |
| Training Data | Git history + bug fix records |
| Output | Risk score per file (0.0-1.0) |
| Time | 1-2 hrs training |
| Cost | $0.54 |

**Integration with LCA**:
```bash
lca risk-predict --module billing
# Output:
# HIGH RISK (0.92): admin_billing_model.php — 47 changes, 12 bug fixes, high complexity
# MED RISK (0.65): billing_controller.php — 23 changes, 5 bug fixes
# LOW RISK (0.15): billing_helper.php — 3 changes, 0 bug fixes
```

---

### Phase 7: Text-to-SQL (Month 5-6)

**Goal**: Natural language queries against your database.

| Item | Details |
|------|---------|
| Model | SQLCoder 7B (fine-tuned on SQL generation) |
| Input | "Show me all unpaid invoices for customer X from last month" |
| Output | SQL query + results |
| VRAM | ~16 GB (fits in A5000) |
| Cost | $0.27/hr while running |

**Safety**: Read-only queries only. Generated SQL goes through validation before execution.

---

## Capability Matrix — RTX A5000 (24 GB VRAM)

### ✅ Comfortable (Under 20 GB VRAM)

| Model/Task | Size | VRAM | Use Case |
|------------|------|------|----------|
| nomic-embed-code | 7B | 14 GB | Code embeddings |
| Phi-3 Mini | 3.8B | 8 GB | Code summarization |
| DeepSeek Coder V2 Lite | 16B (4-bit) | 10 GB | Code generation |
| CodeLlama 7B | 7B | 14 GB | Code completion |
| Llama 3.1 8B | 8B | 16 GB | General assistant |
| Whisper Large V3 | 1.5B | 6 GB | Speech-to-text |
| Stable Diffusion XL | 6.6B | 12 GB | Image generation |
| SQLCoder 7B | 7B | 14 GB | Text-to-SQL |
| BERT/DistilBERT | 110M-340M | 2 GB | Classification |

### ⚠️ Tight (20-24 GB VRAM)

| Model/Task | Size | VRAM | Notes |
|------------|------|------|-------|
| CodeLlama 13B (4-bit) | 13B | 20 GB | Batch size 1 |
| Mixtral 8x7B (4-bit) | 46B | 24 GB | MoE, fits barely |
| Fine-tune 7B (QLoRA) | 7B | 20 GB | Gradient checkpointing needed |

### ❌ Won't Fit

| Model | Size | Needs |
|-------|------|-------|
| Llama 3.1 70B (full) | 70B | 140 GB |
| GPT-4 class | 1.8T | Cluster |
| Training from scratch | Any | Multi-GPU |

---

## Data Privacy & Security

| Concern | Mitigation |
|---------|-----------|
| Source code on RunPod | Pod is isolated VM, SSH-only access, terminated after use |
| Network transit | SCP (SSH encrypted), no plain HTTP |
| Model storage | HuggingFace private repos or downloaded to laptop |
| API key exposure | Use RunPod Secrets manager or env vars |
| Data persistence | Network volume encrypted at rest |
| Multi-tenancy | GPU pod = dedicated VM, not shared |

**Rule**: Never leave source code on RunPod. Upload chunks (derived text), not raw .php files. Download results, terminate.

---

## Key Decisions Log

| Date | Decision | Rationale |
|------|----------|-----------|
| 2026-06-11 | RunPod over AWS/GCP | Pay-per-second, no commitment, cheapest A5000 |
| 2026-06-11 | A5000 over A4000/3090 | 24 GB VRAM covers all planned tasks |
| 2026-06-11 | 30 GB network volume | Model cache + fine-tune storage, ₹180/month |
| 2026-06-11 | nomic-embed-code over nomic-v1.5 | Code-specific training, PHP/JS/SQL aware |
| 2026-06-11 | LoRA over full fine-tune | 50 MB adapter vs 14 GB model, same quality |
| 2026-06-11 | Gemini API as fallback | Free tier, works when RunPod is down |

---

## Quick Reference Commands

```bash
# ── Local (your laptop) ──────────────────────────────────────
# Export chunks
cd logimax-devtools/backend/lca_core/lca/semantic/runpod
python chunk_export.py --source C:\xampp\htdocs\etail_v3

# Upload to RunPod
scp -P <port> chunks.jsonl root@<pod-ip>:~/data/input/
scp -P <port> embed_remote.py root@<pod-ip>:~/

# Download index
scp -r -P <port> root@<pod-ip>:~/data/output/chroma_db/ C:\Users\admin\.logimax\rag_store\

# ── RunPod (GPU pod) ─────────────────────────────────────────
# First-time setup
bash setup_pod.sh

# Rebuild index
python3 embed_remote.py --rebuild

# Fine-tune (future)
python3 finetune_embed.py --data training_pairs.jsonl --epochs 3
```

---

## File Reference

```
logimax-devtools/backend/lca_core/lca/semantic/
├── store.py              ← Multi-backend embedding (gemini/local/runpod)
├── chunker.py            ← Code chunking engine
├── models.py             ← CodeChunk data model
├── runpod/
│   ├── README.md         ← Step-by-step RunPod guide
│   ├── setup_pod.sh      ← One-time pod setup script
│   ├── embed_remote.py   ← GPU embedding script
│   ├── chunk_export.py   ← Local chunk export
│   └── chunks.jsonl      ← Exported chunks (gitignored)
└── AI_GPU_ROADMAP.md     ← This document
```
