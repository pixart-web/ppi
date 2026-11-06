#!/usr/bin/env python3
"""Normaliza as fichas públicas descarregadas (research/raw/props/*.html) para o formato de importação PPI.

Saídas:
  migration/properties.json  — registos normalizados (formato documentado em docs/formato-importacao.md)
  migration/manifest.json    — origem de cada campo, data de recolha, ficheiros e alertas por anúncio

Regras: valores ausentes ficam null (nunca 0 inventado); "Preço sob consulta" não é 0 €;
divergências entre descrição/título e campos estruturados são registadas como alertas,
sem escolher qualquer dos valores.
Uso: python3 -I research/parse_props.py
"""
import glob
import html
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
RAW = os.path.join(ROOT, 'research', 'raw')
OUT = os.path.join(ROOT, 'migration')
BASE = 'https://www.ppiestateagents.com/'


def text(fragment):
    if fragment is None:
        return None
    t = html.unescape(re.sub(r'<[^>]+>', ' ', fragment))
    return re.sub(r'\s+', ' ', t).strip() or None


def info(s, label):
    m = re.search(r'>\s*(?:<span[^>]*>)?' + re.escape(label) + r'(?:</span>)?\s*</p>\s*<p class=["\']info-item[^>]*>(.*?)</p>', s, re.S)
    return text(m.group(1)) if m else None


NA = {'não se aplica', 'nao se aplica', '', '-'}


def na(v):
    return None if v is None or v.strip().lower() in NA else v


def area(v):
    if not v:
        return None, None
    m = re.search(r'([\d.,]+)\s*m', v)
    if not m:
        return None, f'formato de área não reconhecido: {v}'
    n = m.group(1)
    # A origem usa ponto decimal (ex.: 141.3, 47.04) e não separador de milhares nas áreas.
    n = n.replace(',', '.')
    try:
        val = float(n)
    except ValueError:
        return None, f'área inválida: {v}'
    if val == 0:
        return None, 'área 0 na origem tratada como indisponível'
    return (int(val) if val.is_integer() else val), None


def price(v):
    if not v:
        return None, False, None
    if 'consulta' in v.lower():
        return None, True, None
    digits = re.sub(r'[^\d]', '', v)
    if not digits:
        return None, False, f'preço não reconhecido: {v}'
    return int(digits), False, None


NUM = r'(\d{1,3}(?:[.\s]\d{3})+|\d+(?:[.,]\d+)?)'


def to_num(raw, unit):
    raw = raw.strip()
    if re.fullmatch(r'\d{1,3}(?:[.\s]\d{3})+', raw):
        val = float(re.sub(r'[.\s]', '', raw))
    else:
        val = float(raw.replace(',', '.'))
    if unit.lower().startswith('ha') or unit.lower().startswith('hect'):
        val *= 10000
    return val


AREA_RE = re.compile(NUM + r'\s*(m²|m2|m\b|metros quadrados|sqm|ha\b|hectares?)', re.I)
POTENTIAL = re.compile(r'(possib|permit|poder|pode |até|projeto|projecto|edific[áa]vel|ampliad|cont[íi]gu|outro de|cada um|lote ter[áa]|lotes|implanta|mezanino|cerca de|aprox|desde|a partir de|piso|coberta|armaz[ée]m|anexo|garagem|terra[çc]o|varanda|piscina|edif[íi]cios antigos|um com|outro com|incluindo|total do terreno, inclu)', re.I)
LAND_KW = re.compile(r'(terreno|lote|parcela|quinta|herdade|propriedade|plot|land)', re.I)
BUILT_KW = re.compile(r'(constru[íi]da|constru[çc][ãa]o|bruta|[úu]til|habit[áa]vel|casa|moradia|vivenda|apartamento|ru[íi]na|built|house|villa)', re.I)


def tolerance(raw, unit):
    """Tolerância de uma unidade na última casa decimal citada (aceita arredondamento e truncagem)."""
    dec = re.search(r'[.,](\d+)$', raw.strip())
    is_ha = unit.lower().startswith('h')
    if is_ha:
        places = len(dec.group(1)) if dec and not re.fullmatch(r'\d{1,3}(?:[.\s]\d{3})+', raw.strip()) else 0
        return 10000 * (10 ** -places)
    return 1.0


def close(a, b, tol):
    return a is not None and b is not None and abs(a - b) <= max(tol, 0.005 * max(a, b))


