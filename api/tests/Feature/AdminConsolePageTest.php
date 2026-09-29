<?php

namespace Tests\Feature;

use Illuminate\Foundation\Vite;
use Illuminate\Foundation\ViteManifestNotFoundException;
use ReflectionProperty;
use Tests\TestCase;

class AdminConsolePageTest extends TestCase
{
    public function test_admin_console_renders_when_vite_manifest_is_present(): void
    {
        $this->assertFileExists(public_path('build/manifest.json'));
        $this->forgetViteManifestCache();

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertSee('EIS Bridge Console', false);
        $response->assertSee('id="admin-root"', false);
        $response->assertSee('/build/assets/', false);
    }

    public function test_missing_vite_manifest_throws_vite_manifest_not_found_exception(): void
    {
        $manifest = public_path('build/manifest.json');
        $backup = sys_get_temp_dir().'/eis-admin-manifest-'.uniqid('', true).'.json';

        $this->assertFileExists($manifest);
        copy($manifest, $backup);
        unlink($manifest);

        try {
            $this->forgetViteManifestCache();
            $this->withoutExceptionHandling();

            try {
                $this->get('/admin');
                $this->fail('GET /admin was expected to throw when the Vite manifest is missing.');
            } catch (\Throwable $e) {
                $root = $e;
                while ($root->getPrevious() instanceof \Throwable) {
                    $root = $root->getPrevious();
                }

                $this->assertInstanceOf(ViteManifestNotFoundException::class, $root);
                $this->assertStringContainsString('Vite manifest not found', $root->getMessage());
                $this->assertStringContainsString('manifest.json', $root->getMessage());
            }
        } finally {
            if (! is_file($manifest) && is_file($backup)) {
                copy($backup, $manifest);
            }
            if (is_file($backup)) {
                unlink($backup);
            }
            $this->forgetViteManifestCache();
        }
    }

    private function forgetViteManifestCache(): void
    {
        $property = new ReflectionProperty(Vite::class, 'manifests');
        $property->setValue(null, []);
    }
}
