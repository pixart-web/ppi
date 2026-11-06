#!/usr/bin/env python3
"""Descarrega cópias das fotografias publicadas (com a marca de água original) para migration/media/<id>/.

Uso: python3 -I research/fetch_media.py [max_fotos_por_imóvel=12]   (0 = todas)
Idempotente: ficheiros existentes não são descarregados de novo. Regista proveniência em media-index.json.
"""
import concurrent.futures as cf, hashlib, json, os, sys, time, urllib.request
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MAX = int(sys.argv[1]) if len(sys.argv) > 1 else 12
data = json.load(open(os.path.join(ROOT, 'migration', 'properties.json')))
jobs = []
for p in data['properties']:
    photos = p['photos'] if MAX == 0 else p['photos'][:MAX]
    for i, u in enumerate(photos):
        jobs.append((p['external_id'], 'foto', i, u))
    for i, u in enumerate(p['floorplans']):
        jobs.append((p['external_id'], 'planta', i, u))
def get(job):
    ext, kind, i, url = job
    d = os.path.join(ROOT, 'migration', 'media', ext)
    os.makedirs(d, exist_ok=True)
    name = f"{kind}-{i:02d}-{os.path.basename(url).split('?')[0]}"
    path = os.path.join(d, name)
    if not (os.path.exists(path) and os.path.getsize(path) > 1000):
        req = urllib.request.Request(url, headers={'User-Agent': 'PPI-migration-review/1.0'})
        for attempt in range(3):
            try:
                with urllib.request.urlopen(req, timeout=30) as r, open(path + '.part', 'wb') as f:
                    f.write(r.read())
                os.replace(path + '.part', path)
                break
            except Exception as e:
                if attempt == 2:
                    return {'url': url, 'error': str(e)}
                time.sleep(2)
    return {'external_id': ext, 'kind': kind, 'position': i, 'source_url': url, 'file': f'migration/media/{ext}/{name}',
            'sha1': hashlib.sha1(open(path, 'rb').read()).hexdigest(), 'bytes': os.path.getsize(path)}
with cf.ThreadPoolExecutor(4) as ex:
    res = list(ex.map(get, jobs))
ok = [r for r in res if 'error' not in r]
json.dump({'max_photos_per_property': MAX, 'files': ok, 'errors': [r for r in res if 'error' in r]},
          open(os.path.join(ROOT, 'migration', 'media-index.json'), 'w'), indent=1)
print(f"{len(ok)} ficheiros · {len(res)-len(ok)} erros · {sum(r['bytes'] for r in ok)/1e6:.0f} MB")
