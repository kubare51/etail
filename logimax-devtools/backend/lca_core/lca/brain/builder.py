"""Brain Builder v2.2 — Main Orchestrator.

Coordinates code parsing, DB integration, AJAX route resolution, and document generation.

Usage:
  1. lca brain-build <module> --draft   → Auto-generate brain docs + REVIEW_TRACKER.md
  2. Human reviews REVIEW_TRACKER.md    → Confirms/corrects business rules
  3. lca brain-build <module> --finalize → Incorporate human answers into final brain

Components:
  - Parser: CodeParser (parser.py) — regex-based extraction from PHP/JS/views
  - Generator: BrainDocGenerator (generator.py) — markdown document generation
  - Models: data classes (models.py) — shared types
  - Tracker: generates REVIEW_TRACKER.md with Q&A items

Iteration Log:
  v2.2: Feature #15 — DB schema integration: row counts in WIRING.md, DB value discovery in STATUS_CODES
        Feature #16 — PHP if/elseif chain extraction: 3 patterns (direct, ternary, loose nested)
        Feature #17 — DB discoveries section: values in DB but not code, values in code but not DB
        Feature #18 — AJAX route resolution: JS URL → PHP method mapping
        Feature #19 — File split: builder.py → models.py + parser.py + generator.py + builder.py
  v2.0: Feature #12 — View parser: form fields, hidden settings, radio/select value→label maps, tabs
        Feature #13 — View value maps merged into STATUS_CODES auto-fill
        Feature #14 — Enhanced WIRING.md with form field inventory per view
  v1.3: Feature #8 — Auto-extract value→meaning maps from SQL IF() chains
        Feature #9 — Populate STATUS_CODES.md with auto-discovered values
  v1.2: Feature #6 — Controller→Model call chain for indirect table writes
        Feature #7 — Raw SQL (db->query) parsing for table references
  v1.1: Fix #1 — Detect duplicate methods across same-file blocks
        Fix #2 — Parse all JS variants (arrow, assigned, prototype)
        Fix #3 — Handle PHP alternative syntax (if/endif blocks)
        Fix #4 — Smarter context-aware review questions
        Fix #5 — JS AJAX detection improvements
        Fix #6 — Controller→Model call chain for indirect table writes
"""

import re
import json
from pathlib import Path
from typing import Dict, List, Optional, Set, Tuple

from lca.brain.models import (
    ExtractedMethod, StatusCodeValue, ReviewItem, BrainBuildResult
)
from lca.brain.parser import CodeParser
from lca.brain.generator import DBAnalyzer, BrainDocGenerator
from lca.brain.bi_analyzer import BusinessIntelligenceAnalyzer


# ─────────────────────────────────────────────
# Brain Builder — Main orchestrator
# ─────────────────────────────────────────────

