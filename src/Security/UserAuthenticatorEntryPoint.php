<?php

namespace Atk4\Symfony\Module\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class UserAuthenticatorEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, AuthenticationException $authenticationException = null): RedirectResponse
    {
        // add a custom flash message and redirect to the login page
        $request->getSession()->getFlashBag()->add('note', 'You have to login in order to access this page.');

        return new RedirectResponse('/login');
    }
}
