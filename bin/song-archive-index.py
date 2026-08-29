#!/usr/bin/env python3
"""Generate a CSV of every file in var/data: one row per file (song dir, filename)."""
import os, csv

ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..', 'var', 'data'))
OUT = os.path.abspath(os.path.join(ROOT, '..', 'song-archive-files.csv'))

rows = []
for song in sorted(os.listdir(ROOT)):
    d = os.path.join(ROOT, song)
    if not os.path.isdir(d):
        continue
    for f in sorted(os.listdir(d)):
        p = os.path.join(d, f)
        if not os.path.isfile(p) and not os.path.islink(p):
            continue
        ext = f.rsplit('.', 1)[-1].lower() if '.' in f else ''
        is_link = os.path.islink(p)
        target = os.readlink(p) if is_link else ''
        rows.append([song, f, ext, 'yes' if is_link else '', target])

with open(OUT, 'w', newline='') as fh:
    w = csv.writer(fh)
    w.writerow(['song', 'filename', 'ext', 'is_symlink', 'symlink_target'])
    w.writerows(rows)

print(f"Wrote {OUT}")
print(f"  {len(rows)} files across {len(set(r[0] for r in rows))} song folders")
