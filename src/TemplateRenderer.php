<?php

namespace Sendery\Symfony;

use Sendery\Client;
use Symfony\Component\Mime\Email;

class TemplateRenderer
{
    public function __construct(private Client $client) {}

    public function render(Email $email): void
    {
        $headers = $email->getHeaders();
        $template = $headers->get('X-Sendery-Template')?->getBodyAsString();
        if (! $template) {
            return;
        }
        $data = json_decode(base64_decode($headers->get('X-Sendery-Data')?->getBodyAsString() ?? '', true) ?: '', true, flags: JSON_THROW_ON_ERROR);
        $version = $headers->get('X-Sendery-Version')?->getBodyAsString();
        if ($version !== null && (! ctype_digit($version) || (int) $version < 1)) {
            throw new \InvalidArgumentException('Version must be a positive integer.');
        }
        $rendered = $this->client->render($template, $data, $headers->get('X-Sendery-Locale')?->getBodyAsString(), $version === null ? null : (int) $version);
        $email->subject($rendered['subject'])->html($rendered['html'])->text($rendered['text']);
        foreach (['X-Sendery-Template', 'X-Sendery-Data', 'X-Sendery-Locale', 'X-Sendery-Key', 'X-Sendery-Version'] as $name) {
            $headers->remove($name);
        }
    }
}
