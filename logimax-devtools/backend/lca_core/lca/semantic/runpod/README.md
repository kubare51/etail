# LCA Semantic — RunPod GPU Embedding

Build the semantic search index in minutes using a RunPod GPU pod.

## Why RunPod?

| Method | Time | Cost | Quality |
|--------|------|------|---------|
| Local CPU (i5-1135G7) | ~21 hours | $0 | nomic-v1.5 (768d) |
| Gemini API (free tier) | ~2.7 hours | $0 | gemini-001 (768d) |
| **RunPod GPU (A5000)** | **~5 min** | **$0.09** | **nomic-embed-code (7B)** |

## Quick Start (15 min total)

### Step 1: Export chunks locally (1 min)

```powershell
cd c:\xampp\htdocs\etail_v3\logimax-devtools\backend\lca_core\lca\semantic\runpod
python chunk_export.py --source C:\xampp\htdocs\etail_v3 --output chunks.jsonl
```

Output: `chunks.jsonl` (~50 MB, ~28K chunks)

### Step 2: Create RunPod GPU Pod (2 min)

1. Go to [runpod.io](https://runpod.io) → Sign up → Add $5 credit
2. **GPU Pods** → **Deploy** → Pick **RTX A5000** ($0.27/hr)
3. Template: **RunPod PyTorch** (Python + CUDA pre-installed)
4. Container disk: **20 GB** (default is fine)
5. Volume: **None** (skip network volume)
6. Deploy → Wait for **Running** status → Click **Connect** → **SSH**

### Step 3: Setup the pod (3 min)

```bash
# SSH into the pod (use the SSH command from RunPod dashboard)
ssh root@<pod-ip> -p <port> -i ~/.ssh/id_ed25519

# Upload scripts
scp -P <port> setup_pod.sh embed_remote.py root@<pod-ip>:~/
scp -P <port> chunks.jsonl root@<pod-ip>:~/data/input/

# Run setup (installs deps + downloads model)
bash setup_pod.sh
```

### Step 4: Run embedding (3-5 min)

```bash
python3 embed_remote.py --rebuild
```

Expected output:
```
🖥️  GPU: NVIDIA RTX A5000 (24 GB VRAM)
📂 Loaded 28,095 chunks
📊 Speed: 0.0008s/chunk (1,250 chunks/sec)
📐 Estimated: 0.4 min for 28,095 chunks
⏳ 28,095/28,095 (100%) | 1,250 chunks/sec | ~0.0 min left
✅ Done! 28,095 chunks in 0.4 min
💾 Size: 487 MB
```

### Step 5: Download index to laptop (2 min)

```powershell
# From your laptop:
scp -r -P <port> root@<pod-ip>:~/data/output/chroma_db/ C:\Users\admin\.logimax\rag_store\
```

### Step 6: Terminate the pod

Go to RunPod dashboard → **Stop** → **Terminate** the pod.

**Total cost: ~$0.09** (15 min × $0.27/hr)

## Model Details

| Property | Value |
|----------|-------|
| Model | `nomic-ai/nomic-embed-code` |
| Parameters | 7B |
| Dimensions | 768 |
| Context | 8,192 tokens |
| Trained on | CodeSearchNet (Python, Java, Ruby, PHP, JS, Go) |
| VRAM needed | ~14 GB |

## Local Querying After Build

After downloading the index, queries use the **same model locally** via Ollama (GGUF quantized):

```bash
# Install ollama (one-time)
# Then pull the quantized model:
ollama pull nomic-embed-code
```

Or configure `store.py` to use the RunPod backend for queries (sends single query to a serverless endpoint).

## File Structure

```
runpod/
├── README.md           ← This file
├── setup_pod.sh        ← One-time pod setup (installs deps)
├── embed_remote.py     ← GPU embedding script
└── chunk_export.py     ← Local chunk export (runs on laptop)
```

## Troubleshooting

**"Out of VRAM"**: Reduce batch size: `python3 embed_remote.py --batch-size 64`

**"Model download slow"**: RunPod has fast internet (~1 Gbps). If slow, the HuggingFace CDN may be throttled. Set `HF_TOKEN` env var.

**"SCP too slow"**: Use `rsync` with compression: `rsync -avz -e "ssh -p <port>" root@<pod-ip>:~/data/output/chroma_db/ ~/.logimax/rag_store/`
