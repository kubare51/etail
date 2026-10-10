"""
Brain Builder — DB Schema Extractor v2.2
Generates SQL queries for MCP execution, processes results into db_schema.json.
Run from brain builder with: lca brain-db billing
"""

import sys
import json
from pathlib import Path
from datetime import datetime


STATUS_PATTERNS = ['status', 'type', 'is_', 'flag', 'mode', 'state',
                   'category', 'level', 'priority', 'role', '_for',
                   'make_as_', 'allow_', 'has_', 'enable_']

KNOWN_STATUS_COLUMNS = {
    'bill_type', 'item_type', 'payment_mode', 'mc_type', 'bill_discount_type',
    'calculation_based_on', 'billing_for', 'make_as_advance', 'salesTransType',
    'receipt_type', 'issue_type', 'credit_status', 'return_status', 'bill_status',
    'NB_type', 'is_vip', 'lot_mc_type', 'cash_type', 'is_credit', 'is_to_be',
    'is_deleted', 'is_bill_split', 'receipt_to', 'issue_to',
}


def is_status_column(col_name, col_type):
    name = col_name.lower()
    ctype = col_type.lower()
    if name in KNOWN_STATUS_COLUMNS:
        return True
    if any(p in name for p in STATUS_PATTERNS):
        if any(t in ctype for t in ['int', 'tinyint', 'smallint', 'enum']):
            return True
    return False


def process_schema_rows(rows):
    """Process INFORMATION_SCHEMA.COLUMNS rows into structured schema data."""
    tables = {}
    for row in rows:
        table = row['TABLE_NAME']
        if table not in tables:
            tables[table] = {'table': table, 'columns': [], 'status_columns': [], 'indexes': []}
        
        col = {
            'name': row['COLUMN_NAME'],
            'type': row['COLUMN_TYPE'],
            'nullable': row['IS_NULLABLE'] == 'YES',
            'default': row.get('COL_DEFAULT') if row.get('COL_DEFAULT') != 'NULL' else None,
            'key': row.get('COLUMN_KEY', ''),
        }
        tables[table]['columns'].append(col)
        
        if is_status_column(row['COLUMN_NAME'], row['COLUMN_TYPE']):
            tables[table]['status_columns'].append(row['COLUMN_NAME'])
    
    return tables


def process_row_counts(rows):
    """Process TABLE_ROWS data."""
    counts = {}
    for row in rows:
        counts[row['TABLE_NAME']] = row.get('TABLE_ROWS', 0)
    return counts


def process_status_values(rows):
    """Process status value distribution query results."""
    result = {}
    for row in rows:
        table = row.get('tbl', '')
        col = row.get('col', '')
        val = str(row.get('val', 'NULL'))
        cnt = int(row.get('cnt', 0))
        
        if table not in result:
            result[table] = {}
        if col not in result[table]:
            result[table][col] = []
        result[table][col].append({'value': val, 'count': cnt})
    
    return result


def generate_status_value_queries(tables_with_status):
    """Generate SQL to get value distributions for all status columns."""
    unions = []
    for table, cols in tables_with_status.items():
        for col in cols[:5]:  # Max 5 status cols per table
            unions.append(
                f"SELECT '{table}' as tbl, '{col}' as col, "
                f"CAST(`{col}` AS CHAR) as val, COUNT(*) as cnt "
                f"FROM `{table}` GROUP BY `{col}`"
            )
    
    # Split into chunks of 10 UNION queries to avoid timeout
    chunks = []
    for i in range(0, len(unions), 10):
        chunk = unions[i:i+10]
        chunks.append(' UNION ALL '.join(chunk) + ' ORDER BY tbl, col, cnt DESC')
    
    return chunks


def main():
    """Generate the SQL queries needed. Output to stdout for MCP execution."""
    if len(sys.argv) < 2:
        print("Usage: python db_extractor.py <module> --project-root <path>")
        sys.exit(1)
    
    module = sys.argv[1]
    project_root = None
    mode = 'generate'  # 'generate' or 'merge'
    
    for i, arg in enumerate(sys.argv):
        if arg == '--project-root' and i + 1 < len(sys.argv):
            project_root = Path(sys.argv[i + 1])
        if arg == '--merge':
            mode = 'merge'
    
    if project_root is None:
        project_root = Path.cwd()
    
    brain_dir = project_root / 'knowledge_brain' / f'{module}_v2'
    
    if mode == 'generate':
        # Read tables from WIRING.md
        tables = []
        wiring_path = brain_dir / 'WIRING.md'
        if wiring_path.exists():
            import re
            content = wiring_path.read_text(encoding='utf-8')
            in_owned = False
            for line in content.split('\n'):
                if '### Owned' in line:
                    in_owned = True
                    continue
                if '### Referenced' in line:
                    in_owned = False
                    continue
                if in_owned and line.startswith('|'):
                    m = re.search(r'\| `(\w+)` \|', line)
                    if m:
                        tables.append(m.group(1))
        
        if not tables:
            print("ERROR: No tables found. Run brain-build first.", file=sys.stderr)
            sys.exit(1)
        
        # Generate the queries
        in_clause = ','.join([f"'{t}'" for t in tables])
        
        queries = {
            'schema': (
                f"SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, "
                f"IFNULL(COLUMN_DEFAULT, 'NULL') as COL_DEFAULT, COLUMN_KEY "
                f"FROM INFORMATION_SCHEMA.COLUMNS "
                f"WHERE TABLE_SCHEMA = 'retail_dev' AND TABLE_NAME IN ({in_clause}) "
                f"ORDER BY TABLE_NAME, ORDINAL_POSITION"
            ),
            'row_counts': (
                f"SELECT TABLE_NAME, TABLE_ROWS "
                f"FROM INFORMATION_SCHEMA.TABLES "
                f"WHERE TABLE_SCHEMA = 'retail_dev' AND TABLE_NAME IN ({in_clause})"
            ),
            'foreign_keys': (
                f"SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME "
                f"FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE "
                f"WHERE TABLE_SCHEMA = 'retail_dev' AND REFERENCED_TABLE_NAME IS NOT NULL "
                f"AND TABLE_NAME IN ({in_clause})"
            ),
        }
        
        # Output queries as JSON for MCP execution
        output = {'module': module, 'tables': tables, 'queries': queries}
        json.dump(output, sys.stdout, indent=2)
    
    elif mode == 'merge':
        # Read db_schema.json and merge into brain docs
        json_path = brain_dir / 'db_schema.json'
        if not json_path.exists():
            print(f"ERROR: {json_path} not found", file=sys.stderr)
            sys.exit(1)
        
        with open(json_path, 'r', encoding='utf-8') as f:
            db_data = json.load(f)
        
        print(f"Merging DB data: {db_data.get('summary', {})}")


if __name__ == '__main__':
    main()
