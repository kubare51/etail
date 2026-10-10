"""Code Extractor — extracts formulas, accumulators, conditionals, and magic numbers from AST.

Works with tree-sitter AST nodes for both JavaScript and PHP.
Designed to run during full-index (lca index) to populate ModuleIndex with deep analysis data.

Usage:
    extractor = CodeExtractor()
    results = extractor.analyze_file(Path('path/to/file.js'))
    # results = (formulas, accumulators, conditionals, magic_numbers)
"""

import re
from pathlib import Path
from tree_sitter import Node, Parser, Language

try:
    import tree_sitter_javascript as ts_js
    import tree_sitter_php as ts_php
    _JS_LANG = Language(ts_js.language())
    _PHP_LANG = Language(ts_php.language_php())
except ImportError:
    _JS_LANG = None
    _PHP_LANG = None

from lca.models import FormulaExpr, Accumulator, ConditionalBranch, MagicNumber


# ── Operator classification ───────────────────────────────────────────

_OP_NAMES = {
    '+': 'addition', '-': 'subtraction', '*': 'multiplication',
    '/': 'division', '%': 'modulo', '**': 'exponentiation',
}

# Comparison operators that indicate dispatch/magic number patterns
_COMPARISON_OPS = {'==', '===', '!=', '!==', '<', '>', '<=', '>='}

# Augmented assignment operators
_AUGMENTED_OPS = {'+=': 'add', '-=': 'subtract', '*=': 'multiply', '/=': 'divide'}

# Skip list — variables too generic to be meaningful formulas
_SKIP_VARS_JS = {
    'i', 'j', 'k', 'idx', 'key', 'val', 'item', 'items',
    'trHTML', 'tfootHTML', 'empHTML', 'html', 'HTML',
}
_SKIP_VARS_PHP = {
    '$i', '$j', '$k', '$key', '$val', '$item', '$row',
}

# Accumulator noise filter — skip HTML template concatenations
_HTML_ACCUM_PATTERNS = (
    "'<", '"<', "'</", '"</', '<tr', '<td', '<th', '<div', '<span',
    '<table', '<input', '<select', '<option', '<button', '<a ',
    '<p ', '<h1', '<h2', '<h3', '<h4', '<h5', '<h6', '<ul', '<li',
    '<br', '<hr', '<img', '<form', '<label', '<i ', '<b ', '<strong',
)


