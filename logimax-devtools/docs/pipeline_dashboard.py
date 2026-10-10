#!/usr/bin/env python3
"""
Pipeline Dashboard — State Generator
=====================================
Reads git state, changeset files, task artifacts, and workflow state.
Outputs pipeline-state.json for the HTML dashboard to consume.

Usage:
    python pipeline_dashboard.py                # Generate JSON + open browser
    python pipeline_dashboard.py --json-only    # Generate JSON only
    python pipeline_dashboard.py --serve        # Generate + start local server
"""

import json
import os
import subprocess
import sys
import webbrowser
from datetime import datetime
from pathlib import Path


def run_git(args, cwd=None):
    """Run a git command and return stdout."""
    try:
        result = subprocess.run(
            ['git'] + args,
            capture_output=True, text=True, cwd=cwd, timeout=10
        )
        return result.stdout.strip() if result.returncode == 0 else ''
    except Exception:
        return ''


def find_project_root():
    """Walk up from CWD to find .git directory."""
    path = Path(os.getcwd())
    while path != path.parent:
        if (path / '.git').exists():
            return str(path)
        path = path.parent
    return os.getcwd()


def get_git_state(root):
    """Gather git repository state."""
    branch = run_git(['branch', '--show-current'], root)
    last_commit = run_git(['log', '-1', '--format=%H|||%s|||%an|||%ar|||%aI'], root)
    status_lines = run_git(['status', '--short'], root).split('\n')
    status_lines = [l for l in status_lines if l.strip()]

    modified = [l[3:].strip() for l in status_lines if l[:2].strip() in ('M', 'MM', 'AM')]
    staged = [l[3:].strip() for l in status_lines if l[0] in ('A', 'M', 'R', 'D') and l[0] != '?']
    untracked = [l[3:].strip() for l in status_lines if l.startswith('??')]
    deleted = [l[3:].strip() for l in status_lines if 'D' in l[:2]]

    # Commits ahead of origin
    ahead_log = run_git(['log', '--oneline', f'origin/{branch}..HEAD'], root) if branch else ''
    ahead = len([l for l in ahead_log.split('\n') if l.strip()]) if ahead_log else 0

    # Parse last commit
    commit_info = {}
    if last_commit and '|||' in last_commit:
        parts = last_commit.split('|||')
        commit_info = {
            'hash': parts[0][:7],
            'hash_full': parts[0],
            'message': parts[1] if len(parts) > 1 else '',
            'author': parts[2] if len(parts) > 2 else '',
            'relative': parts[3] if len(parts) > 3 else '',
            'date': parts[4] if len(parts) > 4 else '',
        }

    # Branch type
    branch_type = 'other'
    if branch:
        if branch.startswith('bugfix/'):
            branch_type = 'bugfix'
        elif branch.startswith('feature/'):
            branch_type = 'feature'
        elif branch.startswith('hotfix/'):
            branch_type = 'hotfix'
        elif branch in ('PRODUCTION', 'QA', 'support', 'Retail_1.1.1.0001'):
            branch_type = 'environment'

    # Recent commits (last 10)
    recent_log = run_git(['log', '-10', '--format=%h|||%s|||%an|||%ar'], root)
    recent_commits = []
    if recent_log:
        for line in recent_log.split('\n'):
            if '|||' in line:
                parts = line.split('|||')
                recent_commits.append({
                    'hash': parts[0],
                    'message': parts[1] if len(parts) > 1 else '',
                    'author': parts[2] if len(parts) > 2 else '',
                    'relative': parts[3] if len(parts) > 3 else '',
                })

    return {
        'branch': branch,
        'branch_type': branch_type,
        'last_commit': commit_info,
        'recent_commits': recent_commits,
        'modified': modified,
        'staged': staged,
        'untracked': untracked,
        'deleted': deleted,
        'modified_count': len(modified),
        'staged_count': len(staged),
        'untracked_count': len(untracked),
        'total_changes': len(status_lines),
        'ahead_of_origin': ahead,
    }


