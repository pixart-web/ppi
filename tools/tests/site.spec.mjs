import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';
import { wp, evalPhp, adminCreds, login, noConsent, mails } from './helpers.mjs';

const REF = '10300';
const propUrl = async (page, ref = REF) => {
	await page.goto(`/imoveis/?ref=${encodeURIComponent(ref)}`);
	return page.locator('.ppi-card__link').first().getAttribute('href');
};
test.beforeEach(async ({ context, baseURL }) => { await noConsent(context, baseURL); });

test.describe('Catálogo', () => {
	test('listagem mostra contagem, paginação e cartões sem «0 quartos»', async ({ page }) => {
		await page.goto('/imoveis/');
		await expect(page.locator('.ppi-results__count')).toContainText('imóveis encontrados');
		await expect(page.locator('.ppi-card')).toHaveCount(12);
		await expect(page.locator('.ppi-pages')).toBeVisible();
		await page.goto('/imoveis/?tipo=terreno');
		expect(await page.locator('.ppi-fact--bedrooms').count()).toBe(0);
	});
	test('filtros combinados, URL partilhável e estado após recarregar', async ({ page }) => {
		await page.goto('/imoveis/?tipo=moradia&quartos=3&preco_max=1000000&ordem=preco-desc');
		const count = await page.locator('.ppi-results__count').innerText();
		await page.reload();
		await expect(page.locator('.ppi-results__count')).toHaveText(count);
		await expect(page.locator('#ppi-f-tipo')).toHaveValue('moradia');
		await expect(page.locator('#ppi-sort')).toHaveValue('preco-desc');
		await expect(page.locator('.ppi-active .ppi-chip')).not.toHaveCount(0);
	});
	test('ordenação por preço é numérica e sem preço fica no fim', async ({ page }) => {
		const prices = [];
		await page.goto('/imoveis/?ordem=preco-asc');
		for (const t of await page.locator('.ppi-card__price').allInnerTexts()) prices.push(Number(t.replace(/\D/g, '')));
		expect(prices).toEqual([...prices].sort((a, b) => a - b));
		const raw = evalPhp(`$r=PPI\\Search::run(PPI\\Search::params(['ordem'=>'preco-desc','pagina'=>99]));echo $r['pages'];`);
		await page.goto(`/imoveis/?ordem=preco-desc&pagina=${raw}`);
		await expect(page.locator('.ppi-card__price').last()).toContainText('sob consulta');
	});
	test('pesquisa por referência e zero resultados útil', async ({ page }) => {
		await page.goto('/imoveis/?ref=10300');
		await expect(page.locator('.ppi-card')).toHaveCount(1);
		await page.goto('/imoveis/?ref=ZZZ999');
		await expect(page.locator('.ppi-empty')).toBeVisible();
	});
	test('parâmetros inválidos não causam erro', async ({ page }) => {
		const r = await page.goto('/imoveis/?preco_min=abc&quartos=999&tipo=%3Cscript%3E&pagina=99999&carac[]=x');
		expect(r.status()).toBe(200);
		await expect(page.locator('.ppi-card').first()).toBeVisible();
	});
	test('filtros sem recarregar atualizam URL e voltar atrás funciona', async ({ page }) => {
		await page.goto('/imoveis/');
		await page.selectOption('#ppi-f-tipo', 'quinta');
		await expect(page).toHaveURL(/tipo=quinta/);
		await expect(page.locator('#ppi-f-tipo')).toHaveValue('quinta');
		await page.goBack();
		await expect(page.locator('.ppi-pages')).toBeVisible();
	});
	test('funciona sem JavaScript', async ({ browser, baseURL }) => {
		const ctx = await browser.newContext({ javaScriptEnabled: false, baseURL });
		const p = await ctx.newPage();
		await p.goto('/imoveis/');
		await p.selectOption('#ppi-f-tipo', 'moradia');
		await p.click('.ppi-filters__actions button');
		await expect(p).toHaveURL(/tipo=moradia/);
		await expect(p.locator('.ppi-card').first()).toBeVisible();
		await ctx.close();
	});
});

