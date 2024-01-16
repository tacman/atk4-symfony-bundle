<?php

namespace Atk4\Symfony\Module;

use Atk4\Core\Exception;
use Atk4\Data\Persistence;
use Atk4\Symfony\Module\Atk4\Data\Atk4SymfonyModel;
use Atk4\Symfony\Module\Atk4\Data\Decorators\IModelAuditable;
use Atk4\Symfony\Module\Atk4\Data\Decorators\IModelSoftDeletable;
use Atk4\Symfony\Module\Atk4\Data\Decorators\IModelTrackable;
use Atk4\Symfony\Module\Atk4\Data\ModelHelper;
use Atk4\Symfony\Module\Atk4\Data\Models\Audit;
use Symfony\Bundle\SecurityBundle\Security;

class Atk4Persistence
{
    /**
     * @var array <string, Persistence>
     */
    private static array $persistences = [];

    public function __construct(
        private Atk4App $atk4app,
        private array $config,
        private Security $security
    ) {
    }

    public function getPersistence(string $name = null): Persistence
    {
        $name = $name ?? 'main';

        if (isset(self::$persistences[$name])) {
            return self::$persistences[$name];
        }

        $this->config['persistences'] = $this->config['persistences'] ?? [];

        if (!isset($this->config['persistences'][$name])) {
            throw new Exception('Persistence "'.$name.'" not found');
        }

        $config = $this->config['persistences'][$name];

        self::$persistences[$name] = Persistence::connect(
            [
                'driver' => $config['driver'],
                'host' => $config['host'],
                'port' => $config['port'],
                'dbname' => $config['name'],
                'charset' => $config['charset'],
            ],
            $config['user'],
            $config['pass']
        );

        self::$persistences[$name]->onHook(
            Persistence::HOOK_AFTER_ADD,
            fx: function (Persistence $persistence, Atk4SymfonyModel $model) {
                $model->setApp($this->atk4app->getApp());

                $class = $model->getApp()->getUserModel();
                $actor = new $class($persistence);
                $securityUser = $this->security->getUser();

                if (null !== $securityUser) {
                    $actor = $actor->loadBy('email', $this->security->getUser()->getUserIdentifier());
                } else {
                    $actor = $actor->createEntity();
                }

                if (is_a($model, IModelSoftDeletable::class, true)) {
                    ModelHelper::addSoftDeletable($model);
                }

                if (is_a($model, IModelTrackable::class, true)) {
                    ModelHelper::addTrackableCreate($model, $actor);
                    ModelHelper::addTrackableUpdate($model, $actor);
                    ModelHelper::addTrackableDelete($model, $actor);
                }

                if (is_a($model, IModelAuditable::class, true)) {
                    Audit::addModelAudit($model, $actor);
                }
            }
        );

        return self::$persistences[$name];
    }
}

/*
 * ALTER TABLE table_name
 * ADD COLUMN `created_by` INT(11) NULL AFTER `is_deleted`,
 * ADD COLUMN `created_at` DATETIME NULL AFTER `created_by`,
 * ADD COLUMN `updated_by` INT(11) NULL AFTER `created_at`,
 * ADD COLUMN `updated_at` DATETIME NULL AFTER `updated_by`,
 * ADD COLUMN `deleted_by` INT(11) NULL AFTER `updated_at`,
 * ADD COLUMN `deleted_at` DATETIME NULL AFTER `deleted_by`;
 */
