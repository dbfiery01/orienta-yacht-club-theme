#!/usr/bin/env python3
"""Build the fixed parts of the Fleet Roster booklet PDF.

Input: the hand-made booklet PDF (e.g. "OYC Fleet Roster 11-22-26.pdf").
Output (theme assets, read by inc/roster-pdf.php):
  assets/fleet-roster/booklet-static.json  the fixed pages (cover, club info,
      officers, committees, past commodores, dock assignments, fees, work dock
      rules, memorials, notes) as PDF objects, copied as-is.
  assets/fleet-roster/bonum-{regular,bold,italic}.otf  TeX Gyre Bonum
      (GUST Font License), subset to Latin, for the member pages.
  assets/fleet-roster/fonts.json  glyph ids and widths for those fonts.

The member data pages (fleet list, Regular and Associate members) are NOT
taken from the file: the site builds them from the uploaded roster.

Usage: python3 tools/build-roster-booklet.py booklet.pdf [pages]
  pages: comma list of the fixed pages in the source, default 1-5,8,9,24-26.
Needs: pip install pypdf fonttools
"""
import base64, io, json, os, sys, zlib
from pypdf import PdfReader
from pypdf.generic import IndirectObject, DictionaryObject, ArrayObject, StreamObject, NameObject, NumberObject
from fontTools.ttLib import TTFont
from fontTools import subset

src = sys.argv[1]
spec = sys.argv[2] if len(sys.argv) > 2 else '1-5,8,9,24-26'
pages = []
for part in spec.split(','):
    a, _, b = part.partition('-')
    pages += list(range(int(a), int(b or a) + 1))
out_dir = os.path.join(os.path.dirname(os.path.abspath(__file__)), '..', 'assets', 'fleet-roster')
os.makedirs(out_dir, exist_ok=True)
r = PdfReader(src)

objects = {}

def ser(o):
    b = io.BytesIO()
    o.write_to_stream(b)
    return b.getvalue().decode('latin-1')

def visit(o):
    if isinstance(o, IndirectObject):
        if o.idnum in objects:
            return
        objects[o.idnum] = None
        v = o.get_object()
        if isinstance(v, StreamObject):
            d = DictionaryObject({k: val for k, val in v.items()})
            d[NameObject('/Length')] = NumberObject(len(v._data))
            objects[o.idnum] = {'dict': ser(d), 'stream': base64.b64encode(v._data).decode()}
            for val in v.values():
                visit(val)
        else:
            objects[o.idnum] = {'dict': ser(v)}
            visit(v)
    elif isinstance(o, DictionaryObject):
        for k, val in o.items():
            if k != '/Parent':
                visit(val)
    elif isinstance(o, ArrayObject):
        for val in o:
            visit(val)

out_pages = []
for n in pages:
    p = r.pages[n - 1]
    d = DictionaryObject()
    for k, v in p.items():
        if k in ('/Parent', '/Annots'):
            continue
        d[NameObject(k)] = v
    for k in ('/Resources', '/MediaBox'):  # inherited values made explicit
        if k not in d:
            d[NameObject(k)] = p[k]
    # The page is copied as a Form XObject: its content streams joined, with
    # its own resources, so the site draws it and then adds the page number.
    res = p['/Resources']
    visit(res if not isinstance(res, IndirectObject) else res)
    if isinstance(res, IndirectObject):
        visit(res)
    else:
        visit(DictionaryObject(res))
    data = zlib.compress(p.get_contents().get_data())
    mb = [float(x) for x in p['/MediaBox']]
    out_pages.append({
        'source_page': n,
        'resources': ser(res),
        'bbox': mb,
        'stream': base64.b64encode(data).decode(),
    })

json.dump({'source': os.path.basename(src), 'pages': out_pages, 'objects': objects},
          open(os.path.join(out_dir, 'booklet-static.json'), 'w'))

# Fonts: the member pages use TeX Gyre Bonum, as the hand-made booklet does.
uni = list(range(0x20, 0x7f)) + list(range(0xa0, 0x180)) + [0x2013, 0x2014, 0x2018, 0x2019, 0x201c, 0x201d, 0x2022, 0x2026]
metrics = {}
for face in ('Regular', 'Bold', 'Italic'):
    fobj = None
    for p in r.pages:
        for f in p['/Resources'].get('/Font', {}).values():
            f = f.get_object()
            if str(f['/BaseFont']).endswith('Bonum ' + face):
                fobj = f
                break
        if fobj:
            break
    fd = fobj['/DescendantFonts'][0].get_object()['/FontDescriptor'].get_object()
    raw = fd['/FontFile3'].get_object().get_data()
    font = TTFont(io.BytesIO(raw))
    opts = subset.Options()
    opts.layout_features = []
    opts.name_IDs = ['*']
    opts.notdef_outline = True
    sub = subset.Subsetter(opts)
    sub.populate(unicodes=uni)
    sub.subset(font)
    path = os.path.join(out_dir, 'bonum-%s.otf' % face.lower())
    font.save(path)
    font = TTFont(path)
    order = font.getGlyphOrder()
    gid = {g: i for i, g in enumerate(order)}
    cmap = font.getBestCmap()
    upm = font['head'].unitsPerEm
    hm = font['hmtx']
    metrics[face.lower()] = {
        'file': os.path.basename(path),
        'cmap': {str(u): gid[g] for u, g in cmap.items()},
        'widths': [round(hm[g][0] * 1000 / upm) for g in order],
        'ascent': font['hhea'].ascent, 'descent': font['hhea'].descent,
        'capHeight': getattr(font['OS/2'], 'sCapHeight', 700),
        'bbox': [font['head'].xMin, font['head'].yMin, font['head'].xMax, font['head'].yMax],
        'italicAngle': font['post'].italicAngle,
    }
json.dump(metrics, open(os.path.join(out_dir, 'fonts.json'), 'w'))
print('pages', pages, 'objects', len(objects))