class BrainBuilder:
    """Main brain builder — orchestrates code parsing, DB analysis, and doc generation."""
    
    def __init__(self, project_root: str, module: str):
        self.project_root = Path(project_root)
        self.module = module
        self.parser = CodeParser(project_root)
        self.db_analyzer = DBAnalyzer()
        self.result = BrainBuildResult(module=module)
        self.brain_dir = self.project_root / "knowledge_brain" / f"{module}_v2"
    
    def build_draft(self, db_callback=None, progress_callback=None) -> BrainBuildResult:
        """Build draft brain with review tracker."""
        def progress(step, detail=""):
            if progress_callback:
                progress_callback(step, detail)
        
        # Step 1: Find module files
        progress("FILES", "Scanning for module files...")
        files = self.parser.find_module_files(self.module)
        self.result.controller_files = [str(f) for f in files['controllers']]
        self.result.model_files = [str(f) for f in files['models']]
        self.result.js_files = [str(f) for f in files['js']]
        self.result.view_files = [str(f) for f in files['views']]
        
        progress("FILES", 
                 f"Found: {len(files['controllers'])} controllers, "
                 f"{len(files['models'])} models, "
                 f"{len(files['js'])} JS, "
                 f"{len(files['views'])} views")
        
        # Step 2: Parse PHP files
        progress("PARSE", "Extracting methods from PHP files...")
        for php_file in files['controllers'] + files['models']:
            methods = self.parser.parse_php_file(php_file)
            self.result.methods.extend(methods)
            progress("PARSE", f"  {php_file.name}: {len(methods)} methods")
        
        # Step 3: Extract table ownership
        progress("TABLES", "Analyzing table ownership...")
        owned, referenced = self.parser.extract_all_tables(self.result.methods)
        self.result.tables_owned = sorted(owned)
        self.result.tables_referenced = sorted(referenced)
        progress("TABLES", f"  Owned: {len(owned)}, Referenced: {len(referenced)}")
        
        # v1.1: Detect duplicate methods
        self.result.duplicate_methods = self.parser.detect_duplicates(self.result.methods)
        if self.result.duplicate_methods:
            progress("DUPLICATES", f"  ⚠️ Found {len(self.result.duplicate_methods)} duplicate method(s)")
        
        # Step 4: Parse JS files
        progress("JS", "Parsing JavaScript files...")
        for js_file in files['js']:
            js_info = self.parser.parse_js_file(js_file)
            self.result.js_data[str(js_file)] = js_info
            progress("JS", f"  {js_file.name}: {len(js_info['functions'])} functions, "
                          f"{len(js_info['ajax_urls'])} AJAX URLs")
        
        # Step 5: v2.0 — Parse view files
        progress("VIEWS", "Parsing view files...")
        total_fields = 0
        total_hidden = 0
        total_value_maps = 0
        for view_file in files['views']:
            view_info = self.parser.parse_view_file(view_file)
            self.result.view_data[str(view_file)] = view_info
            total_fields += len(view_info['form_fields'])
            total_hidden += len(view_info['hidden_settings'])
            total_value_maps += len(view_info['value_label_maps'])
        progress("VIEWS", f"  Parsed {len(files['views'])} views: "
                          f"{total_fields} form fields, {total_hidden} hidden settings, "
                          f"{total_value_maps} value→label maps")
        
        # Step 6: v1.3 — Extract value maps from SQL IF chains
        progress("VALUEMAPS", "Extracting value→meaning maps from SQL...")
        all_code = ''
        for php_file in files['controllers'] + files['models']:
            try:
                all_code += php_file.read_text(encoding='utf-8', errors='ignore')
            except:
                pass
        self.result.value_maps = self.parser.extract_sql_value_maps(all_code)
        if self.result.value_maps:
            progress("VALUEMAPS", f"  Found maps for: {', '.join(self.result.value_maps.keys())}")
        
        # v2.0: Merge view-extracted value→label maps into main value_maps
        # View maps take priority for values not already in SQL maps
        for view_path, view_info in self.result.view_data.items():
            for field, val_map in view_info['value_label_maps'].items():
                if field not in self.result.value_maps:
                    self.result.value_maps[field] = {}
                for val, label in val_map.items():
                    if val not in self.result.value_maps[field]:
                        self.result.value_maps[field][val] = label
        if total_value_maps > 0:
            progress("VALUEMAPS", f"  After view merge: {len(self.result.value_maps)} columns mapped")
        
        # Step 7: v2.2 — Integrate DB schema data if db_schema.json exists
        db_json_path = self.brain_dir / 'db_schema.json'
        if db_json_path.exists():
            progress("DB", "Loading DB schema data...")
            try:
                import json as _json
                with open(db_json_path, 'r', encoding='utf-8') as f:
                    db_data = _json.load(f)
                
                # Store row counts
                self.result.db_row_counts = db_data.get('row_counts', {})
                self.result.db_status_values = db_data.get('status_values', {})
                self.result.db_discoveries = db_data.get('db_discoveries', {})
                
                # Merge DB status values into value_maps
                # DB values DON'T override code values — they add [DB:count] suffix
                db_new_values = 0
                for table, columns in self.result.db_status_values.items():
                    for col, values in columns.items():
                        col_lower = col.lower()
                        for v in values:
                            val = str(v.get('value', ''))
                            cnt = v.get('count', 0)
                            if val and val != 'NULL':
                                if col_lower not in self.result.value_maps:
                                    self.result.value_maps[col_lower] = {}
                                if val not in self.result.value_maps[col_lower]:
                                    # New value from DB only — mark for review
                                    self.result.value_maps[col_lower][val] = f'[DB:{cnt} rows - NEEDS LABEL]'
                                    db_new_values += 1
                
                tables_with_data = sum(1 for c in self.result.db_row_counts.values() if c > 0)
                progress("DB", f"  Loaded: {len(self.result.db_row_counts)} tables, "
                               f"{tables_with_data} with data, "
                               f"{db_new_values} new values from DB")
                
                # v2.2: Dynamically compute discoveries by comparing DB values vs value_maps
                dynamic_discoveries = {'new_values_not_in_code': [], 'code_values_not_in_db': []}
                
                # Find DB values with no label in value_maps
                for table, columns in self.result.db_status_values.items():
                    for col, values in columns.items():
                        col_lower = col.lower()
                        for v in values:
                            val = str(v.get('value', ''))
                            cnt = v.get('count', 0)
                            if val and val != 'NULL':
                                meaning = self.result.value_maps.get(col_lower, {}).get(val, '')
                                if not meaning or '[DB:' in meaning:
                                    dynamic_discoveries['new_values_not_in_code'].append({
                                        'table': table, 'column': col, 'value': val,
                                        'count': cnt, 'note': 'Not labeled in code or views'
                                    })
                
                # Preserve code-only values from static JSON if present
                static_code_only = db_data.get('db_discoveries', {}).get('code_values_not_in_db', [])
                dynamic_discoveries['code_values_not_in_db'] = static_code_only
                
                self.result.db_discoveries = dynamic_discoveries
                
                if dynamic_discoveries['new_values_not_in_code']:
                    progress("DB", f"  ⚠️ {len(dynamic_discoveries['new_values_not_in_code'])} DB values NOT in code/views")
                if dynamic_discoveries['code_values_not_in_db']:
                    progress("DB", f"  📋 {len(dynamic_discoveries['code_values_not_in_db'])} code values NOT in DB")
                    
            except Exception as e:
                progress("DB", f"  ⚠️ Failed to load db_schema.json: {e}")
        
        # Step 7b: v2.2 — AJAX route resolution (JS URL → PHP method mapping)
        all_ajax_urls = set()
        for js_path, js_info in self.result.js_data.items():
            all_ajax_urls.update(js_info.get('ajax_urls', []))
        
        if all_ajax_urls:
            # Parse URLs into controller/method pairs
            route_map = {}  # controller -> {method: 'matched'|'unmatched'|'cross-module'}
            ctrl_methods = {}  # Build index of PHP methods by controller name
            
            for m in self.result.methods:
                ctrl_name = Path(m.file_path).stem
                if ctrl_name not in ctrl_methods:
                    ctrl_methods[ctrl_name] = set()
                ctrl_methods[ctrl_name].add(m.name)
            
            # Also check for controllers outside the module
            ctrl_dir = self.parser.admin_root / "application" / "controllers"
            
            for url in sorted(all_ajax_urls):
                clean = url.replace('index.php/', '')
                parts = clean.split('/')
                ctrl = parts[0] if len(parts) >= 1 else ''
                method = parts[1].split('?')[0] if len(parts) >= 2 else ''
                if not ctrl or not method:
                    continue
                
                if ctrl not in route_map:
                    route_map[ctrl] = {}
                
                if ctrl in ctrl_methods:
                    # Controller is in this module's files
                    if method in ctrl_methods[ctrl]:
                        route_map[ctrl][method] = 'matched'
                    else:
                        route_map[ctrl][method] = 'unmatched'
                else:
                    # Check if controller exists elsewhere in the project
                    ctrl_file = ctrl_dir / f'{ctrl}.php'
                    if ctrl_file.exists():
                        route_map[ctrl][method] = 'cross-module'
                    else:
                        route_map[ctrl][method] = 'missing-controller'
            
            self.result.ajax_routes = route_map
            
            total = sum(len(methods) for methods in route_map.values())
            matched = sum(1 for c in route_map.values() for s in c.values() if s == 'matched')
            cross = sum(1 for c in route_map.values() for s in c.values() if s == 'cross-module')
            unmatched = total - matched - cross
            progress("AJAX", f"  Routes: {total} endpoints, "
                             f"{matched} matched, {cross} cross-module, {unmatched} unmatched")
        
        # Step 8: Generate review items from extracted data
        progress("REVIEW", "Generating review questions...")
        self._generate_review_items()
        progress("REVIEW", f"  Generated {len(self.result.review_items)} review items")
        
        # Step 9: Business Intelligence inference
        progress("BI", "Inferring business rules, flows, risks...")
        bi = BusinessIntelligenceAnalyzer(self.result)
        bi.analyze()
        val_inv = self.result.validation_inventory
        xmod = self.result.cross_module_map
        val_methods = len(val_inv.get('validated_methods', [])) + len(val_inv.get('unvalidated_methods', []))
        xmod_tables = len(xmod.get('shared_tables', {})) if xmod else 0
        progress("BI", f"  Rules: {len(self.result.business_rules)}, "
                       f"Flows: {len(self.result.business_flows)}, "
                       f"Risks: {len(self.result.business_risks)}, "
                       f"Validation: {val_methods} methods, "
                       f"Cross-module: {xmod_tables} shared tables")
        
        return self.result
    
    def _generate_review_items(self):
        """v1.1: Generate context-aware review questions from extracted code metadata."""
        item_id = 0
        
        # STATUS questions: from code-level status checks
        status_values_seen = {}
        for m in self.result.methods:
            for check in m.status_checks:
                if '=' not in check or check.startswith('$type='):
                    continue
                col, val = check.split('=', 1)
                key = f"{col}={val}"
                if key not in status_values_seen:
                    status_values_seen[key] = m.name
                    
                    # v1.4: Skip if meaning is already known from SQL IF chain
                    if col in self.result.value_maps and val in self.result.value_maps[col]:
                        # Auto-resolved — don't waste human's time asking
                        continue
                    
                    item_id += 1
                    self.result.review_items.append(ReviewItem(
                        id=f"Q-{item_id:03d}",
                        category="STATUS",
                        code_ref=f"{Path(m.file_path).name}:{m.start_line} ({m.name})",
                        ai_observation=f"Code checks `{col} = {val}` in method `{m.name}()`",
                        question=f"What does `{col} = {val}` mean in business terms?",
                        priority="HIGH" if 'status' in col.lower() or 'type' in col.lower() else "MEDIUM"
                    ))
        
        # v1.1: CONTEXT-AWARE RULE questions
        for m in self.result.methods:
            if m.line_count <= 50:
                continue
            
            item_id += 1
            # Build specific observation based on method traits
            observations = []
            if m.line_count > 500:
                observations.append(f"⚠️ MEGA method ({m.line_count} lines)")
            elif m.line_count > 200:
                observations.append(f"Large method ({m.line_count} lines)")
            else:
                observations.append(f"Method ({m.line_count} lines)")
            
            all_writes = m.tables_insert + m.tables_update
            if all_writes:
                observations.append(f"Writes: {', '.join(all_writes)}")
            if m.tables_delete:
                observations.append(f"⚠️ DELETES from: {', '.join(m.tables_delete)}")
            if m.has_transaction:
                observations.append("Uses DB transaction")
            if m.has_otp:
                observations.append("Involves OTP/approval")
            if m.has_file_upload:
                observations.append("Handles file upload")
            if m.models_called:
                observations.append(f"Calls: {', '.join(m.models_called[:3])}")
            if m.post_fields:
                observations.append(f"POST fields: {', '.join(m.post_fields[:5])}")
            
            # v1.1: Generate SPECIFIC questions based on traits
            questions = []
            if m.has_otp:
                questions.append("What requires OTP approval and who approves?")
            if m.tables_delete:
                questions.append("Why is data deleted? Is soft-delete an option?")
            if m.has_transaction:
                questions.append("What happens if the transaction fails mid-way?")
            if len(all_writes) >= 2:
                questions.append("What is the correct order of operations?")
            if m.line_count > 500:
                questions.append("Can this method be broken into smaller pieces?")
            if not questions:
                questions.append("What business rules does this method enforce?")
            
            self.result.review_items.append(ReviewItem(
                id=f"Q-{item_id:03d}",
                category="RULE",
                code_ref=f"{Path(m.file_path).name}:{m.start_line}-{m.end_line} ({m.name})",
                ai_observation=' | '.join(observations),
                question=' '.join(questions),
                priority="HIGH" if m.line_count > 500 or m.tables_delete else "MEDIUM"
            ))
        
        # FLOW questions: for methods that write to multiple tables
        for m in self.result.methods:
            all_writes = set(m.tables_insert + m.tables_update + m.tables_delete)
            if len(all_writes) >= 3:
                item_id += 1
                self.result.review_items.append(ReviewItem(
                    id=f"Q-{item_id:03d}",
                    category="FLOW",
                    code_ref=f"{Path(m.file_path).name}:{m.start_line} ({m.name})",
                    ai_observation=f"Method `{m.name}()` writes to {len(all_writes)} tables: "
                                   f"{', '.join(sorted(all_writes))}. "
                                   f"{'Uses transaction.' if m.has_transaction else '⚠️ NO transaction!'}",
                    question=f"What is the complete business flow? What is the correct table write order? "
                             f"What should be rolled back if something fails?",
                    priority="HIGH"
                ))
        
        # RISK questions: for methods using DELETE
        for m in self.result.methods:
            if m.tables_delete:
                item_id += 1
                self.result.review_items.append(ReviewItem(
                    id=f"Q-{item_id:03d}",
                    category="RISK",
                    code_ref=f"{Path(m.file_path).name}:{m.start_line} ({m.name})",
                    ai_observation=f"Method `{m.name}()` DELETES from: {', '.join(m.tables_delete)}. "
                                   f"{'Inside transaction.' if m.has_transaction else '⚠️ NOT in transaction!'}",
                    question=f"Is the DELETE in `{m.name}()` safe? Should it use soft-delete instead? "
                             f"What data is lost if this fails?",
                    priority="HIGH"
                ))
        
        # v1.1: DUPLICATE questions
        for first, dup in self.result.duplicate_methods:
            item_id += 1
            self.result.review_items.append(ReviewItem(
                id=f"Q-{item_id:03d}",
                category="DUPLICATE",
                code_ref=f"{first} vs {dup}",
                ai_observation=f"Two methods with the same name exist. PHP uses the LAST definition. "
                               f"First at `{first}`, duplicate at `{dup}`.",
                question=f"Is this intentional? Which one should be kept? Is the first one dead code?",
                priority="HIGH"
            ))
    
    def write_draft(self) -> Dict[str, str]:
        """Write all brain draft documents to disk."""
        self.brain_dir.mkdir(parents=True, exist_ok=True)
        
        generator = BrainDocGenerator(self.module, self.result)
        
        docs = {}
        
        # WIRING.md — fully automated
        wiring = generator.generate_wiring()
        (self.brain_dir / "WIRING.md").write_text(wiring, encoding='utf-8')
        docs['WIRING.md'] = wiring
        
        # STATUS_CODES.md — auto with review markers
        status = generator.generate_status_codes()
        (self.brain_dir / "STATUS_CODES.md").write_text(status, encoding='utf-8')
        docs['STATUS_CODES.md'] = status
        
        # REVIEW_TRACKER.md — the Q&A document
        tracker = generator.generate_review_tracker()
        (self.brain_dir / "REVIEW_TRACKER.md").write_text(tracker, encoding='utf-8')
        docs['REVIEW_TRACKER.md'] = tracker
        
        # v2.2: AJAX_ROUTES.md — separate route map
        ajax_routes = generator.generate_ajax_routes()
        if ajax_routes:
            (self.brain_dir / "AJAX_ROUTES.md").write_text(ajax_routes, encoding='utf-8')
            docs['AJAX_ROUTES.md'] = ajax_routes
        
        # v2.3: BI-generated docs (RULES, FLOWS, RISKS)
        rules_md = generator.generate_rules()
        if rules_md:
            (self.brain_dir / "RULES.md").write_text(rules_md, encoding='utf-8')
            docs['RULES.md'] = rules_md
        
        flows_md = generator.generate_flows()
        if flows_md:
            (self.brain_dir / "FLOWS.md").write_text(flows_md, encoding='utf-8')
            docs['FLOWS.md'] = flows_md
        
        risks_md = generator.generate_risks()
        if risks_md:
            (self.brain_dir / "RISKS.md").write_text(risks_md, encoding='utf-8')
            docs['RISKS.md'] = risks_md
        
        # v2.3: VALIDATIONS.md — input validation coverage
        validations_md = generator.generate_validations()
        if validations_md:
            (self.brain_dir / "VALIDATIONS.md").write_text(validations_md, encoding='utf-8')
            docs['VALIDATIONS.md'] = validations_md
        
        # v2.3: CROSS_MODULE.md — cross-module dependency map
        cross_mod_md = generator.generate_cross_module()
        if cross_mod_md:
            (self.brain_dir / "CROSS_MODULE.md").write_text(cross_mod_md, encoding='utf-8')
            docs['CROSS_MODULE.md'] = cross_mod_md
        
        # Placeholder for remaining human-only docs
        for doc_name in ['PATTERNS.md']:
            placeholder = (
                f"# {self.module.title()} Module — {doc_name.replace('.md', '')} (Brain v2.3)\n\n"
                f"> ⚠️ This document requires human input.\n"
                f"> Review RULES.md and FLOWS.md first, then document recurring patterns here.\n\n"
                f"---\n\n"
                f"[Pending manual documentation]\n"
            )
            path = self.brain_dir / doc_name
            if not path.exists():
                path.write_text(placeholder, encoding='utf-8')
                docs[doc_name] = placeholder
        
        return docs
    
    def get_db_queries(self) -> List[Dict]:
        """Get all DB queries needed for brain building."""
        queries = []
        for table in self.result.tables_owned:
            queries.append({
                'purpose': f'schema_{table}',
                'sql': self.db_analyzer.get_table_schema(table)['sql']
            })
        return queries
    
    def add_status_codes_from_db(self, table: str, column: str, 
                                  rows: List[Tuple[str, int]]):
        """Add status code values from DB query results."""
        for value, count in rows:
            self.result.status_codes.append(StatusCodeValue(
                table=table,
                column=column,
                value=str(value) if value is not None else 'NULL',
                count=count,
                meaning="",
                confirmed=False
            ))
            
            self.result.review_items.append(ReviewItem(
                id=f"Q-SC-{table}-{column}-{value}",
                category="STATUS",
                code_ref=f"DB: {table}.{column}",
                ai_observation=f"Column `{table}.{column}` has value `{value}` ({count} rows)",
                question=f"What does `{column} = {value}` mean in `{table}`?",
                priority="HIGH" if count > 10 else "LOW"
            ))
