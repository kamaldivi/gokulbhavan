"""
map_scriptures_from_candidates.py
──────────────────────────────────
Generates data/update_sloka_scripture_ids_v2.sql using:
  - data/scripture.csv          (id, name, short_title — 159 entries)
  - data/scripture_extracted_candidates.csv  (name, count, pipe-sep refs)
  - data/sloka_data.csv         (sloka rows with scripture_reference)

Matches are done by exact scripture_reference string comparison.
Entries in candidates not found in scripture.csv are flagged as skipped.

Usage:
    cd /path/to/gokulbhavan
    python3 scripts/map_scriptures_from_candidates.py
"""

import csv
import re
import unicodedata
from collections import defaultdict
from pathlib import Path

ROOT           = Path(__file__).parent.parent
CSV_PATH       = ROOT / 'data' / 'sloka_data.csv'
SCRIPTURE_CSV  = ROOT / 'data' / 'scripture.csv'
CANDIDATES_CSV = ROOT / 'data' / 'scripture_extracted_candidates.csv'
OUTPUT_SQL     = ROOT / 'data' / 'update_sloka_scripture_ids_v2.sql'


def norm_name(s: str) -> str:
    """Normalize a scripture name for fuzzy lookup."""
    nfd = unicodedata.normalize('NFD', s or '')
    s2  = ''.join(c for c in nfd if unicodedata.category(c) != 'Mn' and ord(c) < 128)
    s2  = re.sub(r"['\-\u2019]", ' ', s2)   # apostrophe / dash → space
    s2  = re.sub(r'[^\w\s]', ' ', s2)
    return re.sub(r'\s+', ' ', s2).strip().lower()


# Manual overrides: candidate name (normalised) → scripture_id
# Use when auto-matching fails or is ambiguous.
MANUAL = {
    # "Caitanya Caritamrita - Adi Lila" refs are bare "Ādi X.Y" verse cites
    'caitanya caritamrita adi lila': 12,
    # Some candidate names differ slightly from scripture.csv
    'navadvipa sataka': 105,       # "Navadvipa Sataka" = id 105
    'skanda purana': 131,          # normalisation handles diacritics
    'naradiya purana': 103,
}

# Entries that have no scripture ID and should be skipped
NO_ID_ENTRIES = {
    'agama sastras',
    'bilvamangala thakura',
    'caitanya mahaprabhu s bhagavata pramana hindi',
    'dusta mana',
    'govinda damodara madhaveti stotram',
    'krama sandarbha',
    'krishna dhyanam',
    'manu samhita',
    'mathara sruti',
    'purana vakya',
    'sankaracarya',
    'smrti vakya',
    'source unknown',
    'sridhara swami',
    'teachings of srila bhakti prajnana kesava gosvami',
    'yamunacarya',
    'yan kali rupa',
}


def load_scriptures() -> dict[str, int]:
    """Return normalized_name → id from scripture.csv."""
    name_to_id: dict[str, int] = {}
    with open(SCRIPTURE_CSV, newline='', encoding='utf-8-sig') as f:
        for row in csv.reader(f):
            if len(row) < 2:
                continue
            sid  = row[0].strip()
            name = row[1].strip()
            if sid.isdigit():
                name_to_id[norm_name(name)] = int(sid)
    return name_to_id


def load_sloka_refs() -> dict[str, list[int]]:
    """Return exact scripture_reference → [sloka_ids] from sloka_data.csv."""
    ref_to_ids: dict[str, list[int]] = defaultdict(list)
    with open(CSV_PATH, newline='', encoding='utf-8-sig') as f:
        for row in csv.DictReader(f):
            sloka_id = (row.get('id') or '').strip()
            ref      = (row.get('scripture_reference') or '').strip()
            if sloka_id.isdigit() and ref:
                ref_to_ids[ref].append(int(sloka_id))
    return ref_to_ids


