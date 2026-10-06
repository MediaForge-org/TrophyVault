#!/usr/bin/env python3
"""Static safety checks for the CI workflow and the compose file (no dependencies, no network).

Fails when CI could plausibly touch a development database or expose the data services:
  * every DB_DATABASE / POSTGRES_DB in the workflow must be a *_test database,
  * the workflow must never run destructive artisan commands or set the destructive opt-in,
  * the backend job must run with APP_ENV=testing,
  * docker-compose.yml may publish only the `web` service on the host, and only on loopback.
Usage: python3 scripts/check-ci.py [--selftest]
  --selftest  mutates temporary copies with known violations and verifies the checker catches every one.
"""
import os
import re
import sys

import shutil
import tempfile

REPO = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))


def check(ROOT: str) -> list[str]:
    problems: list[str] = []

    workflow = open(os.path.join(ROOT, '.github/workflows/ci.yml'), encoding='utf-8').read()

    for match in re.finditer(r'^\s*(DB_DATABASE|POSTGRES_DB):\s*["\']?([^"\'\s#]+)', workflow, flags=re.M):
        if not match.group(2).endswith('_test'):
            problems.append(f'ci.yml: {match.group(1)}={match.group(2)} is not a *_test database')

    for forbidden in ('migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe', 'TROPHYVAULT_ALLOW_DESTRUCTIVE_DB'):
        if forbidden in workflow:
            problems.append(f'ci.yml: must not contain "{forbidden}" (tests reset their own *_test database)')

    backend = re.search(r'^  backend:\n(.*?)^  \w[\w-]*:\n', workflow, flags=re.S | re.M)
    if not backend or not re.search(r'APP_ENV:\s*testing', backend.group(1)):
        problems.append('ci.yml: the backend job must set APP_ENV: testing')
    if not backend or 'DB_DATABASE:' not in backend.group(1):
        problems.append('ci.yml: the backend job must set DB_DATABASE explicitly')

    compose = open(os.path.join(ROOT, 'docker-compose.yml'), encoding='utf-8').read()
    services = re.split(r'^  (?=[a-z_]+:\n)', compose.split('\nservices:\n', 1)[1].split('\nnetworks:\n', 1)[0], flags=re.M)
    for block in services:
        name = block.split(':', 1)[0].strip()
        if 'ports:' in block:
            if name != 'web':
                problems.append(f'docker-compose.yml: service "{name}" publishes host ports; only "web" may')
            elif not re.search(r'127\.0\.0\.1:\$\{APP_PORT', block):
                problems.append('docker-compose.yml: "web" must publish on 127.0.0.1 only')

    example = open(os.path.join(ROOT, '.env.example'), encoding='utf-8').read()
    db = re.search(r'^DB_DATABASE=(\S+)', example, flags=re.M)
    if not db or db.group(1).endswith('_test'):
        problems.append('.env.example: DB_DATABASE must be the development database (not *_test)')
    if not re.search(r'^DB_TEST_USERNAME=\S+', example, flags=re.M):
        problems.append('.env.example: DB_TEST_USERNAME (restricted test role) is missing')
    if re.search(r'^TROPHYVAULT_ALLOW_DESTRUCTIVE_DB=\S', example, flags=re.M):
        problems.append('.env.example: TROPHYVAULT_ALLOW_DESTRUCTIVE_DB must never be preset')

    return problems


def selftest() -> int:
    workflow = '.github/workflows/ci.yml'
    mutations = {
        'dev database in the workflow': (workflow, lambda t: t.replace('DB_DATABASE: trophyvault_test', 'DB_DATABASE: trophyvault', 1), 'not a *_test database'),
        'destructive artisan command in CI': (workflow, lambda t: t.replace('composer install --no-interaction --no-progress --prefer-dist', 'composer install && php artisan migrate:fresh', 1), 'must not contain "migrate:fresh"'),
        'destructive opt-in in CI': (workflow, lambda t: t.replace('APP_ENV: testing', 'APP_ENV: testing\n      TROPHYVAULT_ALLOW_DESTRUCTIVE_DB: trophyvault', 1), 'TROPHYVAULT_ALLOW_DESTRUCTIVE_DB'),
        'backend job without APP_ENV=testing': (workflow, lambda t: t.replace('APP_ENV: testing', 'APP_ENV: local', 1), 'APP_ENV: testing'),
        'exposed redis': ('docker-compose.yml', lambda t: t.replace('      - redis_data:/data', '      - redis_data:/data\n    ports: ["6379:6379"]', 1), 'publishes host ports'),
        'web on all interfaces': ('docker-compose.yml', lambda t: t.replace('"127.0.0.1:${APP_PORT', '"0.0.0.0:${APP_PORT', 1), '127.0.0.1 only'),
        'test database as dev default': ('.env.example', lambda t: re.sub(r'^DB_DATABASE=\S+', 'DB_DATABASE=trophyvault_test', t, flags=re.M), 'development database'),
        'preset destructive opt-in': ('.env.example', lambda t: t + '\nTROPHYVAULT_ALLOW_DESTRUCTIVE_DB=trophyvault\n', 'never be preset'),
    }
    failures = 0
    for label, (relative, mutate, expected) in mutations.items():
        with tempfile.TemporaryDirectory() as tmp:
            for rel in (workflow, 'docker-compose.yml', '.env.example'):
                os.makedirs(os.path.dirname(os.path.join(tmp, rel)), exist_ok=True)
                shutil.copy(os.path.join(REPO, rel), os.path.join(tmp, rel))
            path = os.path.join(tmp, relative)
            original = open(path, encoding='utf-8').read()
            mutated = mutate(original)
            if mutated == original:
                print(f'SELFTEST BROKEN: mutation "{label}" did not change anything')
                failures += 1
                continue
            open(path, 'w', encoding='utf-8').write(mutated)
            found = check(tmp)
            if not any(expected in p for p in found):
                print(f'SELFTEST FAIL: "{label}" was not detected (expected "{expected}", got {found})')
                failures += 1
    if check(REPO):
        print('SELFTEST FAIL: the real repository has problems:', check(REPO))
        failures += 1
    print(f'selftest: {len(mutations) - failures if failures <= len(mutations) else 0}/{len(mutations)} violations detected')
    return 1 if failures else 0


if __name__ == '__main__':
    if '--selftest' in sys.argv:
        sys.exit(selftest())
    found = check(REPO)
    if found:
        print('\n'.join(found))
        sys.exit(1)
    print('ci/compose safety ok')
