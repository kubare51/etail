"""
Context Assembly Engine — the core of the LCA bug remediation pipeline.

Transforms a natural language bug description into a structured context
document that gives an AI agent all the knowledge it needs to diagnose
and fix the bug.

Pipeline:
  1. Semantic search → find relevant code + docs
  2. Module detection → identify which modules are involved
  3. Brain loading → pull business rules, data flows, danger zones
  4. Recipe search → find existing fixes for similar bugs
  5. Assembly → structure everything into context.md

Usage:
    from lca.context.assembler import ContextAssembler
    
    assembler = ContextAssembler(project_root="/path/to/project")
    context = assembler.assemble("discount calculation wrong on billing type 2")
    print(context.to_markdown())
"""
from __future__ import annotations

import os
import re
import json
import time
from dataclasses import dataclass, field
from pathlib import Path
from typing import Optional


# ══════════════════════════════════════════════════════════════════════════════
# Data models
# ══════════════════════════════════════════════════════════════════════════════

@dataclass
class CodeResult:
    """A single RAG search result."""
    id: str
    file_path: str
    module: str
    chunk_type: str
    summary: str
    relevance: float  # 0-100
    start_line: int = 0
    end_line: int = 0
    text: str = ''
    tables: str = ''
    models: str = ''
    method_name: str = ''
    class_name: str = ''


@dataclass
class BrainSection:
    """A section loaded from a module brain document."""
    module: str
    document: str  # e.g., BUSINESS_RULES.md
    section_title: str
    content: str
    doc_type: str = ''  # business_rule, data_flow, danger_zone, etc.


@dataclass
class Recipe:
    """A bug fix recipe from the central repo."""
    filename: str
    title: str
    symptom: str
    module: str
    fix_summary: str
    confidence: float = 0.0  # How well it matches


@dataclass
class AssembledContext:
    """The fully assembled context document."""
    query: str
    detected_modules: list[str] = field(default_factory=list)
    code_results: list[CodeResult] = field(default_factory=list)
    brain_sections: list[BrainSection] = field(default_factory=list)
    system_brain_sections: list[BrainSection] = field(default_factory=list)
    recipes: list[Recipe] = field(default_factory=list)
    assembly_time_sec: float = 0.0
    
    def to_markdown(self) -> str:
        """Render the assembled context as a structured markdown document."""
        lines = []
        lines.append(f'# Bug Context: {self.query}')
        lines.append(f'')
        lines.append(f'> Auto-assembled by LCA Context Engine')
        lines.append(f'> Modules: {", ".join(self.detected_modules) if self.detected_modules else "unknown"}')
        lines.append(f'> Assembly time: {self.assembly_time_sec:.1f}s')
        lines.append('')
        
        # ── Recipes (highest priority — existing fixes) ──
        if self.recipes:
            lines.append('---')
            lines.append('## 🔁 Matching Recipes (Existing Fixes)')
            lines.append('')
            for r in self.recipes:
                lines.append(f'### {r.title}')
                lines.append(f'- **File:** `{r.filename}`')
                lines.append(f'- **Module:** {r.module}')
                lines.append(f'- **Symptom:** {r.symptom}')
                lines.append(f'- **Fix:** {r.fix_summary}')
                lines.append('')
        
        # ── Code Results ──
        if self.code_results:
            lines.append('---')
            lines.append('## 🔍 Relevant Code (RAG Search)')
            lines.append('')
            for i, r in enumerate(self.code_results, 1):
                lines.append(f'### #{i} {r.summary or r.id}')
                lines.append(f'- **File:** `{r.file_path}` (L{r.start_line}-L{r.end_line})')
                lines.append(f'- **Module:** {r.module} | **Type:** {r.chunk_type} | **Relevance:** {r.relevance:.0f}%')
                if r.tables:
                    lines.append(f'- **Tables:** {r.tables}')
                if r.models:
                    lines.append(f'- **Models:** {r.models}')
                if r.text:
                    # Show first 40 lines of code
                    code_lines = r.text.split('\n')[:40]
                    ext = Path(r.file_path).suffix.lstrip('.')
                    lang = {'php': 'php', 'js': 'javascript', 'md': 'markdown'}.get(ext, ext)
                    lines.append(f'```{lang}')
                    lines.extend(code_lines)
                    if len(r.text.split('\n')) > 40:
                        lines.append(f'// ... ({len(r.text.split(chr(10))) - 40} more lines)')
                    lines.append('```')
                lines.append('')
        
        # ── Business Rules ──
        biz_rules = [s for s in self.brain_sections if s.doc_type in ('business_rule', '')]
        if biz_rules:
            lines.append('---')
            lines.append('## 📋 Business Rules')
            lines.append('')
            for s in biz_rules:
                lines.append(f'### [{s.module}] {s.section_title}')
                lines.append(f'_Source: {s.document}_')
                lines.append('')
                lines.append(s.content)
                lines.append('')
        
        # ── Data Flows ──
        data_flows = [s for s in self.brain_sections if s.doc_type == 'data_flow']
        if data_flows:
            lines.append('---')
            lines.append('## 🔄 Data Flows')
            lines.append('')
            for s in data_flows:
                lines.append(f'### [{s.module}] {s.section_title}')
                lines.append(s.content)
                lines.append('')
        
        # ── Danger Zones ──
        danger = [s for s in self.brain_sections if s.doc_type == 'danger_zone']
        if danger:
            lines.append('---')
            lines.append('## ⚠️ Danger Zones')
            lines.append('')
            for s in danger:
                lines.append(f'### [{s.module}] {s.section_title}')
                lines.append(s.content)
                lines.append('')
        
        # ── System Brain (Cross-Module) ──
        if self.system_brain_sections:
            lines.append('---')
            lines.append('## 🌐 Cross-Module Context (System Brain)')
            lines.append('')
            for s in self.system_brain_sections:
                lines.append(f'### {s.section_title}')
                lines.append(f'_Source: {s.document}_')
                lines.append('')
                lines.append(s.content)
                lines.append('')
        
        return '\n'.join(lines)


