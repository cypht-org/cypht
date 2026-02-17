## Feeds

This module set adds RSS/ATOM feed reading support to Cypht. It adds a new
section to the menu for configured feeds, allows them to be integrated into the
combined views, and provides add/remove feed options to the Settings->Servers
page. Like E-mail content, feeds are filtered for security removing any remote
resources.

### OPML Import

This module supports importing RSS/ATOM subscriptions from OPML files.
OPML (Outline Processor Markup Language) is a standard format used by most
RSS readers for exporting and importing feed subscriptions.

#### Supported OPML Versions
- OPML 1.0
- OPML 2.0

#### Using OPML Import
1. Go to Settings → Servers
2. Click the "Import OPML" button
3. Select your OPML file (.opml or .xml)
4. Click "Import" to add all feeds

#### Example OPML Format
```xml
<?xml version="1.0" encoding="UTF-8"?>
<opml version="1.0">
  <head>
    <title>My Feed Subscriptions</title>
  </head>
  <body>
    <outline text="Tech News" title="Tech News"
             xmlUrl="https://feeds.bbci.co.uk/news/technology/rss.xml"
             type="rss"/>
    <outline text="Blog" title="My Blog"
             xmlUrl="https://example.com/feed.xml"
             type="atom"/>
  </body>
</opml>
```

#### Import Notes
- Duplicate feeds (same URL) are automatically skipped
- Nested outline groups are flattened during import
- Maximum file size: 10MB
- Only HTTP/HTTPS URLs are accepted
