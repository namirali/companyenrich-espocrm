<?php

namespace Espo\Modules\CompanyEnrich\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\CompanyEnrich\Tools\Access;
use Espo\Modules\CompanyEnrich\Tools\ProviderError;
use Espo\Modules\CompanyEnrich\Tools\RecordEnrichment;
use Throwable;

class EnrichRecord implements Action
{
    public function __construct(private Access $access, private RecordEnrichment $service) {}

    public function process(Request $request): Response
    {
        $this->access->check();
        $body = $request->getParsedBody();
        if (!is_string($body->entityType ?? null) || !is_string($body->id ?? null)) {
            throw new BadRequest('Provide entityType and id.');
        }
        try {
            $result = $this->service->run($body->entityType, $body->id);
        } catch (BadRequest | Forbidden | Conflict $error) {
            throw $error;
        } catch (ProviderError $error) {
            $result = ['success' => false, 'error' => $error->getMessage()];
        } catch (Throwable) {
            $result = ['success' => false, 'error' => 'Enrichment could not complete. Check the record and provider usage before retrying.'];
        }
        return ResponseComposer::json($result);
    }
}
