<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Domain\User\Profile\DataTransferObjects\UploadUserAvatarData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

final class UploadUserAvatarRequest extends FormRequest
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
        // `File::image()` + `->types()` drops the max-size rule (the static
        // `types()` constructor returns a fresh `File` instance). Stick to
        // plain string rules so the size, mime, and image constraints all
        // take effect.
        return [
            'avatar' => [
                'required',
                'image',
                'mimes:jpeg,png',
                'max:' . (5 * 1024),
            ],
        ];
    }

    public function toData(): UploadUserAvatarData
    {
        /** @var array{avatar: UploadedFile} $validated */
        $validated = $this->validated();

        return UploadUserAvatarData::from($validated);
    }
}