def detect_conflicts(rec):
    """Compara áreas, quartos e preço citados no título/descrição com os campos estruturados.

    Conflito material: o texto atribui ao próprio imóvel uma quantidade do mesmo tipo de um campo
    preenchido, com valor diferente. Potencial de construção, lotes vizinhos, anexos e valores sem
    campo correspondente ficam como alertas de revisão (não bloqueiam a publicação).
    """
    alerts = []
    built = [v for v in (rec['area_gross'], rec['area_useful']) if v]
    every = [v for v in (rec['area_land'], rec['area_gross'], rec['area_useful']) if v]
    for src_name, src in (('título', rec['title'] or ''), ('descrição', rec['description'] or '')):
        for m in AREA_RE.finditer(src):
            raw, unit = m.group(1), m.group(2)
            try:
                val = to_num(raw, unit)
            except ValueError:
                continue
            pre = src[max(0, m.start() - 60):m.start()]
            post = src[m.end():m.end() + 25]
            ctx = (pre + m.group(0) + post).strip()
            if unit.lower() == 'm' and not re.search(r'(área|terreno|lote|com)\s*(de\s*)?$', pre.strip()[-20:], re.I):
                continue  # distâncias ("a 900 m da praia")
            if val < 10:
                continue
            tol = tolerance(raw, unit)
            if any(close(val, k, tol) for k in every):
                continue
            base = {'text_value': val, 'context': ctx, 'source': src_name}
            if POTENTIAL.search(pre[-45:] + ' ' + post[:12]):
                alerts.append({'code': 'area_in_text_unmatched', 'severity': 'warning', **base,
                               'note': 'Área citada (potencial, lote vizinho ou parte do imóvel) sem campo correspondente; verificar.'})
                continue
            l = max((x.end() for x in LAND_KW.finditer(pre)), default=-1)
            b = max((x.end() for x in BUILT_KW.finditer(pre)), default=-1)
            if l < 0 and b < 0:
                alerts.append({'code': 'area_in_text_unmatched', 'severity': 'warning', **base})
                continue
            if l > b:
                if rec['area_land'] is None:
                    alerts.append({'code': 'area_land_only_in_text', 'severity': 'warning', 'field': 'area_land', 'field_value': None, **base})
                else:
                    alerts.append({'code': 'area_land_mismatch', 'severity': 'conflict', 'field': 'area_land', 'field_value': rec['area_land'], **base})
            else:
                if not built:
                    alerts.append({'code': 'area_built_only_in_text', 'severity': 'warning', 'field': 'area_gross', 'field_value': None, **base})
                else:
                    alerts.append({'code': 'area_built_mismatch', 'severity': 'conflict', 'field': 'area_gross',
                                   'field_value': {'bruta': rec['area_gross'], 'util': rec['area_useful']}, **base})
    m = re.search(r'(\d+)\s*(quartos|bedrooms?|bed\b)', rec['title'] or '', re.I)
    if m and rec['bedrooms'] is not None and int(m.group(1)) != rec['bedrooms']:
        alerts.append({'code': 'bedrooms_mismatch', 'severity': 'conflict', 'field': 'bedrooms', 'text_value': int(m.group(1)),
                       'field_value': rec['bedrooms'], 'context': rec['title'], 'source': 'título'})
    for m in re.finditer(r'(\d{1,3}(?:[.\s]\d{3})+)\s*(?:€|euros?)', rec['description'] or '', re.I):
        val = int(re.sub(r'[.\s]', '', m.group(1)))
        ctx = (rec['description'][max(0, m.start() - 60):m.end() + 20]).strip()
        if rec['price'] and val >= 10000 and abs(val - rec['price']) > 1:
            sev = 'warning' if re.search(r'(desde|T\d|cada|lote|from)', ctx, re.I) else 'conflict'
            alerts.append({'code': 'price_in_text_differs', 'severity': sev, 'field': 'price', 'text_value': val,
                           'field_value': rec['price'], 'context': ctx, 'source': 'descrição'})
    seen, out = set(), []
    for a in alerts:
        k = (a['code'], json.dumps(a.get('text_value')))
        if k not in seen:
            seen.add(k)
            out.append(a)
    return out


def looks_english(t):
    if not t:
        return False
    words = re.findall(r'[a-záàâãéêíóôõúç]+', t.lower())
    en = sum(w in {'the', 'with', 'and', 'bedroom', 'bedrooms', 'house', 'villa', 'plot', 'for', 'of', 'semi', 'detached', 'townhouse', 'views', 'sea', 'land', 'building', 'apartment'} for w in words)
    pt = sum(w in {'com', 'de', 'e', 'quartos', 'moradia', 'terreno', 'para', 'vista', 'vistas', 'mar', 'casa', 'construção', 'a', 'do', 'da'} for w in words)
    return en > pt


