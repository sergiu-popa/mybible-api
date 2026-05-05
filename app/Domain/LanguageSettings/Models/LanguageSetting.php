<?php

declare(strict_types=1);

namespace App\Domain\LanguageSettings\Models;

use App\Domain\Bible\Models\BibleVersion;
use App\Domain\Commentary\Models\Commentary;
use App\Domain\Devotional\Models\DevotionalType;
use Database\Factories\LanguageSettingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per ISO-2 language with the per-language defaults
 * (Bible version, commentary, devotional type). Routed by `language`.
 *
 * @property int $id
 * @property string $language
 * @property ?int $default_bible_version_id
 * @property ?int $default_commentary_id
 * @property ?int $default_devotional_type_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read ?BibleVersion $defaultBibleVersion
 * @property-read ?Commentary $defaultCommentary
 * @property-read ?DevotionalType $defaultDevotionalType
 */
#[UseFactory(LanguageSettingFactory::class)]
final class LanguageSetting extends Model
{
    /** @use HasFactory<LanguageSettingFactory> */
    use HasFactory;

    protected $table = 'language_settings';

    protected $guarded = [];

    public function getRouteKeyName(): string
    {
        return 'language';
    }

    /**
     * @return BelongsTo<BibleVersion, $this>
     */
    public function defaultBibleVersion(): BelongsTo
    {
        return $this->belongsTo(BibleVersion::class, 'default_bible_version_id');
    }

    /**
     * @return BelongsTo<Commentary, $this>
     */
    public function defaultCommentary(): BelongsTo
    {
        return $this->belongsTo(Commentary::class, 'default_commentary_id');
    }

    /**
     * @return BelongsTo<DevotionalType, $this>
     */
    public function defaultDevotionalType(): BelongsTo
    {
        return $this->belongsTo(DevotionalType::class, 'default_devotional_type_id');
    }
}
