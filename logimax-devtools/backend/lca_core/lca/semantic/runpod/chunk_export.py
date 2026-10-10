"""
Local Chunk Export — prepares data for RunPod GPU embedding.

Runs on your laptop (no GPU needed). Chunks the entire codebase and
exports to JSONL for upload to RunPod.

Usage:
    python chunk_export.py [--source C:\\xampp\\htdocs\\etail_v3] [--output chunks.jsonl]

Output: JSONL file (~50 MB for 28K chunks)
"""
import argparse
import json
import os
import sys
import time

# Add LCA to path
sys.path.insert(0, os.path.join(os.path.dirname(__file__), '..', '..', '..'))


def main():
    parser = argparse.ArgumentParser(description='Export code chunks for remote embedding')
    parser.add_argument('--source', default=r'C:\xampp\htdocs\etail_v3',
                        help='Source code directory to chunk')
    parser.add_argument('--output', default='chunks.jsonl',
                        help='Output JSONL file')
    parser.add_argument('--include-docs', action='store_true', default=True,
                        help='Include .md documentation files')
    parser.add_argument('--include-brains', action='store_true', default=True,
                        help='Include knowledge_brain/ files')
    args = parser.parse_args()

    print("╔══════════════════════════════════════════════════════════╗")
    print("║  LCA Semantic — Chunk Export for RunPod                 ║")
    print("╚══════════════════════════════════════════════════════════╝")

    # ── Chunk the codebase ────────────────────────────────────────────────
    from lca.semantic.chunker import ingest_directory

    print(f"\n  📂 Source: {args.source}")
    print(f"  📄 Output: {args.output}")
    print(f"  🔍 Chunking...")

    t0 = time.time()
    chunks = list(ingest_directory(
        args.source,
        include_docs=args.include_docs,
        include_brains=args.include_brains,
    ))
    chunk_time = time.time() - t0
    print(f"  ✅ Chunked {len(chunks):,} code segments in {chunk_time:.1f}s")

    # ── Dedup IDs ─────────────────────────────────────────────────────────
    seen = {}
    deduped = []
    for chunk in chunks:
        if chunk.id in seen:
            seen[chunk.id] += 1
            chunk.id = f"{chunk.id}_{seen[chunk.id]}"
        else:
            seen[chunk.id] = 1
        deduped.append(chunk)

    dups = sum(1 for v in seen.values() if v > 1)
    if dups:
        print(f"  ⚠️  De-duplicated {dups} chunk IDs")

    # ── Export to JSONL ───────────────────────────────────────────────────
    print(f"\n  💾 Writing {len(deduped):,} chunks to {args.output}...")
    t0 = time.time()

    # Stats tracking
    type_counts = {}
    module_counts = {}
    total_text_bytes = 0

    with open(args.output, 'w', encoding='utf-8') as f:
        for chunk in deduped:
            text = chunk.to_embedding_text()
            metadata = chunk.to_metadata_dict()

            record = {
                "id": chunk.id,
                "text": text,
                "metadata": metadata,
            }
            f.write(json.dumps(record, ensure_ascii=False) + '\n')

            # Track stats
            ct = metadata.get('chunk_type', 'unknown')
            mod = metadata.get('module', 'unknown')
            type_counts[ct] = type_counts.get(ct, 0) + 1
            module_counts[mod] = module_counts.get(mod, 0) + 1
            total_text_bytes += len(text.encode('utf-8'))

    write_time = time.time() - t0
    file_size_mb = os.path.getsize(args.output) / (1024 * 1024)

    # ── Summary ───────────────────────────────────────────────────────────
    print(f"  ✅ Written in {write_time:.1f}s ({file_size_mb:.1f} MB)")

    print(f"\n  📊 Chunk Distribution:")
    for ct, count in sorted(type_counts.items(), key=lambda x: -x[1]):
        print(f"    {ct:25s} {count:6,}")

    print(f"\n  📊 Top Modules:")
    for mod, count in sorted(module_counts.items(), key=lambda x: -x[1])[:10]:
        print(f"    {mod:25s} {count:6,}")

    avg_text = total_text_bytes / len(deduped) if deduped else 0
    print(f"\n  📏 Avg text size: {avg_text:.0f} bytes/chunk")

    print()
    print("╔══════════════════════════════════════════════════════════╗")
    print(f"║  ✅ Export complete!                                     ║")
    print(f"║                                                         ║")
    print(f"║  File: {args.output}")
    print(f"║  Size: {file_size_mb:.1f} MB | Chunks: {len(deduped):,}")
    print(f"║                                                         ║")
    print(f"║  Upload to RunPod:                                      ║")
    print(f"║  scp {args.output} runpod:~/data/input/                 ║")
    print("╚══════════════════════════════════════════════════════════╝")


if __name__ == '__main__':
    main()
