"""
ChromaDB vector store for code chunks.

Multi-backend embedding support:
  - 'gemini'  : Google AI Studio API (fast, free tier, requires GOOGLE_API_KEY)
  - 'local'   : SentenceTransformer on CPU (offline, slow on weak hardware)
  - 'runpod'  : RunPod serverless GPU (future, for large rebuilds)

Backend selection:
  - Set LCA_EMBED_BACKEND env var ('gemini' or 'local')
  - Or pass backend= parameter to store_chunks/query_chunks
  - Default: 'gemini' if GOOGLE_API_KEY is set, else 'local'
"""
from __future__ import annotations
import json
import os
import subprocess
import sys
import time
from pathlib import Path
from typing import Optional

from .models import CodeChunk, IngestStats

# ── Embedding Configuration ───────────────────────────────────────────────────
# Local model (used when backend='local')
LOCAL_MODEL = "nomic-ai/nomic-embed-text-v1.5"

# BGE model (used when backend='bge' — matches RunPod GPU index)
BGE_MODEL = "BAAI/bge-m3"

# Gemini model (used when backend='gemini')
GEMINI_MODEL = "gemini-embedding-001"
GEMINI_DIMENSIONS = 1024       # Must match BGE index dimensions (1024)
GEMINI_BATCH_SIZE = 20         # Texts per API call (small to avoid quota spikes)
GEMINI_RPM_LIMIT = 80         # Stay under 100 RPM free tier

# Auto-detect backend
def _detect_backend() -> str:
    """Detect embedding backend from env or API key availability."""
    explicit = os.environ.get("LCA_EMBED_BACKEND", "").lower()
    if explicit in ("gemini", "local", "runpod", "bge"):
        return explicit
    # Auto: use gemini if API key is available
    if _get_api_key():
        return "gemini"
    return "bge"  # Default to BGE (matches RunPod-built index)

def _get_api_key() -> Optional[str]:
    """Get Google API key from env or vault."""
    key = os.environ.get("GOOGLE_API_KEY")
    if key:
        return key
    # Try vault
    try:
        result = subprocess.run(
            ["vault", "get", "api_keys.gemini.lmx"],
            capture_output=True, text=True, timeout=5
        )
        if result.returncode == 0 and result.stdout.strip():
            return result.stdout.strip()
    except (FileNotFoundError, subprocess.TimeoutExpired):
        pass
    return None
# ───────────────────────────────────────────────────────────────────────────────

# ChromaDB import — lazy to avoid import errors when just chunking
_chroma_client = None
_collection = None
_embedding_fn = None


def _get_embedding_fn():
    """Get or create the ChromaDB embedding function (for local query compat)."""
    global _embedding_fn
    if _embedding_fn is None:
        from chromadb.utils.embedding_functions import SentenceTransformerEmbeddingFunction
        _embedding_fn = SentenceTransformerEmbeddingFunction(
            model_name=LOCAL_MODEL,
            trust_remote_code=True,
        )
    return _embedding_fn


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
    """Get or create a collection (no embedding fn — we pass embeddings directly)."""
    global _collection
    if _collection is None:
        client = _get_client(persist_dir)
        _collection = client.get_or_create_collection(
            name=collection_name,
            metadata={"hnsw:space": "cosine"},
        )
    return _collection


# ── Gemini Embedding ──────────────────────────────────────────────────────────

def _embed_gemini(texts: list[str], task_type: str = "RETRIEVAL_DOCUMENT") -> list[list[float]]:
    """Embed texts using Google Gemini API with rate limiting and retry."""
    from google import genai
    api_key = _get_api_key()
    if not api_key:
        raise RuntimeError("No GOOGLE_API_KEY found. Set env var or configure vault.")

    client = genai.Client(api_key=api_key)
    all_embeddings = []

    for i in range(0, len(texts), GEMINI_BATCH_SIZE):
        batch = texts[i:i + GEMINI_BATCH_SIZE]
        success = False

        for attempt in range(5):
            try:
                result = client.models.embed_content(
                    model=GEMINI_MODEL,
                    contents=batch,
                    config={
                        "task_type": task_type,
                        "output_dimensionality": GEMINI_DIMENSIONS,
                    },
                )
                batch_embs = [e.values for e in result.embeddings]
                all_embeddings.extend(batch_embs)
                success = True
                break
            except Exception as e:
                err_str = str(e)
                if "429" in err_str or "RESOURCE_EXHAUSTED" in err_str:
                    # Extract retry delay if available, else exponential backoff
                    wait = min(20 * (attempt + 1), 120)
                    print(f"  ⚠️  Rate limited (attempt {attempt+1}/5), waiting {wait}s...")
                    time.sleep(wait)
                else:
                    raise

        if not success:
            raise RuntimeError(
                f"Gemini API failed after 5 retries for batch {i//GEMINI_BATCH_SIZE + 1}. "
                f"Free tier limit is 100 RPM. Try again in a minute."
            )

        # Throttle between batches to respect RPM
        if i + GEMINI_BATCH_SIZE < len(texts):
            time.sleep(60 / GEMINI_RPM_LIMIT)

    return all_embeddings


