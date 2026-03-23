<?php

namespace LaraSpan\Client\Support;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Throwable;

class UserProvider
{
    private ?Closure $resolver = null;

    private ?array $rememberedUser = null;

    /**
     * Set a custom resolver that receives the Authenticatable and returns user details.
     *
     * @param  Closure(Authenticatable): array{id: mixed, name?: mixed, email?: mixed}  $callback
     */
    public function setResolver(Closure $callback): void
    {
        $this->resolver = $callback;
    }

    /**
     * Resolve the current authenticated user details.
     *
     * @return array{id: mixed, name: mixed, email: mixed}|null
     */
    public function resolve(): ?array
    {
        try {
            if (! function_exists('auth')) {
                return $this->getRemembered();
            }

            $auth = auth();

            if (! $auth->check()) {
                return $this->getRemembered();
            }

            $user = $auth->user();

            if ($user === null) {
                return $this->getRemembered();
            }

            return $this->resolveDetails($user);
        } catch (Throwable) {
            return $this->getRemembered();
        }
    }

    /**
     * Remember a user for later retrieval (e.g., after logout).
     */
    public function remember(Authenticatable $user): void
    {
        $this->rememberedUser = $this->resolveDetails($user);
    }

    /**
     * Get the remembered user details if no current auth is available.
     *
     * @return array{id: mixed, name: mixed, email: mixed}|null
     */
    public function getRemembered(): ?array
    {
        return $this->rememberedUser;
    }

    /**
     * Resolve user details from an Authenticatable instance.
     *
     * @return array{id: mixed, name: mixed, email: mixed}
     */
    private function resolveDetails(Authenticatable $user): array
    {
        if ($this->resolver !== null) {
            $details = ($this->resolver)($user);

            return [
                'id' => $details['id'] ?? $user->getAuthIdentifier(),
                'name' => $details['name'] ?? null,
                'email' => $details['email'] ?? null,
            ];
        }

        return [
            'id' => $user->getAuthIdentifier(),
            'name' => $user->name ?? null,
            'email' => $user->email ?? null,
        ];
    }
}
