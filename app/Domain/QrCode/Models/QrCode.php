<?php

declare(strict_types=1);

namespace App\Domain\QrCode\Models;

use App\Domain\QrCode\QueryBuilders\QrCodeQueryBuilder;
use Database\Factories\QrCodeFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $reference
 * @property string $url
 * @property ?string $image_path
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[UseFactory(QrCodeFactory::class)]
final class QrCode extends Model
{
    /** @use HasFactory<QrCodeFactory> */
    use HasFactory;

    protected $table = 'qr_codes';

    protected $guarded = [];

    /**
     * Resolve the publicly-addressable URL for the stored QR image.
     *
     * Returns `null` when the QR has no stored image. The `qr` disk must be
     * configured in `config/filesystems.php` — production overrides this to
     * s3 via env wiring (MBA-020).
     */
    public function imageUrl(): ?string
    {
        if ($this->image_path === null || $this->image_path === '') {
            return null;
        }

        return Storage::disk('qr')->url($this->image_path);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return QrCodeQueryBuilder
     */
    public function newEloquentBuilder($query): Builder
    {
        return new QrCodeQueryBuilder($query);
    }
}
