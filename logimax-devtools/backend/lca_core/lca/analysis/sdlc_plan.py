"""SDLC Plan Engine — Impact Matrix Generator for Pipeline PLANNING Phase.

Analyzes a set of target files and produces a structured impact matrix
showing callers, tables, risk scores, and affected business rules.

Usage:
    from lca.analysis.sdlc_plan import SDLCPlanner
    planner = SDLCPlanner(project_root, index_file)
    matrix = planner.analyze(['admin/application/models/Billing_model.php'])
    print(matrix.complexity)  # S / M / L / XL

Called during the SDLC pipeline PLANNING phase to inform implementation plans.
"""

from __future__ import annotations

import json
import re
import subprocess
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional


# ── Models ────────────────────────────────────────────────────────────

@dataclass
class FileImpact:
    """Impact analysis for a single file."""
    file: str
    functions: list[str] = field(default_factory=list)
    callers: list[dict] = field(default_factory=list)     # [{file, function, line}]
    tables: list[str] = field(default_factory=list)        # tables this file touches
    risk_score: float = 0.0
    business_rules: list[str] = field(default_factory=list)
    is_shared: bool = False       # used by multiple modules


@dataclass
class ImpactMatrix:
    """Full impact matrix for a planned change."""
    files: list[FileImpact] = field(default_factory=list)
    modules_affected: list[str] = field(default_factory=list)
    danger_zones: list[str] = field(default_factory=list)
    cross_module: bool = False

    @property
    def total_callers(self) -> int:
        return sum(len(f.callers) for f in self.files)

    @property
    def total_tables(self) -> int:
        all_tables: set[str] = set()
        for f in self.files:
            all_tables.update(f.tables)
        return len(all_tables)

    @property
    def max_risk(self) -> float:
        if not self.files:
            return 0.0
        return max(f.risk_score for f in self.files)

    @property
    def complexity(self) -> str:
        """Estimate complexity based on impact breadth."""
        n_files = len(self.files)
        n_callers = self.total_callers
        n_tables = self.total_tables
        cross = self.cross_module

        if n_files <= 1 and n_callers <= 3 and not cross:
            return 'S'
        if n_files <= 3 and n_callers <= 10 and not cross:
            return 'M'
        if n_files <= 6 and n_callers <= 20:
            return 'L'
        return 'XL'

    def to_dict(self) -> dict:
        return {
            'complexity': self.complexity,
            'cross_module': self.cross_module,
            'modules_affected': self.modules_affected,
            'total_callers': self.total_callers,
            'total_tables': self.total_tables,
            'max_risk': round(self.max_risk, 2),
            'danger_zones': self.danger_zones,
            'files': [
                {
                    'file': Path(f.file).name,
                    'path': f.file,
                    'functions': f.functions,
                    'callers': f.callers,
                    'tables': f.tables,
                    'risk_score': round(f.risk_score, 2),
                    'business_rules': f.business_rules,
                    'is_shared': f.is_shared,
                }
                for f in self.files
            ],
        }


# ── Planner Engine ────────────────────────────────────────────────────

