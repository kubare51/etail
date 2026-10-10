"""Flow Explainer — generates plain-English business flow documentation from LCA index.

Given a function name, traces the full call chain upstream (who triggers it)
and downstream (what it calls), annotates each step with formulas, accumulators,
conditionals, and magic numbers, then renders a structured markdown narrative.

Usage:
    explainer = FlowExplainer(index)
    result = explainer.explain('billing_model::calculate_total')
    print(result.markdown)
"""

from __future__ import annotations

from dataclasses import dataclass, field
from pathlib import Path
from collections import defaultdict

from lca.models import (
    ModuleIndex, FunctionDef, FunctionCall,
    FormulaExpr, Accumulator, ConditionalBranch, MagicNumber,
)
from lca.core.impact import ImpactAnalyzer


# ── Layer classification ──────────────────────────────────────────────

def _classify_layer(file_path: str, call_type: str = '') -> str:
    """Classify a function into its architectural layer."""
    fp = file_path.lower().replace('\\', '/')

    if call_type.startswith('db_'):
        return 'db'
    if call_type == 'load_view':
        return 'view'

    if '/assets/js/' in fp or '/js/' in fp or fp.endswith(('.js', '.jsx', '.ts')):
        return 'js'
    if '/controllers/' in fp or 'controller' in Path(fp).stem.lower():
        return 'controller'
    if '/models/' in fp or '_model' in Path(fp).stem.lower():
        return 'model'
    if '/views/' in fp:
        return 'view'
    if '/helpers/' in fp:
        return 'helper'

    return 'unknown'


_LAYER_ORDER = {'js': 0, 'controller': 1, 'model': 2, 'helper': 3, 'db': 4, 'view': 5, 'unknown': 6}
_LAYER_ICONS = {
    'js': '🌐', 'controller': '🎮', 'model': '⚙️',
    'helper': '🔧', 'db': '💾', 'view': '👁️', 'unknown': '❓',
}
_LAYER_LABELS = {
    'js': 'JavaScript', 'controller': 'Controller', 'model': 'Model',
    'helper': 'Helper', 'db': 'Database', 'view': 'View', 'unknown': 'Other',
}


# ── Output models ─────────────────────────────────────────────────────

@dataclass
class FlowStep:
    """A single step in the business flow."""
    order: int
    layer: str
    function_name: str
    file: str
    line: int
    direction: str  # 'upstream' (triggers this), 'target' (the queried func), 'downstream' (called by this)
    formulas: list[FormulaExpr] = field(default_factory=list)
    accumulators: list[Accumulator] = field(default_factory=list)
    conditionals: list[ConditionalBranch] = field(default_factory=list)
    magic_numbers: list[MagicNumber] = field(default_factory=list)
    db_operations: list[dict] = field(default_factory=list)
    sub_calls: list[str] = field(default_factory=list)


@dataclass
class FlowExplanation:
    """Complete flow explanation for a function."""
    target: str
    target_file: str
    steps: list[FlowStep] = field(default_factory=list)
    tables_touched: list[str] = field(default_factory=list)
    total_callers: int = 0
    risk_level: str = 'Low'
    markdown: str = ''
    mermaid: str = ''


# ── Main engine ───────────────────────────────────────────────────────

