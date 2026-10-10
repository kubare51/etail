"""
Smart code chunker for CodeIgniter 3 ERP codebases.

Splits source files into meaningful chunks based on language-specific boundaries:
  - PHP: class methods, standalone functions
  - JS: functions, event handlers, AJAX calls
  - CSS: major sections (by comment headers)
  - MD: sections by heading
  - Config/SQL: whole file

Each chunk is enriched with metadata extracted via regex patterns.
"""
from __future__ import annotations
import re
import os
from pathlib import Path
from typing import Generator

from .models import CodeChunk, ChunkType, IngestStats


# â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
# Module detection â€” map file paths to business modules
# â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

MODULE_KEYWORDS = {
    'billing': ['billing', 'bill', 'invoice', 'gst', 'tax'],
    'estimation': ['estimation', 'estimate', 'quotation', 'quote'],
    'customer': ['customer', 'cus_', 'client'],
    'stock': ['stock', 'inventory', 'warehouse', 'godown'],
    'purchase': ['purchase', 'po_', 'supplier', 'vendor'],
    'order': ['order', 'booking', 'so_'],
    'employee': ['employee', 'emp_', 'salary', 'attendance', 'payroll'],
    'accounts': ['accounts', 'ledger', 'journal', 'voucher', 'daybook'],
    'reports': ['report', 'dashboard', 'analytics'],
    'settings': ['settings', 'config', 'setup', 'admin_settings'],
    'auth': ['auth', 'login', 'access_right', 'permission', 'role'],
    'scheme': ['scheme', 'savings', 'chit', 'plan'],
    'repair': ['repair', 'job_card', 'service'],
    'crm': ['crm', 'lead', 'follow_up', 'campaign'],
    'notification': ['notification', 'alert', 'sms', 'email_template'],
}


def detect_module(file_path: str, content: str = "") -> str:
    """Detect the business module from the file path, name, and optionally content."""
    lower = file_path.lower().replace('\\', '/')

    # Check knowledge_brain path first
    if 'knowledge_brain/' in lower:
        parts = lower.split('knowledge_brain/')
        if len(parts) > 1:
            module_part = parts[1].split('/')[0]
            if module_part != '_system':
                return module_part

    # Check path against keyword map
    for module, keywords in MODULE_KEYWORDS.items():
        for kw in keywords:
            if kw in lower:
                return module

    # Fallback: scan content for module-specific table names
    if content:
        content_lower = content[:5000].lower()  # Only scan first 5KB for speed
        TABLE_MODULE_MAP = {
            'billing': ['billing_master', 'billing_items', 'billing_detail', 'bill_no'],
            'estimation': ['estimation_master', 'estimation_items', 'estimation_detail', 'est_no'],
            'customer': ['customer_master', 'customer_detail', 'cus_id', 'customer_id'],
            'stock': ['stock_master', 'stock_issue', 'stock_transfer', 'godown_master'],
            'purchase': ['purchase_master', 'purchase_items', 'purchase_order', 'supplier_master'],
            'order': ['order_master', 'order_items', 'order_detail', 'booking_master'],
            'employee': ['employee_master', 'emp_master', 'salary_master', 'attendance'],
            'accounts': ['ledger_master', 'journal_entry', 'voucher', 'daybook'],
            'scheme': ['scheme_master', 'scheme_detail', 'savings', 'chit_master'],
            'repair': ['repair_master', 'job_card', 'service_master'],
        }
        for module, table_hints in TABLE_MODULE_MAP.items():
            for hint in table_hints:
                if hint in content_lower:
                    return module

    return 'unknown'


# â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
# PHP chunker â€” split by class methods
# â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

# Match: public/private/protected function method_name(...)
PHP_METHOD_PATTERN = re.compile(
    r'^[ \t]*((?:public|private|protected)\s+)?function\s+(\w+)\s*\(',
    re.MULTILINE
)

# Match: $this->load->model('model_name')
PHP_LOAD_MODEL = re.compile(r"\$this->load->model\(\s*['\"]([^'\"]+)['\"]", re.IGNORECASE)

# Match: $this->load->view('view_name')
PHP_LOAD_VIEW = re.compile(r"\$this->load->view\(\s*['\"]([^'\"]+)['\"]", re.IGNORECASE)

# Match: $this->db->from('table') or $this->db->get('table') or ->join('table'
PHP_TABLE_REF = re.compile(
    r"(?:->from|->get|->join|->insert|->update|->delete|->where)\(\s*['\"](\w+)['\"]",
    re.IGNORECASE
)

