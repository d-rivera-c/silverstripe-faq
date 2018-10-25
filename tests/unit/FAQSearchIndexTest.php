<?php

namespace SilverStripe\Faq\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Faq\Search\FAQSearchIndex;

/**
 * FAQSearchIndex Module Unit Tests
 */
class FAQSearchIndexTest extends SapphireTest
{
    /**
     * Test escaping queries
     */
    public function testEscapeQuery()
    {
        $this->assertSame('How did \: I get here\?', FAQSearchIndex::escapeQuery('How did : I get here?'));
    }

    /**
     * Test unescaping queries
     */
    public function testUnescapeQuery()
    {
        $this->assertSame('How did : I get here?', FAQSearchIndex::unescapeQuery('How did \: I get here\?'));
    }
}
