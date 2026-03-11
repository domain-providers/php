<?php
declare(strict_types=1);

namespace DomainProviders\Tests\Unit\Provider\GoDaddy;

use DomainProviders\Capabilities;
use DomainProviders\DTO\DnsRecord;
use DomainProviders\DTO\DomainContact;
use DomainProviders\DTO\DomainName;
use DomainProviders\DTO\DomainRegistrationPeriod;
use DomainProviders\ErrorCategory;
use DomainProviders\Exception\DomainProviderException;
use DomainProviders\Exception\UnsupportedCapabilityException;
use DomainProviders\Provider\GoDaddy\GoDaddyConfig;
use DomainProviders\Provider\GoDaddy\GoDaddyDomainsApiInterface;
use DomainProviders\Provider\GoDaddy\GoDaddyProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GoDaddyProviderTest extends TestCase
{
    private function config(): GoDaddyConfig
    {
        return new GoDaddyConfig(
            apiKey: 'key',
            apiSecret: 'secret',
            customerId: 'cust-1',
            environment: 'sandbox',
        );
    }

    /** @return GoDaddyDomainsApiInterface&MockObject */
    private function apiMock(): GoDaddyDomainsApiInterface
    {
        return $this->createMock(GoDaddyDomainsApiInterface::class);
    }

    private function provider(GoDaddyDomainsApiInterface $api): GoDaddyProvider
    {
        return new GoDaddyProvider($api, $this->config());
    }

    private function contact(): DomainContact
    {
        return new DomainContact(
            fullName: 'John Doe',
            organization: 'Acme Inc',
            email: 'john@example.com',
            phone: '+1.5555555555',
            addressLine1: 'Street 1',
            addressLine2: null,
            city: 'Austin',
            stateOrRegion: 'TX',
            postalCode: '78701',
            countryCode: 'us',
        );
    }

    public function testMetadataAndSupportsExposeCapabilities(): void
    {
        $provider = $this->provider($this->apiMock());

        $metadata = $provider->metadata();

        self::assertSame('GoDaddy', $metadata->providerName);
        self::assertTrue($provider->supports(Capabilities::DOMAIN_REGISTRATION));
        self::assertFalse($provider->supports(Capabilities::DNS_RECORD_LIST));
    }

    public function testListSupportedTldsMapsFromTldsEndpoint(): void
    {
        $api = $this->apiMock();
        $api->expects(self::once())
            ->method('tlds')
            ->willReturn([
                'data' => [
                    ['name' => 'com'],
                    ['name' => '.NET'],
                    'org',
                    ['invalid' => 'ignored'],
                ],
            ]);

        $tlds = $this->provider($api)->listSupportedTlds();
        self::assertSame(['com', 'net', 'org'], $tlds);
    }

    public function testCheckAvailabilityMapsResponse(): void
    {
        $api = $this->apiMock();
        $api->expects(self::once())
            ->method('available')
            ->with('example.com')
            ->willReturn([
                'path' => '/v1/domains/available',
                'data' => [
                    'available' => true,
                    'premium' => true,
                    'price' => 12.99,
                    'currency' => 'USD',
                ],
            ]);

        $result = $this->provider($api)->checkAvailability(new DomainName('example.com'));

        self::assertTrue($result->available);
        self::assertTrue($result->premium);
        self::assertSame('12.99', $result->price?->amount);
        self::assertSame('USD', $result->price?->currency);
    }

    public function testRegisterDomainMapsPayloadAndResult(): void
    {
        $api = $this->apiMock();
        $api->expects(self::once())
            ->method('getAgreement')
            ->with(['com'], true, 'en-US', false)
            ->willReturn(['data' => ['agreementKeys' => ['DNRA']]]);

        $api->expects(self::once())
            ->method('registerDomainForCustomer')
            ->with(
                'cust-1',
                self::callback(function (array $body): bool {
                    return $body['domain'] === 'example.com'
                        && $body['period'] === 2
                        && $body['privacy'] === true
                        && $body['consent']['agreementKeys'] === ['DNRA']
                        && isset($body['contactRegistrant']['email']);
                }),
                null,
            )
            ->willReturn(['ok' => true, 'path' => '/register']);

        $result = $this->provider($api)->registerDomain(
            new DomainName('example.com'),
            new DomainRegistrationPeriod(2),
            $this->contact(),
            null,
            true,
            'en-US',
        );

        self::assertTrue($result->success);
        self::assertSame('register_domain.success', $result->code);
    }

    public function testRenewDomainMapsRequest(): void
    {
        $api = $this->apiMock();
        $api->expects(self::once())
            ->method('renewDomainForCustomer')
            ->with('cust-1', 'example.com', ['period' => 1], null)
            ->willReturn(['ok' => true, 'path' => '/renew']);

        $result = $this->provider($api)->renewDomain(new DomainName('example.com'), new DomainRegistrationPeriod(1));

        self::assertTrue($result->success);
        self::assertSame('renew_domain.success', $result->code);
    }

    public function testTransferDomainMapsRequest(): void
    {
        $api = $this->apiMock();
        $api->expects(self::once())
            ->method('transferDomainForCustomer')
            ->with(
                'cust-1',
                'example.com',
                self::callback(static fn (array $body): bool => $body['authCode'] === 'auth-123' && isset($body['contactRegistrant'])),
                null,
            )
            ->willReturn(['ok' => true, 'path' => '/transfer']);

        $result = $this->provider($api)->transferDomain(new DomainName('example.com'), 'auth-123', $this->contact());

        self::assertTrue($result->success);
        self::assertSame('transfer_domain.initiated', $result->code);
    }

    public function testGetDomainInfoMapsResponse(): void
    {
        $api = $this->apiMock();
        $api->expects(self::once())
            ->method('getDomainForCustomer')
            ->with('cust-1', 'example.com', null, null)
            ->willReturn([
                'path' => '/domain-info',
                'data' => [
                    'domain' => 'example.com',
                    'status' => 'ACTIVE',
                    'expires' => '2027-01-01T00:00:00Z',
                    'createdAt' => '2020-01-01T00:00:00Z',
                    'nameServers' => ['ns1.example.com', 'ns2.example.com'],
                    'transferAwayEligible' => false,
                    'privacy' => true,
                ],
            ]);

        $info = $this->provider($api)->getDomainInfo(new DomainName('example.com'));

        self::assertSame('example.com', $info->domain);
        self::assertSame('active', $info->status);
        self::assertSame('2027-01-01', $info->expirationDate);
        self::assertSame(['ns1.example.com', 'ns2.example.com'], $info->nameservers);
        self::assertTrue($info->locked);
    }

    public function testListDomainsMapsResponseItems(): void
    {
        $api = $this->apiMock();
        $api->expects(self::once())
            ->method('list')
            ->with('shopper-1', ['active'], null, 10, '1', null, null)
            ->willReturn([
                'path' => '/domains',
                'data' => [
                    ['domain' => 'one.com', 'status' => 'ACTIVE', 'expires' => '2026-06-01'],
                    ['domain' => 'two.com', 'status' => 'PENDING', 'expires' => '2026-07-01'],
                ],
            ]);

        $items = $this->provider($api)->listDomains(page: 1, pageSize: 10, status: 'active', shopperId: 'shopper-1');

        self::assertCount(2, $items);
        self::assertSame('one.com', $items[0]->domain);
        self::assertSame('active', $items[0]->status);
        self::assertSame('pending', $items[1]->status);
    }

    public function testGetNameserversUsesDomainInfo(): void
    {
        $api = $this->apiMock();
        $api->expects(self::once())
            ->method('getDomainForCustomer')
            ->willReturn([
                'data' => ['nameServers' => ['ns1.example.com', 'ns2.example.com']],
            ]);

        $result = $this->provider($api)->getNameservers(new DomainName('example.com'));

        self::assertSame(['ns1.example.com', 'ns2.example.com'], $result->nameservers);
    }

    public function testSetNameserversMapsRequest(): void
    {
        $api = $this->apiMock();
        $api->expects(self::once())
            ->method('setDomainNameserversForCustomer')
            ->with('cust-1', 'example.com', ['nameServers' => ['ns1.example.com', 'ns2.example.com']], null)
            ->willReturn(['ok' => true, 'path' => '/ns']);

        $result = $this->provider($api)->setNameservers(
            new DomainName('example.com'),
            new \DomainProviders\DTO\NameserverSet(['ns1.example.com', 'ns2.example.com'])
        );

        self::assertTrue($result->success);
    }

    public function testListDnsRecordsThrowsUnsupportedCapability(): void
    {
        $this->expectException(UnsupportedCapabilityException::class);

        $this->provider($this->apiMock())->listDnsRecords(new DomainName('example.com'));
    }

    public function testCreateDnsRecordMapsRequest(): void
    {
        $api = $this->apiMock();
        $api->expects(self::once())
            ->method('recordAdd')
            ->with('example.com', [
                ['type' => 'A', 'name' => '@', 'data' => '1.2.3.4', 'ttl' => 600],
            ], 'shopper-1')
            ->willReturn(['ok' => true, 'path' => '/dns-create']);

        $record = new DnsRecord(id: null, type: 'A', name: '@', value: '1.2.3.4', ttl: 600);
        $result = $this->provider($api)->createDnsRecord(new DomainName('example.com'), $record, 'shopper-1');

        self::assertTrue($result->success);
    }

    public function testUpdateDnsRecordMapsRequest(): void
    {
        $api = $this->apiMock();
        $api->expects(self::once())
            ->method('recordReplaceTypeName')
            ->with('example.com', 'A', '@', [
                ['type' => 'A', 'name' => '@', 'data' => '5.6.7.8', 'ttl' => 600],
            ], 'shopper-1')
            ->willReturn(['ok' => true, 'path' => '/dns-update']);

        $record = new DnsRecord(id: null, type: 'A', name: '@', value: '5.6.7.8', ttl: 600);
        $result = $this->provider($api)->updateDnsRecord(new DomainName('example.com'), $record, 'shopper-1');

        self::assertTrue($result->success);
    }

    public function testDeleteDnsRecordRequiresMatchRecord(): void
    {
        $this->expectException(DomainProviderException::class);

        $this->provider($this->apiMock())->deleteDnsRecord(new DomainName('example.com'));
    }

    public function testDeleteDnsRecordMapsRequest(): void
    {
        $api = $this->apiMock();
        $api->expects(self::once())
            ->method('recordDeleteTypeName')
            ->with('example.com', 'A', '@', 'shopper-1')
            ->willReturn(['ok' => true, 'path' => '/dns-delete']);

        $record = new DnsRecord(id: null, type: 'A', name: '@', value: '1.2.3.4', ttl: 600);
        $result = $this->provider($api)->deleteDnsRecord(new DomainName('example.com'), null, $record, 'shopper-1');

        self::assertTrue($result->success);
    }

    public function testGetDomainPricingUsesAvailabilityProbe(): void
    {
        $api = $this->apiMock();
        $api->expects(self::once())
            ->method('available')
            ->with('example.com')
            ->willReturn([
                'path' => '/pricing-probe',
                'data' => [
                    'available' => true,
                    'premium' => false,
                    'price' => 9.99,
                    'currency' => 'USD',
                ],
            ]);

        $price = $this->provider($api)->getDomainPricing(domain: new DomainName('example.com'));

        self::assertSame('USD', $price->currency);
        self::assertSame('9.99', $price->registrationPrice);
        self::assertFalse($price->premium);
    }

    public function testCheckTransferAvailabilityMapsResponse(): void
    {
        $api = $this->apiMock();
        $api->expects(self::once())
            ->method('getDomainTransferForCustomer')
            ->with('cust-1', 'example.com', null)
            ->willReturn([
                'path' => '/transfer-status',
                'data' => ['status' => 'BLOCKED', 'locked' => true, 'statusMessage' => 'locked at registry'],
            ]);

        $status = $this->provider($api)->checkTransferAvailability(new DomainName('example.com'));

        self::assertSame('blocked', $status->transferStatus);
        self::assertTrue($status->locked);
        self::assertSame(['locked at registry'], $status->reasons);
    }

    public function testErrorsAreMappedToContractCategory(): void
    {
        $api = $this->apiMock();
        $api->expects(self::once())
            ->method('available')
            ->willThrowException(new \RuntimeException('401 unauthorized'));

        try {
            $this->provider($api)->checkAvailability(new DomainName('example.com'));
            self::fail('Expected DomainProviderException was not thrown.');
        } catch (DomainProviderException $e) {
            self::assertSame(ErrorCategory::AUTHENTICATION, $e->category);
            self::assertFalse((bool) $e->retryable);
        }
    }
}
