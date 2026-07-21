<?php

namespace Tests\Feature\Settings;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingEncryptionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_sensitive_settings_are_encrypted_at_rest_and_decrypted_for_internal_use(): void
    {
        Setting::setMany([
            'school_name' => 'Beloved College',
            'paystack_secret_key' => 'sk_live_private_value',
        ]);

        $storedSecret = Setting::query()
            ->where('key', 'paystack_secret_key')
            ->value('value');

        $this->assertIsString($storedSecret);
        $this->assertStringStartsWith('encrypted:', $storedSecret);
        $this->assertStringNotContainsString('sk_live_private_value', $storedSecret);
        $this->assertSame('sk_live_private_value', Setting::getValue('paystack_secret_key'));
        $this->assertSame('Beloved College', Setting::getValue('school_name'));
        $this->assertArrayNotHasKey('paystack_secret_key', Setting::publicSettings());
    }

    public function test_blank_admin_secret_submission_preserves_the_existing_secret(): void
    {
        Setting::setMany(['paystack_secret_key' => 'existing-secret']);
        Setting::setMany(['paystack_secret_key' => '']);

        $this->assertSame('existing-secret', Setting::getValue('paystack_secret_key'));
        $this->assertSame('', Setting::forAdminForm()['paystack_secret_key']);
    }
}
