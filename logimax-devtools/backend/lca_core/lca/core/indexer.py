"""Core indexing orchestrator."""

import time
from pathlib import Path
from datetime import datetime

from lca.models import ModuleIndex, FunctionDef, FunctionCall, Level2Deps
from lca.models import FormulaExpr, Accumulator, ConditionalBranch, MagicNumber
from lca.parsers import JavaScriptParser, PHPParser, SQLViewParser, BaseParser
from lca.analysis.code_extractor import CodeExtractor
from lca.storage.cache import FileHashCache

# Try to import PythonParser if available
try:
    from lca.parsers.python import PythonParser
    PYTHON_PARSER_AVAILABLE = True
except ImportError:
    PYTHON_PARSER_AVAILABLE = False


class Indexer:
    """Main indexing orchestrator that combines parsers and builds indexes."""
    
    def __init__(self, deep_analyze: bool = True):
        self.parsers: list[BaseParser] = [
            JavaScriptParser(),
            PHPParser(),
            SQLViewParser(),
        ]
        # Add Python parser if tree-sitter-python is installed
        if PYTHON_PARSER_AVAILABLE:
            try:
                self.parsers.append(PythonParser())
            except RuntimeError:
                pass  # tree-sitter-python not installed
        
        # Deep analysis engine for formula/accumulator/conditional extraction
        self._deep_analyze = deep_analyze
        self._code_extractor = CodeExtractor() if deep_analyze else None

    
    def index_directory(
        self,
        path: Path,
        module_name: str,
        recursive: bool = True,
        cache_dir: Path | None = None,
        force_full: bool = False,
    ) -> ModuleIndex:
        """
        Index all supported files in a directory.
        
        Args:
            path: Directory path to index
            module_name: Name for this module index
            recursive: Whether to search subdirectories
            cache_dir: Directory for hash cache file (enables incremental indexing)
            force_full: If True, ignore cache and re-index everything
            
        Returns:
            ModuleIndex containing all discovered functions and calls
        """
        t_start = time.time()
        all_functions: list[FunctionDef] = []
        all_calls: list[FunctionCall] = []
        
        # Collect all files
        if recursive:
            # Manual traversal to filter dirs
            files = []
            for root, dirs, filenames in path.walk(): # Using walk for better control
                # Filter directories
                dirs[:] = [d for d in dirs if d not in {'.git', 'node_modules', 'vendor', '__pycache__', 'logs', 'cache', 'libraries', 'third_party'}]
                
                for name in filenames:
                    files.append(root / name)
        else:
            files = list(path.glob("*"))
        
        # ── Incremental indexing with file-hash cache ──────────────────
        cache: FileHashCache | None = None
        files_to_parse = files
        skipped_count = 0
        
        if cache_dir:
            cache_path = cache_dir / f".lca_cache_{module_name}.json"
            cache = FileHashCache(cache_path)
            
            if not force_full:
                # Use cache to skip unchanged files
                changed, unchanged = cache.get_changed_files(files)
                files_to_parse = changed
                skipped_count = len(unchanged)
                
                # Reload cached results for unchanged files
                for uf in unchanged:
                    cached_data = cache.get_cached_results(uf)
                    if cached_data:
                        funcs_dicts, calls_dicts = cached_data
                        for fd in funcs_dicts:
                            all_functions.append(FunctionDef(**fd))
                        for cd in calls_dicts:
                            # Map 'type' back to 'call_type' if present
                            if "type" in cd and "call_type" not in cd:
                                cd["call_type"] = cd.pop("type")
                            all_calls.append(FunctionCall(**cd))
            else:
                # Full re-index: clear old cache, parse everything
                cache.clear()
        
        # Parse each file with appropriate parser
        parsed_count = 0
        error_count = 0
        for file_path in files_to_parse:
            if not file_path.is_file():
                continue
            
            for parser in self.parsers:
                if parser.can_parse(file_path):
                    try:
                        functions, calls = parser.parse_file(file_path)
                        all_functions.extend(functions)
                        all_calls.extend(calls)
                        parsed_count += 1
                        # Store parsed results in cache
                        if cache:
                            cache.update(
                                file_path,
                                functions=[f.model_dump() for f in functions],
                                calls=[c.model_dump() for c in calls],
                            )
                    except Exception as e:
                        error_count += 1
                        print(f"Error parsing {file_path}: {e}")
                    break
        
        # Save cache to disk
        if cache:
            cache.save()
        
        # Resolve cross-file calls (Linker Pass)
        self._resolve_cross_file_calls(all_functions, all_calls)

        # ── Deep Analysis Pass ─────────────────────────────────────────
        all_formulas: list[FormulaExpr] = []
        all_accumulators: list[Accumulator] = []
        all_conditionals: list[ConditionalBranch] = []
        all_magic_numbers: list[MagicNumber] = []

        if self._code_extractor:
            deep_count = 0
            for file_path in files:
                if not file_path.is_file():
                    continue
                suffix = file_path.suffix.lower()
                if suffix in ('.js', '.jsx', '.ts', '.tsx', '.mjs', '.php', '.phtml', '.inc'):
                    try:
                        formulas, accumulators, conditionals, magic_numbers = (
                            self._code_extractor.analyze_file(file_path)
                        )
                        all_formulas.extend(formulas)
                        all_accumulators.extend(accumulators)
                        all_conditionals.extend(conditionals)
                        all_magic_numbers.extend(magic_numbers)
                        deep_count += 1
                    except Exception as e:
                        print(f'[CodeExtractor] Error on {file_path.name}: {e}')

        t_elapsed = time.time() - t_start

        # Build the index
        index = ModuleIndex(
            module=module_name,
            version="3.1.0",
            indexed_at=datetime.now().isoformat(),
            functions=all_functions,
            calls=all_calls,
            files=[{"path": str(p)} for p in files],
            formulas=all_formulas,
            accumulators=all_accumulators,
            conditionals=all_conditionals,
            magic_numbers=all_magic_numbers,
        )
        
        # Compute level2 dependencies
        index.level2 = self._compute_level2(all_calls)
        
        # Log indexing stats
        deep_stats = ''
        if self._code_extractor:
            deep_stats = (f' | Deep: {len(all_formulas)} formulas, '
                          f'{len(all_accumulators)} accumulators, '
                          f'{len(all_conditionals)} conditionals, '
                          f'{len(all_magic_numbers)} magic numbers')
        print(f'[LCA] Indexed {module_name}: '
              f'{parsed_count} parsed, {skipped_count} cached, {error_count} errors '
              f'→ {len(all_functions)} functions, {len(all_calls)} calls'
              f'{deep_stats} ({t_elapsed:.1f}s)')
        
        return index

    def _resolve_cross_file_calls(self, functions: list[FunctionDef], calls: list[FunctionCall]):
        """
        Link Phase: Resolve target_file for each call by looking up the global symbol table.
        
        Six resolution passes (each pass only touches unresolved calls):
        1. Exact symbol table: function name → file
        2. Model method resolution: model_name::method → model file
        3. CI3 loader path resolution: LOAD:model:name → model file path
        4. Case-insensitive fallback: try lowercased name matching
        5. Self-method resolution: $this->method() → CurrentClass::method
        6. Fuzzy method name matching: unqualified 'method' → find *::method
        """
        # ── 1. Build Global Symbol Table ────────────────────────────────
        symbol_table: dict[str, str] = {}           # exact name → file
        symbol_table_lower: dict[str, str] = {}     # lowered name → file
        method_index: dict[str, list[str]] = {}     # method → [Class::method, ...]
        file_to_class: dict[str, str] = {}          # file path → class name

        for func in functions:
            symbol_table[func.name] = func.file
            symbol_table_lower[func.name.lower()] = func.file

            if "::" in func.name:
                class_name, method = func.name.split("::", 1)
                method_lower = method.lower()
                if method_lower not in method_index:
                    method_index[method_lower] = []
                method_index[method_lower].append(func.name)
                file_to_class[func.file] = class_name

        # ── 2. Build model name → file mapping ─────────────────────────
        model_file_map: dict[str, str] = {}     # model_name_lower → file path
        model_class_map: dict[str, str] = {}    # model_name_lower → actual class name

        for func in functions:
            if "::" in func.name:
                class_name = func.name.split("::")[0]
                lower = class_name.lower()
                model_file_map[lower] = func.file
                model_class_map[lower] = class_name

        # ── Pass 1: Exact match ─────────────────────────────────────────
        for call in calls:
            if call.target_file:
                continue  # Already resolved
            if call.target in symbol_table:
                call.target_file = symbol_table[call.target]

        # ── Pass 2: Model method resolution ─────────────────────────────
        for call in calls:
            if call.target_file:
                continue
            if call.call_type == "model_call" and "::" in call.target:
                model_name, method = call.target.split("::", 1)
                model_lower = model_name.lower()

                if model_lower in model_file_map:
                    call.target_file = model_file_map[model_lower]
                    # Canonicalize the target name to match the definition
                    actual_class = model_class_map.get(model_lower, model_name)
                    canonical = f"{actual_class}::{method}"
                    if canonical in symbol_table:
                        call.target = canonical
                    else:
                        # Try case-insensitive method match
                        canonical_lower = canonical.lower()
                        if canonical_lower in symbol_table_lower:
                            # Find the actual-case name
                            for fn in functions:
                                if fn.name.lower() == canonical_lower:
                                    call.target = fn.name
                                    break

        # ── Pass 3: CI3 loader targets → file paths ─────────────────────
        for call in calls:
            if call.target_file:
                continue
            if call.call_type in ("load_model", "load_view", "load_helper", "load_library"):
                parts = call.target.split(":", 2)
                if len(parts) == 3:
                    _, loader_type, name = parts
                    if loader_type == "model":
                        name_lower = name.split("/")[-1].lower()
                        if name_lower in model_file_map:
                            call.target_file = model_file_map[name_lower]

        # ── Pass 4: Case-insensitive fallback ────────────────────────────
        for call in calls:
            if call.target_file:
                continue
            target_lower = call.target.lower()
            if target_lower in symbol_table_lower:
                call.target_file = symbol_table_lower[target_lower]
                # Also canonicalize the target name
                for fn in functions:
                    if fn.name.lower() == target_lower:
                        call.target = fn.name
                        break

        # ── Pass 5: Self-method resolution ───────────────────────────────
        # $this->method() in a file where the class is known → Class::method
        for call in calls:
            if call.target_file:
                continue
            if call.call_type == "calls" and "::" not in call.target:
                # Check if the call's file defines a class
                caller_class = file_to_class.get(call.file)
                if caller_class:
                    qualified = f"{caller_class}::{call.target}"
                    if qualified in symbol_table:
                        call.target = qualified
                        call.target_file = symbol_table[qualified]

        # ── Pass 6: Fuzzy method name matching ───────────────────────────
        # Unqualified 'method_name' → find any *::method_name
        for call in calls:
            if call.target_file:
                continue
            if "::" not in call.target and call.call_type == "calls":
                method_lower = call.target.lower()
                candidates = method_index.get(method_lower, [])
                if len(candidates) == 1:
                    # Unambiguous match — resolve it
                    call.target = candidates[0]
                    call.target_file = symbol_table.get(candidates[0])
                elif len(candidates) > 1:
                    # Multiple matches — try to find one in the same file or class
                    same_file = [c for c in candidates if symbol_table.get(c) == call.file]
                    if len(same_file) == 1:
                        call.target = same_file[0]
                        call.target_file = symbol_table[same_file[0]]


    
    def index_file(self, path: Path, module_name: str) -> ModuleIndex:
        """Index a single file."""
        functions: list[FunctionDef] = []
        calls: list[FunctionCall] = []
        
        for parser in self.parsers:
            if parser.can_parse(path):
                functions, calls = parser.parse_file(path)
                break
        
        # Deep analysis
        formulas, accumulators, conditionals, magic_numbers = [], [], [], []
        if self._code_extractor:
            try:
                formulas, accumulators, conditionals, magic_numbers = (
                    self._code_extractor.analyze_file(path)
                )
            except Exception as e:
                print(f'[CodeExtractor] Error on {path.name}: {e}')
        
        index = ModuleIndex(
            module=module_name,
            version="3.1.0",
            indexed_at=datetime.now().isoformat(),
            functions=functions,
            calls=calls,
            formulas=formulas,
            accumulators=accumulators,
            conditionals=conditionals,
            magic_numbers=magic_numbers,
        )
        
        index.level2 = self._compute_level2(calls)
        
        return index
    
    def _compute_level2(self, calls: list[FunctionCall]) -> dict[str, Level2Deps]:
        """Compute level 2 dependencies for all functions."""
        # Build adjacency lists
        calls_map: dict[str, set[str]] = {}  # func -> set of functions it calls
        called_by_map: dict[str, set[str]] = {}  # func -> set of functions that call it
        
        for call in calls:
            if call.source not in calls_map:
                calls_map[call.source] = set()
            calls_map[call.source].add(call.target)
            
            if call.target not in called_by_map:
                called_by_map[call.target] = set()
            called_by_map[call.target].add(call.source)
        
        # Build level2 for each function that appears in calls
        level2: dict[str, Level2Deps] = {}
        all_funcs = set(calls_map.keys()) | set(called_by_map.keys())
        
        for func in all_funcs:
            l2_calls = list(calls_map.get(func, set()))
            l2_called_by = list(called_by_map.get(func, set()))
            
            if l2_calls or l2_called_by:
                level2[func] = Level2Deps(
                    calls=l2_calls,
                    called_by=l2_called_by
                )
        
        return level2
                