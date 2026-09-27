# Sendery for Symfony

Send Sendery templates through Symfony Mailer.

[Documentation](https://sendery.co/en/docs/symfony) · [API reference](https://sendery.co/en/docs/send-email) · [Changelog](CHANGELOG.md)

## Requirements

Symfony Mailer 7.4 and PHP 8.3+ with the cURL extension.

## Install

```bash
composer require sendery/symfony:^0.1
```

## Register the transport

Publish a `welcome` template with `name` and `action_url` variables, and create a [project API key](https://sendery.co/en/docs/authentication). Add the factory to `config/services.yaml`.

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

Inject `MailerInterface` and send a `TemplateEmail`. Symfony requires `from()` for validation; Sendery uses the sender configured on your project. Use one recipient. HTML emails, attachments, and `cc` or `bcc` recipients are not supported.

```php
use Sendery\Symfony\TemplateEmail;
use Symfony\Component\Mailer\MailerInterface;

final class WelcomeEmails
{
    public function __construct(private MailerInterface $mailer) {}

    public function send(string $address, string $name): void
    {
        $email = (new TemplateEmail())
            ->from('hello@your-domain.com')
            ->to($address)
            ->template('welcome', [
                'name' => $name,
                'action_url' => 'https://example.com/start',
            ]);

        $this->mailer->send($email);
    }
}
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

API failures throw `TransportException` with a [`Sendery\ApiException`](https://sendery.co/en/docs/php) as the previous exception. Inspect its `status` and `errorCode`. The transport makes one attempt; configure Messenger to [retry temporary failures](https://sendery.co/en/docs/idempotency) and avoid retrying validation or billing errors.

## More

See [idempotency and retries](https://sendery.co/en/docs/idempotency) for retry conditions, delays, and reusing a key across attempts.

## License

[MIT](LICENSE).
