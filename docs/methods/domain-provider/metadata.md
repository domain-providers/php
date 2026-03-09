# metadata

## Signature

```php
public function metadata(): ProviderMetadata;
```

## Purpose

Return provider identity and capability summary metadata.

## Return

- `ProviderMetadata`

## Usage

```php
$metadata = $provider->metadata();
$providerName = $metadata->providerName;
```
