"""
Brain Builder — DB Integration Module v2.2

Integrates DB schema data into the brain builder.
DB data is provided as a JSON file (db_schema.json) populated via MCP queries.

Key features:
1. Validates code-inferred status values against actual DB data
2. Discovers new status values not found in code
3. Adds column type information to WIRING.md
4. Cross-references code table usage with actual DB structure
"""

import json
from pathlib import Path
from typing import Dict, List, Optional, Tuple


class DBIntegrator:
    """Integrates DB schema data into brain build results."""
    
    def __init__(self, db_data: Dict):
        self.db_data = db_data
        self.tables = db_data.get('tables', {})
        self.status_values = db_data.get('status_values', {})
        self.foreign_keys = db_data.get('foreign_keys', [])
        self.row_counts = db_data.get('row_counts', {})
    
    def get_db_value_maps(self) -> Dict[str, Dict[str, str]]:
        """Extract value maps from DB status distributions.
        Returns: {column: {value: '[DB:count]'}} for values found in DB."""
        maps = {}
        for table, columns in self.status_values.items():
            for col, values in columns.items():
                col_lower = col.lower()
                if col_lower not in maps:
                    maps[col_lower] = {}
                for v in values:
                    val = str(v.get('value', ''))
                    cnt = v.get('count', 0)
                    if val and val != 'NULL':
                        maps[col_lower][val] = f'[DB:{cnt} rows]'
        return maps
    
    def validate_code_values(self, code_value_maps: Dict) -> Dict:
        """Compare code-inferred values against DB reality.
        Returns validation report."""
        report = {
            'verified': [],      # Values found in both code and DB
            'code_only': [],     # Values in code but not in DB
            'db_only': [],       # Values in DB but not in code
            'conflicts': [],     # Values where code meaning might be wrong
        }
        
        db_maps = self.get_db_value_maps()
        
        for col, code_vals in code_value_maps.items():
            col_lower = col.lower()
            db_vals = db_maps.get(col_lower, {})
            
            for val, meaning in code_vals.items():
                if val in db_vals:
                    report['verified'].append({
                        'column': col, 'value': val, 'meaning': meaning,
                        'db_count': db_vals[val]
                    })
                else:
                    report['code_only'].append({
                        'column': col, 'value': val, 'meaning': meaning,
                        'note': 'Value exists in code but not in DB (unused or different env)'
                    })
            
            for val, db_info in db_vals.items():
                if val not in code_vals:
                    report['db_only'].append({
                        'column': col, 'value': val, 'db_info': db_info,
                        'note': 'Value exists in DB but not handled in code'
                    })
        
        return report
    
    def get_table_schema(self, table_name: str) -> Optional[Dict]:
        """Get schema for a specific table."""
        return self.tables.get(table_name)
    
    def get_missing_tables(self, code_tables: list) -> list:
        """Find tables referenced in code but not in DB."""
        db_table_names = set(self.tables.keys())
        return [t for t in code_tables if t not in db_table_names]
    
    def generate_db_wiring_section(self, top_n: int = 15) -> str:
        """Generate DB schema section for WIRING.md."""
        lines = [
            "",
            "---",
            "",
            "## Database Schema (Auto-extracted from DB)",
            "",
            "> Values verified against live database",
            "",
        ]
        
        # Sort by row count
        sorted_tables = sorted(
            self.tables.items(),
            key=lambda x: self.row_counts.get(x[0], 0),
            reverse=True
        )
        
        for table_name, schema in sorted_tables[:top_n]:
            cols = schema.get('columns', [])
            row_count = self.row_counts.get(table_name, 0)
            status_cols = schema.get('status_columns', [])
            
            lines.append(f"### `{table_name}` ({len(cols)} columns, {row_count:,} rows)")
            lines.append("")
            
            # Column table
            lines.append("| Column | Type | Null | Default | Key |")
            lines.append("|---|---|---|---|---|")
            for col in cols:
                nullable = 'Y' if col.get('nullable') else 'N'
                default = col.get('default', '-') or '-'
                if len(str(default)) > 20:
                    default = str(default)[:17] + '...'
                key = col.get('key', '') or '-'
                lines.append(
                    f"| `{col['name']}` | {col['type']} | {nullable} | {default} | {key} |"
                )
            
            # Status value distributions
            if table_name in self.status_values:
                for col_name, dist in self.status_values[table_name].items():
                    if dist:
                        lines.append(f"\n**`{col_name}` values (from DB):**\n")
                        lines.append("| Value | Count |")
                        lines.append("|---|---|")
                        for d in dist[:10]:
                            lines.append(f"| {d['value']} | {d['count']:,} |")
            
            lines.append("")
        
        # Foreign keys
        if self.foreign_keys:
            lines.append("### Foreign Key Relationships\n")
            lines.append("| Table | Column | References |")
            lines.append("|---|---|---|")
            for fk in self.foreign_keys:
                lines.append(
                    f"| `{fk['table']}` | `{fk['column']}` | "
                    f"`{fk['references_table']}.{fk['references_column']}` |"
                )
            lines.append("")
        
        return '\n'.join(lines)


def load_db_data(brain_dir: Path) -> Optional[DBIntegrator]:
    """Load db_schema.json if it exists."""
    json_path = brain_dir / 'db_schema.json'
    if json_path.exists():
        with open(json_path, 'r', encoding='utf-8') as f:
            return DBIntegrator(json.load(f))
    return None
