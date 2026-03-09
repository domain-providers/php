
[Docs Home](../../README.md)

## Signature

```php
public static function fromConfig(GoDaddyConfig $config): GoDaddyProvider;
```

## Purpose

Build a ready-to-use `GoDaddyProvider` instance from config values.

## Parameters

- `config`: `GoDaddyConfig`

## Return

- `GoDaddyProvider`

## Usage

```php
$provider = GoDaddyProviderFactory::fromConfig(new GoDaddyConfig(
    apiKey: 'key',
    apiSecret: 'secret',
    customerId: 'customer-id'
));
```
