#!/usr/bin/env python3
"""
Post-download verification for LCA RAG store.

Run this AFTER downloading chroma_db from RunPod to verify:
1. ChromaDB version compatibility
2. Collection exists and has data
3. Embedding model matches
4. Queries return results

Usage:
    python verify_rag_store.py [--store ~/.logimax/rag_store]
"""
import argparse
import json
import os
import sys
from pathlib import Path


def main():
    parser = argparse.ArgumentParser(description='Verify RAG store after download')
    parser.add_argument('--store', default=os.path.join(os.path.expanduser('~'), '.logimax', 'rag_store'),
                        help='Path to ChromaDB store directory')
    args = parser.parse_args()

    print("╔══════════════════════════════════════════════════════════╗")
    print("║  LCA RAG Store — Post-Download Verification            ║")
    print("╚══════════════════════════════════════════════════════════╝")

    errors = []
    warnings = []

    # ── Step 1: Check store exists ─────────────────────────────────────────
    print(f"\n  📂 Store path: {args.store}")
    if not os.path.isdir(args.store):
        print("  ❌ FAIL: Store directory not found")
        sys.exit(1)
    print("  ✅ Store directory exists")

    # ── Step 2: Check manifest ─────────────────────────────────────────────
    manifest_path = os.path.join(args.store, 'manifest.json')
    manifest = None
    if os.path.exists(manifest_path):
        with open(manifest_path, 'r') as f:
            manifest = json.load(f)
        print(f"  ✅ Manifest found:")
        print(f"     Model:    {manifest.get('model', '?')}")
        expected_model = "BAAI/bge-m3"
        if manifest.get('model') and manifest['model'] != expected_model:
            warnings.append(f"Model mismatch: expected {expected_model}, got {manifest['model']}")
            print(f"  ⚠️  Model mismatch: expected {expected_model}")
        print(f"     Dims:     {manifest.get('dimensions', '?')}")
        print(f"     Chunks:   {manifest.get('chunks_embedded', '?')}")
        print(f"     ChromaDB: {manifest.get('chromadb_version', '?')}")
        print(f"     Created:  {manifest.get('created_at', '?')}")
        print(f"     GPU:      {manifest.get('runpod_gpu', '?')}")
    else:
        warnings.append("No manifest.json found (old store format)")
        print("  ⚠️  No manifest.json (old store format)")

    # ── Step 3: Check ChromaDB version ─────────────────────────────────────
    try:
        import chromadb
        local_version = chromadb.__version__
        print(f"\n  🔧 Local ChromaDB: {local_version}")
        if manifest and manifest.get('chromadb_version'):
            remote_version = manifest['chromadb_version']
            if local_version != remote_version:
                errors.append(f"ChromaDB version mismatch: local={local_version} vs store={remote_version}")
                print(f"  ❌ FAIL: Version mismatch (store={remote_version}, local={local_version})")
            else:
                print(f"  ✅ ChromaDB version matches ({local_version})")
    except ImportError:
        errors.append("chromadb not installed")
        print("  ❌ FAIL: chromadb not installed")

    # ── Step 4: Open collection ────────────────────────────────────────────
    try:
        from chromadb.config import Settings
        client = chromadb.PersistentClient(
            path=args.store,
            settings=Settings(anonymized_telemetry=False)
        )
        collections = client.list_collections()
        print(f"\n  📊 Collections: {len(collections)}")
        
        if not collections:
            errors.append("No collections found in store")
            print("  ❌ FAIL: No collections")
        else:
            for col in collections:
                count = col.count()
                print(f"     {col.name}: {count:,} chunks")
                if count == 0:
                    errors.append(f"Collection '{col.name}' is empty")
                    print(f"  ❌ FAIL: Collection '{col.name}' has 0 chunks")
    except Exception as e:
        errors.append(f"Failed to open ChromaDB: {e}")
        print(f"  ❌ FAIL: {e}")

    # ── Step 5: Test query ─────────────────────────────────────────────────
    if not errors:
        print("\n  🔍 Testing query...")
        try:
            collection = client.get_collection("codebase")
            
            # Use BGE model for query
            from lca.semantic.store import _embed_bge_query
            test_query = "billing discount calculation"
            print(f"     Query: \"{test_query}\"")
            
            query_embedding = _embed_bge_query(test_query)
            results = collection.query(
                query_embeddings=[query_embedding],
                n_results=3,
                include=["metadatas", "distances"],
            )
            
            if results['ids'][0]:
                print(f"  ✅ Query returned {len(results['ids'][0])} results:")
                for i in range(len(results['ids'][0])):
                    meta = results['metadatas'][0][i]
                    dist = results['distances'][0][i]
                    print(f"     [{dist:.3f}] {meta.get('file_path', '?')} :: {meta.get('method_name', '?')}")
            else:
                errors.append("Query returned 0 results")
                print("  ❌ FAIL: Query returned 0 results")
        except Exception as e:
            errors.append(f"Query test failed: {e}")
            print(f"  ❌ FAIL: Query test failed: {e}")

    # ── Summary ────────────────────────────────────────────────────────────
    print("\n" + "=" * 60)
    if errors:
        print(f"  ❌ VERIFICATION FAILED — {len(errors)} error(s):")
        for e in errors:
            print(f"     • {e}")
        if warnings:
            print(f"  ⚠️  {len(warnings)} warning(s):")
            for w in warnings:
                print(f"     • {w}")
        sys.exit(1)
    else:
        print("  ✅ ALL CHECKS PASSED — RAG store is ready!")
        if warnings:
            print(f"  ⚠️  {len(warnings)} warning(s):")
            for w in warnings:
                print(f"     • {w}")
        sys.exit(0)


if __name__ == '__main__':
    main()
