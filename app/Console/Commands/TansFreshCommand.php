<?php

namespace App\Console\Commands;

use App\Enums\AuthMediumStatus;
use App\Enums\AuthMediumType;
use App\Enums\TanUsageState;
use App\Models\AuthMedium;
use App\Models\Driver;
use Database\Seeders\TanSeeder;
use Illuminate\Console\Command;

/**
 * Resets the TanSeeder's 10 active demo TANs back to UNUSED + ACTIVE
 * after they were consumed by a driver-login test. The seeded
 * `tan_reference` rows are deterministic, so re-running the seeder
 * (which uses an updateOrCreate pattern) is enough — this command is
 * just a friendlier wrapper that also explains what's available.
 *
 * Optional `--training` flag clears training_status / training expiry
 * on the seeded drivers so EVERY active TAN logs in cleanly (no more
 * `training_required` redirect for Tomasz / Pierre). Use this when
 * you're testing the post-login flow and the negative auth branches
 * aren't what you're after.
 *
 * Optional `--unblock` flag clears `block_status='blocked'` on Klaus
 * so DRV-1005 stops returning `driver_blocked` against TAN 100-0010.
 *
 * Examples:
 *   php artisan tans:fresh
 *   php artisan tans:fresh --training
 *   php artisan tans:fresh --training --unblock
 *   php artisan tans:fresh --no-table
 */
class TansFreshCommand extends Command
{
    protected $signature = 'tans:fresh
        {--training : Also mark all seeded drivers as training_status=valid for 1 year}
        {--unblock  : Also clear block_status on Klaus (DRV-1005)}
        {--no-table   : Skip the helper output table}';

    protected $description = 'Reset demo TANs (and optionally driver training/block flags) for repeat testing.';

    public function handle(): int
    {
        $this->info('Rehydrating demo TANs via TanSeeder…');
        $this->call('db:seed', ['--class' => TanSeeder::class, '--force' => true]);

        // Belt-and-braces guard against any race where a row didn't get
        // restored to ACTIVE — explicitly NULL the consumed/used columns
        // on every active row TanSeeder owns.
        AuthMedium::query()
            ->where('medium_type', AuthMediumType::TAN)
            ->whereIn('tan_reference', [
                'TAN-2026-0001', 'TAN-2026-0005', 'TAN-2026-0006', 'TAN-2026-0007',
                'TAN-2026-0008', 'TAN-2026-0009', 'TAN-2026-0010', 'TAN-2026-0011',
                'TAN-2026-0012', 'TAN-2026-0013',
            ])
            ->update([
                'status' => AuthMediumStatus::ACTIVE,
                'usage_state' => TanUsageState::UNUSED,
                'consumed_at' => null,
                'used_at' => null,
                'consumption_count' => 0,
                'revoked_at' => null,
                'revocation_reason' => null,
            ]);

        if ($this->option('training')) {
            $touched = Driver::query()
                ->whereIn('driver_code', ['DRV-1001','DRV-1002','DRV-1003','DRV-1004','DRV-1005','DRV-1006'])
                ->update([
                    'training_status' => 'valid',
                    'training_valid_until' => now()->addYear()->toDateString(),
                ]);
            $this->info("Refreshed training_status=valid on {$touched} seeded drivers.");
        }

        if ($this->option('unblock')) {
            $touched = Driver::query()
                ->where('driver_code', 'DRV-1005')
                ->update(['block_status' => 'clear']);
            if ($touched > 0) {
                $this->info('Cleared block_status on Klaus Becker (DRV-1005).');
            }
        }

        if (! $this->option('silent')) {
            $this->renderHelpTable();
        }

        return self::SUCCESS;
    }

    protected function renderHelpTable(): void
    {
        $trainingOn = $this->option('training');
        $unblockOn = $this->option('unblock');

        $rows = [
            ['100-0001', 'DRV-1001 Max Mustermann',  '✅ success'],
            ['100-0005', 'DRV-1001 Max Mustermann',  '✅ success'],
            ['100-0006', 'DRV-1002 Anna Schneider',  '✅ success'],
            ['100-0011', 'DRV-1006 Helena Vogel',    '✅ success'],
            ['100-0012', 'DRV-1006 Helena Vogel',    '✅ success'],
            ['100-0013', 'DRV-1002 Anna Schneider',  '✅ success'],
            ['100-0007', 'DRV-1003 Tomasz Nowak',    $trainingOn ? '✅ success (training cleared)' : '⚠ training_required'],
            ['100-0008', 'DRV-1004 Pierre Dupont',   $trainingOn ? '✅ success (training cleared)' : '⚠ training_required'],
            ['100-0009', 'DRV-1004 Pierre Dupont',   $trainingOn ? '✅ success (training cleared)' : '⚠ training_required'],
            ['100-0010', 'DRV-1005 Klaus Becker',    $unblockOn  ? '✅ success (unblocked)'        : '❌ driver_blocked'],
        ];

        $this->newLine();
        $this->table(['TAN code', 'Driver', 'Expected outcome'], $rows);
        $this->line('Reset only TANs:      <fg=cyan>php artisan tans:fresh</>');
        $this->line('Also fix training:    <fg=cyan>php artisan tans:fresh --training</>');
        $this->line('Also unblock Klaus:   <fg=cyan>php artisan tans:fresh --training --unblock</>');
        $this->newLine();
    }
}
