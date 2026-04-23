<?php

declare(strict_types=1);

namespace App\Http\Requests\Hymnal;

use App\Domain\Hymnal\DataTransferObjects\ToggleHymnalFavoriteData;
use App\Domain\Hymnal\Models\HymnalSong;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class ToggleHymnalFavoriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'song_id' => ['required', 'integer', 'exists:hymnal_songs,id'],
        ];
    }

    public function toData(): ToggleHymnalFavoriteData
    {
        /** @var array{song_id: int} $data */
        $data = $this->validated();

        /** @var User $user */
        $user = $this->user();

        /** @var HymnalSong $song */
        $song = HymnalSong::query()->findOrFail($data['song_id']);

        return new ToggleHymnalFavoriteData($user, $song);
    }
}
