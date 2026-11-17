<?php

namespace Espo\Modules\CompanyEnrich\Tools;

use JsonException;
use stdClass;

class Provider
{
    public function __construct(private Operations $operations, private HttpTransport $transport, private Credentials $credentials) {}

    /** @return array<string, mixed> */
    public function run(string $name, stdClass $input): array
    {
        $operation = $this->operations->get($name);
        $input = $this->operations->prepare($name, $input);
        $query = [];
        foreach ((array) $input as $field => $value) {
            if ($field === 'body') continue;
            foreach (is_array($value) ? $value : [$value] as $item) {
                if ($item === null) continue;
                $query[] = rawurlencode($field) . '=' . rawurlencode(is_bool($item) ? ($item ? 'true' : 'false') : (string) $item);
            }
        }
        $url = 'https://api.companyenrich.com' . $operation->path . ($query ? '?' . implode('&', $query) : '');
        $response = $this->transport->request(
            $operation->method,
            $url,
            $this->credentials->key(),
            isset($input->body) ? json_encode($input->body, JSON_THROW_ON_ERROR) : null,
        );
        if ($response['status'] < 200 || $response['status'] >= 300) {
            $message = match ($response['status']) {
                401 => 'Ask an administrator to check the CompanyEnrich API key.',
                402 => 'Your CompanyEnrich account needs more credits.',
                404 => 'No match found. With waitForEnrichment=false, a new domain may still be processing.',
                400, 422 => 'CompanyEnrich rejected the input. Check its API documentation.',
                429 => 'CompanyEnrich rate limit reached. Wait before another request.',
                default => 'CompanyEnrich is unavailable. Check usage before retrying.',
            };
            throw new ProviderError($message);
        }
        try {
            $data = json_decode($response['body'], false, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (JsonException) {
            throw new ProviderError('CompanyEnrich returned invalid JSON. Check usage before retrying.');
        }
        $data = $this->preserveIntegers($data);
        $result = ['success' => true, 'data' => $data];
        $credits = $data instanceof stdClass && isset($data->creditsConsumed) ? $data->creditsConsumed : $response['credits'];
        if (is_int($credits) || is_float($credits)) {
            $result['creditsConsumed'] = $credits;
        }
        return $result;
    }

    private function preserveIntegers(mixed $value): mixed
    {
        if (is_int($value) && ($value > 9007199254740991 || $value < -9007199254740991)) return (string) $value;
        if (is_array($value)) return array_map($this->preserveIntegers(...), $value);
        if ($value instanceof stdClass) {
            foreach ((array) $value as $key => $item) $value->$key = $this->preserveIntegers($item);
        }
        return $value;
    }
}
