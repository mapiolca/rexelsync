# RexelSync for Dolibarr

RexelSync synchronizes Dolibarr supplier purchase prices and supplier stock for the Dolibarr supplier configured as `REXEL`.

## Installation

1. Copy the `rexelsync/` directory into `htdocs/custom/` of a Dolibarr instance.
2. Enable the module from Dolibarr module setup.
3. Open the module setup page and configure the Rexel API credentials, Rexel customer number, and the Dolibarr supplier.
4. Use the setup button to create or link the Dolibarr supplier named `REXEL` when needed.

## Reference Mapping

For v1, the Dolibarr supplier reference `ref_fourn` is parsed directly:

- `supplierCode`: first 3 characters of `ref_fourn`
- `supplierComRef`: remaining characters, after trimming spaces, dashes, underscores, and slashes

Example: `SCHAPCRBCV202` becomes `supplierCode = SCH` and `supplierComRef = APCRBCV202`.

## Rexel API Scope

This version targets the Rexel Discovery API endpoints:

- `POST /external/productprices/productSalePrices`
- `POST /external/stocks/positions`

The purchase price is updated from `clientNetPrice`. Supplier stock is stored in the `supplier_stock` extrafield on `product_fournisseur_price` as:

`availableBranchStock + availableCLRStock + availableServiceCenterStock`

## Dolibarr Features

- Setup page with Rexel API settings and supplier association.
- Manual sync page with one-line and all-line synchronization actions.
- Log page with price and stock evolution.
- Daily disabled cron job for automated synchronization.
- Supplier purchase price extrafield `supplier_stock`.
- Hook display on Rexel supplier proposal and supplier order lines.
