<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The use-case pages: what people do with the product, by role and by task,
 * each one its own page and each one linked to the products it runs on.
 */
class PublicUseCasesPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function slugs(): array
    {
        return array_keys(__('ui.use_cases.items'));
    }

    public function test_the_index_groups_every_use_case_by_role_and_by_task(): void
    {
        $response = $this->get('/use-cases')->assertOk();

        $response->assertSeeText(__('ui.use_cases.roles'));
        $response->assertSeeText(__('ui.use_cases.tasks'));

        foreach (__('ui.use_cases.items') as $useCase) {
            $response->assertSeeText($useCase['role']);
        }
    }

    public function test_every_use_case_has_a_page_that_renders_its_sections(): void
    {
        foreach ($this->slugs() as $slug) {
            $useCase = __('ui.use_cases.items.'.$slug);

            $response = $this->get('/use-case/'.$slug)->assertOk();

            $response->assertSeeText($useCase['role']);
            $response->assertSeeText($useCase['summary']);
            $response->assertSeeText(__('ui.use_cases.tasks_title'));
            $response->assertSeeText(__('ui.use_cases.outcomes_title'));

            foreach ($useCase['outcomes'] as $outcome) {
                $response->assertSeeText($outcome);
            }
        }
    }

    public function test_a_use_case_links_to_the_products_it_runs_on(): void
    {
        $response = $this->get('/use-case/engineering')->assertOk();

        foreach (__('ui.use_cases.items.engineering.products') as $product) {
            $response->assertSee('/product/'.$product, false);
        }
    }

    public function test_a_use_case_links_to_its_documentation(): void
    {
        $response = $this->get('/use-case/engineering')->assertOk();

        $response->assertSee('/docs/'.__('ui.use_cases.items.engineering.docs'), false);
    }

    public function test_an_unknown_use_case_is_not_found(): void
    {
        $this->get('/use-case/not-a-use-case')->assertNotFound();
    }

    public function test_the_page_speaks_arabic_when_the_visitor_does(): void
    {
        $response = $this->get('/use-case/engineering?lang=ar')->assertOk();

        $response->assertSee('<html lang="ar" dir="rtl"', false);
        $response->assertSeeText(__('ui.use_cases.items.engineering.role', locale: 'ar'));
    }

    public function test_every_use_case_is_in_the_sitemap(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertStringContainsString('<loc>'.route('use-cases').'</loc>', (string) $response->getContent());

        foreach ($this->slugs() as $slug) {
            $this->assertStringContainsString(
                '<loc>'.route('use-cases.show', ['slug' => $slug]).'</loc>',
                (string) $response->getContent(),
            );
        }
    }
}
