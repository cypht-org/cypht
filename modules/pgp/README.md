## PGP

This module set provides EXPERIMENTAL support for sending and decrypting
messages encrypted with PGP. This is initial PGP support, with lots of
missing features and probably bugs. It currently supports:

- add/delete public keys with an associated E-mail address
- add/delete private keys. These never leave the browser and are destroyed on logout/browser close
- sign/encrypt/both for outbound mail. This is only available for plain text outbound message types, so won't be available if using markdown or HTML
- decrypt on message read. If the message part is recognized as PGP encrypted text, it will provide controls to decrypt it. Regardless of the original content is it will be rendered as plain text
- passphrases are never stored. They must be entered anytime an action is being performed with a private key (sign, decrypt)
