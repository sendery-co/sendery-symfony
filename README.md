# Sendery — Symfony integration

Send template emails from Symfony with the Sendery SDK.

MIT licensed. Repository: https://github.com/sendery-co/sendery-symfony

Documentation: https://sendery.co/en/docs/symfony

## Install

```
composer require sendery/symfony:^0.1
```

## Install

Symfony Mailer 7.4 / PHP 8.3+

## Configure the transport

Register SenderyTransportFactory as a service tagged mailer.transport_factory. Set MAILER_DSN=sendery://YOUR_API_KEY@sendery.co. Use a secret environment variable, never commit the DSN.

## Send template messages

Use TemplateEmail, including from/to for Symfony’s envelope validation. Sendery uses the sender configured on your project. Arbitrary HTML mail and multiple recipients are rejected.

## Queue with Messenger

Symfony Messenger can serialize TemplateEmail with its generated key and variables. Retry the same message on transient failures. The transport wraps Sendery\ApiException in TransportException so a retry strategy can inspect the underlying status and code.

## Configuration example

```
# config/services.yaml
services:
  Sendery\Symfony\SenderyTransportFactory:
    tags: ['mailer.transport_factory']

# .env.local
MAILER_DSN=sendery://YOUR_API_KEY@sendery.co
```

## Example

```
use Sendery\Symfony\TemplateEmail;

$email = (new TemplateEmail())
    ->from('hello@your-domain.com')
    ->to('alex@example.com')
    ->template('welcome', ['name' => 'Alex']);
$mailer->send($email);
```

## Retries and queues

Reuse a prepared email for retries. New requests receive new keys; when reconstructing a request in another process, supply the original key and unchanged data. Keep API keys server-side. Framework mailers send Sendery templates, not arbitrary HTML or attachments.
