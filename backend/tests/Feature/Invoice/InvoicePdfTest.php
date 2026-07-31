<?php

namespace Tests\Feature\Invoice;

use App\Enums\TenantRole;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\Concerns\CreatesTenantUsers;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use CreatesTenantUsers, RefreshDatabase;

    public function test_owner_can_download_the_invoice_pdf(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->get("/api/v1/invoices/{$invoice->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_role_without_invoices_view_cannot_download_the_pdf(): void
    {
        [$tenant, $photographer] = $this->createTenantWithUser(TenantRole::Photographer);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($photographer)
            ->get("/api/v1/invoices/{$invoice->id}/pdf")
            ->assertForbidden();
    }

    public function test_a_tenant_cannot_download_another_tenants_invoice_pdf(): void
    {
        [, $ownerA] = $this->createTenantWithUser(TenantRole::Owner);
        [$tenantB] = $this->createTenantWithUser(TenantRole::Owner);
        $invoiceB = Invoice::factory()->create(['tenant_id' => $tenantB->id]);

        $this->actingAsUser($ownerA)
            ->get("/api/v1/invoices/{$invoiceB->id}/pdf")
            ->assertNotFound();
    }

    public function test_it_generates_a_time_limited_signed_share_link(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAsUser($owner)
            ->getJson("/api/v1/invoices/{$invoice->id}/share-link")
            ->assertOk();

        $this->assertStringContainsString('signature=', $response->json('data.url'));
        $this->assertNotNull($response->json('data.expires_at'));
    }

    public function test_the_signed_link_serves_the_pdf_with_no_authentication(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $url = URL::temporarySignedRoute('api.v1.invoices.public-pdf', now()->addDays(30), ['invoice' => $invoice->id]);

        $this->get($url)
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_a_tampered_signature_is_rejected(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $url = URL::temporarySignedRoute('api.v1.invoices.public-pdf', now()->addDays(30), ['invoice' => $invoice->id]);

        $this->get($url.'tampered')->assertForbidden();
    }

    public function test_an_expired_signed_link_is_rejected(): void
    {
        [$tenant] = $this->createTenantWithUser(TenantRole::Owner);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $url = URL::temporarySignedRoute('api.v1.invoices.public-pdf', now()->subDay(), ['invoice' => $invoice->id]);

        $this->get($url)->assertForbidden();
    }

    public function test_the_pdf_renders_fine_when_the_tenant_has_no_logo(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->assertNull($tenant->logo_path);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->get("/api/v1/invoices/{$invoice->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_the_pdf_embeds_the_tenants_logo_when_one_is_uploaded(): void
    {
        Storage::fake('public');

        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $this->actingAsUser($owner)
            ->post('/api/v1/settings/logo', ['logo' => UploadedFile::fake()->image('logo.png')])
            ->assertOk();

        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->get("/api/v1/invoices/{$invoice->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /**
     * A tenant that deleted/replaced its logo file behind the scenes (or a
     * migration gone wrong) shouldn't break PDF generation — the logo is
     * just dropped from the render, same as never having one.
     */
    public function test_the_pdf_still_renders_if_the_stored_logo_file_is_missing(): void
    {
        Storage::fake('public');

        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $tenant->update(['logo_path' => 'tenants/'.$tenant->id.'/missing-logo.png']);
        $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAsUser($owner)
            ->get("/api/v1/invoices/{$invoice->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /**
     * Regression test: Helvetica/DejaVu Sans (dompdf's defaults) have no
     * Khmer glyphs — a customer or item name in Khmer used to render as
     * "???????" until resources/views/pdf/invoice.blade.php registered
     * Noto Sans Khmer. dompdf caches that font's metrics under
     * storage_path('fonts') on first use, which doesn't exist by default
     * (see InvoiceService::renderPdf()) — this also guards against that
     * regressing.
     */
    public function test_the_pdf_renders_khmer_text_without_erroring(): void
    {
        [$tenant, $owner] = $this->createTenantWithUser(TenantRole::Owner);
        $customer = \App\Models\Customer::factory()->create(['tenant_id' => $tenant->id, 'name' => 'សុខ ចាន់ថា']);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'notes' => 'អរគុណសម្រាប់ការជាវសេវាកម្មរបស់យើង',
        ]);

        $this->actingAsUser($owner)
            ->get("/api/v1/invoices/{$invoice->id}/pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
