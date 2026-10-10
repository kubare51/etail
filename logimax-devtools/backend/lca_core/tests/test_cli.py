import pytest
from typer.testing import CliRunner
from lca.cli import app
from lca import __version__

runner = CliRunner()

def test_version():
    """Test the --version flag."""
    result = runner.invoke(app, ["--version"])
    assert result.exit_code == 0
    assert f"LCA v{__version__}" in result.stdout

def test_help():
    """Test the --help flag."""
    result = runner.invoke(app, ["--help"])
    assert result.exit_code == 0
    assert "Logimax Code Analyzer" in result.stdout

def test_baseline_help():
    """Test the baseline --help flag."""
    result = runner.invoke(app, ["baseline", "--help"])
    assert result.exit_code == 0
    assert "Manage and compare health baselines" in result.stdout
    assert "save" in result.stdout
    assert "diff" in result.stdout
