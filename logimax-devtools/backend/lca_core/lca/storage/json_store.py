"""JSON file storage for indexes."""

import json
from pathlib import Path

from lca.models import (
    ModuleIndex, FunctionDef, FunctionCall, Level2Deps,
    FormulaExpr, Accumulator, ConditionalBranch, MagicNumber, UIMatch,
)


def save_index(index: ModuleIndex, output_path: Path) -> None:
    """Save a module index to a JSON file."""
    output_path.parent.mkdir(parents=True, exist_ok=True)
    
    data = index.to_kb_format()
    
    with open(output_path, "w", encoding="utf-8") as f:
        json.dump(data, f, indent=2, ensure_ascii=False)


def load_index(path: Path) -> ModuleIndex:
    """Load a module index from a JSON file (KB format)."""
    with open(path, "r", encoding="utf-8") as f:
        data = json.load(f)
    
    # Convert KB format back to ModuleIndex
    functions = [
        FunctionDef(
            name=f["name"],
            file=f["file"],
            line=f["line"],
            end_line=f.get("end_line"),
            params=f.get("params", []),
            language=f.get("language", "javascript")
        )
        for f in data.get("functions", [])
    ]
    
    calls = [
        FunctionCall(
            source=c.get("source", "GLOBAL"),
            target=c["target"],
            line=c["line"],
            file=c.get("file", ""),
            call_type=c.get("type", "calls"),
            target_file=c.get("target_file"),
            metadata=c.get("metadata"),
        )
        for c in data.get("calls", [])
    ]
    
    level2 = {}
    for name, deps in data.get("level2", {}).items():
        level2[name] = Level2Deps(
            calls=deps.get("calls", []),
            called_by=deps.get("called_by", [])
        )
    
    # Deep analysis layers (formulas, accumulators, conditionals, magic_numbers)
    formulas = [
        FormulaExpr(**f)
        for f in data.get("formulas", [])
    ]
    
    accumulators = [
        Accumulator(**a)
        for a in data.get("accumulators", [])
    ]
    
    conditionals = [
        ConditionalBranch(**c)
        for c in data.get("conditionals", [])
    ]
    
    magic_numbers = [
        MagicNumber(**m)
        for m in data.get("magic_numbers", [])
    ]
    
    ui_matches = [
        UIMatch(**u)
        for u in data.get("ui_matches", [])
    ]
    
    return ModuleIndex(
        module=data.get("module", "unknown"),
        version=data.get("version", "2.0.0"),
        indexed_at=data.get("indexed_at", ""),
        functions=functions,
        calls=calls,
        files=data.get("files", []),
        ui_matches=ui_matches,
        level2=level2,
        formulas=formulas,
        accumulators=accumulators,
        conditionals=conditionals,
        magic_numbers=magic_numbers,
    )
