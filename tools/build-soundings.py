#!/usr/bin/env python3
"""Build the map's own sounding data from NOAA's official ENC chart files.

Brad, 2026-11-15: soundings at every zoom, the same across Long Island Sound,
fewer as you zoom out but the shallowest always shown. NOAA's chart pictures
can't do that (whole stretches of the Sound are a flat blue sheet at zoom
9-11), so we read the soundings ourselves.

Input : the state ENC zips (charts.noaa.gov/ENCs/CT_ENCs.zip, NY_, RI_), which
        the site downloads with admin-ajax?action=oyc_enc_src&st=CT|NY|RI into
        uploads/oyc-enc-src/. Unzip them into one folder.
Needs : pip install pyogrio  (its GDAL includes the S-57 reader)
Run   : python3 tools/build-soundings.py <unzipped-folder>
Output: assets/soundings/
          z{9..12}/{x}_{y}.json  thinned soundings for map zoom z, bucketed by
                                 the zoom (z-2) tile x,y: [[lat*1e5, lon*1e5,
                                 depth in decimetres], ...]
          pt/{x}_{y}.json        every sounding, bucketed by zoom-12 tile, for
                                 waypoint depths: [[lat*1e5, lon*1e5, dm, band]]
          meta.json              source cells, date, counts
"""
import glob, json, math, os, sys, time

os.environ['OGR_S57_OPTIONS'] = 'SPLIT_MULTIPOINT=ON,ADD_SOUNDG_DEPTH=ON,UPDATES=APPLY'
import warnings
warnings.filterwarnings('ignore')
import pyogrio

S, W, N, E = 40.50, -74.10, 41.45, -71.40          # Long Island Sound and approaches
GRID = {9: 44, 10: 44, 11: 40, 12: 40}             # px between soundings at each zoom
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'assets', 'soundings')


def px(lat, lon, z):
    n = 2 ** z * 256
    la = math.radians(lat)
    return (lon + 180) / 360 * n, (1 - math.log(math.tan(la) + 1 / math.cos(la)) / math.pi) / 2 * n


def main(src):
    pts, cells, seen = [], [], set()
    for f in sorted(glob.glob(os.path.join(src, '**', '*.000'), recursive=True)):
        c = os.path.basename(f)
        if c in seen:
            continue
        seen.add(c)
        try:
            df = pyogrio.read_dataframe(f, layer='SOUNDG', bbox=(W, S, E, N))
        except Exception:
            continue
        n = 0
        for g, dep in zip(df.geometry, df['DEPTH'] if 'DEPTH' in df else [None] * len(df)):
            if g is None:
                continue
            z = dep if dep is not None and dep == dep else (g.z if g.has_z else None)
            if z is None:
                continue
            pts.append((g.y, g.x, float(z), int(c[2])))
            n += 1
        if n:
            cells.append(c[:8])
    print(len(pts), 'soundings from', len(cells), 'cells')

    def dump(path, data):
        os.makedirs(os.path.dirname(path), exist_ok=True)
        with open(path, 'w') as fh:
            json.dump(data, fh, separators=(',', ':'))

    for d in glob.glob(os.path.join(OUT, '*', '*.json')):
        os.remove(d)
    counts = {}
    # zoom 9-12: the shallowest sounding in each GRIDxGRID px cell. Zoom 11 and
    # out use only the approach charts and smaller (their soundings are chosen by
    # NOAA's cartographers) and skip soundings under 2 ft, which there are almost
    # all at the shore or inside harbours.
    for z, g in GRID.items():
        cell = {}
        for p in pts:
            if z <= 11 and (p[3] > 4 or p[2] < 0.61):
                continue
            X, Y = px(p[0], p[1], z)
            k = (int(X // g), int(Y // g))
            q = cell.get(k)
            if q is None or p[2] < q[2] or (p[2] == q[2] and p[3] > q[3]):
                cell[k] = p
        # labels that would overlap (11px font, ~6.5px a digit) are dropped,
        # shallowest first, so every tile draws the same set
        placed, keep = {}, []
        for p in sorted(cell.values(), key=lambda p: p[2]):
            X, Y = px(p[0], p[1], z)
            w = 6.5 * len(str(int(abs(p[2]) / 0.3048))) + 4
            b = (X - w / 2, Y - 7, X + w / 2, Y + 7)
            near = [q for i in range(int(b[0] // 32) - 1, int(b[2] // 32) + 2)
                    for j in range(int(b[1] // 32) - 1, int(b[3] // 32) + 2) for q in placed.get((i, j), [])]
            if any(b[0] < q[2] and q[0] < b[2] and b[1] < q[3] and q[1] < b[3] for q in near):
                continue
            placed.setdefault((int(X // 32), int(Y // 32)), []).append(b)
            keep.append(p)
        bz, buckets = z - 2, {}
        for p in keep:
            X, Y = px(p[0], p[1], bz)
            buckets.setdefault((int(X // 256), int(Y // 256)), []).append(
                [round(p[0] * 1e5), round(p[1] * 1e5), round(p[2] * 10)])
        for (x, y), arr in buckets.items():
            arr.sort(key=lambda a: a[2])
            dump(os.path.join(OUT, 'z%d' % z, '%d_%d.json' % (x, y)), arr)
        counts['z%d' % z] = len(keep)
    # every sounding for waypoint depths, by zoom-12 tile
    buckets = {}
    for p in pts:
        X, Y = px(p[0], p[1], 12)
        buckets.setdefault((int(X // 256), int(Y // 256)), []).append(
            [round(p[0] * 1e5), round(p[1] * 1e5), round(p[2] * 10), p[3]])
    for (x, y), arr in buckets.items():
        dump(os.path.join(OUT, 'pt', '%d_%d.json' % (x, y)), arr)
    counts['pt'] = len(pts)
    dump(os.path.join(OUT, 'meta.json'), {
        'built': time.strftime('%Y-%m-%d'), 'bbox': [S, W, N, E], 'grid_px': GRID,
        'counts': counts, 'cells': cells,
        'source': 'NOAA ENC (charts.noaa.gov/ENCs) CT, NY, RI state files. Not for navigation.'})
    print(counts)


if __name__ == '__main__':
    main(sys.argv[1] if len(sys.argv) > 1 else '.')
