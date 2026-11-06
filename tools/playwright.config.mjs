import { defineConfig } from '@playwright/test';

export default defineConfig({
	testDir: './tests',
	timeout: 90_000,
	workers: 1, // alguns testes alteram dados via WP-CLI
	reporter: [['list'], ['json', { outputFile: 'test-results/results.json' }]],
	use: { baseURL: process.env.BASE_URL || 'http://ppi.localhost:8097', reducedMotion: 'reduce', locale: 'pt-PT' },
});
