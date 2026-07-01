<?php

namespace Tests\Unit;

use App\Models\ZcmPendingAd;
use App\Models\ZcmPendingAdEnrichment;
use App\Services\PrestashopCatalogService;
use App\Services\ZcmAdPrestashopDraftService;
use App\Services\ZcmAdPrestashopRulesService;
use Mockery;
use Tests\TestCase;

class ZcmAdPrestashopDraftServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_generate_forces_four_languages_and_preserves_reference_without_inventing_year(): void
    {
        config(['services.openai.key' => null]);

        $service = new ZcmAdPrestashopDraftService(
            $this->prestashopCatalog(['id' => 12, 'name' => 'Travagem', 'match_score' => 35]),
            new ZcmAdPrestashopRulesService()
        );

        $draft = $service->generate($this->ad());

        $this->assertSame(['pt', 'fr', 'es', 'en'], array_keys($draft['translations']));
        $this->assertArrayHasKey('pt', $draft['tags']);
        $this->assertArrayHasKey('fr', $draft['tags']);
        $this->assertArrayHasKey('es', $draft['tags']);
        $this->assertArrayHasKey('en', $draft['tags']);

        foreach ($draft['translations'] as $translation) {
            $this->assertStringContainsString('A2045455132', $translation['name']);
            $this->assertStringContainsString('A2045455132', $translation['description']);
            $this->assertStringNotContainsString('2020', $translation['name']);
            $this->assertStringNotContainsString('2020', $translation['description']);
        }

        $this->assertNull($draft['vehicle_year']);
        $this->assertSame('2026-07-01', $draft['rules_version']);
        $this->assertContains('Categoria PrestaShop sugerida com baixa confianca.', $draft['generation_warnings']);
    }

    public function test_save_keeps_draft_in_technical_data_shape_and_accepts_tags_by_language(): void
    {
        $service = Mockery::mock(ZcmAdPrestashopDraftService::class, [
            $this->prestashopCatalog(['id' => 12, 'name' => 'Travagem', 'match_score' => 80]),
            new ZcmAdPrestashopRulesService(),
        ])->makePartial();
        $service->shouldReceive('store')->once()->andReturn(new ZcmPendingAdEnrichment());

        $ad = $this->ad();
        $ad->enrichment->technical_data = [
            'prestashop_draft' => [
                'translations' => [
                    'pt' => ['name' => 'Modulo ABS Mercedes A2045455132', 'iso_code' => 'pt'],
                    'fr' => ['name' => 'Module ABS Mercedes A2045455132', 'iso_code' => 'fr'],
                    'es' => ['name' => 'Modulo ABS Mercedes A2045455132', 'iso_code' => 'es'],
                    'en' => ['name' => 'ABS module Mercedes A2045455132', 'iso_code' => 'en'],
                ],
                'tags' => [
                    'pt' => ['A2045455132'],
                    'fr' => ['A2045455132'],
                    'es' => ['A2045455132'],
                    'en' => ['A2045455132'],
                ],
                'rules_version' => '2026-07-01',
            ],
        ];

        $draft = $service->save($ad, [
            'name' => 'Modulo ABS Mercedes A2045455132',
            'reference' => 'A2045455132',
            'price' => '125.50',
            'currency' => 'EUR',
            'quantity' => 1,
            'condition' => 'reconditioned',
            'category' => 'Modulo ABS',
            'prestashop_category_id' => 12,
            'manufacturer' => 'Mercedes',
            'model' => 'Classe C W204',
            'brand_filter' => 'Mercedes',
            'model_filter' => 'Classe C W204',
            'short_description' => 'Modulo ABS Mercedes A2045455132.',
            'description' => 'Modulo ABS Mercedes Classe C W204 A2045455132.',
            'meta_title' => 'Modulo ABS Mercedes A2045455132',
            'meta_description' => 'Modulo ABS Mercedes A2045455132.',
            'link_rewrite' => 'modulo-abs-mercedes-a2045455132',
            'approval_notes' => '',
            'keywords' => 'A2045455132, Modulo ABS, Mercedes',
            'tags' => [
                'pt' => 'A2045455132, Modulo ABS',
                'fr' => 'A2045455132, Module ABS',
                'es' => 'A2045455132, Modulo ABS',
                'en' => 'A2045455132, ABS module',
            ],
            'translations' => [
                'pt' => ['name' => 'Modulo ABS Mercedes A2045455132'],
                'fr' => ['name' => 'Module ABS Mercedes A2045455132'],
                'es' => ['name' => 'Modulo ABS Mercedes A2045455132'],
                'en' => ['name' => 'ABS module Mercedes A2045455132'],
            ],
        ]);

        $this->assertSame(['A2045455132', 'Modulo ABS', 'Mercedes'], $draft['keywords']);
        $this->assertSame(['A2045455132', 'Module ABS'], $draft['tags']['fr']);
        $this->assertSame('Mercedes', $draft['brand_filter']);
        $this->assertSame('Classe C W204', $draft['model_filter']);
        $this->assertSame('2026-07-01', $draft['rules_version']);
    }

    private function ad(): ZcmPendingAd
    {
        $ad = new ZcmPendingAd([
            'reference' => 'A2045455132',
            'title' => 'Modulo ABS Mercedes Classe C W204 A2045455132',
            'description' => '',
            'category' => 'Modulo ABS',
            'brand_model' => json_encode([
                'manufacturer' => 'Mercedes',
                'car_model' => 'Classe C W204',
            ]),
            'raw_payload' => [
                'brand_model' => [
                    'manufacturer' => 'Mercedes',
                    'car_model' => 'Classe C W204',
                ],
            ],
        ]);

        $ad->setRelation('enrichment', new ZcmPendingAdEnrichment([
            'ai_analysis' => [
                'part_type' => 'Modulo ABS',
                'brand' => 'Mercedes',
                'model' => 'Classe C W204',
            ],
            'seo' => [
                'title' => 'Modulo ABS Mercedes Classe C W204 A2045455132',
                'short_description' => 'Modulo ABS Mercedes A2045455132.',
                'long_description' => 'Modulo ABS Mercedes Classe C W204 A2045455132.',
                'meta_title' => 'Modulo ABS Mercedes A2045455132',
                'meta_description' => 'Modulo ABS Mercedes Classe C W204 A2045455132.',
                'slug' => 'modulo-abs-mercedes-a2045455132',
                'keywords' => ['A2045455132', 'Modulo ABS', 'Mercedes'],
            ],
            'research' => [],
            'images' => [],
            'technical_data' => [],
        ]));

        return $ad;
    }

    private function prestashopCatalog(array $category): PrestashopCatalogService
    {
        $prestashop = Mockery::mock(PrestashopCatalogService::class);
        $prestashop->shouldReceive('languages')->andReturn([
            ['id' => 1, 'name' => 'Portugues', 'iso_code' => 'pt', 'language_code' => 'pt-pt', 'active' => true],
        ]);
        $prestashop->shouldReceive('categories')->andReturn([$category + ['localized_names' => []]]);
        $prestashop->shouldReceive('bestCategory')->andReturn($category + ['localized_names' => []]);

        return $prestashop;
    }
}
