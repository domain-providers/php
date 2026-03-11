<?php
declare(strict_types=1);

namespace DomainProviders\Provider\GoDaddy;

use DomainProviders\Capabilities;
use DomainProviders\Contract\DomainProviderInterface;
use DomainProviders\Contract\TldDiscoveryInterface;
use DomainProviders\DTO\AvailabilityResult;
use DomainProviders\DTO\DnsRecord;
use DomainProviders\DTO\DomainContact;
use DomainProviders\DTO\DomainInfo;
use DomainProviders\DTO\DomainName;
use DomainProviders\DTO\DomainPrice;
use DomainProviders\DTO\DomainRegistrationPeriod;
use DomainProviders\DTO\Money;
use DomainProviders\DTO\NameserverSet;
use DomainProviders\DTO\OperationResult;
use DomainProviders\DTO\ProviderCapability;
use DomainProviders\DTO\ProviderMetadata;
use DomainProviders\DTO\TransferAvailabilityResult;
use DomainProviders\ErrorCategory;
use DomainProviders\Exception\DomainProviderException;
use DomainProviders\Exception\UnsupportedCapabilityException;
use DomainProviders\Statuses;

final class GoDaddyProvider implements DomainProviderInterface, TldDiscoveryInterface
{
    /** @var array<string, bool> */
    private array $capabilities = [
        Capabilities::AVAILABILITY_CHECK => true,
        Capabilities::DOMAIN_REGISTRATION => true,
        Capabilities::DOMAIN_RENEWAL => true,
        Capabilities::DOMAIN_TRANSFER => true,
        Capabilities::DOMAIN_INFO => true,
        Capabilities::DOMAIN_LISTING => true,
        Capabilities::NAMESERVER_READ => true,
        Capabilities::NAMESERVER_UPDATE => true,
        Capabilities::DNS_RECORD_LIST => false,
        Capabilities::DNS_RECORD_CREATE => true,
        Capabilities::DNS_RECORD_UPDATE => true,
        Capabilities::DNS_RECORD_DELETE => true,
        Capabilities::PRICING_LOOKUP => true,
    ];

    public function __construct(
        private readonly GoDaddyDomainsApiInterface $domainsApi,
        private readonly GoDaddyConfig $config,
    ) {
    }

    public function metadata(): ProviderMetadata
    {
        $capabilitySummary = [];
        foreach ($this->capabilities as $name => $supported) {
            $capabilitySummary[] = new ProviderCapability($name, $supported);
        }

        return new ProviderMetadata(
            providerName: 'GoDaddy',
            providerKey: 'godaddy',
            environment: $this->config->environment,
            accountReference: $this->config->customerId,
            supportedTlds: null,
            capabilitySummary: $capabilitySummary,
        );
    }

    public function supports(string $capability): bool
    {
        return $this->capabilities[$capability] ?? false;
    }

    public function listSupportedTlds(): array
    {
        try {
            $response = $this->domainsApi->tlds();
            $data = $this->extractData($response);

            if (!is_array($data)) {
                return [];
            }

            $tlds = [];
            foreach ($data as $item) {
                if (is_string($item)) {
                    $normalized = ltrim(strtolower(trim($item)), '.');
                    if ($normalized !== '') {
                        $tlds[$normalized] = true;
                    }

                    continue;
                }

                if (!is_array($item)) {
                    continue;
                }

                $candidate = $item['name'] ?? $item['tld'] ?? $item['extension'] ?? null;
                if (!is_string($candidate)) {
                    continue;
                }

                $normalized = ltrim(strtolower(trim($candidate)), '.');
                if ($normalized !== '') {
                    $tlds[$normalized] = true;
                }
            }

            return array_values(array_keys($tlds));
        } catch (\Throwable) {
            return [];
        }
    }

