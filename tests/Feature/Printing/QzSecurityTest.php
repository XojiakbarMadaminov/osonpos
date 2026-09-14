<?php

use App\Actions\Organizations\CreateDefaultOrganizationRoles;
use App\Domain\Authorization\OrganizationAuthorization;
use App\Enums\OrganizationRole;
use App\Models\Device;
use App\Models\Organization;
use App\Models\Store;
use App\Models\User;

function qzSecurityContext(): array
{
    $organization = Organization::factory()->create();
    $store = Store::factory()->for($organization)->create();
    $user = User::factory()->create();
    $organization->users()->attach($user);
    $store->users()->attach($user);
    app(CreateDefaultOrganizationRoles::class)->execute($organization);
    app(OrganizationAuthorization::class)->runForUserInTenant(
        $user,
        $organization,
        fn (User $tenantUser) => $tenantUser->assignRole(OrganizationRole::Cashier->value),
    );
    $device = Device::factory()->for($organization)->for($store)->create();

    return [$user, [
        'current_organization_id' => $organization->id,
        'current_store_id' => $store->id,
        ...posDeviceSession($device),
    ]];
}

it('keeps QZ signing endpoints unavailable until production signing is enabled', function () {
    [$user, $session] = qzSecurityContext();

    $this->actingAs($user)->withSession($session)->get('/api/pos/qz/certificate')->assertNotFound();
    $this->actingAs($user)->withSession($session)->postJson('/api/pos/qz/sign', ['data' => 'payload'])->assertNotFound();
});

it('signs QZ payloads server-side without returning the private key', function () {
    [$user, $session] = qzSecurityContext();
    $privateKey = openssl_pkey_new(['private_key_bits' => 2048]);
    openssl_pkey_export($privateKey, $privatePem);
    $details = openssl_pkey_get_details($privateKey);
    $privatePath = tempnam(storage_path('framework/testing'), 'qz-key-');
    $certificatePath = tempnam(storage_path('framework/testing'), 'qz-cert-');
    file_put_contents($privatePath, $privatePem);
    file_put_contents($certificatePath, 'test-certificate');
    config()->set('printing.qz', [
        'signing_enabled' => true,
        'certificate_path' => $certificatePath,
        'private_key_path' => $privatePath,
        'private_key_passphrase' => null,
    ]);

    try {
        $this->actingAs($user)->withSession($session)->get('/api/pos/qz/certificate')
            ->assertOk()->assertSeeText('test-certificate');
        $signature = $this->actingAs($user)->withSession($session)
            ->postJson('/api/pos/qz/sign', ['data' => 'payload-to-sign'])
            ->assertOk()->getContent();

        expect($signature)->not->toContain('PRIVATE KEY')
            ->and(openssl_verify('payload-to-sign', base64_decode($signature), $details['key'], OPENSSL_ALGO_SHA512))->toBe(1);
    } finally {
        unlink($privatePath);
        unlink($certificatePath);
    }
});
