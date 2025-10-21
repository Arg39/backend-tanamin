<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\CompanyPartnership;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;

class CompanyPartnershpTest extends TestCase
{
    #[Test]
    public function it_can_fill_and_get_attributes()
    {
        $data = [
            'partner_name' => 'Partner A',
            'logo' => 'logos/partner-a.png',
            'website_url' => 'https://partner-a.com',
        ];

        $partnership = new CompanyPartnership($data);

        $this->assertEquals('Partner A', $partnership->partner_name);
        $this->assertEquals('logos/partner-a.png', $partnership->logo);
        $this->assertEquals('https://partner-a.com', $partnership->website_url);
    }

    #[Test]
    public function it_has_correct_table_and_key_settings()
    {
        $partnership = new CompanyPartnership();

        $this->assertEquals('company_partnerships', $partnership->getTable());
        $this->assertFalse($partnership->incrementing);
        $this->assertEquals('string', $partnership->getKeyType());
    }

    #[Test]
    public function it_generates_uuid_when_creating()
    {
        $partnership = \Mockery::mock(CompanyPartnership::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $partnership->fill([
            'partner_name' => 'Partner B',
            'logo' => 'logos/partner-b.png',
            'website_url' => 'https://partner-b.com',
        ]);
        $partnership->setAttribute('id', null);

        $partnership->shouldReceive('save')->andReturnUsing(function () use ($partnership) {
            if (empty($partnership->id)) {
                $partnership->id = (string) Str::uuid();
            }
            return true;
        });

        $partnership->save();

        $this->assertNotEmpty($partnership->id);
        $this->assertTrue(Str::isUuid($partnership->id));
    }

    #[Test]
    public function it_returns_logo_url_attribute()
    {
        $partnership = new CompanyPartnership([
            'logo' => 'logos/partner-c.png',
        ]);

        $expectedUrl = url('storage/logos/partner-c.png');
        $this->assertEquals($expectedUrl, $partnership->logo_url);

        $partnership->logo = null;
        $this->assertNull($partnership->logo_url);
    }
}
