#!/usr/bin/env python3
"""Regenerate assets/tide-predictions-8518091.json (the weather board's
last-resort tide fallback) from NOAA's published harmonic constituents.

The bundle currently covers 2026-07-15 -> 2027-08-15 — REGENERATE BEFORE
AUG 2027 (just edit the dates below and re-run).

Setup:
    python3 -m venv tidenv && tidenv/bin/pip install numpy scipy
    tidenv/bin/pip install --no-deps pytides2
    # pytides2 0.0.5 needs a one-line fix under numpy>=2:
    #   in site-packages/pytides2/tide.py replace `np.float` with `float`

Inputs (already saved next to this script; refresh them if NOAA re-runs the
station analysis — source URLs in the comments):
    harcon-8518091.json  https://api.tidesandcurrents.noaa.gov/mdapi/prod/webapi/stations/8518091/harcon.json
    datums-8518091.json  https://api.tidesandcurrents.noaa.gov/mdapi/prod/webapi/stations/8518091/datums.json

Validation: computed extrema matched NOAA's own predictions for 2026-07-17
to the minute and within 0.1 ft (see repo history for the comparison).
"""
import json, datetime, os
from zoneinfo import ZoneInfo
from pytides2.tide import Tide
import pytides2.constituent as cons

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, '..', 'assets', 'tide-predictions-8518091.json')
START = datetime.date(2026, 7, 15)      # <-- edit these two when regenerating
END   = datetime.date(2027, 8, 15)

h = json.load(open(os.path.join(HERE, 'harcon-8518091.json')))['HarmonicConstituents']
d = json.load(open(os.path.join(HERE, 'datums-8518091.json')))
dd = {x['name']: x['value'] for x in d['datums']}
Z0 = dd['MSL'] - dd['MLLW']             # bundle heights are relative to MLLW

by_name = {c.name.upper(): c for c in cons.noaa}
ALIAS = {'LAM2': 'LAMBDA2', 'RHO': 'RHO1'}   # NOAA name -> pytides name
model, amps, phases = [], [], []
for row in h:
    n = ALIAS.get(row['name'].upper(), row['name'].upper())
    c = by_name.get(n)
    if c is None:
        raise SystemExit('unmapped constituent: ' + row['name'])
    model.append(c); amps.append(row['amplitude']); phases.append(row['phase_GMT'])
model.append(cons._Z0); amps.append(Z0); phases.append(0)
tide = Tide(constituents=model, amplitudes=amps, phases=phases)

NY = ZoneInfo('America/New_York')
t0 = datetime.datetime(START.year, START.month, START.day, tzinfo=NY) \
        .astimezone(datetime.timezone.utc).replace(tzinfo=None)
events = []
for t, ht, kind in tide.extrema(t0):
    tl = t.replace(tzinfo=datetime.timezone.utc).astimezone(NY)
    if tl.date() > END:
        break
    events.append({'t': tl.strftime('%Y-%m-%d %H:%M'), 'v': '%.3f' % float(ht),
                   'type': 'H' if kind == 'H' else 'L'})
out = {'station': '8518091', 'datum': 'MLLW', 'units': 'ft', 'time_zone': 'lst_ldt',
       'source': 'harmonic (NOAA published constituents, epoch %s)' % d.get('epoch'),
       'generated': datetime.date.today().isoformat(),
       'coverage_start': str(START), 'coverage_end': str(END), 'events': events}
json.dump(out, open(OUT, 'w'), separators=(',', ':'))
print('wrote %d events to %s' % (len(events), OUT))
