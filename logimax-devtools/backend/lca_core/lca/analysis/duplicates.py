"""Duplicate Code Finder — finds functions that are 80%+ similar across files.

Algorithm:
  1. Extract all functions with their body text
  2. Normalize the body (strip whitespace, comments, variable names)
  3. Compute similarity using SequenceMatcher
  4. Group functions with >threshold similarity as duplicates

Ported from: duplicate_finder.py (standalone C4 tool)
"""

import re
from dataclasses import dataclass, field
from difflib import SequenceMatcher
from pathlib import Path
from typing import List, Optional

from lca.analysis.modules import detect_module


@dataclass
class DuplicateFunction:
    """A function that is part of a duplicate cluster."""
    name: str
    file: str
    module: str
    line: int
    size: int


@dataclass
class DuplicateCluster:
    """A group of functions that are highly similar."""
    functions: List[DuplicateFunction]
    similarity: float
    count: int
    cross_module: bool
    cross_file: bool


@dataclass
class DuplicateScanResult:
    """Results of a duplicate code scan."""
    files_scanned: int
    total_functions: int
    duplicate_clusters: int
    duplicate_functions: int
    cross_module_duplicates: int
    cross_file_duplicates: int
    clusters: List[DuplicateCluster] = field(default_factory=list)


# ── Function Extraction ───────────────────────────────────────────────

def extract_functions_with_body(filepath: Path):
    """
    Extract all functions with their full body from a PHP file.
    Returns list of dicts: {name, body, line, size}
    """
    functions = []
    try:
        content = filepath.read_text(encoding='utf-8', errors='ignore')
    except Exception:
        return functions

    # Find function definitions
    pattern = r'(?:public|private|protected)?\s*(?:static\s+)?function\s+(\w+)\s*\([^)]*\)\s*\{'
    matches = list(re.finditer(pattern, content))

    for match in matches:
        func_name = match.group(1)
        start = match.end()  # After opening {
        line = content[:match.start()].count('\n') + 1

        # Find matching closing brace
        depth = 1
        pos = start
        while pos < len(content) and depth > 0:
            if content[pos] == '{':
                depth += 1
            elif content[pos] == '}':
                depth -= 1
            pos += 1

        if depth == 0:
            body = content[start:pos-1].strip()
            if len(body) > 50:  # Skip trivial functions
                functions.append({
                    'name': func_name,
                    'body': body,
                    'line': line,
                    'size': len(body),
                })

    return functions


def normalize_body(body: str) -> str:
    """
    Normalize function body for comparison:
    - Remove comments
    - Replace variable names with $V
    - Replace string literals with placeholders
    - Collapse whitespace
    """
    text = re.sub(r'//[^\n]*', '', body)
    text = re.sub(r'/\*.*?\*/', '', text, flags=re.DOTALL)
    text = re.sub(r"'[^']*'", "'S'", text)
    text = re.sub(r'"[^"]*"', '"S"', text)
    text = re.sub(r'\$\w+', '$V', text)
    text = re.sub(r'\s+', ' ', text).strip()
    return text


# ── Similarity Computation ────────────────────────────────────────────

def compute_similarity(body1: str, body2: str) -> float:
    """Compute normalized similarity ratio between two function bodies."""
    return SequenceMatcher(None, body1, body2).ratio()


# ── Main Scanner ──────────────────────────────────────────────────────

def scan_duplicates(
    project_root: Path,
    threshold: float = 0.80,
    module_filter: Optional[str] = None,
) -> DuplicateScanResult:
    """Scan codebase for duplicate functions."""
    scan_dirs = [
        project_root / 'admin' / 'application' / 'controllers',
        project_root / 'admin' / 'application' / 'models',
        project_root / 'admin' / 'application' / 'helpers',
    ]

    all_functions = []
    files_scanned = 0

    for scan_dir in scan_dirs:
        if not scan_dir.is_dir():
            continue
        for fpath in sorted(scan_dir.rglob('*.php')):
            rel_path = str(fpath.relative_to(project_root))
            module = detect_module(rel_path)

            if module_filter and module != module_filter:
                continue

            files_scanned += 1
            funcs = extract_functions_with_body(fpath)
            for f in funcs:
                f['file'] = rel_path
                f['module'] = module
                f['normalized'] = normalize_body(f['body'])
                all_functions.append(f)

    # Compare functions — O(n^2) but pruned by size
    duplicates = []
    seen = set()

    # Sort by normalized body length for faster pruning
    all_functions.sort(key=lambda x: len(x['normalized']))

    for i in range(len(all_functions)):
        if i in seen:
            continue
        cluster = [all_functions[i]]

        for j in range(i + 1, len(all_functions)):
            if j in seen:
                continue

            len_i = len(all_functions[i]['normalized'])
            len_j = len(all_functions[j]['normalized'])
            if len_j > len_i * 1.5:
                break  # Sorted, so all subsequent will be larger

            if len_i < 100:  # Skip very short functions
                continue

            sim = compute_similarity(
                all_functions[i]['normalized'],
                all_functions[j]['normalized']
            )

            if sim >= threshold:
                if (all_functions[i]['file'] == all_functions[j]['file'] and
                    all_functions[i]['name'] == all_functions[j]['name']):
                    continue
                cluster.append(all_functions[j])
                seen.add(j)

        if len(cluster) > 1:
            seen.add(i)
            sims = []
            for a in range(len(cluster)):
                for b in range(a+1, len(cluster)):
                    sims.append(compute_similarity(
                        cluster[a]['normalized'],
                        cluster[b]['normalized']
                    ))
            avg_sim = sum(sims) / len(sims) if sims else 0

            funcs_list = [
                DuplicateFunction(
                    name=f['name'], file=f['file'], module=f['module'],
                    line=f['line'], size=f['size'],
                )
                for f in cluster
            ]
            modules_set = set(f['module'] for f in cluster)
            files_set = set(f['file'] for f in cluster)

            duplicates.append(DuplicateCluster(
                functions=funcs_list,
                similarity=round(avg_sim * 100, 1),
                count=len(cluster),
                cross_module=len(modules_set) > 1,
                cross_file=len(files_set) > 1,
            ))

    # Sort by similarity descending
    duplicates.sort(key=lambda x: (-x.similarity, -x.count))

    return DuplicateScanResult(
        files_scanned=files_scanned,
        total_functions=len(all_functions),
        duplicate_clusters=len(duplicates),
        duplicate_functions=sum(d.count for d in duplicates),
        cross_module_duplicates=sum(1 for d in duplicates if d.cross_module),
        cross_file_duplicates=sum(1 for d in duplicates if d.cross_file),
        clusters=duplicates,
    )
