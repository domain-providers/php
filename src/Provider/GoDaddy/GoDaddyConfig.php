<?php
declare(strict_types=1);

namespace DomainProviders\Provider\GoDaddy;

use DomainProviders\Config\ProviderConfig;

class GoDaddyConfig extends ProviderConfig
{
    /**
     * @param list<string>|null $onlyTlds
     * @param list<string> $exceptTlds
     * @param list<string> $priorityTlds
     */
    public function __construct(
        public readonly string $apiKey,
        public readonly string $apiSecret,
        public readonly string $customerId,
        public readonly string $environment = 'production',
        ?array $onlyTlds = null,
        array $exceptTlds = [],
        int $priority = 100,
        array $priorityTlds = [],
    ) {
        parent::__construct($onlyTlds, $exceptTlds, $priority, $priorityTlds);
    }
}
