<?php

declare(strict_types=1);

namespace App\Http\Requests\Devotionals;

use App\Domain\Devotional\DataTransferObjects\ToggleDevotionalFavoriteData;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class ToggleDevotionalFavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'devotional_id' => ['required', 'integer', 'exists:devotionals,id'],
        ];
    }

    public function toData(): ToggleDevotionalFavoriteData
    {
        /** @var User $user */
        $user = $this->user();

        return new ToggleDevotionalFavoriteData(
            user: $user,
            devotionalId: (int) $this->input('devotional_id'),
        );
    }
}
