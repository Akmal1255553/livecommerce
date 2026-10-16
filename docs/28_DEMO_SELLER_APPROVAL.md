# Temporary demo seller approval

`SELLER_AUTO_APPROVE_STORES=true` makes new seller applications active immediately.
The setting defaults to false in normal installations. Existing authentication,
seller ownership checks and suspended-store restrictions remain in place.

Enabled for the Render demo API on 2026-10-16 at the owner's request. Five pending
stores belonging to active seller accounts were activated. Suspended stores and
account roles were not changed.

To restore manual review for new applications, set this variable to false and
redeploy the API. Already approved stores remain active. This server-only change
works with the existing website and APK; users can refresh the seller dashboard.

Validation: 7 seller/dashboard/product tests passed (59 assertions), including
default pending applications, immediate demo access and suspended-store denial.
