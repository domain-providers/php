# registerDomain

## Signature

```php
public function registerDomain(
    DomainName $domain,
    DomainRegistrationPeriod $period,
    DomainContact $registrantContact,
    ?NameserverSet $nameservers = null,
    ?bool $privacyEnabled = null,
): OperationResult;
```

## Purpose

Register a domain for the configured provider account context.

## Parameters

- `domain`: target `DomainName`
- `period`: `DomainRegistrationPeriod` in years
- `registrantContact`: `DomainContact`
- `nameservers`: optional `NameserverSet`
- `privacyEnabled`: optional privacy flag

## Return

- `OperationResult`

## Usage

```php
$result = $provider->registerDomain($domain, new DomainRegistrationPeriod(1), $contact);
```
