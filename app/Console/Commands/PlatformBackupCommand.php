<?php

namespace App\Console\Commands;

use App\Enums\BackupVerificationStatus;
use App\Services\Operations\DatabaseBackupManager;
use Illuminate\Console\Command;

class PlatformBackupCommand extends Command
{
    protected $signature = 'platform:backup
        {--verify : Restore the new backup into the disposable verification database}
        {--dry-run : Show the resolved backup plan without writing or executing anything}';

    protected $description = 'Create and optionally restore-verify a platform database backup';

    public function handle(DatabaseBackupManager $backups): int
    {
        $verify = (bool) $this->option('verify');

        if ((bool) $this->option('dry-run')) {
            $plan = $backups->plan($verify);

            $this->components->info('Backup dry run completed. No process was executed and no file was written.');
            $this->table(['Setting', 'Resolved value'], collect($plan)
                ->map(fn (bool|int|string $value, string $key): array => [
                    str_replace('_', ' ', $key),
                    is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value,
                ])->values()->all());

            return self::SUCCESS;
        }

        try {
            $backup = $backups->run($verify);
        } catch (\Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $location = $backup->provider_reference !== null
            ? 'managed snapshot '.$backup->provider_reference
            : $backup->disk.':'.$backup->path;

        $this->components->info("Database backup #{$backup->getKey()} written to [{$location}].");
        $this->line('Size: '.($backup->bytes ?? 0).' bytes');
        $this->line('SHA-256: '.($backup->checksum ?? 'provider-managed'));

        if ($backup->verification_status === BackupVerificationStatus::Verified) {
            $this->components->info('Disposable restore verification passed.');
        }

        return self::SUCCESS;
    }
}
