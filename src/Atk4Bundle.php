<?php

namespace Atk4\Symfony\Module;

use Atk4\Symfony\Module\DependencyInjection\Atk4Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class Atk4Bundle extends Bundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new Atk4Extension();
    }
}