## Brute Force Protection

This module implements login protection against brute force attacks by tracking failed authentication attempts and enforcing temporary account lockouts. It stores attempt counts keyed by IP address and username to detect and block automated credential guessing attacks.

The module uses a database-backed tracker to monitor login failures, storing attempt counts, lockout status, and timing information for each IP/username combination. When the threshold of failed attempts is exceeded, the account becomes temporarily locked, effectively slowing down automated password attacks while remaining transparent to legitimate users.
