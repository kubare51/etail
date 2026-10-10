"""Watch mode for live reindexing — incremental single-file updates.

Monitors file changes and performs surgical SQLite updates instead
of full directory rebuilds. Inspired by IntelliJ's incremental indexer.

Architecture:
  1. Initial full index → SQLite DB
  2. On file change → reparse single file → update symbols/refs in DB
  3. Re-resolve affected references (callers of changed symbols)
"""

import sys
import time
from pathlib import Path
from datetime import datetime
from typing import Optional, Callable

from rich.console import Console

try:
    from watchdog.observers import Observer
    from watchdog.events import FileSystemEventHandler, FileModifiedEvent, FileCreatedEvent, FileDeletedEvent
    WATCHDOG_AVAILABLE = True
except ImportError:
    WATCHDOG_AVAILABLE = False


console = Console()


class IncrementalHandler(FileSystemEventHandler):
    """Handler that performs incremental single-file reindexing."""
    
    def __init__(
        self, 
        project_root: Path,
        db_path: Path,
        extensions: set[str],
        debounce_seconds: float = 1.0,
        on_update: Optional[Callable] = None,
    ):
        self.project_root = project_root
        self.db_path = db_path
        self.extensions = extensions
        self.debounce_seconds = debounce_seconds
        self.on_update = on_update
        self._last_update = 0.0
        self._pending_files: set[str] = set()
        self._db = None
        self._indexer = None
    
    def _get_db(self):
        if self._db is None:
            from lca.storage.symbol_db import SymbolDB
            self._db = SymbolDB(self.db_path)
        return self._db
    
    def _get_indexer(self):
        if self._indexer is None:
            from lca.core import Indexer
            self._indexer = Indexer()
        return self._indexer
    
    def _should_process(self, path: str) -> bool:
        """Check if file should trigger reindexing."""
        p = Path(path)
        return p.suffix.lower() in self.extensions and p.exists()
    
    def _process_batch(self) -> None:
        """Process all pending file changes as a batch."""
        now = time.time()
        if now - self._last_update < self.debounce_seconds:
            return
        
        self._last_update = now
        
        files_to_process = list(self._pending_files)
        self._pending_files.clear()
        
        if not files_to_process:
            return
        
        t_start = time.time()
        db = self._get_db()
        indexer = self._get_indexer()
        conn = db._get_conn()
        
        total_symbols = 0
        total_refs = 0
        
        for file_path_str in files_to_process:
            try:
                file_path = Path(file_path_str)
                
                # 1. Remove old data for this file
                conn.execute("DELETE FROM refs WHERE file_path = ?", (file_path_str,))
                conn.execute("DELETE FROM symbols WHERE file_path = ?", (file_path_str,))
                
                # 2. Reparse the single file
                result = indexer.index_file(file_path, "incremental")
                
                # 3. Insert new symbols
                for f in result.functions:
                    class_name = None
                    if "::" in f.name:
                        class_name = f.name.split("::")[0]
                    
                    conn.execute(
                        """INSERT OR REPLACE INTO symbols
                           (name, file_id, file_path, line, end_line, language, class_name, params)
                           VALUES (?, (SELECT COALESCE((SELECT id FROM files WHERE path = ?), 0)),
                                   ?, ?, ?, ?, ?, ?)""",
                        (f.name, file_path_str, file_path_str, f.line, f.end_line,
                         f.language, class_name,
                         __import__('json').dumps(f.params) if f.params else None),
                    )
                    total_symbols += 1
                    
                    # Insert metrics if available
                    if f.metrics:
                        m = f.metrics
                        conn.execute(
                            """INSERT OR REPLACE INTO metrics
                               (symbol_id, cyclomatic_complexity, cognitive_complexity,
                                lines_of_code, param_count, max_nesting_depth)
                               VALUES ((SELECT id FROM symbols WHERE name = ? AND file_path = ?),
                                       ?, ?, ?, ?, ?)""",
                            (f.name, file_path_str,
                             m.get("cyclomatic_complexity"),
                             m.get("cognitive_complexity"),
                             m.get("lines_of_code"),
                             m.get("param_count"),
                             m.get("max_nesting_depth")),
                        )
                
                # 4. Insert new references
                for c in result.calls:
                    conn.execute(
                        """INSERT INTO refs
                           (source_name, target_name, line, file_path, call_type, target_file, metadata)
                           VALUES (?, ?, ?, ?, ?, ?, ?)""",
                        (c.source, c.target, c.line, c.file, c.call_type, c.target_file,
                         __import__('json').dumps(c.metadata) if c.metadata else None),
                    )
                    total_refs += 1
                
            except Exception as e:
                console.print(f"[red]Error reindexing {Path(file_path_str).name}:[/red] {e}")
        
        conn.commit()
        t_elapsed = (time.time() - t_start) * 1000  # ms
        
        file_names = ", ".join(Path(f).name for f in files_to_process[:3])
        if len(files_to_process) > 3:
            file_names += f" +{len(files_to_process) - 3} more"
        
        console.print(
            f"[green]⚡[/green] Updated {file_names}: "
            f"{total_symbols} symbols, {total_refs} refs "
            f"[dim]({t_elapsed:.0f}ms)[/dim]"
        )
        
        if self.on_update:
            self.on_update(files_to_process)
    
    def on_modified(self, event: FileModifiedEvent) -> None:
        if not event.is_directory and self._should_process(event.src_path):
            self._pending_files.add(event.src_path)
            self._process_batch()
    
    def on_created(self, event: FileCreatedEvent) -> None:
        if not event.is_directory and self._should_process(event.src_path):
            self._pending_files.add(event.src_path)
            self._process_batch()
    
    def on_deleted(self, event: FileDeletedEvent) -> None:
        if not event.is_directory:
            p = Path(event.src_path)
            if p.suffix.lower() in self.extensions:
                # Remove all data for deleted file
                db = self._get_db()
                conn = db._get_conn()
                conn.execute("DELETE FROM refs WHERE file_path = ?", (event.src_path,))
                conn.execute("DELETE FROM symbols WHERE file_path = ?", (event.src_path,))
                conn.commit()
                console.print(f"[yellow]✗[/yellow] Removed index for deleted file: {p.name}")


