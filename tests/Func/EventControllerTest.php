<?php

namespace App\Tests\Func;

use App\DataFixtures\EventFixtures;
use Doctrine\ORM\Tools\SchemaTool;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class EventControllerTest extends WebTestCase
{
    protected AbstractDatabaseTool $databaseTool;
    private static KernelBrowser $client;

    protected function setUp(): void
    {
        self::$client = static::createClient();

        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $metaData = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->updateSchema($metaData);

        $this->databaseTool = static::getContainer()->get(DatabaseToolCollection::class)->get();

        $this->databaseTool->loadFixtures(
            [EventFixtures::class]
        );
    }

    public function testUpdateShouldReturnEmptyResponse(): void
    {
        $client = self::$client;

        $client->request(
            'PUT',
            sprintf('/api/event/%d/update', EventFixtures::EVENT_1_ID),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['comment' => 'It‘s a test comment !!!!!!!!!!!!!!!!!!!!!!!!!!!'])
        );

        self::assertResponseStatusCodeSame(204);
    }

    public function testUpdateShouldReturnHttpNotFoundResponse(): void
    {
        $client = self::$client;

        $client->request(
            'PUT',
            sprintf('/api/event/%d/update', 7897897897),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['comment' => 'It‘s a test comment !!!!!!!!!!!!!!!!!!!!!!!!!!!'])
        );

        self::assertResponseStatusCodeSame(404);

        $expectedJson = <<<JSON
              {
                "message":"Event identified by 7897897897 not found !"
              }
            JSON;

        self::assertJsonStringEqualsJsonString($expectedJson, $client->getResponse()->getContent());
    }

    /**
     * @dataProvider providePayloadViolations
     */
    public function testUpdateShouldReturnBadRequest(string $payload, string $expectedResponse): void
    {
        $client = self::$client;

        $client->request(
            'PUT',
            sprintf('/api/event/%d/update', EventFixtures::EVENT_1_ID),
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );

        self::assertResponseStatusCodeSame(400);
        self::assertJsonStringEqualsJsonString($expectedResponse, $client->getResponse()->getContent());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public function providePayloadViolations(): iterable
    {
        yield 'comment too short' => [
            <<<JSON
            {
              "comment": "short"
            }
            JSON,
            <<<JSON
            {
              "message": "This value is too short. It should have 20 characters or more."
            }
            JSON,
        ];
    }
}
