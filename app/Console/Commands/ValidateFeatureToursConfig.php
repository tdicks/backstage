<?php

namespace App\Console\Commands;

use App\Support\FeatureTourConfig;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

#[Signature('app:validate-feature-tours-config
    {--strict : Treat warnings as failures}
    {--dump-config : Output the merged feature tour config}')]
#[Description('Validate the merged feature tour config files for schema and reference errors')]
class ValidateFeatureToursConfig extends Command
{
    public function __construct(private FeatureTourConfig $featureTourConfig)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $paths = $this->featureTourConfig->paths();
        $report = $this->featureTourConfig->validationReport();
        $errors = $report['errors'];
        $warnings = $report['warnings'];

        $this->line('Validating feature tour config files:');

        foreach ($paths as $path) {
            $this->line('  - '.$path);
        }

        if ($paths === []) {
            $this->line('  - (none found)');
        }

        if ((bool) $this->option('dump-config')) {
            $this->newLine();
            $this->line('Merged config:');
            $this->line(Yaml::dump($this->featureTourConfig->mergedConfig(), 99, 2));
        }

        foreach ($errors as $error) {
            $this->error('ERROR: '.$error);
        }

        foreach ($warnings as $warning) {
            $this->warn('WARNING: '.$warning);
        }

        if ($errors !== []) {
            $this->newLine();
            $this->error('Feature tour config validation failed.');

            return self::FAILURE;
        }

        if ((bool) $this->option('strict') && $warnings !== []) {
            $this->newLine();
            $this->error('Feature tour config has warnings and strict mode is enabled.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Feature tour config is valid.');

        return self::SUCCESS;
    }
}
