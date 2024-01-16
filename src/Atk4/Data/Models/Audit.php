<?php

declare(strict_types=1);

namespace Atk4\Symfony\Module\Atk4\Data\Models;

use Atk4\Data\Model;
use Atk4\Symfony\Module\Atk4\Data\Atk4SymfonyModel;
use Carbon\Carbon;
use Detection\MobileDetect;

/**
 * @property string $model       @Atk4\Field()
 * @property string $model_id    @Atk4\Field()
 * @property string $action      @Atk4\Field()
 * @property array  $data_before @Atk4\Field()
 * @property array  $data_after  @Atk4\Field()
 * @property array  $data_diff   @Atk4\Field()
 * @property array  $user_info   @Atk4\Field()
 * @property User   $o_user      @Atk4\RefOne(field_name="user_id")
 * @property float  $time_taken  @Atk4\Field()
 */
class Audit extends Atk4SymfonyModel
{
    public static $pending_audit;

    public $table = 'audit';

    protected static Model $user_model;

    public static function addModelAudit(Model $m, $actor)
    {
        static::$user_model = $actor;

        $m->addReference('AuditLog', [
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

    private static function preparePendingAudit(Atk4SymfonyModel $model, array $data, $action)
    {
        /** @var Audit $model_audit */
        $model_audit = $model->ref('AuditLog')->createEntity();

        $model_audit->set('model_id', $model->getId());
        $model_audit->set('action', $action);
        $model_audit->set('data_before', $data);
        $model_audit->set('user_info', self::getUserInfo());
        $model_audit->set('user_id', $model->getApp()->getApplicationUser()->getId());
        $model_audit->set('time_taken', microtime(true));
        $model_audit->set('timestamp', Carbon::now());

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

    public function loadLast(): self
    {
        return $this->setOrder('id', 'desc')->tryLoadAny();
    }

    protected function init(): void
    {
        parent::init();

        $user_model = static::$user_model ?? new User($this->getPersistence());

        $this->addField('model', [
            'type' => 'string',
            'caption' => 'Ref.Model',
        ]);     // model class name

        $this->addField('model_id', [
            'type' => 'string',
            'caption' => 'Ref.ID',
        ]);  // id of related model record

        $this->addField('action');

        $this->hasOne('user_id', [
            'model' => [get_class($user_model)],
            'theirField' => $user_model->idField,
            'type' => $user_model->getField($user_model->idField)->type,
        ])->addFields([
            'user_name' => $user_model->titleField,
        ]);

        $this->addField('user_info', [
            'type' => 'json',
        ]);

        $this->addField('data_before', [
            'type' => 'json',
            'caption' => 'Before',
        ]);

        $this->addField('data_after', [
            'type' => 'json',
            'caption' => 'After',
        ]);

        $this->addField('data_diff', [
            'type' => 'json',
            'caption' => 'Changes',
        ]);

        $this->addField('time_taken', ['type' => 'float']);

        $this->addField('timestamp', ['type' => 'datetime']);

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
