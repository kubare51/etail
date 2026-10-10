"""
Test Coverage Analyzer for LCA.

Parses the Playwright E2E test suite to produce a coverage map:
  - Which controllers/views are tested
  - Which page objects exist and what URLs they cover
  - Test count per module
  - Gap analysis: untested controllers/models
  - YAML data coverage

Works with the existing LCA index to cross-reference:
  test page objects → navigate() URLs → PHP controllers → model methods

Usage:
  CLI:   lca test-coverage --tests-dir tests/ --index lca_index.json
  MCP:   get_test_coverage tool
"""

from __future__ import annotations

import re
import ast
from pathlib import Path
from dataclasses import dataclass, field
from typing import Optional


@dataclass
class PageObjectInfo:
    """Parsed page object metadata."""
    name: str                          # e.g. "BillingPage"
    file: str                          # e.g. "tests/pages/billing_page.py"
    urls: list[str] = field(default_factory=list)       # navigate() targets
    methods: list[str] = field(default_factory=list)    # public methods
    selectors: list[str] = field(default_factory=list)  # XPath/CSS selectors
    has_visual_regression: bool = False
    has_calc_engine: bool = False


@dataclass
class TestFileInfo:
    """Parsed test file metadata."""
    name: str                          # e.g. "test_billing"
    file: str
    test_class: str = ""               # e.g. "TestBilling"
    test_count: int = 0
    markers: list[str] = field(default_factory=list)    # pytest markers
    page_object: str = ""              # which page object it imports
    data_file: str = ""                # which YAML it uses


@dataclass
class CoverageResult:
    """Full test coverage analysis result."""
    page_objects: list[PageObjectInfo]
    test_files: list[TestFileInfo]
    yaml_files: list[str]
    total_tests: int = 0
    total_pages: int = 0
    total_yaml: int = 0
    # Cross-ref with LCA index
    tested_controllers: list[str] = field(default_factory=list)
    untested_controllers: list[str] = field(default_factory=list)
    tested_urls: list[str] = field(default_factory=list)
    url_to_controller: dict = field(default_factory=dict)
    coverage_pct: float = 0.0


