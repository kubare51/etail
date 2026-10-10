"""File hash cache for incremental indexing.

Tracks SHA256 hashes of source files and their parsed results to skip
unchanged files during re-indexing, providing significant speedup on
large codebases.

Cache stores per-file: hash, functions[], calls[] so that unchanged files
contribute their cached results to the merged index without re-parsing.
"""

import json
import hashlib
from pathlib import Path
from datetime import datetime


class FileHashCache:
    """Manages file hash cache with per-file parsed results for incremental indexing."""
    
    CACHE_VERSION = 2  # Bumped: now stores per-file functions/calls
    
    def __init__(self, cache_path: Path):
        self.cache_path = cache_path
        self._cache: dict[str, dict] = {}  # file_path -> {hash, indexed_at, functions, calls}
        self._load()
    
    def _load(self) -> None:
        """Load cache from disk."""
        if self.cache_path.exists():
            try:
                data = json.loads(self.cache_path.read_text(encoding="utf-8"))
                if data.get("version") == self.CACHE_VERSION:
                    self._cache = data.get("files", {})
                else:
                    # Cache version mismatch — start fresh
                    self._cache = {}
            except (json.JSONDecodeError, KeyError):
                self._cache = {}
    
    def save(self) -> None:
        """Persist cache to disk."""
        self.cache_path.parent.mkdir(parents=True, exist_ok=True)
        data = {
            "version": self.CACHE_VERSION,
            "updated_at": datetime.now().isoformat(),
            "file_count": len(self._cache),
            "files": self._cache,
        }
        self.cache_path.write_text(
            json.dumps(data, ensure_ascii=False),  # No indent — keep cache compact
            encoding="utf-8"
        )
    
    @staticmethod
    def _hash_file(file_path: Path) -> str:
        """Compute SHA256 hash of a file."""
        h = hashlib.sha256()
        with open(file_path, "rb") as f:
            for chunk in iter(lambda: f.read(8192), b""):
                h.update(chunk)
        return h.hexdigest()
    
    def get_changed_files(self, files: list[Path]) -> tuple[list[Path], list[Path]]:
        """Partition files into changed and unchanged.
        
        Args:
            files: All candidate files to check
            
        Returns:
            Tuple of (changed_files, unchanged_files)
        """
        changed = []
        unchanged = []
        
        for f in files:
            key = str(f)
            try:
                current_hash = self._hash_file(f)
            except (OSError, PermissionError):
                changed.append(f)
                continue
            
            cached = self._cache.get(key)
            if cached and cached.get("hash") == current_hash and "functions" in cached:
                unchanged.append(f)
            else:
                changed.append(f)
        
        return changed, unchanged
    
    def get_cached_results(self, file_path: Path) -> tuple[list[dict], list[dict]] | None:
        """Get cached function definitions and calls for an unchanged file.
        
        Returns:
            Tuple of (functions_dicts, calls_dicts) or None if not cached.
        """
        cached = self._cache.get(str(file_path))
        if cached and "functions" in cached and "calls" in cached:
            return cached["functions"], cached["calls"]
        return None
    
    def update(self, file_path: Path, functions: list[dict] | None = None,
               calls: list[dict] | None = None) -> None:
        """Update the hash and optionally the parsed results for a file."""
        try:
            file_hash = self._hash_file(file_path)
            entry: dict = {
                "hash": file_hash,
                "indexed_at": datetime.now().isoformat(),
            }
            if functions is not None:
                entry["functions"] = functions
            if calls is not None:
                entry["calls"] = calls
            self._cache[str(file_path)] = entry
        except (OSError, PermissionError):
            pass
    
    def remove(self, file_path: Path) -> None:
        """Remove a file from the cache (e.g., if deleted)."""
        self._cache.pop(str(file_path), None)
    
    def clear(self) -> None:
        """Clear the entire cache (force full re-index)."""
        self._cache = {}
    
    @property
    def file_count(self) -> int:
        """Number of files tracked in cache."""
        return len(self._cache)
