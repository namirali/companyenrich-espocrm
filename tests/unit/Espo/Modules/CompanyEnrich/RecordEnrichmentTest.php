<?php

namespace tests\unit\Espo\Modules\CompanyEnrich;

use Espo\Core\Acl;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\CompanyEnrich\Tools\Provider;
use Espo\Modules\CompanyEnrich\Tools\RecordEnrichment;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use Espo\ORM\TransactionManager;
use PHPUnit\Framework\TestCase;

class RecordEnrichmentTest extends TestCase
{
    private function record(array &$values): Entity
    {
        $record = $this->createMock(Entity::class);
        $record->method('get')->willReturnCallback(fn ($field) => $values[$field] ?? null);
        $record->method('set')->willReturnCallback(function ($field, $value) use (&$values, $record) {
            $values[$field] = $value;
            return $record;
        });
        return $record;
    }

    public function testFillEmptyAndPreserveExistingValues(): void
    {
        $values = ['website' => 'example.com', 'name' => 'Owner name', 'description' => null, 'modifiedAt' => '2026-11-02 10:00:00'];
        $record = $this->record($values);
        [$service, $manager, $provider] = $this->service($record, $record);
        $provider->expects(self::once())->method('run')->with('enrich-company', (object) ['domain' => 'example.com'])->willReturn([
            'data' => (object) ['id' => 'company', 'name' => 'Provider name', 'description' => 'Research description'], 'creditsConsumed' => 0,
        ]);
        $manager->expects(self::once())->method('saveEntity')->with($record);
        $result = $service->run('Account', 'abc123');
        self::assertSame(['description'], $result['updatedFields']);
        self::assertSame('Owner name', $values['name']);
        self::assertSame('Research description', $values['description']);
        self::assertArrayNotHasKey('data', $result);
        self::assertSame(0, $result['creditsConsumed']);
    }

    public function testConcurrentChangesNeverOverwriteOrRepeatProviderCall(): void
    {
        $values = ['website' => 'example.com', 'name' => 'Owner name', 'modifiedAt' => 'same-second'];
        $changed = [...$values, 'description' => 'Concurrent edit'];
        [$service, $manager, $provider] = $this->service($this->record($values), $this->record($changed));
        $provider->expects(self::once())->method('run')->willReturn(['data' => (object) ['id' => 'company', 'description' => 'Provider']]);
        $manager->expects(self::never())->method('saveEntity');
        $this->expectException(Conflict::class);
        $service->run('Account', 'abc123');
    }

    public function testDeniedAccessNeverCallsProvider(): void
    {
        $values = ['website' => 'example.com'];
        $record = $this->record($values);
        [$service, $manager, $provider] = $this->service($record, $record, false);
        $provider->expects(self::never())->method('run');
        $manager->expects(self::never())->method('saveEntity');
        $this->expectException(Forbidden::class);
        $service->run('Account', 'abc123');
    }

    public function testPopulatedRecordsDoNotSpendCredits(): void
    {
        $values = ['website' => 'example.com', 'name' => 'Existing', 'description' => 'Existing'];
        $record = $this->record($values);
        [$service, $manager, $provider] = $this->service($record, $record);
        $provider->expects(self::never())->method('run');
        $manager->expects(self::never())->method('saveEntity');
        self::assertSame([], $service->run('Account', 'abc123')['updatedFields']);
    }

    private function service(Entity $record, Entity $locked, bool $accessible = true): array
    {
        $manager = $this->createMock(EntityManager::class);
        $manager->method('getEntityById')->willReturn($record);
        $repository = $this->createMock(RDBRepository::class);
        $builder = $this->createMock(RDBSelectBuilder::class);
        $builder->method('where')->willReturnSelf();
        $builder->method('findOne')->willReturn($locked);
        $repository->method('forUpdate')->willReturn($builder);
        $manager->method('getRDBRepository')->willReturn($repository);
        $transaction = $this->createMock(TransactionManager::class);
        $transaction->method('run')->willReturnCallback(fn ($callback) => $callback());
        $manager->method('getTransactionManager')->willReturn($transaction);
        $acl = $this->createMock(Acl::class);
        $acl->method('checkEntityRead')->willReturn($accessible);
        $acl->method('checkEntityEdit')->willReturn($accessible);
        $acl->method('checkField')->willReturn($accessible);
        $provider = $this->createMock(Provider::class);
        return [new RecordEnrichment($manager, $acl, $provider), $manager, $provider];
    }
}