test.describe('Ficha', () => {
	test('galeria por teclado: abrir, navegar, Escape devolve o foco', async ({ page }) => {
		await page.goto(await propUrl(page));
		const btn = page.locator('.ppi-gallery__all');
		await btn.focus();
		await page.keyboard.press('Enter');
		await expect(page.locator('[data-ppi-lightbox]')).toBeVisible();
		await page.keyboard.press('ArrowRight');
		await expect(page.locator('[data-ppi-current]')).toHaveText('2');
		await page.keyboard.press('Escape');
		await expect(page.locator('[data-ppi-lightbox]')).toBeHidden();
		await expect(btn).toBeFocused();
	});
	test('galeria em toque: swipe muda de fotografia', async ({ page }) => {
		await page.goto(await propUrl(page));
		await page.locator('.ppi-gallery__all').click();
		const box = await page.locator('[data-ppi-track]').boundingBox();
		const y = box.y + box.height / 2;
		await page.mouse.move(box.x + box.width * 0.8, y);
		await page.mouse.down();
		await page.mouse.move(box.x + box.width * 0.2, y, { steps: 5 });
		await page.mouse.up();
		await expect(page.locator('[data-ppi-current]')).toHaveText('2');
	});
	test('menu móvel: abre, navega por teclado, Escape fecha', async ({ page }) => {
		await page.setViewportSize({ width: 390, height: 800 });
		await page.goto('/');
		const b = page.locator('[data-nav-toggle]');
		await b.click();
		await expect(page.locator('[data-nav-panel] .menu a').first()).toBeFocused();
		await page.keyboard.press('Escape');
		await expect(b).toHaveAttribute('aria-expanded', 'false');
		await expect(b).toBeFocused();
	});
	test('terreno não mostra quartos; preço sob consulta não é 0 €', async ({ page }) => {
		await page.goto(await propUrl(page, '10378-A'));
		await expect(page.locator('.ppi-summary')).not.toContainText('Quartos');
		const ref = evalPhp(`foreach(PPI\\Search::index('pt') as $r){if($r['por']){echo $r['ref'];break;}}`);
		await page.goto(await propUrl(page, ref));
		await expect(page.locator('.ppi-prop-head__price')).toHaveText('Preço sob consulta');
	});
	test('coordenadas privadas e morada interna nunca saem no HTML/REST', async ({ page, request }) => {
		const url = await propUrl(page);
		const id = evalPhp(`echo PPI\\Rest::id_by_ref('${REF}','pt');`);
		evalPhp(`update_post_meta(${id},'ppi_lat_private','37.123456');`);
		const html = await (await request.get(url)).text();
		expect(html).not.toContain('37.123456');
		const rest = await (await request.get(`/wp-json/wp/v2/properties/${id}`)).text();
		expect(rest).not.toContain('37.123456');
		expect(rest).not.toContain('address_private');
		evalPhp(`delete_post_meta(${id},'ppi_lat_private');`);
	});
	test('imóveis com conflito não estão públicos', async ({ request }) => {
		const r = await request.get('/imoveis/?ref=10456');
		expect(await r.text()).toContain('ppi-empty');
		const status = evalPhp(`$p=get_posts(['post_type'=>'ppi_property','post_status'=>'any','meta_key'=>'ppi_reference','meta_value'=>'10456','lang'=>'pt','fields'=>'ids']);echo get_post_status($p[0]);`);
		expect(status).toBe('pending');
	});
	test('imagens ilustrativas estão assinaladas', async ({ page }) => {
		await page.goto(await propUrl(page, '10359'));
		await expect(page.locator('.ppi-gallery__label').first()).toContainText('não é fotografia');
	});
});

