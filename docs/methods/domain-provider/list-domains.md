
[Docs Home](../../README.md)

## Signature

```php
public function listDomains(?int $page = null, ?int $pageSize = null, ?string $status = null): array;
```

## Purpose

List domains for provider account context when supported.

## Parameters

- `page`: optional page marker/index
- `pageSize`: optional page size
- `status`: optional status filter

## Return

- `list<DomainInfo>`
