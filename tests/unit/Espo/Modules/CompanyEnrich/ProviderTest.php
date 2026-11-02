<?php

namespace tests\unit\Espo\Modules\CompanyEnrich;

use Espo\Modules\CompanyEnrich\Tools\Credentials;
use Espo\Modules\CompanyEnrich\Tools\HttpTransport;
use Espo\Modules\CompanyEnrich\Tools\Operations;
use Espo\Modules\CompanyEnrich\Tools\Provider;
use Espo\Modules\CompanyEnrich\Tools\ProviderError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

class ProviderTest extends TestCase
{
    public static function operations(): iterable
    {
        $spec = json_decode(file_get_contents(__DIR__ . '/../../../../../src/files/custom/Espo/Modules/CompanyEnrich/Resources/operations.json'));
        foreach ($spec->operations as $operation) yield $operation->name => [$operation];
    }

    #[DataProvider('operations')]
    public function testOperationMapping(stdClass $operation): void
    {
        $transport = $this->createMock(HttpTransport::class);
        $transport->expects(self::once())->method('request')->willReturnCallback(
            function (string $method, string $url, string $key, ?string $body) use ($operation): array {
                self::assertSame($operation->method, $method);
                self::assertSame('api.companyenrich.com', parse_url($url, PHP_URL_HOST));
                self::assertSame($operation->path, parse_url($url, PHP_URL_PATH));
                self::assertSame('synthetic-key', $key);
                self::assertSame($method === 'POST', $body !== null);
                self::assertStringNotContainsString('expand', $body ?? '');
                return ['status' => 200, 'body' => '{"id":"profile"}', 'credits' => 1.0];
            },
        );
        $result = $this->provider($transport)->run($operation->name, $operation->example);
        self::assertSame('profile', $result['data']->id);
    }

    public function testFalseExpansionAndLargeIdsPreserved(): void
    {
        $transport = $this->createMock(HttpTransport::class);
        $transport->expects(self::once())->method('request')->with(
            'GET', 'https://api.companyenrich.com/companies/enrich?domain=example.com&waitForEnrichment=false&expand=workforce', 'synthetic-key', null,
        )->willReturn(['status' => 200, 'body' => '{"nested":[{"id":9223372036854775806}],"creditsConsumed":0}', 'credits' => 10.0]);
        $result = $this->provider($transport)->run('enrich-company', (object) [
            'domain' => 'https://Example.com/path', 'waitForEnrichment' => false, 'expand' => ['workforce'],
        ]);
        self::assertSame('9223372036854775806', $result['data']->nested[0]->id);
        self::assertSame(0, $result['creditsConsumed']);
    }

    public static function invalidInputs(): iterable
    {
        yield ['search-people', (object) ['body' => (object) ['companyDomain' => 'example.com']]];
        yield ['get-person', (object) ['id' => 9223372036854775806]];
        yield ['get-person', (object) ['id' => '9223372036854775808']];
        yield ['get-company', (object) ['id' => 'bad']];
        yield ['search-companies', (object) ['body' => (object) ['pageSize' => 101]]];
        yield ['search-companies', (object) ['body' => (object) ['page' => 0]]];
        yield ['lookup-person', (object) ['body' => (object) ['email' => 'bad']]];
        yield ['company-workforce', new stdClass()];
        yield ['similar-companies', (object) ['body' => new stdClass()]];
        yield ['enrich-company', (object) ['domain' => 'example.com', 'expand' => ['education']]];
        yield ['enrich-companies-batch', (object) ['body' => (object) ['domains' => array_fill(0, 51, 'example.com')]]];
        yield ['enrich-company-properties', (object) ['body' => new stdClass()]];
    }

    #[DataProvider('invalidInputs')]
    public function testInvalidInputNeverCallsProvider(string $name, stdClass $input): void
    {
        $transport = $this->createMock(HttpTransport::class);
        $transport->expects(self::never())->method('request');
        $this->expectException(ProviderError::class);
        $this->provider($transport)->run($name, $input);
    }

    public static function failures(): iterable
    {
        foreach ([400, 401, 402, 404, 422, 429, 500, 302] as $status) yield [$status];
    }

    #[DataProvider('failures')]
    public function testProviderFailuresAreRedactedAndNotRetried(int $status): void
    {
        $transport = $this->createMock(HttpTransport::class);
        $transport->expects(self::once())->method('request')->willReturn(['status' => $status, 'body' => 'private-provider-body', 'credits' => null]);
        try {
            $this->provider($transport)->run('enrich-company', (object) ['domain' => 'example.com']);
            self::fail('Expected a safe error.');
        } catch (ProviderError $error) {
            self::assertStringNotContainsString('private-provider-body', $error->getMessage());
        }
    }

    public function testSearchDefaultsAndFullFilters(): void
    {
        $transport = $this->createMock(HttpTransport::class);
        $transport->expects(self::once())->method('request')->willReturnCallback(function ($method, $url, $key, $body) {
            self::assertEquals((object) ['domains' => ['example.com'], 'positionQuery' => ['Engineer'], 'page' => 1, 'pageSize' => 10], json_decode($body));
            return ['status' => 200, 'body' => '{"items":[],"totalCount":0}', 'credits' => 0.0];
        });
        self::assertSame(0, $this->provider($transport)->run('search-people', (object) [
            'body' => (object) ['domains' => ['https://Example.com'], 'positionQuery' => ['Engineer']],
        ])['data']->totalCount);
    }

    public function testCountZeroAndArrayPreserved(): void
    {
        $transport = $this->createMock(HttpTransport::class);
        $transport->method('request')->willReturn(['status' => 200, 'body' => '0', 'credits' => 0.0]);
        self::assertSame(0, $this->provider($transport)->run('count-companies', (object) ['body' => new stdClass()])['data']);
    }

    public function testInvalidJsonIsNotRetried(): void
    {
        $transport = $this->createMock(HttpTransport::class);
        $transport->expects(self::once())->method('request')->willReturn(['status' => 200, 'body' => '{broken', 'credits' => null]);
        $this->expectException(ProviderError::class);
        $this->provider($transport)->run('get-person', (object) ['id' => '9223372036854775806']);
    }

    public function testBatchArrayRetainsLargeIdsAndNulls(): void
    {
        $transport = $this->createMock(HttpTransport::class);
        $transport->expects(self::once())->method('request')->willReturn([
            'status' => 200, 'body' => '[{"id":9223372036854775806,"name":null}]', 'credits' => null,
        ]);
        $result = $this->provider($transport)->run('enrich-companies-batch', (object) [
            'body' => (object) ['domains' => ['example.com']],
        ]);
        self::assertIsArray($result['data']);
        self::assertSame('9223372036854775806', $result['data'][0]->id);
        self::assertNull($result['data'][0]->name);
    }

    private function provider(HttpTransport $transport): Provider
    {
        $credentials = $this->createMock(Credentials::class);
        $credentials->method('key')->willReturn('synthetic-key');
        return new Provider(new Operations(), $transport, $credentials);
    }
}
