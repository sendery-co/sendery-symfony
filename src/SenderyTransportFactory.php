<?php

namespace Sendery\Symfony;

use Sendery\Client;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

class SenderyTransportFactory extends AbstractTransportFactory
{
    public function create(Dsn $dsn): TransportInterface
    {
        if (! $this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn, 'sendery', $this->getSupportedSchemes());
        }

        return new SenderyTransport(new Client($dsn->getUser() ?? '', 'https://'.$dsn->getHost().($dsn->getPort() ? ':'.$dsn->getPort() : '')));
    }

    protected function getSupportedSchemes(): array
    {
        return ['sendery'];
    }
}