# ══════════════════════════════════════════════════════════════════════════════
# Context Assembler
# ══════════════════════════════════════════════════════════════════════════════

# Brain document types to load, mapped by filename patterns
BRAIN_DOC_TYPES = {
    'BUSINESS_RULES': 'business_rule',
    'DATA_FLOW': 'data_flow',
    'DANGER_ZONE': 'danger_zone',
    'FIX_GUIDE': 'fix_guide',
    'CONTROLLER_MAP': 'controller_map',
    'MODEL_MAP': 'model_map',
    'AJAX_MAP': 'ajax_map',
    'VIEW_MAP': 'view_map',
    'ACTIVE_BUGS': 'active_bugs',
    'MODULE_BRAIN': 'module_brain',
    'SHARED_DEPENDENCIES': 'shared_deps',
    'METHOD_INDEX': 'method_index',
    'QUICK_REFERENCE': 'quick_ref',
}

# Priority docs — always load for bug context (small, high-value)
PRIORITY_DOC_TYPES = {'business_rule', 'data_flow', 'danger_zone', 'fix_guide'}

# Skip these docs — too verbose or low-value for bug context
SKIP_DOC_PATTERNS = {
    'COVERAGE_TRACKER', 'FORENSIC_TEMPLATE', 'INVARIANT_MATRIX',
    'DB_VERIFY', 'LESSONS_LEARNED', 'SCHEMA_ANALYSIS',
    'FLOW_RISK', 'BRAIN_COMPLETE', 'CROSS_MODULE_MAP',
}

# System brain docs to load for cross-module context
SYSTEM_BRAIN_DOCS = [
    'SHARED_TABLES.md',
    'DANGER_ZONES.md',
    'CROSS_MODULE_BUGS.md',
    'MODULE_DEPENDENCIES.md',
]


