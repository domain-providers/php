
[Docs Home](../../README.md)

## Signature

```php
public function __construct(
    string $apiKey,
    string $apiSecret,
    string $customerId,
    string $environment = 'production',
    ?array $onlyTlds = null,
    array $exceptTlds = [],
    int $priority = 100,
    array $priorityTlds = [],
)
```

## Purpose

Create GoDaddy provider config used by `GoDaddyProviderFactory::fromConfig()`.

## Parameters

- `apiKey`: GoDaddy API key
- `apiSecret`: GoDaddy API secret
- `customerId`: customer identifier used by v2 domain operations
- `environment`: logical environment label used in metadata
- `onlyTlds`: optional allow-list for this provider (`null` means all)
- `exceptTlds`: deny-list of TLDs for this provider
- `priority`: default provider priority (lower number wins)
- `priorityTlds`: TLDs where this provider should be preferred first
