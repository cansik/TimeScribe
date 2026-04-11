<?php

declare(strict_types=1);

use App\Enums\TimestampTypeEnum;
use App\Listeners\StandbyOrLocked;
use App\Models\Timestamp;
use App\Settings\GeneralSettings;
use Native\Desktop\Events\PowerMonitor\ScreenLocked;

use function Pest\Laravel\mock;

it('saves the automatic break threshold setting', function (): void {
    $response = $this->patch(route('settings.start-stop.update'), [
        'stopBreakAutomatic' => 'break',
        'stopBreakAutomaticActivationTime' => '13',
        'stopBreakAutomaticBreakThreshold' => 5,
        'stopWorkTimeReset' => '20',
        'stopBreakTimeReset' => '30',
    ]);

    $response->assertRedirect(route('settings.start-stop.edit'));

    $settings = resolve(GeneralSettings::class);

    expect($settings->stopBreakAutomatic)->toBe('break')
        ->and($settings->stopBreakAutomaticActivationTime)->toBe('13')
        ->and($settings->stopBreakAutomaticBreakThreshold)->toBe(5)
        ->and($settings->stopWorkTimeReset)->toBe(20)
        ->and($settings->stopBreakTimeReset)->toBe(30);
});

it('rejects a negative automatic break threshold setting', function (): void {
    $response = $this->from(route('settings.start-stop.edit'))->patch(route('settings.start-stop.update'), [
        'stopBreakAutomatic' => 'break',
        'stopBreakAutomaticBreakThreshold' => -1,
    ]);

    $response->assertSessionHasErrors(['stopBreakAutomaticBreakThreshold']);
});

it('does not start a break when the inactivity threshold is not reached', function (): void {
    $settings = resolve(GeneralSettings::class);
    $settings->stopBreakAutomatic = 'break';
    $settings->stopBreakAutomaticBreakThreshold = 5;
    $settings->save();

    Timestamp::create([
        'type' => TimestampTypeEnum::WORK,
        'started_at' => now()->subHour(),
        'last_ping_at' => now()->subMinutes(3),
    ]);

    (new StandbyOrLocked)->handle(mock(ScreenLocked::class));

    expect(Timestamp::query()->count())->toBe(1)
        ->and(Timestamp::where('type', TimestampTypeEnum::BREAK)->count())->toBe(0)
        ->and(Timestamp::whereNull('ended_at')->first()?->type)->toBe(TimestampTypeEnum::WORK);
});

it('starts a break when the inactivity threshold is reached', function (): void {
    $settings = resolve(GeneralSettings::class);
    $settings->stopBreakAutomatic = 'break';
    $settings->stopBreakAutomaticBreakThreshold = 5;
    $settings->save();

    Timestamp::create([
        'type' => TimestampTypeEnum::WORK,
        'started_at' => now()->subHour(),
        'last_ping_at' => now()->subMinutes(6),
    ]);

    (new StandbyOrLocked)->handle(mock(ScreenLocked::class));

    expect(Timestamp::where('type', TimestampTypeEnum::BREAK)->count())->toBe(1)
        ->and(Timestamp::where('type', TimestampTypeEnum::WORK)->first()?->ended_at)->not->toBeNull()
        ->and(Timestamp::whereNull('ended_at')->first()?->type)->toBe(TimestampTypeEnum::BREAK);
});
