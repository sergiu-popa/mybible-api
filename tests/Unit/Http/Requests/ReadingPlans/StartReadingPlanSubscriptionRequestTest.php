<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\ReadingPlans;

use App\Http\Requests\ReadingPlans\StartReadingPlanSubscriptionRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class StartReadingPlanSubscriptionRequestTest extends TestCase
{
    public function test_it_authorizes_by_default(): void
    {
        $this->assertTrue((new StartReadingPlanSubscriptionRequest)->authorize());
    }

    public function test_it_passes_when_start_date_is_missing(): void
    {
        Carbon::setTestNow('2026-05-01 12:00:00');

        $this->assertFalse($this->validator([])->fails());

        Carbon::setTestNow();
    }

    public function test_it_passes_when_start_date_is_today_or_future(): void
    {
        Carbon::setTestNow('2026-05-01 12:00:00');

        $this->assertFalse($this->validator(['start_date' => '2026-05-01'])->fails());
        $this->assertFalse($this->validator(['start_date' => '2026-06-15'])->fails());

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

    public function test_start_date_helper_defaults_to_today_when_missing(): void
    {
        Carbon::setTestNow('2026-05-01 12:00:00');

        $request = StartReadingPlanSubscriptionRequest::create('/', 'POST', []);
        $request->setContainer($this->app)->validateResolved();

        $this->assertEquals(
            CarbonImmutable::parse('2026-05-01')->startOfDay(),
            $request->startDate(),
        );

        Carbon::setTestNow();
    }

    public function test_start_date_helper_returns_validated_date_when_present(): void
    {
        Carbon::setTestNow('2026-05-01 12:00:00');

        $request = StartReadingPlanSubscriptionRequest::create('/', 'POST', [
            'start_date' => '2026-06-15',
        ]);
        $request->setContainer($this->app)->validateResolved();

        $this->assertEquals(
            CarbonImmutable::parse('2026-06-15'),
            $request->startDate(),
        );

        Carbon::setTestNow();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validator(array $payload): \Illuminate\Contracts\Validation\Validator
    {
        return Validator::make($payload, (new StartReadingPlanSubscriptionRequest)->rules());
    }
}