    public function checkAvailability(DomainName $domain): AvailabilityResult
    {
        $this->assertCapability(Capabilities::AVAILABILITY_CHECK);

        try {
            $response = $this->domainsApi->available($domain->full);
            $data = $this->extractData($response);

            $available = (bool) ($data['available'] ?? $data['domainAvailable'] ?? false);
            $premium = (bool) ($data['priceInfo']['premium'] ?? $data['premium'] ?? false);

            $price = null;
            if (isset($data['price']) || isset($data['priceInfo']['currentPrice'])) {
                $price = new Money(
                    amount: (string) ($data['priceInfo']['currentPrice'] ?? $data['price']),
                    currency: (string) ($data['currency'] ?? 'USD'),
                );
            }

            return new AvailabilityResult(
                available: $available,
                premium: $premium,
                price: $price,
                reason: $data['reason'] ?? null,
                providerReference: $response['path'] ?? null,
            );
        } catch (\Throwable $e) {
            throw $this->mapException('check_availability', $e);
        }
    }

    public function registerDomain(
        DomainName $domain,
        DomainRegistrationPeriod $period,
        DomainContact $registrantContact,
        ?NameserverSet $nameservers = null,
        ?bool $privacyEnabled = null,
        ?string $marketId = null,
    ): OperationResult {
        $this->assertCapability(Capabilities::DOMAIN_REGISTRATION);

        $body = [
            'domain' => $domain->full,
            'period' => $period->years,
            'consent' => [
                'agreedAt' => gmdate('Y-m-d\\TH:i:s\\Z'),
                'agreedBy' => 'domain-providers/php',
                'agreementKeys' => $this->fetchAgreementKeys($domain->tld, $privacyEnabled ?? false, $marketId),
            ],
            'contactAdmin' => $this->toGoDaddyContact($registrantContact),
            'contactBilling' => $this->toGoDaddyContact($registrantContact),
            'contactRegistrant' => $this->toGoDaddyContact($registrantContact),
            'contactTech' => $this->toGoDaddyContact($registrantContact),
            'renewAuto' => false,
        ];

        if ($nameservers !== null) {
            $body['nameServers'] = $nameservers->nameservers;
        }

        if ($privacyEnabled !== null) {
            $body['privacy'] = $privacyEnabled;
        }

        try {
            $response = $this->domainsApi->registerDomainForCustomer(
                customerId: $this->config->customerId,
                body: $body,
            );

            return new OperationResult(
                success: (bool) ($response['ok'] ?? true),
                message: 'Domain registration request accepted.',
                code: 'register_domain.success',
                retryable: false,
                providerReference: $response['path'] ?? null,
            );
        } catch (\Throwable $e) {
            throw $this->mapException('register_domain', $e);
        }
    }

    public function renewDomain(DomainName $domain, DomainRegistrationPeriod $period): OperationResult
    {
        $this->assertCapability(Capabilities::DOMAIN_RENEWAL);

        try {
            $response = $this->domainsApi->renewDomainForCustomer(
                customerId: $this->config->customerId,
                domain: $domain->full,
                body: ['period' => $period->years],
            );

            return new OperationResult(
                success: (bool) ($response['ok'] ?? true),
                message: 'Domain renewal request accepted.',
                code: 'renew_domain.success',
                retryable: false,
                providerReference: $response['path'] ?? null,
            );
        } catch (\Throwable $e) {
            throw $this->mapException('renew_domain', $e);
        }
    }

    public function transferDomain(
        DomainName $domain,
        string $authCode,
        ?DomainContact $registrantContact = null,
    ): OperationResult {
        $this->assertCapability(Capabilities::DOMAIN_TRANSFER);

        $body = [
            'authCode' => $authCode,
            'consent' => [
                'agreedAt' => gmdate('Y-m-d\\TH:i:s\\Z'),
                'agreedBy' => 'domain-providers/php',
                'agreementKeys' => [],
            ],
        ];

        if ($registrantContact !== null) {
            $contact = $this->toGoDaddyContact($registrantContact);
            $body['contactAdmin'] = $contact;
            $body['contactBilling'] = $contact;
            $body['contactRegistrant'] = $contact;
            $body['contactTech'] = $contact;
        }

        try {
            $response = $this->domainsApi->transferDomainForCustomer(
                customerId: $this->config->customerId,
                domain: $domain->full,
                body: $body,
            );

            return new OperationResult(
                success: (bool) ($response['ok'] ?? true),
                message: 'Domain transfer request initiated.',
                code: 'transfer_domain.initiated',
                retryable: false,
                providerReference: $response['path'] ?? null,
            );
        } catch (\Throwable $e) {
            throw $this->mapException('transfer_domain', $e);
        }
    }

