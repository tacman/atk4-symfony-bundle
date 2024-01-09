<?php

namespace Atk4\Symfony\Module\Command;

use Atk4\Core\Exception;
use Atk4\Data\Model;
use Atk4\Data\Reference\HasOne;
use Atk4\Data\Schema\Migrator;
use Atk4\Symfony\Module\Atk4Persistence;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'models:rebuild',
    description: 'Rebuild database',
)]
class ModelsRebuildCommand extends Command
{
    public function __construct(
        protected Atk4Persistence $atk4Persistence
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('class', 'c', InputOption::VALUE_OPTIONAL, 'Option description')
            ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $symfonyStyle = new SymfonyStyle($input, $output);

        $class = $input->getOption('class') ?? '';
        $path = $input->getOption('path') ?? '';

        if ('' === $class) {
            if ('' === $path) {
                throw new Exception('Error path cannot be empty if no class is specified');
            }

            $classes = [];

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
            foreach ($iterator as $file) {
                if ($file->isDir()) {
                    continue;
                }

                $src = file_get_contents($file->getRealPath());
                preg_match('#^namespace\s+(.+?);.*class\s+(\w+).+;$#sm', $src, $m);
                $className = $m[1].'\\'.$m[2];

                $reflClass = new \ReflectionClass($className);

                if ($reflClass->isAbstract()) {
                    continue;
                }

                if ($reflClass->isSubclassOf(Model::class)) {
                    $classes[$className] = $className;
                }
            }

            $classes = array_values($classes);
        } else {
            $classes = [$class];
        }

        try {
            foreach ($classes as $className) {
                /** @var Model $class */
                $class = new $className($this->atk4Persistence->getPersistence());

                $symfonyStyle->writeln('# Model:'.$className);

                $migration_table = new Migrator($class->getPersistence());
                $migration_table->table($class->table);

                $migration = new Migrator($class);

                $migration->dropIfExists(true);
                $migration->create();

                foreach ($class->getReferences() as $reference) {
                    if (is_a($reference, HasOne::class, true)) {
                        $migration->createForeignKey($reference);
                    }
                }

                continue;
                if (!$migration_table->isTableExists($class->table)) {
                    $createFlags = AbstractPlatform::CREATE_INDEXES | AbstractPlatform::CREATE_FOREIGNKEYS;

                    $res = $migration->getConnection()->getDatabasePlatform()->getCreateTableSQL($migration->table, $createFlags);

                    echo $res[0].';'.PHP_EOL;
                    continue;
                }

                $tableDiff = $migration_table->getConnection()->createSchemaManager()->createComparator()
                    ->compareTables($migration->table, $migration_table->table);

                if ($tableDiff->isEmpty()) {
                    $symfonyStyle->writeln('# '.$className.' is up to date');
                    continue;
                }

                $symfonyStyle->writeln('# '.$className.' is not up to date');

                $res = $migration->getConnection()->getDatabasePlatform()->getAlterTableSQL($tableDiff);

                echo $res[0].';'.PHP_EOL;
            }
        } catch (\Atk4\Data\Exception $e) {
            $symfonyStyle->error($e->getColorfulText());
        } catch (\Throwable $e) {
            $symfonyStyle->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
