# ChangeLog

## Unreleased

- Align Rexel price and stock API calls with the documented JWT plus `Ocp-Apim-Subscription-Key` authentication contract.
- Add OAuth2 Azure AD v1 `resource` support, keeping `scope` as a fallback.
- Send the Rexel customer word as `idCodOrigin`, keep `agenceCode` optional but validated when filled, send `orderingQty` first as a JSON number, and avoid empty optional Rexel payload fields.
- Send optional delivery location fields to the price and stock endpoint payloads when configured, matching the extracted Rexel API documentation.
- Retry Rexel endpoints once with string `orderingQty` when Rexel rejects the documented numeric form with `BW-RESTJSON-100016`.
- Reject UUID-shaped OAuth2 client identifiers in the Rexel customer number field before calling the API.
- Require a numeric Rexel customer account number and retry schema rejections with numeric root scalar fields.
- Use only the Dolibarr supplier reference `ref_fourn` to build Rexel `supplierCode` and `supplierComRef`; the product reference is ignored for Rexel API calls.
- Add schema-compatibility retries for single-object `productDetails` payloads.
- Make the RexelSync log table SQL idempotent to avoid duplicate table and duplicate index errors on module reactivation.
- Add debug logs for Rexel API endpoint, masked payload, header names, HTTP status, and masked error bodies without exposing secrets.
- Add a Rexel API client version marker to request debug logs to detect incomplete deployments.
- Keep Rexel price and stock payload fields in the documented order required by TIBCO JSON-to-XML validation.
- Keep alternate Rexel reference candidates parsed only from `ref_fourn`, from explicit separator format to the three-character supplier-code fallback.
- Align the module descriptor compatibility floor with Dolibarr v20+ and PHP 8.0+.
- Replace full-page all-product synchronization with capped AJAX batches and a progress dialog to avoid long PHP requests.
- Preserve sync list sorting, pagination, limit, and filters after one-line synchronization.
- Set the default interactive synchronization batch size to 50 rows while keeping the server maximum at 250.
- Add sortable and filterable synchronization-list columns, including a multiselect status filter and latest-log based state/date sorting.
- Move the synchronization row count into the Synchronization tab badge and remove the old supplier-count summary line.
- Display known Rexel API and RexelSync error messages translated in the synchronization and log lists.
- Replace the module pictogram with a local stylized RexelSync SVG icon.
