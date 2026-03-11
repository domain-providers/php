<?php
declare(strict_types=1);

namespace DomainProviders\Handler;

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
use DomainProviders\DTO\ProviderCapability;
use DomainProviders\DTO\ProviderMetadata;
use DomainProviders\DTO\TransferAvailabilityResult;
use DomainProviders\Registry\ProviderNotFoundException;

final class DomainProviderHandler implements DomainProviderInterface
{
    /** @var array<string, ProviderRegistration> */
    private array $registrations = [];

    /** @var array<string, string> */
    private array $preferredProviderByTld = [];

    public function registerProvider(string $key, DomainProviderInterface $provider, ?ProviderConfig $config = null): self
    {
        $this->registrations[$key] = new ProviderRegistration($key, $provider, $config);
        return $this;
    }

    public function preferProviderForTld(string $tld, string $providerKey): self
    {
        $this->preferredProviderByTld[ProviderConfig::normalizeTld($tld)] = $providerKey;
        return $this;
    }

    public function provider(string $key): DomainProviderInterface
    {
        if (!isset($this->registrations[$key])) {
            throw new ProviderNotFoundException(sprintf('Provider "%s" is not registered.', $key));
        }

        return $this->registrations[$key]->provider;
    }

    /** @return list<string> */
    public function registeredProviderKeys(): array
    {
        return array_values(array_keys($this->registrations));
    }

    /** @return list<string> */
    public function listProviderTlds(string $providerKey): array
    {
        $provider = $this->provider($providerKey);
        if (!$provider instanceof TldDiscoveryInterface) {
            return [];
        }

        return $provider->listSupportedTlds();
    }

    public function metadata(): ProviderMetadata
    {
        $supportedTlds = [];
        foreach ($this->registrations as $registration) {
            $provider = $registration->provider;
            if (!$provider instanceof TldDiscoveryInterface) {
                continue;
            }

            foreach ($provider->listSupportedTlds() as $tld) {
                $supportedTlds[ProviderConfig::normalizeTld($tld)] = true;
            }
        }

        $capabilitySummary = [];
        foreach ($this->allCapabilities() as $capability) {
            $capabilitySummary[] = new ProviderCapability($capability, $this->supports($capability));
        }

        return new ProviderMetadata(
            providerName: 'DomainProviderHandler',
            providerKey: 'domain-provider-handler',
            environment: 'mixed',
            accountReference: null,
            supportedTlds: $supportedTlds === [] ? null : array_values(array_keys($supportedTlds)),
            capabilitySummary: $capabilitySummary,
        );
    }

    public function supports(string $capability): bool
    {
        foreach ($this->registrations as $registration) {
            if ($registration->provider->supports($capability)) {
                return true;
            }
        }

        return false;
    }

    public function checkAvailability(DomainName $domain): AvailabilityResult
    {
        return $this->selectForDomain($domain, Capabilities::AVAILABILITY_CHECK)->checkAvailability($domain);
    }

    public function registerDomain(
        DomainName $domain,
        DomainRegistrationPeriod $period,
        DomainContact $registrantContact,
        ?NameserverSet $nameservers = null,
        ?bool $privacyEnabled = null,
        ?string $marketId = null,
    ): OperationResult {
        return $this->selectForDomain($domain, Capabilities::DOMAIN_REGISTRATION)
            ->registerDomain($domain, $period, $registrantContact, $nameservers, $privacyEnabled, $marketId);
    }

    public function renewDomain(DomainName $domain, DomainRegistrationPeriod $period): OperationResult
    {
        return $this->selectForDomain($domain, Capabilities::DOMAIN_RENEWAL)->renewDomain($domain, $period);
    }

    public function transferDomain(
        DomainName $domain,
        string $authCode,
        ?DomainContact $registrantContact = null,
    ): OperationResult {
        return $this->selectForDomain($domain, Capabilities::DOMAIN_TRANSFER)
            ->transferDomain($domain, $authCode, $registrantContact);
    }

    public function getDomainInfo(DomainName $domain): DomainInfo
    {
        return $this->selectForDomain($domain, Capabilities::DOMAIN_INFO)->getDomainInfo($domain);
    }

    public function listDomains(?int $page = null, ?int $pageSize = null, ?string $status = null, ?string $shopperId = null): array
    {
        return $this->selectByCapability(Capabilities::DOMAIN_LISTING)->listDomains($page, $pageSize, $status, $shopperId);
    }

    public function getNameservers(DomainName $domain): NameserverSet
    {
        return $this->selectForDomain($domain, Capabilities::NAMESERVER_READ)->getNameservers($domain);
    }

    public function setNameservers(DomainName $domain, NameserverSet $nameservers): OperationResult
    {
        return $this->selectForDomain($domain, Capabilities::NAMESERVER_UPDATE)->setNameservers($domain, $nameservers);
    }

    public function listDnsRecords(DomainName $domain): array
    {
        return $this->selectForDomain($domain, Capabilities::DNS_RECORD_LIST)->listDnsRecords($domain);
    }

