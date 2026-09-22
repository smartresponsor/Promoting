import { expect, test } from '@playwright/test';

test('standalone Promoting runtime handles an unknown route through Symfony', async ({ request }) => {
  const response = await request.get('/');
  expect(response.status()).toBe(404);
});