def _embed_gemini_query(text: str) -> list[float]:
    """Embed a single query text using Gemini API."""
    results = _embed_gemini([text], task_type="RETRIEVAL_QUERY")
    return results[0]


# ── Local Embedding ───────────────────────────────────────────────────────────

def _embed_local(texts: list[str]) -> list[list[float]]:
    """Embed texts using local SentenceTransformer."""
    from sentence_transformers import SentenceTransformer
    model = SentenceTransformer(LOCAL_MODEL, trust_remote_code=True)
    embeddings = model.encode(
        texts, show_progress_bar=False, normalize_embeddings=True
    )
    return embeddings.tolist()


def _embed_local_query(text: str) -> list[float]:
    """Embed a single query using local model."""
    return _embed_local([f"search_query: {text}"])[0]


# ── BGE Embedding ─────────────────────────────────────────────────────────────

_bge_model = None

def _get_bge_model():
    """Lazy-load BGE-M3 model using SentenceTransformer."""
    global _bge_model
    if _bge_model is None:
        from sentence_transformers import SentenceTransformer
        _bge_model = SentenceTransformer(BGE_MODEL, trust_remote_code=True)
    return _bge_model


def _embed_bge(texts: list[str]) -> list[list[float]]:
    """Embed texts using BGE-M3 via sentence-transformers.
    
    Uses SentenceTransformer for correct pooling (M3 needs special handling).
    M3 supports up to 8192 tokens — no truncation at 512.
    """
    model = _get_bge_model()
    embeddings = model.encode(
        texts, show_progress_bar=False, normalize_embeddings=True
    )
    return embeddings.tolist()


def _embed_bge_query(text: str) -> list[float]:
    """Embed a single query using BGE-M3 model."""
    return _embed_bge([text])[0]


def _auto_batch_size(backend: str) -> int:
    """Auto-tune batch size based on backend and available RAM."""
    if backend == "gemini":
        return GEMINI_BATCH_SIZE
    try:
        import psutil
        avail_gb = psutil.virtual_memory().available / (1024 ** 3)
        if avail_gb > 6:
            return 500
        elif avail_gb > 3:
            return 200
        else:
            return 100
    except ImportError:
        return 200


def store_chunks(
    chunks: list[CodeChunk],
    persist_dir: str,
    collection_name: str = "codebase",
    batch_size: int = 0,
    backend: str = "",
) -> int:
    """
    Store code chunks in ChromaDB with embeddings.

    Args:
        chunks: List of CodeChunk objects to store
        persist_dir: Directory for ChromaDB persistence
        collection_name: Name of the collection
        batch_size: Chunks per batch (0 = auto)
        backend: 'gemini', 'local', or '' (auto-detect)

    Returns:
        Number of chunks stored
    """
    if not backend:
        backend = _detect_backend()
    if batch_size <= 0:
        batch_size = _auto_batch_size(backend)

    print(f"  🔧 Backend: {backend} | Batch size: {batch_size}")

    collection = _get_collection(persist_dir, collection_name)

    # ── Pre-rebuild benchmark ─────────────────────────────────────────────
    if len(chunks) > 50:
        print(f"  📊 Benchmarking encode speed ({backend})...")
        sample_texts = [c.to_embedding_text() for c in chunks[:10]]
        t0 = time.time()
        if backend == "gemini":
            _embed_gemini(sample_texts)
        else:
            _embed_local(sample_texts)
        bench_elapsed = time.time() - t0
        per_chunk = bench_elapsed / len(sample_texts)
        est_min = (per_chunk * len(chunks)) / 60
        print(f"  ⏱️  Speed: {per_chunk:.4f}s/chunk ({1/per_chunk:.0f} chunks/sec)")
        print(f"  📐 Estimated: {est_min:.1f} min for {len(chunks):,} chunks")
        print()

    # ── Global dedup ──────────────────────────────────────────────────────
    seen = {}
    deduped = []
    for chunk in chunks:
        if chunk.id in seen:
            seen[chunk.id] += 1
            chunk.id = f"{chunk.id}_{seen[chunk.id]}"
        else:
            seen[chunk.id] = 1
        deduped.append(chunk)

    # ── Embed and store with progress ─────────────────────────────────────
    stored = 0
    total = len(deduped)
    t_start = time.time()

    for i in range(0, total, batch_size):
        batch = deduped[i:i + batch_size]

        ids = [c.id for c in batch]
        documents = [c.to_embedding_text() for c in batch]
        metadatas = [c.to_metadata_dict() for c in batch]

        # Embed using selected backend
        if backend == "gemini":
            embeddings = _embed_gemini(documents)
        elif backend == "bge":
            embeddings = _embed_bge(documents)
        else:
            embeddings = _embed_local(documents)

        # Upsert with pre-computed embeddings
        collection.upsert(
            ids=ids,
            embeddings=embeddings,
            documents=documents,
            metadatas=metadatas,
        )
        stored += len(batch)

        # ── Progress logging ──────────────────────────────────────────
        elapsed = time.time() - t_start
        pct = (stored / total) * 100
        rate = stored / elapsed if elapsed > 0 else 0
        remaining = ((total - stored) / rate) if rate > 0 else 0
        print(
            f"  ⏳ {stored:,}/{total:,} ({pct:.0f}%) | "
            f"{rate:.1f} chunks/sec | "
            f"~{remaining/60:.0f} min left"
        )

    # Final summary
    elapsed_total = time.time() - t_start
    print(f"  ✅ Embedded {stored:,} chunks in {elapsed_total/60:.1f} minutes ({backend})")

    return stored


