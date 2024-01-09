<?php

namespace Atk4\Symfony\Module\Atk4\Ui;

use App\Models\User;
use Atk4\Symfony\Module\Atk4Persistence;
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

    public function getModel($model, $persistenceName = 'main'): \Atk4\Data\Model
    {
        $model = new $model($this->getPersistence($persistenceName));
        $model->setApp($this);

        return $model;
    }

    public function getApplicationUser()
    {
        $securityUser = $this->security->getUser();

        $user = $this->getModel(User::class);
        if (null === $securityUser) {
            return $user->createEntity();
        }

        return $user->loadBy('email', $securityUser->getEmail());
    }

    public function getStoragePath(string $name, string $fileUrl): string
    {
        return Path::normalize($this->filesystem[$name]['path'].'/'.$fileUrl);
    }
}
