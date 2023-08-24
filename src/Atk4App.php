<?php

namespace Atk4\Symfony\Module;

use Atk4\Core\Exception;
use Atk4\Ui\App;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Atk4App
{
    private ?App $app = null;

    public function __construct(
        array $config,
        protected RequestStack $requestStack
    )
    {
        $this->app = new App(
            $this->normalizeSymfonyConfig($config)
        );
    }

    public function getApp(): App
    {
        return $this->app;
    }

    public function handleRequest($callback): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $response = $callback($this->app);

            if ($response instanceof \Symfony\Component\HttpFoundation\Response) {
                return $response;
            }

            if ($response !== null) {
                throw new Exception('Callback must return null or Symfony\Component\HttpFoundation\Response');
            }

            $this->app->run();
        } catch (\Atk4\Ui\Exception\ExitApplicationError $e) {
        } catch (\Throwable $e) {
            $this->app->caughtException($e);
        }

        return new \Symfony\Component\HttpFoundation\Response(
            $this->app->getResponse()->getBody()->getContents(),
            $this->app->getResponse()->getStatusCode(),
            $this->app->getResponse()->getHeaders()
        );
    }

    private function normalizeSymfonyConfig(array $config)
    {
        $config['cdn']['fomantic-ui'] = $config['cdn']['fomantic'];
        unset($config['cdn']['fomantic']);

        $config['cdn']['highlight.js'] = $config['cdn']['highlight'];
        unset($config['cdn']['highlight']);

        $config['cdn']['chart.js'] = $config['cdn']['chart'];
        unset($config['cdn']['chart']);


        foreach($config['cdn'] as &$uri) {
            $uri = $this->requestStack->getCurrentRequest()->getUriForPath($uri);
        }


        return $config;
    }
}