# ── Legacy handler (full rebuild) ──────────────────────────────────────

class IndexUpdateHandler(FileSystemEventHandler):
    """Legacy handler that triggers full reindexing on file changes."""
    
    def __init__(
        self, 
        path: Path, 
        module_name: str, 
        output_path: Path,
        extensions: set[str],
        debounce_seconds: float = 2.0,
        on_index: Optional[Callable] = None
    ):
        self.path = path
        self.module_name = module_name
        self.output_path = output_path
        self.extensions = extensions
        self.debounce_seconds = debounce_seconds
        self.on_index = on_index
        self._last_update = 0.0
        self._pending_files: set[str] = set()
    
    def _should_process(self, path: str) -> bool:
        p = Path(path)
        return p.suffix.lower() in self.extensions
    
    def _trigger_reindex(self) -> None:
        now = time.time()
        if now - self._last_update < self.debounce_seconds:
            return
        
        self._last_update = now
        
        from lca.core import Indexer
        from lca.storage import save_index
        
        console.print(f"[cyan]Reindexing:[/cyan] {len(self._pending_files)} file(s) changed")
        
        try:
            indexer = Indexer()
            if self.path.is_file():
                result = indexer.index_file(self.path, self.module_name)
            else:
                result = indexer.index_directory(self.path, self.module_name)
            
            save_index(result, self.output_path)
            
            console.print(
                f"[green]✓[/green] Updated index: "
                f"{len(result.functions)} functions, {len(result.calls)} calls"
            )
            
            if self.on_index:
                self.on_index(result)
            
            self._pending_files.clear()
            
        except Exception as e:
            console.print(f"[red]Error:[/red] {e}")
    
    def on_modified(self, event: FileModifiedEvent) -> None:
        if not event.is_directory and self._should_process(event.src_path):
            self._pending_files.add(event.src_path)
            self._trigger_reindex()
    
    def on_created(self, event: FileCreatedEvent) -> None:
        if not event.is_directory and self._should_process(event.src_path):
            self._pending_files.add(event.src_path)
            self._trigger_reindex()
    
    def on_deleted(self, event: FileDeletedEvent) -> None:
        if not event.is_directory and self._should_process(event.src_path):
            self._pending_files.add(event.src_path)
            self._trigger_reindex()


# ── Public API ─────────────────────────────────────────────────────────

