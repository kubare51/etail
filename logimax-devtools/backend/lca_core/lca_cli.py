#!/usr/bin/env python3
"""
LCA CLI — Logimax Code Analysis Command Line Interface
========================================================
Single entry point to discover and run all LCA tools.

Usage:
    python lca_cli.py --list                          # List all tools
    python lca_cli.py complexity --module billing      # Run a tool
    python lca_cli.py dashboard --module billing       # Run health dashboard
    python lca_cli.py --help                           # Help
"""

import argparse
import sys
import os
from pathlib import Path

TOOLS = {
    'risk': {
        'script': 'impact_analyzer.py',
        'name': 'Risk Score (C1)',
        'desc': 'PR risk scoring 0-100 with review gates',
        'args': '--changed-files',
    },
    'dead-code': {
        'script': 'dead_code_detector.py',
        'name': 'Dead Code Detector (C2)',
        'desc': 'Find functions with zero callers',
    },
    'sql-map': {
        'script': 'sql_code_mapper.py',
        'name': 'SQL-to-Code Mapper (C3)',
        'desc': 'Map database tables to PHP functions',
    },
    'duplicates': {
        'script': 'duplicate_finder.py',
        'name': 'Duplicate Finder (C4)',
        'desc': 'Find 80%+ similar functions (copy-paste)',
    },
    'auto-brain': {
        'script': 'auto_brain.py',
        'name': 'Auto Module Brain (C5)',
        'desc': 'Generate 7 Module Brain docs from code',
    },
    'flow-maps': {
        'script': 'flow_map.py',
        'name': 'Flow Maps (C6)',
        'desc': 'Generate Mermaid onboarding diagrams',
    },
    'client-diff': {
        'script': 'cross_client_diff.py',
        'name': 'Cross-Client Diff (C7)',
        'desc': 'Compare two client codebases at function level',
    },
    'complexity': {
        'script': 'complexity_analyzer.py',
        'name': 'Complexity Analyzer (L1)',
        'desc': 'Cyclomatic complexity + A-F ratings',
    },
    'security': {
        'script': 'security_scanner.py',
        'name': 'Security Scanner (L2)',
        'desc': '12-rule SAST (SQL injection, XSS, etc)',
    },
    'n1': {
        'script': 'n1_detector.py',
        'name': 'N+1 Detector (L3)',
        'desc': 'Find DB calls inside loops',
    },
    'hardcoded': {
        'script': 'hardcoded_scanner.py',
        'name': 'Hardcoded Scanner (L5)',
        'desc': 'Magic numbers, URLs, IPs, dates',
    },
    'dashboard': {
        'script': 'health_dashboard.py',
        'name': 'Health Dashboard (L4)',
        'desc': 'Unified 0-100 score from all tools',
    },
}


def list_tools():
    """Print all available tools."""
    print('=' * 70)
    print('  Logimax Code Analysis (LCA) Suite')
    print('  12 tools for static analysis of CI3 codebases')
    print('=' * 70)
    print()

    print(f'  {"Command":<16} {"Tool":<30} {"Description"}')
    print(f'  {"-"*15:<16} {"-"*29:<30} {"-"*35}')

    for cmd, info in TOOLS.items():
        print(f'  {cmd:<16} {info["name"]:<30} {info["desc"]}')

    print()
    print('Usage:')
    print('  python lca_cli.py <command> --project-root <path> [--module <name>]')
    print()
    print('Examples:')
    print('  python lca_cli.py complexity --project-root . --module billing')
    print('  python lca_cli.py security --project-root . --module estimation')
    print('  python lca_cli.py dashboard --project-root . --module billing')
    print('  python lca_cli.py dashboard --project-root . --all')
    print()
    print('Run tests:')
    print('  python test_lca.py')
    print()


def main():
    if len(sys.argv) == 1 or '--list' in sys.argv or '-l' in sys.argv:
        list_tools()
        sys.exit(0)

    command = sys.argv[1]
    if command in ('-h', '--help', 'help'):
        list_tools()
        sys.exit(0)

    if command not in TOOLS:
        print(f'Unknown command: {command}')
        print(f'Available: {", ".join(TOOLS.keys())}')
        sys.exit(1)

    # Delegate to the actual script
    tool = TOOLS[command]
    script = os.path.join(os.path.dirname(__file__), tool['script'])
    remaining_args = sys.argv[2:]

    # Re-exec the target script with remaining args
    sys.argv = [script] + remaining_args
    exec(open(script, encoding='utf-8').read(), {'__name__': '__main__', '__file__': script})


if __name__ == '__main__':
    main()
