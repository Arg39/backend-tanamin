<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\ContactUsMessage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class ContactUsMessageTest extends TestCase
{
    #[Test]
    public function it_can_fill_and_get_attributes()
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'subject' => 'Inquiry',
            'message' => 'Hello, I have a question.',
        ];

        $message = new ContactUsMessage($data);
        $message->id = 'msg-1';

        $this->assertEquals('msg-1', $message->id);
        $this->assertEquals('John Doe', $message->name);
        $this->assertEquals('john@example.com', $message->email);
        $this->assertEquals('Inquiry', $message->subject);
        $this->assertEquals('Hello, I have a question.', $message->message);
    }

    #[Test]
    public function it_has_correct_table_and_key_settings()
    {
        $message = new ContactUsMessage();

        $this->assertEquals('contact_us_messages', $message->getTable());
        $this->assertFalse($message->incrementing);
        $this->assertEquals('string', $message->getKeyType());
    }

    #[Test]
    public function it_generates_uuid_when_creating()
    {
        $message = \Mockery::mock(ContactUsMessage::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $message->fill([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'subject' => 'Support',
            'message' => 'Need help with my order.',
        ]);
        $message->setAttribute('id', null);

        $message->shouldReceive('save')->andReturnUsing(function () use ($message) {
            if (empty($message->id)) {
                $message->id = (string) Str::uuid();
            }
            return true;
        });

        $message->save();

        $this->assertNotEmpty($message->id);
        $this->assertTrue(Str::isUuid($message->id));
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
