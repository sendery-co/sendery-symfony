<?php

use PHPUnit\Framework\TestCase;
use Sendery\Client;
use Sendery\Symfony\SenderyTransport;
use Sendery\Symfony\TemplateEmail;
use Sendery\Symfony\TemplateEmailListener;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\NullTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Exception\LogicException;

class TransportTest extends TestCase
{
    public function test_smtp_listener_renders_content_removes_private_headers_and_skips_queued_and_sendery_messages(): void
    {
        $calls = 0;
        $client = new Client('sandbox-key', 'https://example.com', function ($method, $url, $headers, $body) use (&$calls): array {
            $calls++;
            $this->assertSame('https://example.com/api/v1/emails/render', $url);
            $this->assertSame(3, json_decode($body, true)['version']);

            return ['status' => 200, 'body' => '{"subject":"Hello","html":"<p>Hello</p>","text":"Hello"}'];
        });
        $email = (new TemplateEmail)->from('sender@example.com')->to('recipient@example.com')->template('welcome', ['name' => 'Secret'])->version(3)->attach('PDF', 'invoice.pdf');
        $listener = new TemplateEmailListener($client);
        $envelope = Envelope::create($email);
        $listener(new MessageEvent($email, $envelope, 'smtp://localhost', true));
        $listener(new MessageEvent($email, $envelope, 'sendery'));
        $this->assertSame(0, $calls);
        $listener(new MessageEvent($email, $envelope, 'smtp://localhost'));
        $this->assertSame(1, $calls);
        $this->assertSame('Hello', $email->getSubject());
        $this->assertSame('<p>Hello</p>', $email->getHtmlBody());
        $this->assertSame('Hello', $email->getTextBody());
        $this->assertCount(1, $email->getAttachments());
        $this->assertStringNotContainsString('X-Sendery-', $email->toString());
        $this->assertStringNotContainsString('Secret', $email->toString());
    }

    public function test_transport_event_renders_before_mime_validation(): void
    {
        $client = new Client('snd_sandbox_test', 'https://example.com', fn () => ['status' => 200, 'body' => '{"subject":"Rendered","html":"<p>Ready</p>","text":"Ready"}']);
        $dispatcher = new EventDispatcher;
        $dispatcher->addListener(MessageEvent::class, new TemplateEmailListener($client));
        $transport = new NullTransport($dispatcher);
        $message = $transport->send((new TemplateEmail)->from('a@example.com')->to('b@example.com')->template('welcome', []));
        $email = $message->getOriginalMessage();
        $this->assertSame('Rendered', $email->getSubject());
        $this->assertSame('Ready', $email->getTextBody());
        $this->assertStringNotContainsString('X-Sendery-', $email->toString());
    }

    public function test_missing_smtp_render_listener_cannot_send_an_empty_email(): void
    {
        $email = (new TemplateEmail)->from('sender@example.com')->to('recipient@example.com')->template('welcome', []);
        $this->expectException(LogicException::class);
        (new NullTransport)->send($email);
    }

    public function test_serialized_template_messages_keep_the_key_and_data(): void
    {
        $keys = [];
        $transport = new SenderyTransport(new Client('secret', 'https://example.com', function ($method, $url, $headers, $body) use (&$keys): array {
            $keys[] = $headers['Idempotency-Key'];
            $data = json_decode($body, true);
            $this->assertSame('welcome', $data['template']);
            $this->assertSame(3, $data['version']);
            $this->assertSame(['name' => 'Steven'], $data['data']);
            $this->assertArrayNotHasKey('html', $data);
            $this->assertSame(['filename' => 'invoice.pdf', 'content' => base64_encode("PDF\x00bytes"), 'content_type' => 'application/pdf'], $data['attachments'][0]);

            return ['status' => 202, 'body' => '{"id":"msg","status":"queued"}'];
        }));
        $email = (new TemplateEmail)->from('sender@example.com')->to('steven@example.com')->template('welcome', ['name' => 'Steven'])->version(3)->attach("PDF\x00bytes", 'invoice.pdf', 'application/pdf');
        $transport->send($email);
        $transport->send(unserialize(serialize($email)));
        $this->assertSame($keys[0], $keys[1]);
    }

    public function test_ordinary_html_mail_is_not_silently_discarded(): void
    {
        $transport = new SenderyTransport(new Client('secret', 'https://example.com'));
        $this->expectException(TransportException::class);
        $transport->send((new Email)->from('sender@example.com')->to('steven@example.com')->html('Hello'));
    }
}
