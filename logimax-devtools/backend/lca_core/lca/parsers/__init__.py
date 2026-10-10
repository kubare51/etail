from .base import BaseParser
from .javascript import JavaScriptParser
from .php import PHPParser
from .sql import SQLViewParser

AVAILABLE_PARSERS = [
    PHPParser(),
    JavaScriptParser(),
    SQLViewParser(),
]

def get_parser_for_file(filepath: str) -> BaseParser | None:
    path_str = str(filepath)
    for parser in AVAILABLE_PARSERS:
        if any(path_str.endswith(ext) for ext in parser.extensions):
            return parser
    return None

try:
    from .python import PythonParser
    AVAILABLE_PARSERS.append(PythonParser())
    __all__ = ["BaseParser", "JavaScriptParser", "PHPParser", "PythonParser", "SQLViewParser", "AVAILABLE_PARSERS", "get_parser_for_file"]
except ImportError:
    __all__ = ["BaseParser", "JavaScriptParser", "PHPParser", "SQLViewParser", "AVAILABLE_PARSERS", "get_parser_for_file"]
