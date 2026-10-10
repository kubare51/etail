"""Brain Builder v2.2 — Code Parser.

Extracts structured metadata from PHP, JS, and view files using regex patterns.
Handles: method extraction, table ownership, AJAX URLs, form fields, value→label maps,
and PHP if/elseif chain extraction.
"""

import re
from pathlib import Path
from typing import Dict, List, Optional, Set, Tuple

from lca.brain.models import ExtractedMethod


class CodeParser:
    """Extract structured metadata from PHP and JS files using regex patterns."""
    
    # PHP patterns
    RE_PHP_METHOD = re.compile(
        r'^\s*(public\s+|private\s+|protected\s+)?function\s+(\w+)\s*\(([^)]*)\)',
        re.MULTILINE
    )
    # Direct DB operations
    RE_DB_INSERT = re.compile(r'\$this->db->insert\s*\(\s*[\'"](\w+)[\'"]', re.IGNORECASE)
    RE_DB_UPDATE = re.compile(r'\$this->db->update\s*\(\s*[\'"](\w+)[\'"]', re.IGNORECASE)
    RE_DB_DELETE = re.compile(r'\$this->db->delete\s*\(\s*[\'"](\w+)[\'"]', re.IGNORECASE)
    RE_DB_FROM = re.compile(r'\$this->db->(?:from|get|get_where)\s*\(\s*[\'"](\w+)[\'"]', re.IGNORECASE)
    RE_DB_JOIN = re.compile(r'\$this->db->join\s*\(\s*[\'"](\w+)[\'"]', re.IGNORECASE)
    RE_DB_WHERE = re.compile(r'\$this->db->where\s*\(\s*[\'"](\w+)[\'"]', re.IGNORECASE)
    
    # v1.2: CORRECT generic wrapper signatures:
    #   insertData($data, $table)           → table is 2nd arg (after array/variable)
    #   insertBatchData($data, $table)      → table is 2nd arg
    #   updateData($data, $id_field, $id_value, $table) → table is 4th arg
    #   deleteData($id_field, $id_value, $table) → table is 3rd arg
    # The table name is always the LAST string argument
    RE_GENERIC_INSERT = re.compile(
        r'(?:insertData|insertBatchData)\s*\([^)]*,\s*[\'"](\w+)[\'"]\s*\)',
        re.IGNORECASE
    )
    RE_GENERIC_UPDATE = re.compile(
        r'updateData\s*\([^)]*,\s*[\'"](\w+)[\'"]\s*\)',
        re.IGNORECASE
    )
    RE_GENERIC_DELETE = re.compile(
        r'deleteData\s*\([^)]*,\s*[\'"](\w+)[\'"]\s*\)',
        re.IGNORECASE
    )
    
    # v1.2: Indirect calls via dynamic $model variable or named model
    #   $this->$model->deleteData('col', $val, 'table')
    #   $this->billing_model->insertData($data, 'table')
    RE_MODEL_INSERT = re.compile(
        r'\$this->(?:\$\w+|\w+_model)->(?:insertData|insertBatchData)\s*\([^)]*,\s*[\'"](\w+)[\'"]\s*\)',
        re.IGNORECASE
    )
    RE_MODEL_UPDATE = re.compile(
        r'\$this->(?:\$\w+|\w+_model)->updateData\s*\([^)]*,\s*[\'"](\w+)[\'"]\s*\)',
        re.IGNORECASE
    )
    RE_MODEL_DELETE = re.compile(
        r'\$this->(?:\$\w+|\w+_model)->deleteData\s*\([^)]*,\s*[\'"](\w+)[\'"]\s*\)',
        re.IGNORECASE
    )
    
    # v1.2: Raw SQL parser — catches $this->db->query("SELECT ... FROM table ...")
    RE_RAW_SQL_FROM = re.compile(
        r'FROM\s+[`]?(\w+)[`]?(?:\s+(?:as\s+)?\w+)?',
        re.IGNORECASE
    )
    RE_RAW_SQL_JOIN = re.compile(
        r'JOIN\s+[`]?(\w+)[`]?(?:\s+(?:as\s+)?\w+)?',
        re.IGNORECASE
    )
    RE_RAW_SQL_INSERT = re.compile(
        r'INSERT\s+(?:INTO\s+)?[`]?(\w+)[`]?',
        re.IGNORECASE
    )
    RE_RAW_SQL_UPDATE_STMT = re.compile(
        r'UPDATE\s+[`]?(\w+)[`]?\s+SET',
        re.IGNORECASE
    )
    RE_RAW_SQL_DELETE_STMT = re.compile(
        r'DELETE\s+FROM\s+[`]?(\w+)[`]?',
        re.IGNORECASE
    )

    RE_LOAD_MODEL = re.compile(r'\$this->load->model\s*\(\s*[\'"](\w+)[\'"]', re.IGNORECASE)
    # v1.2: Also catch $this->$model->method() dynamic calls
    RE_MODEL_CALL = re.compile(r'\$this->(?:(\w+_model)|\$(\w+))->(\w+)\s*\(', re.IGNORECASE)
    RE_INPUT_POST = re.compile(r'\$this->input->post\s*\(\s*[\'"](\w+)[\'"]', re.IGNORECASE)
    
    # v1.1: Much wider status check regex — catches more patterns
    RE_STATUS_WHERE = re.compile(
        r"""(?:
            # Pattern 1: $this->db->where('column', value)
            \$this->db->where\s*\(\s*['"]([\w.]+)['"]\s*,\s*(\d+|'[^']*'|"[^"]*")
            |
            # Pattern 2: where('column =', value)  or where('column !=', value)
            \$this->db->where\s*\(\s*['"](\w+)\s*[!=<>]+\s*['"]\s*,\s*(\d+|'[^']*'|"[^"]*")
            |
            # Pattern 3: ==, != in plain PHP: $var == 1 or $row->status == 2
            (?:\$\w+(?:->(\w+))?)\s*(?:==|!=)\s*(\d+)
        )""",
        re.IGNORECASE | re.VERBOSE
    )
    
    # v1.1: Detect specific patterns
    RE_JSON_ENCODE = re.compile(r'json_encode\s*\(', re.IGNORECASE)
    RE_TRANS_BEGIN = re.compile(r'\$this->db->trans_(?:begin|start)\s*\(', re.IGNORECASE)
    RE_TRANS_END = re.compile(r'\$this->db->trans_complete\s*\(', re.IGNORECASE)
    RE_OTP_PATTERN = re.compile(r'(?:sendotp|send_otp|verify_otp|otp_|_otp)', re.IGNORECASE)
    RE_FILE_UPLOAD = re.compile(r'(?:upload|move_uploaded|tmp_name|base64ToFile)', re.IGNORECASE)
    
    # JS patterns
    RE_JS_FUNCTION = re.compile(
        r'(?:function\s+(\w+)\s*\(([^)]*)\)|(\w+)\s*[:=]\s*function\s*\(([^)]*)\))',
        re.MULTILINE
    )
    # v1.1: Much wider AJAX detection — catches url concatenation patterns
    RE_AJAX_URL = re.compile(r'url\s*:\s*[\'"]([^"\']+)[\'"]', re.IGNORECASE)
    RE_AJAX_BASEURL = re.compile(
        r"""(?:
            url\s*:\s*(?:base_url|baseUrl|site_url)\s*\+\s*['"]([^'"]+)['"]
            |
            url\s*:\s*['"]([^'"]+)['"]
            |
            \$\.(?:ajax|post|get)\s*\(\s*(?:base_url|baseUrl|site_url)\s*\+\s*['"]([^'"]+)['"]
            |
            \$\.(?:ajax|post|get)\s*\(\s*['"]([^'"]+)['"]
        )""",
        re.IGNORECASE | re.VERBOSE
    )
    RE_JQUERY_SELECTOR = re.compile(r'\$\s*\(\s*[\'"]([#.]\w+)[\'"]', re.IGNORECASE)
    
    def __init__(self, project_root: str):
        self.project_root = Path(project_root)
        self.admin_root = self.project_root / "admin"
    
    def find_module_files(self, module_name: str) -> Dict[str, List[Path]]:
        """Find all files belonging to a module by name patterns."""
        result = {
            'controllers': [],
            'models': [],
            'views': [],
            'js': [],
            'css': []
        }
        
        patterns = self._get_module_patterns(module_name)
        
        # Controllers
        ctrl_dir = self.admin_root / "application" / "controllers"
        if ctrl_dir.exists():
            for f in ctrl_dir.glob("*.php"):
                if any(p in f.stem.lower() for p in patterns):
                    result['controllers'].append(f)
        
        # Models
        model_dir = self.admin_root / "application" / "models"
        if model_dir.exists():
            for f in model_dir.glob("*.php"):
                if any(p in f.stem.lower() for p in patterns):
                    result['models'].append(f)
        
        # v1.1 FIX: Views — deduplicate with set
        view_dir = self.admin_root / "application" / "views"
        seen_views = set()
        if view_dir.exists():
            for pattern in patterns:
                for d in view_dir.iterdir():
                    if d.is_dir() and pattern in d.name.lower():
                        for f in d.rglob("*.php"):
                            if str(f) not in seen_views:
                                seen_views.add(str(f))
                                result['views'].append(f)
        
        # JS files
        js_dir = self.admin_root / "assets" / "js"
        if js_dir.exists():
            for f in js_dir.glob("*.js"):
                if any(p in f.stem.lower() for p in patterns):
                    result['js'].append(f)
        
        # CSS files
        css_dir = self.admin_root / "assets" / "css"
        if css_dir.exists():
            for f in css_dir.glob("*.css"):
                if any(p in f.stem.lower() for p in patterns):
                    result['css'].append(f)
        
        return result
    
    def _get_module_patterns(self, module_name: str) -> List[str]:
        """Get search patterns for a module name."""
        name = module_name.lower().strip()
        patterns = [name]
        
        aliases = {
            'billing': ['billing', 'bill', 'ret_billing', 'invoice'],
            'estimation': ['estimation', 'estimate', 'ret_estimation'],
            'stock_issue': ['stock_issue', 'stock', 'stockissue'],
            'purchase': ['purchase', 'ret_purchase', 'supplier'],
            'customer_order': ['customer_order', 'customerorder', 'order'],
            'tagging': ['tagging', 'taging', 'tag', 'ret_taging'],
            'lot': ['lot', 'lot_inward', 'lotinward'],
            'payment': ['payment', 'issue_receipt', 'receipt'],
            'reports': ['report', 'ret_reports', 'rep_'],
            'account': ['account', 'acc_', 'journal', 'ledger'],
        }
        
        if name in aliases:
            patterns = aliases[name]
        
        return patterns
    
    def parse_php_file(self, file_path: Path) -> List[ExtractedMethod]:
        """Parse a PHP file and extract method metadata."""
        try:
            content = file_path.read_text(encoding='utf-8', errors='replace')
        except Exception:
            return []
        
        lines = content.split('\n')
        methods = []
        
        for match in self.RE_PHP_METHOD.finditer(content):
            visibility = (match.group(1) or 'function').strip()
            name = match.group(2)
            params = match.group(3).strip()
            start_pos = match.start()
            start_line = content[:start_pos].count('\n') + 1
            
            end_line = self._find_method_end(lines, start_line - 1)
            method_body = '\n'.join(lines[start_line - 1:end_line])
            
            method = ExtractedMethod(
                name=name,
                file_path=str(file_path),
                start_line=start_line,
                end_line=end_line,
                visibility=visibility,
                params=params,
                line_count=end_line - start_line + 1
            )
            
            # v1.2: Extract tables — direct + generic wrappers + indirect + raw SQL
            direct_insert = set(self.RE_DB_INSERT.findall(method_body))
            generic_insert = set(self.RE_GENERIC_INSERT.findall(method_body))
            indirect_insert = set(self.RE_MODEL_INSERT.findall(method_body))
            method.tables_insert = list(self._filter_tables(direct_insert | generic_insert | indirect_insert))
            
            direct_update = set(self.RE_DB_UPDATE.findall(method_body))
            generic_update = set(self.RE_GENERIC_UPDATE.findall(method_body))
            indirect_update = set(self.RE_MODEL_UPDATE.findall(method_body))
            method.tables_update = list(self._filter_tables(direct_update | generic_update | indirect_update))
            
            direct_delete = set(self.RE_DB_DELETE.findall(method_body))
            generic_delete = set(self.RE_GENERIC_DELETE.findall(method_body))
            indirect_delete = set(self.RE_MODEL_DELETE.findall(method_body))
            method.tables_delete = list(self._filter_tables(direct_delete | generic_delete | indirect_delete))
            
            select_tables = set(self.RE_DB_FROM.findall(method_body))
            select_tables.update(self.RE_DB_JOIN.findall(method_body))
            
            # v1.2: Also extract from raw SQL strings
            raw_sql_tables = self._extract_raw_sql_tables(method_body)
            select_tables.update(raw_sql_tables['select'])
            method.tables_insert = list(set(method.tables_insert) | self._filter_tables(raw_sql_tables['insert']))
            method.tables_update = list(set(method.tables_update) | self._filter_tables(raw_sql_tables['update']))
            method.tables_delete = list(set(method.tables_delete) | self._filter_tables(raw_sql_tables['delete']))
            method.tables_select = list(self._filter_tables(select_tables))
            
            # v1.2: Model calls — handle both named model and dynamic $model
            model_calls = set()
            for m_match in self.RE_MODEL_CALL.finditer(method_body):
                model_name = m_match.group(1) or f"${m_match.group(2)}"
                method_name = m_match.group(3)
                model_calls.add(f"{model_name}->{method_name}")
            method.models_called = list(model_calls)
            
            # v1.1: Wider status check extraction
            method.status_checks = self._extract_status_checks(method_body)
            
            # v1.1: Detect method characteristics
            method.has_transaction = bool(self.RE_TRANS_BEGIN.search(method_body))
            method.has_json_response = bool(self.RE_JSON_ENCODE.search(method_body))
            method.has_otp = bool(self.RE_OTP_PATTERN.search(method_body)) or 'otp' in name.lower()
            method.has_file_upload = bool(self.RE_FILE_UPLOAD.search(method_body))
            
            # v1.1: Extract POST fields
            method.post_fields = list(set(self.RE_INPUT_POST.findall(method_body)))
            
            # Validations
            method.validations = self._extract_validations(method_body)
            
            methods.append(method)
        
        return methods
    
    def _extract_status_checks(self, code: str) -> List[str]:
        """Extract status-like column=value checks from code. Much wider than v1.0."""
        checks = set()
        
        # Pattern 1: $this->db->where('column', value) — direct DB queries
        for m in re.finditer(
            r"\$this->db->where\s*\(\s*['\"](\w+)['\"]"
            r"\s*,\s*(\d+)",
            code
        ):
            col, val = m.group(1), m.group(2)
            # Only capture columns that look like status/flag/type
            if self._is_status_column(col):
                checks.add(f"{col}={val}")
        
        # Pattern 2: where('column =', value)
        for m in re.finditer(
            r"\$this->db->where\s*\(\s*['\"](\w+)\s*=\s*['\"]"
            r"\s*,\s*(\d+)",
            code
        ):
            col, val = m.group(1), m.group(2)
            if self._is_status_column(col):
                checks.add(f"{col}={val}")
        
        # Pattern 3: PHP comparisons: $var->column == value or $data['column'] == value
        for m in re.finditer(
            r"(?:\$\w+->(\w+)|\$\w+\[['\"](\w+)['\"]\])\s*==\s*(\d+)",
            code
        ):
            col = m.group(1) or m.group(2)
            val = m.group(3)
            if self._is_status_column(col):
                checks.add(f"{col}={val}")
        
        # Pattern 4: switch($type) case 'save': — for method routing
        for m in re.finditer(
            r"case\s+['\"](\w+)['\"]",
            code
        ):
            checks.add(f"$type={m.group(1)}")
        
        return list(checks)
    
    def _is_status_column(self, col_name: str) -> bool:
        """Check if a column name looks like a status/type/flag column."""
        status_indicators = [
            'status', 'type', 'is_', 'flag', 'mode', 'state',
            'category', 'level', 'priority', 'role', 'tag_status',
            'bill_status', 'credit_status', 'return_status', 'payment_mode',
            'item_type', 'bill_type', 'bill_discount_type', 'mc_type',
            'calculation_based_on', 'billing_for', 'make_as_advance',
            'salesTransType', 'is_eda', 'is_credit', 'is_bill_split',
            'is_partial_sale', 'is_non_tag', 'is_delivered', 'is_trail',
            'receipt_type', 'pos_req_status', 'fin_status', 'purchase_status'
        ]
        col_lower = col_name.lower()
        return any(s in col_lower for s in status_indicators) or col_lower in status_indicators
    
    # v1.2: Table name validation — reject PHP/SQL keywords and non-table names
    NOISE_WORDS = frozenset([
        'select', 'from', 'where', 'and', 'or', 'not', 'in', 'null', 'set',
        'values', 'into', 'update', 'delete', 'insert', 'join', 'left', 'right',
        'inner', 'outer', 'on', 'as', 'order', 'by', 'group', 'having', 'limit',
        'offset', 'distinct', 'count', 'sum', 'avg', 'min', 'max', 'case', 'when',
        'then', 'else', 'end', 'if', 'ifnull', 'concat', 'date', 'date_format',
        'str_pad', 'true', 'false', 'this', 'self', 'data', 'result', 'status',
        'table', 'field', 'value', 'key', 'index', 'column', 'row',
        'INFORMATION_SCHEMA', 'COLUMNS', 'dual',
    ])
    
    def _filter_tables(self, names: set) -> set:
        """Filter out non-table names from extracted set."""
        return {
            n for n in names
            if n.lower() not in self.NOISE_WORDS
            and len(n) > 2
            and not n.startswith('$')
            and not n[0].isdigit()
        }
    
    def _extract_raw_sql_tables(self, code: str) -> Dict[str, set]:
        """v1.2: Extract tables from raw SQL in $this->db->query() calls."""
        result = {'select': set(), 'insert': set(), 'update': set(), 'delete': set()}
        
        # Find all SQL strings inside db->query() calls
        sql_strings = re.findall(
            r'\$this->db->query\s*\(\s*["\'](.+?)["\'](?:\s*\..*?)*\s*\)',
            code, re.DOTALL | re.IGNORECASE
        )
        # Also match multi-line query patterns: $this->db->query("SELECT...
        sql_strings += re.findall(
            r'\$this->db->query\s*\(\s*["\'](.+?)["\']',
            code, re.DOTALL | re.IGNORECASE
        )
        # Also match: $sql = "SELECT ... FROM table ..."; (raw SQL assigned to variable)
        sql_strings += re.findall(
            r'\$sql\s*=\s*["\'](.+?)["\']',
            code, re.DOTALL | re.IGNORECASE
        )
        
        for sql in sql_strings:
            # Clean up PHP concatenation artifacts
            sql_clean = re.sub(r'\.\s*\$\w+(?:->\w+)*\s*\.', ' ', sql)
            sql_clean = re.sub(r'"\s*\.\s*"', ' ', sql_clean)
            
            # Extract tables
            for m in self.RE_RAW_SQL_FROM.finditer(sql_clean):
                result['select'].add(m.group(1))
            for m in self.RE_RAW_SQL_JOIN.finditer(sql_clean):
                result['select'].add(m.group(1))
            for m in self.RE_RAW_SQL_INSERT.finditer(sql_clean):
                result['insert'].add(m.group(1))
            for m in self.RE_RAW_SQL_UPDATE_STMT.finditer(sql_clean):
                result['update'].add(m.group(1))
            for m in self.RE_RAW_SQL_DELETE_STMT.finditer(sql_clean):
                result['delete'].add(m.group(1))
        
        return result
    
    def extract_sql_value_maps(self, code: str) -> Dict[str, Dict[str, str]]:
        """v1.3: Extract value→meaning maps from SQL IF chains.
        
        Parses patterns like:
          if(bill_type = 1,'Sales',if(bill_type = 2,'Sales&Purchase',...))
          if(bill_status = 1,'Success',if(bill_status = 2,'Cancelled',''))
        
        Returns: {'bill_type': {'1': 'Sales', '2': 'Sales&Purchase', ...}}
        """
        maps = {}
        
        # Pattern: if(column = value, 'label', ...)
        # Captures: column, value, label
        for m in re.finditer(
            r"if\s*\(\s*(?:\w+\.)?(\w+)\s*=\s*(\d+)\s*,\s*'([^']+)'",
            code, re.IGNORECASE
        ):
            col, val, label = m.group(1), m.group(2), m.group(3)
            if col not in maps:
                maps[col] = {}
            maps[col][val] = label
        
        return maps
    
    def _find_method_end(self, lines: List[str], start_idx: int) -> int:
        """Find the ending line of a method by counting braces."""
        brace_count = 0
        found_open = False
        in_string = False
        string_char = None
        
        for i in range(start_idx, min(len(lines), start_idx + 6000)):
            line = lines[i]
            # v1.1: Better string/comment handling
            j = 0
            while j < len(line):
                char = line[j]
                
                # Skip line comments
                if not in_string and j + 1 < len(line):
                    two = line[j:j+2]
                    if two == '//' or two == '# ':
                        break
                
                # String handling
                if char in ('"', "'") and not in_string:
                    in_string = True
                    string_char = char
                elif char == string_char and in_string:
                    # Check for escape
                    if j > 0 and line[j-1] != '\\':
                        in_string = False
                        string_char = None
                elif not in_string:
                    if char == '{':
                        brace_count += 1
                        found_open = True
                    elif char == '}':
                        brace_count -= 1
                
                j += 1
            
            if found_open and brace_count == 0:
                return i + 1
        
        return min(start_idx + 50, len(lines))
    
    def _extract_validations(self, code: str) -> List[str]:
        """Extract validation-like IF conditions."""
        validations = []
        patterns = [
            r'if\s*\(\s*(?:empty|!isset|strlen|is_null|sizeof)\s*\(\s*\$(\w+)',
            r'if\s*\(\s*\$(\w+)\s*(?:==|!=|>|<|>=|<=)',
        ]
        for pat in patterns:
            for m in re.finditer(pat, code):
                validations.append(m.group(0)[:80])
        return validations[:20]
    
    def parse_js_file(self, file_path: Path) -> Dict:
        """Parse a JS file and extract function names + AJAX calls."""
        try:
            content = file_path.read_text(encoding='utf-8', errors='replace')
        except Exception:
            return {'functions': [], 'ajax_urls': [], 'selectors': [], 'line_count': 0}
        
        functions = []
        for m in self.RE_JS_FUNCTION.finditer(content):
            name = m.group(1) or m.group(3)
            params = m.group(2) or m.group(4) or ""
            line = content[:m.start()].count('\n') + 1
            if name:
                functions.append({'name': name, 'line': line, 'params': params})
        
        # v1.1: Better AJAX URL extraction
        ajax_urls = set()
        for m in self.RE_AJAX_BASEURL.finditer(content):
            url = m.group(1) or m.group(2) or m.group(3) or m.group(4)
            if url and not url.startswith('#') and len(url) > 3:
                ajax_urls.add(url)
        
        # Also check for $.ajax, $.post, $.get patterns
        for m in re.finditer(r'\$\.(?:ajax|post|get)\s*\(', content):
            # Get the next 200 chars after the match to find url
            snippet = content[m.start():m.start()+300]
            url_match = re.search(r'url\s*:\s*[\'"]([^"\']+)[\'"]', snippet)
            if url_match:
                ajax_urls.add(url_match.group(1))
            # Also check for direct URL as first arg: $.post('url', data)
            direct_match = re.search(r'\$\.(?:post|get)\s*\(\s*[\'"]([^"\']+)[\'"]', snippet)
            if direct_match:
                ajax_urls.add(direct_match.group(1))
        
        selectors = list(set(self.RE_JQUERY_SELECTOR.findall(content)))[:30]
        
        return {
            'file_name': file_path.name,
            'functions': functions,
            'ajax_urls': sorted(ajax_urls),
            'selectors': selectors,
            'line_count': content.count('\n') + 1
        }
    
    def parse_view_file(self, file_path: Path) -> Dict:
        """v2.0: Parse a PHP view file and extract form fields, hidden settings,
        radio/select value→label maps, tab structure, and model calls."""
        try:
            content = file_path.read_text(encoding='utf-8', errors='replace')
        except Exception:
            return {'file_name': file_path.name, 'form_fields': [], 'hidden_settings': [],
                    'value_label_maps': {}, 'tabs': [], 'model_calls': [], 'line_count': 0,
                    'inline_css_lines': 0}
        
        # v2.0 fix: Strip PHP tags to prevent regex confusion
        # PHP tags like <?php echo $x == 1 ? 'checked' : '' ?> break HTML regex
        # because the ?> introduces a > character inside an HTML tag
        content_clean = re.sub(r'<\?php.*?\?>', '', content, flags=re.DOTALL)
        
        result = {
            'file_name': file_path.name,
            'line_count': content.count('\n') + 1,
            'form_fields': [],
            'hidden_settings': [],
            'value_label_maps': {},
            'tabs': [],
            'model_calls': [],
            'inline_css_lines': 0,
        }
        
        # 1. Form fields: name="billing[field_name]" or name="field_name"
        seen_fields = set()
        for m in re.finditer(
            r'name\s*=\s*["\']([\w\[\]]+)["\']\s*',
            content_clean, re.IGNORECASE
        ):
            field_name = m.group(1)
            if field_name in seen_fields:
                continue
            seen_fields.add(field_name)
            
            # Look around the match for type and id
            snippet = content[max(0, m.start()-200):min(len(content), m.end()+200)]
            type_match = re.search(r'type\s*=\s*["\']([\w]+)["\']', snippet)
            input_type = type_match.group(1) if type_match else 'text'
            id_match = re.search(r'\bid\s*=\s*["\']([\w]+)["\']', snippet)
            field_id = id_match.group(1) if id_match else ''
            
            result['form_fields'].append({
                'name': field_name,
                'type': input_type,
                'id': field_id,
            })
        
        # 2. Hidden settings: <input type="hidden" id="X" value="<?php ...?>">
        for m in re.finditer(
            r'<input\s+[^>]*type\s*=\s*["\']hidden["\'][^>]*>',
            content, re.IGNORECASE
        ):
            tag = m.group(0)
            id_match = re.search(r'\bid\s*=\s*["\']([\w]+)["\']', tag)
            value_match = re.search(r'value\s*=\s*["\'](.*?)["\']', tag, re.DOTALL)
            name_match = re.search(r'name\s*=\s*["\']([\w\[\]]+)["\']', tag)
            if id_match:
                val_str = value_match.group(1) if value_match else ''
                setting = {
                    'id': id_match.group(1),
                    'name': name_match.group(1) if name_match else '',
                    'has_php_value': '<?php' in val_str,
                }
                if '<?php' in val_str:
                    php_match = re.search(r'\$(?:this->)?([\w\->]+)', val_str)
                    if php_match:
                        setting['php_source'] = php_match.group(0)
                result['hidden_settings'].append(setting)
        
        # 3. Radio value→label maps
        # Pattern: value="1" ...> Label  or  value="1"> <label>Label</label>
        for m in re.finditer(
            r'<input[^>]*type\s*=\s*["\']radio["\'][^>]*value\s*=\s*["\']([\w]+)["\'][^>]*>\s*(?:<label[^>]*>)?\s*([^<\n]+)',
            content_clean, re.IGNORECASE
        ):
            val = m.group(1)
            label = m.group(2).strip().strip(')').strip()
            # v2.0 fix: reject labels that are actually HTML attributes (contain =, >, <)
            if not label or len(label) > 50 or '<?php' in label or '=' in label or '>' in label:
                continue
            
            # Find the field name for this radio
            snippet = content_clean[max(0, m.start()-100):m.end()]
            name_match = re.search(r'name\s*=\s*["\']([\w\[\]]+)["\']', snippet)
            if name_match:
                field = name_match.group(1)
                # Normalize: billing[bill_type] -> bill_type
                field_clean = re.sub(r'^\w+\[([\w]+)\]$', r'\1', field)
                if field_clean not in result['value_label_maps']:
                    result['value_label_maps'][field_clean] = {}
                result['value_label_maps'][field_clean][val] = label
        
        # Also handle: value="1" checked tabindex=9> <label for="X" class="Y"> SALES </label>
        for m in re.finditer(
            r'value\s*=\s*["\']([\w]+)["\'][^>]*>\s*<label[^>]*>\s*([^<]+?)\s*</label>',
            content_clean, re.IGNORECASE
        ):
            val = m.group(1)
            label = m.group(2).strip()
            if not label or len(label) > 50 or '<?php' in label:
                continue
            snippet = content_clean[max(0, m.start()-200):m.end()]
            name_match = re.search(r'name\s*=\s*["\']([\w\[\]]+)["\']', snippet)
            if name_match:
                field = name_match.group(1)
                field_clean = re.sub(r'^\w+\[([\w]+)\]$', r'\1', field)
                if field_clean not in result['value_label_maps']:
                    result['value_label_maps'][field_clean] = {}
                if val not in result['value_label_maps'][field_clean]:
                    result['value_label_maps'][field_clean][val] = label
        
        # Select/option pairs
        for m in re.finditer(
            r'<option[^>]*value\s*=\s*["\']?([\w]+)["\']?[^>]*>([^<]+)</option>',
            content_clean, re.IGNORECASE
        ):
            val = m.group(1)
            label = m.group(2).strip()
            if not label or '<?php' in label or len(label) > 50:
                continue
            before = content_clean[max(0, m.start()-500):m.start()]
            select_match = re.search(r'<select[^>]*name\s*=\s*["\']([\w\[\]]+)["\']', before)
            if select_match:
                field = select_match.group(1)
                field_clean = re.sub(r'^\w+\[([\w]+)\]$', r'\1', field)
                if field_clean not in result['value_label_maps']:
                    result['value_label_maps'][field_clean] = {}
                result['value_label_maps'][field_clean][val] = label
        
        # 3b. v2.2: PHP if/elseif chain extraction from raw content (NOT content_clean)
        # Pattern A: Direct — if ($var['col'] == val) { $title = 'Label'; }
        if_chain_pat = re.compile(
            r"(?:else\s*)?if\s*\(\s*"
            r"\$(\w+)\s*\[\s*['\"](\w+)['\"]\s*\]"
            r"\s*==\s*"
            r"['\"]?(\d+)['\"]?"
            r"\s*\)"
            r"\s*\{?\s*"
            r"(?://[^\n]*\n)?"
            r"\s*\$(\w+)\s*=\s*"
            r"['\"]([^'\"]+)['\"]",
            re.IGNORECASE
        )
        for m in if_chain_pat.finditer(content):
            col_name = m.group(2)
            val = m.group(3)
            label = m.group(5).strip()
            if label and len(label) <= 80:
                if col_name not in result['value_label_maps']:
                    result['value_label_maps'][col_name] = {}
                if val not in result['value_label_maps'][col_name]:
                    result['value_label_maps'][col_name][val] = label
        
        # Pattern B: Ternary — $var['col']==val ? 'Label' : ...
        ternary_pat = re.compile(
            r"\$(\w+)\s*\[\s*['\"](\w+)['\"]\s*\]"
            r"\s*==\s*"
            r"['\"]?(\d+)['\"]?"
            r"\s*\?\s*"
            r"['\"]([^'\"]+)['\"]",
            re.IGNORECASE
        )
        for m in ternary_pat.finditer(content):
            col_name = m.group(2)
            val = m.group(3)
            label = m.group(4).strip()
            if label and len(label) <= 80:
                if col_name not in result['value_label_maps']:
                    result['value_label_maps'][col_name] = {}
                if val not in result['value_label_maps'][col_name]:
                    result['value_label_maps'][col_name][val] = label
        
        # Pattern C: Loose — if check with nested code before assignment
        loose_pat = re.compile(
            r"(?:else\s*)?if\s*\(\s*"
            r"\$(\w+)\s*\[\s*['\"](\w+)['\"]\s*\]"
            r"\s*==\s*"
            r"['\"]?(\d+)['\"]?"
            r"\s*\)",
            re.IGNORECASE
        )
        assign_pat = re.compile(r"\$(\w+)\s*=\s*['\"]([^'\"]+)['\"]")
        label_vars = {'title', 'bill_title', 'heading', 'label', 'type_name'}
        
        for m in loose_pat.finditer(content):
            col_name = m.group(2)
            val = m.group(3)
            if col_name in result['value_label_maps'] and val in result['value_label_maps'][col_name]:
                continue
            lookahead = content[m.end():m.end()+300]
            next_check = re.search(
                r"(?:else\s*)?if\s*\(\s*\$\w+\s*\[\s*['\"]" + re.escape(col_name) + r"['\"]\s*\]\s*==",
                lookahead
            )
            if next_check:
                lookahead = lookahead[:next_check.start()]
            assign_match = assign_pat.search(lookahead)
            if assign_match and assign_match.group(1) in label_vars:
                label = assign_match.group(2).strip()
                if label and len(label) <= 80:
                    if col_name not in result['value_label_maps']:
                        result['value_label_maps'][col_name] = {}
                    if val not in result['value_label_maps'][col_name]:
                        result['value_label_maps'][col_name][val] = label
        
        # 4. Tab structure from nav-tabs
        for m in re.finditer(
            r'<a[^>]*href\s*=\s*["\']\#([\w]+)["\'][^>]*>([^<]+)',
            content_clean, re.IGNORECASE
        ):
            before = content_clean[max(0, m.start()-200):m.start()]
            if 'data-toggle="tab"' in before or "data-toggle='tab'" in before or \
               'data-toggle="tab"' in m.group(0):
                result['tabs'].append({'id': m.group(1), 'label': m.group(2).strip()})
        # Simpler: just find tab-pane IDs and their labels
        for m in re.finditer(
            r'data-toggle\s*=\s*["\']tab["\'][^>]*>([^<]+)',
            content_clean, re.IGNORECASE
        ):
            label = m.group(1).strip()
            if label and label not in [t.get('label') for t in result['tabs']]:
                # Find href
                snippet = content_clean[max(0, m.start()-200):m.end()]
                href_match = re.search(r'href\s*=\s*["\']\#([\w]+)["\']', snippet)
                if href_match:
                    result['tabs'].append({'id': href_match.group(1), 'label': label})
        
        # 5. Model calls from views
        for m in re.finditer(
            r'\$this->([\w]+_model)->([\w]+)\s*\(',
            content, re.IGNORECASE
        ):
            call = f"{m.group(1)}->{m.group(2)}"
            if call not in result['model_calls']:
                result['model_calls'].append(call)
        
        # 6. Inline CSS size
        style_blocks = re.findall(r'<style[^>]*>(.*?)</style>', content, re.DOTALL | re.IGNORECASE)
        result['inline_css_lines'] = sum(block.count('\n') for block in style_blocks)
        
        return result
    
    def extract_all_tables(self, methods: List[ExtractedMethod]) -> Tuple[Set[str], Set[str]]:
        """From all methods, determine owned vs referenced tables."""
        written = set()
        read_only = set()
        
        for m in methods:
            written.update(m.tables_insert)
            written.update(m.tables_update)
            written.update(m.tables_delete)
            read_only.update(m.tables_select)
        
        referenced = read_only - written
        return written, referenced
    
    def detect_duplicates(self, methods: List[ExtractedMethod]) -> List[Tuple[str, str]]:
        """v1.1: Detect methods with the same name in the same file (likely bugs)."""
        seen = {}
        duplicates = []
        for m in methods:
            key = (m.file_path, m.name)
            if key in seen:
                duplicates.append((
                    f"{Path(m.file_path).name}:{seen[key]}",
                    f"{Path(m.file_path).name}:{m.start_line}"
                ))
            else:
                seen[key] = m.start_line
        return duplicates
