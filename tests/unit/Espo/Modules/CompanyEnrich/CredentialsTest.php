<?php

namespace tests\unit\Espo\Modules\CompanyEnrich;

use Espo\Core\Utils\Crypt;
use Espo\Entities\Integration;
use Espo\Modules\CompanyEnrich\Hooks\Integration\EncryptKey;
use Espo\Modules\CompanyEnrich\Tools\Credentials;
use Espo\Modules\CompanyEnrich\Tools\ProviderError;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class CredentialsTest extends TestCase
{
    public function testKeyEncryptedOncePreservedOnBlankAndRemovedExplicitly(): void
    {
        $values = ['apiKey' => 'synthetic-key', 'enabled' => true];
        $fetched = (object) [];
        $entity = $this->createMock(Integration::class);
        $entity->method('getId')->willReturn('CompanyEnrich');
        $entity->method('get')->willReturnCallback(function ($field) use (&$values) { return $values[$field] ?? null; });
        $entity->method('getFetched')->willReturn($fetched);
        $entity->method('set')->willReturnCallback(function ($field, $value) use (&$values, $entity) { $values[$field] = $value; return $entity; });
        $crypt = $this->createMock(Crypt::class);
        $crypt->expects(self::once())->method('encrypt')->with('synthetic-key')->willReturn('encrypted-value');
        $hook = new EncryptKey($crypt);
        $hook->beforeSave($entity);
        self::assertSame('encrypted-value', $values['apiKey']);
        $fetched->apiKey = 'encrypted-value';
        $hook->beforeSave($entity);
        $values['apiKey'] = '';
        $hook->beforeSave($entity);
        self::assertSame('encrypted-value', $values['apiKey']);
        $values['clearApiKey'] = true;
        $hook->beforeSave($entity);
        self::assertNull($values['apiKey']);
        self::assertFalse($values['enabled']);
        self::assertFalse($values['clearApiKey']);
    }

    public function testDisabledIntegrationCannotDecryptKey(): void
    {
        $entity = $this->createMock(Integration::class);
        $entity->method('get')->willReturn(false);
        $manager = $this->createMock(EntityManager::class);
        $manager->method('getEntityById')->willReturn($entity);
        $crypt = $this->createMock(Crypt::class);
        $crypt->expects(self::never())->method('decrypt');
        $this->expectException(ProviderError::class);
        (new Credentials($manager, $crypt))->key();
    }
}
