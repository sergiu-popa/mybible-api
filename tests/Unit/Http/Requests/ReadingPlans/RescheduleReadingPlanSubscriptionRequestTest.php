<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\ReadingPlans;

use App\Http\Requests\ReadingPlans\RescheduleReadingPlanSubscriptionRequest;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class RescheduleReadingPlanSubscriptionRequestTest extends TestCase
{
    public function test_it_fails_when_start_date_is_missing(): void
    {
        Carbon::setTestNow('2026-05-01 12:00:00');

        $validator = $this->validator([]);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('start_date', $validator->errors()->toArray());

        Carbon::setTestNow();
    }

    public function test_it_fails_when_start_date_is_not_a_date(): void
    {
        Carbon::setTestNow('2026-05-01 12:00:00');

        $validator = $this->validator(['start_date' => 'not-a-date']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('start_date', $validator->errors()->toArray());

        Carbon::setTestNow();
    }

    public function test_it_fails_when_start_date_is_in_the_past(): void
    {
        Carbon::setTestNow('2026-05-01 12:00:00');

        $validator = $this->validator(['start_date' => '2026-04-30']);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('start_date', $validator->errors()->toArray());

        Carbon::setTestNow();
    }

    public function test_it_passes_when_start_date_is_today(): void
    {
        Carbon::setTestNow('2026-05-01 12:00:00');

        $this->assertFalse($this->validator(['start_date' => '2026-05-01'])->fails());

        Carbon::setTestNow();
    }

    public function test_it_passes_when_start_date_is_in_the_future(): void
    {
        Carbon::setTestNow('2026-05-01 12:00:00');

        $this->assertFalse($this->validator(['start_date' => '2026-06-15'])->fails());

        Carbon::setTestNow();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validator(array $payload): ValidatorContract
    {
        return Validator::make($payload, (new RescheduleReadingPlanSubscriptionRequest)->rules());
    }
}
