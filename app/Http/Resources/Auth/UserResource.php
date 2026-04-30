<?php

declare(strict_types=1);

namespace App\Http\Resources\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'language' => $this->language,
            'languages' => $this->languages,
            'ui_locale' => $this->ui_locale,
            'preferred_version' => $this->preferred_version,
            'avatar_url' => $this->avatar_url,
            'is_super' => $this->is_super,
            'active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
