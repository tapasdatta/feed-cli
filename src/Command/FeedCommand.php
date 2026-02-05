<?php

namespace App\Command;

use App\Importer\ImporterResolver;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'feed:import',
    description: 'Import data from feed files',


)]
class FeedCommand
{
    public function __construct(private ImporterResolver $resolver)
    {
        //
    }

    public function __invoke(
        #[Argument('.')] string $target,
        #[Argument('.')] string $file,
        #[Argument('.')] string $type,
        OutputInterface $output
    ) {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        if (!file_exists($file)) {
            $output->writeln("File not found: {$file}");
            return Command::FAILURE;
        }

        try {
            $importer = $this->resolver->resolve(
                $target
            );
        } catch (\InvalidArgumentException $e) {
            $output->writeln($e->getMessage());
            return Command::FAILURE;
        }

        $result = $importer->import(
            $file,
            $type
        );

        $output->writeln($importer->key());

        $output->writeln($result->type());

        $duration = round(microtime(true) - $startTime, 2);
        $memoryUsed = round((memory_get_peak_usage(true) - $startMemory) / 1024 / 1024, 2);

        $output->writeln("Processed: " . ($result->processed ?? 0));
        $output->writeln("Skipped: " . ($result->skipped ?? 0));
        $output->writeln("Time: {$duration}s");
        $output->writeln("Memory: {$memoryUsed}MB");

        return Command::SUCCESS;
    }
}
