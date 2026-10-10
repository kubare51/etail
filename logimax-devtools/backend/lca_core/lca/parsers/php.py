"""PHP parser using tree-sitter."""

from pathlib import Path
import re
import tree_sitter_php as ts_php
from tree_sitter import Language, Parser, Node

from lca.models import FunctionDef, FunctionCall
from lca.analysis.metrics import compute_metrics
from .base import BaseParser

# CI3 loader method → call_type mapping
_CI3_LOADER_TYPES = {
    "model": "load_model",
    "view": "load_view",
    "helper": "load_helper",
    "library": "load_library",
}


class PHPParser(BaseParser):
    """Parser for PHP files."""
    
    def __init__(self):
        # tree-sitter-php uses language_php function
        self._parser = Parser(Language(ts_php.language_php()))

    
    @property
    def language(self) -> str:
        return "php"
    
    @property
    def extensions(self) -> list[str]:
        return [".php", ".phtml", ".inc"]
    
    def parse_file(self, file_path: Path) -> tuple[list[FunctionDef], list[FunctionCall]]:
        """Parse a PHP file and extract functions and calls."""
        content = file_path.read_bytes()
        tree = self._parser.parse(content)
        
        if tree.root_node.has_error:
            print(f"[Warning] Syntax errors detected in {file_path.name}. Proceeding with partial AST.")
        
        functions: list[FunctionDef] = []
        calls: list[FunctionCall] = []
        
        self._current_function = "GLOBAL"
        self._current_class = None
        self._file_path = str(file_path)
        # Track models loaded via $this->load->model('name')
        # Maps model_name -> loaded_as (e.g. 'ret_estimation_model' -> 'ret_estimation_model')
        self._loaded_models: dict[str, str] = {}
        
        self._traverse(tree.root_node, functions, calls)
        
        return functions, calls
    
    def _traverse(self, node: Node, functions: list[FunctionDef], calls: list[FunctionCall]):
        """Recursively traverse the AST."""
        
        # Class declarations
        if node.type == "class_declaration":
            name_node = node.child_by_field_name("name")
            if name_node:
                self._current_class = name_node.text.decode("utf-8")
            for child in node.children:
                self._traverse(child, functions, calls)
            self._current_class = None
            return
        
        # Function declarations
        elif node.type == "function_definition":
            name_node = node.child_by_field_name("name")
            if name_node:
                func_name = name_node.text.decode("utf-8")
                if self._current_class:
                    func_name = f"{self._current_class}::{func_name}"
                
                func = FunctionDef(
                    name=func_name,
                    file=self._file_path,
                    line=node.start_point[0] + 1,
                    end_line=node.end_point[0] + 1,
                    params=self._extract_params(node),
                    language="php",
                    metrics=compute_metrics(node, "php"),
                )
                functions.append(func)
                
                old_context = self._current_function
                self._current_function = func_name
                for child in node.children:
                    self._traverse(child, functions, calls)
                self._current_function = old_context
                return
        
        # Method declarations (in classes)
        elif node.type == "method_declaration":
            name_node = node.child_by_field_name("name")
            if name_node:
                func_name = name_node.text.decode("utf-8")
                if self._current_class:
                    func_name = f"{self._current_class}::{func_name}"
                
                func = FunctionDef(
                    name=func_name,
                    file=self._file_path,
                    line=node.start_point[0] + 1,
                    end_line=node.end_point[0] + 1,
                    params=self._extract_params(node),
                    language="php",
                    metrics=compute_metrics(node, "php"),
                )
                functions.append(func)
                
                old_context = self._current_function
                self._current_function = func_name
                for child in node.children:
                    self._traverse(child, functions, calls)
                self._current_function = old_context
                return
        
        # Function calls: foo() or $obj->foo() or Class::method()
        elif node.type == "function_call_expression":
            call = self._extract_call(node)
            if call:
                calls.append(call)
        
        elif node.type == "member_call_expression":
            result = self._extract_member_call(node)
            if result:
                if isinstance(result, list):
                    calls.extend(result)
                else:
                    calls.append(result)
        
        elif node.type == "scoped_call_expression":
            call = self._extract_static_call(node)
            if call:
                calls.append(call)
        
        # Recurse into children
        for child in node.children:
            self._traverse(child, functions, calls)
    
    def _extract_params(self, node: Node) -> list[str]:
        """Extract parameter names from a function node."""
        params = []
        params_node = node.child_by_field_name("parameters")
        if params_node:
            for child in params_node.named_children:
                if child.type == "simple_parameter":
                    name = child.child_by_field_name("name")
                    if name:
                        params.append(name.text.decode("utf-8"))
        return params
    
    def _extract_call(self, node: Node) -> FunctionCall | None:
        """Extract simple function call."""
        func_node = node.child_by_field_name("function")
        if not func_node:
            return None
        
        if func_node.type == "name":
            target = func_node.text.decode("utf-8")
        else:
            return None
        
        # Skip built-ins
        skip_targets = {
            "echo", "print", "print_r", "var_dump", "die", "exit",
            "isset", "empty", "unset", "array", "list",
            "count", "strlen", "substr", "str_replace",
            "json_encode", "json_decode", "implode", "explode",
            "include", "include_once", "require", "require_once"
        }
        if target in skip_targets:
            return None
        
        return FunctionCall(
            source=self._current_function,
            target=target,
            line=node.start_point[0] + 1,
            file=self._file_path,
            call_type="calls"
        )
    
    # ── DB method classification ──────────────────────────────────────
    # Methods where the first arg is a table name
    _DB_TABLE_METHODS_READ = {"get", "get_where", "from", "count_all", "count_all_results"}
    _DB_TABLE_METHODS_WRITE = {"insert", "insert_batch", "update", "update_batch", "delete", "replace", "empty_table", "truncate"}
    _DB_TABLE_METHODS_ALL = _DB_TABLE_METHODS_READ | _DB_TABLE_METHODS_WRITE
    # join() has table as first arg but is always a read operation
    _DB_JOIN_METHODS = {"join"}
    # Methods that don't carry table names (chaining context only)
    _DB_CHAIN_METHODS = {"select", "where", "or_where", "where_in", "or_where_in",
                         "where_not_in", "like", "or_like", "not_like",
                         "group_by", "order_by", "limit", "offset",
                         "set", "having", "distinct"}

    def _extract_member_call(self, node: Node) -> FunctionCall | list[FunctionCall] | None:
        """Extract method call: $obj->method() or $this->db->get('table').
        
        Handles:
        - DB interactions: $this->db->get('table')
        - CI3 loader calls: $this->load->model('name'), view, helper, library
        - Model method calls: $this->model_name->method()
        - Standard method calls: $this->method() or $obj->method()
        
        Returns a single FunctionCall, a list of FunctionCalls (for multi-table
        queries), or None if the call should be skipped.
        """
        name_node = node.child_by_field_name("name")  # method name (e.g. 'get')
        object_node = node.child_by_field_name("object") # e.g. '$this->db'

        if not name_node or not object_node:
            return None
        
        target_method = name_node.text.decode("utf-8")
        object_text = object_node.text.decode("utf-8")

        # ── DB Interactions (CodeIgniter Active Record) ────────────────
        if "->db" in object_text:
            db_result = self._extract_db_call(node, target_method, object_text)
            if db_result:
                return db_result
            # If it's a known chain method (select, where, etc.) skip it
            # to avoid polluting the call graph with noise
            if target_method in self._DB_CHAIN_METHODS:
                return None

        # ── CI3 Loader calls: $this->load->model/view/helper/library ──
        if object_text == "$this->load" and target_method in _CI3_LOADER_TYPES:
            return self._extract_loader_call(node, target_method)

        # ── Model method calls: $this->model_name->method() ──────────
        if object_text.startswith("$this->"):
            prop_name = object_text[7:]  # strip '$this->'
            # Check if this property is a loaded model (ends with _model)
            if prop_name.endswith("_model") or prop_name in self._loaded_models:
                return FunctionCall(
                    source=self._current_function,
                    target=f"{prop_name}::{target_method}",
                    line=node.start_point[0] + 1,
                    file=self._file_path,
                    call_type="model_call"
                )

        # ── Standard method call ──────────────────────────────────────
        target = target_method
        
        return FunctionCall(
            source=self._current_function,
            target=target,
            line=node.start_point[0] + 1,
            file=self._file_path,
            call_type="calls"
        )

    def _extract_db_call(self, node: Node, method: str, object_text: str
                         ) -> FunctionCall | list[FunctionCall] | None:
        """Extract DB table references from CodeIgniter Active Record calls.
        
        Handles:
        - Direct table methods: get('table'), insert('table'), from('table'), etc.
        - join('table condition', ...)
        - get_where('table', conditions)
        - insert_batch('table', data), update_batch('table', data, key)
        - query("SELECT ... FROM table JOIN table2 ...")
        """
        line = node.start_point[0] + 1

        # ── 1. Direct table-arg methods: get/insert/update/delete/from/etc. ──
        if method in self._DB_TABLE_METHODS_ALL:
            table = self._get_first_string_arg(node)
            if table:
                table = self._clean_table_name(table)
                op_type = "db_read" if method in self._DB_TABLE_METHODS_READ else "db_write"
                return FunctionCall(
                    source=self._current_function,
                    target=f"TABLE:{table}",
                    line=line,
                    file=self._file_path,
                    call_type=op_type,
                    target_file=None
                )

        # ── 2. join('table condition', ...) ──
        if method in self._DB_JOIN_METHODS:
            table = self._get_first_string_arg(node)
            if table:
                # join arg is often 'table_name alias ON ...' or 'table_name ON ...'
                table = table.split()[0]  # First word is the table name
                table = self._clean_table_name(table)
                return FunctionCall(
                    source=self._current_function,
                    target=f"TABLE:{table}",
                    line=line,
                    file=self._file_path,
                    call_type="db_read",
                    target_file=None
                )

        # ── 3. query("SQL string") — extract ALL table references ──
        if method == "query":
            return self._extract_tables_from_raw_sql(node, line)

        return None

    def _extract_tables_from_raw_sql(self, node: Node, line: int
                                     ) -> list[FunctionCall] | None:
        """Extract all table references from a raw SQL query string.
        
        Handles:
        - Simple strings: query("SELECT * FROM table")
        - Interpolated strings: query("SELECT * FROM $table")  
        - Concatenated strings: query("SELECT * FROM table WHERE " . $var)
        
        Finds tables after FROM, JOIN, UPDATE, INTO, and DELETE FROM keywords.
        Returns multiple FunctionCall objects for multi-table queries.
        """
        args_node = node.child_by_field_name("arguments")
        if not args_node or args_node.child_count == 0:
            return None

        for arg in args_node.children:
            if arg.type == "argument":
                arg = arg.children[0] if arg.children else arg
            
            sql = None
            
            # Simple string literal: query("SELECT ...")
            if arg.type == "string":
                sql = arg.text.decode("utf-8").strip("'\"")
            
            # Interpolated string: query("SELECT ... $var ...")
            elif arg.type == "encapsed_string":
                sql = arg.text.decode("utf-8").strip("'\"")
            
            # Concatenated string: query("SELECT ..." . $var . "...")
            # Collect all string fragments from the binary expression tree
            elif arg.type == "binary_expression":
                fragments = self._collect_string_fragments(arg)
                if fragments:
                    sql = " ".join(fragments)
            
            if sql:
                return self._parse_sql_tables(sql, line)
        return None

    def _collect_string_fragments(self, node: Node) -> list[str]:
        """Recursively collect string literal fragments from a concatenation tree.
        
        Handles nested binary_expression trees like:
            "SELECT * FROM " . $table . " WHERE " . $var
        Extracts: ["SELECT * FROM ", " WHERE "]
        """
        fragments = []
        
        if node.type == "string":
            text = node.text.decode("utf-8").strip("'\"")
            if text.strip():
                fragments.append(text)
        elif node.type == "encapsed_string":
            text = node.text.decode("utf-8").strip("'\"")
            if text.strip():
                fragments.append(text)
        elif node.type == "binary_expression":
            # Recurse into left and right children
            for child in node.children:
                if child.type == ".":  # PHP concatenation operator
                    continue
                fragments.extend(self._collect_string_fragments(child))
        # Variables, function calls, etc. — skip (can't resolve statically)
        
        return fragments

    def _parse_sql_tables(self, sql: str, line: int) -> list[FunctionCall] | None:
        """Parse a raw SQL string to find all referenced tables with read/write classification."""
        calls = []
        seen_tables = set()

        # Determine the primary operation for read/write classification
        sql_upper = sql.strip().upper()
        is_write = sql_upper.startswith(("INSERT", "UPDATE", "DELETE", "REPLACE", "CREATE", "ALTER", "DROP", "TRUNCATE"))

        # Pattern: FROM table, JOIN table, UPDATE table, INTO table, DELETE FROM table
        # Requires table name to start with a letter or underscore (not parentheses)
        # Also handles backtick-quoted: FROM `table` t
        table_pattern = re.compile(
            r"(?i)(?:FROM|JOIN|UPDATE|INTO|DELETE\s+FROM)\s+[`]?([a-zA-Z_][a-zA-Z0-9_]*)[`]?"
        )

        for match in table_pattern.finditer(sql):
            table_name = match.group(1)
            # Skip SQL keywords that might be false positives
            if table_name.upper() in (
                    # SQL keywords
                    "SELECT", "WHERE", "SET", "VALUES", "NULL", "FROM",
                    "AND", "OR", "NOT", "IN", "ON", "AS", "IF", "IS",
                    "LEFT", "RIGHT", "INNER", "OUTER", "CROSS", "JOIN",
                    "GROUP", "ORDER", "BY", "HAVING", "LIMIT", "OFFSET",
                    "DISTINCT", "ALL", "ANY", "EXISTS", "BETWEEN", "LIKE",
                    "CASE", "WHEN", "THEN", "ELSE", "END", "ASC", "DESC",
                    "UNION", "EXCEPT", "INTERSECT", "INTO",
                    "TABLE", "INDEX", "VIEW", "CREATE", "ALTER", "DROP",
                    "SHOW", "COLUMNS", "INFORMATION_SCHEMA", "TABLE_SCHEMA",
                    "TRUE", "FALSE", "CONCAT", "COALESCE", "IFNULL",
                    "COUNT", "SUM", "AVG", "MIN", "MAX",
                    # PHP/CI3 artifacts that leak through concatenation
                    "Old", "Tag", "Retaggnig", "Import",
                    ):
                continue
            # Skip single/two-letter strings — these are SQL aliases, not table names
            if len(table_name) <= 2:
                continue
            # Skip dotted references (column refs like wl.id_col) and subquery artifacts
            if '.' in table_name or table_name.startswith('('):
                continue
            if table_name not in seen_tables:
                seen_tables.add(table_name)
                calls.append(FunctionCall(
                    source=self._current_function,
                    target=f"TABLE:{table_name}",
                    line=line,
                    file=self._file_path,
                    call_type="db_write" if is_write else "db_read",
                    target_file=None
                ))

        return calls if calls else None

    def _get_first_string_arg(self, node: Node) -> str | None:
        """Extract the first string argument from a function call node."""
        args_node = node.child_by_field_name("arguments")
        if not args_node or args_node.child_count == 0:
            return None
        for arg in args_node.children:
            if arg.type == "argument":
                arg = arg.children[0] if arg.children else arg
            if arg.type == "string":
                return arg.text.decode("utf-8").strip("'\"")
        return None

    @staticmethod
    def _clean_table_name(table: str) -> str:
        """Clean a table name — remove aliases, backticks, schema prefixes.
        
        Examples:
            'ret_billing b'     -> 'ret_billing'
            '`ret_billing`'     -> 'ret_billing'
            'ret_billing AS b'  -> 'ret_billing'
        """
        table = table.strip().strip('`').strip()
        # Remove alias: 'table alias' or 'table AS alias'
        parts = table.split()
        return parts[0].strip('`')

    def _extract_loader_call(self, node: Node, loader_type: str
                             ) -> FunctionCall | list[FunctionCall] | None:
        """Extract CI3 loader calls: $this->load->model/view/helper/library.
        
        Handles:
        - $this->load->model('model_name')
        - $this->load->model('subdir/model_name')
        - $this->load->view('folder/view_name', $data)
        - $this->load->helper('helper_name')
        - $this->load->helper(array('helper1', 'helper2'))
        - $this->load->library('library_name')
        """
        call_type = _CI3_LOADER_TYPES[loader_type]
        line = node.start_point[0] + 1

        # Try to get the first argument
        args_node = node.child_by_field_name("arguments")
        if not args_node:
            return None

        results: list[FunctionCall] = []

        for arg in args_node.named_children:
            # Unwrap 'argument' wrapper node
            actual = arg
            if arg.type == "argument" and arg.named_children:
                actual = arg.named_children[0]

            # String argument: load->model('name')
            if actual.type == "string":
                name = actual.text.decode("utf-8").strip("'\"")
                # For models, track the loaded name for later resolution
                if loader_type == "model":
                    # 'subdir/model_name' -> loaded as 'model_name'
                    loaded_as = name.split("/")[-1]
                    self._loaded_models[loaded_as] = name
                
                results.append(FunctionCall(
                    source=self._current_function,
                    target=f"LOAD:{loader_type}:{name}",
                    line=line,
                    file=self._file_path,
                    call_type=call_type
                ))
                break  # Only first arg is the name

            # Array argument: load->helper(array('h1', 'h2'))
            elif actual.type == "array_creation_expression":
                for elem in actual.named_children:
                    if elem.type == "array_element_initializer":
                        for val in elem.named_children:
                            if val.type == "string":
                                name = val.text.decode("utf-8").strip("'\"")
                                results.append(FunctionCall(
                                    source=self._current_function,
                                    target=f"LOAD:{loader_type}:{name}",
                                    line=line,
                                    file=self._file_path,
                                    call_type=call_type
                                ))
                break

        return results if results else None
    
    def _extract_static_call(self, node: Node) -> FunctionCall | None:
        """Extract static call: Class::method()"""
        scope_node = node.child_by_field_name("scope")
        name_node = node.child_by_field_name("name")
        
        if not name_node:
            return None
        
        if scope_node and scope_node.type == "name":
            class_name = scope_node.text.decode("utf-8")
            method_name = name_node.text.decode("utf-8")
            target = f"{class_name}::{method_name}"
        else:
            target = name_node.text.decode("utf-8")
        
        return FunctionCall(
            source=self._current_function,
            target=target,
            line=node.start_point[0] + 1,
            file=self._file_path,
            call_type="calls"
        )