test.describe('Favoritos e comparação', () => {
	test('persistem após recarregar; anúncio retirado é tratado', async ({ page }) => {
		await page.goto('/imoveis/?ref=10300');
		await page.locator('[data-ppi-fav="10300"]').click();
		await page.locator('[data-ppi-compare="10300"]').click();
		await page.goto('/imoveis/?ref=10284');
		await page.locator('[data-ppi-fav="10284"]').click();
		await page.locator('[data-ppi-compare="10284"]').click();
		await page.goto('/favoritos/');
		await expect(page.locator('[data-ppi-list] .ppi-card')).toHaveCount(2);
		await page.reload();
		await expect(page.locator('[data-ppi-list] .ppi-card')).toHaveCount(2);
		await page.goto('/comparar/');
		await expect(page).toHaveURL(/refs=/);
		await expect(page.locator('.ppi-compare__table thead th')).toHaveCount(2);
		const id = evalPhp(`echo PPI\\Rest::id_by_ref('10284','pt');`);
		wp('post', 'update', id, '--post_status=draft');
		try {
			await page.goto('/favoritos/');
			await expect(page.locator('.ppi-card--gone')).toHaveCount(1);
		} finally { wp('post', 'update', id, '--post_status=publish'); }
	});
});

test.describe('Contactos', () => {
	const del = () => evalPhp(`foreach(get_posts(['post_type'=>'ppi_lead','post_status'=>'any','fields'=>'ids','posts_per_page'=>-1]) as $i){wp_delete_post($i,true);} global $wpdb;$wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '%ppi_rl_%'");`);
	test.beforeEach(() => del());
	const fill = async (page, name) => {
		await page.waitForTimeout(3200); // tempo mínimo antispam
		await page.fill('#contactar input[name=name]', name);
		await page.fill('#contactar input[name=email]', 'teste@ppi.test');
	};
	test('pedido de visita grava lead com imóvel correto e envia para a caixa de teste', async ({ page }) => {
		await page.goto(await propUrl(page));
		const name = `QA ${Date.now()}`;
		await fill(page, name);
		await page.click('#contactar .ppi-form__submit');
		await expect(page.locator('#contactar .ppi-form__done')).toContainText('ainda não está marcada');
		const ref = evalPhp(`$l=get_posts(['post_type'=>'ppi_lead','post_status'=>'any','fields'=>'ids']);echo get_post_meta($l[0],'_ppi_property_ref',true).'|'.get_post_meta($l[0],'_ppi_mail_status',true);`);
		expect(ref).toBe(`${REF}|sent`);
		const m = await mails(`subject:"${name}"`);
		expect(m.length).toBe(1);
		expect(m[0].To[0].Address).toBe('leads@ppi.test');
	});
	test('falha de email não perde o lead nem indica entrega', async ({ page }) => {
		// Porta SMTP inválida só nesta chamada: simulada no servidor.
		const out = evalPhp(`add_filter('pre_wp_mail',fn()=>false);$r=PPI\\Leads::process(['type'=>'contact','name'=>'Falha SMTP','email'=>'falha@ppi.test','message'=>'Teste de falha','ts'=>(function(){$t=(string)(time()-10);return $t.'.'.substr(hash_hmac('sha256',$t,wp_salt('nonce')),0,16);})(),'token'=>'a1']);echo $r['ok']?'ok':'ko';echo '|'.get_post_meta($r['lead_id'],'_ppi_mail_status',true);`);
		expect(out).toBe('ok|failed');
	});
	test('duplo envio não duplica; honeypot não grava', async () => {
		const ts = `$t=(string)(time()-10);$s=$t.'.'.substr(hash_hmac('sha256',$t,wp_salt('nonce')),0,16);`;
		const n = evalPhp(`${ts}$a=['type'=>'contact','name'=>'Dup','email'=>'dup@ppi.test','message'=>'Mensagem igual','ts'=>$s,'token'=>'b1'];PPI\\Leads::process($a);PPI\\Leads::process($a);PPI\\Leads::process($a+['website'=>'']);$h=$a;$h['website']='spam';$h['message']='outra';PPI\\Leads::process($h);echo count(get_posts(['post_type'=>'ppi_lead','post_status'=>'any','fields'=>'ids','posts_per_page'=>-1]));`);
		expect(n).toBe('1');
	});
	test('validação: erros acessíveis e sem JS mantém os valores', async ({ browser, baseURL }) => {
		const ctx = await browser.newContext({ javaScriptEnabled: false, baseURL });
		const p = await ctx.newPage();
		await p.goto('/contactos/');
		await p.waitForTimeout(3200);
		await p.fill('input[name=name]', 'A Pessoa');
		await p.fill('input[name=email]', 'invalido');
		await p.click('.ppi-form__submit');
		await expect(p.locator('.ppi-form__errors')).toBeVisible();
		await expect(p.locator('input[name=name]')).toHaveValue('A Pessoa');
		await expect(p.locator('input[name=email]')).toHaveAttribute('aria-invalid', 'true');
		await ctx.close();
	});
});

