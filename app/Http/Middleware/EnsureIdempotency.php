<?php

namespace App\Http\Middleware;

use App\Enums\IdempotencyStatus;
use App\Http\Idempotency\RequestFingerprint;
use App\Models\IdempotencyKey;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Makes a POST safe to retry with the same Idempotency-Key.
 *
 * The key is stored in PostgreSQL, not in Redis: the UNIQUE (tenant_id, key)
 * constraint is the lock, and a key cannot be evicted (see ADR-002).
 * Only 2xx responses are stored; anything else frees the key for a retry.
 */
class EnsureIdempotency
{
    private const HEADER = 'Idempotency-Key';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER);

        if (! is_string($key) || $key === '' || strlen($key) > 255) {
            return $this->error('The Idempotency-Key header is required (1 to 255 characters).', 400);
        }

        $hash = RequestFingerprint::fromRequest($request);

        // INSERT first, look second: of two concurrent requests, exactly one creates the
        // row; the other hits the unique constraint and gets the existing row back.
        $record = IdempotencyKey::query()->createOrFirst(
            ['key' => $key],
            ['request_hash' => $hash, 'status' => IdempotencyStatus::InFlight],
        );

        if (! $record->wasRecentlyCreated) {
            return $this->answerForExisting($record, $hash);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $record->delete();

            throw $exception;
        }

        if (! $response->isSuccessful()) {
            $record->delete();

            return $response;
        }

        $record->update([
            'status' => IdempotencyStatus::Completed,
            'response_status' => $response->getStatusCode(),
            'response_body' => $response instanceof JsonResponse ? $response->getData(true) : null,
        ]);

        return $response;
    }

    private function answerForExisting(IdempotencyKey $record, string $hash): Response
    {
        if (! hash_equals($record->request_hash, $hash)) {
            return $this->error('This Idempotency-Key was already used with a different request.', 422);
        }

        if ($record->status === IdempotencyStatus::InFlight) {
            return $this->error('A request with this Idempotency-Key is still in progress.', 409);
        }

        return response()->json(
            $record->response_body,
            $record->response_status ?? 200,
            ['Idempotent-Replayed' => 'true'],
        );
    }

    private function error(string $message, int $status): JsonResponse
    {
        return response()->json(['message' => $message], $status);
    }
}