    public function getDomainInfo(DomainName $domain): DomainInfo
    {
        $this->assertCapability(Capabilities::DOMAIN_INFO);

        try {
            $response = $this->domainsApi->getDomainForCustomer(
                customerId: $this->config->customerId,
                domain: $domain->full,
            );
            $data = $this->extractData($response);

            $rawStatuses = isset($data['status']) ? [(string) $data['status']] : null;
            $normalizedStatus = $this->mapStatus($data['status'] ?? null);

            return new DomainInfo(
                domain: (string) ($data['domain'] ?? $domain->full),
                status: $normalizedStatus,
                expirationDate: $this->normalizeDate($data['expires'] ?? $data['expirationDate'] ?? null),
                registrationDate: $this->normalizeDate($data['createdAt'] ?? $data['createdDate'] ?? null),
                nameservers: $data['nameServers'] ?? null,
                authCodeSupported: true,
                locked: isset($data['transferAwayEligible']) ? !(bool) $data['transferAwayEligible'] : null,
                privacyEnabled: $data['privacy'] ?? null,
                providerReference: $response['path'] ?? null,
                rawStatuses: $rawStatuses,
            );
        } catch (\Throwable $e) {
            throw $this->mapException('get_domain_info', $e);
        }
    }

    public function listDomains(?int $page = null, ?int $pageSize = null, ?string $status = null, ?string $shopperId = null): array
    {
        $this->assertCapability(Capabilities::DOMAIN_LISTING);

        try {
            $response = $this->domainsApi->list(
                xShopperId: $shopperId,
                statuses: $status !== null ? [$status] : null,
                limit: $pageSize,
                marker: $page !== null ? (string) $page : null,
            );

            $data = $this->extractData($response);
            if (!is_array($data)) {
                return [];
            }

            $items = [];
            foreach ($data as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $items[] = new DomainInfo(
                    domain: (string) ($row['domain'] ?? ''),
                    status: $this->mapStatus($row['status'] ?? null),
                    expirationDate: $this->normalizeDate($row['expires'] ?? null),
                    registrationDate: $this->normalizeDate($row['createdAt'] ?? null),
                    nameservers: $row['nameServers'] ?? null,
                    providerReference: $response['path'] ?? null,
                    rawStatuses: isset($row['status']) ? [(string) $row['status']] : null,
                );
            }

            return $items;
        } catch (\Throwable $e) {
            throw $this->mapException('list_domains', $e);
        }
    }

    public function getNameservers(DomainName $domain): NameserverSet
    {
        $this->assertCapability(Capabilities::NAMESERVER_READ);

        try {
            $info = $this->getDomainInfo($domain);
            return new NameserverSet($info->nameservers ?? []);
        } catch (\Throwable $e) {
            throw $this->mapException('get_nameservers', $e);
        }
    }

    public function setNameservers(DomainName $domain, NameserverSet $nameservers): OperationResult
    {
        $this->assertCapability(Capabilities::NAMESERVER_UPDATE);

        try {
            $response = $this->domainsApi->setDomainNameserversForCustomer(
                customerId: $this->config->customerId,
                domain: $domain->full,
                body: [
                    'nameServers' => $nameservers->nameservers,
                ],
            );

            return new OperationResult(
                success: (bool) ($response['ok'] ?? true),
                message: 'Nameservers updated.',
                code: 'set_nameservers.success',
                retryable: false,
                providerReference: $response['path'] ?? null,
            );
        } catch (\Throwable $e) {
            throw $this->mapException('set_nameservers', $e);
        }
    }

    public function listDnsRecords(DomainName $domain): array
    {
        $this->assertCapability(Capabilities::DNS_RECORD_LIST);

        throw new UnsupportedCapabilityException(Capabilities::DNS_RECORD_LIST);
    }

    public function createDnsRecord(DomainName $domain, DnsRecord $record, ?string $shopperId = null): OperationResult
    {
        $this->assertCapability(Capabilities::DNS_RECORD_CREATE);

        try {
            $response = $this->domainsApi->recordAdd(
                domain: $domain->full,
                records: [$record->toArray()],
                xShopperId: $shopperId,
            );

            return new OperationResult(
                success: (bool) ($response['ok'] ?? true),
                message: 'DNS record created.',
                code: 'create_dns_record.success',
                retryable: false,
                providerReference: $response['path'] ?? null,
            );
        } catch (\Throwable $e) {
            throw $this->mapException('create_dns_record', $e);
        }
    }

