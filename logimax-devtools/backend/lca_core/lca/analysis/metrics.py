"""Code metrics engine — computes complexity and quality metrics from AST.

Implements:
  - Cyclomatic Complexity (McCabe, 1976) — counts decision paths
  - Cognitive Complexity (SonarSource, 2017) — measures readability
  - Nesting Depth — deepest structural nesting level
  - Lines of Code — raw LOC per function

Works with tree-sitter AST nodes for both PHP and JavaScript.
"""

from tree_sitter import Node


# ── Node type maps per language ────────────────────────────────────────

# Decision point nodes that increment cyclomatic complexity
_DECISION_NODES = {
    "php": {
        "if_statement", "elseif_clause", "while_statement", "do_statement",
        "for_statement", "foreach_statement", "switch_statement",
        "case_statement", "catch_clause", "conditional_expression",  # ternary
    },
    "javascript": {
        "if_statement", "while_statement", "do_statement",
        "for_statement", "for_in_statement",
        "switch_statement", "switch_case",
        "catch_clause", "ternary_expression",
    },
}

# Logical operators that add paths (&&, ||, ??)
_LOGICAL_OPS = {
    "php": {"and", "or", "&&", "||", "??"},
    "javascript": {"&&", "||", "??"},
}

# Nesting structures (increase nesting depth)
_NESTING_NODES = {
    "php": {
        "if_statement", "else_clause", "elseif_clause",
        "while_statement", "do_statement", "for_statement", "foreach_statement",
        "switch_statement", "try_statement", "catch_clause",
        "function_definition", "method_declaration", "class_declaration",
        "anonymous_function_creation_expression",
    },
    "javascript": {
        "if_statement", "else_clause",
        "while_statement", "do_statement", "for_statement", "for_in_statement",
        "switch_statement", "try_statement", "catch_clause",
        "function_declaration", "method_definition",
        "arrow_function", "function",
    },
}

# Cognitive complexity: nodes that increment AND add nesting penalty
_COGNITIVE_INCREMENT = {
    "php": {
        "if_statement", "elseif_clause",
        "while_statement", "do_statement", "for_statement", "foreach_statement",
        "switch_statement", "catch_clause", "conditional_expression",
    },
    "javascript": {
        "if_statement",
        "while_statement", "do_statement", "for_statement", "for_in_statement",
        "switch_statement", "catch_clause", "ternary_expression",
    },
}

# Cognitive: nodes that increment but DON'T add nesting penalty
_COGNITIVE_INCREMENT_NO_NESTING = {
    "php": {"else_clause", "elseif_clause"},
    "javascript": {"else_clause"},
}

# Cognitive: nodes that increase nesting level (for nesting penalty)
_COGNITIVE_NESTING = {
    "php": {
        "if_statement", "while_statement", "do_statement",
        "for_statement", "foreach_statement", "switch_statement",
        "try_statement", "catch_clause",
        "anonymous_function_creation_expression",
    },
    "javascript": {
        "if_statement", "while_statement", "do_statement",
        "for_statement", "for_in_statement", "switch_statement",
        "try_statement", "catch_clause",
        "arrow_function", "function",
    },
}


def compute_metrics(node: Node, language: str = "php") -> dict:
    """Compute all code metrics for a function AST node.

    Args:
        node: tree-sitter AST node (function_definition or method_declaration)
        language: 'php' or 'javascript'

    Returns:
        Dict with keys: cyclomatic, cognitive, max_nesting, lines_of_code, param_count
    """
    lang = "php" if language == "php" else "javascript"

    return {
        "cyclomatic_complexity": _cyclomatic_complexity(node, lang),
        "cognitive_complexity": _cognitive_complexity(node, lang),
        "max_nesting_depth": _max_nesting_depth(node, lang),
        "lines_of_code": (node.end_point[0] - node.start_point[0] + 1),
        "param_count": _count_params(node),
    }


# ── Cyclomatic Complexity ──────────────────────────────────────────────

def _cyclomatic_complexity(node: Node, lang: str) -> int:
    """McCabe cyclomatic complexity: 1 + number of decision points.

    Each if, elseif, for, while, case, catch, ternary, &&, || adds 1.
    """
    decision_types = _DECISION_NODES.get(lang, set())
    logical_ops = _LOGICAL_OPS.get(lang, set())
    complexity = 1  # Base complexity

    def walk(n: Node):
        nonlocal complexity

        if n.type in decision_types:
            complexity += 1

        # Count logical operators (&& || ??)
        if n.type == "binary_expression":
            op_node = n.child_by_field_name("operator")
            if op_node:
                op_text = op_node.text.decode("utf-8", errors="ignore")
                if op_text in logical_ops:
                    complexity += 1

        for child in n.children:
            walk(child)

    walk(node)
    return complexity


# ── Cognitive Complexity (SonarSource method) ──────────────────────────

def _cognitive_complexity(node: Node, lang: str) -> int:
    """SonarSource cognitive complexity.

    Rules:
    1. Increment for each break in linear flow (if, for, while, catch, ternary)
    2. Increment for each level of nesting (nested if inside for = +2)
    3. Logical operators (&&, ||) increment but don't add nesting penalty
    4. else/elseif increment but don't increase nesting level
    """
    increment_nodes = _COGNITIVE_INCREMENT.get(lang, set())
    no_nesting_nodes = _COGNITIVE_INCREMENT_NO_NESTING.get(lang, set())
    nesting_nodes = _COGNITIVE_NESTING.get(lang, set())
    logical_ops = _LOGICAL_OPS.get(lang, set())
    complexity = 0

    def walk(n: Node, nesting_level: int):
        nonlocal complexity

        if n.type in increment_nodes:
            # Increment + nesting penalty
            complexity += 1 + nesting_level

        elif n.type in no_nesting_nodes:
            # Increment only, no nesting penalty
            complexity += 1

        # Logical operators: flat increment
        if n.type == "binary_expression":
            op_node = n.child_by_field_name("operator")
            if op_node:
                op_text = op_node.text.decode("utf-8", errors="ignore")
                if op_text in logical_ops:
                    complexity += 1

        # Recurse with updated nesting
        next_level = nesting_level + 1 if n.type in nesting_nodes else nesting_level
        for child in n.children:
            walk(child, next_level)

    # Start at nesting 0 (the function body itself doesn't count)
    for child in node.children:
        walk(child, 0)

    return complexity


# ── Max Nesting Depth ──────────────────────────────────────────────────

def _max_nesting_depth(node: Node, lang: str) -> int:
    """Maximum structural nesting depth within a function."""
    nesting_types = _NESTING_NODES.get(lang, set())
    # Remove function/class from nesting (we start inside the function)
    nesting_types = nesting_types - {
        "function_definition", "method_declaration", "class_declaration",
        "function_declaration", "method_definition",
    }
    max_depth = 0

    def walk(n: Node, depth: int):
        nonlocal max_depth

        if n.type in nesting_types:
            depth += 1
            if depth > max_depth:
                max_depth = depth

        for child in n.children:
            walk(child, depth)

    walk(node, 0)
    return max_depth


# ── Parameter Count ────────────────────────────────────────────────────

def _count_params(node: Node) -> int:
    """Count function parameters."""
    params = node.child_by_field_name("parameters")
    if not params:
        return 0
    count = 0
    for child in params.named_children:
        if child.type in ("simple_parameter", "identifier", "formal_parameters",
                          "required_parameter", "optional_parameter",
                          "rest_pattern", "assignment_pattern"):
            count += 1
    return count
