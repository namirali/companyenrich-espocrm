<?php

namespace Espo\Modules\CompanyEnrich\Tools;

use Espo\Core\Acl;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;

class Access
{
    public function __construct(private Acl $acl, private User $user) {}

    public function check(): void
    {
        if ($this->user->isPortal() || !$this->acl->checkScope('CompanyEnrich')) {
            throw new Forbidden('CompanyEnrich access is required.');
        }
    }
}
