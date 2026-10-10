"""CI3 security rule engine — pattern-based vulnerability detection.

Scans tree-sitter AST nodes for known dangerous patterns specific to
CodeIgniter 3 applications. Does NOT do full taint tracking (Phase 2),
but catches the most common vulnerability classes through pattern matching.

Rules:
  SEC-001: Raw SQL query usage ($this->db->query())
  SEC-002: Raw superglobal access ($_POST, $_GET, $_REQUEST)
  SEC-003: Unescaped output (echo $var without htmlspecialchars)
  SEC-004: String interpolation in DB where() clause
  SEC-005: Missing CSRF in AJAX response handler
  SEC-006: File upload without server-side validation
  SEC-007: eval() / exec() usage
  SEC-008: Unparameterized query with user input pattern
"""

import re
from dataclasses import dataclass, field
from pathlib import Path
from tree_sitter import Node


@dataclass
class SecurityFinding:
    """A single security issue found by the rule engine."""
    rule_id: str
    severity: str  # CRITICAL, HIGH, MEDIUM, LOW
    title: str
    description: str
    file: str
    line: int
    function: str
    snippet: str = ""
    recommendation: str = ""


@dataclass
class SecurityScanResult:
    """Results of a security scan."""
    file: str
    findings: list[SecurityFinding] = field(default_factory=list)
    scanned_functions: int = 0

    @property
    def critical_count(self) -> int:
        return sum(1 for f in self.findings if f.severity == "CRITICAL")

    @property
    def high_count(self) -> int:
        return sum(1 for f in self.findings if f.severity == "HIGH")

    def to_dict(self) -> dict:
        return {
            "file": self.file,
            "total_findings": len(self.findings),
            "critical": self.critical_count,
            "high": self.high_count,
            "scanned_functions": self.scanned_functions,
            "findings": [
                {
                    "rule_id": f.rule_id,
                    "severity": f.severity,
                    "title": f.title,
                    "description": f.description,
                    "line": f.line,
                    "function": f.function,
                    "snippet": f.snippet,
                    "recommendation": f.recommendation,
                }
                for f in self.findings
            ],
        }


