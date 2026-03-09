
[Docs Home](../../README.md)

## Signature

```php
public function deleteDnsRecord(DomainName $domain, ?string $recordId = null, ?DnsRecord $matchRecord = null): OperationResult;
```

## Purpose

Delete a DNS record.

## Parameters

- `domain`: target `DomainName`
- `recordId`: optional provider record ID
- `matchRecord`: optional typed match context

## Return

- `OperationResult`

## Notes

Some providers require `matchRecord` if ID-based deletion is not supported.
