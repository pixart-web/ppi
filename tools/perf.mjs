// Medição de laboratório (Chromium, mobile 390px, CPU 4x, rede 1,6 Mbps/150 ms). Não substitui dados de campo.
import { chromium } from '@playwright/test';
const base = process.env.BASE_URL || 'http://ppi.localhost:8097';
const browser = await chromium.launch();
const pr = await browser.newPage(); await pr.goto(base + '/imoveis/?ref=10300');
const ficha = await pr.locator('.ppi-card__link').first().getAttribute('href'); await pr.close();
for (const [n, u] of [['home', base + '/'], ['listagem', base + '/imoveis/'], ['ficha', ficha]]) {
	const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true });
	await ctx.addCookies([{ name: 'ppi_consent', value: encodeURIComponent('{"v":1}'), url: base }]);
	const page = await ctx.newPage();
	const c = await ctx.newCDPSession(page);
	await c.send('Emulation.setCPUThrottlingRate', { rate: 4 });
	await c.send('Network.enable');
	await c.send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: 1.6e6 / 8, uploadThroughput: 750e3 / 8 });
	await page.addInitScript(() => {
		window.__m = { lcp: 0, cls: 0 };
		new PerformanceObserver(l => { for (const e of l.getEntries()) window.__m.lcp = e.startTime; }).observe({ type: 'largest-contentful-paint', buffered: true });
		new PerformanceObserver(l => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__m.cls += e.value; }).observe({ type: 'layout-shift', buffered: true });
	});
	await page.goto(u, { waitUntil: 'load', timeout: 60000 });
	await page.waitForTimeout(1500);
	const m = await page.evaluate(() => ({ ...window.__m, bytes: performance.getEntriesByType('resource').reduce((a, r) => a + r.transferSize, 0), reqs: performance.getEntriesByType('resource').length }));
	console.log(`${n}: LCP ${(m.lcp / 1000).toFixed(2)} s · CLS ${m.cls.toFixed(3)} · ${m.reqs} pedidos · ${(m.bytes / 1024).toFixed(0)} KB`);
	await ctx.close();
}
await browser.close();
