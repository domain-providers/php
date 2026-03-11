<?php
declare(strict_types=1);

namespace DomainProviders\Tests\Fixtures;

use DomainProviders\Contract\DomainProviderInterface;
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

final class FakeProvider implements DomainProviderInterface
{
    public function metadata(): ProviderMetadata
    {
        return new ProviderMetadata('Fake', 'fake', 'test', null, null, []);
    }

    public function supports(string $capability): bool
    {
        return true;
    }

    public function checkAvailability(DomainName $domain): AvailabilityResult
    {
        return new AvailabilityResult(true, false);
    }

    public function registerDomain(DomainName $domain, DomainRegistrationPeriod $period, DomainContact $registrantContact, ?NameserverSet $nameservers = null, ?bool $privacyEnabled = null, ?string $marketId = null): OperationResult
    {
        return new OperationResult(true);
    }

    public function renewDomain(DomainName $domain, DomainRegistrationPeriod $period): OperationResult
    {
        return new OperationResult(true);
    }

    public function transferDomain(DomainName $domain, string $authCode, ?DomainContact $registrantContact = null): OperationResult
    {
        return new OperationResult(true);
    }

    public function getDomainInfo(DomainName $domain): DomainInfo
    {
        return new DomainInfo($domain->full, 'active');
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
        return new OperationResult(true);
    }

    public function listDnsRecords(DomainName $domain): array
    {
        return [];
    }

    public function createDnsRecord(DomainName $domain, DnsRecord $record, ?string $shopperId = null): OperationResult
    {
        return new OperationResult(true);
    }

    public function updateDnsRecord(DomainName $domain, DnsRecord $record, ?string $shopperId = null): OperationResult
    {
        return new OperationResult(true);
    }

    public function deleteDnsRecord(DomainName $domain, ?string $recordId = null, ?DnsRecord $matchRecord = null, ?string $shopperId = null): OperationResult
    {
        return new OperationResult(true);
    }

    public function getDomainPricing(?DomainName $domain = null, ?string $tld = null, ?DomainRegistrationPeriod $period = null): DomainPrice
    {
        return new DomainPrice('USD', '10.00', '10.00', '10.00', null, false, 1);
    }

    public function checkTransferAvailability(DomainName $domain): TransferAvailabilityResult
    {
        return new TransferAvailabilityResult('ready');
    }
}
