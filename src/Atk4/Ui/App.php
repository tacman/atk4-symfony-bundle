<?php

namespace Atk4\Symfony\Module\Atk4\Ui;

use Atk4\Symfony\Module\Atk4Persistence;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Uri;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Filesystem\Path;

/**
 * @method \Atk4\Symfony\Module\Atk4\Ui\App getApp()
 */
class App extends \Atk4\Ui\App
{
    public $callExit = false;
    public bool $alwaysRun = false;

    protected string $urlBuildingExt = '';
    protected string $urlBuildingIndexPage = '';

    protected ContainerInterface $container;

    protected Security $security;

    protected array $filesystem;

    protected string $user_class;

    public function __construct(array $defaults = [])
    {
        if (PHP_SAPI === 'cli') {
            $requestFactory = new Psr17Factory();
            $requestCreator = new ServerRequestCreator($requestFactory, $requestFactory, $requestFactory, $requestFactory);

            $defaults['request'] = $requestCreator->fromGlobals()->withUri(new Uri('/'));
        }

        parent::__construct($defaults);
    }

    protected function emitResponse(): void
    {
        // no echo needed
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    protected function getPersistence($name): \Atk4\Data\Persistence
    {
        $persistence = $this->container->get(Atk4Persistence::class);

        return $persistence->getPersistence($name);
    }

    public function getModel($model, $persistenceName = 'main', $defaults = []): \Atk4\Data\Model
    {
        $persistence = $this->getPersistence($persistenceName);
        $model = new $model($persistence, $defaults);

        return $model;
    }

    public function getApplicationUser()
    {
        $securityUser = $this->security->getUser();

        $user = $this->getModel($this->user_class);
        if (null === $securityUser) {
            return $user->createEntity();
        }

        return $user->loadBy('email', $securityUser->getEmail());
    }

    public function getStoragePath(string $name, string $fileUrl): string
    {
        return Path::normalize($this->filesystem[$name]['path'].'/'.$fileUrl);
    }

    public function getUserModel(): string
    {
        return $this->user_class;
    }
}
