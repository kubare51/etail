#!/usr/bin/env python3
"""
RunPod GPU Embedding Script — GPU-ONLY (v2, battle-tested)

Embeds chunks using GPU and saves raw vectors to numpy files.
ChromaDB loading happens locally via load_chroma.py (CPU-only, free).

Key features:
  - Sorts chunks by length (reduces padding waste, prevents OOM spikes)
  - Incremental checkpoints (crash recovery)
  - --test flag for quick validation before full run
  - No ChromaDB dependency (GPU-only, pure embedding)
  - No texts.json (use local chunks.jsonl instead — saves ~90 MB download)

Usage:
    # Test with 500 chunks first (ALWAYS do this):
    python3 embed_gpu_only.py --test 500

    # Full run (use nohup!):
    nohup python3 embed_gpu_only.py > ~/embed.log 2>&1 &
    tail -f ~/embed.log

    # Resume after crash:
    python3 embed_gpu_only.py --resume
"""
import argparse
import json
import os
import sys
import time

import numpy as np
import torch
from sentence_transformers import SentenceTransformer


CHECKPOINT_INTERVAL = 1000  # Save checkpoint every N chunks


def main():
    parser = argparse.ArgumentParser(description='GPU-only embedding (no ChromaDB)')
    parser.add_argument('--input', default=os.path.expanduser('~/data/input/chunks.jsonl'))
    parser.add_argument('--output', default=os.path.expanduser('~/data/output/embeddings'))
    parser.add_argument('--batch-size', type=int, default=64,
                        help='Encoding batch size (64 safe for 48GB VRAM with M3)')
    parser.add_argument('--model', default='BAAI/bge-m3')
    parser.add_argument('--test', type=int, default=0,
                        help='Embed only N chunks for quick validation (0 = all)')
    parser.add_argument('--resume', action='store_true',
                        help='Resume from last checkpoint')
    args = parser.parse_args()

    print("╔══════════════════════════════════════════════════════════╗")
    print("║  LCA Semantic — GPU-Only Embedding (v2)                ║")
    print("╚══════════════════════════════════════════════════════════╝")
    sys.stdout.flush()

    # ── GPU Info ──────────────────────────────────────────────────────────
    if torch.cuda.is_available():
        gpu_name = torch.cuda.get_device_name(0)
        vram = torch.cuda.get_device_properties(0).total_memory / 1e9
        print(f"  🖥️  GPU: {gpu_name} ({vram:.0f} GB VRAM)")
    else:
        print("  ⚠️  No GPU — running on CPU (will be slow)")
        gpu_name = "CPU"
    sys.stdout.flush()

    # ── Load chunks ──────────────────────────────────────────────────────
    print(f"\n  📂 Loading: {args.input}")
    chunks = []
    with open(args.input, 'r', encoding='utf-8') as f:
        for line in f:
            line = line.strip()
            if line:
                chunks.append(json.loads(line))

    if args.test > 0:
        chunks = chunks[:args.test]
        print(f"  🧪 TEST MODE: Using first {args.test} chunks only")

    print(f"  ✅ {len(chunks):,} chunks loaded")
    sys.stdout.flush()

    # ── Sort by text length (reduces padding waste, prevents OOM) ────────
    print(f"  🔀 Sorting chunks by length (short → long)...")
    original_order = list(range(len(chunks)))
    sorted_indices = sorted(original_order, key=lambda i: len(chunks[i]['text']))
    chunks_sorted = [chunks[i] for i in sorted_indices]
    
    lengths = [len(c['text']) for c in chunks_sorted]
    print(f"     Shortest: {lengths[0]:,} chars | Longest: {lengths[-1]:,} chars")
    print(f"     Median: {lengths[len(lengths)//2]:,} chars")
    sys.stdout.flush()

    # ── Check for resume ─────────────────────────────────────────────────
    os.makedirs(args.output, exist_ok=True)
    checkpoint_path = os.path.join(args.output, 'checkpoint.json')
    vectors_path = os.path.join(args.output, 'vectors_partial.npy')
    start_idx = 0

    if args.resume and os.path.exists(checkpoint_path):
        with open(checkpoint_path) as f:
            cp = json.load(f)
        start_idx = cp['chunks_done']
        print(f"\n  🔄 RESUMING from checkpoint: {start_idx:,}/{len(chunks_sorted):,} chunks done")
        # Load partial vectors
        if os.path.exists(vectors_path):
            partial = np.load(vectors_path)
            all_vectors = list(partial)
            print(f"     Loaded {len(all_vectors):,} existing vectors")
        else:
            all_vectors = []
            start_idx = 0
    else:
        all_vectors = []
        # Clean output dir for fresh start
        for f in ['vectors_partial.npy', 'checkpoint.json', 'vectors.npy']:
            p = os.path.join(args.output, f)
            if os.path.exists(p):
                os.remove(p)

    sys.stdout.flush()

    # ── Load model ───────────────────────────────────────────────────────
    print(f"\n  🔧 Loading model: {args.model}")
    t0 = time.time()
    model = SentenceTransformer(args.model, trust_remote_code=True)
    if torch.cuda.is_available():
        model = model.to('cuda')
    print(f"  ✅ Model loaded in {time.time() - t0:.1f}s")
    sys.stdout.flush()

    # ── Quick benchmark ──────────────────────────────────────────────────
    sample = [c['text'] for c in chunks_sorted[:min(args.batch_size, len(chunks_sorted))]]
    t0 = time.time()
    test_emb = model.encode(sample, show_progress_bar=False, normalize_embeddings=True,
                            batch_size=args.batch_size)
    bench_time = time.time() - t0
    per_chunk = bench_time / len(sample)
    remaining_chunks = len(chunks_sorted) - start_idx
    est_min = (per_chunk * remaining_chunks) / 60

    print(f"\n  📊 Benchmark (shortest {len(sample)} chunks):")
    print(f"     Speed: {per_chunk:.4f}s/chunk ({1/per_chunk:.0f} chunks/sec)")
    print(f"     Embedding dims: {test_emb.shape[1]}")
    print(f"     Est. remaining: {est_min:.1f} min for {remaining_chunks:,} chunks")
    print(f"     Batch size: {args.batch_size}")
    sys.stdout.flush()

    if args.test > 0 and args.test <= args.batch_size:
        # Test complete — save and exit
        print(f"\n  ✅ TEST PASSED — dims={test_emb.shape[1]}, speed={per_chunk:.4f}s/chunk")
        print(f"  💡 For full run: nohup python3 {sys.argv[0]} > ~/embed.log 2>&1 &")
        return

    # ── Embed in batches with checkpoints ────────────────────────────────
    total = len(chunks_sorted)
    print(f"\n  🚀 Embedding {remaining_chunks:,} chunks (from {start_idx:,})...")
    sys.stdout.flush()
    t_start = time.time()

    for i in range(start_idx, total, args.batch_size):
        batch_texts = [c['text'] for c in chunks_sorted[i:i + args.batch_size]]

        embeddings = model.encode(
            batch_texts,
            show_progress_bar=False,
            normalize_embeddings=True,
            batch_size=args.batch_size,
        )
        all_vectors.extend(embeddings.tolist())

        done = len(all_vectors)
        elapsed = time.time() - t_start
        rate = (done - start_idx) / elapsed if elapsed > 0 else 0
        remaining = ((total - done) / rate / 60) if rate > 0 else 0
        pct = done * 100 / total

        print(f"  ⏳ {done:,}/{total:,} ({pct:.0f}%) | "
              f"{rate:.0f} chunks/sec | ~{remaining:.1f} min left")
        sys.stdout.flush()

        # Checkpoint every N chunks
        if done % CHECKPOINT_INTERVAL < args.batch_size:
            np.save(vectors_path, np.array(all_vectors, dtype=np.float32))
            with open(checkpoint_path, 'w') as f:
                json.dump({'chunks_done': done, 'total': total,
                          'model': args.model, 'batch_size': args.batch_size}, f)

    embedding_time = time.time() - t_start

    # ── Save final output ────────────────────────────────────────────────
    print(f"\n  💾 Saving final vectors...")
    sys.stdout.flush()

    # Convert to numpy and unsort back to original order
    vectors_sorted = np.array(all_vectors, dtype=np.float32)
    
    # Create reverse mapping: original_position[i] = position_in_sorted
    reverse_map = [0] * len(sorted_indices)
    for sorted_pos, original_pos in enumerate(sorted_indices):
        reverse_map[original_pos] = sorted_pos
    
    # Unsort vectors back to original chunk order
    vectors_original = vectors_sorted[reverse_map]
    
    final_path = os.path.join(args.output, 'vectors.npy')
    np.save(final_path, vectors_original)
    print(f"     vectors.npy: {os.path.getsize(final_path) / 1e6:.1f} MB")

    # Save IDs and metadata (in original order, matching chunks.jsonl)
    ids = [chunks[i]['id'] for i in range(len(chunks))]
    with open(os.path.join(args.output, 'ids.json'), 'w') as f:
        json.dump(ids, f)

    metadatas = [chunks[i]['metadata'] for i in range(len(chunks))]
    with open(os.path.join(args.output, 'metadatas.json'), 'w') as f:
        json.dump(metadatas, f)

    # Manifest
    manifest = {
        'model': args.model,
        'dimensions': int(vectors_original.shape[1]),
        'chunks_embedded': len(chunks),
        'embedding_time_sec': round(embedding_time, 1),
        'batch_size': args.batch_size,
        'gpu': gpu_name,
        'sorted_by_length': True,
        'created_at': __import__('datetime').datetime.now().isoformat(),
        'note': 'texts NOT included — use local chunks.jsonl for ChromaDB loading',
    }
    with open(os.path.join(args.output, 'manifest.json'), 'w') as f:
        json.dump(manifest, f, indent=2)

    # Clean up checkpoint files
    for f in ['vectors_partial.npy', 'checkpoint.json']:
        p = os.path.join(args.output, f)
        if os.path.exists(p):
            os.remove(p)

    # ── Summary ──────────────────────────────────────────────────────────
    total_size = sum(
        os.path.getsize(os.path.join(args.output, f))
        for f in os.listdir(args.output)
    ) / 1e6

    print(f"\n╔══════════════════════════════════════════════════════════╗")
    print(f"║  ✅ Done! {len(chunks):,} chunks in {embedding_time/60:.1f} min")
    print(f"║  📂 Output: {args.output}")
    print(f"║  💾 Total: {total_size:.0f} MB (no texts — use local chunks.jsonl)")
    print(f"║  🔧 Model: {args.model}")
    print(f"║  📐 Dims: {vectors_original.shape[1]}")
    print(f"║")
    print(f"║  Next steps:")
    print(f"║  1. Download: scp -P PORT -r runpod:{args.output}/ ~/.logimax/embeddings/")
    print(f"║  2. Load locally: python load_chroma.py --input ~/.logimax/embeddings/")
    print(f"║  3. STOP THE POD!")
    print(f"╚══════════════════════════════════════════════════════════╝")
    sys.stdout.flush()


if __name__ == '__main__':
    main()
