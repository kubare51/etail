"""
ChromaDB vector store for code chunks.

Handles embedding storage, querying, and collection management.
Uses ChromaDB's built-in sentence-transformer embeddings (all-MiniLM-L6-v2)
by default — no API keys required.
"""
from __future__ import annotations
import json
import os
from pathlib import Path
from typing import Optional

from .models import CodeChunk, IngestStats

# ChromaDB import — lazy to avoid import errors when just chunking
_chroma_client = None
_collection = None


def _get_client(persist_dir: str):
    """Get or create a persistent ChromaDB client."""
    global _chroma_client
    if _chroma_client is None:
        import chromadb
        from chromadb.config import Settings
        os.makedirs(persist_dir, exist_ok=True)
        _chroma_client = chromadb.PersistentClient(
            path=persist_dir,
            settings=Settings(anonymized_telemetry=False)
        )
    return _chroma_client


def _get_collection(persist_dir: str, collection_name: str = "codebase"):
    """Get or create a collection."""
    global _collection
    if _collection is None:
        client = _get_client(persist_dir)
        _collection = client.get_or_create_collection(
            name=collection_name,
            metadata={"hnsw:space": "cosine"}
        )
    return _collection


def store_chunks(
    chunks: list[CodeChunk],
    persist_dir: str,
    collection_name: str = "codebase",
    batch_size: int = 100,
) -> int:
    """
    Store code chunks in ChromaDB with embeddings.

    Args:
        chunks: List of CodeChunk objects to store
        persist_dir: Directory for ChromaDB persistence
        collection_name: Name of the collection
        batch_size: Number of chunks to upsert at once

    Returns:
        Number of chunks stored
    """
    collection = _get_collection(persist_dir, collection_name)

    # Global dedup — ensure all IDs are unique across the entire batch
    seen = {}
    deduped = []
    for chunk in chunks:
        if chunk.id in seen:
            # Append counter to make unique
            seen[chunk.id] += 1
            chunk.id = f"{chunk.id}_{seen[chunk.id]}"
        else:
            seen[chunk.id] = 1
        deduped.append(chunk)

    stored = 0
    for i in range(0, len(deduped), batch_size):
        batch = deduped[i:i + batch_size]

        ids = [c.id for c in batch]
        documents = [c.to_embedding_text() for c in batch]
        metadatas = [c.to_metadata_dict() for c in batch]

        collection.upsert(
            ids=ids,
            documents=documents,
            metadatas=metadatas,
        )
        stored += len(batch)

    return stored


def query_chunks(
    query: str,
    persist_dir: str,
    collection_name: str = "codebase",
    n_results: int = 10,
    where_filter: Optional[dict] = None,
) -> list[dict]:
    """
    Query the vector store for relevant code chunks.

    Args:
        query: Natural language query
        persist_dir: Directory for ChromaDB persistence
        collection_name: Collection name
        n_results: Max results to return
        where_filter: ChromaDB where filter (e.g., {"module": "billing"})

    Returns:
        List of dicts with: id, document, metadata, distance
    """
    collection = _get_collection(persist_dir, collection_name)

    kwargs = {
        "query_texts": [query],
        "n_results": n_results,
        "include": ["documents", "metadatas", "distances"],
    }
    if where_filter:
        kwargs["where"] = where_filter

    results = collection.query(**kwargs)

    # Flatten results
    output = []
    if results and results['ids'] and results['ids'][0]:
        for i in range(len(results['ids'][0])):
            output.append({
                "id": results['ids'][0][i],
                "document": results['documents'][0][i] if results['documents'] else "",
                "metadata": results['metadatas'][0][i] if results['metadatas'] else {},
                "distance": results['distances'][0][i] if results['distances'] else 0,
            })

    return output


def get_collection_stats(persist_dir: str, collection_name: str = "codebase") -> dict:
    """Get stats about the stored collection."""
    try:
        collection = _get_collection(persist_dir, collection_name)
        count = collection.count()

        # Sample some metadata to get distribution
        sample = collection.peek(limit=min(count, 100))

        type_counts = {}
        module_counts = {}
        if sample and sample['metadatas']:
            for meta in sample['metadatas']:
                ct = meta.get('chunk_type', 'unknown')
                mod = meta.get('module', 'unknown')
                type_counts[ct] = type_counts.get(ct, 0) + 1
                module_counts[mod] = module_counts.get(mod, 0) + 1

        return {
            "total_chunks": count,
            "collection": collection_name,
            "persist_dir": persist_dir,
            "sample_by_type": type_counts,
            "sample_by_module": module_counts,
        }
    except Exception as e:
        return {"error": str(e)}


def delete_collection(persist_dir: str, collection_name: str = "codebase"):
    """Delete a collection (for rebuild)."""
    global _collection
    client = _get_client(persist_dir)
    try:
        client.delete_collection(collection_name)
    except Exception:
        pass  # Collection may not exist
    _collection = None


def export_chunks_jsonl(chunks: list[CodeChunk], output_path: str) -> int:
    """Export chunks to JSONL file (for external embedding pipelines)."""
    with open(output_path, 'w', encoding='utf-8') as f:
        for chunk in chunks:
            record = {
                "id": chunk.id,
                "text": chunk.to_embedding_text(),
                "metadata": chunk.to_metadata_dict(),
                "raw_code": chunk.text[:4000],
            }
            f.write(json.dumps(record, ensure_ascii=False) + '\n')

    return len(chunks)
