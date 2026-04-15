<?php

declare(strict_types=1);

use App\Enums\TimestampTypeEnum;
use App\Models\Timestamp;
use App\Services\TimestampService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

beforeEach(function (): void {
    Cache::clear();
});

afterEach(function (): void {
    Cache::clear();
    Date::setTestNow();
});

it('discards the auto break regardless of grace time', function (): void {
    Date::setTestNow(Date::parse('2025-01-08 09:00:00'));

    Timestamp::create([
        'type' => TimestampTypeEnum::WORK,
        'started_at' => Date::parse('2025-01-08 08:00:00'),
        'last_ping_at' => Date::parse('2025-01-08 09:00:00'),
    ]);

    TimestampService::startAutoBreak(5);

    // Advance past grace time — manual discard should still work
    Date::setTestNow(Date::parse('2025-01-08 09:30:00'));

    $discarded = TimestampService::discardAutoBreak();
    $activeTimestamp = Timestamp::whereNull('ended_at')->sole();

    expect($discarded)->toBeTrue()
        ->and($activeTimestamp->type)->toBe(TimestampTypeEnum::WORK)
        ->and($activeTimestamp->started_at->equalTo(Date::parse('2025-01-08 08:00:00')))->toBeTrue()
        ->and(Timestamp::where('type', TimestampTypeEnum::BREAK)->count())->toBe(0);
});

it('discards the auto break within grace time', function (): void {
    Date::setTestNow(Date::parse('2025-01-08 09:00:00'));

    Timestamp::create([
        'type' => TimestampTypeEnum::WORK,
        'started_at' => Date::parse('2025-01-08 08:00:00'),
        'last_ping_at' => Date::parse('2025-01-08 09:00:00'),
    ]);

    TimestampService::startAutoBreak(5);

    Date::setTestNow(Date::parse('2025-01-08 09:02:00'));

    $discarded = TimestampService::discardAutoBreak();

    expect($discarded)->toBeTrue()
        ->and(Timestamp::whereNull('ended_at')->value('type'))->toBe(TimestampTypeEnum::WORK)
        ->and(Timestamp::where('type', TimestampTypeEnum::BREAK)->count())->toBe(0);
});

it('returns false when no pending auto break exists', function (): void {
    $discarded = TimestampService::discardAutoBreak();

    expect($discarded)->toBeFalse();
});

it('hasPendingAutoBreak returns true while pending and false after discard', function (): void {
    Date::setTestNow(Date::parse('2025-01-08 09:00:00'));

    Timestamp::create([
        'type' => TimestampTypeEnum::WORK,
        'started_at' => Date::parse('2025-01-08 08:00:00'),
        'last_ping_at' => Date::parse('2025-01-08 09:00:00'),
    ]);

    TimestampService::startAutoBreak(5);

    expect(TimestampService::hasPendingAutoBreak())->toBeTrue();

    TimestampService::discardAutoBreak();

    expect(TimestampService::hasPendingAutoBreak())->toBeFalse();
});

it('clears the cache key after a successful discard', function (): void {
    Date::setTestNow(Date::parse('2025-01-08 09:00:00'));

    Timestamp::create([
        'type' => TimestampTypeEnum::WORK,
        'started_at' => Date::parse('2025-01-08 08:00:00'),
        'last_ping_at' => Date::parse('2025-01-08 09:00:00'),
    ]);

    TimestampService::startAutoBreak(5);

    Date::setTestNow(Date::parse('2025-01-08 09:20:00'));

    TimestampService::discardAutoBreak();

    expect(TimestampService::hasPendingAutoBreak())->toBeFalse();
});
