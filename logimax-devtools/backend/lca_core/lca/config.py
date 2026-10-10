"""LCA Configuration -- .lca.toml loader.

Loads project-level configuration from .lca.toml at the project root.
Falls back to sensible defaults for all values.

Example .lca.toml:

    [project]
    name = "etail_v3"
    root = "."

    [health]
    complexity_weight = 0.30
    security_weight = 0.25
    dead_code_weight = 0.15
    n1_weight = 0.15
    hardcoded_weight = 0.10
    smell_weight = 0.05

    [health.thresholds]
    grade_a = 90
    grade_b = 75
    grade_c = 60
    grade_d = 40

    [scan]
    exclude_dirs = ["vendor", "node_modules", ".git", "tests"]
    exclude_files = ["*.min.js", "*.map"]

    [hardcoded]
    # Values to ignore (known safe hardcoded values)
    whitelist = [
        "== 15",   # payment mode: Cash
        "== 11",   # payment mode: UPI
        "> 180",   # phonepe timeout check
    ]

    [n1]
    # Patterns to ignore
    whitelist = [
        "get_where",  # single-row lookups in loops are often OK
    ]

    [risk]
    churn_months = 3
    critical_threshold = 70
    high_threshold = 50

    [modules]
    # Custom module mappings (extends built-in map)
    [modules.custom]
    loyalty = ["admin_loyalty", "loyalty_model"]
    crm = ["admin_crm", "crm_model"]
"""

from __future__ import annotations

import os
from pathlib import Path
from typing import Any
from pydantic import BaseModel, Field, ValidationError


class HealthConfig(BaseModel):
    """Health score weights and thresholds."""
    complexity_weight: float = 0.30
    security_weight: float = 0.25
    dead_code_weight: float = 0.15
    n1_weight: float = 0.15
    hardcoded_weight: float = 0.10
    smell_weight: float = 0.05
    grade_a: int = 90
    grade_b: int = 75
    grade_c: int = 60
    grade_d: int = 40


class ScanConfig(BaseModel):
    """Scan exclusion settings."""
    exclude_dirs: list[str] = Field(default_factory=lambda: [
        'vendor', 'node_modules', '.git', 'tests', '.venv', '__pycache__',
    ])
    exclude_files: list[str] = Field(default_factory=lambda: [
        '*.min.js', '*.map', '*.lock',
    ])


class HardcodedConfig(BaseModel):
    """Hardcoded scanner settings."""
    whitelist: list[str] = Field(default_factory=list)


class N1Config(BaseModel):
    """N+1 scanner settings."""
    whitelist: list[str] = Field(default_factory=list)


class RiskConfig(BaseModel):
    """Regression risk settings."""
    churn_months: int = 3
    critical_threshold: int = 70
    high_threshold: int = 50
    medium_threshold: int = 30


class ModulesConfig(BaseModel):
    """Custom module mappings."""
    custom: dict[str, list[str]] = Field(default_factory=dict)


class LCAConfig(BaseModel):
    """Top-level LCA configuration."""
    project_name: str = ""
    project_root: str = "."
    health: HealthConfig = Field(default_factory=HealthConfig)
    scan: ScanConfig = Field(default_factory=ScanConfig)
    hardcoded: HardcodedConfig = Field(default_factory=HardcodedConfig)
    n1: N1Config = Field(default_factory=N1Config)
    risk: RiskConfig = Field(default_factory=RiskConfig)
    modules: ModulesConfig = Field(default_factory=ModulesConfig)

    _config_path: str = ""

    @property
    def config_path(self) -> str:
        return self._config_path


def _deep_get(d: dict, *keys: str, default: Any = None) -> Any:
    """Safely get nested dict value."""
    for key in keys:
        if isinstance(d, dict):
            d = d.get(key, default)
        else:
            return default
    return d


