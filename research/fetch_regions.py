#!/usr/bin/env python3
"""Descarrega fotografias de região com licença livre (Wikimedia Commons) e gera migration/locations.json.
Cada imagem fica identificada com autor, licença e origem; nunca é apresentada como parte de um imóvel."""
import html, json, os, re, time, urllib.parse, urllib.request
ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
UA = {'User-Agent': 'PPI-site-review/1.0'}
TEXTS = json.load(open(os.path.join(ROOT, 'research', 'locations-text.json')))
out = {}
for slug, d in TEXTS.items():
    item = {'intro_pt': d['pt'], 'intro_en': d['en']}
    if d.get('file'):
        u = 'https://commons.wikimedia.org/w/api.php?' + urllib.parse.urlencode({'action': 'query', 'format': 'json', 'titles': d['file'], 'prop': 'imageinfo', 'iiprop': 'url|extmetadata', 'iiurlwidth': 1600})
        page = next(iter(json.load(urllib.request.urlopen(urllib.request.Request(u, headers=UA), timeout=30))['query']['pages'].values()))
        i = page['imageinfo'][0]; m = i['extmetadata']
        artist = re.sub(r'\s+', ' ', html.unescape(re.sub(r'<[^>]+>', '', m['Artist']['value']))).strip()
        lic = m['LicenseShortName']['value']
        name = f'{slug}.jpg'; path = os.path.join(ROOT, 'migration', 'regions', name)
        if not os.path.exists(path):
            with urllib.request.urlopen(urllib.request.Request(i['thumburl'], headers=UA), timeout=60) as r, open(path, 'wb') as f:
                f.write(r.read())
            time.sleep(1)
        item.update({'image_file': f'regions/{name}', 'image_source': i['descriptionurl'],
                     'image_credit': f'<a href="{html.escape(i["descriptionurl"])}" rel="noopener">{html.escape(artist)}, {html.escape(lic)}</a>'})
    out[slug] = item
json.dump({'format': 'ppi-locations/1', 'status': 'Textos editoriais propostos, por rever pela PPI.', 'items': out}, open(os.path.join(ROOT, 'migration', 'locations.json'), 'w'), ensure_ascii=False, indent=1)
print(len(out))
