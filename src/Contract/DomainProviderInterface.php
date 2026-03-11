<?php
declare(strict_types=1);

namespace DomainProviders\Contract;

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

interface DomainProviderInterface
{
    public function metadata(): ProviderMetadata;

    public function supports(string $capability): bool;

    public function checkAvailability(DomainName $domain): AvailabilityResult;

    public function registerDomain(
        DomainName $domain,
        DomainRegistrationPeriod $period,
        DomainContact $registrantContact,
        ?NameserverSet $nameservers = null,
        ?bool $privacyEnabled = null,
        ?string $marketId = null,
    ): OperationResult;

    public function renewDomain(DomainName $domain, DomainRegistrationPeriod $period): OperationResult;

    public function transferDomain(
        DomainName $domain,
        string $authCode,
        ?DomainContact $registrantContact = null,
    ): OperationResult;

    public function getDomainInfo(DomainName $domain): DomainInfo;

    /** @return list<DomainInfo> */
    public function listDomains(?int $page = null, ?int $pageSize = null, ?string $status = null, ?string $shopperId = null): array;

    public function getNameservers(DomainName $domain): NameserverSet;

    public function setNameservers(DomainName $domain, NameserverSet $nameservers): OperationResult;

    /** @return list<DnsRecord> */
    public function listDnsRecords(DomainName $domain): array;

    public function createDnsRecord(DomainName $domain, DnsRecord $record, ?string $shopperId = null): OperationResult;

    public function updateDnsRecord(DomainName $domain, DnsRecord $record, ?string $shopperId = null): OperationResult;

    public function deleteDnsRecord(DomainName $domain, ?string $recordId = null, ?DnsRecord $matchRecord = null, ?string $shopperId = null): OperationResult;

    public function getDomainPricing(
        ?DomainName $domain = null,
        ?string $tld = null,
        ?DomainRegistrationPeriod $period = null,
    ): DomainPrice;

    public function checkTransferAvailability(DomainName $domain): TransferAvailabilityResult;
}
