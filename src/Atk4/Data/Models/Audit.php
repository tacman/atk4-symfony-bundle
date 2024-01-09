<?php

declare(strict_types=1);

namespace Atk4\Symfony\Module\Atk4\Data\Models;

use Atk4\Data\Model;
use Detection\MobileDetect;

class Audit extends Model
{
    public static $pending_audit;
    /**
     * @var Model|mixed
     */
    private static Model $user_model;
    public $table = 'audit';

    public static function addModelAudit(Model $m, Model $user)
    {
        self::$user_model = $user;

        $m->addRef('AuditLog', [
            'model' => function (Model $m) {
                $self = new self($m->getPersistence());
                $self->addCondition('model', get_class($m));

                return $m->isEntity() && $m->isLoaded()
                    ? $self->addCondition('model_id', $m->getId())
                    : $self;
            },
        ]);

        // insert
        $m->onHook(
            Model::HOOK_BEFORE_INSERT,
            (function (Model $m, array &$data) {
                self::beforeInsert($m, $data);
            })(...),
            [],
            -100
        ); // called as soon as possible
        $m->onHook(
            Model::HOOK_AFTER_INSERT,
            (function (Model $m) {
                self::afterInsert($m);
            })(...),
            [],
            100
        );  // called as late as possible
        // update
        $m->onHook(
            Model::HOOK_BEFORE_UPDATE,
            (function (Model $m, array $data) {
                self::beforeUpdate($m, $data);
            })(...),
            [],
            -100
        ); // called as soon as possible
        $m->onHook(
            Model::HOOK_AFTER_UPDATE,
            (function (Model $m) {
                self::afterUpdate($m);
            })(...),
            [],
            100
        );  // called as late as possible
        // delete
        $m->onHook(
            Model::HOOK_BEFORE_DELETE,
            (function (Model $m, $model_id) {
                self::beforeDelete($m, $model_id);
            })(...),
            [],
            -100
        ); // called as soon as possible
        $m->onHook(
            Model::HOOK_AFTER_DELETE,
            (function (Model $m, $model_id) {
                self::afterDelete($m, $model_id);
            })(...),
            [],
            100
        );  // called as late as possible
    }

    public static function beforeInsert(Model $model, array &$data)
    {
        self::$pending_audit = self::preparePendingAudit($model, [], 'create');
    }

    private static function preparePendingAudit(Model $model, array $data, $action)
    {
        /** @var Audit $model_audit */
        $model_audit = $model->ref('AuditLog')->createEntity();

        $model_audit->set('model_id', $model->getId());
        $model_audit->set('action', $action);
        $model_audit->set('data_before', $data);
        $model_audit->set('user_info', self::getUserInfo());
        $model_audit->set('user_id', self::getApplicationUserId());
        $model_audit->set('time_taken', microtime(true));

        return $model_audit;
    }

    private static function getIpAddress(): string
    {
        $names = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ];

        foreach ($names as $name) {
            if (isset($_SERVER[$name]) && '' !== (string) $_SERVER[$name]) {
                return $_SERVER[$name];
            }
        }

        return 'UNKNOWN';
    }

    private static function getUserInfo(): array
    {
        $mobileDetect = new MobileDetect();

        return [
            'ip' => self::getIpAddress(),
            'device-agent' => $mobileDetect->getUserAgent(),
            'device-type' => ($mobileDetect->isMobile() ? ($mobileDetect->isTablet() ? 'tablet' : 'phone') : 'desktop'),
        ];
    }

    public static function afterInsert(Model $model)
    {
        self::$pending_audit->set('model_id', $model->getId());
        self::$pending_audit->set('data_after', self::normalizeModelData($model->get()));
        self::$pending_audit->save();

        self::$pending_audit = null;
    }

    private static function normalizeModelData($data): array
    {
        if (empty($data)) {
            return [];
        }

        $ret = [];

        foreach ($data as $fieldName => $value) {
            if (in_array($fieldName, [
                'created_at',
                'created_by',
                'updated_at',
                'updated_by',
                'deleted_at',
                'deleted_by',
                'is_deleted',
            ], true)) {
                continue;
            }

            if ($value instanceof \DateTime) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $ret[$fieldName] = $value;
        }

        return $ret;
    }

    public static function beforeUpdate(Model $model, array $data)
    {
        $intersect = array_merge(
            self::normalizeModelData($model->get()),
            self::normalizeModelData($model->getDirtyRef())
        );

        self::$pending_audit = self::preparePendingAudit($model, $intersect, 'update');
    }

    public static function afterUpdate(Model $model)
    {
        self::$pending_audit->set('data_after', self::normalizeModelData($model->get()))->save();
    }

    public static function beforeDelete(Model $model, $model_id)
    {
        $intersect = array_merge(
            self::normalizeModelData($model->get()),
            self::normalizeModelData($model->getDirtyRef())
        );

        self::$pending_audit = self::preparePendingAudit($model, $intersect, 'delete');
    }

    public static function afterDelete(Model $model, $model_id)
    {
        self::$pending_audit->set('data_after', self::normalizeModelData($model->get()))->save();
    }

    private static function getApplicationUserId()
    {
        if (PHP_SAPI === 'cli') {
            return 0;
        }

        return self::$user_model->isLoaded()
            ? self::$user_model->getId()
            : 0
        ;
    }

    public function loadLast(): self
    {
        return $this->setOrder('id', 'desc')->tryLoadAny();
    }

    protected function init(): void
    {
        parent::init();

        $this->addField('model', ['type' => 'string']);     // model class name
        $this->addField('model_id', ['type' => 'string']);  // id of related model record

        $this->addField('action');

        $this->hasOne('user_id', [
            'model' => [get_class(self::$user_model)],
            'theirField' => self::$user_model->idField,
            'type' => self::$user_model->getField(self::$user_model->idField)->type,
        ]);

        $this->addField('user_info', [
            'type' => 'json',
        ]);

        $this->addField('data_before', [
            'type' => 'json',
        ]);

        $this->addField('data_after', [
            'type' => 'json',
        ]);

        $this->addField('data_diff', [
            'type' => 'json',
        ]);

        $this->addField('time_taken', ['type' => 'float']);

        $this->onHook(Model::HOOK_BEFORE_SAVE, function (Model $model, bool $isUpdate) {
            $model->set(
                'data_diff',
                array_diff_multidimensional(
                    $model->get('data_after'),
                    $model->get('data_before')
                )
            );

            $model->set('time_taken', microtime(true) - $model->get('time_taken'));
        });
    }
}
