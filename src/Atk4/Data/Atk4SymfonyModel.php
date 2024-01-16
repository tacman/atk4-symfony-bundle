<?php

namespace Atk4\Symfony\Module\Atk4\Data;

use Atk4\Core\AppScopeTrait;
use Atk4\Data\Model;
use Atk4\Symfony\Module\Atk4\Ui\App;

/**
 * @method App getApp()
 */
class Atk4SymfonyModel extends Model
{
    use AppScopeTrait;
}