    public function updateDnsRecord(DomainName $domain, DnsRecord $record, ?string $shopperId = null): OperationResult
    {
        $this->assertCapability(Capabilities::DNS_RECORD_UPDATE);

        try {
            $response = $this->domainsApi->recordReplaceTypeName(
                domain: $domain->full,
                type: strtoupper($record->type),
                name: $record->name,
                records: [$record->toArray()],
                xShopperId: $shopperId,
            );

            return new OperationResult(
                success: (bool) ($response['ok'] ?? true),
                message: 'DNS record updated.',
                code: 'update_dns_record.success',
                retryable: false,
                providerReference: $response['path'] ?? null,
            );
        } catch (\Throwable $e) {
            throw $this->mapException('update_dns_record', $e);
        }
    }

    public function deleteDnsRecord(DomainName $domain, ?string $recordId = null, ?DnsRecord $matchRecord = null, ?string $shopperId = null): OperationResult
    {
        $this->assertCapability(Capabilities::DNS_RECORD_DELETE);

        if ($matchRecord === null) {
            throw new DomainProviderException(
                category: ErrorCategory::VALIDATION,
                message: 'GoDaddy delete requires record type and name match details.',
                codeName: 'delete_dns_record.validation',
                retryable: false,
            );
        }

        try {
            $response = $this->domainsApi->recordDeleteTypeName(
                domain: $domain->full,
                type: strtoupper($matchRecord->type),
                name: $matchRecord->name,
                xShopperId: $shopperId,
            );

            return new OperationResult(
                success: (bool) ($response['ok'] ?? true),
                message: 'DNS record deleted.',
                code: 'delete_dns_record.success',
                retryable: false,
                providerReference: $response['path'] ?? null,
            );
        } catch (\Throwable $e) {
            throw $this->mapException('delete_dns_record', $e);
        }
    }

    public function getDomainPricing(?DomainName $domain = null, ?string $tld = null, ?DomainRegistrationPeriod $period = null): DomainPrice
    {
        $this->assertCapability(Capabilities::PRICING_LOOKUP);

        if ($domain === null && $tld === null) {
            throw new DomainProviderException(
                category: ErrorCategory::VALIDATION,
                message: 'Either domain or tld is required for pricing.',
                codeName: 'get_domain_pricing.validation',
                retryable: false,
            );
        }

        $probeDomain = $domain;
        if ($probeDomain === null) {
            $probeDomain = new DomainName('example.' . strtolower((string) $tld));
        }

        $availability = $this->checkAvailability($probeDomain);
        $amount = $availability->price?->amount ?? '0';
        $currency = $availability->price?->currency ?? 'USD';
        $years = $period?->years ?? 1;

        return new DomainPrice(
            currency: $currency,
            registrationPrice: $amount,
            renewalPrice: $amount,
            transferPrice: $amount,
            restorePrice: null,
            premium: $availability->premium,
            billingPeriodYears: $years,
            providerReference: $availability->providerReference,
        );
    }

    public function checkTransferAvailability(DomainName $domain): TransferAvailabilityResult
    {
        $this->assertCapability(Capabilities::DOMAIN_TRANSFER);

        try {
            $response = $this->domainsApi->getDomainTransferForCustomer(
                customerId: $this->config->customerId,
                domain: $domain->full,
            );
            $data = $this->extractData($response);

            $state = (string) ($data['status'] ?? 'unknown');
            $blocked = in_array(strtolower($state), ['blocked', 'denied', 'failed'], true);

            return new TransferAvailabilityResult(
                transferStatus: $blocked ? 'blocked' : 'ready',
                locked: $data['locked'] ?? null,
                authCodeRequired: true,
                reasons: isset($data['statusMessage']) ? [(string) $data['statusMessage']] : null,
                providerReference: $response['path'] ?? null,
            );
        } catch (\Throwable $e) {
            throw $this->mapException('check_transfer_availability', $e);
        }
    }