def get_changeset_state(root):
    """Read .changes/ directory."""
    changes_dir = os.path.join(root, '.changes')
    current_file = os.path.join(changes_dir, 'current.json')
    history_dir = os.path.join(changes_dir, 'history')

    current = None
    if os.path.exists(current_file):
        try:
            with open(current_file, 'r', encoding='utf-8') as f:
                current = json.load(f)
        except Exception:
            pass

    history = []
    if os.path.exists(history_dir):
        for fname in sorted(os.listdir(history_dir), reverse=True)[:20]:
            fpath = os.path.join(history_dir, fname)
            try:
                with open(fpath, 'r', encoding='utf-8') as f:
                    entry = json.load(f)
                    entry['_filename'] = fname
                    history.append(entry)
            except Exception:
                pass

    return {
        'has_current': current is not None,
        'current': current,
        'history': history,
        'history_count': len(history),
    }


def get_task_state(root):
    """Read task.md and implementation_plan.md if they exist."""
    task_data = {'exists': False, 'total': 0, 'done': 0, 'in_progress': 0, 'remaining': 0, 'items': []}
    plan_data = {'exists': False, 'summary': ''}

    # Check common locations for task.md
    task_paths = [
        os.path.join(root, 'task.md'),
        os.path.join(root, '.planning', 'task.md'),
    ]

    # Also check Antigravity artifact directory pattern
    home = os.path.expanduser('~')
    brain_dir = os.path.join(home, '.gemini', 'antigravity-ide', 'brain')
    if os.path.exists(brain_dir):
        for conv_dir in os.listdir(brain_dir):
            t = os.path.join(brain_dir, conv_dir, 'task.md')
            if os.path.exists(t):
                # Use the most recently modified one
                task_paths.insert(0, t)

    for tp in task_paths:
        if os.path.exists(tp):
            try:
                with open(tp, 'r', encoding='utf-8') as f:
                    content = f.read()
                task_data['exists'] = True
                task_data['path'] = tp

                for line in content.split('\n'):
                    line = line.strip()
                    if line.startswith('- [x]') or line.startswith('- [X]'):
                        task_data['done'] += 1
                        task_data['total'] += 1
                        task_data['items'].append({'text': line[6:].strip(), 'status': 'done'})
                    elif line.startswith('- [/]'):
                        task_data['in_progress'] += 1
                        task_data['total'] += 1
                        task_data['items'].append({'text': line[6:].strip(), 'status': 'in_progress'})
                    elif line.startswith('- [ ]'):
                        task_data['remaining'] += 1
                        task_data['total'] += 1
                        task_data['items'].append({'text': line[6:].strip(), 'status': 'todo'})
                break
            except Exception:
                pass

    return {'task': task_data, 'plan': plan_data}


def get_hooks_state(root):
    """Check installed git hooks."""
    hooks_dir = os.path.join(root, '.git', 'hooks')
    hooks = []
    if os.path.exists(hooks_dir):
        for f in os.listdir(hooks_dir):
            if not f.endswith('.sample'):
                fpath = os.path.join(hooks_dir, f)
                if os.path.isfile(fpath):
                    size = os.path.getsize(fpath)
                    hooks.append({'name': f, 'size': size})
    return {'installed': hooks, 'count': len(hooks)}


def get_workflows_state(root):
    """Count available workflows."""
    wf_dir = os.path.join(root, '.agent', 'workflows')
    workflows = []
    if os.path.exists(wf_dir):
        for f in sorted(os.listdir(wf_dir)):
            if f.endswith('.md') and f != '00-INDEX.md':
                fpath = os.path.join(wf_dir, f)
                size = os.path.getsize(fpath)
                name = f.replace('.md', '')
                workflows.append({'name': name, 'file': f, 'size': size})
    return {'workflows': workflows, 'count': len(workflows)}


def get_ci_workflows_state(root):
    """Count GitHub Actions workflows."""
    ci_dir = os.path.join(root, '.github', 'workflows')
    ci = []
    if os.path.exists(ci_dir):
        for f in sorted(os.listdir(ci_dir)):
            if f.endswith('.yml') or f.endswith('.yaml'):
                ci.append({'name': f.replace('.yml', '').replace('.yaml', ''), 'file': f})
    return {'workflows': ci, 'count': len(ci)}


