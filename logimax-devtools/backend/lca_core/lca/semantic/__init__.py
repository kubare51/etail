"""
Semantic search module for LCA.

Adds vector-based natural language search to the code analyzer.
Uses ChromaDB with sentence-transformers for embedding.

Usage:
    lca semantic "how does discount calculation work"
    lca semantic-ingest          # rebuild the vector index
    lca semantic-stats           # show collection info
"""
