# Sendery for Symfony

Send Sendery templates through Symfony Mailer.

[Documentation](https://sendery.co/en/docs/symfony) · [API reference](https://sendery.co/en/docs/send-email) · [Changelog](CHANGELOG.md)

## Requirements

Symfony Mailer 7.4 and PHP 8.3+ with the cURL extension.

## Install

```bash
composer require sendery/symfony:^0.1.1
```

## Register the transport

Create a [project API key](https://sendery.co/en/docs/authentication) and register the transport in `config/services.yaml`.

```yaml
# config/services.yaml — add to your existing services section.
services:
  Sendery\Symfony\SenderyTransportFactory:
    tags: ['mailer.transport_factory']
```

## Set the API key

Set `MAILER_DSN` in `.env.local` or your hosting provider’s secret settings.

```dotenv
MAILER_DSN=sendery://YOUR_PROJECT_API_KEY@sendery.co
```

## Configure Mailer

Use the DSN in your existing Mailer configuration.

```yaml
# config/packages/mailer.yaml
framework:
  mailer:
    dsn: '%env(MAILER_DSN)%'
```

## Send an email

Pass the recipient, your published template’s key, and its variables to this service. Set `from()` to your project’s sender address.

```php
use Sendery\Symfony\TemplateEmail;
use Symfony\Component\Mailer\MailerInterface;

final class TemplateEmails
{
    public function __construct(private MailerInterface $mailer) {}

    public function send(string $address, string $template, array $data): void
    {
        $email = (new TemplateEmail())
            ->from('hello@your-domain.com')
            ->to($address)
            ->template($template, $data);

        $this->mailer->send($email);
    }
}
```

## Send a specific version

Choose a [published template version](https://sendery.co/en/docs/send-email#section-5) to keep sending it after newer versions are published. By default, Sendery uses the latest version.

```php
use Sendery\Symfony\TemplateEmail;

$email = (new TemplateEmail())
    ->from('hello@your-domain.com')
    ->to('alex@example.com')
    ->template('your-template', [
        'name' => 'Alex',
        'action_url' => 'https://example.com/start',
    ])
    ->version(3);

$mailer->send($email);
```

## Attachments

Attach files to `TemplateEmail` with Symfony’s `attach()` method.

Send up to 10 files totaling 5 MB. See the [attachment reference](https://sendery.co/en/docs/send-email#section-6) for supported formats and limits.

```php
use Sendery\Symfony\TemplateEmail;

$email = (new TemplateEmail())
    ->from('hello@your-domain.com')
    ->to('alex@example.com')
    ->template('your-template', [
        'name' => 'Alex',
        'action_url' => 'https://example.com/start',
    ], idempotencyKey: 'your-idempotency-key')
    ->attach(file_get_contents('/path/document.pdf'), 'document.pdf', 'application/pdf');

$mailer->send($email);
```

## Send with Messenger

With Symfony Messenger and your chosen transport installed, set `MESSENGER_TRANSPORT_DSN` and route mail to `async`. Run `php bin/console messenger:consume async`. The same queued `TemplateEmail` keeps its key and variables on retries.

```yaml
# config/packages/messenger.yaml
framework:
  messenger:
    transports:
      async: '%env(MESSENGER_TRANSPORT_DSN)%'
    routing:
      'Symfony\Component\Mailer\Messenger\SendEmailMessage': async
```

## Handle failures

A failed send throws `TransportException`. If you use Messenger, [retry temporary failures](https://sendery.co/en/docs/queues) with the same email. Fix [API key, template, or billing errors](https://sendery.co/en/docs/errors) before trying again.

## More

Learn how to [retry emails without duplicate sends](https://sendery.co/en/docs/idempotency).

## License

[MIT](LICENSE).