    public function createDnsRecord(DomainName $domain, DnsRecord $record, ?string $shopperId = null): OperationResult
    {
        return $this->selectForDomain($domain, Capabilities::DNS_RECORD_CREATE)->createDnsRecord($domain, $record, $shopperId);
    }

    public function updateDnsRecord(DomainName $domain, DnsRecord $record, ?string $shopperId = null): OperationResult
    {
        return $this->selectForDomain($domain, Capabilities::DNS_RECORD_UPDATE)->updateDnsRecord($domain, $record, $shopperId);
    }

    public function deleteDnsRecord(DomainName $domain, ?string $recordId = null, ?DnsRecord $matchRecord = null, ?string $shopperId = null): OperationResult
    {
        return $this->selectForDomain($domain, Capabilities::DNS_RECORD_DELETE)
            ->deleteDnsRecord($domain, $recordId, $matchRecord, $shopperId);
    }

    public function getDomainPricing(
        ?DomainName $domain = null,
        ?string $tld = null,
        ?DomainRegistrationPeriod $period = null,
    ): DomainPrice {
        if ($domain !== null) {
            return $this->selectForDomain($domain, Capabilities::PRICING_LOOKUP)->getDomainPricing($domain, $tld, $period);
        }

        if ($tld !== null) {
            $probe = new DomainName(sprintf('pricing-probe.%s', ProviderConfig::normalizeTld($tld)));
            return $this->selectForDomain($probe, Capabilities::PRICING_LOOKUP)->getDomainPricing($domain, $tld, $period);
        }

        return $this->selectByCapability(Capabilities::PRICING_LOOKUP)->getDomainPricing($domain, $tld, $period);
    }

    public function checkTransferAvailability(DomainName $domain): TransferAvailabilityResult
    {
        return $this->selectForDomain($domain, Capabilities::DOMAIN_TRANSFER)->checkTransferAvailability($domain);
    }

    private function selectForDomain(DomainName $domain, string $capability): DomainProviderInterface
    {
        $tld = $this->resolveRoutingTld($domain);
        $preferredProvider = $this->preferredProviderByTld[$tld] ?? null;

        if ($preferredProvider !== null && isset($this->registrations[$preferredProvider])) {
            $registration = $this->registrations[$preferredProvider];
            $config = $registration->config;

            if ($registration->provider->supports($capability) && ($config === null || $config->matchesTld($tld))) {
                return $registration->provider;
            }
        }

        $candidates = [];
        $index = 0;
        foreach ($this->registrations as $registration) {
            $index++;

            if (!$registration->provider->supports($capability)) {
                continue;
            }

            $config = $registration->config;
            if ($config !== null && !$config->matchesTld($tld)) {
                continue;
            }

            $candidates[] = [
                'priority_tld' => $config?->isPriorityTld($tld) === true ? 0 : 1,
                'priority' => $config?->priority ?? 100,
                'index' => $index,
                'provider' => $registration->provider,
            ];
        }

        if ($candidates === []) {
            throw new ProviderNotFoundException(sprintf('No provider matches capability "%s" for TLD "%s".', $capability, $tld));
        }

        usort(
            $candidates,
            static fn (array $a, array $b): int => [$a['priority_tld'], $a['priority'], $a['index']] <=> [$b['priority_tld'], $b['priority'], $b['index']]
        );

        return $candidates[0]['provider'];
    }

    private function selectByCapability(string $capability): DomainProviderInterface
    {
        $candidates = [];
        $index = 0;
        foreach ($this->registrations as $registration) {
            $index++;

            if (!$registration->provider->supports($capability)) {
                continue;
            }

            $candidates[] = [
                'priority' => $registration->config?->priority ?? 100,
                'index' => $index,
                'provider' => $registration->provider,
            ];
        }

        if ($candidates === []) {
            throw new ProviderNotFoundException(sprintf('No provider matches capability "%s".', $capability));
        }

        usort(
            $candidates,
            static fn (array $a, array $b): int => [$a['priority'], $a['index']] <=> [$b['priority'], $b['index']]
        );

        return $candidates[0]['provider'];
    }

    private function resolveRoutingTld(DomainName $domain): string
    {
        $full = strtolower($domain->full);
        $knownTlds = [];

        foreach ($this->preferredProviderByTld as $tld => $_providerKey) {
            $knownTlds[$tld] = strlen($tld);
        }

        foreach ($this->registrations as $registration) {
            $config = $registration->config;
            if ($config === null) {
                continue;
            }

            foreach ($config->normalizedOnlyTlds() ?? [] as $tld) {
                $knownTlds[$tld] = strlen($tld);
            }

            foreach ($config->normalizedExceptTlds() as $tld) {
                $knownTlds[$tld] = strlen($tld);
            }

            foreach ($config->normalizedPriorityTlds() as $tld) {
                $knownTlds[$tld] = strlen($tld);
            }
        }

        arsort($knownTlds);

        foreach (array_keys($knownTlds) as $candidate) {
            if ($full === $candidate || str_ends_with($full, '.' . $candidate)) {
                return $candidate;
            }
        }

        return ProviderConfig::normalizeTld($domain->tld);
    }

    /** @return list<string> */
    private function allCapabilities(): array
    {
        return [
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
        ];
    }
}
