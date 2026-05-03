<?php

declare(strict_types=1);

namespace App\Domain\Analytics\Actions;

use App\Domain\Analytics\DataTransferObjects\ResourceDownloadContextData;
use App\Domain\Analytics\Events\DownloadOccurred;
use App\Domain\Analytics\Models\ResourceDownload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RecordResourceDownloadAction
{
    public function execute(
        Model $target,
        ResourceDownloadContextData $context,
        string $eventType,
    ): ResourceDownload {
        $alias = Relation::getMorphAlias($target::class);

        if (! is_string($alias) || $alias === $target::class) {
            throw new InvalidArgumentException(sprintf(
                'Model class %s is not registered in the polymorphic morph map.',
                $target::class,
            ));
        }

        $row = DB::transaction(function () use ($target, $context, $alias): ResourceDownload {
            $download = new ResourceDownload;
            $download->downloadable_type = $alias;
            $download->downloadable_id = (int) $target->getKey();
            $download->user_id = $context->userId;
            $download->device_id = $context->deviceId;
            $download->language = $context->language;
            $download->source = $context->source;
            $download->save();

            return $download;
        });

        DownloadOccurred::dispatch($eventType, $target, $context, $row);

        return $row;
    }
}
