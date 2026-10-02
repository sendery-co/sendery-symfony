<?php

namespace Sendery\Symfony;

use Sendery\ApiException;
use Sendery\Attachment;
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
        if (! $email instanceof Email || count($message->getEnvelope()->getRecipients()) !== 1 || $email->getCc() || $email->getBcc()) {
            throw new TransportException('Sendery supports one recipient per template email, without CC or BCC.');
        }
        $attachments = [];
        foreach ($email->getAttachments() as $part) {
            if ($part->getDisposition() !== 'attachment') {
                throw new TransportException('Inline attachments are not supported; use a hosted image in the template.');
            }
            $attachments[] = new Attachment($part->getFilename() ?? 'attachment', $part->getBody(), $part->getMediaType().'/'.$part->getMediaSubtype());
        }
        $headers = $email->getHeaders();
        $template = $headers->get('X-Sendery-Template')?->getBodyAsString();
        $key = $headers->get('X-Sendery-Key')?->getBodyAsString();
        if (! $template || ! $key) {
            throw new TransportException('Use a Sendery template email. Arbitrary HTML emails are not supported.');
        }
        $data = json_decode(base64_decode($headers->get('X-Sendery-Data')?->getBodyAsString() ?? '', true) ?: '', true, flags: JSON_THROW_ON_ERROR);
        $version = $headers->get('X-Sendery-Version')?->getBodyAsString();
        if ($version !== null && (! ctype_digit($version) || (int) $version < 1)) {
            throw new TransportException('Version must be a positive integer.');
        }
        try {
            $receipt = $this->client->send($message->getEnvelope()->getRecipients()[0]->getAddress(), $template, $data, $headers->get('X-Sendery-Locale')?->getBodyAsString(), $key, $attachments, $version === null ? null : (int) $version);
            $message->setMessageId($receipt['id']);
        } catch (ApiException $exception) {
            throw new TransportException($exception->getMessage(), $exception->status, $exception);
        }
    }
}
