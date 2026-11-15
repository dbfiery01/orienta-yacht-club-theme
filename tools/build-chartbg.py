#!/usr/bin/env python3
"""Build the chart background tiles for zoom 9-12 (assets/chartbg).

At zoom 9-11 NOAA's chart service draws Long Island Sound as one flat light-blue
area (its small-scale charts have no detail there), and its approach charts are
empty at zoom 9. Brad (2026-11-15) wants the Sound to look the same at every
zoom, so for zoom 9-12 the map draws its own background from the same NOAA ENC
files as the soundings (tools/build-soundings.py):

  water at 5 ft or deeper   white
  water under 5 ft          light blue
  drying (above datum)      green-grey
  depth contours            thin grey lines (zoom 9-10: approach charts and
                            smaller only)
  rocks / obstructions /    small dark crosses (zoom 12; zoom 10-11 only the
  wrecks                    ones that dry or are under 5 ft)

Land is left transparent, so NOAA's own chart (land, coastline, names) shows
through underneath. Cells are drawn least detailed first (usage band 2 → 5), so
the most detailed chart wins wherever several overlap. Tiles are 512 px (the
map shows zoom 9-12 tiles at 2x) and stored as palette PNGs:
assets/chartbg/{z}/{x}/{y}.png. Tiles with nothing drawn are not written.

Usage:  python3 tools/build-chartbg.py <folder with the unzipped *_ENCs.zip>
Needs:  pip install pyogrio shapely pillow (GDAL S57 driver inside pyogrio).
"""
import glob
import math
import os
import shutil
import sys

os.environ.setdefault('OGR_S57_OPTIONS', 'SPLIT_MULTIPOINT=ON,ADD_SOUNDG_DEPTH=ON,UPDATES=APPLY')
import pyogrio  # noqa: E402
from PIL import Image, ImageDraw  # noqa: E402

S, W, N, E = 40.50, -74.10, 41.45, -71.40          # must match SND_BOX in inc/wind-map.php
ZOOMS = (9, 10, 11, 12)
TS = 512
SHALLOW_M = 1.524                                   # 5 ft, OYC_ENC_CHART_FT
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'assets', 'chartbg')

# palette indexes
T, DEEP, SHAL, DRY, CONT, HAZ = 0, 1, 2, 3, 4, 5
PAL = [0, 0, 0, 255, 255, 255, 165, 200, 225, 172, 186, 150, 128, 128, 128, 40, 40, 40]


def gpx(lon, lat, z):
    n = 2 ** z * TS
    la = math.radians(max(-85, min(85, lat)))
    return (lon + 180) / 360 * n, (1 - math.log(math.tan(la) + 1 / math.cos(la)) / math.pi) / 2 * n


def rings(g):
    if g is None:
        return []
    if g.geom_type == 'Polygon':
        return [g]
    if hasattr(g, 'geoms'):
        out = []
        for p in g.geoms:
            out += rings(p)
        return out
    return []


def lines(g):
    if g is None:
        return []
    if g.geom_type == 'LineString':
        return [g]
    if g.geom_type == 'Polygon':
        return [g.exterior] + list(g.interiors)
    if hasattr(g, 'geoms'):
        out = []
        for p in g.geoms:
            out += lines(p)
        return out
    return []


