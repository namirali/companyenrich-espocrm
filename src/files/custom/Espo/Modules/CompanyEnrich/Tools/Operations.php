<?php

namespace Espo\Modules\CompanyEnrich\Tools;

use stdClass;

class Operations
{
    private stdClass $specification;

    public function __construct()
    {
        $source = file_get_contents(__DIR__ . '/../Resources/operations.json');
        if ($source === false) throw new ProviderError('CompanyEnrich request schemas are unavailable.');
        $this->specification = json_decode(
            $source,
            false,
            64,
            JSON_THROW_ON_ERROR,
        );
    }

    public function get(string $name): stdClass
    {
        foreach ($this->specification->operations as $operation) {
            if ($operation->name === $name) {
                return $operation;
            }
        }

        throw new ProviderError('Unknown CompanyEnrich operation.');
    }

    public function prepare(string $name, stdClass $input): stdClass
    {
        if (strlen(json_encode($input, JSON_THROW_ON_ERROR)) > 262144) {
            throw new ProviderError('Request exceeds 256 KiB.');
        }

        $this->validate($input, $this->get($name)->inputSchema);
        $input = json_decode(json_encode($input, JSON_THROW_ON_ERROR));
        if (isset($input->domain)) {
            $input->domain = $this->domain($input->domain);
        }
        if (isset($input->id)) {
            if (in_array($name, ['get-person', 'person-education'], true)) {
                if (strlen($input->id) === 19 && strcmp($input->id, '9223372036854775807') > 0) {
                    throw new ProviderError('Person IDs must fit in a signed 64-bit integer.');
                }
            } elseif (!preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/i', $input->id)) {
                throw new ProviderError('Company IDs must be UUIDs.');
            }
        }
        if ($name === 'company-workforce' && empty($input->id) && empty($input->domain)) {
            throw new ProviderError('Provide a company ID or domain.');
        }
        if (isset($input->body)) {
            if (in_array($name, ['search-companies', 'similar-companies', 'search-people'], true)) {
                $input->body->page ??= 1;
                $input->body->pageSize ??= 10;
            }
            if (isset($input->body->page) && $input->body->page < 1) {
                throw new ProviderError('Page must be at least 1.');
            }
            if (isset($input->body->domain)) {
                $input->body->domain = $this->domain($input->body->domain);
            }
            if (isset($input->body->domains)) {
                $input->body->domains = array_map($this->domain(...), $input->body->domains);
                if ($name === 'search-people' && count($input->body->domains) > 2500) {
                    throw new ProviderError('People search accepts at most 2,500 domains.');
                }
            }
            if (str_contains($name, 'similar') && empty($input->body->domain) && empty($input->body->domains)) {
                throw new ProviderError('Provide at least one seed company domain.');
            }
            if ($name === 'lookup-person' && !filter_var($input->body->email, FILTER_VALIDATE_EMAIL)) {
                throw new ProviderError('Provide a valid known email address.');
            }
            if ($name === 'enrich-company-properties' && !array_filter(
                (array) $input->body,
                fn ($value) => is_string($value) && trim($value) !== '',
            )) {
                throw new ProviderError('Provide a company name or social identifier.');
            }
        }

        return $input;
    }

    private function domain(string $value): string
    {
        $domain = parse_url(str_contains($value, '://') ? $value : 'https://' . $value, PHP_URL_HOST);
        if (!$domain || !str_contains($domain, '.') || strlen($domain) > 253) {
            throw new ProviderError('Enter a company domain such as example.com.');
        }

        return strtolower($domain);
    }

    private function validate(mixed $value, stdClass $schema, string $path = 'input'): void
    {
        if (isset($schema->{'$ref'})) {
            $name = basename($schema->{'$ref'});
            $this->validate($value, $this->specification->components->schemas->$name, $path);
            return;
        }
        if (isset($schema->anyOf)) {
            foreach ($schema->anyOf as $choice) {
                try {
                    $this->validate($value, $choice, $path);
                    return;
                } catch (ProviderError) {}
            }
            throw new ProviderError("Invalid value at $path.");
        }
        foreach ($schema->allOf ?? [] as $choice) {
            $this->validate($value, $choice, $path);
        }
        $validType = match ($schema->type ?? null) {
            'object' => $value instanceof stdClass,
            'array' => is_array($value),
            'string' => is_string($value),
            'integer' => is_int($value),
            'number' => is_float($value) || is_int($value),
            'boolean' => is_bool($value),
            'null' => $value === null,
            default => true,
        };
        if (!$validType || (isset($schema->enum) && !in_array($value, $schema->enum, true))) {
            throw new ProviderError("Invalid value at $path.");
        }
        if ($value instanceof stdClass) {
            foreach ($schema->required ?? [] as $key) {
                if (!property_exists($value, $key)) {
                    throw new ProviderError("Missing $path.$key.");
                }
            }
            foreach ((array) $value as $key => $item) {
                if (isset($schema->properties->$key)) {
                    $this->validate($item, $schema->properties->$key, "$path.$key");
                } elseif (($schema->additionalProperties ?? true) === false) {
                    throw new ProviderError("Unknown field at $path.$key.");
                }
            }
        }
        if (is_array($value)) {
            if (count($value) < ($schema->minItems ?? 0) || count($value) > ($schema->maxItems ?? PHP_INT_MAX)) {
                throw new ProviderError("Invalid number of items at $path.");
            }
            foreach ($value as $item) {
                if (isset($schema->items)) {
                    $this->validate($item, $schema->items, $path . '[]');
                }
            }
        }
        if (is_string($value)) {
            if (mb_strlen($value) < ($schema->minLength ?? 0) || mb_strlen($value) > ($schema->maxLength ?? PHP_INT_MAX)) {
                throw new ProviderError("Invalid length at $path.");
            }
            if (isset($schema->pattern) && !preg_match('~' . str_replace('~', '\\~', $schema->pattern) . '~u', $value)) {
                throw new ProviderError("Invalid format at $path.");
            }
        }
        if (is_int($value) || is_float($value)) {
            if ($value < ($schema->minimum ?? -INF) || $value > ($schema->maximum ?? INF)) {
                throw new ProviderError("Out of range at $path.");
            }
        }
    }
}
