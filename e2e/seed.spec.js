import { expect, test } from '@playwright/test';

// Seed test — wzorzec dla wszystkich testów E2E w tym projekcie.
// Ryzyko (context/foundation/prd.md, guardrail "Prywatność"): niezalogowany
// użytkownik widzi listę zakupów, albo sesja gubi się po odświeżeniu strony.
// Granice prawdziwe: routing, middleware auth, sesja, baza. Nic nie mockujemy.
test('logowanie otwiera listę zakupów, a sesja przeżywa odświeżenie', async ({ page }) => {
    // Setup: gość trafia na stronę główną i zostaje odesłany do logowania.
    await page.goto('/');
    await page.waitForURL('**/login');

    // Akcja: logowanie kontem z DatabaseSeeder.
    await page.getByLabel('Adres e-mail').fill('test@example.com');
    await page.getByLabel('Hasło').fill('password');
    await page.getByRole('button', { name: 'Zaloguj się' }).click();

    // Asercja: jesteśmy na liście zakupów jako zalogowany użytkownik.
    await page.waitForURL('**/');
    await expect(page.getByRole('heading', { name: 'Lista zakupów' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Test User' })).toBeVisible();

    // Asercja właściwa dla ryzyka: odświeżenie nie wyrzuca z sesji.
    await page.reload();
    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByRole('heading', { name: 'Lista zakupów' })).toBeVisible();

    // Cleanup: wylogowanie, żeby kolejny przebieg startował z czystej sesji.
    await page.getByRole('button', { name: 'Test User' }).click();
    await page.getByRole('link', { name: 'Wyloguj się' }).click();
    await page.waitForURL('**/login');
});
