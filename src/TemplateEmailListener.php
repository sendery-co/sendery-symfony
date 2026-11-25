<?php

namespace Sendery\Symfony;

use Sendery\Client;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;

class TemplateEmailListener
{
    public function __construct(private Client $client) {}

    public function __invoke(MessageEvent $event): void
    {
        if ($event->isQueued() || $event->getTransport() === 'sendery') {
            return;
        }
        $email = $event->getMessage();
        if ($email instanceof Email) {
            (new TemplateRenderer($this->client))->render($email);
        }
    }
}
