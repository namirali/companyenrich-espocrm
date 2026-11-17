<?php

namespace Espo\Modules\CompanyEnrich\Hooks\Integration;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Utils\Crypt;
use Espo\ORM\Entity;

class EncryptKey
{
    public function __construct(private Crypt $crypt) {}

    public function beforeSave(Entity $entity): void
    {
        if ($entity->getId() !== 'CompanyEnrich') return;
        $old = $entity->getFetched('data')->apiKey ?? null;
        $key = $entity->get('apiKey');
        if ($entity->get('clearApiKey') === true) {
            $entity->set('apiKey', null);
            $entity->set('enabled', false);
        } elseif ($key === null || $key === '') {
            $entity->set('apiKey', $old);
        } elseif ($key !== $old) {
            if (!is_string($key) || strlen($key) > 512 || trim($key) === '' || preg_match('/[\r\n]/', $key)) {
                throw new BadRequest('Invalid CompanyEnrich API key.');
            }
            $entity->set('apiKey', $this->crypt->encrypt(trim($key)));
        }
        $entity->set('clearApiKey', false);
        if ($entity->get('enabled') && !$entity->get('apiKey')) {
            throw new BadRequest('Set an API key before enabling CompanyEnrich.');
        }
    }
}
