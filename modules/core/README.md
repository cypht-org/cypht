## Core

The core module set handles page layout, authentication, the main menu, and the
default settings pages. It is the only required module set to run Cypht.

Technically you can disable every other module set in Cypht and still login
(assuming you are not using IMAP authentication). Doing so gives you a
very limited main menu, the combined pages (unread, flagged, everything,
search), all of which will have no content since that is all handled by other
module sets, and the settings pages with limited options.

This set also contains many helper functions for other module sets to use that
don't really "fit" into the framework proper.
