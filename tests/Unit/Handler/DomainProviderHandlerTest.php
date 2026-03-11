<?php
declare(strict_types=1);

namespace DomainProviders\Tests\Unit\Handler;

use DomainProviders\Capabilities;
use DomainProviders\Config\ProviderConfig;
use DomainProviders\Contract\DomainProviderInterface;
use DomainProviders\Contract\TldDiscoveryInterface;
use DomainProviders\DTO\AvailabilityResult;
use DomainProviders\DTO\DnsRecord;
use DomainProviders\DTO\DomainContact;
use DomainProviders\DTO\DomainInfo;
use DomainProviders\DTO\DomainName;
use DomainProviders\DTO\DomainPrice;
use DomainProviders\DTO\DomainRegistrationPeriod;
use DomainProviders\DTO\NameserverSet;
use DomainProviders\DTO\OperationResult;
use DomainProviders\DTO\ProviderMetadata;
use DomainProviders\DTO\TransferAvailabilityResult;
use DomainProviders\Handler\DomainProviderHandler;
use PHPUnit\Framework\TestCase;

final class DomainProviderHandlerTest extends TestCase
{
    public function testRoutesByOnlyAndExceptTlds(): void
    {
        $handler = new DomainProviderHandler();

        $godaddy = new RoutingTestProvider('godaddy');
        $namecheap = new RoutingTestProvider('namecheap');

        $handler
            ->registerProvider('godaddy', $godaddy, new TestProviderConfig(exceptTlds: ['rs', 'co.rs', 'in.rs'], priority: 10))
            ->registerProvider('namecheap', $namecheap, new TestProviderConfig(onlyTlds: ['rs', 'co.rs', 'in.rs'], priority: 20));

        $com = $handler->checkAvailability(new DomainName('example.com'));
        $rs = $handler->checkAvailability(new DomainName('example.rs'));
        $coRs = $handler->checkAvailability(new DomainName('example.co.rs'));

        self::assertSame('godaddy', $com->providerReference);
        self::assertSame('namecheap', $rs->providerReference);
        self::assertSame('namecheap', $coRs->providerReference);
    }

    public function testPriorityTldsOverrideGlobalPriorityForMatchingTld(): void
    {
        $handler = new DomainProviderHandler();

        $godaddy = new RoutingTestProvider('godaddy');
        $namecheap = new RoutingTestProvider('namecheap');

        $handler
            ->registerProvider('godaddy', $godaddy, new TestProviderConfig(priority: 10))
            ->registerProvider('namecheap', $namecheap, new TestProviderConfig(priority: 50, priorityTlds: ['com']));

        $com = $handler->checkAvailability(new DomainName('example.com'));
        $net = $handler->checkAvailability(new DomainName('example.net'));

        self::assertSame('namecheap', $com->providerReference);
        self::assertSame('godaddy', $net->providerReference);
    }

    public function testPreferredProviderForTldWinsWhenEligible(): void
    {
        $handler = new DomainProviderHandler();

        $godaddy = new RoutingTestProvider('godaddy');
        $namecheap = new RoutingTestProvider('namecheap');

        $handler
            ->registerProvider('godaddy', $godaddy, new TestProviderConfig(priority: 10))
            ->registerProvider('namecheap', $namecheap, new TestProviderConfig(priority: 50))
            ->preferProviderForTld('com', 'namecheap');

        $result = $handler->checkAvailability(new DomainName('example.com'));
        self::assertSame('namecheap', $result->providerReference);
    }

    public function testListProviderTldsUsesDiscoveryInterface(): void
    {
        $handler = new DomainProviderHandler();
        $handler->registerProvider('godaddy', new RoutingTestProvider('godaddy', [Capabilities::AVAILABILITY_CHECK], ['com', 'net']));

        self::assertSame(['com', 'net'], $handler->listProviderTlds('godaddy'));
    }
}

final class TestProviderConfig extends ProviderConfig
{
}

