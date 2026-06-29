# RexelSync for Dolibarr

RexelSync synchronizes Dolibarr supplier purchase prices, supplier stock, and the Rexel customer profile cache for the Dolibarr supplier configured as `REXEL`.

## Installation

1. Copy the `rexelsync/` directory into `htdocs/custom/` of a Dolibarr instance.
2. Enable the module from Dolibarr module setup.
3. Open the module setup page and configure the Rexel API credentials, Rexel customer number, and the Dolibarr supplier.
4. Use the setup button to create or link the Dolibarr supplier named `REXEL` when needed.

## Reference Mapping

For v1, Rexel references are parsed only from the Dolibarr supplier reference `ref_fourn`, with this priority:

- `ref_fourn` is parsed with an explicit separator format when possible, for example `CODE_REFERENCE`.
- As a fallback, `supplierCode` is the first 3 characters of `ref_fourn` and `supplierComRef` is the remaining value after trimming spaces, dashes, underscores, and slashes.

Examples: supplier reference `TRM85851` becomes `supplierCode = TRM` and `supplierComRef = 85851`; `SCHAPCRBCV202` becomes `supplierCode = SCH` and `supplierComRef = APCRBCV202`.

The Dolibarr product reference is not used to build Rexel `supplierCode` or `supplierComRef`, even when it looks like `CODE_REFERENCE`.

## Rexel API Scope

This version covers the Rexel Discovery API endpoints:

- `POST /external/productprices/productSalePrices`
- `POST /external/stocks/positions`
- `GET /external/customers/{idCustomer}`

The Discovery `units`, quotes and orders endpoints are identified in the Rexel offer but are not integrated yet. Premium-only product media, CEE, environmental, replacement and sustainable endpoints are listed as unavailable in the Compatibility tab and are not reimplemented locally.

Rexel API calls use OAuth2 client credentials only. The module fetches an access token from the configured token URL with `client_id`, `client_secret` and `scope`, then sends Rexel requests with `Authorization: Bearer ...` and the fixed API Management header `Ocp-Apim-Subscription-Key`. A legacy `REXELSYNC_TOKEN_RESOURCE` constant is still accepted silently for existing Azure AD v1 configurations, but it is no longer exposed in setup. The Rexel `idCustomer` must be the numeric Rexel customer account number, not the OAuth2 `client_id` and not the customer word sent in `idCodOrigin`.

The Rexel customer word is sent as `idCodOrigin` when configured. Rexel payload fields are emitted in the documented order because the TIBCO gateway validates the JSON as an XML sequence. The Rexel branch code is optional; when it is configured, it is sent as `agenceCode`, otherwise it is omitted from the payload. Product request quantities are sent first as JSON numbers in `orderingQty`, matching the extracted Rexel API documentation, with a string fallback for compatibility. If Rexel returns `BW-RESTJSON-100016`, the API call retries schema-compatible variants: string `orderingQty`, numeric root scalar fields when the customer account number is numeric, and a single-object `productDetails` form. Optional delivery location fields are sent to the price and stock endpoints when configured.

The purchase price is updated from `clientNetPrice`. Supplier stock is stored in the `supplier_stock` extrafield on `product_fournisseur_price` as:

`availableBranchStock + availableCLRStock + availableServiceCenterStock`

## Customer Synchronization

The Customers setup tab calls `GET /external/customers/{idCustomer}` with the same OAuth2 and subscription-key authentication as the product endpoints. It stores a per-entity cache linked to the configured Rexel supplier thirdparty through `fk_soc`:

- customer profile and billing address in `rexelsync_customer_profile`;
- delivery addresses in `rexelsync_customer_address`;
- customer agreements and derogations in `rexelsync_customer_agreement`.

The cache is refreshed transactionally for the active entity. It does not create a parallel Dolibarr thirdparty or address book; Dolibarr native thirdparty data remains the source of truth for ERP business records.

## Dolibarr Features

- Setup page with Rexel API settings and supplier association.
- Manual sync page with one-line synchronization and all-line AJAX batch synchronization with progress.
- Log page with price and stock evolution.
- Internal Customers setup tab to synchronize and display Rexel customer profile, delivery addresses and agreements.
- Daily disabled cron job for automated synchronization.
- Supplier purchase price extrafield `supplier_stock`.
- Hook display on Rexel supplier proposal and supplier order lines.

## Batch Synchronization

The all-line synchronization button runs the Rexel supplier rows in AJAX batches to avoid long PHP requests. It always targets all Rexel supplier price rows, regardless of the filters currently visible in the list. The `REXELSYNC_BATCH_SIZE` setting controls the batch size; `0` uses the default of 50 rows and the server enforces a maximum of 250.
