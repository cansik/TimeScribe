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

it('restores work when unlocking within the auto break grace time', function (): void {
    Date::setTestNow(Date::parse('2025-01-08 09:00:00'));

    Timestamp::create([
        'type' => TimestampTypeEnum::WORK,
        'started_at' => Date::parse('2025-01-08 08:00:00'),
        'last_ping_at' => Date::parse('2025-01-08 09:00:00'),
    ]);

    TimestampService::startAutoBreak(5);

    Date::setTestNow(Date::parse('2025-01-08 09:03:00'));

    $restored = TimestampService::restorePendingAutoBreak();
    $activeTimestamp = Timestamp::whereNull('ended_at')->sole();

    expect($restored)->toBeTrue()
        ->and($activeTimestamp->type)->toBe(TimestampTypeEnum::WORK)
        ->and($activeTimestamp->started_at->equalTo(Date::parse('2025-01-08 08:00:00')))->toBeTrue()
        ->and($activeTimestamp->last_ping_at->equalTo(Date::parse('2025-01-08 09:03:00')))->toBeTrue()
        ->and(Timestamp::where('type', TimestampTypeEnum::BREAK)->count())->toBe(0);
});

it('keeps the break when the auto break grace time has expired', function (): void {
    Date::setTestNow(Date::parse('2025-01-08 09:00:00'));

    Timestamp::create([
        'type' => TimestampTypeEnum::WORK,
        'started_at' => Date::parse('2025-01-08 08:00:00'),
        'last_ping_at' => Date::parse('2025-01-08 09:00:00'),
    ]);

    TimestampService::startAutoBreak(5);

    Date::setTestNow(Date::parse('2025-01-08 09:06:00'));

    $restored = TimestampService::restorePendingAutoBreak();
    $activeTimestamp = Timestamp::whereNull('ended_at')->sole();

    expect($restored)->toBeFalse()
        ->and($activeTimestamp->type)->toBe(TimestampTypeEnum::BREAK)
        ->and($activeTimestamp->started_at->equalTo(Date::parse('2025-01-08 09:00:00')))->toBeTrue();
});

it('starts a break immediately when grace time is zero', function (): void {
    Date::setTestNow(Date::parse('2025-01-08 09:00:00'));

    Timestamp::create([
        'type' => TimestampTypeEnum::WORK,
        'started_at' => Date::parse('2025-01-08 08:00:00'),
        'last_ping_at' => Date::parse('2025-01-08 09:00:00'),
    ]);

    TimestampService::startAutoBreak(0);

    expect(TimestampService::hasPendingAutoBreak())->toBeFalse()
        ->and(Timestamp::whereNull('ended_at')->where('type', TimestampTypeEnum::BREAK)->exists())->toBeTrue();
});

it('leaves cache key intact after grace time expires', function (): void {
    Date::setTestNow(Date::parse('2025-01-08 09:00:00'));

    Timestamp::create([
        'type' => TimestampTypeEnum::WORK,
        'started_at' => Date::parse('2025-01-08 08:00:00'),
        'last_ping_at' => Date::parse('2025-01-08 09:00:00'),
    ]);

    TimestampService::startAutoBreak(5);

    Date::setTestNow(Date::parse('2025-01-08 09:06:00'));

    TimestampService::restorePendingAutoBreak();

    expect(TimestampService::hasPendingAutoBreak())->toBeTrue();
});

it('clears cache on successful restore', function (): void {
    Date::setTestNow(Date::parse('2025-01-08 09:00:00'));

    Timestamp::create([
        'type' => TimestampTypeEnum::WORK,
        'started_at' => Date::parse('2025-01-08 08:00:00'),
        'last_ping_at' => Date::parse('2025-01-08 09:00:00'),
    ]);

    TimestampService::startAutoBreak(5);

    Date::setTestNow(Date::parse('2025-01-08 09:02:00'));

    TimestampService::restorePendingAutoBreak();

    expect(TimestampService::hasPendingAutoBreak())->toBeFalse();
});

it('does not create a pending state when no active work timestamp exists', function (): void {
    Date::setTestNow(Date::parse('2025-01-08 09:00:00'));

    TimestampService::startAutoBreak(5);

    expect(TimestampService::hasPendingAutoBreak())->toBeFalse()
        ->and(Timestamp::whereNull('ended_at')->count())->toBe(0);
});
