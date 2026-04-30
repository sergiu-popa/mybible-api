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
 * @property list<string> $languages
 * @property string|null $ui_locale
 * @property bool $is_active
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
        'languages',
        'ui_locale',
        'is_active',
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
            'languages' => 'array',
            'is_active' => 'boolean',
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

    /**
     * Whether this admin may mutate a row whose content language is
     * `$code`. Super-admins always pass; other admins must have the code
     * in their `languages[]` scope.
     *
     * Use this from policies and controllers handling admin mutations on
     * language-scoped entities (devotionals, news, lessons, etc.). The
     * admin UI hides out-of-scope rows, but the API is the security
     * boundary.
     */
    public function canManageLanguage(string $code): bool
    {
        if ($this->is_super) {
            return true;
        }

        return in_array($code, $this->languages, true);
    }

    /**
     * Whether this admin may mutate a row that has no language (e.g.
     * Bible catalog entries that are global). Only super-admins may.
     */
    public function canManageLanguageless(): bool
    {
        return $this->is_super;
    }
}
