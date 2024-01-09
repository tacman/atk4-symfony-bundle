<?php

namespace Atk4\Symfony\Module\Security;

use Atk4\Symfony\Module\Atk4App;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class UserAuthenticator extends AbstractAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly HttpUtils $httpUtils,
        private readonly Atk4App $atk4app
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('email');
        $password = $request->request->get('password');

        return new Passport(
            new UserBadge($email, function ($userIdentifier) {
                $model = $this->atk4app->getApp()->getModel(\App\Models\User::class);
                $entity = $model->tryLoadBy('email', $userIdentifier);

                if (null === $entity) {
                    throw new AuthenticationCredentialsNotFoundException('User not found');
                }

                return User::fromUserLogin($entity);
            }),
            new PasswordCredentials($password),
            // [new CsrfTokenBadge('login', $csrfToken)]
        );
    }

    protected function getLoginUrl(Request $request): string
    {
        return '/login';
    }

    public function supports(Request $request): ?bool
    {
        return $request->isMethod('POST') && $this->getLoginUrl($request) === $request->getBaseUrl().$request->getPathInfo();
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
