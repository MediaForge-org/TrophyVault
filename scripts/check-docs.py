#!/usr/bin/env python3
"""Documentation checks that need no database: relative Markdown links, anchors and Mermaid block structure.

Usage: python3 scripts/check-docs.py        (run from anywhere; exits non-zero on problems)
The Mermaid *syntax* is parsed separately by scripts/check-mermaid.mjs (needs Node).
"""
import glob
import os
import re
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
FILES = sorted(glob.glob(os.path.join(ROOT, 'docs', '**', '*.md'), recursive=True)) + [
    os.path.join(ROOT, 'README.md'),
    os.path.join(ROOT, 'CLAUDE.md'),
]


def slug(heading: str) -> str:
    """GitHub-style anchor for a heading."""
    text = re.sub(r'\[([^\]]*)\]\([^)]*\)', r'\1', heading)  # [text](url) -> text
    text = re.sub(r'[`*_]', '', text.strip().lower())
    text = re.sub(r'[^\w\- ]', '', text)
    return text.replace(' ', '-')


def anchors(path: str) -> set[str]:
    found = set()
    in_code = False
    for line in open(path, encoding='utf-8'):
        if line.startswith('```'):
            in_code = not in_code
        if not in_code:
            m = re.match(r'#{1,6}\s+(.*)', line)
            if m:
                found.add(slug(m.group(1)))
    return found


problems = []
for f in FILES:
    text = open(f, encoding='utf-8').read()
    body = re.sub(r'```.*?```', '', text, flags=re.S)  # ignore links inside code fences
    for target in re.findall(r'\]\(([^)\s]+)\)', body):
        if target.startswith(('http://', 'https://', 'mailto:')):
            continue
        path, _, anchor = target.partition('#')
        resolved = os.path.normpath(os.path.join(os.path.dirname(f), path)) if path else f
        rel = os.path.relpath(f, ROOT)
        if not os.path.exists(resolved):
            problems.append(f'{rel}: broken link {target}')
        elif anchor and resolved.endswith('.md') and anchor not in anchors(resolved):
            problems.append(f'{rel}: missing anchor #{anchor} in {os.path.relpath(resolved, ROOT)}')
    for i, block in enumerate(re.findall(r'```mermaid\n(.*?)```', text, flags=re.S), 1):
        if not block.lstrip().startswith('erDiagram'):
            problems.append(f'{os.path.relpath(f, ROOT)}: mermaid block {i} does not start with erDiagram')

if problems:
    print('\n'.join(problems))
    sys.exit(1)
print(f'docs ok: {len(FILES)} files, links and anchors resolve')
