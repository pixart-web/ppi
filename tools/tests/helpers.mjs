import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';

const root = new URL('../../', import.meta.url).pathname;

export function wp(...args) {
	return execFileSync('docker-compose', ['run', '--rm', '-T', 'cli', 'wp', ...args], { cwd: root, encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] }).trim();
}
export function evalPhp(code) {
	return wp('eval', code);
}
export function adminCreds() {
	const t = readFileSync(root + '.credentials.local', 'utf8');
	return { user: t.match(/USER=(.*)/)[1], pass: t.match(/PASS=(.*)/)[1] };
}
export async function login(page, user, pass) {
	await page.goto('/wp-login.php');
	await page.fill('#user_login', user);
	await page.fill('#user_pass', pass);
	await page.click('#wp-submit');
	await page.waitForURL(/wp-admin/);
}
/** Dispensa o aviso de cookies (escolha mínima). */
export async function noConsent(context, baseURL) {
	await context.addCookies([{ name: 'ppi_consent', value: encodeURIComponent('{"v":1,"analytics":false,"external":false}'), url: baseURL }]);
}
export const mailpit = 'http://127.0.0.1:8098';
export async function mails(q) {
	const r = await fetch(`${mailpit}/api/v1/search?query=${encodeURIComponent(q)}`);
	return (await r.json()).messages || [];
}
