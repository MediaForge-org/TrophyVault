#!/usr/bin/env python3
"""Regenerates docs/data-model/data-dictionary.md from the migrated test database.

Columns, keys, constraints and indexes come from the PostgreSQL catalog; the prose per table lives in the P dict below.
When you add a migration: migrate the test DB (make test-backend), add the prose entry, run this script, review the diff.
DocumentationConsistencyTest fails while docs and schema disagree.
"""
import json
import os
import re
import subprocess
import sys

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
SQL = '''
select json_build_object(
 'tables', (select json_agg(t) from (
   select c.relname as name,
     (select json_agg(json_build_object('name',a.attname,'type',format_type(a.atttypid,a.atttypmod),'null',not a.attnotnull,'default',pg_get_expr(d.adbin,d.adrelid)) order by a.attnum)
        from pg_attribute a left join pg_attrdef d on d.adrelid=a.attrelid and d.adnum=a.attnum
        where a.attrelid=c.oid and a.attnum>0 and not a.attisdropped) as columns,
     (select json_agg(json_build_object('type',con.contype,'def',pg_get_constraintdef(con.oid),'name',con.conname) order by con.contype, con.conname) from pg_constraint con where con.conrelid=c.oid) as constraints,
     (select json_agg(pg_get_indexdef(i.indexrelid) order by pg_get_indexdef(i.indexrelid)) from pg_index i where i.indrelid=c.oid and not i.indisprimary and not exists (select 1 from pg_constraint k where k.conindid=i.indexrelid and k.contype in ('u'))) as indexes
   from pg_class c join pg_namespace n on n.oid=c.relnamespace and n.nspname='public' where c.relkind='r' and c.relname not in ('migrations','failed_jobs') order by c.relname) t));
'''

# Reads the schema of the *test* database (migrated by the test suite / make test-backend).
result = subprocess.run(
    ['docker', 'compose', 'exec', '-T', 'postgres', 'sh', '-c', 'psql -U "$POSTGRES_USER" -d "${POSTGRES_DB}_test" -At'],
    input=SQL, text=True, capture_output=True, cwd=ROOT, check=False,
)
if result.returncode != 0 or not result.stdout.strip():
    sys.exit('Cannot read the schema: ' + result.stderr + '\nRun the stack and the backend tests first (make up && make test-backend).')