def parse(path, fetched_at):
    s = open(path, encoding='utf-8').read()
    slug = os.path.basename(path)[:-5]
    ext_id = re.search(r'-(\d{6,})$', slug).group(1)
    alerts = []
    prov = {}

    h1 = re.search(r'<h1 class="left-section-heading">(.*?)<span', s, re.S)
    title = text(h1.group(1)) if h1 else None
    prov['title'] = 'h1.left-section-heading'
    ptxt = re.search(r'<p class="left-section-heading preco[^>]*>(.*?)</p>', s, re.S)
    ptxt = text(ptxt.group(1)) if ptxt else None
    p, on_request, err = price(ptxt)
    if err:
        alerts.append({'code': 'price_unparsed', 'severity': 'conflict', 'context': ptxt})
    if p and p >= 20_000_000:
        alerts.append({'code': 'price_outlier', 'severity': 'conflict', 'field': 'price', 'field_value': p,
                       'context': f'Preço publicado {ptxt} muito acima do restante catálogo; confirmar antes de publicar.'})
    prov['price'] = 'p.preco'
    badge = re.search(r'id="featured-imovel-text"[^>]*>([^<]*)<', s)
    badge = badge.group(1).strip() if badge else None

    rec = {
        'external_id': ext_id,
        'source': 'ppiestateagents.com (HCPro) — página pública',
        'source_url': BASE + slug,
        'reference': info(s, 'Referência'),
        'title': title,
        'description': None,
        'business': info(s, 'Natureza'),
        'type': info(s, 'Tipo de Imóvel'),
        'subtype': na(info(s, 'Subtipo de Imóvel')),
        'typology': na(info(s, 'Tipologia')),
        'bedrooms': None,
        'bathrooms': None,
        'condition': na(info(s, 'Estado')),
        'energy_class': na(info(s, 'Certificado energético')),
        'year': None,
        'district': info(s, 'Distrito'),
        'municipality': info(s, 'Concelho'),
        'parish': info(s, 'Freguesia'),
        'postal_code': info(s, 'Código Postal'),
        'price': p,
        'price_on_request': on_request,
        'currency': 'EUR',
        'price_text': ptxt,
        'badge': badge,
        'area_useful': None, 'area_gross': None, 'area_land': None,
        'features': [],
        'photos': [], 'floorplans': [], 'video': None,
        'agent': None,
        'location_query': None,
        'commercial_status': 'available',
        'fetched_at': fetched_at,
    }
    for k, lab in (('reference', 'Referência'), ('business', 'Natureza'), ('type', 'Tipo de Imóvel'), ('subtype', 'Subtipo de Imóvel'),
                   ('typology', 'Tipologia'), ('condition', 'Estado'), ('energy_class', 'Certificado energético'), ('district', 'Distrito'),
                   ('municipality', 'Concelho'), ('parish', 'Freguesia'), ('postal_code', 'Código Postal')):
        prov[k] = f'ficha: «{lab}»'
    y = info(s, 'Ano')
    if y and re.fullmatch(r'\d{4}', y):
        rec['year'] = int(y)
        prov['year'] = 'ficha: «Ano»'
    for k, lab in (('area_useful', 'Área Útil'), ('area_gross', 'Área Bruta'), ('area_land', 'Área de Terreno')):
        v, e = area(info(s, lab))
        rec[k] = v
        prov[k] = f'ficha: «{lab}»'
        if e:
            alerts.append({'code': 'area_zero_or_invalid', 'severity': 'info', 'field': k, 'context': e})
    if rec['typology']:
        m = re.match(r'T(\d+)(\+(\d+))?', rec['typology'])
        if m:
            rec['bedrooms'] = int(m.group(1))
            prov['bedrooms'] = 'derivado de «Tipologia» (T = n.º de quartos)'
            if m.group(2):
                alerts.append({'code': 'typology_plus', 'severity': 'info', 'field': 'bedrooms',
                               'context': f'Tipologia {rec["typology"]}: divisão adicional não contabilizada como quarto.'})
    d = re.search(r'<div id="descricao".*?<pre[^>]*>(.*?)</pre>', s, re.S)
    if d:
        rec['description'] = html.unescape(re.sub(r'<br\s*/?>', '\n', d.group(1))).strip()
        rec['description'] = re.sub(r'<[^>]+>', '', rec['description'])
        prov['description'] = 'ficha: «Descrição»'
    for m in re.finditer(r'<tr><th>([^<]+)</td><td>([^<]+)</th><td>([^<]*)</td></tr>', s):
        g, lab, val = (html.unescape(x).strip() for x in m.groups())
        lab = lab.rstrip(':').strip()
        rec['features'].append({'group': g, 'label': lab, 'value': val})
        if lab == 'Nº de Casas de Banho' and val.isdigit():
            rec['bathrooms'] = int(val)
            prov['bathrooms'] = 'características: «Nº de Casas de Banho»'
    prov['features'] = 'ficha: «Características» (tabela)'
    slider = re.search(r'id="photo-slider" class="flexslider">(.*?)</ul>', s, re.S)
    if slider:
        seen = []
        for u in re.findall(r'href="(https://multimedia\.hcpro\.pt/[^"]+/foto/[^"]+)"', slider.group(1)):
            if u not in seen:
                seen.append(u)
        rec['photos'] = seen
    prov['photos'] = '#photo-slider (ordem da origem, com marca de água)'
    plan = []
    for u in re.findall(r'(https://multimedia\.hcpro\.pt/hcpro/7565/properties/\d+/planta/[^"\')\s]+)', s):
        if u not in plan:
            plan.append(u)
    rec['floorplans'] = plan
    yt = re.search(r'youtube\.com/embed/([A-Za-z0-9_-]{6,})', s)
    if yt:
        rec['video'] = 'https://www.youtube.com/watch?v=' + yt.group(1)
        prov['video'] = 'iframe YouTube na ficha'
    ag = re.search(r'id="consultor-info".*?<p translate="no" class="notranslate">([^<]+)</p>\s*<p>([^<]*)</p>', s, re.S)
    if ag:
        rec['agent'] = {'name': ag.group(1).strip(), 'role': ag.group(2).strip()}
        prov['agent'] = '#consultor-info (não validado pela agência)'
    mq = re.search(r'maps\.google\.pt/maps\?q=([^&"]+)', s)
    if mq:
        rec['location_query'] = html.unescape(mq.group(1)).replace('+', ' ').strip()
        prov['location_query'] = 'iframe Google Maps (pesquisa por morada aproximada, sem coordenadas)'
    if not rec['photos']:
        alerts.append({'code': 'no_photos', 'severity': 'warning', 'context': 'Ficha sem fotografias.'})
    if rec['type'] == 'Terreno' and rec['bedrooms']:
        alerts.append({'code': 'land_with_bedrooms', 'severity': 'warning', 'field': 'bedrooms', 'context': 'Terreno com tipologia.'})
    if looks_english(title):
        alerts.append({'code': 'title_language', 'severity': 'info', 'field': 'title',
                       'context': 'Título publicado em inglês no site português; rever a versão PT.'})
    if looks_english((rec['description'] or '')[:400]):
        alerts.append({'code': 'description_language', 'severity': 'info', 'field': 'description',
                       'context': 'Descrição publicada em inglês no site português; rever a versão PT.'})
    if rec['business'] != 'Venda':
        alerts.append({'code': 'business_unexpected', 'severity': 'info', 'context': rec['business']})
    alerts += detect_conflicts(rec)
    return rec, prov, alerts