class SDLCPlanner:
    """Impact matrix generator for the SDLC pipeline PLANNING phase."""

    def __init__(self, project_root: Path, index_file: Optional[Path] = None):
        self.root = Path(project_root)
        self.index_file = index_file
        self._db = None

    def analyze(self, target_files: list[str]) -> ImpactMatrix:
        """Analyze impact of changing the target files.

        Args:
            target_files: List of file paths relative to project root.

        Returns:
            ImpactMatrix with callers, tables, risk, and business rules.
        """
        matrix = ImpactMatrix()
        modules_seen: set[str] = set()

        for fpath in target_files:
            impact = FileImpact(file=fpath)

            # Extract functions from the file
            impact.functions = self._extract_functions(fpath)

            # Find callers of these functions
            impact.callers = self._find_callers(fpath, impact.functions)

            # Find tables this file touches
            impact.tables = self._find_tables(fpath)

            # Calculate risk score
            impact.risk_score = self._calculate_risk(fpath)

            # Find related business rules
            impact.business_rules = self._find_business_rules(fpath)

            # Check if shared across modules
            caller_modules = set()
            for caller in impact.callers:
                mod = self._detect_module(caller.get('file', ''))
                if mod:
                    caller_modules.add(mod)
            file_module = self._detect_module(fpath)
            if file_module:
                modules_seen.add(file_module)
            modules_seen.update(caller_modules)
            impact.is_shared = len(caller_modules) > 1

            matrix.files.append(impact)

        matrix.modules_affected = sorted(modules_seen)
        matrix.cross_module = len(modules_seen) > 1

        # Check danger zones
        matrix.danger_zones = self._check_danger_zones(target_files)

        return matrix

    # ── Extract Functions ─────────────────────────────────────────────

    def _extract_functions(self, fpath: str) -> list[str]:
        """Extract function/method names from a file."""
        full_path = self.root / fpath
        if not full_path.exists():
            return []

        try:
            content = full_path.read_text(encoding='utf-8', errors='ignore')
        except Exception:
            return []

        funcs: list[str] = []

        if fpath.endswith('.php'):
            for m in re.finditer(r'function\s+(\w+)\s*\(', content):
                name = m.group(1)
                if name not in ('__construct', '__destruct', '__get', '__set'):
                    funcs.append(name)
        elif fpath.endswith('.js'):
            # Named functions
            for m in re.finditer(r'function\s+(\w+)\s*\(', content):
                funcs.append(m.group(1))
            # Arrow/var functions
            for m in re.finditer(r'(?:const|let|var)\s+(\w+)\s*=\s*(?:function|\()', content):
                funcs.append(m.group(1))

        return funcs

    # ── Find Callers (via grep) ───────────────────────────────────────

    def _find_callers(self, fpath: str, functions: list[str]) -> list[dict]:
        """Find all callers of the given functions using a single batched git grep."""
        callers: list[dict] = []
        app_dir = self.root / 'admin' / 'application'

        if not app_dir.exists():
            app_dir = self.root

        # Filter to meaningful function names (skip very short or common ones)
        search_funcs = [f for f in functions if len(f) >= 4]
        if not search_funcs:
            return callers

        # Batch into a single git grep with alternation — max ~30 functions per call
        # to avoid command line length limits
        BATCH_SIZE = 30
        for batch_start in range(0, len(search_funcs), BATCH_SIZE):
            batch = search_funcs[batch_start:batch_start + BATCH_SIZE]
            pattern = '|'.join(re.escape(f) for f in batch)

            try:
                result = subprocess.run(
                    ['git', 'grep', '-nE', '--no-color', pattern,
                     '--', 'admin/application/', 'admin/assets/js/'],
                    capture_output=True, text=True,
                    cwd=str(self.root),
                    timeout=15,
                    encoding='utf-8', errors='replace',
                )

                for line in result.stdout.strip().split('\n'):
                    if not line.strip():
                        continue
                    parts = line.split(':', 2)
                    if len(parts) < 3:
                        continue

                    caller_file = parts[0]
                    caller_line = parts[1]
                    caller_content = parts[2].strip()

                    # Skip self-references
                    if caller_file == fpath:
                        continue
                    # Skip comments
                    if caller_content.startswith('//') or caller_content.startswith('*'):
                        continue

                    # Identify which function matched
                    matched_func = ''
                    for f in batch:
                        if f in caller_content:
                            # Skip if this is the function definition itself
                            if f'function {f}' in caller_content:
                                matched_func = ''
                                break
                            matched_func = f
                            break

                    if matched_func:
                        callers.append({
                            'file': caller_file,
                            'function': matched_func,
                            'line': int(caller_line) if caller_line.isdigit() else 0,
                            'snippet': caller_content[:100],
                        })
            except (subprocess.TimeoutExpired, Exception):
                pass

        # Deduplicate by file+line
        seen: set[str] = set()
        unique: list[dict] = []
        for c in callers:
            key = f"{c['file']}:{c['line']}"
            if key not in seen:
                seen.add(key)
                unique.append(c)

        return unique

    # ── Find Tables ───────────────────────────────────────────────────

    def _find_tables(self, fpath: str) -> list[str]:
        """Find database tables this file interacts with."""
        full_path = self.root / fpath
        if not full_path.exists() or not fpath.endswith('.php'):
            return []

        try:
            content = full_path.read_text(encoding='utf-8', errors='ignore')
        except Exception:
            return []

        tables: set[str] = set()

        # CI3 active record patterns
        patterns = [
            r"""\$this\s*->\s*db\s*->\s*from\s*\(\s*['"](\w+)['"]\s*\)""",
            r"""\$this\s*->\s*db\s*->\s*insert\s*\(\s*['"](\w+)['"]\s*""",
            r"""\$this\s*->\s*db\s*->\s*update\s*\(\s*['"](\w+)['"]\s*""",
            r"""\$this\s*->\s*db\s*->\s*delete\s*\(\s*['"](\w+)['"]\s*""",
            r"""\$this\s*->\s*db\s*->\s*get\s*\(\s*['"](\w+)['"]\s*\)""",
            r"""\$this\s*->\s*db\s*->\s*get_where\s*\(\s*['"](\w+)['"]""",
            r"""\$this\s*->\s*db\s*->\s*join\s*\(\s*['"](\w+)['"]""",
            r"""\$this\s*->\s*db\s*->\s*count_all\s*\(\s*['"](\w+)['"]""",
        ]

        for pattern in patterns:
            for m in re.finditer(pattern, content):
                table = m.group(1)
                if not table.startswith('$'):  # Skip variable table names
                    tables.add(table)

        return sorted(tables)

    # ── Risk Score ────────────────────────────────────────────────────

    def _calculate_risk(self, fpath: str) -> float:
        """Calculate risk score (0.0-1.0) based on git churn and complexity."""
        score = 0.0

        # Factor 1: Git change frequency (churn)
        try:
            result = subprocess.run(
                ['git', 'log', '--oneline', '--follow', '-n', '50', '--', fpath],
                capture_output=True, text=True,
                cwd=str(self.root),
                timeout=10,
                encoding='utf-8', errors='replace',
            )
            commits = len([l for l in result.stdout.strip().split('\n') if l.strip()])
            # Normalize: 0 commits = 0, 50+ commits = 0.5
            score += min(commits / 100.0, 0.5)
        except Exception:
            pass

        # Factor 2: File size (proxy for complexity)
        full_path = self.root / fpath
        if full_path.exists():
            try:
                content = full_path.read_text(encoding='utf-8', errors='ignore')
                lines = len(content.split('\n'))
                # Normalize: 0 lines = 0, 1000+ lines = 0.3
                score += min(lines / 3333.0, 0.3)
            except Exception:
                pass

        # Factor 3: Known high-risk file names
        high_risk_names = [
            'common_model', 'auth_model', 'access_right',
            'admin_billing', 'admin_estimation',
        ]
        basename = Path(fpath).stem.lower()
        if any(hrn in basename for hrn in high_risk_names):
            score += 0.2

        return min(score, 1.0)

    # ── Business Rules ────────────────────────────────────────────────

    def _find_business_rules(self, fpath: str) -> list[str]:
        """Find business rules that reference functions in this file."""
        rules: list[str] = []
        brain_root = self.root / 'knowledge_brain'
        if not brain_root.exists():
            return rules

        module = self._detect_module(fpath)
        if not module:
            return rules

        rules_file = brain_root / module / 'BUSINESS_RULES.md'
        if not rules_file.exists():
            return rules

        try:
            content = rules_file.read_text(encoding='utf-8', errors='ignore')
            funcs = self._extract_functions(fpath)

            for func in funcs:
                if func.lower() in content.lower():
                    # Find the rule that mentions this function
                    for line in content.split('\n'):
                        if func.lower() in line.lower() and ('BR-' in line or 'RULE' in line):
                            rules.append(line.strip()[:120])
                            break
        except Exception:
            pass

        return rules

    # ── Danger Zones ──────────────────────────────────────────────────

    def _check_danger_zones(self, files: list[str]) -> list[str]:
        """Check if any target files are in known danger zones."""
        zones: list[str] = []
        danger_file = self.root / 'knowledge_brain' / '_SYSTEM' / 'DANGER_ZONES.md'

        if not danger_file.exists():
            return zones

        try:
            content = danger_file.read_text(encoding='utf-8', errors='ignore')
            for fpath in files:
                basename = Path(fpath).name.lower()
                if basename in content.lower():
                    zones.append(f'{Path(fpath).name} is listed in DANGER_ZONES.md')
        except Exception:
            pass

        # Also check for common high-risk patterns
        for fpath in files:
            lower = fpath.lower()
            if 'config/' in lower or 'config\\' in lower:
                zones.append(f'{Path(fpath).name} is a config file - requires approval')
            if 'helpers/' in lower or 'helpers\\' in lower:
                zones.append(f'{Path(fpath).name} is a global helper - check all importers')
            if 'common_model' in lower or 'auth_model' in lower:
                zones.append(f'{Path(fpath).name} is a shared model - check all callers')

        return zones

    # ── Helpers ────────────────────────────────────────────────────────

    def _detect_module(self, fpath: str) -> Optional[str]:
        """Detect module name from file path."""
        lower = fpath.lower().replace('\\', '/')
        module_keywords = [
            'billing', 'estimation', 'purchase', 'stock', 'inventory',
            'godown', 'customer', 'supplier', 'report', 'gst', 'account',
            'payment', 'receipt', 'journal', 'ledger', 'scheme', 'opening',
        ]
        for keyword in module_keywords:
            if keyword in lower:
                return keyword
        return None


