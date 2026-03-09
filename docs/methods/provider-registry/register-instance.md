
[Docs Home](../../README.md)

## Signature

```php
public function registerInstance(string $key, DomainProviderInterface $provider): self;
```

## Purpose

Register a concrete provider instance by key.

## Parameters

- `key`: provider key, for example `godaddy` or `my-provider`
- `provider`: provider instance

## Return

- `ProviderRegistry` for fluent chaining
