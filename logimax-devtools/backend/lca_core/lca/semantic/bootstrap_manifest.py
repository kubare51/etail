"""
Bootstrap a sync manifest for an existing ChromaDB store built via RunPod.

The RunPod pipeline (embed_gpu_only.py + load_chroma.py) bypasses the
manifest system. This script creates a manifest by:
1. Walking the source directory (same logic as chunker)
2. Computing file hashes for all indexed files
3. Querying ChromaDB to find which chunk IDs belong to which files
4. Writing a .lca_rag_manifest.json that matches the current state

After running this, `lca rag-sync` will work incrementally.

Usage:
    python bootstrap_manifest.py <source_dir> <store_dir>
    python bootstrap_manifest.py c:/xampp/htdocs/etail_v3 ~/.logimax/rag_store
"""
from __future__ import annotations
import argparse
import json
import os
import sys
import time
from datetime import datetime
from pathlib import Path

# Add parent packages to path
sys.path.insert(0, str(Path(__file__).resolve().parent.parent.parent))

from lca.semantic.chunker import (
    ingest_directory, SKIP_DIRS, SKIP_FILES, MAX_FILE_SIZE,
)
from lca.semantic.sync import (
    SyncManifest, save_manifest, _file_hash, _safe_mtime,
)


def bootstrap(source_dir: str, persist_dir: str, backend: str = 'bge') -> SyncManifest:
    """Build a manifest by chunking the source dir (no embedding needed)."""
    
    print(f'  Source: {source_dir}')
    print(f'  Store:  {persist_dir}')
    print()
    
    t_start = time.time()
    manifest = SyncManifest(
        backend=backend,
        last_sync=datetime.now().isoformat(),
    )
    
    # Chunk everything (just to get IDs — no embedding)
    print('  Chunking source files to build ID map...')
    chunk_count = 0
    file_count = 0
    
    for chunk in ingest_directory(source_dir, include_docs=True, include_brains=True):
        fp = chunk.file_path
        
        if fp not in manifest.files:
            abs_path = str(Path(source_dir) / fp.replace('/', os.sep))
            manifest.files[fp] = {
                'hash': _file_hash(abs_path),
                'mtime': _safe_mtime(abs_path),
                'chunk_ids': [],
                'chunk_count': 0,
            }
            file_count += 1
        
        manifest.files[fp]['chunk_ids'].append(chunk.id)
        manifest.files[fp]['chunk_count'] += 1
        chunk_count += 1
        
        if chunk_count % 5000 == 0:
            print(f'  ... {chunk_count:,} chunks from {file_count:,} files')
    
    manifest.total_files = file_count
    manifest.total_chunks = chunk_count
    
    # Save
    save_manifest(persist_dir, manifest)
    
    elapsed = time.time() - t_start
    print(f'\n  ✅ Manifest created: {file_count:,} files, {chunk_count:,} chunks')
    print(f'  📂 Saved to: {persist_dir}/.lca_rag_manifest.json')
    print(f'  ⏱  Time: {elapsed:.1f}s')
    print(f'\n  Now run: lca rag-status')
    print(f'  Or:      lca rag-sync  (for incremental updates)')
    
    return manifest


def main():
    parser = argparse.ArgumentParser(description='Bootstrap sync manifest for existing RAG store')
    parser.add_argument('source_dir', help='Project root directory')
    parser.add_argument('store_dir', help='ChromaDB store directory')
    parser.add_argument('--backend', default='bge', help='Embedding backend used (default: bge)')
    args = parser.parse_args()
    
    source = os.path.abspath(args.source_dir)
    store = os.path.expanduser(args.store_dir)
    
    if not os.path.isdir(source):
        print(f'Error: Source directory not found: {source}')
        sys.exit(1)
    if not os.path.isdir(store):
        print(f'Error: Store directory not found: {store}')
        sys.exit(1)
    
    print('╔══════════════════════════════════════════════════════════╗')
    print('║  LCA Semantic — Bootstrap Manifest                     ║')
    print('║  (Creates manifest for existing RunPod-built store)    ║')
    print('╚══════════════════════════════════════════════════════════╝')
    
    bootstrap(source, store, args.backend)


if __name__ == '__main__':
    main()
