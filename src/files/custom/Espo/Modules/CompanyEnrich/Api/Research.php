<?php

namespace Espo\Modules\CompanyEnrich\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\CompanyEnrich\Tools\Access;
use Espo\Modules\CompanyEnrich\Tools\Provider;
use Espo\Modules\CompanyEnrich\Tools\ProviderError;
use stdClass;
use Throwable;

class Research implements Action
{
    public function __construct(private Access $access, private Provider $provider) {}

    public function process(Request $request): Response
    {
        $this->access->check();
        $body = $request->getParsedBody();
        if (!is_string($body->operation ?? null) || !($body->input ?? null) instanceof stdClass) {
            throw new BadRequest('Provide an operation name and input object.');
        }
        try {
            $result = $this->provider->run($body->operation, $body->input);
        } catch (ProviderError $error) {
            $result = ['success' => false, 'error' => $error->getMessage()];
        } catch (Throwable) {
            $result = ['success' => false, 'error' => 'CompanyEnrich could not complete the request. Check usage before retrying.'];
        }
        return ResponseComposer::json($result);
    }
}