class FlowExplainer:
    """Generate structured flow narratives from LCA index data."""

    def __init__(self, index: ModuleIndex):
        self.index = index
        self.impact = ImpactAnalyzer(index)

        # Pre-build lookup maps
        self._func_map: dict[str, FunctionDef] = {f.name: f for f in index.functions}
        self._func_map_lower: dict[str, FunctionDef] = {f.name.lower(): f for f in index.functions}

        # Call maps
        self._calls_by_source: dict[str, list[FunctionCall]] = defaultdict(list)
        self._calls_by_target: dict[str, list[FunctionCall]] = defaultdict(list)
        for c in index.calls:
            self._calls_by_source[c.source].append(c)
            self._calls_by_target[c.target].append(c)

        # Deep analysis maps (by function name)
        self._formulas_by_func: dict[str, list[FormulaExpr]] = defaultdict(list)
        for f in index.formulas:
            self._formulas_by_func[f.function].append(f)

        self._accums_by_func: dict[str, list[Accumulator]] = defaultdict(list)
        for a in index.accumulators:
            self._accums_by_func[a.function].append(a)

        self._conds_by_func: dict[str, list[ConditionalBranch]] = defaultdict(list)
        for c in index.conditionals:
            self._conds_by_func[c.function].append(c)

        self._magic_by_func: dict[str, list[MagicNumber]] = defaultdict(list)
        for m in index.magic_numbers:
            self._magic_by_func[m.function].append(m)

    def explain(self, query: str, depth: int = 3) -> FlowExplanation:
        """Build full flow explanation for a function.

        Args:
            query: Function name (exact or partial match)
            depth: Max depth to trace downstream calls

        Returns:
            FlowExplanation with steps, diagram, and markdown
        """
        # ── 1. Resolve function name (fuzzy match) ────────────────────
        func_def = self._resolve_function(query)
        if not func_def:
            return FlowExplanation(
                target=query,
                target_file='',
                markdown=f'❌ Function not found: `{query}`\n\nTip: Use partial name like `calculate_total` instead of the full path.',
            )

        target_name = func_def.name
        target_file = func_def.file
        result = FlowExplanation(target=target_name, target_file=target_file)

        steps: list[FlowStep] = []
        visited: set[str] = set()
        tables: set[str] = set()

        # ── 2. Trace UPSTREAM (who triggers this function) ────────────
        upstream_calls = self._calls_by_target.get(target_name, [])

        # Separate AJAX callers (JS→PHP) from internal callers
        ajax_callers = [c for c in upstream_calls if c.call_type == 'ajax_call']
        internal_callers = [c for c in upstream_calls if c.call_type != 'ajax_call']

        for c in ajax_callers:
            if c.source in visited:
                continue
            visited.add(c.source)
            meta = c.metadata or {}
            step = FlowStep(
                order=0,
                layer='js',
                function_name=c.source,
                file=c.file,
                line=c.line,
                direction='upstream',
            )
            step.formulas = self._formulas_by_func.get(c.source, [])
            step.accumulators = self._accums_by_func.get(c.source, [])
            steps.append(step)

        for c in internal_callers[:10]:  # Limit to prevent explosion
            if c.source in visited or c.source == target_name:
                continue
            visited.add(c.source)
            caller_def = self._func_map.get(c.source)
            caller_file = caller_def.file if caller_def else c.file
            step = FlowStep(
                order=0,
                layer=_classify_layer(caller_file),
                function_name=c.source,
                file=caller_file,
                line=c.line,
                direction='upstream',
            )
            steps.append(step)

        result.total_callers = len(upstream_calls)

        # ── 3. The target function itself ─────────────────────────────
        target_step = FlowStep(
            order=0,
            layer=_classify_layer(target_file),
            function_name=target_name,
            file=target_file,
            line=func_def.line,
            direction='target',
            formulas=self._formulas_by_func.get(target_name, []),
            accumulators=self._accums_by_func.get(target_name, []),
            conditionals=self._conds_by_func.get(target_name, []),
            magic_numbers=self._magic_by_func.get(target_name, []),
        )
        steps.append(target_step)

        # ── 4. Trace DOWNSTREAM (what does it call) ───────────────────
        self._trace_downstream(target_name, steps, visited, tables, depth, current_depth=0)

        # ── 5. Assign step order ──────────────────────────────────────
        # Sort: upstream first (by layer), then target, then downstream (by layer)
        upstream = sorted(
            [s for s in steps if s.direction == 'upstream'],
            key=lambda s: _LAYER_ORDER.get(s.layer, 99),
        )
        target_steps = [s for s in steps if s.direction == 'target']
        downstream = sorted(
            [s for s in steps if s.direction == 'downstream'],
            key=lambda s: _LAYER_ORDER.get(s.layer, 99),
        )

        ordered = upstream + target_steps + downstream
        for i, step in enumerate(ordered, 1):
            step.order = i

        result.steps = ordered
        result.tables_touched = sorted(tables)

        # ── 6. Risk assessment ────────────────────────────────────────
        caller_count = len(upstream_calls)
        table_count = len(tables)
        if caller_count > 15 or table_count > 5:
            result.risk_level = 'High'
        elif caller_count > 5 or table_count > 2:
            result.risk_level = 'Medium'
        else:
            result.risk_level = 'Low'

        # ── 7. Generate outputs ───────────────────────────────────────
        result.mermaid = self._generate_mermaid(result)
        result.markdown = self._generate_markdown(result)

        return result

    # ── Internal helpers ──────────────────────────────────────────────

    def _resolve_function(self, query: str) -> FunctionDef | None:
        """Fuzzy-match a function name from the index."""
        # Exact match
        if query in self._func_map:
            return self._func_map[query]

        # Case-insensitive exact
        q_lower = query.lower()
        if q_lower in self._func_map_lower:
            return self._func_map_lower[q_lower]

        # Partial match — prefer methods over globals
        candidates = []
        for name, func in self._func_map.items():
            if q_lower in name.lower():
                # Score: exact suffix match > contains
                bare = name.split('::')[-1].lower() if '::' in name else name.lower()
                score = 3 if bare == q_lower else (2 if bare.startswith(q_lower) else 1)
                candidates.append((score, name, func))

        if not candidates:
            return None

        # Sort by score desc, then by name length (shorter = more specific)
        candidates.sort(key=lambda x: (-x[0], len(x[1])))
        return candidates[0][2]

    def _trace_downstream(
        self,
        func_name: str,
        steps: list[FlowStep],
        visited: set[str],
        tables: set[str],
        max_depth: int,
        current_depth: int,
    ):
        """Recursively trace downstream calls."""
        if current_depth >= max_depth:
            return

        downstream_calls = self._calls_by_source.get(func_name, [])

        for call in downstream_calls:
            # DB operations
            if call.call_type in ('db_read', 'db_write'):
                table = call.target.replace('TABLE:', '')
                tables.add(table)
                # Add DB step
                db_key = f'DB:{table}'
                if db_key not in visited:
                    visited.add(db_key)
                    op = 'READ' if call.call_type == 'db_read' else 'WRITE'
                    step = FlowStep(
                        order=0,
                        layer='db',
                        function_name=table,
                        file=call.file,
                        line=call.line,
                        direction='downstream',
                        db_operations=[{'table': table, 'operation': op}],
                    )
                    steps.append(step)
                continue

            # View loads
            if call.call_type == 'load_view':
                view_key = f'VIEW:{call.target}'
                if view_key not in visited:
                    visited.add(view_key)
                    step = FlowStep(
                        order=0,
                        layer='view',
                        function_name=call.target,
                        file=call.file,
                        line=call.line,
                        direction='downstream',
                    )
                    steps.append(step)
                continue

            # Regular function calls
            if call.target in visited or call.target == func_name:
                continue
            visited.add(call.target)

            callee_def = self._func_map.get(call.target)
            callee_file = callee_def.file if callee_def else (call.target_file or call.file)
            callee_line = callee_def.line if callee_def else call.line

            step = FlowStep(
                order=0,
                layer=_classify_layer(callee_file, call.call_type),
                function_name=call.target,
                file=callee_file,
                line=callee_line,
                direction='downstream',
                formulas=self._formulas_by_func.get(call.target, []),
                accumulators=self._accums_by_func.get(call.target, []),
                conditionals=self._conds_by_func.get(call.target, []),
                magic_numbers=self._magic_by_func.get(call.target, []),
            )

            # Collect sub-calls for display
            sub_calls_raw = self._calls_by_source.get(call.target, [])
            step.sub_calls = [
                c.target for c in sub_calls_raw[:5]
                if c.call_type not in ('db_read', 'db_write', 'load_view')
            ]

            steps.append(step)

            # Recurse for model calls (they often chain to DB)
            if call.call_type in ('model_call', 'calls'):
                self._trace_downstream(call.target, steps, visited, tables, max_depth, current_depth + 1)

    def _generate_mermaid(self, result: FlowExplanation) -> str:
        """Generate a Mermaid flow diagram."""
        lines = ['graph LR']
        edges: list[str] = []
        node_ids: dict[str, str] = {}

        def _node_id(name: str) -> str:
            safe = name.replace('::', '_').replace(' ', '_').replace('-', '_')
            safe = ''.join(c for c in safe if c.isalnum() or c == '_')
            node_ids[name] = safe
            return safe

        # Collect edges from steps
        upstream = [s for s in result.steps if s.direction == 'upstream']
        target = [s for s in result.steps if s.direction == 'target']
        downstream = [s for s in result.steps if s.direction == 'downstream']

        target_name = result.target
        target_id = _node_id(target_name)

        # Upstream → target
        for s in upstream:
            src_id = _node_id(s.function_name)
            icon = _LAYER_ICONS.get(s.layer, '')
            label = s.function_name.split('::')[-1] if '::' in s.function_name else s.function_name
            lines.append(f'  {src_id}["{icon} {label}"]')
            edge_label = 'AJAX' if s.layer == 'js' else 'calls'
            edges.append(f'  {src_id} -->|{edge_label}| {target_id}')

        # Target node
        target_label = target_name.split('::')[-1] if '::' in target_name else target_name
        target_icon = _LAYER_ICONS.get(_classify_layer(result.target_file), '⚙️')
        lines.append(f'  {target_id}["{target_icon} {target_label}"]')

        # Target → downstream
        for s in downstream:
            dst_id = _node_id(s.function_name)
            icon = _LAYER_ICONS.get(s.layer, '')
            label = s.function_name.split('::')[-1] if '::' in s.function_name else s.function_name

            if s.layer == 'db':
                op = s.db_operations[0]['operation'] if s.db_operations else 'ACCESS'
                lines.append(f'  {dst_id}[("{icon} {label}")]')
                edges.append(f'  {target_id} -->|{op}| {dst_id}')
            elif s.layer == 'view':
                lines.append(f'  {dst_id}["{icon} {label}"]')
                edges.append(f'  {target_id} -->|loads| {dst_id}')
            else:
                lines.append(f'  {dst_id}["{icon} {label}"]')
                edges.append(f'  {target_id} --> {dst_id}')

        lines.extend(edges)
        return '\n'.join(lines)

    def _generate_markdown(self, result: FlowExplanation) -> str:
        """Generate full markdown explanation."""
        md = []
        md.append(f'## Flow: `{result.target}`\n')
        md.append(f'**📍 Location:** `{Path(result.target_file).name}:L{result.steps[0].line if result.steps else "?"}`')
        md.append(f'**⚠️ Risk:** {result.risk_level} ({result.total_callers} callers, {len(result.tables_touched)} tables)\n')

        # ── Upstream ──
        upstream = [s for s in result.steps if s.direction == 'upstream']
        if upstream:
            md.append('### ⬆ Triggered By\n')
            for s in upstream:
                icon = _LAYER_ICONS.get(s.layer, '')
                fname = Path(s.file).name if s.file else '?'
                md.append(f'- {icon} `{s.function_name}` ({fname}:L{s.line})')
            md.append('')

        # ── Target function detail ──
        target_steps = [s for s in result.steps if s.direction == 'target']
        if target_steps:
            ts = target_steps[0]

            if ts.formulas:
                md.append('### 🔢 Formulas\n')
                md.append('```')
                for f in ts.formulas[:15]:
                    prec = f' [{f.precision}]' if f.precision else ''
                    md.append(f'{f.variable} = {f.expression[:80]}{prec}')
                md.append('```\n')

            if ts.accumulators:
                md.append('### 📊 Accumulators\n')
                md.append('```')
                for a in ts.accumulators[:10]:
                    wrap = f'{a.wrapper}(' if a.wrapper else ''
                    wrap_end = ')' if a.wrapper else ''
                    op = '+=' if a.direction == 'add' else '-='
                    md.append(f'{a.variable} {op} {wrap}{a.source_field[:60]}{wrap_end}')
                md.append('```\n')

            if ts.conditionals:
                md.append('### 🔀 Business Rules\n')
                md.append('```')
                # Group by variable for dispatch tables
                by_var: dict[str, list[ConditionalBranch]] = defaultdict(list)
                for c in ts.conditionals:
                    by_var[c.variable].append(c)
                for var, branches in list(by_var.items())[:10]:
                    if len(branches) > 1:
                        md.append(f'switch ({var}):')
                        for b in branches[:8]:
                            action = f' → {b.action_summary[:50]}' if b.action_summary else ''
                            md.append(f'  case {b.value}{action}')
                    else:
                        b = branches[0]
                        action = f' → {b.action_summary[:50]}' if b.action_summary else ''
                        md.append(f'if ({var} {b.operator} {b.value}){action}')
                md.append('```\n')

        # ── Downstream calls ──
        downstream = [s for s in result.steps if s.direction == 'downstream']
        model_calls = [s for s in downstream if s.layer == 'model']
        db_ops = [s for s in downstream if s.layer == 'db']
        view_loads = [s for s in downstream if s.layer == 'view']
        other_calls = [s for s in downstream if s.layer not in ('model', 'db', 'view')]

        if model_calls:
            md.append('### ⚙️ Model Calls\n')
            for s in model_calls[:15]:
                fname = Path(s.file).name if s.file else '?'
                md.append(f'- `{s.function_name}` ({fname}:L{s.line})')
                # Show formulas in model methods
                for f in s.formulas[:3]:
                    md.append(f'  - Formula: `{f.variable} = {f.expression[:60]}`')
                for a in s.accumulators[:3]:
                    op = '+=' if a.direction == 'add' else '-='
                    md.append(f'  - Accumulator: `{a.variable} {op} {a.source_field[:40]}`')
            md.append('')

        if db_ops:
            md.append('### 💾 Database Operations\n')
            md.append('```')
            for s in db_ops:
                op = s.db_operations[0]['operation'] if s.db_operations else 'ACCESS'
                md.append(f'{op} → {s.function_name}')
            md.append('```\n')

        if view_loads:
            md.append('### 👁️ Views\n')
            for s in view_loads:
                md.append(f'- `{s.function_name}`')
            md.append('')

        if other_calls:
            md.append('### 🔧 Other Calls\n')
            for s in other_calls[:10]:
                md.append(f'- `{s.function_name}` ({_LAYER_LABELS.get(s.layer, s.layer)})')
            md.append('')

        # ── Mermaid diagram ──
        if result.mermaid:
            md.append('### 🔗 Flow Diagram\n')
            md.append('```mermaid')
            md.append(result.mermaid)
            md.append('```\n')

        # ── Summary ──
        md.append('### 📋 Summary\n')
        layers_seen = sorted(set(s.layer for s in result.steps), key=lambda l: _LAYER_ORDER.get(l, 99))
        layer_chain = ' → '.join(_LAYER_LABELS.get(l, l) for l in layers_seen)
        md.append(f'- **Layers:** {layer_chain}')
        md.append(f'- **Tables:** {", ".join(result.tables_touched) if result.tables_touched else "none"}')
        md.append(f'- **Risk:** {result.risk_level}')
        md.append(f'- **Total callers:** {result.total_callers}')

        return '\n'.join(md)
