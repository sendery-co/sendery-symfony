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

## Development and staging

Use a **Sandbox** API key for development and staging. Sendery prevents email and workflow delivery from that key, even when the project is live. Production requires a **Live** key and live sending enabled on the project.

To use SMTP or another Symfony transport, set `MAILER_DSN` normally and register this listener. It renders the published Sendery template before the configured transport sends it; choose a test SMTP destination for development.

```yaml
services:
  Sendery\Client:
    arguments: ['%env(SENDERY_API_KEY)%']
  Sendery\Symfony\TemplateEmailListener:
    arguments: ['@Sendery\Client']
    tags:
      - { name: kernel.event_listener, event: Symfony\Component\Mailer\Event\MessageEvent }
```

Keep `SENDERY_API_KEY` in your server environment. Rendering is limited to 10 requests per minute per workspace and 100 / 1,000 / 5,000 / 10,000 per calendar month on Free / Starter / Business / Scale or Enterprise. Render errors stop delivery. Direct client calls use the API independently of `MAILER_DSN`.

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
    ->to('steven@example.com')
    ->template('your-template', [
        'name' => 'Steven',
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
    ->to('steven@example.com')
    ->template('your-template', [
        'name' => 'Steven',
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

## Events and workflows

Send events such as account signups or orders from your app. Your team can use them to automate emails in Sendery, choosing the content, recipients, and timing. Available on all plans.

Start with [Register event definitions](https://sendery.co/en/docs/register-event-definitions) to define the data Sendery can expect, then [send events](https://sendery.co/en/docs/send-events) as they happen. Define at least one email address field and include an address when sending events.

Use a `Sendery\Client` configured with the same project API key. Events do not go through Symfony Mailer.

```php
$receipt = $sendery->event(
    name: 'account.created',
    language: 'en',
    objectType: 'account',
    objectId: '842',
    data: ['email' => 'steven@example.com', 'name' => 'Steven'],
)->retry()->send();
```

`language` selects a published template translation; omit it to use the template’s default.

Retries reuse the same event data and idempotency key. To retry across jobs or process restarts, supply and persist your own key. A successful response confirms that Sendery accepted the event. [Learn what you can automate with workflows](https://sendery.co/en/docs/build-workflow).
