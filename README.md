# community-sdks/domain-providers-php

PHP implementation of the [`contracts`](https://github.com/community-sdks/contracts/tree/main/domain-providers) specification.

This package provides:

- contract-aligned DTOs and operation interfaces
- capability-aware provider abstraction
- first provider adapter for GoDaddy via `community-sdks/godaddy-php`

## Install

```bash
composer require community-sdks/domain-providers-php
```

GoDaddy support is included out of the box.

## Quick start (GoDaddy)

```php
<?php

declare(strict_types=1);

use DomainProviders\DTO\DomainName;
use DomainProviders\DTO\DomainRegistrationPeriod;
use DomainProviders\Handler\DomainProviderHandler;
use DomainProviders\Provider\GoDaddy\GoDaddyConfig;
use DomainProviders\Provider\GoDaddy\GoDaddyProviderFactory;

$config = new GoDaddyConfig(
    apiKey: 'your-key',
    apiSecret: 'your-secret',
    customerId: 'your-customer-id',
    // Routing fields shared by all ProviderConfig implementations:
    onlyTlds: null,
    exceptTlds: ['rs', 'co.rs', 'in.rs'],
    priority: 20,
    priorityTlds: ['net'],
);

$provider = GoDaddyProviderFactory::fromConfig($config);

$availability = $provider->checkAvailability(new DomainName('example.com'));

if ($availability->available) {
    $result = $provider->renewDomain(
        new DomainName('example.com'),
        new DomainRegistrationPeriod(1)
    );
}

// Request-scoped values like shopper and market are passed where needed:
$domains = $provider->listDomains(status: 'active', shopperId: 'optional-shopper-id');
```

## Provider-agnostic routing handler

Use `DomainProviderHandler` to register multiple providers and route by TLD rules.

```php
<?php

declare(strict_types=1);

use DomainProviders\DTO\DomainName;
use DomainProviders\Handler\DomainProviderHandler;
use DomainProviders\Provider\GoDaddy\GoDaddyConfig;
use DomainProviders\Provider\GoDaddy\GoDaddyProviderFactory;
use Vendor\Namecheap\NamecheapConfig;
use Vendor\Namecheap\NamecheapProviderFactory;

$godaddyConfig = new GoDaddyConfig(
    apiKey: 'gd-key',
    apiSecret: 'gd-secret',
    customerId: 'gd-customer',
    exceptTlds: ['rs', 'co.rs', 'in.rs'],
    priority: 20,
);

$namecheapConfig = new NamecheapConfig(
    apiKey: 'nc-key',
    apiSecret: 'nc-secret',
    onlyTlds: ['rs', 'co.rs', 'in.rs'],
    priority: 10,
    priorityTlds: ['com'],
);

$handler = (new DomainProviderHandler())
    ->registerProvider('godaddy', GoDaddyProviderFactory::fromConfig($godaddyConfig), $godaddyConfig)
    ->registerProvider('namecheap', NamecheapProviderFactory::fromConfig($namecheapConfig), $namecheapConfig)
    ->preferProviderForTld('com', 'namecheap');

$availability = $handler->checkAvailability(new DomainName('example.co.rs')); // routed to namecheap
$comAvailability = $handler->checkAvailability(new DomainName('example.com')); // prefers namecheap

// If provider supports TLD discovery, you can inspect its live TLD list.
$godaddyTlds = $handler->listProviderTlds('godaddy');
```

Routing decision order for domain-based operations:

- explicit `preferProviderForTld()` match
- `onlyTlds` and `exceptTlds` filtering
- `priorityTlds` match
- numeric `priority` (lower number = higher priority)

## Contract coverage

This package includes contract methods for:

- check availability
- register domain
- renew domain
- transfer domain
- get domain info
- list domains
- get/set nameservers
- list/create/update/delete DNS records
- get pricing
- check transfer availability
- provider metadata and capabilities

Unsupported provider operations are reported through `UnsupportedCapabilityException`.

## Method docs

Detailed method-by-method documentation is available in:

- [`docs/README.md`](docs/README.md)
- [`docs/methods/domain-provider/index.md`](docs/methods/domain-provider/index.md)
- [`docs/methods/provider-registry/index.md`](docs/methods/provider-registry/index.md)
- [`docs/methods/godaddy-provider-factory/index.md`](docs/methods/godaddy-provider-factory/index.md)
- [`docs/methods/godaddy-config/index.md`](docs/methods/godaddy-config/index.md)
- [`docs/methods/godaddy-domains-api-interface/index.md`](docs/methods/godaddy-domains-api-interface/index.md)

## Custom providers outside this package

You can register custom providers (class, instance, or factory) using `ProviderRegistry`.

```php
<?php

use DomainProviders\Registry\ProviderRegistry;
use Vendor\Custom\MyProvider;

$registry = new ProviderRegistry();

$registry->registerClass('my-provider', MyProvider::class);
// or registerInstance('my-provider', new MyProvider())
// or registerFactory('my-provider', fn() => new MyProvider($deps))

$provider = $registry->get('my-provider');
```

## Provider notes (GoDaddy)

- The `community-sdks/godaddy-php` client currently exposes DNS retrieval by `{type}/{name}` path, not a full zone list endpoint in this adapter.
- Because of that, `dns_record_list` is declared unsupported for now, while create/update/delete DNS operations are supported.
