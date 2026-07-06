<?php

/**
 * OPML Parser Tests
 *
 * Feed xmlUrl fixtures use literal public IPs because validateUrl() calls
 * feed_url_is_allowed(), which resolves hostnames via DNS and rejects URLs
 * when lookup fails (common in offline CI/sandbox). Tests never connect to
 * these addresses — they are only valid URL input for parsing/validation.
 *
 * @package tests
 */
class OpmlTest extends PHPUnit\Framework\TestCase
{
    /**
     * 93.184.216.34 — long-standing public IPv4 for example.com (IANA
     * documentation domain, RFC 5737). Stand-in for a hostname like
     * https://example.com/feed.xml without requiring DNS in tests.
     */
    private const PUBLIC_FEED_HOST_EXAMPLE = '93.184.216.34';

    /**
     * 1.1.1.1 — Cloudflare public DNS resolver; stable, routable IPv4 used
     * as a second distinct allowed host in multi-feed fixtures.
     */
    private const PUBLIC_FEED_HOST_ALT = '1.1.1.1';

    protected function setUp(): void
    {
        require_once APP_PATH.'modules/feeds/hm-feed.php';
        require_once APP_PATH.'modules/feeds/hm-opml.php';
    }

    /**
     * Test parsing valid OPML content
     */
    public function testParseValidOpml()
    {
        $example = self::PUBLIC_FEED_HOST_EXAMPLE;
        $alt = self::PUBLIC_FEED_HOST_ALT;
        $opml = '<?xml version="1.0" encoding="UTF-8"?>
<opml version="1.0">
    <head>
        <title>Test Feeds</title>
    </head>
    <body>
        <outline text="Example Feed" xmlUrl="https://'.$example.'/feed.xml" type="rss"/>
        <outline text="Another Feed" title="Another Feed" xmlUrl="https://'.$alt.'/feed.xml" type="atom"/>
    </body>
</opml>';
        
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
        $example = self::PUBLIC_FEED_HOST_EXAMPLE;
        $alt = self::PUBLIC_FEED_HOST_ALT;
        $opml = '<?xml version="1.0" encoding="UTF-8"?>
<opml version="1.0">
    <head><title>Nested Feeds</title></head>
    <body>
        <outline text="Tech" title="Tech">
            <outline text="TechCrunch" xmlUrl="https://'.$example.'/feed1.xml" type="rss"/>
            <outline text="Verge" xmlUrl="https://'.$example.'/feed2.xml" type="rss"/>
        </outline>
        <outline text="News" title="News">
            <outline text="BBC" xmlUrl="https://'.$alt.'/feed.xml" type="rss"/>
        </outline>
    </body>
</opml>';
        
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
        $example = self::PUBLIC_FEED_HOST_EXAMPLE;
        $opml = '<?xml version="1.0" encoding="UTF-8"?>
<opml version="1.0">
    <head><title>Test</title></head>
    <body>
        <outline text="Valid Feed" xmlUrl="https://'.$example.'/feed.xml"/>
        <outline text="Invalid Entry" title="No URL"/>
    </body>
</opml>';
        
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
        $parser = new Hm_Opml_Parser();
        $example = self::PUBLIC_FEED_HOST_EXAMPLE;
        
        $this->assertTrue($parser->validateUrl('https://'.$example.'/feed.xml'));
        $this->assertTrue($parser->validateUrl('http://'.$example.'/feed.xml'));
        
        // Invalid URLs
        $this->assertFalse($parser->validateUrl('ftp://example.com/feed.xml'));
        $this->assertFalse($parser->validateUrl('not-a-url'));
        $this->assertFalse($parser->validateUrl(''));
        $this->assertFalse($parser->validateUrl('http://127.0.0.1/feed.xml'));
        $this->assertFalse($parser->validateUrl('http://169.254.169.254/latest/meta-data/'));
    }
    
    /**
     * Test feed data structure
     */
    public function testFeedDataStructure()
    {
        $example = self::PUBLIC_FEED_HOST_EXAMPLE;
        $alt = self::PUBLIC_FEED_HOST_ALT;
        $opml = '<?xml version="1.0" encoding="UTF-8"?>
<opml version="1.0">
    <head><title>Test</title></head>
    <body>
        <outline text="HTTPS Feed" xmlUrl="https://'.$example.'/secure-feed.xml"/>
        <outline text="HTTP Feed" xmlUrl="http://'.$alt.'/feed.xml"/>
    </body>
</opml>';
        
        $parser = new Hm_Opml_Parser();
        $parser->parse($opml);
        $feeds = $parser->getFeeds();
        
        // Check HTTPS feed
        $this->assertEquals('HTTPS Feed', $feeds[0]['name']);
        $this->assertEquals('https://'.$example.'/secure-feed.xml', $feeds[0]['server']);
        $this->assertTrue($feeds[0]['tls']);
        $this->assertEquals(443, $feeds[0]['port']);
        
        // Check HTTP feed
        $this->assertEquals('HTTP Feed', $feeds[1]['name']);
        $this->assertEquals('http://'.$alt.'/feed.xml', $feeds[1]['server']);
        $this->assertFalse($feeds[1]['tls']);
        $this->assertEquals(80, $feeds[1]['port']);
    }
    
    /**
     * Test fallback to title when text is empty
     */
    public function testTitleFallback()
    {
        $example = self::PUBLIC_FEED_HOST_EXAMPLE;
        $opml = '<?xml version="1.0" encoding="UTF-8"?>
<opml version="1.0">
    <head><title>Test</title></head>
    <body>
        <outline title="Feed Title Only" xmlUrl="https://'.$example.'/feed.xml"/>
    </body>
</opml>';
        
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
        $example = self::PUBLIC_FEED_HOST_EXAMPLE;
        $feed_url = 'https://'.$example.'/feed.xml';
        $opml = '<?xml version="1.0" encoding="UTF-8"?>
<opml version="1.0">
    <head><title>Test</title></head>
    <body>
        <outline xmlUrl="'.$feed_url.'"/>
    </body>
</opml>';
        
        $parser = new Hm_Opml_Parser();
        $parser->parse($opml);
        $feeds = $parser->getFeeds();
        
        $this->assertEquals($feed_url, $feeds[0]['name']);
    }
}
