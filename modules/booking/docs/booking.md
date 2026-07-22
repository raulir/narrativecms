# Booking module

Service / beauty-style **treatment catalogue** (not e‑commerce SKUs).

## Lists

| Panel | Role |
|-------|------|
| `booking/treatment_category` | Groups (e.g. Hair, Nails) |
| `booking/treatment` | Bookable line: duration, price, subtreatments, special prices (Ladies/Men/Child), optional service FK |

## Relation to shop

Separate from `shop/product`. Shop is products/cart/orders; booking is appointment-style services.

Moved from legacy `stock/treatment*` for reuse in the shared CMS modules project.

## Enable

Add `booking` to site **modules** in CMS settings when a project needs this catalogue. Until then the module files sit idle.

## Migrated from

Formerly `stock/treatment` and `stock/treatment_category`. DB `panel_name` values should be `booking/…` (auto-updated on this project if any rows existed).