def determine_phase(git, changeset, task):
    """Determine current pipeline phase."""
    cs = changeset.get('current')

    if changeset['has_current']:
        status = cs.get('status', 'planned') if cs else 'planned'
        if status == 'ready':
            return {'phase': 'READY_TO_COMMIT', 'icon': '📦', 'label': 'Ready to Commit', 'color': '#10b981'}
        elif status == 'in-progress':
            return {'phase': 'CODING', 'icon': '💻', 'label': 'Coding', 'color': '#f59e0b'}
        else:
            return {'phase': 'PLANNING', 'icon': '📐', 'label': 'Planning', 'color': '#8b5cf6'}

    if git['total_changes'] > 0:
        return {'phase': 'CODING', 'icon': '💻', 'label': 'Coding (Uncommitted Changes)', 'color': '#f59e0b'}

    if git['ahead_of_origin'] > 0:
        return {'phase': 'PR_READY', 'icon': '🔍', 'label': 'Ready for PR', 'color': '#06b6d4'}

    return {'phase': 'IDLE', 'icon': '⬜', 'label': 'Idle', 'color': '#6b7280'}


def get_next_actions(phase, git, changeset):
    """Suggest next actions based on phase."""
    actions = []
    p = phase['phase']

    if p == 'IDLE':
        actions.append({'text': 'Start a new task', 'command': '/get-requirement', 'type': 'primary'})
        actions.append({'text': 'Check for bugs', 'command': '/module-bug-audit', 'type': 'secondary'})
    elif p == 'PLANNING':
        actions.append({'text': 'Approve plan and start coding', 'command': '/fix-single-bug', 'type': 'primary'})
        actions.append({'text': 'Review the plan', 'command': '/plan-task', 'type': 'secondary'})
    elif p == 'CODING':
        actions.append({'text': 'Review changes', 'command': '/review-changes', 'type': 'primary'})
        actions.append({'text': 'Save and pause', 'command': '/pause-work', 'type': 'secondary'})
        actions.append({'text': 'Prepare commit', 'command': '/prepare-commit', 'type': 'secondary'})
    elif p == 'READY_TO_COMMIT':
        actions.append({'text': 'Stage and commit', 'command': 'git add . && git commit', 'type': 'primary'})
        actions.append({'text': 'Review first', 'command': '/review-changes', 'type': 'secondary'})
    elif p == 'PR_READY':
        actions.append({'text': 'Push and create PR', 'command': 'git push', 'type': 'primary'})
        actions.append({'text': 'Check pipeline', 'command': '/pipeline-status', 'type': 'secondary'})

    return actions


def generate_state():
    """Generate complete pipeline state."""
    root = find_project_root()

    git = get_git_state(root)
    changeset = get_changeset_state(root)
    task = get_task_state(root)
    hooks = get_hooks_state(root)
    workflows = get_workflows_state(root)
    ci = get_ci_workflows_state(root)
    phase = determine_phase(git, changeset, task)
    actions = get_next_actions(phase, git, changeset)

    # Calculate progress
    progress = 0
    if task['task']['total'] > 0:
        progress = int((task['task']['done'] / task['task']['total']) * 100)
    elif changeset['has_current']:
        cs = changeset['current']
        if cs and cs.get('status') == 'ready':
            progress = 90
        elif cs and cs.get('status') == 'in-progress':
            progress = 50
        else:
            progress = 20

    state = {
        'generated_at': datetime.now().isoformat(),
        'project_root': root,
        'project_name': os.path.basename(root),
        'phase': phase,
        'progress': progress,
        'git': git,
        'changeset': changeset,
        'task': task,
        'hooks': hooks,
        'workflows': workflows,
        'ci': ci,
        'next_actions': actions,
    }

    # Write JSON
    output_path = os.path.join(root, 'logimax-devtools', 'docs', 'pipeline-state.json')
    os.makedirs(os.path.dirname(output_path), exist_ok=True)
    with open(output_path, 'w', encoding='utf-8') as f:
        json.dump(state, f, indent=2, ensure_ascii=False)

    print(f'Pipeline state written to: {output_path}')
    return output_path, state


def main():
    json_path, state = generate_state()

    if '--json-only' in sys.argv:
        return

    # Open dashboard
    dashboard = os.path.join(os.path.dirname(json_path), 'pipeline-dashboard.html')
    if os.path.exists(dashboard):
        webbrowser.open(f'file:///{dashboard.replace(os.sep, "/")}')
        print(f'Dashboard opened in browser')
    else:
        print(f'Dashboard HTML not found at: {dashboard}')
        print(f'JSON state available at: {json_path}')


if __name__ == '__main__':
    main()
