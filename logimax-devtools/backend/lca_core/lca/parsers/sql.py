"""SQL view file parser.

Parses SQL view definition files (CREATE VIEW ... AS SELECT ... FROM ...)
to extract view names and their table dependencies.
"""

import re
from pathlib import Path

from lca.models import FunctionDef, FunctionCall
from .base import BaseParser


class SQLViewParser(BaseParser):
    """Parser for SQL view definition files."""
    
    # Pattern: CREATE [OR REPLACE] [... modifiers ...] VIEW view_name
    # MySQL exports include modifiers like ALGORITHM=UNDEFINED SQL SECURITY DEFINER
    # We use non-greedy .{0,200}? to skip any modifiers between CREATE and VIEW
    _VIEW_PATTERN = re.compile(
        r"CREATE\s+(?:OR\s+REPLACE\s+)?.{0,200}?VIEW\s+[`]?(\w+)[`]?",
        re.IGNORECASE
    )
    
    # Pattern to extract tables: FROM table, JOIN table, etc.
    # Handles backtick-quoted names and optional opening parenthesis from subqueries
    _TABLE_PATTERN = re.compile(
        r"(?:FROM|JOIN)\s+\(?[`]?([a-zA-Z_]\w*)[`]?",
        re.IGNORECASE
    )
    
    # SQL keywords that aren't table names
    _SQL_KEYWORDS = {
        "SELECT", "WHERE", "SET", "VALUES", "NULL", "FROM",
        "AND", "OR", "NOT", "IN", "ON", "AS", "IF", "IS",
        "LEFT", "RIGHT", "INNER", "OUTER", "CROSS", "JOIN",
        "GROUP", "ORDER", "BY", "HAVING", "LIMIT", "OFFSET",
        "DISTINCT", "ALL", "ANY", "EXISTS", "BETWEEN", "LIKE",
        "CASE", "WHEN", "THEN", "ELSE", "END", "ASC", "DESC",
        "UNION", "EXCEPT", "INTERSECT", "INTO",
        "TABLE", "INDEX", "VIEW", "CREATE", "ALTER", "DROP",
        "CONCAT", "COALESCE", "IFNULL", "COUNT", "SUM", "AVG", "MIN", "MAX",
        "TRUE", "FALSE", "DUAL",
    }
    
    @property
    def language(self) -> str:
        return "sql"
    
    @property
    def extensions(self) -> list[str]:
        return [".sql"]
    
    def parse_file(self, file_path: Path) -> tuple[list[FunctionDef], list[FunctionCall]]:
        """Parse a SQL view file and extract view definitions and table references."""
        try:
            content = file_path.read_text(encoding="utf-8", errors="ignore")
        except Exception:
            return [], []
        
        functions: list[FunctionDef] = []
        calls: list[FunctionCall] = []
        file_str = str(file_path)
        
        # Find all CREATE VIEW statements
        for view_match in self._VIEW_PATTERN.finditer(content):
            view_name = view_match.group(1)
            line = content[:view_match.start()].count("\n") + 1
            
            # Register the view as a "function" definition
            functions.append(FunctionDef(
                name=f"VIEW:{view_name}",
                file=file_str,
                line=line,
                end_line=None,
                params=[],
                language="sql"
            ))
            
            # Extract the view body (from this CREATE to next CREATE or EOF)
            body_start = view_match.end()
            next_view = self._VIEW_PATTERN.search(content, body_start)
            body_end = next_view.start() if next_view else len(content)
            view_body = content[body_start:body_end]
            
            # Find all referenced tables in the view body
            seen_tables = set()
            for table_match in self._TABLE_PATTERN.finditer(view_body):
                table_name = table_match.group(1)
                
                # Skip SQL keywords and short aliases
                if table_name.upper() in self._SQL_KEYWORDS:
                    continue
                if len(table_name) <= 2:
                    continue
                
                if table_name not in seen_tables:
                    seen_tables.add(table_name)
                    table_line = line + view_body[:table_match.start()].count("\n")
                    calls.append(FunctionCall(
                        source=f"VIEW:{view_name}",
                        target=f"TABLE:{table_name}",
                        line=table_line,
                        file=file_str,
                        call_type="view_reads",
                        target_file=None
                    ))
        
        return functions, calls
