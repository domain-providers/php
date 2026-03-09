# listDnsRecords

## Signature

```php
public function listDnsRecords(DomainName $domain): array;
```

## Purpose

List DNS records for a domain when provider supports this capability.

## Parameters

- `domain`: target `DomainName`

## Return

- `list<DnsRecord>`

## Errors

- `UnsupportedCapabilityException` when provider does not support DNS listing.
