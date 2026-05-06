<?php

declare(strict_types=1);

namespace App\Domain\Auth\Actions;

use App\Domain\Analytics\Actions\RecordAnalyticsEventAction;
use App\Domain\Analytics\DataTransferObjects\ResourceDownloadContextData;
use App\Domain\Analytics\Enums\EventType;
use App\Domain\Auth\DataTransferObjects\AuthTokenData;
use App\Domain\Auth\DataTransferObjects\LoginUserData;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

final class LoginUserAction
{
    public function __construct(
        private readonly RecordAnalyticsEventAction $recordAnalyticsEvent,
    ) {}

    /**
     * Lazily-computed dummy hash used to burn CPU when the user does not exist,
     * so a failed lookup is not trivially distinguishable from a wrong
     * password via wall-clock timing. Computed via the configured hasher so the
     * cost tracks `config('hashing.bcrypt.rounds')` instead of a frozen literal.
     */
    private static ?string $dummyHash = null;

    public function execute(LoginUserData $data, ?ResourceDownloadContextData $context = null): AuthTokenData
    {
        $user = User::where('email', $data->email)->first();

        if ($user === null) {
            Hash::check($data->password, self::dummyHash());

            throw new InvalidCredentialsException;
        }

        $driver = self::driverForHash($user->password);

        if ($driver === null || ! Hash::driver($driver)->check($data->password, $user->password)) {
            throw new InvalidCredentialsException;
        }

        // Disabled users present the same generic credential error so the
        // endpoint does not disclose whether an account is suspended vs.
        // simply has wrong credentials. The disable flow already revokes
        // existing tokens; this stops a re-login from minting a new one.
        if (! $user->is_active) {
            throw new InvalidCredentialsException;
        }

        if ($driver !== 'argon2id') {
            // Symfony stored some passwords as bcrypt under its `auto` hasher.
            // Re-hash to the configured Argon2id driver on first successful
            // login so legacy rows converge without forcing a password reset.
            $user->password = $data->password;
            $user->save();
        }

        $token = $user->createToken('auth')->plainTextToken;

        // The resolved request context arrives without a user_id (the
        // request is unauthenticated until the token is minted) — copy
        // it over with the freshly authenticated id so the emitted
        // event carries proper attribution.
        $eventContext = new ResourceDownloadContextData(
            userId: (int) $user->getKey(),
            deviceId: $context?->deviceId,
            language: $context?->language,
            source: $context?->source,
        );

        $this->recordAnalyticsEvent->execute(
            eventType: EventType::AuthLogin,
            context: $eventContext,
        );

        return new AuthTokenData(
            user: $user,
            plainTextToken: $token,
        );
    }

    private static function driverForHash(string $hash): ?string
    {
        return match (Hash::info($hash)['algoName'] ?? null) {
            'argon2id' => 'argon2id',
            'argon2i' => 'argon',
            'bcrypt' => 'bcrypt',
            default => null,
        };
    }

    private static function dummyHash(): string
    {
        if (self::$dummyHash === null) {
            self::$dummyHash = Hash::make('dummy-password');
        }

        return self::$dummyHash;
    }
}
