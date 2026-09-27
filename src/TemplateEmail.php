<?php

namespace Sendery\Symfony;

use Symfony\Component\Mime\Email;

class TemplateEmail extends Email
{
    public function template(string $key, array $data, ?string $locale = null, ?string $idempotencyKey = null): static
    {
        foreach (['X-Sendery-Template', 'X-Sendery-Data', 'X-Sendery-Locale', 'X-Sendery-Key'] as $name) {
            $this->getHeaders()->remove($name);
        }
        $this->getHeaders()->addTextHeader('X-Sendery-Template', $key);
        $this->getHeaders()->addTextHeader('X-Sendery-Data', base64_encode(json_encode((object) $data, JSON_THROW_ON_ERROR)));
        $this->getHeaders()->addTextHeader('X-Sendery-Key', $idempotencyKey ?? bin2hex(random_bytes(16)));
        if ($locale !== null) {
            $this->getHeaders()->addTextHeader('X-Sendery-Locale', $locale);
        }
        $this->text(' ');

        return $this;
    }
}