def load_candidates() -> list[tuple[str, list[str]]]:
    """Return [(raw_name, [raw_refs]), ...] from candidates CSV."""
    rows = []
    with open(CANDIDATES_CSV, newline='', encoding='utf-8-sig') as f:
        for row in csv.DictReader(f):
            name     = (row.get('name') or '').strip()
            refs_raw = (row.get('raw_reference_examples') or '').strip()
            refs     = [r.strip() for r in refs_raw.split('|') if r.strip()]
            if name and refs:
                rows.append((name, refs))
    return rows


def main():
    scriptures    = load_scriptures()
    ref_to_ids    = load_sloka_refs()
    candidates    = load_candidates()

    matched_by_scr: dict[int, list[int]] = defaultdict(list)
    skipped_candidates: list[str] = []
    unresolved_refs: list[tuple[str, str]] = []   # (candidate_name, raw_ref)

    for cand_name, raw_refs in candidates:
        nn = norm_name(cand_name)

        # Resolve scripture ID
        if nn in NO_ID_ENTRIES:
            skipped_candidates.append(cand_name)
            continue

        sid = MANUAL.get(nn) or scriptures.get(nn)
        if sid is None:
            skipped_candidates.append(f'{cand_name!r}  [norm={nn!r}]')
            continue

        # Match each raw ref to sloka IDs
        for raw_ref in raw_refs:
            sloka_ids = ref_to_ids.get(raw_ref)
            if sloka_ids:
                matched_by_scr[sid].extend(sloka_ids)
            else:
                unresolved_refs.append((cand_name, raw_ref))

    # ── Report ────────────────────────────────────────────────────────────────
    total_matched = sum(len(v) for v in matched_by_scr.values())
    print(f'\nMatched: {total_matched} slokas across {len(matched_by_scr)} scriptures')

    if skipped_candidates:
        print(f'\nSkipped (no scripture ID): {len(skipped_candidates)}')
        for s in skipped_candidates:
            print(f'  - {s}')

    if unresolved_refs:
        print(f'\nUnresolved refs (in candidates but not found in CSV): {len(unresolved_refs)}')
        for name, ref in unresolved_refs:
            print(f'  [{name}] {ref!r}')

    print('\nMatched by scripture:')
    for sid in sorted(matched_by_scr):
        print(f'  id={sid:3d}  {len(matched_by_scr[sid])} slokas')

    # ── Generate SQL ──────────────────────────────────────────────────────────
    # Build a name lookup (id → name) from scripture.csv for comments
    id_name: dict[int, str] = {}
    with open(SCRIPTURE_CSV, newline='', encoding='utf-8-sig') as f:
        for row in csv.reader(f):
            if len(row) >= 2 and row[0].strip().isdigit():
                id_name[int(row[0])] = row[1].strip()

    lines = [
        '-- update_sloka_scripture_ids_v2.sql',
        '-- Generated by scripts/map_scriptures_from_candidates.py',
        '-- Assigns scripture_id for the 127 newly added scripture entries (ids 33–159).',
        '-- Run AFTER update_sloka_scripture_ids.sql (which covers ids 1–32).',
        '-- Safe to re-run: UPDATE is idempotent.',
        '',
        'SET NAMES utf8mb4;',
        '',
    ]

    for sid in sorted(matched_by_scr):
        ids   = sorted(set(matched_by_scr[sid]))
        name  = id_name.get(sid, f'id={sid}')
        chunk = ', '.join(str(i) for i in ids)
        lines.append(f'-- {name} (id={sid}) — {len(ids)} slokas')
        lines.append(f'UPDATE sloka SET scripture_id = {sid} WHERE id IN ({chunk});')
        lines.append('')

    lines.append(f'-- Done: {total_matched} slokas updated.')
    lines.append('')

    OUTPUT_SQL.write_text('\n'.join(lines), encoding='utf-8')
    print(f'\nSQL written to: {OUTPUT_SQL}')


if __name__ == '__main__':
    main()
