"""
RunPod GPU Embedding Script — LCA Semantic Search

Reads pre-chunked JSONL data, embeds with BAAI/bge-m3 on GPU,
and saves to ChromaDB. Run on a GPU pod (RTX A6000 recommended).

IMPORTANT: The model here MUST match store.py BGE_MODEL for local queries.
Currently: BAAI/bge-m3 (1024 dimensions, cosine similarity)

Usage:
    python3 embed_remote.py [--input chunks.jsonl] [--output ./chroma_db] [--batch-size 256]

Expected time: ~3-5 min for 28K chunks on A5000
"""
import argparse
import json
import os
import shutil
import sys
import time

import torch


def main():
    parser = argparse.ArgumentParser(description='Embed code chunks on GPU')
    parser.add_argument('--input', default=os.path.expanduser('~/data/input/chunks.jsonl'),
                        help='Path to chunks JSONL file')
    parser.add_argument('--output', default=os.path.expanduser('~/data/output/chroma_db'),
                        help='Output directory for ChromaDB')
    parser.add_argument('--batch-size', type=int, default=256,
                        help='Encoding batch size (256 for 24GB VRAM)')
    parser.add_argument('--model', default='BAAI/bge-m3',
                        help='Embedding model name (must match store.py BGE_MODEL)')
    parser.add_argument('--rebuild', action='store_true',
                        help='Delete existing index and rebuild from scratch')
    args = parser.parse_args()

    print("╔══════════════════════════════════════════════════════════╗")
    print("║  LCA Semantic — GPU Embedding                          ║")
    print("╚══════════════════════════════════════════════════════════╝")

    # ── Check GPU ─────────────────────────────────────────────────────────
    if torch.cuda.is_available():
        gpu = torch.cuda.get_device_name(0)
        vram = torch.cuda.get_device_properties(0).total_memory / (1024 ** 3)
        print(f"  🖥️  GPU: {gpu} ({vram:.0f} GB VRAM)")
    else:
        print("  ⚠️  No GPU detected — running on CPU (will be slow)")

    # ── Load chunks ───────────────────────────────────────────────────────
    print(f"\n  📂 Loading chunks from: {args.input}")
    if not os.path.exists(args.input):
        print(f"  ❌ File not found: {args.input}")
        sys.exit(1)

    chunks = []
    with open(args.input, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if line:
                chunks.append(json.loads(line))

    print(f"  ✅ Loaded {len(chunks):,} chunks")

    # ── Load model ────────────────────────────────────────────────────────
    print(f"\n  🔧 Loading model: {args.model}")
    t0 = time.time()
    from sentence_transformers import SentenceTransformer
    model = SentenceTransformer(args.model, trust_remote_code=True)

    # Move to GPU if available
    if torch.cuda.is_available():
        model = model.to(torch.device('cuda'))

    print(f"  ✅ Model loaded in {time.time() - t0:.1f}s")

    # ── Pre-benchmark ─────────────────────────────────────────────────────
    print(f"\n  📊 Benchmarking...")
    sample = [c['text'] for c in chunks[:50]]
    t0 = time.time()
    model.encode(sample, show_progress_bar=False, normalize_embeddings=True,
                 batch_size=args.batch_size)
    bench = time.time() - t0
    per_chunk = bench / len(sample)
    est_min = (per_chunk * len(chunks)) / 60
    print(f"  ⏱️  Speed: {per_chunk:.4f}s/chunk ({1/per_chunk:.0f} chunks/sec)")
    print(f"  📐 Estimated: {est_min:.1f} min for {len(chunks):,} chunks")
    print(f"  💡 Batch size: {args.batch_size} (512 recommended for 48GB VRAM)")

    # ── Setup ChromaDB ────────────────────────────────────────────────────
    if args.rebuild and os.path.exists(args.output):
        print(f"\n  🗑️  Deleting existing index: {args.output}")
        shutil.rmtree(args.output)

    import chromadb
    from chromadb.config import Settings
    os.makedirs(args.output, exist_ok=True)
    client = chromadb.PersistentClient(
        path=args.output,
        settings=Settings(anonymized_telemetry=False)
    )
    collection = client.get_or_create_collection(
        name="codebase",
        metadata={"hnsw:space": "cosine"},
    )

    # ── Embed + Store ─────────────────────────────────────────────────────
    print(f"\n  🚀 Embedding {len(chunks):,} chunks...")
    stored = 0
    total = len(chunks)
    t_start = time.time()

    for i in range(0, total, args.batch_size):
        batch = chunks[i:i + args.batch_size]

        ids = [c['id'] for c in batch]
        texts = [c['text'] for c in batch]
        metadatas = [c['metadata'] for c in batch]

        # GPU-accelerated encoding
        embeddings = model.encode(
            texts,
            show_progress_bar=False,
            normalize_embeddings=True,
            batch_size=args.batch_size,
        ).tolist()

        # Upsert to ChromaDB
        collection.upsert(
            ids=ids,
            embeddings=embeddings,
            documents=texts,
            metadatas=metadatas,
        )
        stored += len(batch)

        # Progress
        elapsed = time.time() - t_start
        pct = (stored / total) * 100
        rate = stored / elapsed if elapsed > 0 else 0
        remaining = ((total - stored) / rate / 60) if rate > 0 else 0
        print(f"  ⏳ {stored:,}/{total:,} ({pct:.0f}%) | "
              f"{rate:.0f} chunks/sec | ~{remaining:.1f} min left")

    elapsed_total = time.time() - t_start

    # ── Write manifest (version tracking) ─────────────────────────────────
    import chromadb as _chromadb
    from datetime import datetime
    manifest = {
        'model': args.model,
        'chromadb_version': _chromadb.__version__,
        'chunks_embedded': stored,
        'dimensions': len(embeddings[0]) if embeddings else 0,
        'created_at': datetime.now().isoformat(),
        'runpod_gpu': torch.cuda.get_device_name(0) if torch.cuda.is_available() else 'CPU',
        'collection_name': 'codebase',
        'input_file': args.input,
    }
    manifest_path = os.path.join(args.output, 'manifest.json')
    with open(manifest_path, 'w') as f:
        json.dump(manifest, f, indent=2)
    print(f"\n  📋 Manifest written: {manifest_path}")

    # ── Summary ───────────────────────────────────────────────────────────
    print()
    print("╔══════════════════════════════════════════════════════════╗")
    print(f"║  ✅ Done! {stored:,} chunks in {elapsed_total/60:.1f} min              ║")
    print(f"║  📂 Index: {args.output}")
    print(f"║  💾 Size: {_dir_size_mb(args.output):.0f} MB")
    print(f"║  🔧 Model: {args.model}")
    print(f"║  📐 Dims: {manifest['dimensions']}")
    print("║                                                         ║")
    print("║  Download to laptop:                                    ║")
    print("║  scp -r runpod:~/data/output/chroma_db/ \\               ║")
    print("║      ~/.logimax/rag_store/                              ║")
    print("╚══════════════════════════════════════════════════════════╝")

    # ── Quick query test ──────────────────────────────────────────────────
    print("\n  🔍 Quick query test...")
    test_queries = [
        "billing discount calculation",
        "stock transfer between godowns",
        "customer payment processing",
    ]
    for q in test_queries:
        q_emb = model.encode([q], normalize_embeddings=True).tolist()
        results = collection.query(
            query_embeddings=q_emb,
            n_results=3,
            include=["metadatas", "distances"],
        )
        print(f"\n  Q: \"{q}\"")
        if results['ids'][0]:
            for j in range(len(results['ids'][0])):
                meta = results['metadatas'][0][j]
                dist = results['distances'][0][j]
                print(f"    [{dist:.3f}] {meta.get('file_path', '?')} :: "
                      f"{meta.get('method_name', '?')}")


def _dir_size_mb(path):
    """Get directory size in MB."""
    total = 0
    for dirpath, _, filenames in os.walk(path):
        for f in filenames:
            fp = os.path.join(dirpath, f)
            total += os.path.getsize(fp)
    return total / (1024 * 1024)


if __name__ == '__main__':
    main()
