<?php

namespace Tests\Feature\Finance;

use App\Enums\UserRole;
use App\Models\FeeInvoice;
use App\Models\FeeItem;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceWorkspaceAndGatewaySettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Setting::flushCache();

        parent::tearDown();
    }

    public function test_accountant_principal_and_admin_can_open_finance_but_only_admin_configures_gateways(): void
    {
        $accountant = User::factory()->role(UserRole::Accountant)->create();
        $principal = User::factory()->role(UserRole::Principal)->create();
        $admin = User::factory()->role(UserRole::Admin)->create();

        foreach ([$accountant, $principal, $admin] as $actor) {
            $this->actingAs($actor)
                ->get($this->webUrl('/admin/finance'))
                ->assertOk()
                ->assertSee('Fees and Payments');
        }

        $this->actingAs($principal)
            ->get($this->webUrl('/admin/finance/gateways'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get($this->webUrl('/admin/finance/gateways'))
            ->assertOk()
            ->assertSee('Gateway Settings');
    }

    public function test_gateway_secrets_are_encrypted_and_blank_updates_preserve_them(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();

        $this->actingAs($admin)
            ->put($this->webUrl('/admin/finance/gateways'), [
                'enabled_payment_gateways' => ['paystack', 'flutterwave'],
                'finance_fallback_email' => 'payments@beloved.example',
                'paystack_public_key' => 'pk_test_public',
                'paystack_secret_key' => 'sk_test_private',
                'paystack_webhook_secret' => 'paystack-hook-secret',
                'flutterwave_secret_key' => 'flw-secret',
                'flutterwave_secret_hash' => 'flw-hash',
            ])->assertSessionHasNoErrors();

        $stored = Setting::query()->where('key', 'paystack_secret_key')->value('value');
        $this->assertStringStartsWith('encrypted:', $stored);
        $this->assertStringNotContainsString('sk_test_private', $stored);
        $this->assertSame('sk_test_private', Setting::getValue('paystack_secret_key'));

        $this->actingAs($admin)
            ->put($this->webUrl('/admin/finance/gateways'), [
                'enabled_payment_gateways' => ['paystack'],
                'paystack_secret_key' => '',
                'paystack_webhook_secret' => '',
            ])->assertSessionHasNoErrors();

        $this->assertSame('sk_test_private', Setting::getValue('paystack_secret_key'));
    }

    public function test_student_and_linked_parent_can_open_payment_portal(): void
    {
        $parent = User::factory()->role(UserRole::Parent)->create();
        $studentUser = User::factory()->role(UserRole::Student)->create();
        $student = Student::query()->create([
            'user_id' => $studentUser->id,
            'parent_user_id' => $parent->id,
            'admission_no' => 'ADM-26-FIN-PORTAL',
            'status' => 'active',
        ]);
        $feeItem = FeeItem::query()->create([
            'name' => 'Portal Fee',
            'amount' => 2000,
            'is_mandatory' => true,
        ]);
        FeeInvoice::query()->create([
            'invoice_no' => 'INV-FIN-PORTAL',
            'student_id' => $student->id,
            'fee_item_id' => $feeItem->id,
            'amount_due' => 2000,
            'amount_paid' => 0,
            'balance' => 2000,
            'status' => 'unpaid',
            'issued_at' => now(),
        ]);

        $this->actingAs($studentUser)
            ->get($this->appUrl('/payments'))
            ->assertOk()
            ->assertSee('Select invoices to pay');

        $this->actingAs($parent)
            ->get($this->appUrl('/payments?student_id='.$student->id))
            ->assertOk()
            ->assertSee('INV-FIN-PORTAL');
    }

    private function webUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.web').$path;
    }

    private function appUrl(string $path): string
    {
        return 'http://'.config('platform.hosts.app').$path;
    }
}
