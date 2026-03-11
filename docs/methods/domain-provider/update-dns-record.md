
[Docs Home](../../README.md)

## Signature

```php
public function updateDnsRecord(DomainName $domain, DnsRecord $record, ?string $shopperId = null): OperationResult;
```

## Purpose

Update an existing DNS record.

## Parameters

- `domain`: target `DomainName`
- `record`: updated `DnsRecord`
- `shopperId`: optional shopper scope header value

## Return

- `OperationResult`
