<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Mobile;

use App\Domain\Mobile\DataTransferObjects\UpdateMobileVersionData;
use App\Domain\Mobile\Models\MobileVersion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMobileVersionRequest extends FormRequest
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
        $version = $this->route('version');
        $ignoreId = $version instanceof MobileVersion ? $version->id : null;
        $effectivePlatform = $this->input('platform', $version instanceof MobileVersion ? $version->platform : null);
        $effectiveKind = $this->input('kind', $version instanceof MobileVersion ? $version->kind : null);

        $platformRule = Rule::unique('mobile_versions', 'platform')
            ->ignore($ignoreId)
            ->where(function ($query) use ($effectiveKind): void {
                if (is_string($effectiveKind) && $effectiveKind !== '') {
                    $query->where('kind', $effectiveKind);
                }
            });
        $kindRule = Rule::unique('mobile_versions', 'kind')
            ->ignore($ignoreId)
            ->where(function ($query) use ($effectivePlatform): void {
                if (is_string($effectivePlatform) && $effectivePlatform !== '') {
                    $query->where('platform', $effectivePlatform);
                }
            });

        return [
            'platform' => ['sometimes', 'required', 'string', 'in:ios,android', $platformRule],
            'kind' => ['sometimes', 'required', 'string', 'in:' . MobileVersion::KIND_MIN_REQUIRED . ',' . MobileVersion::KIND_LATEST, $kindRule],
            'version' => ['sometimes', 'required', 'string', 'max:25', 'regex:/^\d+\.\d+\.\d+(?:[-+][\w.]+)?$/'],
            'released_at' => ['sometimes', 'nullable', 'date'],
            'release_notes' => ['sometimes', 'nullable', 'array'],
            'store_url' => ['sometimes', 'nullable', 'url', 'max:255'],
        ];
    }

    public function toData(): UpdateMobileVersionData
    {
        $v = $this->validated();

        return new UpdateMobileVersionData(
            platform: array_key_exists('platform', $v) ? (string) $v['platform'] : null,
            kind: array_key_exists('kind', $v) ? (string) $v['kind'] : null,
            version: array_key_exists('version', $v) ? (string) $v['version'] : null,
            releasedAt: array_key_exists('released_at', $v) && is_string($v['released_at'])
                ? CarbonImmutable::parse($v['released_at'])
                : null,
            releaseNotes: array_key_exists('release_notes', $v) && is_array($v['release_notes']) ? $v['release_notes'] : null,
            storeUrl: array_key_exists('store_url', $v) && $v['store_url'] !== null ? (string) $v['store_url'] : null,
            platformProvided: array_key_exists('platform', $v),
            kindProvided: array_key_exists('kind', $v),
            versionProvided: array_key_exists('version', $v),
            releasedAtProvided: array_key_exists('released_at', $v),
            releaseNotesProvided: array_key_exists('release_notes', $v),
            storeUrlProvided: array_key_exists('store_url', $v),
        );
    }
}
