<?php

namespace Espo\Modules\CompanyEnrich\Tools;

use Espo\ORM\EntityManager;
use Espo\Core\Utils\Crypt;

class Credentials
{
    public function __construct(private EntityManager $entityManager, private Crypt $crypt) {}

    public function key(): string
    {
        $integration = $this->entityManager->getEntityById('Integration', 'CompanyEnrich');
        if (!$integration?->get('enabled') || !$integration->get('apiKey')) {
            throw new ProviderError('Ask an administrator to enable CompanyEnrich and set its API key.');
        }
        $key = trim($this->crypt->decrypt($integration->get('apiKey')));
        if ($key === '' || preg_match('/[\r\n]/', $key)) {
            throw new ProviderError('Ask an administrator to replace the CompanyEnrich API key.');
        }
        return $key;
    }
}
