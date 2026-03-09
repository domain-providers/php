
[Docs Home](../../README.md)

## Signature

```php
public function getDomainPricing(
    ?DomainName $domain = null,
    ?string $tld = null,
    ?DomainRegistrationPeriod $period = null,
): DomainPrice;
```

## Purpose

Get normalized domain pricing for a domain or TLD.

## Parameters

- `domain`: optional concrete `DomainName`
- `tld`: optional TLD key when domain is not supplied
- `period`: optional period in years

## Return

- `DomainPrice`

## Errors

- Validation error when both `domain` and `tld` are `null`.