class CodeExtractor:
    """AST-level code extractor for business rule extraction.

    Extracts 4 types of patterns from function bodies:
    1. Formulas: var = expr (arithmetic assignments)
    2. Accumulators: var += parseFloat(field) (running totals)
    3. Conditionals: if (var == value) { action } (dispatch tables)
    4. Magic Numbers: hardcoded literals in comparisons
    """

    def __init__(self):
        self._js_parser = Parser(_JS_LANG) if _JS_LANG else None
        self._php_parser = Parser(_PHP_LANG) if _PHP_LANG else None

    def analyze_file(self, file_path: Path) -> tuple[
        list[FormulaExpr], list[Accumulator],
        list[ConditionalBranch], list[MagicNumber]
    ]:
        """Analyze a single file for deep patterns.

        Args:
            file_path: Path to JS or PHP file

        Returns:
            Tuple of (formulas, accumulators, conditionals, magic_numbers)
        """
        suffix = file_path.suffix.lower()
        if suffix in ('.js', '.jsx', '.ts', '.tsx', '.mjs'):
            return self._analyze_js(file_path)
        elif suffix in ('.php', '.phtml', '.inc'):
            return self._analyze_php(file_path)
        return [], [], [], []

    # ── JavaScript Analysis ────────────────────────────────────────────

    def _analyze_js(self, file_path: Path) -> tuple[
        list[FormulaExpr], list[Accumulator],
        list[ConditionalBranch], list[MagicNumber]
    ]:
        """Run all 4 analyzers on a JavaScript file."""
        if not self._js_parser:
            return [], [], [], []

        content = file_path.read_bytes()
        tree = self._js_parser.parse(content)
        file_str = str(file_path)

        formulas: list[FormulaExpr] = []
        accumulators: list[Accumulator] = []
        conditionals: list[ConditionalBranch] = []
        magic_numbers: list[MagicNumber] = []

        current_func = 'GLOBAL'

        def walk(node: Node, func_name: str):
            nonlocal current_func
            old_func = current_func

            # Track function context
            if node.type == 'function_declaration':
                name_node = node.child_by_field_name('name')
                if name_node:
                    func_name = name_node.text.decode('utf-8')
                    current_func = func_name
            elif node.type == 'variable_declarator':
                name_node = node.child_by_field_name('name')
                value_node = node.child_by_field_name('value')
                if name_node and value_node and value_node.type in ('function', 'arrow_function'):
                    func_name = name_node.text.decode('utf-8')
                    current_func = func_name

            # 1. Formula extraction: var X = A op B
            if node.type == 'variable_declarator':
                self._extract_js_formula(node, current_func, file_str, formulas)

            # 1b. Formula extraction: X = A op B (reassignment)
            elif node.type == 'assignment_expression':
                self._extract_js_assignment_formula(node, current_func, file_str, formulas)

            # 2. Accumulator detection: X += parseFloat(Y)
            elif node.type == 'augmented_assignment_expression':
                self._extract_js_accumulator(node, current_func, file_str, accumulators)

            # 3. Conditional mapping: if (X == Y) { ... }
            elif node.type == 'if_statement':
                self._extract_js_conditional(node, current_func, file_str, conditionals, magic_numbers)

            # 3b. Switch/case mapping
            elif node.type == 'switch_statement':
                self._extract_js_switch(node, current_func, file_str, conditionals, magic_numbers)

            # 4. Magic numbers in binary expressions (outside if conditions)
            elif node.type == 'binary_expression':
                self._extract_js_magic_number(node, current_func, file_str, magic_numbers)

            # Recurse
            for child in node.children:
                walk(child, current_func)

            current_func = old_func

        walk(tree.root_node, 'GLOBAL')
        return formulas, accumulators, conditionals, magic_numbers

    def _extract_js_formula(self, node: Node, func: str, file: str,
                            formulas: list[FormulaExpr]):
        """Extract formula from variable_declarator: var X = A op B."""
        name_node = node.child_by_field_name('name')
        value_node = node.child_by_field_name('value')

        if not name_node or not value_node:
            return

        var_name = name_node.text.decode('utf-8')
        if var_name in _SKIP_VARS_JS or len(var_name) < 2:
            return

        # Check if value contains arithmetic
        expr_node = value_node
        precision = None

        # Handle parseFloat(...).toFixed(N) wrapping
        if value_node.type == 'call_expression':
            func_node = value_node.child_by_field_name('function')
            if func_node:
                func_text = func_node.text.decode('utf-8')
                if func_text == 'parseFloat':
                    args = value_node.child_by_field_name('arguments')
                    if args and args.named_child_count > 0:
                        expr_node = args.named_children[0]
                        # Check for .toFixed() chain on parent
                        precision = self._detect_precision(node)
            # Handle expr.toFixed(N)
            if func_node and func_node.type == 'member_expression':
                prop = func_node.child_by_field_name('property')
                obj = func_node.child_by_field_name('object')
                if prop and prop.text.decode('utf-8') == 'toFixed':
                    precision = value_node.text.decode('utf-8', errors='ignore')
                    # Get the actual expression being fixed
                    if obj:
                        expr_node = obj

        if not self._has_arithmetic(expr_node):
            return

        expression = expr_node.text.decode('utf-8', errors='ignore').strip()
        # Limit expression length for readability
        if len(expression) > 200:
            expression = expression[:200] + '...'

        operator = self._classify_operator(expr_node)

        formulas.append(FormulaExpr(
            variable=var_name,
            expression=expression,
            operator=operator,
            line=node.start_point[0] + 1,
            function=func,
            file=file,
            precision=precision,
            language='javascript',
        ))

    def _extract_js_assignment_formula(self, node: Node, func: str, file: str,
                                        formulas: list[FormulaExpr]):
        """Extract formula from assignment_expression: X = A op B."""
        left = node.child_by_field_name('left')
        right = node.child_by_field_name('right')

        if not left or not right:
            return

        var_name = left.text.decode('utf-8')
        if var_name in _SKIP_VARS_JS or len(var_name) < 2:
            return

        expr_node = right
        precision = None

        # Handle parseFloat wrapping
        if right.type == 'call_expression':
            func_node = right.child_by_field_name('function')
            if func_node:
                func_text = func_node.text.decode('utf-8')
                if func_text == 'parseFloat':
                    args = right.child_by_field_name('arguments')
                    if args and args.named_child_count > 0:
                        expr_node = args.named_children[0]
                # toFixed chain
                if func_node.type == 'member_expression':
                    prop = func_node.child_by_field_name('property')
                    if prop and prop.text.decode('utf-8') == 'toFixed':
                        precision = right.text.decode('utf-8', errors='ignore')
                        obj = func_node.child_by_field_name('object')
                        if obj:
                            expr_node = obj

        if not self._has_arithmetic(expr_node):
            return

        expression = expr_node.text.decode('utf-8', errors='ignore').strip()
        if len(expression) > 200:
            expression = expression[:200] + '...'

        formulas.append(FormulaExpr(
            variable=var_name,
            expression=expression,
            operator=self._classify_operator(expr_node),
            line=node.start_point[0] + 1,
            function=func,
            file=file,
            precision=precision,
            language='javascript',
        ))

    def _extract_js_accumulator(self, node: Node, func: str, file: str,
                                 accumulators: list[Accumulator]):
        """Extract accumulator from: X += parseFloat(Y) or X -= Y."""
        left = node.child_by_field_name('left')
        right = node.child_by_field_name('right')
        op_node = node.child_by_field_name('operator')

        if not left or not right or not op_node:
            return

        op_text = op_node.text.decode('utf-8')
        if op_text not in _AUGMENTED_OPS:
            return

        var_name = left.text.decode('utf-8')
        if var_name in _SKIP_VARS_JS:
            return

        # Detect wrapper (parseFloat, Number, etc.)
        wrapper = None
        source_node = right

        if right.type == 'call_expression':
            func_node = right.child_by_field_name('function')
            if func_node:
                wrapper_name = func_node.text.decode('utf-8')
                if wrapper_name in ('parseFloat', 'parseInt', 'Number'):
                    wrapper = wrapper_name
                    args = right.child_by_field_name('arguments')
                    if args and args.named_child_count > 0:
                        source_node = args.named_children[0]

        source_field = source_node.text.decode('utf-8', errors='ignore').strip()
        if len(source_field) > 100:
            source_field = source_field[:100] + '...'

        # Filter noise: skip HTML template concatenations
        sf_stripped = source_field.strip().strip("'").strip('"').strip()
        if any(sf_stripped.startswith(p) for p in _HTML_ACCUM_PATTERNS):
            return
        # Also skip if the variable name ends in HTML/html
        if var_name.lower().endswith('html'):
            return

        accumulators.append(Accumulator(
            variable=var_name,
            source_field=source_field,
            wrapper=wrapper,
            line=node.start_point[0] + 1,
            function=func,
            file=file,
            direction=_AUGMENTED_OPS[op_text],
            language='javascript',
        ))

    def _extract_js_conditional(self, node: Node, func: str, file: str,
                                 conditionals: list[ConditionalBranch],
                                 magic_nums: list[MagicNumber]):
        """Extract conditional dispatch from if (X == Y) { action }."""
        condition = node.child_by_field_name('condition')
        consequence = node.child_by_field_name('consequence')

        if not condition:
            return

        # Handle parenthesized_expression wrapper
        cond_inner = condition
        if condition.type == 'parenthesized_expression' and condition.named_child_count > 0:
            cond_inner = condition.named_children[0]

        if cond_inner.type != 'binary_expression':
            return

        left = cond_inner.child_by_field_name('left')
        right = cond_inner.child_by_field_name('right')
        op = cond_inner.child_by_field_name('operator')

        if not left or not right or not op:
            return

        op_text = op.text.decode('utf-8')
        if op_text not in _COMPARISON_OPS:
            return

        variable = left.text.decode('utf-8', errors='ignore')
        value = right.text.decode('utf-8', errors='ignore')

        # Get action summary from consequence
        action = ''
        if consequence:
            action = self._first_statement_summary(consequence)

        conditionals.append(ConditionalBranch(
            variable=variable,
            value=value,
            operator=op_text,
            action_summary=action,
            line=node.start_point[0] + 1,
            function=func,
            file=file,
            branch_type='if',
            language='javascript',
        ))

        # Check for literal comparison → magic number
        if right.type in ('number', 'string'):
            magic_nums.append(MagicNumber(
                value=value,
                variable=variable,
                operator=op_text,
                line=node.start_point[0] + 1,
                function=func,
                file=file,
                context=cond_inner.text.decode('utf-8', errors='ignore')[:100],
                language='javascript',
            ))

    def _extract_js_switch(self, node: Node, func: str, file: str,
                            conditionals: list[ConditionalBranch],
                            magic_nums: list[MagicNumber]):
        """Extract dispatch table from switch (X) { case Y: ... }."""
        value_node = node.child_by_field_name('value')
        if not value_node:
            return
        switch_var = value_node.text.decode('utf-8', errors='ignore')

        body = node.child_by_field_name('body')
        if not body:
            return

        for child in body.named_children:
            if child.type == 'switch_case':
                case_value_node = child.child_by_field_name('value')
                if case_value_node:
                    case_value = case_value_node.text.decode('utf-8', errors='ignore')
                    action = self._first_statement_summary(child)

                    conditionals.append(ConditionalBranch(
                        variable=switch_var,
                        value=case_value,
                        operator='==',
                        action_summary=action,
                        line=child.start_point[0] + 1,
                        function=func,
                        file=file,
                        branch_type='switch_case',
                        language='javascript',
                    ))

                    if case_value_node.type in ('number', 'string'):
                        magic_nums.append(MagicNumber(
                            value=case_value,
                            variable=switch_var,
                            operator='case',
                            line=child.start_point[0] + 1,
                            function=func,
                            file=file,
                            context=f'switch ({switch_var}) case {case_value}',
                            language='javascript',
                        ))

    def _extract_js_magic_number(self, node: Node, func: str, file: str,
                                  magic_nums: list[MagicNumber]):
        """Extract magic numbers from standalone binary comparisons."""
        # Only process direct comparisons, not nested in if (handled separately)
        parent = node.parent
        if parent and parent.type in ('if_statement', 'parenthesized_expression'):
            return  # Already handled by conditional extractor

        left = node.child_by_field_name('left')
        right = node.child_by_field_name('right')
        op = node.child_by_field_name('operator')

        if not left or not right or not op:
            return

        op_text = op.text.decode('utf-8')
        if op_text not in _COMPARISON_OPS:
            return

        # Only capture if right side is a literal
        if right.type not in ('number', 'string'):
            return

        variable = left.text.decode('utf-8', errors='ignore')
        value = right.text.decode('utf-8', errors='ignore')

        magic_nums.append(MagicNumber(
            value=value,
            variable=variable,
            operator=op_text,
            line=node.start_point[0] + 1,
            function=func,
            file=file,
            context=node.text.decode('utf-8', errors='ignore')[:100],
            language='javascript',
        ))

    # ── PHP Analysis ───────────────────────────────────────────────────

    def _analyze_php(self, file_path: Path) -> tuple[
        list[FormulaExpr], list[Accumulator],
        list[ConditionalBranch], list[MagicNumber]
    ]:
        """Run all 4 analyzers on a PHP file."""
        if not self._php_parser:
            return [], [], [], []

        content = file_path.read_bytes()
        tree = self._php_parser.parse(content)
        file_str = str(file_path)

        formulas: list[FormulaExpr] = []
        accumulators: list[Accumulator] = []
        conditionals: list[ConditionalBranch] = []
        magic_numbers: list[MagicNumber] = []

        current_func = 'GLOBAL'
        current_class = None

        def walk(node: Node):
            nonlocal current_func, current_class

            old_func = current_func
            old_class = current_class

            # Track class context
            if node.type == 'class_declaration':
                name_node = node.child_by_field_name('name')
                if name_node:
                    current_class = name_node.text.decode('utf-8')

            # Track function/method context
            if node.type in ('function_definition', 'method_declaration'):
                name_node = node.child_by_field_name('name')
                if name_node:
                    fname = name_node.text.decode('utf-8')
                    if current_class:
                        current_func = f'{current_class}::{fname}'
                    else:
                        current_func = fname

            # 1. Formula: $var = $a op $b
            if node.type == 'assignment_expression':
                self._extract_php_formula(node, current_func, file_str, formulas)

            # 2. Accumulator: $var += $expr
            elif node.type == 'augmented_assignment_expression':
                self._extract_php_accumulator(node, current_func, file_str, accumulators)

            # 3. Conditional: if ($var == value) { ... }
            elif node.type == 'if_statement':
                self._extract_php_conditional(node, current_func, file_str, conditionals, magic_numbers)

            # 3b. Switch/case
            elif node.type == 'switch_statement':
                self._extract_php_switch(node, current_func, file_str, conditionals, magic_numbers)

            # 5. SQL formula extraction from db->query() strings
            if node.type == 'member_call_expression':
                self._extract_sql_formulas(node, current_func, file_str, formulas)

            # Recurse
            for child in node.children:
                walk(child)

            current_func = old_func
            current_class = old_class

        walk(tree.root_node)
        return formulas, accumulators, conditionals, magic_numbers

    def _extract_php_formula(self, node: Node, func: str, file: str,
                              formulas: list[FormulaExpr]):
        """Extract formula from PHP assignment: $var = $a - $b."""
        left = node.child_by_field_name('left')
        right = node.child_by_field_name('right')

        if not left or not right:
            return

        var_name = left.text.decode('utf-8')
        if var_name in _SKIP_VARS_PHP or len(var_name) < 3:
            return

        if not self._has_arithmetic(right):
            return

        expression = right.text.decode('utf-8', errors='ignore').strip()
        if len(expression) > 200:
            expression = expression[:200] + '...'

        precision = None
        # Detect number_format() or round() wrapping
        if right.type == 'function_call_expression':
            func_node = right.child_by_field_name('function')
            if func_node:
                fname = func_node.text.decode('utf-8')
                if fname in ('number_format', 'round'):
                    precision = right.text.decode('utf-8', errors='ignore')[:50]

        formulas.append(FormulaExpr(
            variable=var_name,
            expression=expression,
            operator=self._classify_operator(right),
            line=node.start_point[0] + 1,
            function=func,
            file=file,
            precision=precision,
            language='php',
        ))

    def _extract_php_accumulator(self, node: Node, func: str, file: str,
                                  accumulators: list[Accumulator]):
        """Extract PHP accumulator: $total += (float)$val or $total += $row->field."""
        left = node.child_by_field_name('left')
        right = node.child_by_field_name('right')
        op_node = node.child_by_field_name('operator')

        if not left or not right or not op_node:
            return

        op_text = op_node.text.decode('utf-8')
        if op_text not in _AUGMENTED_OPS:
            return

        var_name = left.text.decode('utf-8')
        if var_name in _SKIP_VARS_PHP:
            return

        wrapper = None
        source_node = right

        # Detect (float), (int) casts
        if right.type == 'cast_expression':
            cast_type = right.child_by_field_name('type')
            if cast_type:
                wrapper = f'({cast_type.text.decode("utf-8")})'
            value = right.child_by_field_name('value')
            if value:
                source_node = value

        source_field = source_node.text.decode('utf-8', errors='ignore').strip()
        if len(source_field) > 100:
            source_field = source_field[:100] + '...'

        accumulators.append(Accumulator(
            variable=var_name,
            source_field=source_field,
            wrapper=wrapper,
            line=node.start_point[0] + 1,
            function=func,
            file=file,
            direction=_AUGMENTED_OPS[op_text],
            language='php',
        ))

    def _extract_php_conditional(self, node: Node, func: str, file: str,
                                  conditionals: list[ConditionalBranch],
                                  magic_nums: list[MagicNumber]):
        """Extract PHP conditional: if ($var == value) { action }."""
        condition = node.child_by_field_name('condition')
        body = node.child_by_field_name('body')

        if not condition:
            return

        cond_inner = condition
        if condition.type == 'parenthesized_expression' and condition.named_child_count > 0:
            cond_inner = condition.named_children[0]

        if cond_inner.type != 'binary_expression':
            return

        left = cond_inner.child_by_field_name('left')
        right = cond_inner.child_by_field_name('right')
        op = cond_inner.child_by_field_name('operator')

        if not left or not right or not op:
            return

        op_text = op.text.decode('utf-8')
        if op_text not in _COMPARISON_OPS:
            return

        variable = left.text.decode('utf-8', errors='ignore')
        value = right.text.decode('utf-8', errors='ignore')

        action = ''
        if body:
            action = self._first_statement_summary(body)

        conditionals.append(ConditionalBranch(
            variable=variable,
            value=value,
            operator=op_text,
            action_summary=action,
            line=node.start_point[0] + 1,
            function=func,
            file=file,
            branch_type='if',
            language='php',
        ))

        if right.type in ('integer', 'float', 'string', 'encapsed_string'):
            magic_nums.append(MagicNumber(
                value=value,
                variable=variable,
                operator=op_text,
                line=node.start_point[0] + 1,
                function=func,
                file=file,
                context=cond_inner.text.decode('utf-8', errors='ignore')[:100],
                language='php',
            ))

    def _extract_php_switch(self, node: Node, func: str, file: str,
                             conditionals: list[ConditionalBranch],
                             magic_nums: list[MagicNumber]):
        """Extract PHP switch/case dispatch."""
        condition = node.child_by_field_name('condition')
        if not condition:
            return

        cond_inner = condition
        if condition.type == 'parenthesized_expression' and condition.named_child_count > 0:
            cond_inner = condition.named_children[0]
        switch_var = cond_inner.text.decode('utf-8', errors='ignore')

        body = node.child_by_field_name('body')
        if not body:
            return

        for child in body.named_children:
            if child.type == 'case_statement':
                # case_statement has the value as a direct child (not named 'value' in PHP grammar)
                case_value = None
                for sub in child.children:
                    if sub.type in ('integer', 'float', 'string', 'encapsed_string', 'name'):
                        case_value = sub.text.decode('utf-8', errors='ignore')
                        break

                if case_value:
                    action = self._first_statement_summary(child)
                    conditionals.append(ConditionalBranch(
                        variable=switch_var,
                        value=case_value,
                        operator='case',
                        action_summary=action,
                        line=child.start_point[0] + 1,
                        function=func,
                        file=file,
                        branch_type='switch_case',
                        language='php',
                    ))
                    magic_nums.append(MagicNumber(
                        value=case_value,
                        variable=switch_var,
                        operator='case',
                        line=child.start_point[0] + 1,
                        function=func,
                        file=file,
                        context=f'switch ({switch_var}) case {case_value}',
                        language='php',
                    ))

    # ── Shared Helpers ─────────────────────────────────────────────────

    @staticmethod
    def _has_arithmetic(node: Node) -> bool:
        """Check if a node contains arithmetic operations."""
        if node.type == 'binary_expression':
            op = node.child_by_field_name('operator')
            if op and op.text.decode('utf-8') in ('+', '-', '*', '/', '%'):
                return True
        # Check children recursively (but shallow — max 3 levels)
        for child in node.children:
            if child.type == 'binary_expression':
                op = child.child_by_field_name('operator')
                if op and op.text.decode('utf-8') in ('+', '-', '*', '/', '%'):
                    return True
                for grandchild in child.children:
                    if grandchild.type == 'binary_expression':
                        op2 = grandchild.child_by_field_name('operator')
                        if op2 and op2.text.decode('utf-8') in ('+', '-', '*', '/', '%'):
                            return True
        return False

    @staticmethod
    def _classify_operator(node: Node) -> str:
        """Classify the primary operator of an expression."""
        if node.type != 'binary_expression':
            return 'unknown'
        op = node.child_by_field_name('operator')
        if not op:
            return 'unknown'
        op_text = op.text.decode('utf-8')

        # Check if compound (has multiple different operators)
        has_other = False
        for child in node.children:
            if child.type == 'binary_expression':
                child_op = child.child_by_field_name('operator')
                if child_op and child_op.text.decode('utf-8') != op_text:
                    has_other = True
                    break

        if has_other:
            return 'compound'
        return _OP_NAMES.get(op_text, 'unknown')

    @staticmethod
    def _detect_precision(node: Node) -> str | None:
        """Detect .toFixed(N) chained on the expression or parent."""
        # Walk up to find call_expression with toFixed
        current = node
        for _ in range(5):
            parent = current.parent
            if not parent:
                break
            if parent.type == 'call_expression':
                func_node = parent.child_by_field_name('function')
                if func_node and func_node.type == 'member_expression':
                    prop = func_node.child_by_field_name('property')
                    if prop and prop.text.decode('utf-8') == 'toFixed':
                        return parent.text.decode('utf-8', errors='ignore')[:50]
            current = parent
        return None

    @staticmethod
    def _first_statement_summary(body_node: Node) -> str:
        """Get a one-line summary of the first meaningful statement in a block."""
        for child in body_node.named_children:
            # Skip braces, comments
            if child.type in ('{', '}', 'comment', 'break_statement', 'continue_statement'):
                continue
            text = child.text.decode('utf-8', errors='ignore').strip()
            # Truncate long statements
            first_line = text.split('\n')[0].strip()
            if len(first_line) > 120:
                first_line = first_line[:120] + '...'
            return first_line
        return ''

    # ── SQL Formula Extraction ─────────────────────────────────────────

    # Regex: Match computed SQL columns like:
    #   SUM(col) as alias, IFNULL(SUM(col),0) as alias, (a.col + b.col) as alias
    #   Excludes: IFNULL(simple_col, '-'), DATE_FORMAT(...), simple column renames
    _SQL_COMPUTED_RE = re.compile(
        r'(?:'
        # Pattern 1: IFNULL/COALESCE wrapping an aggregate function
        r'(?:IFNULL|COALESCE)\s*\(\s*(?:SUM|AVG|COUNT|MIN|MAX)\s*\([^)]+\)\s*,\s*[^)]+\)'
        r'|'
        # Pattern 2: Bare aggregate function
        r'(?:SUM|AVG|COUNT|MIN|MAX)\s*\([^)]+\)'
        r'|'
        # Pattern 3: Parenthesized arithmetic with actual column references
        # e.g., (b.tot_bill_amount - b.tot_amt_received) as due_amt
        r'\(\s*\w+\.\w+\s*[+\-*/]\s*\w+(?:\.\w+)?(?:\s*[+\-*/]\s*\w+(?:\.\w+)?)*\s*\)'
        r')'
        r'\s+(?:as|AS)\s+(\w+)',
        re.IGNORECASE
    )

    # Regex: Match inline arithmetic in SELECT like (a.col - b.col) as alias
    _SQL_ARITH_RE = re.compile(
        r'(\w+\.\w+\s*[+\-*/]\s*\w+(?:\.\w+)?)\s+(?:as|AS)\s+(\w+)',
        re.IGNORECASE
    )

    def _extract_sql_formulas(self, node: Node, func: str, file: str,
                               formulas: list[FormulaExpr]):
        """Extract computed columns from $this->db->query("SELECT ...") calls.

        Finds SQL aggregate formulas (SUM, IFNULL(SUM(...)), arithmetic) and
        creates FormulaExpr entries with language='sql'.
        """
        # Verify it's a db->query() call
        method_node = node.child_by_field_name('name')
        if not method_node or method_node.text.decode('utf-8') != 'query':
            return

        # Get the object to verify it's $this->db
        obj_node = node.child_by_field_name('object')
        if obj_node:
            obj_text = obj_node.text.decode('utf-8', errors='ignore')
            if 'db' not in obj_text:
                return

        # Get the arguments to find the SQL string
        args_node = node.child_by_field_name('arguments')
        if not args_node:
            return

        # Extract the full text of the first argument (the SQL string)
        sql_text = ''
        for arg in args_node.named_children:
            arg_text = arg.text.decode('utf-8', errors='ignore')
            # Strip PHP string delimiters and concatenation
            sql_text = arg_text
            break

        if not sql_text:
            return

        # Clean up PHP string artifacts: remove quotes, concatenation operators, variables
        # Normalize to a single SQL string
        sql_clean = sql_text.replace('"', ' ').replace("'", ' ').replace('.', ' ').replace('$', '')

        # Better: just use the raw text and search for SQL patterns
        line = node.start_point[0] + 1

        # Find computed columns via regex on the raw SQL text
        for match in self._SQL_COMPUTED_RE.finditer(sql_text):
            alias = match.group(1)
            expression = match.group(0)
            # Clean up expression for readability
            expression = expression.strip()
            if len(expression) > 200:
                expression = expression[:200] + '...'

            formulas.append(FormulaExpr(
                variable=alias,
                expression=expression,
                operator='sql_aggregate',
                line=line,
                function=func,
                file=file,
                precision=None,
                language='sql',
            ))

        # Find inline arithmetic: (a.col - b.col) as alias, a.col*b.col as alias
        for match in self._SQL_ARITH_RE.finditer(sql_text):
            expression = match.group(1).strip()
            alias = match.group(2)

            # Skip if already captured by the aggregate regex
            if any(f.variable == alias and f.line == line for f in formulas):
                continue

            # Skip trivial non-arithmetic
            if not any(op in expression for op in ('+', '-', '*', '/')):
                continue

            formulas.append(FormulaExpr(
                variable=alias,
                expression=expression,
                operator='sql_arithmetic',
                line=line,
                function=func,
                file=file,
                precision=None,
                language='sql',
            ))
