<?php

namespace App\Console\Commands;

use App\Services\Operations\ProductionEnvironmentValidator;
use Illuminate\Console\Command;

class ValidateProductionCommand extends Command
{
    protected $signature = 'platform:validate-production';

    protected $description = 'Fail unless the current configuration is ready for a production deployment';

    public function handle(ProductionEnvironmentValidator $validator): int
    {
        $results = $validator->validate();

        $this->table(
            ['Check', 'Result', 'Required state'],
            collect($results)->map(fn (array $result): array => [
                str_replace('_', ' ', $result['name']),
                $result['passed'] ? 'PASS' : 'FAIL',
                $result['details'],
            ])->all(),
        );

        if (! $validator->passes($results)) {
            $this->components->error('Production validation failed. Deployment must stop.');

            return self::FAILURE;
        }

        $this->components->info('Production environment validation passed.');

        return self::SUCCESS;
    }
}