final class RoutingTestProvider implements DomainProviderInterface, TldDiscoveryInterface
{
    /** @var array<string, bool> */
    private array $supportsMap;

    /** @param list<string> $supportedTlds */
    public function __construct(
        private readonly string $key,
        array $supports = [
            Capabilities::AVAILABILITY_CHECK,
            Capabilities::DOMAIN_REGISTRATION,
            Capabilities::DOMAIN_RENEWAL,
            Capabilities::DOMAIN_TRANSFER,
            Capabilities::DOMAIN_INFO,
            Capabilities::DOMAIN_LISTING,
            Capabilities::NAMESERVER_READ,
            Capabilities::NAMESERVER_UPDATE,
            Capabilities::DNS_RECORD_LIST,
            Capabilities::DNS_RECORD_CREATE,
            Capabilities::DNS_RECORD_UPDATE,
            Capabilities::DNS_RECORD_DELETE,
            Capabilities::PRICING_LOOKUP,
        ],
        private readonly array $supportedTlds = [],
    ) {
        $this->supportsMap = [];
        foreach ($supports as $capability) {
            $this->supportsMap[$capability] = true;
        }
    }

    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata($this->key, $this->key, 'test', null, $this->supportedTlds, []);
    }

    public function supports(string $capability): bool
    {
        return $this->supportsMap[$capability] ?? false;
    }

    public function listSupportedTlds(): array
    {
        return $this->supportedTlds;
    }

    public function checkAvailability(DomainName $domain): AvailabilityResult
    {
        return new AvailabilityResult(true, false, null, null, $this->key);
    }

    public function registerDomain(DomainName $domain, DomainRegistrationPeriod $period, DomainContact $registrantContact, ?NameserverSet $nameservers = null, ?bool $privacyEnabled = null, ?string $marketId = null): OperationResult
    {
        return new OperationResult(true, providerReference: $this->key);
    }

    public function renewDomain(DomainName $domain, DomainRegistrationPeriod $period): OperationResult
    {
        return new OperationResult(true, providerReference: $this->key);
    }

    public function transferDomain(DomainName $domain, string $authCode, ?DomainContact $registrantContact = null): OperationResult
    {
        return new OperationResult(true, providerReference: $this->key);
    }

    public function getDomainInfo(DomainName $domain): DomainInfo
    {
        return new DomainInfo($domain->full, 'active', providerReference: $this->key);
    }

    public function listDomains(?int $page = null, ?int $pageSize = null, ?string $status = null, ?string $shopperId = null): array
    {
        return [];
    }

    public function getNameservers(DomainName $domain): NameserverSet
    {
        return new NameserverSet(['ns1.example.test']);
    }

    public function setNameservers(DomainName $domain, NameserverSet $nameservers): OperationResult
    {
        return new OperationResult(true, providerReference: $this->key);
    }

    public function listDnsRecords(DomainName $domain): array
    {
        return [];
    }

    public function createDnsRecord(DomainName $domain, DnsRecord $record, ?string $shopperId = null): OperationResult
    {
        return new OperationResult(true, providerReference: $this->key);
    }

    public function updateDnsRecord(DomainName $domain, DnsRecord $record, ?string $shopperId = null): OperationResult
    {
        return new OperationResult(true, providerReference: $this->key);
    }

    public function deleteDnsRecord(DomainName $domain, ?string $recordId = null, ?DnsRecord $matchRecord = null, ?string $shopperId = null): OperationResult
    {
        return new OperationResult(true, providerReference: $this->key);
    }

    public function getDomainPricing(?DomainName $domain = null, ?string $tld = null, ?DomainRegistrationPeriod $period = null): DomainPrice
    {
        return new DomainPrice('USD', '10.00', '10.00', '10.00', null, false, 1, $this->key);
    }

    public function checkTransferAvailability(DomainName $domain): TransferAvailabilityResult
    {
        return new TransferAvailabilityResult('ready', providerReference: $this->key);
    }
}