def load_config(project_root: str | Path | None = None) -> LCAConfig:
    """Load LCA configuration from .lca.toml.

    Searches for .lca.toml in:
      1. Provided project_root
      2. Current working directory
      3. Parent directories (up to 5 levels)

    Returns default config if no file found.
    """
    config = LCAConfig()

    # Find .lca.toml
    search_dirs = []
    if project_root:
        search_dirs.append(Path(project_root))
    search_dirs.append(Path.cwd())

    toml_path = None
    for start_dir in search_dirs:
        current = start_dir.resolve()
        for _ in range(5):
            candidate = current / '.lca.toml'
            if candidate.exists():
                toml_path = candidate
                break
            parent = current.parent
            if parent == current:
                break
            current = parent
        if toml_path:
            break

    if not toml_path:
        return config

    # Parse TOML
    try:
        import tomllib
    except ImportError:
        try:
            import tomli as tomllib  # type: ignore
        except ImportError:
            # Python < 3.11 without tomli — fall back to basic parser
            return _load_basic(toml_path, config)

    try:
        with open(toml_path, 'rb') as f:
            data = tomllib.load(f)
    except Exception:
        return config

    return _apply_config(data, config, str(toml_path))


def _apply_config(data: dict, config: LCAConfig, path: str) -> LCAConfig:
    """Apply parsed TOML data to config object."""
    try:
        new_config = LCAConfig.model_validate(data)
        new_config._config_path = path
        return new_config
    except ValidationError as e:
        from rich.console import Console
        console = Console()
        console.print(f"[red]Configuration Error in {path}:[/red]")
        for error in e.errors():
            loc = ".".join(str(l) for l in error["loc"])
            console.print(f"  - [yellow]{loc}[/yellow]: {error['msg']}")
        console.print("[dim]Falling back to default configuration.[/dim]")
        config._config_path = path
        return config


def _load_basic(toml_path: Path, config: LCAConfig) -> LCAConfig:
    """Basic TOML parser for Python < 3.11 without tomli.

    Only handles flat key=value and simple arrays. Good enough for
    whitelist entries and basic settings.
    """
    import re

    config._config_path = str(toml_path)
    try:
        content = toml_path.read_text(encoding='utf-8')
    except Exception:
        return config

    current_section = ''
    for line in content.split('\n'):
        line = line.strip()
        if not line or line.startswith('#'):
            continue

        # Section header
        section_match = re.match(r'^\[(.+)]$', line)
        if section_match:
            current_section = section_match.group(1)
            continue

        # Key = value
        kv_match = re.match(r'^(\w+)\s*=\s*(.+)$', line)
        if not kv_match:
            continue

        key = kv_match.group(1)
        value = kv_match.group(2).strip()

        # Parse value
        if value.startswith('['):
            # Simple array
            items = re.findall(r'"([^"]*)"', value)
            parsed = items
        elif value.startswith('"'):
            parsed = value.strip('"')
        elif value in ('true', 'false'):
            parsed = value == 'true'
        else:
            try:
                parsed = float(value) if '.' in value else int(value)
            except ValueError:
                parsed = value

        # Apply to config
        if current_section == 'hardcoded' and key == 'whitelist':
            config.hardcoded.whitelist = parsed
        elif current_section == 'n1' and key == 'whitelist':
            config.n1.whitelist = parsed
        elif current_section == 'risk':
            if key == 'churn_months':
                config.risk.churn_months = parsed
            elif key == 'critical_threshold':
                config.risk.critical_threshold = parsed

    return config


def generate_default_config() -> str:
    """Generate a default .lca.toml file content."""
    return '''# LCA Configuration
# Place this file at your project root as .lca.toml

[project]
name = ""
root = "."

[health]
complexity_weight = 0.30
security_weight = 0.25
dead_code_weight = 0.15
n1_weight = 0.15
hardcoded_weight = 0.10
smell_weight = 0.05

[health.thresholds]
grade_a = 90
grade_b = 75
grade_c = 60
grade_d = 40

[scan]
exclude_dirs = ["vendor", "node_modules", ".git", "tests", ".venv"]
exclude_files = ["*.min.js", "*.map"]

[hardcoded]
# Known-safe hardcoded values to suppress (matched against finding text)
whitelist = []

[n1]
# N+1 patterns to suppress
whitelist = []

[risk]
churn_months = 3
critical_threshold = 70
high_threshold = 50
medium_threshold = 30
'''
