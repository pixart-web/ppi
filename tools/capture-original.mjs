// Capturas do site original (referência de inventário, não para publicação).
import { chromium } from '@playwright/test';
const out = new URL('../research/original-captures/', import.meta.url).pathname;
const pages = { home: '/', imoveis: '/imoveis?natureza=8&pesquisa=false', ficha: '/imovel-venda-moradia-t5-6210605', vender: '/vender-imovel', contactos: '/contactos' };
const browser = await chromium.launch();
for (const w of [390, 1440]) {
	const ctx = await browser.newContext({ viewport: { width: w, height: w < 700 ? 844 : 900 } });
	const page = await ctx.newPage();
	for (const [n, p] of Object.entries(pages)) {
		await page.goto('https://www.ppiestateagents.com' + p, { waitUntil: 'networkidle', timeout: 60000 }).catch(() => {});
		await page.waitForTimeout(800);
		await page.screenshot({ path: `${out}${n}-${w}.jpg`, quality: 60, fullPage: false });
	}
	await ctx.close();
}
await browser.close();
console.log('ok');