def read(src):
    cells, seen = [], set()
    for f in sorted(glob.glob(os.path.join(src, '**', '*.000'), recursive=True)):
        c = os.path.basename(f)
        if c in seen:
            continue
        seen.add(c)
        try:
            names = [lay[0] for lay in pyogrio.list_layers(f)]
        except Exception:
            continue
        cell = {'band': int(c[2]), 'name': c[:8], 'areas': [], 'cont': [], 'haz': []}
        for lay in ('DEPARE', 'DRGARE'):
            if lay not in names:
                continue
            try:
                df = pyogrio.read_dataframe(f, layer=lay, bbox=(W, S, E, N))
            except Exception:
                continue
            for g, d1 in zip(df.geometry, df['DRVAL1'] if 'DRVAL1' in df else [None] * len(df)):
                d1 = d1 if d1 is not None and d1 == d1 else 0.0
                k = DRY if d1 < 0 else (SHAL if d1 < SHALLOW_M - 0.01 else DEEP)
                for p in rings(g):
                    cell['areas'].append((k, p))
        if 'DEPCNT' in names:
            try:
                df = pyogrio.read_dataframe(f, layer='DEPCNT', bbox=(W, S, E, N))
                for g in df.geometry:
                    cell['cont'] += lines(g)
            except Exception:
                pass
        for lay in ('UWTROC', 'OBSTRN', 'WRECKS'):
            if lay not in names:
                continue
            try:
                df = pyogrio.read_dataframe(f, layer=lay, bbox=(W, S, E, N))
            except Exception:
                continue
            vs = df['VALSOU'] if 'VALSOU' in df else [None] * len(df)
            wl = df['WATLEV'] if 'WATLEV' in df else [None] * len(df)
            for g, v, w in zip(df.geometry, vs, wl):
                if g is None:
                    continue
                pt = g if g.geom_type == 'Point' else g.representative_point()
                v = v if v is not None and v == v else None
                danger = (w is not None and w == w and int(w) in (1, 2, 4, 5)) or (v is not None and v < SHALLOW_M)
                cell['haz'].append((pt.x, pt.y, danger))
        if cell['areas'] or cell['cont'] or cell['haz']:
            cells.append(cell)
    cells.sort(key=lambda c: (c['band'], c['name']))
    return cells


def render(cells, z):
    x0, y0 = gpx(W, N, z)
    x1, y1 = gpx(E, S, z)
    tx0, ty0, tx1, ty1 = int(x0 // TS), int(y0 // TS), int(x1 // TS), int(y1 // TS)
    ox, oy = tx0 * TS, ty0 * TS
    img = Image.new('P', ((tx1 - tx0 + 1) * TS, (ty1 - ty0 + 1) * TS), T)
    img.putpalette(PAL + [0] * (768 - len(PAL)))
    d = ImageDraw.Draw(img)

    def pts(coords):
        return [(gpx(c[0], c[1], z)[0] - ox, gpx(c[0], c[1], z)[1] - oy) for c in coords]

    cw = 2 if z >= 11 else 1
    for c in cells:
        for k, p in c['areas']:
            ext = pts(p.exterior.coords)
            if len(ext) >= 3:
                d.polygon(ext, fill=k)
            for h in p.interiors:      # islands / land inside the area: let NOAA's land show
                hp = pts(h.coords)
                if len(hp) >= 3:
                    d.polygon(hp, fill=T)
    # contours after all fills; zoomed out (9-10) only the approach charts and
    # smaller, as the harbour charts' contours there are a grey scribble
    for c in cells:
        if z <= 10 and c['band'] > 4:
            continue
        for ln in c['cont']:
            lp = pts(ln.coords)
            if len(lp) >= 2:
                d.line(lp, fill=CONT, width=cw)
    if z >= 10:
        r = 4 if z >= 12 else 3
        for c in cells:
            for lon, lat, danger in c['haz']:
                if z <= 11 and not danger:
                    continue
                X, Y = gpx(lon, lat, z)
                X -= ox
                Y -= oy
                d.line([(X - r, Y), (X + r, Y)], fill=HAZ, width=2)
                d.line([(X, Y - r), (X, Y + r)], fill=HAZ, width=2)
    n = 0
    for tx in range(tx0, tx1 + 1):
        for ty in range(ty0, ty1 + 1):
            t = img.crop(((tx - tx0) * TS, (ty - ty0) * TS, (tx - tx0 + 1) * TS, (ty - ty0 + 1) * TS))
            if t.getextrema() == (0, 0):
                continue
            p = os.path.join(OUT, str(z), str(tx))
            os.makedirs(p, exist_ok=True)
            t.save(os.path.join(p, '%d.png' % ty), optimize=True, transparency=0)
            n += 1
    return n


def main(src):
    cells = read(src)
    print(len(cells), 'cells with depth areas / contours / hazards')
    if os.path.isdir(OUT):
        shutil.rmtree(OUT)
    for z in ZOOMS:
        print('zoom', z, render(cells, z), 'tiles', flush=True)


if __name__ == '__main__':
    if len(sys.argv) != 2:
        sys.exit(__doc__)
    main(sys.argv[1])