    private function assertCapability(string $capability): void
    {
        if (!$this->supports($capability)) {
            throw new UnsupportedCapabilityException($capability);
        }
    }

    /** @return array<string, mixed> */
    private function extractData(mixed $response): array
    {
        if (is_array($response)) {
            if (isset($response['data']) && is_array($response['data'])) {
                return $response['data'];
            }

            return $response;
        }

        return [];
    }

    private function normalizeDate(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return substr($value, 0, 10);
    }

    private function mapStatus(mixed $providerStatus): string
    {
        $raw = strtolower((string) $providerStatus);

        return match (true) {
            str_contains($raw, 'active') => Statuses::ACTIVE,
            str_contains($raw, 'expire') => Statuses::EXPIRED,
            str_contains($raw, 'suspend') => Statuses::SUSPENDED,
            str_contains($raw, 'delete') => Statuses::DELETED,
            str_contains($raw, 'transfer') && str_contains($raw, 'pending') => Statuses::TRANSFER_PENDING,
            str_contains($raw, 'transfer') => Statuses::TRANSFERRED,
            str_contains($raw, 'pending') => Statuses::PENDING,
            $raw === '' => Statuses::UNKNOWN,
            default => Statuses::UNKNOWN,
        };
    }

    /** @return array<string, mixed> */
    private function toGoDaddyContact(DomainContact $contact): array
    {
        return [
            'nameFirst' => $this->firstName($contact->fullName),
            'nameLast' => $this->lastName($contact->fullName),
            'email' => $contact->email,
            'phone' => $contact->phone,
            'addressMailing' => [
                'address1' => $contact->addressLine1,
                'address2' => $contact->addressLine2,
                'city' => $contact->city,
                'state' => $contact->stateOrRegion,
                'postalCode' => $contact->postalCode,
                'country' => strtoupper($contact->countryCode),
            ],
            'organization' => $contact->organization,
        ];
    }

    private function firstName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        return $parts[0] ?? $fullName;
    }

    private function lastName(string $fullName): string
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        if (count($parts) < 2) {
            return $parts[0] ?? 'Unknown';
        }

        return (string) end($parts);
    }

    private function mapException(string $operation, \Throwable $e): DomainProviderException
    {
        $message = strtolower($e->getMessage());

        $category = match (true) {
            str_contains($message, '401'),
            str_contains($message, 'unauthorized') => ErrorCategory::AUTHENTICATION,
            str_contains($message, '403'),
            str_contains($message, 'forbidden') => ErrorCategory::AUTHORIZATION,
            str_contains($message, '429'),
            str_contains($message, 'rate') => ErrorCategory::RATE_LIMIT,
            str_contains($message, 'timeout') => ErrorCategory::PROVIDER_TIMEOUT,
            str_contains($message, 'not found') => ErrorCategory::DOMAIN_NOT_FOUND,
            str_contains($message, 'unavailable') => ErrorCategory::DOMAIN_UNAVAILABLE,
            str_contains($message, 'already') && str_contains($message, 'register') => ErrorCategory::DOMAIN_ALREADY_REGISTERED,
            default => ErrorCategory::PROVIDER_COMMUNICATION,
        };

        return new DomainProviderException(
            category: $category,
            message: sprintf('%s failed: %s', $operation, $e->getMessage()),
            codeName: $operation . '.' . $category,
            retryable: in_array($category, [ErrorCategory::PROVIDER_COMMUNICATION, ErrorCategory::PROVIDER_TIMEOUT, ErrorCategory::RATE_LIMIT], true),
            previous: $e,
        );
    }

    /** @return list<string> */
    private function fetchAgreementKeys(string $tld, bool $privacy, ?string $marketId = null): array
    {
        try {
            $response = $this->domainsApi->getAgreement(
                tlds: [strtolower($tld)],
                privacy: $privacy,
                xMarketId: $marketId,
                forTransfer: false,
            );
            $data = $this->extractData($response);

            if (!isset($data['agreementKeys']) || !is_array($data['agreementKeys'])) {
                return [];
            }

            return array_values(array_map(static fn (mixed $k): string => (string) $k, $data['agreementKeys']));
        } catch (\Throwable) {
            return [];
        }
    }
}