# Match: class ClassName extends CI_Controller/CI_Model
PHP_CLASS_PATTERN = re.compile(
    r'^class\s+(\w+)\s+extends\s+(\w+)', re.MULTILINE
)


def _find_method_end(lines: list[str], start_idx: int) -> int:
    """Find the closing brace of a method by tracking brace depth."""
    depth = 0
    found_opening = False

    for i in range(start_idx, len(lines)):
        for char in lines[i]:
            if char == '{':
                depth += 1
                found_opening = True
            elif char == '}':
                depth -= 1
                if found_opening and depth == 1:
                    # Back to class-level depth
                    return i
                if found_opening and depth == 0:
                    # Standalone function (not in a class)
                    return i

    return len(lines) - 1


def _extract_docblock(lines: list[str], method_line: int) -> str:
    """Extract the docblock comment above a method."""
    doc_lines = []
    i = method_line - 1
    while i >= 0 and i >= method_line - 20:
        stripped = lines[i].strip()
        if stripped.endswith('*/'):
            # Found end of docblock, collect upward
            while i >= 0:
                doc_lines.insert(0, lines[i])
                if lines[i].strip().startswith('/**') or lines[i].strip().startswith('/*'):
                    break
                i -= 1
            break
        elif stripped.startswith('//'):
            doc_lines.insert(0, lines[i])
            i -= 1
        elif stripped == '':
            i -= 1
        else:
            break

    return '\n'.join(doc_lines)


def chunk_php(file_path: str, content: str, rel_path: str) -> list[CodeChunk]:
    """Split a PHP file into chunks by method."""
    chunks = []
    lines = content.split('\n')
    module = detect_module(rel_path, content)
    seen_ids = {}  # Track duplicate method names

    # Detect class name
    class_match = PHP_CLASS_PATTERN.search(content)
    class_name = class_match.group(1) if class_match else None
    parent_class = class_match.group(2) if class_match else None

    # Determine chunk type from parent class
    is_controller = parent_class and 'controller' in parent_class.lower() if parent_class else False
    is_model = parent_class and 'model' in parent_class.lower() if parent_class else False

    # Find all methods
    methods = list(PHP_METHOD_PATTERN.finditer(content))

    if not methods:
        # No methods found â€” treat whole file as one chunk
        chunks.append(CodeChunk(
            id=f"{rel_path}::whole_file",
            chunk_type=ChunkType.WHOLE_FILE,
            text=content[:8000],
            file_path=rel_path,
            start_line=1,
            end_line=len(lines),
            module=module,
            class_name=class_name,
            tables_touched=PHP_TABLE_REF.findall(content),
            models_loaded=PHP_LOAD_MODEL.findall(content),
        ))
        return chunks

    for match in methods:
        visibility = (match.group(1) or '').strip() or 'public'
        method_name = match.group(2)

        # Find line number of this method
        method_offset = match.start()
        method_line = content[:method_offset].count('\n')

        # Find method end
        end_line = _find_method_end(lines, method_line)

        # Extract method text
        method_text = '\n'.join(lines[method_line:end_line + 1])

        # Extract docblock
        docblock = _extract_docblock(lines, method_line)
        full_text = (docblock + '\n' + method_text).strip() if docblock else method_text

        # Detect dependencies within this method
        tables = list(set(PHP_TABLE_REF.findall(method_text)))
        models = list(set(PHP_LOAD_MODEL.findall(method_text)))
        views = list(set(PHP_LOAD_VIEW.findall(method_text)))

        # Determine chunk type
        if is_controller:
            chunk_type = ChunkType.CONTROLLER_METHOD
        elif is_model:
            chunk_type = ChunkType.MODEL_METHOD
        elif 'helper' in rel_path.lower():
            chunk_type = ChunkType.HELPER_FUNCTION
        elif 'libraries' in rel_path.lower():
            chunk_type = ChunkType.LIBRARY_METHOD
        else:
            chunk_type = ChunkType.HELPER_FUNCTION

        # Build summary
        summary_parts = []
        if class_name:
            summary_parts.append(f"{class_name}::{method_name}()")
        else:
            summary_parts.append(f"{method_name}()")
        if tables:
            summary_parts.append(f"tables: {', '.join(tables[:3])}")
        if views:
            summary_parts.append(f"renders: {', '.join(views[:2])}")

        chunk_id = f"{rel_path}::{method_name}"
        if chunk_id in seen_ids:
            seen_ids[chunk_id] += 1
            chunk_id = f"{chunk_id}_{seen_ids[chunk_id]}"
        else:
            seen_ids[chunk_id] = 1

        chunks.append(CodeChunk(
            id=chunk_id,
            chunk_type=chunk_type,
            text=full_text,
            summary=' | '.join(summary_parts),
            file_path=rel_path,
            start_line=method_line + 1,
            end_line=end_line + 1,
            module=module,
            class_name=class_name,
            method_name=method_name,
            visibility=visibility,
            models_loaded=models,
            views_rendered=views,
            tables_touched=tables,
        ))

    return chunks


