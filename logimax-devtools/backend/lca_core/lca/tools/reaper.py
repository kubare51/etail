from pathlib import Path
import json
from rich.console import Console
from rich.table import Table
from rich.panel import Panel

console = Console()

class Reaper:
    """The Dead Code Detector backend."""
    
    def __init__(self, index_path: str):
        self.index_path = Path(index_path)
        self.data = self._load_index()
        
    def _load_index(self) -> dict:
        if not self.index_path.exists():
            raise FileNotFoundError(f"Index not found: {self.index_path}")
        with open(self.index_path, "r", encoding="utf-8") as f:
            return json.load(f)

    def scan(self) -> list[dict]:
        """Find functions with 0 incoming calls (dead code candidates).
        
        CI3-aware detection:
        - Controllers are entry points (called by router), always excluded
        - Model methods are checked for model_call references
        - JS functions are checked for direct + event-bound calls
        - Classifies zombies by category for triage
        """
        
        # 1. Build incoming call counts
        incoming_counts: dict[str, int] = {}
        incoming_types: dict[str, set] = {}  # track WHAT calls each function
        
        all_funcs = self.data.get("functions", [])
        for f in all_funcs:
            incoming_counts[f["name"]] = 0
            incoming_types[f["name"]] = set()
            
        calls = self.data.get("calls", [])
        for c in calls:
            target = c.get("target")
            if target in incoming_counts:
                incoming_counts[target] += 1
                incoming_types[target].add(c.get("type", "calls"))
                
        # 2. Filter for "Zombies" with CI3 awareness
        zombies = []
        for f in all_funcs:
            name = f["name"]
            file_path = f["file"]
            file_lower = file_path.lower()
            
            # SAFEGUARD: Ignore Controllers (entry points via router)
            if "controllers" in file_lower:
                continue
                
            # SAFEGUARD: Ignore standard CI lifecycle methods
            if name.split("::")[-1] in {"index", "__construct", "db_connect"}:
                continue

            if incoming_counts[name] == 0:
                # Classify zombie category
                if "models" in file_lower:
                    category = "model_method"
                elif file_lower.endswith(".js") or file_lower.endswith(".jsx"):
                    category = "js_function"
                elif "helpers" in file_lower:
                    category = "helper"
                elif "libraries" in file_lower:
                    category = "library"
                else:
                    category = "other"
                
                zombies.append({
                    "name": name,
                    "file": file_path,
                    "line": f["line"],
                    "category": category,
                    "confidence": "Medium",
                })
                
        # 3. GREPPER GUARD: Textual Verification
        # Only index files that are in the parsed index (avoiding vendor/node_modules if excluded)
        indexed_files = [f["path"] for f in self.data.get("files", [])]
        
        # Build token frequency map if not already cached (could be optimized)
        # For now, we do a "lazy" scan or a full scan? A full scan is safer.
        # To avoid massive latency, we only scan if we have zombies.
        
        if zombies:
            console.print(f"[yellow]Grepper Guard: Verifying {len(zombies)} candidates against {len(indexed_files)} files...[/yellow]")
            token_counts = self._build_token_counts(indexed_files)
            
            verified_zombies = []
            for z in zombies:
                name = z["name"]
                # Count in text (all files)
                text_hits = token_counts.get(name, 0)
                
                # If hit count > 1, it implies usage (1 definition + at least 1 usage)
                # We update confidence or remove it.
                if text_hits <= 1:
                    # High Confidence: Found in graph 0 times, found in text <= 1 time.
                    z["confidence"] = "High"
                    z["hits"] = text_hits
                    verified_zombies.append(z)
                else:
                    # Low Confidence: Graph says 0, but text says > 1. Likely dynamic.
                    # We can either exclude it or mark it as "Suspicious".
                    # User wants to be careful. Let's mark it "Dynamic?" and keep it but low severity.
                    z["confidence"] = "Low"
                    z["title"] = f"Dynamic?: {name}" 
                    z["hits"] = text_hits
                    verified_zombies.append(z)
            
            zombies = verified_zombies

        return zombies

    def _build_token_counts(self, file_paths: list[str]) -> dict:
        """Scan all files and count token occurrences."""
        import re
        from collections import Counter
        
        counts = Counter()
        # Regex for valid PHP identifiers (simple version)
        token_pattern = re.compile(r'[a-zA-Z_][a-zA-Z0-9_]*')
        
        for path_str in file_paths:
            try:
                path = Path(path_str)
                if not path.exists():
                    continue
                    
                # Skip massive files or binary
                if path.stat().st_size > 500 * 1024:  # 500KB limit
                    continue

                with open(path, 'r', encoding='utf-8', errors='ignore') as f:
                    content = f.read()
                    # Find all tokens
                    tokens = token_pattern.findall(content)
                    counts.update(tokens)
            except Exception:
                continue
                
        return counts

    def print_report(self, zombies: list[dict]):
        console.print(Panel(f"[bold red]Reaper Found {len(zombies)} Zombie Functions[/bold red]", border_style="red"))
        
        if not zombies:
            console.print("[green]No dead code detected! Clean codebase.[/green]")
            return

        # Category breakdown
        from collections import Counter
        cats = Counter(z.get("category", "other") for z in zombies)
        console.print("\n[bold]Category Breakdown:[/bold]")
        for cat, count in cats.most_common():
            icon = {"model_method": "🔧", "js_function": "📜", "helper": "🔨", "library": "📚"}.get(cat, "❓")
            console.print(f"  {icon} {cat}: {count}")
        console.print()

        # Show by category
        for cat in ["model_method", "helper", "library", "js_function", "other"]:
            cat_zombies = [z for z in zombies if z.get("category") == cat]
            if not cat_zombies:
                continue
            
            conf_high = [z for z in cat_zombies if z.get("confidence") == "High"]
            
            table = Table(
                title=f"{cat} ({len(cat_zombies)} total, {len(conf_high)} high confidence)",
                show_header=True, header_style="bold red"
            )
            table.add_column("Function", style="white")
            table.add_column("File", style="dim")
            table.add_column("Line")
            table.add_column("Confidence", style="yellow")
            
            # Show top 15 per category, high confidence first
            sorted_z = sorted(cat_zombies, key=lambda z: (0 if z.get("confidence") == "High" else 1))
            for z in sorted_z[:15]:
                short_path = Path(z["file"]).name
                table.add_row(z["name"], short_path, str(z["line"]), z.get("confidence", "?"))
                
            console.print(table)
            if len(cat_zombies) > 15:
                console.print(f"[dim]...and {len(cat_zombies)-15} more {cat}[/dim]")
            console.print()

