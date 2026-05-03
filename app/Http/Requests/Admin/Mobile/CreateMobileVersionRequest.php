<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Mobile;

use App\Domain\Mobile\DataTransferObjects\CreateMobileVersionData;
use App\Domain\Mobile\Models\MobileVersion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateMobileVersionRequest extends FormRequest
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
        $platform = $this->input('platform');

        $kindRule = Rule::unique('mobile_versions', 'kind')
            ->where(function ($query) use ($platform): void {
                if (is_string($platform) && $platform !== '') {
                    $query->where('platform', $platform);
                }
            });

        return [
            'platform' => ['required', 'string', 'in:ios,android'],
            'kind' => [
                'required',
                'string',
                'in:' . MobileVersion::KIND_MIN_REQUIRED . ',' . MobileVersion::KIND_LATEST,
                $kindRule,
            ],
            'version' => ['required', 'string', 'max:25', 'regex:/^\d+\.\d+\.\d+(?:[-+][\w.]+)?$/'],
            'released_at' => ['nullable', 'date'],
            'release_notes' => ['nullable', 'array'],
            'store_url' => ['nullable', 'url', 'max:255'],
        ];
    }

    public function toData(): CreateMobileVersionData
    {
        /** @var array<string, mixed> $v */
        $v = $this->validated();

        return new CreateMobileVersionData(
            platform: (string) $v['platform'],
            kind: (string) $v['kind'],
            version: (string) $v['version'],
            releasedAt: isset($v['released_at']) && is_string($v['released_at'])
                ? CarbonImmutable::parse($v['released_at'])
                : null,
            releaseNotes: isset($v['release_notes']) && is_array($v['release_notes']) ? $v['release_notes'] : null,
            storeUrl: isset($v['store_url']) ? (string) $v['store_url'] : null,
        );
    }
}
