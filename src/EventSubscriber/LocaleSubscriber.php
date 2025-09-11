<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Http\SecurityEvents;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * Sets and updates the user preffered local in the session
 * Please read: https://symfony.com/doc/4.x/session/locale_sticky_session.html
 */
class LocaleSubscriber implements EventSubscriberInterface
{
    private String $defaultLocale;
    private SessionInterface $session;

    public function __construct(SessionInterface $session,  $defaultLocale = 'en')
    {
        $this->defaultLocale = $defaultLocale;
        $this->session = $session;
    }

    public function onInteractiveLogin(InteractiveLoginEvent $event)
    {
        $user = $event->getAuthenticationToken()->getUser();

        if (null !== $user->getLocale()) {
            $this->session->set('_locale', $user->getLocale());
        }
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();
        if (!$request->hasPreviousSession()) {
            return;
        }

        if ($locale = $request->request->get('locale')) {
            // set the local from the request to the seassion
            $request->setLocale($locale);
        } else {
            // if no explicit locale has been set on this request, use one from the session
            $request->setLocale($request->getSession()->get('_locale', $this->defaultLocale));
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
            SecurityEvents::INTERACTIVE_LOGIN => 'onInteractiveLogin',
        ];
    }
}
