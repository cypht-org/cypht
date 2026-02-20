<?php

/**
 * OPML Parser Tests
 * @package tests
 */

class OpmlTest extends PHPUnit\Framework\TestCase
{
    /**
     * Test parsing valid OPML content
     */
    public function testParseValidOpml()
    {
        $opml = '<?xml version="1.0" encoding="UTF-8"?>
<opml version="1.0">
    <head>
        <title>Test Feeds</title>
    </head>
    <body>
        <outline text="Example Feed" xmlUrl="https://example.com/feed.xml" type="rss"/>
        <outline text="Another Feed" title="Another Feed" xmlUrl="https://example.org/feed.xml" type="atom"/>
    </body>
</opml>';
        
        require_once APP_PATH.'modules/feeds/hm-opml.php';
        $parser = new Hm_Opml_Parser();
        $result = $parser->parse($opml);
        
        $this->assertTrue($result, 'Parsing valid OPML should succeed');
        $feeds = $parser->getFeeds();
        $this->assertCount(2, $feeds, 'Should parse 2 feeds');
    }
    
    /**
     * Test parsing OPML with nested outlines
     */
    public function testParseNestedOpml()
    {
        $opml = '<?xml version="1.0" encoding="UTF-8"?>
<opml version="1.0">
    <head><title>Nested Feeds</title></head>
    <body>
        <outline text="Tech" title="Tech">
            <outline text="TechCrunch" xmlUrl="https://techcrunch.com/feed/" type="rss"/>
            <outline text="Verge" xmlUrl="https://theverge.com/rss/index.xml" type="rss"/>
        </outline>
        <outline text="News" title="News">
            <outline text="BBC" xmlUrl="https://feeds.bbci.co.uk/news/rss.xml" type="rss"/>
        </outline>
    </body>
</opml>';
        
        require_once APP_PATH.'modules/feeds/hm-opml.php';
        $parser = new Hm_Opml_Parser();
        $result = $parser->parse($opml);
        
        $this->assertTrue($result);
        $feeds = $parser->getFeeds();
        $this->assertCount(3, $feeds, 'Should parse 3 feeds from nested structure');
    }
    
    /**
     * Test skipping outline without xmlUrl
     */
    public function testSkipOutlineWithoutXmlUrl()
    {
        $opml = '<?xml version="1.0" encoding="UTF-8"?>
<opml version="1.0">
    <head><title>Test</title></head>
    <body>
        <outline text="Valid Feed" xmlUrl="https://example.com/feed.xml"/>
        <outline text="Invalid Entry" title="No URL"/>
    </body>
</opml>';
        
        require_once APP_PATH.'modules/feeds/hm-opml.php';
        $parser = new Hm_Opml_Parser();
        $result = $parser->parse($opml);
        
        $this->assertTrue($result);
        $feeds = $parser->getFeeds();
        $this->assertCount(1, $feeds, 'Should only parse 1 feed (skip the one without xmlUrl)');
    }
    
    /**
     * Test invalid XML handling
     */
    public function testInvalidXml()
    {
        $opml = '<?xml version="1.0"?>
<not-opml>
    <content>Invalid document</content>
</not-opml>';
        
        require_once APP_PATH.'modules/feeds/hm-opml.php';
        $parser = new Hm_Opml_Parser();
        $result = $parser->parse($opml);
        
        $this->assertFalse($result, 'Parsing invalid OPML should fail');
        $this->assertNotEmpty($parser->error, 'Should have error message');
    }
    
    /**
     * Test empty content handling
     */
    public function testEmptyContent()
    {
        require_once APP_PATH.'modules/feeds/hm-opml.php';
        $parser = new Hm_Opml_Parser();
        $result = $parser->parse('');
        
        $this->assertFalse($result);
        $this->assertNotEmpty($parser->error);
    }
    
    /**
     * Test URL validation
     */
    public function testUrlValidation()
    {
        require_once APP_PATH.'modules/feeds/hm-opml.php';
        $parser = new Hm_Opml_Parser();
        
        // Valid URLs
        $this->assertTrue($parser->validateUrl('https://example.com/feed.xml'));
        $this->assertTrue($parser->validateUrl('http://example.com/feed.xml'));
        
        // Invalid URLs
        $this->assertFalse($parser->validateUrl('ftp://example.com/feed.xml'));
        $this->assertFalse($parser->validateUrl('not-a-url'));
        $this->assertFalse($parser->validateUrl(''));
    }
    
    /**
     * Test feed data structure
     */
    public function testFeedDataStructure()
    {
        $opml = '<?xml version="1.0" encoding="UTF-8"?>
<opml version="1.0">
    <head><title>Test</title></head>
    <body>
        <outline text="HTTPS Feed" xmlUrl="https://secure.example.com/feed.xml"/>
        <outline text="HTTP Feed" xmlUrl="http://example.com/feed.xml"/>
    </body>
</opml>';
        
        require_once APP_PATH.'modules/feeds/hm-opml.php';
        $parser = new Hm_Opml_Parser();
        $parser->parse($opml);
        $feeds = $parser->getFeeds();
        
        // Check HTTPS feed
        $this->assertEquals('HTTPS Feed', $feeds[0]['name']);
        $this->assertEquals('https://secure.example.com/feed.xml', $feeds[0]['server']);
        $this->assertTrue($feeds[0]['tls']);
        $this->assertEquals(443, $feeds[0]['port']);
        
        // Check HTTP feed
        $this->assertEquals('HTTP Feed', $feeds[1]['name']);
        $this->assertEquals('http://example.com/feed.xml', $feeds[1]['server']);
        $this->assertFalse($feeds[1]['tls']);
        $this->assertEquals(80, $feeds[1]['port']);
    }
    
    /**
     * Test fallback to title when text is empty
     */
    public function testTitleFallback()
    {
        $opml = '<?xml version="1.0" encoding="UTF-8"?>
<opml version="1.0">
    <head><title>Test</title></head>
    <body>
        <outline title="Feed Title Only" xmlUrl="https://example.com/feed.xml"/>
    </body>
</opml>';
        
        require_once APP_PATH.'modules/feeds/hm-opml.php';
        $parser = new Hm_Opml_Parser();
        $parser->parse($opml);
        $feeds = $parser->getFeeds();
        
        $this->assertEquals('Feed Title Only', $feeds[0]['name']);
    }
    
    /**
     * Test fallback to URL when name is empty
     */
    public function testUrlFallback()
    {
        $opml = '<?xml version="1.0" encoding="UTF-8"?>
<opml version="1.0">
    <head><title>Test</title></head>
    <body>
        <outline xmlUrl="https://example.com/feed.xml"/>
    </body>
</opml>';
        
        require_once APP_PATH.'modules/feeds/hm-opml.php';
        $parser = new Hm_Opml_Parser();
        $parser->parse($opml);
        $feeds = $parser->getFeeds();
        
        $this->assertEquals('https://example.com/feed.xml', $feeds[0]['name']);
    }
}
