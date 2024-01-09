<?php

namespace Atk4\Symfony\Module\Atk4\Data\Models;

use Atk4\Data\Field\PasswordField;
use Atk4\Data\Model;

/**
 * @property string $name     @Atk4\Field()
 * @property string $email    @Atk4\Field()
 * @property string $password @Atk4\Field()
 * @property array  $roles    @Atk4\Field()
 */
class User extends Model
{
    public $table = 'user';

    protected function init(): void
    {
        parent::init();

        $this->addField($this->fieldName()->name);
        $this->addField($this->fieldName()->email);
        $this->addField('password', [PasswordField::class]);
        $this->addField($this->fieldName()->roles, ['type' => 'json']);
    }
}
