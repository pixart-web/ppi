// Capturas reais das páginas principais. Uso: node screenshots.mjs [filtro]; WIDTHS=390,768,1440
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';
const base = process.env.BASE_URL || 'http://ppi.localhost:8097';
const only = process.argv[2] || '';
const out = process.env.OUT || new URL('../docs/capturas/', import.meta.url).pathname;
mkdirSync(out, { recursive: true });
const widths = (process.env.WIDTHS || '390,768,1440').split(',').map(Number);
const browser = await chromium.launch();
const probe = await browser.newPage();
await probe.goto(base + '/imoveis/?ref=10300');
const ficha = new URL(await probe.locator('.ppi-card__link').first().getAttribute('href')).pathname;
await probe.goto(base + '/imoveis/?ref=10378-A');
const terreno = new URL(await probe.locator('.ppi-card__link').first().getAttribute('href')).pathname;
await probe.close();
const pages = { home: '/', listagem: '/imoveis/', ficha, 'ficha-terreno': terreno, vender: '/vender/', localizacoes: '/localizacoes/', budens: '/localizacoes/vila-do-bispo/budens/', sobre: '/sobre/', contactos: '/contactos/', 'home-en': '/en/' };
for (const w of widths) {
	const ctx = await browser.newContext({ viewport: { width: w, height: w < 700 ? 844 : 900 }, reducedMotion: 'reduce' });
	await ctx.addCookies([{ name: 'ppi_consent', value: encodeURIComponent('{"v":1,"analytics":false,"external":false}'), url: base }]);
	const page = await ctx.newPage();
	for (const [name, path] of Object.entries(pages)) {
		if (only && !name.includes(only)) continue;
		await page.goto(base + path, { waitUntil: 'load' });
		await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 600) { window.scrollTo(0, y); await new Promise(r => setTimeout(r, 60)); } window.scrollTo(0, 0); });
		await page.waitForTimeout(250);
		await page.screenshot({ path: `${out}${name}-${w}.jpg`, quality: 70, type: 'jpeg', fullPage: process.env.FULL !== '0' });
		const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
		if (overflow > 0) console.log(`OVERFLOW ${name} @${w}: ${overflow}px`);
	}
	await ctx.close();
}
await browser.close();
console.log('ok');
