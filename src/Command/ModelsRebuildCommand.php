<?php

namespace Atk4\Symfony\Module\Command;

use Atk4\Core\Exception;
use Atk4\Data\Model;
use Atk4\Data\Reference\HasOne;
use Atk4\Data\Schema\Migrator;
use Atk4\Symfony\Module\Atk4App;
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
    /**
     * @var array<string> List of processed models
     */
    private static $processedModel = [];

    public function __construct(
        protected Atk4App $atk4App,
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
                preg_match('#^namespace\s+(.+?);.*class\s+(\w+).+$#sm', $src, $m);
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
                $this->processModel($symfonyStyle, $className);
            }
        } catch (\Atk4\Data\Exception $e) {
            $symfonyStyle->error($e->getColorfulText());
        } catch (\Throwable $e) {
            $symfonyStyle->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    private function processModel(SymfonyStyle $symfonyStyle, mixed $className)
    {
        if (in_array($className, static::$processedModel)) {
            $symfonyStyle->writeln('# '.$className.' already processed');

            return;
        }

        static::$processedModel[] = $className;

        $symfonyStyle->writeln('# Model:'.$className);

        /** @var Model $class */
        $class = $this->atk4App->getApp()->getModel($className);

        $migration = new Migrator($class);
        $migration->dropIfExists(true);

        $table_exists = $migration->isTableExists($class->table);

        if (!$table_exists) {
            $createFlags = AbstractPlatform::CREATE_INDEXES | AbstractPlatform::CREATE_FOREIGNKEYS;

            $statements = $migration->getConnection()->getDatabasePlatform()->getCreateTableSQL($migration->table, $createFlags);

            foreach ($statements as $sql) {
                $migration->getConnection()->getConnection()->executeQuery($sql);
            }

            $symfonyStyle->writeln('# '.$className.': table '.$class->table.' is up to date');
        }

        foreach ($class->getReferences() as $reference) {
            if (is_a($reference, HasOne::class, true)) {
                $this->processModel($symfonyStyle, $reference->model[0]);
                $migration->createForeignKey($reference);
            }
        }

        if (!$table_exists) {
            return;
        }

        $migration = new Migrator($class);

        $tableDiff = $migration->getConnection()->createSchemaManager()->createComparator()
            ->compareTables(
                $migration->table,
                $migration->getConnection()->createSchemaManager()->introspectTable($class->table)
            );

        if ($tableDiff->isEmpty()) {
            $symfonyStyle->writeln('# '.$className.': table '.$class->table.' no changes');

            return;
        }

        $symfonyStyle->writeln('# '.$className.': table '.$class->table.' is not up to date');

        $statements = $migration->getConnection()->getDatabasePlatform()->getAlterTableSQL($tableDiff);

        foreach ($statements as $sql) {
            $migration->getConnection()->getConnection()->executeQuery($sql);
        }
    }
}
