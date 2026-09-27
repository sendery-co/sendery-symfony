<?php

namespace Sendery\Symfony;

use Sendery\ApiException;
use Sendery\Client;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

class SenderyTransport extends AbstractTransport
{
    public function __construct(private Client $client)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'sendery';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();
        if (! $email instanceof Email || count($message->getEnvelope()->getRecipients()) !== 1 || $email->getCc() || $email->getBcc() || $email->getAttachments()) {
            throw new TransportException('Sendery supports one recipient per template email and no attachments.');
        }
        $headers = $email->getHeaders();
        $template = $headers->get('X-Sendery-Template')?->getBodyAsString();
        $key = $headers->get('X-Sendery-Key')?->getBodyAsString();
        if (! $template || ! $key) {
            throw new TransportException('Use a Sendery template email. Arbitrary HTML emails are not supported.');
        }
        $data = json_decode(base64_decode($headers->get('X-Sendery-Data')?->getBodyAsString() ?? '', true) ?: '', true, flags: JSON_THROW_ON_ERROR);
        try {
            $receipt = $this->client->send($message->getEnvelope()->getRecipients()[0]->getAddress(), $template, $data, $headers->get('X-Sendery-Locale')?->getBodyAsString(), $key);
            $message->setMessageId($receipt['id']);
        } catch (ApiException $exception) {
            throw new TransportException($exception->getMessage(), $exception->status, $exception);
        }
    }
}
