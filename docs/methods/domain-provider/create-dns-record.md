
[Docs Home](../../README.md)

## Signature

```php
public function createDnsRecord(DomainName $domain, DnsRecord $record, ?string $shopperId = null): OperationResult;
```

## Purpose

Create a DNS record in a domain zone.

## Parameters

- `domain`: target `DomainName`
- `record`: `DnsRecord`
- `shopperId`: optional shopper scope header value

## Return

- `OperationResult`
