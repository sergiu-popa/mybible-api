<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Mobile\Models\MobileVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MobileVersion>
 */
final class MobileVersionFactory extends Factory
{
    protected $model = MobileVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'platform' => 'ios',
            'kind' => MobileVersion::KIND_LATEST,
            'version' => '3.4.1',
            'released_at' => null,
            'release_notes' => null,
            'store_url' => 'https://apps.apple.com/app/id1',
        ];
    }

    public function ios(): self
    {
        return $this->state(['platform' => 'ios']);
    }

    public function android(): self
    {
        return $this->state(['platform' => 'android']);
    }

    public function latest(): self
    {
        return $this->state(['kind' => MobileVersion::KIND_LATEST]);
    }

    public function minRequired(): self
    {
        return $this->state(['kind' => MobileVersion::KIND_MIN_REQUIRED]);
    }
}