# â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
# JS chunker â€” split by functions and AJAX blocks
# â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

# Match: function functionName(
JS_FUNCTION_PATTERN = re.compile(
    r'^[ \t]*(?:var\s+)?(?:const\s+)?(?:let\s+)?(\w+)\s*[:=]\s*function\s*\(|'
    r'^[ \t]*function\s+(\w+)\s*\(',
    re.MULTILINE
)

# Match: $(document).on('click', 'selector', function
JS_EVENT_PATTERN = re.compile(
    r"""\$\((?:document|['"][\w#.]+['"])\)\.(?:on|click|change|submit|keyup)\(\s*['"](\w+)['"]""",
    re.IGNORECASE
)

# Match: $.ajax({ url: '...'
JS_AJAX_PATTERN = re.compile(
    r"""\$\.ajax\s*\(\s*\{[^}]*url\s*:\s*['"]([^'"]+)['"]""",
    re.IGNORECASE | re.DOTALL
)


def chunk_js(file_path: str, content: str, rel_path: str) -> list[CodeChunk]:
    """Split a JS file into chunks by function."""
    chunks = []
    lines = content.split('\n')
    module = detect_module(rel_path, content)
    seen_ids = {}

    # Find all functions
    functions = list(JS_FUNCTION_PATTERN.finditer(content))

    if not functions or len(content) < 500:
        # Small file or no functions â€” treat as whole file
        ajax_urls = JS_AJAX_PATTERN.findall(content)
        chunks.append(CodeChunk(
            id=f"{rel_path}::whole_file",
            chunk_type=ChunkType.WHOLE_FILE,
            text=content[:8000],
            file_path=rel_path,
            start_line=1,
            end_line=len(lines),
            module=module,
            ajax_endpoints=ajax_urls,
        ))
        return chunks

    for i, match in enumerate(functions):
        func_name = match.group(1) or match.group(2)
        func_offset = match.start()
        func_line = content[:func_offset].count('\n')

        # Find end: either next function or end of file
        if i + 1 < len(functions):
            next_offset = functions[i + 1].start()
            end_line = content[:next_offset].count('\n') - 1
        else:
            end_line = len(lines) - 1

        func_text = '\n'.join(lines[func_line:end_line + 1])
        ajax_urls = JS_AJAX_PATTERN.findall(func_text)

        chunk_id = f"{rel_path}::{func_name}"
        if chunk_id in seen_ids:
            seen_ids[chunk_id] += 1
            chunk_id = f"{chunk_id}_{seen_ids[chunk_id]}"
        else:
            seen_ids[chunk_id] = 1

        chunks.append(CodeChunk(
            id=chunk_id,
            chunk_type=ChunkType.JS_FUNCTION,
            text=func_text,
            summary=f"JS function {func_name}()" + (f" | ajax: {', '.join(ajax_urls[:2])}" if ajax_urls else ""),
            file_path=rel_path,
            start_line=func_line + 1,
            end_line=end_line + 1,
            module=module,
            method_name=func_name,
            ajax_endpoints=ajax_urls,
        ))

    return chunks


# â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
# Markdown chunker â€” split by headings
# â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

MD_HEADING_PATTERN = re.compile(r'^(#{1,3})\s+(.+)$', re.MULTILINE)


