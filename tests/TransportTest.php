<?php

use PHPUnit\Framework\TestCase;
use Sendery\Client;
use Sendery\Symfony\SenderyTransport;
use Sendery\Symfony\TemplateEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Email;

class TransportTest extends TestCase
{
    public function test_serialized_template_messages_keep_the_key_and_data(): void
    {
        $keys = [];
        $transport = new SenderyTransport(new Client('secret', 'https://example.com', function ($method, $url, $headers, $body) use (&$keys): array {
            $keys[] = $headers['Idempotency-Key'];
            $data = json_decode($body, true);
            $this->assertSame('welcome', $data['template']);
            $this->assertSame(3, $data['version']);
            $this->assertSame(['name' => 'Alex'], $data['data']);
            $this->assertArrayNotHasKey('html', $data);
            $this->assertSame(['filename' => 'invoice.pdf', 'content' => base64_encode("PDF\x00bytes"), 'content_type' => 'application/pdf'], $data['attachments'][0]);

            return ['status' => 202, 'body' => '{"id":"msg","status":"queued"}'];
        }));
        $email = (new TemplateEmail)->from('sender@example.com')->to('alex@example.com')->template('welcome', ['name' => 'Alex'])->version(3)->attach("PDF\x00bytes", 'invoice.pdf', 'application/pdf');
        $transport->send($email);
        $transport->send(unserialize(serialize($email)));
        $this->assertSame($keys[0], $keys[1]);
    }

    public function test_ordinary_html_mail_is_not_silently_discarded(): void
    {
        $transport = new SenderyTransport(new Client('secret', 'https://example.com'));
        $this->expectException(TransportException::class);
        $transport->send((new Email)->from('sender@example.com')->to('alex@example.com')->html('Hello'));
    }
}
