import re,html,sys
s=open(sys.argv[1],encoding='utf-8',errors='ignore').read()
s=s[s.find('<body'):]
s=re.sub(r'<script.*?</script>|<style.*?</style>|<select.*?</select>','',s,flags=re.S)
t=html.unescape(re.sub(r'<[^>]+>','\n',s))
L=[l.strip() for l in t.split('\n') if l.strip()]
try: a=max(i for i,l in enumerate(L) if l=='Pesquisar')+1
except ValueError: a=0
try: b=next(i for i,l in enumerate(L) if l.startswith('Centros de Resolu'))
except StopIteration: b=len(L)
print('\n'.join(L[a:b]))
