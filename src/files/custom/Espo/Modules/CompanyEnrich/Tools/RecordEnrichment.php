<?php

namespace Espo\Modules\CompanyEnrich\Tools;

use Espo\Core\Acl;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Conflict;
use Espo\Core\Exceptions\Forbidden;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use stdClass;

class RecordEnrichment
{
    private const MAP = [
        'Account' => ['name' => 'name', 'description' => 'description'],
        'Contact' => ['firstName' => 'first_name', 'lastName' => 'last_name', 'description' => 'position'],
    ];

    public function __construct(private EntityManager $entityManager, private Acl $acl, private Provider $provider) {}

    /** @return array<string, mixed> */
    public function run(string $type, string $id): array
    {
        if (!isset(self::MAP[$type]) || !preg_match('/^[a-zA-Z0-9_-]{1,64}$/', $id)) {
            throw new BadRequest('Provide an Account or Contact record ID.');
        }
        $record = $this->entityManager->getEntityById($type, $id);
        $record = $this->checkRecord($record, $type);
        $identifierField = $type === 'Account' ? 'website' : 'emailAddress';
        $identifier = $record->get($identifierField);
        if (!is_string($identifier) || trim($identifier) === '') {
            throw new BadRequest($type === 'Account' ? 'The account needs a website.' : 'The contact needs a known email address.');
        }
        $allowed = array_filter(array_keys(self::MAP[$type]), fn ($field) =>
            $this->acl->checkField($type, $field, 'read') && $this->acl->checkField($type, $field, 'edit')
        );
        $eligible = array_filter($allowed, fn ($field) => $this->empty($record->get($field)));
        if (!$eligible) {
            return ['success' => true, 'updatedFields' => [], 'message' => 'No empty, editable fields. No CompanyEnrich request was made.'];
        }
        $snapshot = $this->snapshot($record, $identifierField, $allowed);
        $result = $this->provider->run(
            $type === 'Account' ? 'enrich-company' : 'lookup-person',
            $type === 'Account' ? (object) ['domain' => $identifier] : (object) ['body' => (object) ['email' => $identifier]],
        );
        if (!($result['data'] ?? null) instanceof stdClass || !isset($result['data']->id)) {
            throw new ProviderError('CompanyEnrich returned no identifiable profile. Nothing was saved.');
        }
        // The paid request is outside this transaction, including any deadlock retry.
        $updatedFields = $this->entityManager->getTransactionManager()->run(function () use (
            $type, $id, $identifierField, $allowed, $snapshot, $result,
        ): array {
            $current = $this->entityManager->getRDBRepository($type)->forUpdate()->where(['id' => $id])->findOne();
            $current = $this->checkRecord($current, $type);
            if ($snapshot !== $this->snapshot($current, $identifierField, $allowed)) {
                throw new Conflict('The record changed during enrichment. Nothing was saved. Check provider usage before retrying.');
            }
            $updated = [];
            foreach ($allowed as $field) {
                if (!$this->acl->checkField($type, $field, 'edit') || !$this->acl->checkField($type, $field, 'read')) continue;
                $providerField = self::MAP[$type][$field] ?? null;
                if ($providerField === null) continue;
                $value = $result['data']->$providerField ?? null;
                if ($this->empty($current->get($field)) && is_string($value) && trim($value) !== '') {
                    if ($field !== 'description' && preg_match('/[<>=]/', $value)) continue;
                    $limit = $field === 'description' ? 10000 : ($field === 'name' ? 249 : 100);
                    $current->set($field, mb_substr($value, 0, $limit));
                    $updated[] = $field;
                }
            }
            if ($updated) $this->entityManager->saveEntity($current);
            return $updated;
        });
        // Do not return a full profile through the CRM mutation route.
        return array_filter([
            'success' => true,
            'updatedFields' => $updatedFields,
            'creditsConsumed' => $result['creditsConsumed'] ?? null,
        ], fn ($value) => $value !== null);
    }

    private function checkRecord(?Entity $record, string $type): Entity
    {
        $identifierField = $type === 'Account' ? 'website' : 'emailAddress';
        if (!$record || !$this->acl->checkEntityRead($record) || !$this->acl->checkEntityEdit($record) ||
            !$this->acl->checkField($type, $identifierField, 'read')) {
            throw new Forbidden('The record or required fields are not accessible.');
        }
        return $record;
    }

    /** @param string[] $fields */
    private function snapshot(Entity $record, string $identifierField, array $fields): string
    {
        return json_encode(array_map($record->get(...), ['modifiedAt', $identifierField, ...$fields]), JSON_THROW_ON_ERROR);
    }

    private function empty(mixed $value): bool
    {
        return $value === null || $value === '';
    }
}
