
[Docs Home](../../README.md)

## Signature

```php
public function transferDomain(
    DomainName $domain,
    string $authCode,
    ?DomainContact $registrantContact = null,
): OperationResult;
```

## Purpose

Initiate domain transfer into current provider account.

## Parameters

- `domain`: target `DomainName`
- `authCode`: transfer/EPP auth code
- `registrantContact`: optional transfer contact payload

## Return

- `OperationResult`
