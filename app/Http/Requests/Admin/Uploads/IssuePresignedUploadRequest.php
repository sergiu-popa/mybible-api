<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Uploads;

use App\Domain\Admin\Uploads\DataTransferObjects\PresignedUploadRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class IssuePresignedUploadRequest extends FormRequest
{
    /** Hard upper bound (100 MB) — anything bigger needs a multipart flow. */
    public const MAX_BYTES = 100 * 1024 * 1024;

    /**
     * Allowed content types. Restricted to the formats the admin
     * actually uploads (resources, news hero images, avatars). New
     * formats are added here on purpose so we don't accidentally let
     * the admin presign uploads we don't know how to serve back.
     *
     * @var list<string>
     */
    public const ALLOWED_CONTENT_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/pdf',
        'application/zip',
        'audio/mpeg',
        'audio/mp4',
        'video/mp4',
    ];

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
            'filename' => ['required', 'string', 'max:255'],
            'content_type' => ['required', 'string', Rule::in(self::ALLOWED_CONTENT_TYPES)],
            'size' => ['required', 'integer', 'min:1', 'max:' . self::MAX_BYTES],
        ];
    }

    public function toData(): PresignedUploadRequest
    {
        /** @var array{filename: string, content_type: string, size: int} $validated */
        $validated = $this->validated();

        return PresignedUploadRequest::from($validated);
    }
}