test.describe('Permissões', () => {
	test('leads fora da REST e do site público; sem leitura por editor', async ({ page, request, browser, baseURL }) => {
		expect((await request.get('/wp-json/wp/v2/ppi_lead')).status()).toBe(404);
		expect((await request.get('/?post_type=ppi_lead')).url()).not.toContain('ppi_lead=');
		const out = evalPhp(`$e=get_role('editor');echo (int)$e->has_cap('ppi_manage_leads').(int)$e->has_cap('ppi_import').(int)$e->has_cap('edit_ppi_properties');`);
		expect(out).toBe('001');
		const pw = `Qa-${Date.now()}-x`;
		wp('user', 'create', 'qa-editor', 'qa-editor@ppi.test', '--role=editor', `--user_pass=${pw}`);
		try {
			await login(page, 'qa-editor', pw);
			const r = await page.goto('/wp-admin/edit.php?post_type=ppi_lead');
			expect(r.status()).toBe(403);
			const s = await page.goto('/wp-admin/edit.php?post_type=ppi_property&page=ppi-settings');
			expect(s.status()).toBe(403);
			const e = await page.goto('/wp-admin/admin-post.php?action=ppi_leads_export');
			expect(e.status()).toBe(403);
		} finally { wp('user', 'delete', 'qa-editor', '--yes'); }
	});
	test('CV privado não acessível diretamente', async ({ request }) => {
		const r = await request.get('/wp-content/uploads/ppi-private/.htaccess');
		expect(r.status()).toBe(403);
	});
});

test.describe('Backoffice', () => {
	test('criar, publicar e retirar um imóvel', async ({ page }) => {
		const { user, pass } = adminCreds();
		await login(page, user, pass);
		await page.goto('/wp-admin/post-new.php?post_type=ppi_property');
		const ref = `QA${Date.now() % 100000}`;
		await page.fill('#title', `Imóvel de teste ${ref}`);
		await page.fill('#ppi-f-reference', ref);
		await page.selectOption('#ppi-f-type', { label: 'Moradia' });
		await page.fill('#ppi-f-price', '250000');
		await page.click('#publish');
		await page.waitForURL(/post\.php/);
		await expect(page.locator('#message')).toBeVisible();
		const id = new URL(page.url()).searchParams.get('post');
		await page.goto(`/imoveis/?ref=${ref}`);
		await expect(page.locator('.ppi-card')).toHaveCount(1);
		evalPhp(`update_post_meta(${id},'ppi_commercial_status','withdrawn');PPI\\Search::flush();`);
		await page.goto(`/imoveis/?ref=${ref}`);
		await expect(page.locator('.ppi-empty')).toBeVisible();
		const url = evalPhp(`echo get_permalink(${id});`);
		expect((await page.request.get(url)).status()).toBe(410);
		wp('post', 'delete', id, '--force');
	});
	test('imóvel com conflito não pode ser publicado sem nota', async () => {
		const out = evalPhp(`$p=get_posts(['post_type'=>'ppi_property','post_status'=>'pending','lang'=>'pt','fields'=>'ids','posts_per_page'=>1])[0];wp_update_post(['ID'=>$p,'post_status'=>'publish']);echo get_post_status($p);`);
		expect(out).toBe('pending');
	});
});

