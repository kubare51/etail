"""GitHub API integration for LCA workflow commands.

Handles PR creation, updates, and status checks using the GitHub REST API.
Requires GITHUB_TOKEN environment variable or .git credentials.
"""

from __future__ import annotations

import json
import os
import re
import subprocess
from pathlib import Path
from typing import Optional
from urllib.request import Request, urlopen
from urllib.error import HTTPError


def _get_github_token() -> Optional[str]:
    """Get GitHub token from environment, gh CLI, or git credential manager."""
    # 1. Environment variable (most common)
    token = os.environ.get('GITHUB_TOKEN') or os.environ.get('GH_TOKEN')
    if token:
        return token

    # 2. Try gh CLI auth token
    try:
        result = subprocess.run(
            ['gh', 'auth', 'token'],
            capture_output=True, text=True, timeout=5
        )
        if result.returncode == 0 and result.stdout.strip():
            return result.stdout.strip()
    except (FileNotFoundError, subprocess.TimeoutExpired):
        pass

    # 3. Try git credential manager (Windows credential store)
    try:
        cred_input = 'protocol=https\nhost=github.com\n\n'
        result = subprocess.run(
            ['git', 'credential', 'fill'],
            input=cred_input, capture_output=True, text=True, timeout=10
        )
        if result.returncode == 0:
            for line in result.stdout.splitlines():
                if line.startswith('password='):
                    return line.split('=', 1)[1].strip()
    except (FileNotFoundError, subprocess.TimeoutExpired):
        pass

    return None


def _parse_remote_url(project_root: Path) -> tuple[str, str]:
    """Extract owner/repo from git remote origin URL.

    Handles:
      - git@github.com:Owner/Repo.git
      - git@github.com-alias:Owner/Repo.git
      - https://github.com/Owner/Repo.git
    """
    try:
        result = subprocess.run(
            ['git', 'remote', 'get-url', 'origin'],
            capture_output=True, text=True, cwd=project_root, timeout=5
        )
        url = result.stdout.strip()
    except (FileNotFoundError, subprocess.TimeoutExpired):
        raise RuntimeError("Could not read git remote URL")

    # SSH: git@github.com:Owner/Repo.git or git@github.com-alias:Owner/Repo.git
    ssh_match = re.match(r'git@github\.com[^:]*:(.+?)/(.+?)(?:\.git)?$', url)
    if ssh_match:
        return ssh_match.group(1), ssh_match.group(2)

    # HTTPS: https://github.com/Owner/Repo.git
    https_match = re.match(r'https?://github\.com/(.+?)/(.+?)(?:\.git)?$', url)
    if https_match:
        return https_match.group(1), https_match.group(2)

    raise RuntimeError(f"Cannot parse GitHub owner/repo from remote URL: {url}")


def _get_current_branch(project_root: Path) -> str:
    """Get current git branch name."""
    result = subprocess.run(
        ['git', 'branch', '--show-current'],
        capture_output=True, text=True, cwd=project_root, timeout=5
    )
    branch = result.stdout.strip()
    if not branch:
        raise RuntimeError("Could not determine current branch (detached HEAD?)")
    return branch


def _github_api(method: str, endpoint: str, token: str, data: dict = None) -> dict:
    """Make a GitHub REST API request."""
    url = f"https://api.github.com{endpoint}"
    headers = {
        'Authorization': f'token {token}',
        'Accept': 'application/vnd.github.v3+json',
        'Content-Type': 'application/json',
        'User-Agent': 'LCA-CLI/2.0',
    }

    body = json.dumps(data).encode('utf-8') if data else None
    req = Request(url, data=body, headers=headers, method=method)

    try:
        with urlopen(req, timeout=30) as resp:
            return json.loads(resp.read().decode('utf-8'))
    except HTTPError as e:
        error_body = e.read().decode('utf-8') if e.fp else ''
        try:
            error_json = json.loads(error_body)
        except (json.JSONDecodeError, ValueError):
            error_json = {'message': error_body}
        raise RuntimeError(
            f"GitHub API error {e.code}: {error_json.get('message', error_body)}"
        ) from e


def find_existing_pr(
    owner: str, repo: str, head_branch: str, base_branch: str, token: str
) -> Optional[dict]:
    """Find an existing open PR for the given head→base."""
    endpoint = f"/repos/{owner}/{repo}/pulls?state=open&head={owner}:{head_branch}&base={base_branch}"
    prs = _github_api('GET', endpoint, token)
    return prs[0] if prs else None


def create_pr(
    owner: str, repo: str, title: str, body: str,
    head_branch: str, base_branch: str, token: str,
    draft: bool = False
) -> dict:
    """Create a new PR. Returns the PR data dict."""
    return _github_api('POST', f"/repos/{owner}/{repo}/pulls", token, {
        'title': title,
        'body': body,
        'head': head_branch,
        'base': base_branch,
        'draft': draft,
    })


def update_pr(
    owner: str, repo: str, pr_number: int,
    title: str, body: str, token: str
) -> dict:
    """Update an existing PR's title and body. Returns the updated PR data."""
    return _github_api('PATCH', f"/repos/{owner}/{repo}/pulls/{pr_number}", token, {
        'title': title,
        'body': body,
    })


def create_or_update_pr(
    project_root: Path,
    title: str,
    body: str,
    base_branch: str,
    draft: bool = False,
) -> dict:
    """Create or update a PR for the current branch.

    Returns dict with keys: action ('created'|'updated'), number, url, title
    """
    token = _get_github_token()
    if not token:
        raise RuntimeError(
            "No GitHub token found. Set GITHUB_TOKEN env var or run 'gh auth login'"
        )

    owner, repo = _parse_remote_url(project_root)
    head_branch = _get_current_branch(project_root)

    # Check for existing PR
    existing = find_existing_pr(owner, repo, head_branch, base_branch, token)

    if existing:
        # Update existing PR
        pr_number = existing['number']
        updated = update_pr(owner, repo, pr_number, title, body, token)
        return {
            'action': 'updated',
            'number': pr_number,
            'url': updated['html_url'],
            'title': updated['title'],
        }
    else:
        # Create new PR
        created = create_pr(owner, repo, title, body, head_branch, base_branch, token, draft)
        return {
            'action': 'created',
            'number': created['number'],
            'url': created['html_url'],
            'title': created['title'],
        }
