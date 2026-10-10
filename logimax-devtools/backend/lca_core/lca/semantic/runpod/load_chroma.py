#!/usr/bin/env python3
"""
Load pre-computed embeddings into ChromaDB — runs locally (CPU-only, free).

Reads numpy vectors from embed_gpu_only.py output and texts from the
local chunks.jsonl (not duplicated from RunPod — saves ~90 MB download).

Usage:
    python load_chroma.py \\
        --embeddings ~/.logimax/embeddings/ \\
        --chunks path/to/chunks.jsonl \\
        --output ~/.logimax/rag_store/

Architecture (split GPU/CPU):
  RunPod GPU: chunks.jsonl → embed_gpu_only.py → vectors.npy  ($0.49/hr)
  Local CPU:  vectors.npy + chunks.jsonl → load_chroma.py → ChromaDB  (free)
"""
import argparse
import json
import os
import sys
import time

import numpy as np


def main():
    parser = argparse.ArgumentParser(description='Load embeddings into ChromaDB (CPU-only)')
    parser.add_argument('--embeddings', required=True,
                        help='Directory with vectors.npy, ids.json, metadatas.json from RunPod')
    parser.add_argument('--chunks', required=True,
                        help='Local chunks.jsonl file (for document texts)')
    parser.add_argument('--output', default=os.path.join(os.path.expanduser('~'), '.logimax', 'rag_store'),
                        help='ChromaDB store directory')
    parser.add_argument('--batch-size', type=int, default=500,
                        help='Upsert batch size (500 is safe for CPU)')
    parser.add_argument('--rebuild', action='store_true',
                        help='Delete existing store and rebuild')
    parser.add_argument('--collection', default='codebase')
    args = parser.parse_args()

    print("╔══════════════════════════════════════════════════════════╗")
    print("║  LCA Semantic — Load Embeddings into ChromaDB          ║")
    print("║  (CPU-only, free)                                      ║")
    print("╚══════════════════════════════════════════════════════════╝")

    # ── Verify inputs ────────────────────────────────────────────────────
    for f in ['vectors.npy', 'ids.json', 'metadatas.json', 'manifest.json']:
        path = os.path.join(args.embeddings, f)
        if not os.path.exists(path):
            print(f"  ❌ Missing: {path}")
            sys.exit(1)

    if not os.path.exists(args.chunks):
        print(f"  ❌ Missing chunks file: {args.chunks}")
        sys.exit(1)

    # Read manifest
    with open(os.path.join(args.embeddings, 'manifest.json')) as f:
        manifest = json.load(f)
    print(f"  📋 Model: {manifest.get('model')} | Dims: {manifest.get('dimensions')} | "
          f"Chunks: {manifest.get('chunks_embedded'):,}")

    # ── Load vectors ─────────────────────────────────────────────────────
    print(f"\n  📂 Loading vectors from {args.embeddings}...")
    vectors = np.load(os.path.join(args.embeddings, 'vectors.npy'))
    print(f"     Shape: {vectors.shape}")

    with open(os.path.join(args.embeddings, 'ids.json')) as f:
        ids = json.load(f)

    with open(os.path.join(args.embeddings, 'metadatas.json')) as f:
        metadatas = json.load(f)

    # ── Load texts from local chunks.jsonl ───────────────────────────────
    print(f"  📂 Loading texts from {args.chunks}...")
    texts = []
    chunk_ids_from_file = []
    with open(args.chunks, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if line:
                chunk = json.loads(line)
                texts.append(chunk['text'])
                chunk_ids_from_file.append(chunk['id'])

    # Verify alignment
    if len(ids) != len(texts):
        print(f"  ❌ ID count mismatch: embeddings={len(ids)}, chunks.jsonl={len(texts)}")
        sys.exit(1)

    if ids[:10] != chunk_ids_from_file[:10]:
        print(f"  ⚠️  ID order mismatch! First 3 from embeddings: {ids[:3]}")
        print(f"  ⚠️  First 3 from chunks.jsonl: {chunk_ids_from_file[:3]}")
        print(f"  ⚠️  Building ID→text lookup (slower but safe)...")
        # Fall back to ID-based lookup
        text_by_id = {cid: txt for cid, txt in zip(chunk_ids_from_file, texts)}
        texts = [text_by_id.get(cid, '') for cid in ids]
        missing = sum(1 for t in texts if not t)
        if missing > 0:
            print(f"  ⚠️  {missing} IDs not found in chunks.jsonl")

    assert len(ids) == len(texts) == len(metadatas) == vectors.shape[0], \
        f"Size mismatch: ids={len(ids)}, texts={len(texts)}, meta={len(metadatas)}, vectors={vectors.shape[0]}"

    print(f"  ✅ {len(ids):,} chunks aligned and ready")

    # ── Setup ChromaDB ───────────────────────────────────────────────────
    import chromadb
    from chromadb.config import Settings

    if args.rebuild and os.path.exists(args.output):
        import shutil
        print(f"\n  🗑️  Deleting existing store: {args.output}")
        shutil.rmtree(args.output)

    os.makedirs(args.output, exist_ok=True)
    client = chromadb.PersistentClient(
        path=args.output,
        settings=Settings(anonymized_telemetry=False)
    )
    collection = client.get_or_create_collection(
        name=args.collection,
        metadata={"hnsw:space": "cosine"},
    )
    print(f"  ✅ ChromaDB ready: {args.output}")

    # ── Upsert in batches ────────────────────────────────────────────────
    total = len(ids)
    print(f"\n  📥 Loading {total:,} chunks into ChromaDB...")
    t_start = time.time()

    for i in range(0, total, args.batch_size):
        end = min(i + args.batch_size, total)
        collection.upsert(
            ids=ids[i:end],
            embeddings=vectors[i:end].tolist(),
            documents=texts[i:end],
            metadatas=metadatas[i:end],
        )
        elapsed = time.time() - t_start
        rate = end / elapsed if elapsed > 0 else 0
        remaining = ((total - end) / rate / 60) if rate > 0 else 0
        print(f"  ⏳ {end:,}/{total:,} ({end*100//total}%) | "
              f"{rate:.0f} chunks/sec | ~{remaining:.1f} min left")

    elapsed_total = time.time() - t_start

    # Copy manifest to store
    import shutil
    shutil.copy2(
        os.path.join(args.embeddings, 'manifest.json'),
        os.path.join(args.output, 'manifest.json')
    )

    # ── Verify ───────────────────────────────────────────────────────────
    count = collection.count()
    store_size = _dir_size_mb(args.output)

    print(f"\n╔══════════════════════════════════════════════════════════╗")
    print(f"║  ✅ Done! {count:,} chunks loaded in {elapsed_total/60:.1f} min")
    print(f"║  📂 Store: {args.output}")
    print(f"║  💾 Size: {store_size:.0f} MB")
    print(f"║")
    print(f"║  Test: lca semantic \"billing discount calculation\"")
    print(f"╚══════════════════════════════════════════════════════════╝")

    if count != total:
        print(f"\n  ⚠️  WARNING: Expected {total:,} but got {count:,}")
        sys.exit(1)


def _dir_size_mb(path: str) -> float:
    total = 0
    for dirpath, _, filenames in os.walk(path):
        for f in filenames:
            fp = os.path.join(dirpath, f)
            total += os.path.getsize(fp)
    return total / (1024 * 1024)


if __name__ == '__main__':
    main()
