<?php

namespace Atk4\Symfony\Module\Middleware;

use Atk4\Core\Exception;
use Atk4\Symfony\Module\Atk4App;
use Atk4\Ui\Js\JsToast;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

class RequestMiddlewareListener
{
    public function __construct(
        protected Container $container,
        protected Atk4App $atk4app)
    {
    }

    public function onKernelRequest(RequestEvent $requestEvent): void
    {
        $requestEvent->getRequest();

        // Perform some logic here, e.g.:
        // $request->attributes->set('someAttribute', 'someValue');
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $controllerArgumentsEvent): void
    {
        if (!$controllerArgumentsEvent->isMainRequest()) {
            return;
        }

        $app = $this->atk4app->getApp();
        $controller = $controllerArgumentsEvent->getController();
        $controllerArgumentsEvent->setController(function (...$args) use ($app, $controller) {
            try {
                $response = $controller(...$args);

                if ($response instanceof \Symfony\Component\HttpFoundation\Response) {
                    return $response;
                }

                if (null !== $response) {
                    throw new Exception('Callback must return null or Symfony\Component\HttpFoundation\Response');
                }

                $app->run();
            } catch (\Atk4\Ui\Exception\ExitApplicationError $e) {
            } catch (\Throwable $e) {
                $app->caughtException($e);
            }

            $response = $app->getResponse();

            return new \Symfony\Component\HttpFoundation\Response(
                (string) $response->getBody(),
                $response->getStatusCode(),
                $response->getHeaders()
            );
        }
        );
    }

    public function onKernelResponse(ResponseEvent $responseEvent): void
    {
        $contentType = $responseEvent->getResponse()->headers->get('content-type') ?? '';
        $type = strtolower($contentType);
        if (false !== strpos('text/html', $type)) {
            $this->addFlashBagHtml($responseEvent);

            return;
        }

        if (false !== strpos('application/json', $type)) {
            $this->addFlashBagJson($responseEvent);

            return;
        }

        /*
        $pos = strripos($content, '</body>');

        if (false !== $pos) {
            $toolbar = "\n".str_replace("\n", '', $this->twig->render(
                    '@WebProfiler/Profiler/toolbar_js.html.twig',
                    [
                        'full_stack' => class_exists(FullStack::class),
                        'excluded_ajax_paths' => $this->excludedAjaxPaths,
                        'token' => $response->headers->get('X-Debug-Token'),
                        'request' => $request,
                        'csp_script_nonce' => $nonces['csp_script_nonce'] ?? null,
                        'csp_style_nonce' => $nonces['csp_style_nonce'] ?? null,
                    ]
                ))."\n";
            $content = substr($content, 0, $pos).$toolbar.substr($content, $pos);
            $response->setContent($content);
        }
        */
    }

    protected function getFlashMessages()
    {
        $flashBag = [];
        $session = $this->container->get('request_stack')->getSession();
        foreach ($session->getFlashBag()->all() as $type => $messages) {
            foreach ($messages as $message) {
                switch ($type) {
                    case 'success':
                        $message = [
                            'title' => $message['title'] ?? 'Success',
                            'message' => $message['message'] ?? $message,
                            'class' => 'success',
                        ];
                        break;
                    case 'error':
                        $message = [
                            'title' => $message['title'] ?? 'Error',
                            'message' => $message['message'] ?? $message,
                            'class' => 'error',
                        ];
                        break;
                    case 'warning':
                        $message = [
                            'title' => $message['title'] ?? 'Warning',
                            'message' => $message['message'] ?? $message,
                            'class' => 'warning',
                        ];
                        break;
                    case 'info':
                        $message = [
                            'title' => $message['title'] ?? 'Info',
                            'message' => $message['message'] ?? $message,
                            'class' => 'info',
                        ];
                        break;
                    default:
                        $message = [
                            'title' => $message['title'] ?? 'Message',
                            'message' => $message['message'] ?? $message,
                        ];
                        break;
                }

                $options = array_merge($message, [
                    'showProgress' => 'top',
                ]);

                $flashBag[] = new JsToast($options);
            }
        }

        return $flashBag;
    }

    private function addFlashBagHtml(ResponseEvent $responseEvent)
    {
        $response = $responseEvent->getResponse();
        $content = $response->getContent();

        $pos = strripos($content, '</body>');

        if (false !== $pos) {
            $flashes = '<script>';
            foreach ($this->getFlashMessages() as $flashMessage) {
                $flashes .= $flashMessage->jsRender().PHP_EOL;
            }
            $flashes .= '</script>';

            $content = substr($content, 0, $pos).$flashes.substr($content, $pos);
            $response->setContent($content);
        }
    }

    private function addFlashBagJson(ResponseEvent $responseEvent)
    {
        $response = $responseEvent->getResponse();
        $content = $response->getContent();
        $app = $this->atk4app->getApp();
        $json = $app->decodeJson($content);
        foreach ($this->getFlashMessages() as $flashMessage) {
            $json['atkjs'] .= $flashMessage->jsRender();
        }
        $content = $app->encodeJson($json);
        $response->setContent($content);
    }
}