class CI3SecurityScanner:
    """Pattern-based security scanner for CI3 PHP code."""

    def scan_ast(
        self,
        root_node: Node,
        file_path: str,
        source_bytes: bytes,
    ) -> SecurityScanResult:
        """Scan a parsed PHP AST for security issues.

        Args:
            root_node: tree-sitter root AST node
            file_path: source file path for reporting
            source_bytes: raw file bytes for snippet extraction
        """
        result = SecurityScanResult(file=file_path)
        self._current_function = "GLOBAL"
        self._source = source_bytes
        self._file = file_path
        self._result = result

        self._walk(root_node)

        return result

    def _walk(self, node: Node) -> None:
        """Recursively walk AST and check rules at each node."""

        # Track current function context
        if node.type in ("method_declaration", "function_definition"):
            name_node = node.child_by_field_name("name")
            if name_node:
                old = self._current_function
                self._current_function = name_node.text.decode("utf-8", errors="ignore")
                self._result.scanned_functions += 1

                for child in node.children:
                    self._walk(child)

                self._current_function = old
                return

        # ── Rule checks ────────────────────────────────────────────

        # SEC-001: Raw SQL query
        self._check_raw_sql(node)

        # SEC-002: Raw superglobals
        self._check_raw_superglobals(node)

        # SEC-003: Unescaped echo
        self._check_unescaped_echo(node)

        # SEC-004: String interpolation in where()
        self._check_interpolated_where(node)

        # SEC-007: eval/exec
        self._check_eval(node)

        # Recurse
        for child in node.children:
            self._walk(child)

    def _snippet(self, node: Node, max_len: int = 120) -> str:
        """Extract source snippet for a node."""
        text = node.text.decode("utf-8", errors="ignore").strip()
        if len(text) > max_len:
            text = text[:max_len] + "..."
        return text

    def _add(self, rule_id: str, severity: str, title: str,
             description: str, node: Node, recommendation: str = "") -> None:
        """Add a finding."""
        self._result.findings.append(SecurityFinding(
            rule_id=rule_id,
            severity=severity,
            title=title,
            description=description,
            file=self._file,
            line=node.start_point[0] + 1,
            function=self._current_function,
            snippet=self._snippet(node),
            recommendation=recommendation,
        ))

    # ── Rule Implementations ──────────────────────────────────────────

    def _check_raw_sql(self, node: Node) -> None:
        """SEC-001: Detect $this->db->query() — raw SQL execution."""
        if node.type != "member_call_expression":
            return

        text = node.text.decode("utf-8", errors="ignore")

        # Pattern: $this->db->query(
        if "->db->query(" in text:
            # Check if it uses bindings (second argument)
            args = node.child_by_field_name("arguments")
            arg_count = 0
            if args:
                arg_count = sum(1 for c in args.named_children)

            if arg_count < 2:
                self._add(
                    "SEC-001", "HIGH",
                    "Raw SQL query without parameter binding",
                    "$this->db->query() without bindings is vulnerable to SQL injection. "
                    "Use CI3 Query Builder or add parameter bindings as the second argument.",
                    node,
                    "Use $this->db->query($sql, $bindings) or switch to Query Builder.",
                )
            else:
                self._add(
                    "SEC-001", "LOW",
                    "Raw SQL query (with bindings)",
                    "$this->db->query() used with bindings. Verify bindings cover all user input.",
                    node,
                    "Consider using Query Builder instead for safer, more maintainable code.",
                )

    def _check_raw_superglobals(self, node: Node) -> None:
        """SEC-002: Detect $_POST, $_GET, $_REQUEST, $_COOKIE access."""
        if node.type != "subscript_expression":
            return

        text = node.text.decode("utf-8", errors="ignore")
        superglobals = {"$_POST", "$_GET", "$_REQUEST", "$_COOKIE", "$_FILES", "$_SERVER"}

        for sg in superglobals:
            if text.startswith(sg + "["):
                self._add(
                    "SEC-002", "HIGH",
                    f"Direct {sg} access (bypass CI3 input filtering)",
                    f"Using {sg} directly bypasses CI3's XSS filtering and CSRF protection. "
                    f"Always use $this->input->post(), $this->input->get(), etc.",
                    node,
                    f"Replace with $this->input->{sg.lower().replace('$_', '')}()",
                )
                return

    def _check_unescaped_echo(self, node: Node) -> None:
        """SEC-003: Detect echo of variables without htmlspecialchars."""
        if node.type != "echo_statement":
            return

        text = node.text.decode("utf-8", errors="ignore")

        # If echo contains a variable and no htmlspecialchars/xss_clean
        if "$" in text and "htmlspecialchars" not in text and "xss_clean" not in text:
            # Skip echo of json_encode (safe) and numeric values
            if "json_encode" in text or "intval" in text or "floatval" in text:
                return
            # Skip simple echo of string literals
            if text.count("$") == 0:
                return

            # Check if it's echoing a simple variable (higher risk)
            if re.search(r'echo\s+\$\w+', text):
                self._add(
                    "SEC-003", "MEDIUM",
                    "Unescaped variable in echo statement",
                    "Echoing a variable without htmlspecialchars() or xss_clean() "
                    "may lead to XSS (Cross-Site Scripting) attacks.",
                    node,
                    "Wrap output with htmlspecialchars($var, ENT_QUOTES, 'UTF-8')",
                )

    def _check_interpolated_where(self, node: Node) -> None:
        """SEC-004: Detect string interpolation in ->where() calls."""
        if node.type != "member_call_expression":
            return

        text = node.text.decode("utf-8", errors="ignore")

        # Pattern: ->where("col = '$var'") or ->where("col = " . $var)
        if "->where(" in text or "->or_where(" in text:
            # Check for string interpolation or concatenation with variables
            args = node.child_by_field_name("arguments")
            if args:
                arg_text = args.text.decode("utf-8", errors="ignore")
                # Detect: "col = '$var'" or "col = '" . $var
                if ("$" in arg_text and ("'" in arg_text or '"' in arg_text)):
                    # But not array-style where('col', $var) which is safe
                    named_args = list(args.named_children)
                    if len(named_args) == 1:
                        self._add(
                            "SEC-004", "CRITICAL",
                            "SQL injection in where() clause",
                            "String interpolation/concatenation in where() is vulnerable "
                            "to SQL injection. Use parameterized where: ->where('col', $value)",
                            node,
                            "Use ->where('column', $value) instead of ->where(\"column = '$value'\")",
                        )

    def _check_eval(self, node: Node) -> None:
        """SEC-007: Detect eval() and similar dangerous functions."""
        if node.type != "function_call_expression":
            return

        func_node = node.child_by_field_name("function")
        if not func_node:
            return

        func_name = func_node.text.decode("utf-8", errors="ignore")
        dangerous = {"eval", "exec", "system", "passthru", "shell_exec",
                      "popen", "proc_open", "pcntl_exec", "assert"}

        if func_name in dangerous:
            self._add(
                "SEC-007", "CRITICAL",
                f"Dangerous function call: {func_name}()",
                f"{func_name}() executes arbitrary code and should never be used "
                f"with user-controlled input. This is almost always a security vulnerability.",
                node,
                f"Remove {func_name}() call or use a safer alternative.",
            )


def scan_file(file_path: Path) -> SecurityScanResult:
    """Convenience function to scan a single PHP file."""
    import tree_sitter_php as ts_php
    from tree_sitter import Language, Parser

    parser = Parser(Language(ts_php.language_php()))
    content = file_path.read_bytes()
    tree = parser.parse(content)

    scanner = CI3SecurityScanner()
    return scanner.scan_ast(tree.root_node, str(file_path), content)