def query_chunks(
    query: str,
    persist_dir: str,
    collection_name: str = "codebase",
    n_results: int = 10,
    where_filter: Optional[dict] = None,
    backend: str = "",
) -> list[dict]:
    """
    Query the vector store for relevant code chunks.

    Args:
        query: Natural language query
        persist_dir: Directory for ChromaDB persistence
        collection_name: Collection name
        n_results: Max results to return
        where_filter: ChromaDB where filter
        backend: 'gemini', 'local', or '' (auto-detect)

    Returns:
        List of dicts with: id, document, metadata, distance
    """
    if not backend:
        backend = _detect_backend()

    collection = _get_collection(persist_dir, collection_name)

    # Embed query using the same backend
    if backend == "gemini":
        query_embedding = _embed_gemini_query(query)
    elif backend == "bge":
        query_embedding = _embed_bge_query(query)
    else:
        query_embedding = _embed_local_query(query)

    kwargs = {
        "query_embeddings": [query_embedding],
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


def _keyword_score(query: str, document: str, metadata: dict) -> float:
    """Simple keyword relevance score (0.0 to 1.0).

    Scores higher when query terms appear in key metadata fields
    (summary, method_name, tables, module) vs just in the code body.
    """
    query_terms = set(query.lower().split())
    if not query_terms:
        return 0.0

    score = 0.0
    total_weight = 0.0

    # Weighted field matching
    fields = {
        'summary': (metadata.get('summary', ''), 5.0),
        'method_name': (metadata.get('method_name', ''), 4.0),
        'class_name': (metadata.get('class_name', ''), 3.0),
        'tables': (metadata.get('tables', ''), 3.0),
        'models': (metadata.get('models', ''), 2.0),
        'module': (metadata.get('module', ''), 2.0),
        'file_path': (metadata.get('file_path', ''), 1.0),
        'document': (document[:2000], 0.5),
    }

    for field_name, (field_value, weight) in fields.items():
        field_lower = field_value.lower()
        matches = sum(1 for term in query_terms if term in field_lower)
        if matches > 0:
            field_score = matches / len(query_terms)
            score += field_score * weight
            total_weight += weight

    max_possible = sum(w for _, (_, w) in fields.items())
    return min(score / max_possible, 1.0) if max_possible > 0 else 0.0


def hybrid_query_chunks(
    query: str,
    persist_dir: str,
    collection_name: str = "codebase",
    n_results: int = 10,
    where_filter: Optional[dict] = None,
    vector_weight: float = 0.6,
    keyword_weight: float = 0.4,
) -> list[dict]:
    """Hybrid search: vector similarity + keyword scoring via Reciprocal Rank Fusion.

    Over-fetches from vector search, applies keyword scoring to each result,
    then merges using RRF for better combined ranking.

    Args:
        query: Natural language query
        persist_dir: ChromaDB store directory
        collection_name: Collection name
        n_results: Final number of results to return
        where_filter: ChromaDB filter
        vector_weight: Weight for vector ranking (0.0-1.0)
        keyword_weight: Weight for keyword ranking (0.0-1.0)
    """
    # Over-fetch from vector search (3x for re-ranking headroom)
    vector_results = query_chunks(
        query, persist_dir, collection_name,
        n_results=min(n_results * 3, 50),
        where_filter=where_filter,
    )

    if not vector_results:
        return []

    # Score each result with keyword matching
    for r in vector_results:
        r['keyword_score'] = _keyword_score(query, r['document'], r['metadata'])

    # Reciprocal Rank Fusion
    k = 60  # RRF constant

    # Vector rank (already sorted by distance)
    for rank, r in enumerate(vector_results):
        r['vector_rrf'] = 1.0 / (k + rank + 1)

    # Keyword rank (sort by keyword score descending)
    keyword_sorted = sorted(vector_results, key=lambda x: x['keyword_score'], reverse=True)
    for rank, r in enumerate(keyword_sorted):
        r['keyword_rrf'] = 1.0 / (k + rank + 1)

    # Combined score
    for r in vector_results:
        r['hybrid_score'] = (
            vector_weight * r['vector_rrf'] +
            keyword_weight * r['keyword_rrf']
        )

    # Sort by hybrid score and return top N
    vector_results.sort(key=lambda x: x['hybrid_score'], reverse=True)
    return vector_results[:n_results]