class TestCoverageAnalyzer:
    """Analyzes the Playwright E2E test suite for coverage metrics."""

    def __init__(self, tests_dir: str, index_data: dict = None):
        self.tests_dir = Path(tests_dir)
        self.index_data = index_data  # Optional LCA index for cross-ref
        self._page_objects: list[PageObjectInfo] = []
        self._test_files: list[TestFileInfo] = []
        self._yaml_files: list[str] = []

    def analyze(self) -> CoverageResult:
        """Run full coverage analysis."""
        self._scan_page_objects()
        self._scan_test_files()
        self._scan_yaml_files()

        result = CoverageResult(
            page_objects=self._page_objects,
            test_files=self._test_files,
            yaml_files=self._yaml_files,
            total_tests=sum(t.test_count for t in self._test_files),
            total_pages=len(self._page_objects),
            total_yaml=len(self._yaml_files),
        )

        # Collect all URLs from page objects
        result.tested_urls = []
        for po in self._page_objects:
            result.tested_urls.extend(po.urls)

        # Cross-ref with LCA index if available
        if self.index_data:
            result = self._cross_reference(result)

        return result

    def _scan_page_objects(self):
        """Parse all page object files."""
        pages_dir = self.tests_dir / "pages"
        if not pages_dir.exists():
            return

        for py_file in sorted(pages_dir.glob("*_page.py")):
            if py_file.name == "base_page.py":
                continue
            po = self._parse_page_object(py_file)
            if po:
                self._page_objects.append(po)

    def _parse_page_object(self, file_path: Path) -> Optional[PageObjectInfo]:
        """Parse a single page object file."""
        try:
            content = file_path.read_text(encoding="utf-8")
            tree = ast.parse(content)
        except Exception:
            return None

        po = PageObjectInfo(
            name="",
            file=str(file_path.relative_to(self.tests_dir.parent)),
        )

        for node in ast.walk(tree):
            # Find the class name
            if isinstance(node, ast.ClassDef) and node.name != "BasePage":
                po.name = node.name

                for item in node.body:
                    # Class-level string constants (selectors)
                    if isinstance(item, ast.Assign):
                        for target in item.targets:
                            if isinstance(target, ast.Name):
                                if isinstance(item.value, ast.Constant) and isinstance(item.value.value, str):
                                    val = item.value.value
                                    if val.startswith("//") or val.startswith("#"):
                                        po.selectors.append(f"{target.id}: {val[:80]}")

                    # Methods
                    if isinstance(item, ast.FunctionDef):
                        if not item.name.startswith("_"):
                            po.methods.append(item.name)

        # Extract URLs from navigate() calls
        url_pattern = re.compile(r'self\.navigate\(["\'](.+?)["\']\)')
        for match in url_pattern.finditer(content):
            po.urls.append(match.group(1))

        # Check for visual regression
        po.has_visual_regression = "to_have_screenshot" in content

        # Check for calculation engine
        calc_keywords = ["def calc_", "taxable", "payable", "net_weight", "pure_weight"]
        po.has_calc_engine = any(kw in content.lower() for kw in calc_keywords)

        return po if po.name else None

    def _scan_test_files(self):
        """Parse all test files."""
        tests_dir = self.tests_dir / "tests"
        if not tests_dir.exists():
            return

        for py_file in sorted(tests_dir.glob("test_*.py")):
            tf = self._parse_test_file(py_file)
            if tf:
                self._test_files.append(tf)

    def _parse_test_file(self, file_path: Path) -> Optional[TestFileInfo]:
        """Parse a single test file."""
        try:
            content = file_path.read_text(encoding="utf-8")
            tree = ast.parse(content)
        except Exception:
            return None

        tf = TestFileInfo(
            name=file_path.stem,
            file=str(file_path.relative_to(self.tests_dir.parent)),
        )

        # Count test methods and extract class
        for node in ast.walk(tree):
            if isinstance(node, ast.ClassDef):
                tf.test_class = node.name
                for item in node.body:
                    if isinstance(item, ast.FunctionDef) and item.name.startswith("test_"):
                        tf.test_count += 1

        # Extract markers
        marker_pattern = re.compile(r'@pytest\.mark\.(\w+)')
        tf.markers = list(set(marker_pattern.findall(content)))

        # Extract page object import
        import_pattern = re.compile(r'from pages\.(\w+) import (\w+)')
        match = import_pattern.search(content)
        if match:
            tf.page_object = match.group(2)

        # Extract YAML data file
        data_pattern = re.compile(r'load_test_data\(["\'](\w+)["\']\)')
        match = data_pattern.search(content)
        if match:
            tf.data_file = match.group(1)

        return tf if tf.test_count > 0 else None

    def _scan_yaml_files(self):
        """List all YAML data files."""
        data_dir = self.tests_dir / "data"
        if not data_dir.exists():
            return
        self._yaml_files = sorted([
            f.stem for f in data_dir.glob("*.yml")
        ])

    def _cross_reference(self, result: CoverageResult) -> CoverageResult:
        """Cross-reference test URLs with LCA index to find untested controllers."""
        functions = self.index_data.get("functions", [])

        # Build set of controller methods from index
        all_controllers = set()
        for func in functions:
            name = func.get("name", "")
            file_path = func.get("file", "")
            if "controllers" in file_path and "::" in name:
                all_controllers.add(name)

        # Build URL → controller mapping
        # CI3 URL pattern: index.php/{module}/{controller}/{method}
        tested_controllers = set()
        for url in result.tested_urls:
            parts = url.replace("index.php/", "").split("/")
            if len(parts) >= 2:
                # Try to match against indexed controllers
                controller_method = parts[-1] if len(parts) >= 3 else ""
                for ctrl in all_controllers:
                    ctrl_lower = ctrl.lower()
                    if any(p.lower() in ctrl_lower for p in parts if len(p) > 3):
                        tested_controllers.add(ctrl)

        result.tested_controllers = sorted(tested_controllers)
        result.untested_controllers = sorted(all_controllers - tested_controllers)

        if all_controllers:
            result.coverage_pct = round(
                len(tested_controllers) / len(all_controllers) * 100, 1
            )

        return result

    def to_dict(self) -> dict:
        """Serialize for JSON output."""
        result = self.analyze()
        return {
            "summary": {
                "total_page_objects": result.total_pages,
                "total_test_files": len(result.test_files),
                "total_tests": result.total_tests,
                "total_yaml_files": result.total_yaml,
                "coverage_pct": result.coverage_pct,
                "visual_regression_points": sum(
                    1 for p in result.page_objects if p.has_visual_regression
                ),
                "calc_engines": sum(
                    1 for p in result.page_objects if p.has_calc_engine
                ),
            },
            "page_objects": [
                {
                    "name": p.name,
                    "file": p.file,
                    "urls": p.urls,
                    "method_count": len(p.methods),
                    "selector_count": len(p.selectors),
                    "visual_regression": p.has_visual_regression,
                    "calc_engine": p.has_calc_engine,
                }
                for p in result.page_objects
            ],
            "test_files": [
                {
                    "name": t.name,
                    "file": t.file,
                    "class": t.test_class,
                    "test_count": t.test_count,
                    "markers": t.markers,
                    "page_object": t.page_object,
                    "data_file": t.data_file,
                }
                for t in result.test_files
            ],
            "yaml_files": result.yaml_files,
            "tested_urls": result.tested_urls,
            "tested_controllers": result.tested_controllers,
            "untested_controllers": result.untested_controllers[:50],
        }
