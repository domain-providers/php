
[Docs Home](../../README.md)

## Signature

```php
public function registerFactory(string $key, callable $factory): self;
```

## Purpose

Register a provider factory callback. Use this when provider creation needs config/dependencies.

## Parameters

- `key`: provider key
- `factory`: callable returning `DomainProviderInterface`

## Return

- `ProviderRegistry`
