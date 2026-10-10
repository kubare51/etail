from .json_store import save_index, load_index
from .symbol_db import SymbolDB

# Legacy import — may not exist in all installs
try:
    from .sqlite import SQLiteStorage
except ImportError:
    SQLiteStorage = None

__all__ = ["save_index", "load_index", "SymbolDB", "SQLiteStorage"]
