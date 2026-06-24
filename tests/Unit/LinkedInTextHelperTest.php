<?php

namespace Tests\Unit;

use App\Services\Social\LinkedInTextHelper;
use Tests\TestCase;

class LinkedInTextHelperTest extends TestCase
{
    /** @test */
    public function it_passes_plain_text_through_unchanged(): void
    {
        $this->assertSame('Hello LinkedIn', LinkedInTextHelper::htmlToLinkedInText('Hello LinkedIn'));
    }

    /** @test */
    public function it_returns_empty_string_for_empty_input(): void
    {
        $this->assertSame('', LinkedInTextHelper::htmlToLinkedInText(''));
    }

    /** @test */
    public function it_converts_br_tags_to_newlines(): void
    {
        $result = LinkedInTextHelper::htmlToLinkedInText("Line one<br>Line two<br/>Line three");
        $this->assertSame("Line one\nLine two\nLine three", $result);
    }

    /** @test */
    public function it_converts_div_close_tags_to_newlines(): void
    {
        $result = LinkedInTextHelper::htmlToLinkedInText("<div>First</div><div>Second</div>");
        $this->assertStringContainsString("First", $result);
        $this->assertStringContainsString("Second", $result);
        $this->assertStringNotContainsString('<div>', $result);
        $this->assertStringContainsString("\n", $result);
    }

    /** @test */
    public function it_strips_all_html_tags(): void
    {
        $html   = '<p>Hello <strong>world</strong></p>';
        $result = LinkedInTextHelper::htmlToLinkedInText($html);
        $this->assertStringNotContainsString('<', $result);
        $this->assertStringContainsString('Hello world', $result);
    }

    /** @test */
    public function it_decodes_html_entities(): void
    {
        $result = LinkedInTextHelper::htmlToLinkedInText('AT&amp;T &lt;rocks&gt; &amp; so does Q&amp;A');
        $this->assertSame('AT&T <rocks> & so does Q&A', $result);
    }

    /** @test */
    public function it_collapses_excess_blank_lines(): void
    {
        $html   = "<p>Para one</p><p></p><p></p><p>Para two</p>";
        $result = LinkedInTextHelper::htmlToLinkedInText($html);
        // Should not have more than two consecutive newlines
        $this->assertDoesNotMatch('/\n{3,}/', $result);
    }

    /** @test */
    public function it_handles_post_6_content_preserving_list_items(): void
    {
        // Simulates the real contenteditable HTML that caused Bug 3
        $html = implode('', [
            'A US court just made it official: if AI alone made it, nobody owns it.',
            '<div><br></div>',
            '<div>In March, the Supreme Court let stand a lower court ruling that said an AI-generated image has no copyright protection because there was no human creative expression in it.</div>',
            '<div><br></div>',
            '<div>What this means for creators using AI tools:</div>',
            '<div><br></div>',
            '<div>1. Pure AI output = public domain from day one</div>',
            '<div>2. Human + AI collaboration can still be copyrightable</div>',
            '<div>3. Your creative choices matter — prompts, selection, editing</div>',
            '<div>4. Document your creative process if you want IP protection</div>',
            '<div>5. The line between "tool" and "author" is now legally defined</div>',
        ]);

        $result = LinkedInTextHelper::htmlToLinkedInText($html);

        // No HTML tags survive
        $this->assertStringNotContainsString('<div>', $result);
        $this->assertStringNotContainsString('<br>', $result);

        // All 5 list items survive as distinct lines
        $this->assertStringContainsString('1. Pure AI output = public domain from day one', $result);
        $this->assertStringContainsString('2. Human + AI collaboration can still be copyrightable', $result);
        $this->assertStringContainsString('3. Your creative choices matter', $result);
        $this->assertStringContainsString('4. Document your creative process', $result);
        $this->assertStringContainsString('5. The line between "tool" and "author"', $result);

        // Opening sentence preserved
        $this->assertStringContainsString('A US court just made it official', $result);

        // No 3+ consecutive newlines
        $this->assertDoesNotMatch('/\n{3,}/', $result);
    }
}
