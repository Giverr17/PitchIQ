#!/usr/bin/env python3
"""
Subset the Material Symbols Outlined font down to only the icons this app uses.

The full font ships ~3.96MB (all ~3,500 icons). This produces a ~67KB woff2 with
just the icons listed in resources/fonts/material-symbols-icons.txt.

Why this is fiddly: Material Symbols renders icons via ligatures whose components
are individual letters (h+o+m+e -> home). A naive `pyftsubset --text` keeps every
icon spellable from the letters you use (≈ the whole font). So instead we:
  1. resolve each icon name to its exact output glyph via the font's GSUB table,
  2. subset to those glyphs + the letters, with layout-closure OFF so no other
     icons are pulled in, keeping all features (the ligatures live under `rlig`).

Run:  python scripts/subset-icons.py        (or: npm run icons:subset)
Requires:  pip install fonttools brotli
"""
import os
import re
import subprocess
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(ROOT, "node_modules", "material-symbols", "material-symbols-outlined.woff2")
LIST = os.path.join(ROOT, "resources", "fonts", "material-symbols-icons.txt")
OUT = os.path.join(ROOT, "resources", "fonts", "material-symbols-outlined-subset.woff2")

from fontTools.ttLib import TTFont  # noqa: E402


def ligature_tables(gsub):
    tabs = []
    for lk in gsub.LookupList.Lookup:
        for st in lk.SubTable:
            real = st.ExtSubTable if (lk.LookupType == 7 and hasattr(st, "ExtSubTable")) else st
            if hasattr(real, "ligatures"):
                tabs.append(real)
    return tabs


def main():
    if not os.path.exists(SRC):
        sys.exit(f"Source font not found: {SRC}\nRun `npm install` first.")

    names = [n for n in re.split(r"\s+", open(LIST).read().strip()) if n]
    font = TTFont(SRC)
    cmap = font.getBestCmap()
    tabs = ligature_tables(font["GSUB"].table)

    def resolve(name):
        try:
            comps = [cmap[ord(c)] for c in name]
        except KeyError:
            return None
        for lt in tabs:
            for lg in lt.ligatures.get(comps[0], []):
                if list(lg.Component) == comps[1:]:
                    return lg.LigGlyph
        return None

    glyphs, missing = set(), []
    for n in names:
        g = resolve(n)
        glyphs.add(g) if g else missing.append(n)

    if missing:
        print(f"WARNING: {len(missing)} icon name(s) not found in font: {', '.join(missing)}")

    cmd = [
        sys.executable, "-m", "fontTools.subset", SRC,
        f"--output-file={OUT}",
        "--flavor=woff2",
        "--layout-features=*",
        "--no-layout-closure",
        "--glyphs=" + ",".join(sorted(glyphs)),
        "--text=abcdefghijklmnopqrstuvwxyz_",
        "--no-hinting",
    ]
    subprocess.run(cmd, check=True)
    kb = os.path.getsize(OUT) / 1024
    print(f"Subset {len(glyphs)} icons -> {OUT}  ({kb:.1f} KB)")


if __name__ == "__main__":
    main()
