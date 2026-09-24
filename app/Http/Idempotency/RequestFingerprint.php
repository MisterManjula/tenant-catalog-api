<?php

namespace App\Http\Idempotency;

use Illuminate\Http\Request;

/**
 * sha256 of method + path + canonical JSON body. Canonical means object keys are
 * sorted at every level, so {"a":1,"b":2} and {"b":2,"a":1} are the same request.
 */
final class RequestFingerprint
{
    public static function fromRequest(Request $request): string
    {
        return self::of($request->method(), $request->path(), $request->json()->all());
    }

    /**
     * @param  array<mixed>  $body
     */
    public static function of(string $method, string $path, array $body): string
    {
        $canonicalBody = json_encode(self::sortKeys($body), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        return hash('sha256', strtoupper($method)."\n".$path."\n".$canonicalBody);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function sortKeys(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(
            fn (mixed $item): mixed => is_array($item) ? self::sortKeys($item) : $item,
            $value,
        );
    }
}