def main():
    fetched_at = open(os.path.join(RAW, 'fetched_at.txt')).read().strip()
    files = sorted(glob.glob(os.path.join(RAW, 'props', '*.html')))
    listed = set(open(os.path.join(RAW, 'urls-venda.txt')).read().split())
    props, manifest = [], []
    refs = {}
    for f in files:
        rec, prov, alerts = parse(f, fetched_at)
        slug = os.path.basename(f)[:-5]
        if rec['reference'] in refs:
            alerts.append({'code': 'duplicate_reference', 'severity': 'conflict', 'context': f'Referência repetida em {refs[rec["reference"]]}'})
        refs.setdefault(rec['reference'], slug)
        rec['review'] = {'conflicts': [a for a in alerts if a['severity'] == 'conflict'],
                         'warnings': [a for a in alerts if a['severity'] != 'conflict']}
        props.append(rec)
        manifest.append({
            'external_id': rec['external_id'], 'reference': rec['reference'], 'source_url': rec['source_url'],
            'fetched_at': fetched_at, 'in_listing': slug in listed, 'html_file': f'research/raw/props/{slug}.html',
            'fields': prov, 'photos_total': len(rec['photos']), 'floorplans_total': len(rec['floorplans']),
            'alerts': alerts,
        })
    props.sort(key=lambda r: r['external_id'])
    manifest.sort(key=lambda r: r['external_id'])
    json.dump({'format': 'ppi-properties/1', 'generated_from': BASE, 'fetched_at': fetched_at, 'count': len(props), 'properties': props},
              open(os.path.join(OUT, 'properties.json'), 'w'), ensure_ascii=False, indent=1)
    json.dump({'format': 'ppi-manifest/1', 'fetched_at': fetched_at, 'source_declared_count': 152, 'pages_collected': len(files),
               'listing_urls_unique': len(listed), 'items': manifest},
              open(os.path.join(OUT, 'manifest.json'), 'w'), ensure_ascii=False, indent=1)
    c = sum(1 for p in props if p['review']['conflicts'])
    print(f'{len(props)} imóveis · {c} com conflitos · {sum(len(p["photos"]) for p in props)} fotografias · {sum(len(p["floorplans"]) for p in props)} plantas')


if __name__ == '__main__':
    sys.exit(main())
