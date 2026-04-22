<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Auth\Actions;

use App\Domain\Auth\Actions\ResetPasswordAction;
use App\Domain\Auth\DataTransferObjects\ResetPasswordData;
use App\Domain\Auth\Exceptions\InvalidPasswordResetTokenException;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ResetPasswordActionTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function failureStatusProvider(): array
    {
        return [
            'invalid user' => [PasswordBroker::INVALID_USER],
            'invalid token' => [PasswordBroker::INVALID_TOKEN],
            'throttled' => [PasswordBroker::RESET_THROTTLED],
        ];
    }

    #[DataProvider('failureStatusProvider')]
    public function test_it_throws_on_each_non_success_broker_status(string $status): void
    {
        $this->swapBrokerWithStub($status);

        $action = new ResetPasswordAction;

        $this->expectException(InvalidPasswordResetTokenException::class);

        $action->execute(new ResetPasswordData(
            email: 'jane@example.com',
            token: 'whatever',
            password: 'brand-new-pass',
        ));
    }

    public function test_it_returns_silently_on_password_reset_status(): void
    {
        $this->swapBrokerWithStub(PasswordBroker::PASSWORD_RESET);

        $action = new ResetPasswordAction;

        $action->execute(new ResetPasswordData(
            email: 'jane@example.com',
            token: 'whatever',
            password: 'brand-new-pass',
        ));

        $this->expectNotToPerformAssertions();
    }

    private function swapBrokerWithStub(string $status): void
    {
        $broker = new class($status) implements PasswordBroker
        {
            public function __construct(private readonly string $status) {}

            public function sendResetLink(array $credentials, ?\Closure $callback = null): string
            {
                return $this->status;
            }

            public function reset(#[\SensitiveParameter] array $credentials, \Closure $callback): string
            {
                return $this->status;
            }

            public function getUser(array $credentials): ?CanResetPassword
            {
                throw new \LogicException('Not needed in this stub.');
            }

            public function createToken(CanResetPassword $user): string
            {
                return 'stub';
            }

            public function deleteToken(CanResetPassword $user): void {}

            public function tokenExists(CanResetPassword $user, #[\SensitiveParameter] string $token): bool
            {
                return true;
            }

            public function getRepository(): TokenRepositoryInterface
            {
                throw new \LogicException('Not needed in this stub.');
            }
        };

        $factory = new class($broker) implements PasswordBrokerFactory
        {
            public function __construct(private readonly PasswordBroker $broker) {}

            public function broker($name = null): PasswordBroker
            {
                return $this->broker;
            }
        };

        Password::swap($factory);
    }
}