d = json.loads(result.stdout)
T={t['name']:t for t in d['tables']}
P={
'providers':('Sync / Providers (reference)','An information source (PSN, PSNProfiles, PowerPyx, PlayStationTrophies, community, manual). Says nothing about whether a connector exists.','Reference data seeded by its migration; changes via new migrations.','Identity of provenance and external ids.'),
'platforms':('Catalog (reference)','Our canonical platform vocabulary (`ps3`, `ps4`, `ps5`, `vita`, `psp`, `pc`). Provider platform names are mapped to these codes in adapters.','Reference data seeded by its migration.','`pc` exists for PlayStation PC trophy lists.'),
'regions':('Catalog (reference)','Release regions incl. the real values `global` and `unknown`.','Reference data seeded by its migration.','Never NULL: a release always has a region.'),
'users':('Identity','Local TrophyVault user (owner of personal data and PSN accounts). Authentication details are a later decision; the table is the multi-user anchor.','Hard delete; cascades to the user\'s PSN accounts and their progress.','Email unique case-insensitively (`lower(email)`).'),
'psn_accounts':('Identity','A linked PlayStation account, separate from the local user. Earned-trophy state hangs on it ([ADR 0012](../decisions/0012-account-owned-progress.md)).','Hard delete (cascade from user); cascades to its progress rows. Credentials are NOT stored here (planned `psn_account_credentials`).','`psn_account_id` is PSN\'s account id (opaque text), unique across all users. `profile` = non-secret profile data only.'),
'games':('Catalog','The work, independent of platform releases. Root of the catalog.','Retired via `retired_at`, never deleted while releases/sets exist (RESTRICT).','`slug` = stable human handle; `title`/`sort_title` for display/sorting; trigram index for substring search.'),
'editions':('Catalog','A marketing edition of one game (Standard, Deluxe, …). Never creates a separate game.','Follows its game; RESTRICT while releases reference it.','`UNIQUE (id, game_id)` exists only as the target of the composite FK from `game_releases`.'),
'game_releases':('Catalog','A concrete published variant of a game: optional edition, one region, n platforms, optional release date.','Retired via `retired_at`; FK RESTRICT to its game.','Composite FK `(edition_id, game_id)` keeps the edition inside the same game. Platforms via `game_release_platforms`.'),
'game_release_platforms':('Catalog','Platforms a release is published on (n:m, cross-gen releases).','Cascades with the release.','At least one platform per release is an application rule.'),
'game_relations':('Catalog','Relation between two games: `remake_of`, `remaster_of`, `sequel_to`, `collection_contains` (directed), `related` (symmetric, ordered pair).','Cascades with either game.','Unique per `(kind, unordered pair)`; no self relation. `prequel_to` is not stored.'),
'trophy_sets':('Trophies','An independent PlayStation trophy list; belongs to exactly one game; independent of releases and platforms.','Retired via `retired_at`; FK RESTRICT to game; RESTRICT while accounts hold it.','Provider ids (`npCommunicationId`) live in `external_identifiers`. `UNIQUE (id, game_id)` is the target of `game_release_trophy_sets`.'),
'trophy_set_platforms':('Trophies','Platform labels of a trophy list (n:m).','Cascades with the set.',''),
'game_release_trophy_sets':('Trophies ↔ Catalog','Which releases use which trophy list (n:m). Carries `game_id` so release and set are forced to belong to the same game.','Cascades with release or set.','Composite FKs to `game_releases(id, game_id)` and `trophy_sets(id, game_id)`.'),
'external_identifiers':('Catalog (cross-cutting)','Maps provider ids (provider, namespace, value) to a game, release or trophy set (exclusive arc with real FKs).','Retired via `retired_at`; cascades with the entity.','Exactly one entity column is set. Active `(provider, namespace, value)` is unique ([ADR 0009](../decisions/0009-external-identifiers.md)).'),
'trophy_groups':('Trophies','Structural group of a trophy list (`default`, `001`, …) plus TrophyVault\'s own classification.','Cascades with the set; deletion of the set is blocked while accounts hold it.','`set_key` = key inside the list. `classification`/`classification_origin` are TrophyVault-owned and independent of structure; `unknown` is a real value.'),
'trophies':('Trophies','One trophy definition inside its list and group. Look-alike trophies of different sets are separate rows.','Cascades with the set/group; RESTRICT while any account has earned it.','`UNIQUE (trophy_set_id, set_key)`; at most one platinum per set (partial unique index). `UNIQUE (id, trophy_set_id)` is the target of `psn_account_trophies`.'),
'trophy_set_relations':('Trophies','Relation between two trophy sets: stacks/equivalence (symmetric) and `autopop`/`partial_autopop` (directed).','Cascades with either set.','No self relation; one row per `(kind, unordered pair)`; a pair is either `autopop` or `partial_autopop`.'),
'trophy_relations':('Trophies','Relation between two trophies: `equivalent`/`similar`/`related` (symmetric), `replaces`/`autopop` (directed).','Cascades with either trophy.','Same pair rules as set relations.'),
'psn_account_trophy_sets':('Player progress','"This PSN account has this trophy list" (library membership + sync metadata).','Hard delete; cascades from the account; RESTRICT on the set.','No completion columns — completion is derived.'),
'psn_account_trophies':('Player progress','One row per **earned** trophy of a PSN account (absence = not earned).','Hard delete; cascades from the account (through its set membership); RESTRICT on the trophy definition.','`earned_at` NULL = earned, time unknown. Composite FKs guarantee trophy ∈ set ∈ account library.'),
}
out=['# Data dictionary (physical schema)','',
'The tables below exist in the database after M0.3. They are listed with the columns, keys, constraints and indexes **as migrated** (generated from the PostgreSQL catalog; `DocumentationConsistencyTest` fails if a table or column is missing here).',
'Tables that are part of the v1 logical model but not yet migrated are described in [`logical-model.md`](logical-model.md); their rules are in [ADR 0010](../decisions/0010-provenance-and-sync-model.md) and [ADR 0013](../decisions/0013-objectives-trackers-guides.md).','',
'Common to all tables: `id uuid` primary key with default `uuidv7()` (exceptions noted), `created_at`/`updated_at timestamptz` where the table owns rows. See [`conventions.md`](conventions.md).','',
'Delete policy abbreviations: **CASCADE** child follows parent, **RESTRICT** parent cannot be deleted while children exist.','']
order=['providers','platforms','regions','users','psn_accounts','games','editions','game_releases','game_release_platforms','game_relations','trophy_sets','trophy_set_platforms','game_release_trophy_sets','external_identifiers','trophy_groups','trophies','trophy_set_relations','trophy_relations','psn_account_trophy_sets','psn_account_trophies']
assert set(order)==set(T)
def short(df):
    df=re.sub(r"::(text|jsonb|character varying)","",df)
    return df
for name in order:
    t=T[name]; dom,purpose,life,notes=P[name]
    out+=[f'### `{name}`','',f'**Domain:** {dom} · **Purpose:** {purpose}','',f'**Lifecycle / deletion:** {life}']
    if notes: out+=['',f'**Notes:** {notes}']
    out+=['','| Column | Type | Null | Default |','| --- | --- | --- | --- |']
    for c in t['columns']:
        df=c['default'] or ''
        df=short(df).replace('|','\\|')
        out.append(f"| `{c['name']}` | {c['type']} | {'yes' if c['null'] else 'no'} | {('`'+df+'`') if df else ''} |")
    cons=t['constraints'] or []
    pk=[c for c in cons if c['type']=='p']; fk=[c for c in cons if c['type']=='f']; uq=[c for c in cons if c['type']=='u']; ck=[c for c in cons if c['type']=='c']
    out+=['']
    if pk: out.append('- **Primary key:** '+short(pk[0]['def']).replace('PRIMARY KEY ',''))
    for c in fk: out.append('- **FK:** '+short(c['def']).replace('FOREIGN KEY ','').replace(' REFERENCES ',' → '))
    for c in uq: out.append('- **Unique:** '+short(c['def']).replace('UNIQUE ',''))
    for c in ck: out.append('- **Check** `'+c['name']+'`: `'+short(c['def']).replace('CHECK ','')+'`')
    for ix in (t['indexes'] or []):
        m=re.match(r'CREATE (UNIQUE )?INDEX (\S+) ON public\.\S+ (.*)$',ix)
        if m: out.append(f"- **{'Unique index' if m.group(1) else 'Index'}** `{m.group(2)}`: `{short(m.group(3))}`")
    out.append('')
open(os.path.join(ROOT, 'docs/data-model/data-dictionary.md'), 'w').write('\n'.join(out) + '\n')
print('data dictionary written:', len(T), 'tables')
