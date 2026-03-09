# get

## Signature

```php
public function get(string $key): DomainProviderInterface;
```

## Purpose

Resolve a provider instance by key.

## Parameters

- `key`: provider key

## Return

- `DomainProviderInterface`

## Errors

- `ProviderNotFoundException` when key is missing.
- `InvalidArgumentException` when factory returns invalid type.
