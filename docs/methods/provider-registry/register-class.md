# registerClass

## Signature

```php
public function registerClass(string $key, string $providerClass): self;
```

## Purpose

Register a provider class name that can be instantiated with no constructor arguments.

## Parameters

- `key`: provider key
- `providerClass`: class-string implementing `DomainProviderInterface`

## Return

- `ProviderRegistry`

## Errors

- `InvalidArgumentException` if class does not implement `DomainProviderInterface`.