class ContextAssembler:
    """
    Assembles rich context from a bug description.
    
    Args:
        project_root: Path to the project root
        store_dir: Path to ChromaDB store (default: ~/.logimax/rag_store)
        brain_dir: Brain directory name (default: knowledge_brain)
    """
    
    def __init__(
        self,
        project_root: str = '.',
        store_dir: str = '',
        brain_dir: str = 'knowledge_brain',
    ):
        self.project_root = Path(project_root).resolve()
        self.store_dir = store_dir or os.path.join(
            os.path.expanduser('~'), '.logimax', 'rag_store'
        )
        self.brain_dir = self.project_root / brain_dir
    
    def assemble(
        self,
        query: str,
        top_k: int = 15,
        include_code: bool = True,
        include_brain: bool = True,
        include_system: bool = True,
        include_recipes: bool = True,
        module_filter: Optional[str] = None,
    ) -> AssembledContext:
        """
        Assemble context for a bug description.
        
        Args:
            query: Natural language bug description
            top_k: Number of RAG results to include
            include_code: Include RAG code results
            include_brain: Include module brain sections
            include_system: Include system brain sections
            include_recipes: Include recipe matches
            module_filter: Restrict to specific module
            
        Returns:
            AssembledContext with all gathered information
        """
        t_start = time.time()
        ctx = AssembledContext(query=query)
        
        # Step 1: RAG search
        if include_code:
            ctx.code_results = self._search_rag(query, top_k, module_filter)
        
        # Step 2: Detect modules
        ctx.detected_modules = self._detect_modules(query, ctx.code_results, module_filter)
        
        # Step 3: Load brain sections
        if include_brain and ctx.detected_modules:
            ctx.brain_sections = self._load_brain_sections(ctx.detected_modules, query)
        
        # Step 4: Load system brain
        if include_system and len(ctx.detected_modules) > 0:
            ctx.system_brain_sections = self._load_system_brain(ctx.detected_modules)
        
        # Step 5: Search recipes
        if include_recipes:
            ctx.recipes = self._search_recipes(query, ctx.detected_modules)
        
        ctx.assembly_time_sec = time.time() - t_start
        return ctx
    
    # ── Step 1: RAG Search ────────────────────────────────────────────────
    
    def _search_rag(
        self, query: str, top_k: int, module_filter: Optional[str]
    ) -> list[CodeResult]:
        """Search the vector store for relevant code and documentation."""
        try:
            from lca.semantic.store import hybrid_query_chunks
        except ImportError:
            return []
        
        where_filter = {}
        if module_filter:
            where_filter['module'] = module_filter
        
        results = hybrid_query_chunks(
            query=query,
            persist_dir=self.store_dir,
            n_results=top_k,
            where_filter=where_filter if where_filter else None,
        )
        
        code_results = []
        for r in results:
            meta = r.get('metadata', {})
            distance = r.get('distance', 1.0)
            relevance = max(0, (1 - distance) * 100)
            
            code_results.append(CodeResult(
                id=r.get('id', ''),
                file_path=meta.get('file_path', ''),
                module=meta.get('module', 'unknown'),
                chunk_type=meta.get('chunk_type', ''),
                summary=meta.get('summary', r.get('id', '')),
                relevance=relevance,
                start_line=int(meta.get('start_line', 0)),
                end_line=int(meta.get('end_line', 0)),
                text=r.get('document', ''),
                tables=meta.get('tables', ''),
                models=meta.get('models', ''),
                method_name=meta.get('method_name', ''),
                class_name=meta.get('class_name', ''),
            ))
        
        return code_results
    
    # ── Step 2: Module Detection ──────────────────────────────────────────
    
    def _detect_modules(
        self,
        query: str,
        code_results: list[CodeResult],
        module_filter: Optional[str],
    ) -> list[str]:
        """Detect which modules are relevant from query + search results."""
        if module_filter:
            return [module_filter]
        
        # Count module occurrences in results, weighted by relevance
        module_scores: dict[str, float] = {}
        for r in code_results:
            mod = r.module
            if mod and mod != 'unknown':
                module_scores[mod] = module_scores.get(mod, 0) + r.relevance
        
        # Also check query text against known module names
        query_lower = query.lower()
        MODULE_ALIASES = {
            'billing': ['billing', 'bill', 'invoice', 'gst'],
            'estimation': ['estimation', 'estimate', 'quotation', 'quote'],
            'customer': ['customer', 'client'],
            'stock': ['stock', 'inventory', 'warehouse', 'godown'],
            'stock_issue': ['stock issue', 'stock_issue', 'issue note'],
            'purchase': ['purchase', 'po', 'supplier', 'vendor'],
            'order': ['order', 'booking'],
            'employee': ['employee', 'salary', 'attendance', 'payroll'],
            'accounts': ['accounts', 'ledger', 'journal', 'voucher'],
            'payment': ['payment', 'receipt', 'collection'],
            'reports': ['report', 'dashboard'],
            'settings': ['settings', 'config'],
            'auth': ['auth', 'login', 'permission', 'access'],
        }
        
        for module, aliases in MODULE_ALIASES.items():
            for alias in aliases:
                if alias in query_lower:
                    module_scores[module] = module_scores.get(module, 0) + 200  # Boost explicit mentions
        
        # Sort by score, return top modules (max 3)
        sorted_modules = sorted(module_scores.items(), key=lambda x: -x[1])
        return [m for m, _ in sorted_modules[:3]]
    
    # ── Step 3: Brain Loading ─────────────────────────────────────────────
    
    def _load_brain_sections(
        self, modules: list[str], query: str
    ) -> list[BrainSection]:
        """Load relevant brain sections for detected modules."""
        sections = []
        
        for module in modules:
            # Check both exact name and common variations
            module_dirs = self._find_brain_dirs(module)
            
            for mod_dir in module_dirs:
                if not mod_dir.exists():
                    continue
                
                for md_file in sorted(mod_dir.glob('*.md')):
                    doc_name = md_file.stem.upper()
                    doc_type = ''
                    
                    # Skip known verbose/low-value docs
                    if any(skip in doc_name for skip in SKIP_DOC_PATTERNS):
                        continue
                    
                    # Determine document type
                    for pattern, dtype in BRAIN_DOC_TYPES.items():
                        if pattern in doc_name:
                            doc_type = dtype
                            break
                    
                    # Filter by doc relevance to bug context
                    is_priority = doc_type in PRIORITY_DOC_TYPES
                    
                    if not is_priority:
                        # Only load secondary docs if query mentions their domain
                        query_lower = query.lower()
                        if doc_type == 'controller_map' and not any(
                            kw in query_lower for kw in ['controller', 'route', 'endpoint', 'url', 'ajax']
                        ):
                            continue
                        elif doc_type == 'model_map' and not any(
                            kw in query_lower for kw in ['model', 'query', 'database', 'table', 'sql']
                        ):
                            continue
                        elif doc_type == 'method_index' and not any(
                            kw in query_lower for kw in ['method', 'function', 'call', 'trace']
                        ):
                            continue
                        elif doc_type in ('ajax_map', 'view_map'):
                            if not any(kw in query_lower for kw in ['ajax', 'view', 'form', 'ui', 'print', 'page']):
                                continue
                        elif doc_type == 'active_bugs':
                            pass  # Always include active bugs list
                        elif doc_type == 'module_brain':
                            pass  # Always include module brain summary
                        elif doc_type == '' and not is_priority:
                            # Unknown doc type — skip unless query is very specific
                            continue
                    
                    try:
                        content = md_file.read_text(encoding='utf-8', errors='ignore')
                    except OSError:
                        continue
                    
                    if len(content.strip()) < 50:
                        continue
                    
                    # Extract relevant sections (don't dump entire 500-line doc)
                    relevant = self._extract_relevant_sections(content, query)
                    
                    if relevant:
                        sections.append(BrainSection(
                            module=module,
                            document=f'{module}/{md_file.name}',
                            section_title=md_file.stem.replace('_', ' ').title(),
                            content=relevant,
                            doc_type=doc_type,
                        ))
        
        return sections
    
    def _find_brain_dirs(self, module: str) -> list[Path]:
        """Find brain directories matching a module name."""
        dirs = []
        
        # Exact match
        exact = self.brain_dir / module
        if exact.exists():
            dirs.append(exact)
        
        # Case-insensitive search
        if not dirs and self.brain_dir.exists():
            for d in self.brain_dir.iterdir():
                if d.is_dir() and d.name.lower() == module.lower():
                    dirs.append(d)
        
        # Also check _auto/ directory
        auto = self.brain_dir / '_auto' / module
        if auto.exists():
            dirs.append(auto)
        
        return dirs
    
    def _extract_relevant_sections(self, content: str, query: str) -> str:
        """Extract sections from a brain doc that are relevant to the query."""
        query_terms = set(query.lower().split())
        
        # Split by ## headings
        sections = re.split(r'\n(?=##\s)', content)
        
        scored_sections = []
        for section in sections:
            # Score by keyword overlap
            section_lower = section.lower()
            matches = sum(1 for term in query_terms if term in section_lower)
            
            if matches > 0 or len(sections) <= 3:
                # Include if any query term matches, or if the doc is small
                scored_sections.append((matches, section.strip()))
        
        # Sort by relevance, take top 5 sections
        scored_sections.sort(key=lambda x: -x[0])
        top_sections = [s for _, s in scored_sections[:5]]
        
        # Cap total output at ~3000 chars
        result = '\n\n'.join(top_sections)
        if len(result) > 3000:
            result = result[:3000] + '\n\n... (truncated)'
        
        return result
    
    # ── Step 4: System Brain ──────────────────────────────────────────────
    
    def _load_system_brain(self, modules: list[str]) -> list[BrainSection]:
        """Load relevant system brain sections for cross-module context."""
        sections = []
        system_dir = self.brain_dir / '_SYSTEM'
        
        if not system_dir.exists():
            return sections
        
        for doc_name in SYSTEM_BRAIN_DOCS:
            doc_path = system_dir / doc_name
            if not doc_path.exists():
                continue
            
            try:
                content = doc_path.read_text(encoding='utf-8', errors='ignore')
            except OSError:
                continue
            
            # Extract sections that mention any of our detected modules
            relevant = self._extract_module_sections(content, modules)
            
            if relevant:
                sections.append(BrainSection(
                    module='_SYSTEM',
                    document=f'_SYSTEM/{doc_name}',
                    section_title=doc_name.replace('.md', '').replace('_', ' ').title(),
                    content=relevant,
                    doc_type='system',
                ))
        
        return sections
    
    def _extract_module_sections(self, content: str, modules: list[str]) -> str:
        """Extract sections from system brain that mention our modules."""
        sections = re.split(r'\n(?=##\s)', content)
        relevant = []
        
        for section in sections:
            section_lower = section.lower()
            for mod in modules:
                if mod.lower() in section_lower:
                    relevant.append(section.strip())
                    break
        
        result = '\n\n'.join(relevant[:5])
        if len(result) > 3000:
            result = result[:3000] + '\n\n... (truncated)'
        
        return result
    
    # ── Step 5: Recipe Search ─────────────────────────────────────────────
    
    def _search_recipes(
        self, query: str, modules: list[str]
    ) -> list[Recipe]:
        """Search for matching bug fix recipes."""
        recipes = []
        
        # Check local recipes directory
        recipe_dirs = [
            self.project_root / 'bug-recipes' / 'recipes',
            self.project_root.parent / 'bug-recipes' / 'recipes',
            Path(os.path.expanduser('~')) / '.logimax' / 'bug-recipes' / 'recipes',
        ]
        
        query_lower = query.lower()
        query_terms = set(query_lower.split())
        
        for recipe_dir in recipe_dirs:
            if not recipe_dir.exists():
                continue
            
            for recipe_file in recipe_dir.rglob('*.md'):
                if recipe_file.name.startswith('_'):
                    continue  # Skip templates
                
                try:
                    content = recipe_file.read_text(encoding='utf-8', errors='ignore')
                except OSError:
                    continue
                
                # Quick relevance check — count query term matches in recipe
                content_lower = content.lower()
                matches = sum(1 for term in query_terms if term in content_lower)
                
                # Also check module match
                module_match = any(mod.lower() in content_lower for mod in modules)
                
                if matches >= 2 or (matches >= 1 and module_match):
                    # Parse recipe frontmatter
                    title = recipe_file.stem.replace('-', ' ').replace('_', ' ').title()
                    symptom = ''
                    fix_summary = ''
                    recipe_module = ''
                    
                    # Extract key fields from recipe content
                    for line in content.split('\n'):
                        line_stripped = line.strip()
                        if line_stripped.startswith('# '):
                            title = line_stripped[2:]
                        elif 'symptom' in line_stripped.lower() and ':' in line_stripped:
                            symptom = line_stripped.split(':', 1)[1].strip()
                        elif 'module' in line_stripped.lower() and ':' in line_stripped:
                            recipe_module = line_stripped.split(':', 1)[1].strip()
                        elif 'fix' in line_stripped.lower() and ':' in line_stripped:
                            fix_summary = line_stripped.split(':', 1)[1].strip()
                    
                    recipes.append(Recipe(
                        filename=str(recipe_file.relative_to(recipe_dir.parent)),
                        title=title,
                        symptom=symptom or '(see recipe)',
                        module=recipe_module or (modules[0] if modules else ''),
                        fix_summary=fix_summary or '(see recipe)',
                        confidence=matches / max(len(query_terms), 1),
                    ))
        
        # Sort by confidence
        recipes.sort(key=lambda r: -r.confidence)
        return recipes[:5]  # Top 5


# ══════════════════════════════════════════════════════════════════════════════
# Convenience function
# ══════════════════════════════════════════════════════════════════════════════

def assemble_context(
    query: str,
    project_root: str = '.',
    store_dir: str = '',
    top_k: int = 15,
    module: Optional[str] = None,
    output_path: Optional[str] = None,
) -> AssembledContext:
    """
    One-shot context assembly.
    
    Args:
        query: Bug description
        project_root: Project root path
        store_dir: ChromaDB store path
        top_k: Number of RAG results
        module: Optional module filter
        output_path: If set, write context.md to this path
    
    Returns:
        AssembledContext
    """
    assembler = ContextAssembler(
        project_root=project_root,
        store_dir=store_dir,
    )
    
    ctx = assembler.assemble(
        query=query,
        top_k=top_k,
        module_filter=module,
    )
    
    if output_path:
        Path(output_path).write_text(ctx.to_markdown(), encoding='utf-8')
    
    return ctx
