# renewDomain

## Signature

```php
public function renewDomain(DomainName $domain, DomainRegistrationPeriod $period): OperationResult;
```

## Purpose

Renew an existing domain.

## Parameters

- `domain`: target `DomainName`
- `period`: renewal period

## Return

- `OperationResult`

## Usage

```php
$result = $provider->renewDomain(new DomainName('example.com'), new DomainRegistrationPeriod(1));
```
