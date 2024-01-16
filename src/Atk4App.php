<?php

namespace Atk4\Symfony\Module;

use App\Kernel;
use Atk4\Symfony\Module\Atk4\Ui\App;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class Atk4App
{
    private ?App $app = null;

    private array $config;

    public function __construct(
        array $config,
        protected RequestStack $requestStack,
        protected Kernel $kernel,
        protected Security $security
    ) {
        $this->config = $this->normalizeSymfonyConfig($config);
        $this->config['container'] = $kernel->getContainer();
        $this->config['user_class'] = $this->config['security']['user_class'] ?? Atk4\Data\Models\User::class;

        if (isset($this->config['security'])) {
            unset($this->config['security']);
        }

        if (isset($this->config['persistences'])) {
            unset($this->config['persistences']);
        }
    }

    /**
     * @throws \ErrorException
     */
    public function getApp(): App
    {
        if (null === $this->app) {
            $this->config['security'] = $this->security;
            $this->app = new App($this->config);
        }

        return $this->app;
    }

    private function normalizeSymfonyConfig(array $config)
    {
        $config['cdn']['fomantic-ui'] = $config['cdn']['fomantic'];
        unset($config['cdn']['fomantic']);

        $config['cdn']['highlight.js'] = $config['cdn']['highlight'];
        unset($config['cdn']['highlight']);

        $config['cdn']['chart.js'] = $config['cdn']['chart'];
        unset($config['cdn']['chart']);

        if (PHP_SAPI === 'cli') {
            return $config;
        }

        foreach ($config['cdn'] as &$uri) {
            $uri = $this->requestStack->getCurrentRequest()->getUriForPath($uri);
        }

        return $config;
    }
}