# ── Text Formatter ────────────────────────────────────────────────────

def format_plan_text(matrix: ImpactMatrix) -> str:
    """Format the impact matrix as human-readable text."""
    lines: list[str] = []

    lines.append('=' * 60)
    lines.append('  SDLC Impact Matrix')
    lines.append('=' * 60)
    lines.append('')
    lines.append(f'  Complexity: {matrix.complexity}')
    lines.append(f'  Cross-module: {"YES" if matrix.cross_module else "No"}')
    lines.append(f'  Modules: {", ".join(matrix.modules_affected) or "N/A"}')
    lines.append(f'  Total callers: {matrix.total_callers}')
    lines.append(f'  Total tables: {matrix.total_tables}')
    lines.append(f'  Max risk score: {matrix.max_risk:.2f}')
    lines.append('')

    if matrix.danger_zones:
        lines.append('  [!] DANGER ZONES:')
        for dz in matrix.danger_zones:
            lines.append(f'      - {dz}')
        lines.append('')

    for fi in matrix.files:
        lines.append('-' * 60)
        lines.append(f'  File: {Path(fi.file).name}')
        lines.append(f'  Path: {fi.file}')
        lines.append(f'  Risk: {fi.risk_score:.2f}  Shared: {"YES" if fi.is_shared else "No"}')

        if fi.functions:
            lines.append(f'  Functions: {", ".join(fi.functions[:10])}')

        if fi.tables:
            lines.append(f'  Tables: {", ".join(fi.tables)}')

        if fi.callers:
            lines.append(f'  Callers ({len(fi.callers)}):')
            for c in fi.callers[:15]:
                lines.append(f'    - {c["file"]}:{c["line"]} -> {c["function"]}()')
            if len(fi.callers) > 15:
                lines.append(f'    ... and {len(fi.callers) - 15} more')

        if fi.business_rules:
            lines.append(f'  Business Rules:')
            for rule in fi.business_rules:
                lines.append(f'    - {rule}')

        lines.append('')

    lines.append('=' * 60)
    return '\n'.join(lines)
