## Bounces

Remembers the addresses your messages bounced from and warns you before you
write to them again. Enable it by adding `bounces` to `CYPHT_MODULES`.

### In Cypht

- Opening a delivery failure report shows a banner above the message
  ("Delivery failed permanently to bob@example.com (5.1.1)") and records the
  addresses it reports. Opening the same report again does not count twice.
- On the compose page, recipients with a permanent failure, or whose
  bounces reached a pause or stop step, get a warning below the form while
  you type.
- Settings > Bounced addresses lists the recorded addresses with their
  counters, and lets you reset or delete them.
- Settings > Site > General > "Track bounced addresses" stops the recording,
  the banner and the compose warning.

The check runs when Cypht retrieves a message's content, when you open it or
when it is pre-fetched, and only for messages that look like bounces
(delivery-status reports, X-Failed-Recipients, mailer-daemon or postmaster
senders). The result is cached per message, so the banner does not parse it
again. The list is kept in your user settings, so it is saved with them.

### Pieces for applications embedding Cypht

The same classes let a host application record the bounces its own filter
runner finds and stop emailing addresses that keep bouncing.

- `Hm_Bounce_Parser`: turns a raw message into bounce events, one per bounced
  recipient, with the bounce type (`hard` for a permanent failure, `soft` for
  a transient one) and the Message-ID of the bounce. Out-of-office replies
  and other auto-responses are not bounces. Uses `rambomst/php-bounce-handler`.
- `Hm_Bounce_Policy`: pure escalation rules. `"10:1d, 5:7d, 5:14d, 5:remove"`
  (the default) pauses sending one day after 10 bounces, a week after 5 more,
  two weeks after 5 more, then stops sending for good after 5 more. Durations
  use `h`, `d` or `w`; the last step repeats while the address keeps bouncing.
- `Hm_Bounce_Store`: where the state of the addresses and the bounces already
  seen are kept. `Hm_Bounce_Store_User_Config` keeps them in the user config.
  A host can plug its own store (for example on its own database tables) by
  setting the `bounce_store_class` site config value, the same way
  `sieve_client_factory` replaces the Sieve client; the class is constructed
  with the user config. The store is used everywhere, including the banner
  and the Bounced addresses page: with a store shared by all users, the
  bounces a user opens in a personal mailbox end up in the shared list.
- `Hm_Bounce_Service`: records a bounce once per bounce message, applies the
  policy, and answers `isSuppressed($email)` before a message is sent. Also
  `reset`, `pause`, `remove` and `delete` for an admin interface.

`get_bounce_service()` reads `bounce_rules`, `bounce_hard_weight` (how much a
hard bounce counts for, default 1) and `bounce_reset_after_days` (days
without a counted bounce after which escalation starts again from the first
step, default 0: never) from the site config. They are not in `.env`: a host
sets them on its site config object.

### Filter condition and action

With the `sievefilters` module set, the filter editor gets an "Is bounce"
condition (Soft / Hard) and an "Add to bounce list" action. A Sieve server
cannot evaluate them: they are left out of the generated script. The action
requires a `bounce` extension, so it is only offered when the account's Sieve
client lists `bounce` in `getExtensions()`, which a real server never does.
The condition stays visible: on an account with a real Sieve server a filter
using it runs without it, or is invalid when "Is bounce" is its only
condition. They are meant for host-side filter runners, which read the filter
definition from the script header and process messages themselves, for
example for mailboxes without a Sieve server. Such a host provides its own
`sieve_client_factory` whose client adds `bounce` to its extensions:

```php
require_once APP_PATH.'modules/bounces/functions.php';

$service = get_bounce_service($site_config, $user_config);
$parser = new Hm_Bounce_Parser();

// for each message the runner reads
$events = $parser->parse($raw_message, $headers);
if (bounce_condition_matches($events, $condition['type'])) {
    // "Add to bounce list" action
    foreach ($service->recordEvents($events) as $record) {
        if ($record['action'] === Hm_Bounce_Policy::ACTION_REMOVE) {
            // the address kept bouncing: unsubscribe it, tell the user...
        }
    }
}
```

Before sending:

```php
if ($service->isSuppressed($recipient)) {
    // skip, sending to this address is paused or stopped
}
```