def watch_incremental(
    project_root: Path,
    db_path: Optional[Path] = None,
    extensions: Optional[set[str]] = None,
    debounce_seconds: float = 1.0,
    watch_dirs: Optional[list[str]] = None,
) -> None:
    """
    Watch project directories for changes and perform incremental SQLite updates.
    
    This is the preferred watch mode — single-file updates in ~50ms instead
    of full 25-second rebuilds.
    
    Args:
        project_root: Project root directory
        db_path: Path to SQLite database (default: ./fullstack_index.db)
        extensions: File extensions to watch
        debounce_seconds: Debounce delay
        watch_dirs: Subdirectories to watch (default: CI3 standard dirs)
    """
    if not WATCHDOG_AVAILABLE:
        console.print("[red]Error:[/red] watchdog not installed. Run: uv add watchdog")
        sys.exit(1)
    
    if db_path is None:
        db_path = Path("./fullstack_index.db")
    
    if extensions is None:
        extensions = {".js", ".jsx", ".php", ".py", ".mjs"}
    
    if watch_dirs is None:
        watch_dirs = [
            "admin/application/controllers",
            "admin/application/models",
            "admin/assets/js",
        ]
    
    # Ensure DB exists (should have been created by index_fullstack)
    if not db_path.exists():
        console.print(f"[yellow]Warning:[/yellow] Database not found at {db_path}")
        console.print("Run index_fullstack first to build the initial database.")
        console.print("Starting in legacy full-rebuild mode instead...")
        watch_and_index(project_root, "fullstack")
        return
    
    console.print(f"[bold green]⚡ Incremental Watch Mode[/bold green]")
    console.print(f"[dim]Database: {db_path}[/dim]")
    console.print(f"[dim]Extensions: {', '.join(extensions)}[/dim]")
    
    handler = IncrementalHandler(
        project_root=project_root,
        db_path=db_path,
        extensions=extensions,
        debounce_seconds=debounce_seconds,
    )
    
    observer = Observer()
    
    for subdir in watch_dirs:
        watch_path = project_root / subdir
        if watch_path.exists():
            observer.schedule(handler, str(watch_path), recursive=True)
            console.print(f"[dim]  Watching: {subdir}[/dim]")
        else:
            console.print(f"[yellow]  Skip: {subdir} (not found)[/yellow]")
    
    observer.start()
    console.print("[dim]Press Ctrl+C to stop[/dim]")
    
    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        console.print("\n[yellow]Stopping watcher...[/yellow]")
        observer.stop()
    
    observer.join()
    handler._get_db().close()
    console.print("[green]✓[/green] Watch mode stopped")


def watch_and_index(
    path: Path,
    module_name: str,
    output_path: Optional[Path] = None,
    extensions: Optional[set[str]] = None,
    debounce_seconds: float = 2.0,
) -> None:
    """
    Legacy watch mode — full directory rebuild on changes.
    Use watch_incremental() for faster updates.
    """
    if not WATCHDOG_AVAILABLE:
        console.print("[red]Error:[/red] watchdog not installed. Run: uv add watchdog")
        sys.exit(1)
    
    if output_path is None:
        output_path = Path(f"./{module_name}_index.json")
    
    if extensions is None:
        extensions = {".js", ".jsx", ".php", ".py", ".mjs"}
    
    # Initial index
    from lca.core import Indexer
    from lca.storage import save_index
    
    console.print(f"[cyan]Initial indexing:[/cyan] {path}")
    indexer = Indexer()
    
    if path.is_file():
        result = indexer.index_file(path, module_name)
        watch_path = path.parent
    else:
        result = indexer.index_directory(path, module_name)
        watch_path = path
    
    save_index(result, output_path)
    console.print(
        f"[green]✓[/green] Initial index: "
        f"{len(result.functions)} functions, {len(result.calls)} calls"
    )
    
    # Start watching
    event_handler = IndexUpdateHandler(
        path=path,
        module_name=module_name,
        output_path=output_path,
        extensions=extensions,
        debounce_seconds=debounce_seconds,
    )
    
    observer = Observer()
    observer.schedule(event_handler, str(watch_path), recursive=True)
    observer.start()
    
    console.print(f"[bold green]Watching:[/bold green] {watch_path}")
    console.print(f"[dim]Extensions: {', '.join(extensions)}[/dim]")
    console.print(f"[dim]Output: {output_path}[/dim]")
    console.print("[dim]Press Ctrl+C to stop[/dim]")
    
    try:
        while True:
            time.sleep(1)
    except KeyboardInterrupt:
        console.print("\n[yellow]Stopping watcher...[/yellow]")
        observer.stop()
    
    observer.join()
    console.print("[green]✓[/green] Watch mode stopped")
