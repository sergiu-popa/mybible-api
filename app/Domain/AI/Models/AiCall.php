<?php

declare(strict_types=1);

namespace App\Domain\AI\Models;

use App\Domain\AI\Enums\AiCallStatus;
use App\Models\User;
use Database\Factories\AiCallFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Append-only audit row for one Anthropic Claude API call.
 *
 * @property int $id
 * @property string $prompt_version
 * @property string $prompt_name
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property int $cache_creation_input_tokens
 * @property int $cache_read_input_tokens
 * @property int $latency_ms
 * @property AiCallStatus $status
 * @property ?string $error_message
 * @property ?string $subject_type
 * @property ?int $subject_id
 * @property ?int $triggered_by_user_id
 * @property Carbon $created_at
 */
#[UseFactory(AiCallFactory::class)]
final class AiCall extends Model
{
    /** @use HasFactory<AiCallFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'ai_calls';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AiCallStatus::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'cache_creation_input_tokens' => 'integer',
            'cache_read_input_tokens' => 'integer',
            'latency_ms' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
