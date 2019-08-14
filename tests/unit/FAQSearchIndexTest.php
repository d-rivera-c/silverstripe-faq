<?php

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
        $this->assertTrue(FAQSearchIndex::escapeQuery('How did : I get here?') === 'How did \: I get here\?');
    }

    /**
     * Test unescaping queries
     */
    public function testUnescapeQuery()
    {
        $this->assertTrue(FAQSearchIndex::unescapeQuery('How did \: I get here\?') === 'How did : I get here?');
    }
}
