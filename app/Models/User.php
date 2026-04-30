<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\Notifications\PasswordResetNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property list<string> $roles
 * @property bool $is_super
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'roles',
        'is_super',
        'language',
        'preferred_version',
        'avatar',
        'last_login',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login' => 'datetime',
            'password' => 'hashed',
            'roles' => 'array',
            'is_super' => 'boolean',
        ];
    }

    /**
     * Public URL for the stored avatar on the `avatars` disk, or `null` when
     * the user has no avatar. The `avatar` column stores a relative path
     * matching the Symfony-era layout, so existing objects continue to
     * resolve after the cutover.
     *
     * @return Attribute<string|null, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $path = $this->attributes['avatar'] ?? null;

            if (! is_string($path) || $path === '') {
                return null;
            }

            return Storage::disk('avatars')->url($path);
        });
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new PasswordResetNotification($token));
    }
}
