#!/bin/bash
# ─────────────────────────────────────────────────────────────────────────────
# RunPod GPU Pod Setup — LCA Semantic Embedding
# Run this ONCE after creating a new pod.
# Template: PyTorch (has Python + CUDA pre-installed)
# ─────────────────────────────────────────────────────────────────────────────

set -e

echo "╔══════════════════════════════════════════════════════════╗"
echo "║  LCA Semantic — RunPod Setup                            ║"
echo "╚══════════════════════════════════════════════════════════╝"

# 1. Install dependencies
echo ""
echo "📦 Installing Python packages..."
pip install --quiet --break-system-packages sentence-transformers==5.5.1 chromadb==1.5.9 psutil

# 2. Pre-download the embedding model (caches for subsequent runs)
echo ""
echo "🔽 Pre-downloading BGE-M3 embedding model..."
python3 -c "
from sentence_transformers import SentenceTransformer
import time
t0 = time.time()
model = SentenceTransformer('BAAI/bge-m3', trust_remote_code=True)
elapsed = time.time() - t0
print(f'  ✅ Model downloaded and loaded in {elapsed:.1f}s')
# Quick sanity check
emb = model.encode(['test function hello world'], normalize_embeddings=True)
print(f'  ✅ Embedding shape: {emb.shape} (should be [1, 1024])')
import chromadb
print(f'  ✅ ChromaDB version: {chromadb.__version__} (must be 1.5.9)')
import sentence_transformers
print(f'  ✅ sentence-transformers: {sentence_transformers.__version__}')
print(f'  ✅ GPU available: {__import__(\"torch\").cuda.is_available()}')
if __import__('torch').cuda.is_available():
    print(f'  ✅ GPU: {__import__(\"torch\").cuda.get_device_name(0)}')
    vram = __import__('torch').cuda.get_device_properties(0).total_memory / 1e9
    print(f'  ✅ VRAM: {vram:.0f} GB')
"

# 3. Create working directories
mkdir -p ~/data/input ~/data/output

echo ""
echo "╔══════════════════════════════════════════════════════════╗"
echo "║  ✅ Setup complete!                                     ║"
echo "║                                                         ║"
echo "║  Next steps:                                            ║"
echo "║  1. SCP your chunks.jsonl to ~/data/input/              ║"
echo "║  2. Run: python3 embed_remote.py                        ║"
echo "║  3. SCP ~/data/output/chroma_db/ back to your laptop    ║"
echo "╚══════════════════════════════════════════════════════════╝"
