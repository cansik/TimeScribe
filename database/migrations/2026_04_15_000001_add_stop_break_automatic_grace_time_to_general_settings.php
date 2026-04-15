<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.stopBreakAutomaticGraceTime', 0);
    }

    public function down(): void
    {
        $this->migrator->delete('general.stopBreakAutomaticGraceTime');
    }
};
