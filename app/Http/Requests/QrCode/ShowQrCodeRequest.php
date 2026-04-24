<?php

declare(strict_types=1);

namespace App\Http\Requests\QrCode;

use App\Domain\Reference\Formatter\ReferenceFormatter;
use App\Domain\Reference\Parser\ReferenceParser;
use App\Domain\Reference\Reference;
use App\Http\Rules\ValidReference;
use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

final class ShowQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reference' => [
                'required',
                'string',
                'max:200',
                new ValidReference($this->container->make(ReferenceParser::class), $this),
            ],
        ];
    }

    /**
     * Return the canonical reference string parsed out of the request.
     *
     * {@see ValidReference} stashes the parsed {@see Reference} on the request
     * attribute bag. We re-render it through {@see ReferenceFormatter} so the
     * lookup key is normalised (e.g. `GEN.1:1.VDC`), matching whatever canonical
     * form the seeder / storage pipeline uses.
     */
    public function canonicalReference(ReferenceFormatter $formatter): string
    {
        $reference = $this->attributes->get(ValidReference::PARSED_ATTRIBUTE_KEY);

        if (! $reference instanceof Reference) {
            // Should never happen — ValidReference populates this on success.
            throw new RuntimeException('Parsed reference missing from request attributes.');
        }

        if ($reference->version === null) {
            // Without a version ReferenceFormatter::toCanonical() throws; fall
            // back to the raw input, which is already validated above.
            $raw = $this->input('reference');

            return is_string($raw) ? $raw : '';
        }

        return $formatter->toCanonical($reference);
    }
}
