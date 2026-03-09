
[Docs Home](../../README.md)

## Signature

```php
public function supports(string $capability): bool;
```

## Purpose

Check if a provider supports a specific capability.

## Parameters

- `capability`: capability key from `DomainProviders\Capabilities`

## Return

- `bool`

## Usage

```php
if ($provider->supports(\DomainProviders\Capabilities::DOMAIN_RENEWAL)) {
    // safe to call renewDomain
}
```
