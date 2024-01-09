<?php

declare(strict_types=1);

namespace Atk4\Symfony\Module\Atk4\Data;

use Atk4\Core\Exception;
use Atk4\Data\Model;
use Atk4\Data\ValidationException;
use Carbon\Carbon;
use Ramsey\Uuid\Uuid;

class ModelHelper
{
    public static function addSoftDeletable(Model $model): void
    {
        $model->addField('is_deleted', [
            'type' => 'boolean',
            'system' => true,
            'default' => 0,
        ]);

        $model->addCondition('is_deleted', false);

        $model->onHook(Model::HOOK_BEFORE_DELETE, function (Model $entity) {
            if (!$entity->isLoaded()) {
                throw (new Exception('Model must be loaded before delete'))->addMoreInfo('model', $entity);
            }

            $entity->getModel()->scope()->clear();

            $entity->saveAndUnload(['is_deleted' => true]);

            $entity->hook(Model::HOOK_AFTER_DELETE);

            $entity->breakHook(false); // this will cancel original delete()
        }, [], 100);
    }

    public static function addTrackableDelete(Model $model, Model $userActor): void
    {
        $model->assertIsModel();
        $userActor->assertIsEntity();

        $model->hasOne('deleted_by', [
            'model' => [get_class($userActor)],
            'theirField' => $userActor->getField($userActor->idField)->shortName,
            'type' => $userActor->getField($userActor->idField)->type,
            'system' => true,
        ]);
        $model->addField('deleted_at', [
            'type' => 'datetime',
            'system' => true,
        ]);

        $model->onHook(Model::HOOK_BEFORE_DELETE, function (Model $model) use ($userActor) {
            $model->set('deleted_by', $userActor->getId());
            $model->set('deleted_at', self::getCurrentTime());
        }, [], 0);
    }

    public static function addTrackableCreate(Model $model, Model $userActor): void
    {
        $model->assertIsModel();
        $userActor->assertIsEntity();

        $model->hasOne('created_by', [
            'model' => [get_class($userActor)],
            'theirField' => $userActor->getField($userActor->idField)->shortName,
            'type' => $userActor->getField($userActor->idField)->type,
            'system' => true,
        ]);

        $model->addField('created_at', [
            'type' => 'datetime',
            'system' => true,
        ]);

        $model->onHook(Model::HOOK_BEFORE_INSERT, function (Model $model, &$data) use ($userActor) {
            $data['created_by'] = $userActor->getId();
            $data['created_at'] = self::getCurrentTime();
        }, [], 0);
    }

    public static function addTrackableUpdate(Model $model, Model $userActor): void
    {
        $model->assertIsModel();
        $userActor->assertIsEntity();

        $model->hasOne('updated_by', [
            'model' => [get_class($userActor)],
            'theirField' => $userActor->getField($userActor->idField)->shortName,
            'type' => $userActor->getField($userActor->idField)->type,
            'system' => true,
        ]);
        $model->addField('updated_at', [
            'type' => 'datetime',
            'system' => true,
        ]);

        $model->onHook(Model::HOOK_BEFORE_UPDATE, function (Model $model, &$data) use ($userActor) {
            $data['updated_by'] = $userActor->getId();
            $data['updated_at'] = self::getCurrentTime();
        }, [], 100);
    }

    private static function getCurrentTime(): \DateTimeInterface
    {
        return Carbon::now();
    }

    public static function fieldSetAsGuid(Model $model, string $fieldName = 'guid'): void
    {
        $model->getField($fieldName)->type = 'guid';

        $model->onHook(Model::HOOK_BEFORE_INSERT, function (Model $model, array &$data) use ($fieldName) {
            if ('' === $data[$fieldName]) {
                $data[$fieldName] = self::newGUID();
            }
        }, [], -200);
    }

    public static function fieldSetAsBarcode(Model $model, string $fieldName = 'barcode'): void
    {
        $model->onHook(Model::HOOK_BEFORE_INSERT, function (Model $model, array &$data) use ($fieldName) {
            if ('' === $data[$fieldName]) {
                $data[$fieldName] = strtoupper(substr(self::newGUID(), 3, 26));
            }
        }, [], -200);
    }

    public static function fieldSetUnique(Model $model, string $field): void
    {
        $model->onHook(Model::HOOK_BEFORE_SAVE, function (Model $model, bool $is_update) use ($field) {
            if ($model->isDirty($field)) {
                $a = clone $model->getModel();
                if ($is_update) {
                    $a->addCondition($a->idField, '!=', $model->getId());
                }
                $a->addCondition($field, $model->get($field));
                $entity = $a->tryLoadAny();
                if (null !== $entity) {
                    if (function_exists('_td')) {
                        throw new ValidationException([$field => _td('validation', '{field} with such value already exists', ['{field}' => ucwords($field)])], $model);
                    }
                    throw new ValidationException([$field => 'Field with such value already exists'], $model);
                }
            }
        });
    }

    public static function newGUID(): string
    {
        return Uuid::uuid4()->toString();
    }

    public static function sluggify(string $label, int $maxLength = 300): string
    {
        $result = strtolower($label);

        $result = preg_replace('~[^A-Za-z0-9-\\s]~', '', $result);
        $result = trim(preg_replace('~[\\s-]+~', ' ', $result));
        $result = trim(substr($result, 0, $maxLength));

        return preg_replace('~\\s~', '-', $result);
    }

    public static function numberFormat(float $amount, int $decimal = 2): string
    {
        return number_format($amount, $decimal, '.', '');
    }

    public static function money(float $amount, int $decimal = 2, string $currency = '€'): string
    {
        return self::numberFormat((float) ($amount ?? 0), $decimal).' '.$currency;
    }
}
