<?php

declare(strict_types=1);

namespace App\Domain\Collections\Models;

use Database\Factories\CollectionReferenceFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $collection_topic_id
 * @property string $reference
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read CollectionTopic $collectionTopic
 */
#[UseFactory(CollectionReferenceFactory::class)]
final class CollectionReference extends Model
{
    /** @use HasFactory<CollectionReferenceFactory> */
    use HasFactory;

    protected $guarded = [];

    /**
     * @return BelongsTo<CollectionTopic, $this>
     */
    public function collectionTopic(): BelongsTo
    {
        return $this->belongsTo(CollectionTopic::class);
    }
}
