<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class FormSubmitGuardTest extends TestCase
{
    public function test_wrap_save_blocks_reentry_and_clears_saving_after_the_request(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/composables/usePageLoad.js');

        $this->assertNotFalse($source);
        $this->assertMatchesRegularExpression('/if \(unref\(saving\)\)\s+return false/', $source);
        $this->assertStringContainsString('saving.value = true', $source);
        $this->assertStringContainsString('finally', $source);
        $this->assertStringContainsString('saving.value = false', $source);
    }

    public function test_button_keeps_its_label_and_uses_the_existing_loader(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/resources/js/components/ui/HButton.vue');

        $this->assertStringContainsString('loading: Boolean', $source);
        $this->assertStringContainsString('h-loader', $source);
        $this->assertStringContainsString(':disabled="busy"', $source);
        $this->assertStringContainsString('is-loading', $source);
        $this->assertStringContainsString('<slot />', $source);
    }
}
