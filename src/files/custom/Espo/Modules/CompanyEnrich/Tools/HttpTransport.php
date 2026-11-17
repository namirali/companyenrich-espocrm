<?php

namespace Espo\Modules\CompanyEnrich\Tools;

class HttpTransport
{
    /** @return array{status: int, body: string, credits: ?float} */
    public function request(string $method, string $url, string $key, ?string $body): array
    {
        if (!in_array($method, ['GET', 'POST'], true)) {
            throw new ProviderError('Unsupported provider method.');
        }
        $response = '';
        $tooLarge = false;
        $credits = null;
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Accept: application/json', 'Content-Type: application/json'],
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 50,
            CURLOPT_WRITEFUNCTION => function ($handle, string $chunk) use (&$response, &$tooLarge): int {
                if (strlen($response) + strlen($chunk) > 8 * 1024 * 1024) {
                    $tooLarge = true;
                    return 0;
                }
                $response .= $chunk;
                return strlen($chunk);
            },
            CURLOPT_HEADERFUNCTION => function ($handle, string $header) use (&$credits): int {
                if (preg_match('/^x-credit-cost:\s*([\d.]+)\s*$/i', trim($header), $match)) {
                    $credits = (float) $match[1];
                }
                return strlen($header);
            },
        ]);
        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }
        $ok = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        unset($curl);
        if ($tooLarge) {
            throw new ProviderError('CompanyEnrich response exceeded 8 MiB. Reduce the page size.');
        }
        if ($ok === false) {
            throw new ProviderError('CompanyEnrich request did not complete. It may have consumed credits; check usage before retrying.');
        }
        return ['status' => $status, 'body' => $response, 'credits' => $credits];
    }
}
