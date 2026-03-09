# checkAvailability

## Signature

```php
public function checkAvailability(DomainName $domain): AvailabilityResult;
```

## Purpose

Check domain registration availability and optional pricing hints.

## Parameters

- `domain`: `DomainName`

## Return

- `AvailabilityResult`

## Usage

```php
$result = $provider->checkAvailability(new DomainName('example.com'));
if ($result->available) {
    // proceed
}
```
