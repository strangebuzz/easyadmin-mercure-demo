<?php

declare(strict_types=1);

namespace App\Tests\E2E\Controller\Admin;

use Symfony\Component\Panther\PantherTestCase as E2ETestCase;

/**
 * @see https://github.com/symfony/panther#creating-isolated-browsers-to-test-apps-using-mercure-or-websockets
 */
final class ArticleCrudControllerTest extends E2ETestCase
{
    private const ARTICLE_LIST_URL = '/admin?crudAction=index&crudControllerFqcn=App%5CController%5CAdmin%5CArticleCrudController';

    // this is the second article as we created the first one in the AdminCrud test
    private const ARTICLE_EDIT_URL = '/admin?crudAction=edit&crudControllerFqcn=App\Controller\Admin\ArticleCrudController&entityId=2';

    private const NOTIFICATION_SELECTOR = '#conflict_notification';

    public function testMercureNotification(): void
    {
        $this->takeScreenshotIfTestFailed();

        // 1st administrator connects
        $client = self::createPantherClient([
            'external_base_uri' => 'http://127.0.0.1:8000', // use the Symfony CLI
        ]);

        $client->request('GET', self::ARTICLE_LIST_URL);
        self::assertSelectorTextContains('body', 'Article');
        self::assertSelectorTextContains('body', 'Add Article');

        // 1st administrator creates an article
        $client->request('GET', '/admin?crudAction=new&crudControllerFqcn=App\Controller\Admin\ArticleCrudController');
        $client->submitForm('Create', [
            'Article[code]' => 'CDB142',
            'Article[description]' => 'Chaise de bureau 2',
            'Article[quantity]' => '10',
            'Article[price]' => '50',
        ]);

        // 1st admin access the edit page of the article he juste created
        $client->request('GET', self::ARTICLE_EDIT_URL);

        self::assertSelectorTextContains('body', 'Save changes');
        // self::assertSelectorIsNotVisible(self::NOTIFICATION_SELECTOR); // does not work on CI

        // 2nd administrator access the edit page of the same article and mofifies the quantity
        $client2 = self::createAdditionalPantherClient();
        $client2->request('GET', self::ARTICLE_EDIT_URL);
        $client2->submitForm('Save changes', [
            'Article[quantity]' => '9',
        ]);

        // 1st admin has a notification thanks to Mercure and is invited to reload the page
        $client->waitForVisibility(self::NOTIFICATION_SELECTOR);
        self::assertSelectorIsVisible(self::NOTIFICATION_SELECTOR);
        self::assertSelectorTextContains('#conflict_notification', 'The data displayed is outdated');
        self::assertSelectorTextContains('#conflict_notification', 'Reload');
    }
}