test.describe('Idiomas e SEO', () => {
	test('PT/EN mantém equivalência e factos', async ({ page }) => {
		const url = await propUrl(page);
		await page.goto(url);
		const pt = await page.locator('.ppi-prop-head__price').innerText();
		await page.locator('.site-tools__lang a[lang=en]').click();
		await expect(page).toHaveURL(/\/en\//);
		await expect(page.locator('html')).toHaveAttribute('lang', /en/);
		const en = await page.locator('.ppi-prop-head__price').innerText();
		expect(en.replace(/\D/g, '')).toBe(pt.replace(/\D/g, ''));
		await expect(page.locator('.ppi-summary')).toContainText('Bedrooms');
		await expect(page.locator('link[rel=alternate][hreflang]')).not.toHaveCount(0);
	});
	test('anúncio sem inglês não é duplicado em português', async ({ page }) => {
		await page.goto('/en/imoveis/');
		await expect(page.locator('.ppi-catalog-head .ppi-note')).toContainText('available in English');
		const n = Number(evalPhp(`echo count(PPI\\Search::index('en'));`));
		expect(n).toBeLessThan(20);
	});
	test('redirects individuais e indexação', async ({ request }) => {
		const r = await request.get('/imovel-venda-moradia-t3-budens-999', { maxRedirects: 0 });
		expect([404, 410]).toContain(r.status());
		const url = evalPhp(`$p=json_decode(file_get_contents('/migration/properties.json'),true)['properties'];foreach($p as $x){if($x['reference']==='10300'){echo parse_url($x['source_url'],PHP_URL_PATH);}}`);
		const old = await request.get(url, { maxRedirects: 0 });
		expect(old.status()).toBe(301);
		expect(old.headers().location).toContain('/imoveis/');
		const f = await (await request.get('/imoveis/?tipo=moradia')).text();
		expect(f).toMatch(/name=['"]robots['"][^>]*noindex/);
		const head = await request.get('/');
		expect(head.headers()['x-robots-tag']).toContain('noindex');
		const sm = await (await request.get('/wp-sitemap.xml')).text();
		expect(sm).not.toContain('ppi_lead');
	});
});

test.describe('Acessibilidade e reflow', () => {
	for (const [n, path] of [['home', '/'], ['imoveis', '/imoveis/'], ['vender', '/vender/'], ['contactos', '/contactos/'], ['localidades', '/localizacoes/']]) {
		test(`axe sem violações graves: ${n}`, async ({ page }) => {
			await page.goto(path);
			const r = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']).analyze();
			const bad = r.violations.filter(v => ['serious', 'critical'].includes(v.impact));
			expect(bad.map(v => `${v.id}: ${v.nodes[0].target}`)).toEqual([]);
		});
	}
	test('axe na ficha', async ({ page }) => {
		await page.goto(await propUrl(page));
		const r = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
		expect(r.violations.filter(v => ['serious', 'critical'].includes(v.impact)).map(v => `${v.id}: ${v.nodes[0].target}`)).toEqual([]);
	});
	for (const w of [320, 390, 720, 768, 1440]) { // 720 = 1440 com zoom de 200%
		test(`sem overflow horizontal a ${w}px`, async ({ page }) => {
			await page.setViewportSize({ width: w, height: 800 });
			for (const path of ['/', '/imoveis/', '/vender/', await propUrl(page)]) {
				await page.goto(path);
				const o = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
				expect(o, path).toBeLessThanOrEqual(0);
			}
		});
	}
	test('sem erros JS, sem imagens quebradas', async ({ page }) => {
		const errs = [];
		page.on('pageerror', e => errs.push(e.message));
		for (const path of ['/', '/imoveis/', '/localizacoes/', await propUrl(page), '/en/']) {
			await page.goto(path, { waitUntil: 'load' });
			await page.evaluate(() => document.querySelectorAll('img[loading=lazy]').forEach(i => { i.loading = 'eager'; }));
			await page.waitForTimeout(600);
			const broken = await page.evaluate(() => [...document.images].filter(i => i.src && i.complete && !i.naturalWidth).map(i => i.src));
			expect(broken, path).toEqual([]);
		}
		expect(errs).toEqual([]);
	});
});
