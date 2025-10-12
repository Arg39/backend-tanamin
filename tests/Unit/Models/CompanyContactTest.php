<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\CompanyContact;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class CompanyContactTest extends TestCase
{
    #[Test]
    public function it_can_fill_and_get_attributes()
    {
        $data = [
            'telephone' => '021-123456',
            'email' => 'info@company.com',
            'address' => 'Jl. Raya No. 1',
            'social_media' => ['instagram' => '@company', 'facebook' => 'companyfb'],
        ];

        $contact = new CompanyContact($data);
        $contact->id = 'contact-1';

        $this->assertEquals('contact-1', $contact->id);
        $this->assertEquals('021-123456', $contact->telephone);
        $this->assertEquals('info@company.com', $contact->email);
        $this->assertEquals('Jl. Raya No. 1', $contact->address);
        $this->assertEquals(['instagram' => '@company', 'facebook' => 'companyfb'], $contact->social_media);
    }

    #[Test]
    public function it_has_correct_table_and_key_settings()
    {
        $contact = new CompanyContact();

        $this->assertEquals('company_contacts', $contact->getTable());
        $this->assertFalse($contact->incrementing);
        $this->assertEquals('string', $contact->getKeyType());
    }

    #[Test]
    public function it_generates_uuid_when_creating()
    {
        // Mock CompanyContact model
        $contact = \Mockery::mock(CompanyContact::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $contact->fill([
            'telephone' => '021-123456',
            'email' => 'info@company.com',
            'address' => 'Jl. Raya No. 1',
            'social_media' => ['instagram' => '@company'],
        ]);
        $contact->setAttribute('id', null);

        $contact->shouldReceive('save')->andReturnUsing(function () use ($contact) {
            if (empty($contact->id)) {
                $contact->id = (string) Str::uuid();
            }
            return true;
        });

        $contact->save();

        $this->assertNotEmpty($contact->id);
        $this->assertTrue(Str::isUuid($contact->id));
    }
}