def chunk_markdown(file_path: str, content: str, rel_path: str) -> list[CodeChunk]:
    """Split a markdown file into chunks by heading."""
    chunks = []
    module = detect_module(rel_path, content)
    lines = content.split('\n')

    headings = list(MD_HEADING_PATTERN.finditer(content))

    if not headings or len(content) < 300:
        # Small file â€” whole file as one chunk
        is_brain = 'knowledge_brain' in rel_path.lower()
        chunks.append(CodeChunk(
            id=f"{rel_path}::whole_file",
            chunk_type=ChunkType.BRAIN_DOCUMENT if is_brain else ChunkType.DOCUMENTATION,
            text=content[:8000],
            file_path=rel_path,
            start_line=1,
            end_line=len(lines),
            module=module,
        ))
        return chunks

    for i, match in enumerate(headings):
        heading_text = match.group(2).strip()
        heading_offset = match.start()
        heading_line = content[:heading_offset].count('\n')

        # Section ends at next heading or EOF
        if i + 1 < len(headings):
            next_offset = headings[i + 1].start()
            end_line = content[:next_offset].count('\n') - 1
        else:
            end_line = len(lines) - 1

        section_text = '\n'.join(lines[heading_line:end_line + 1]).strip()

        if len(section_text) < 50:
            continue  # Skip tiny sections

        is_brain = 'knowledge_brain' in rel_path.lower()
        safe_heading = re.sub(r'[^\w\s-]', '', heading_text).strip().replace(' ', '_').lower()

        chunks.append(CodeChunk(
            id=f"{rel_path}::{safe_heading}",
            chunk_type=ChunkType.BRAIN_DOCUMENT if is_brain else ChunkType.DOCUMENTATION,
            text=section_text,
            summary=heading_text,
            file_path=rel_path,
            start_line=heading_line + 1,
            end_line=end_line + 1,
            module=module,
            tags=[heading_text.lower()],
        ))

    return chunks


# â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
# View/Config chunker â€” whole file as single chunk
# â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

def chunk_whole_file(file_path: str, content: str, rel_path: str, chunk_type: ChunkType) -> list[CodeChunk]:
    """Treat the entire file as a single chunk."""
    module = detect_module(rel_path, content)
    lines = content.split('\n')

    return [CodeChunk(
        id=f"{rel_path}::whole_file",
        chunk_type=chunk_type,
        text=content[:8000],
        file_path=rel_path,
        start_line=1,
        end_line=len(lines),
        module=module,
        tables_touched=PHP_TABLE_REF.findall(content) if chunk_type == ChunkType.VIEW_FILE else [],
    )]


# â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
# Main chunker â€” walks directory and dispatches to language-specific chunkers
# â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•

# Files/dirs to skip
SKIP_DIRS = {
    'node_modules', 'vendor', '.git', '.svn', '__pycache__',
    'cache', 'logs', 'uploads', 'temp', 'tmp',
    '.sdlc', '.agent', '.github',
}

SKIP_FILES = {
    '.gitignore', '.htaccess', 'composer.lock', 'package-lock.json',
}

# Max file size to process (500KB)
MAX_FILE_SIZE = 500_000


def ingest_directory(
    source_dir: str,
    include_docs: bool = True,
    include_brains: bool = True,
) -> Generator[CodeChunk, None, None]:
    """
    Walk a directory and yield CodeChunks for all supported files.

    Args:
        source_dir: Root directory to scan
        include_docs: Include .md documentation files
        include_brains: Include knowledge_brain/ files
    """
    source_path = Path(source_dir)

    for root, dirs, files in os.walk(source_path):
        # Prune skip directories
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS]

        rel_root = os.path.relpath(root, source_path)

        for filename in sorted(files):
            if filename in SKIP_FILES:
                continue

            file_path = os.path.join(root, filename)
            rel_path = os.path.join(rel_root, filename).replace('\\', '/')

            # Clean up leading ./
            if rel_path.startswith('./'):
                rel_path = rel_path[2:]

            # Skip too-large files
            try:
                size = os.path.getsize(file_path)
                if size > MAX_FILE_SIZE or size == 0:
                    continue
            except OSError:
                continue

            # Read file
            try:
                with open(file_path, 'r', encoding='utf-8', errors='ignore') as f:
                    content = f.read()
            except (OSError, UnicodeDecodeError):
                continue

            # Skip empty/tiny files
            if len(content.strip()) < 20:
                continue

            # Dispatch by extension
            ext = Path(filename).suffix.lower()

            if ext == '.php':
                yield from chunk_php(file_path, content, rel_path)

            elif ext == '.js':
                yield from chunk_js(file_path, content, rel_path)

            elif ext == '.md' and (include_docs or (include_brains and 'knowledge_brain' in rel_path)):
                yield from chunk_markdown(file_path, content, rel_path)

            elif ext in ('.css', '.scss'):
                yield from chunk_whole_file(file_path, content, rel_path, ChunkType.CSS_SECTION)

            elif ext == '.sql':
                yield from chunk_whole_file(file_path, content, rel_path, ChunkType.SQL_MIGRATION)

            elif ext in ('.json', '.ini', '.env') and 'config' in rel_path.lower():
                yield from chunk_whole_file(file_path, content, rel_path, ChunkType.CONFIG_FILE)

