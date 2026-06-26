# ChangeLog

## Unreleased

- Align Rexel price and stock API calls with the documented JWT plus `Ocp-Apim-Subscription-Key` authentication contract.
- Add OAuth2 Azure AD v1 `resource` support, keeping `scope` as a fallback.
- Send the Rexel customer word as `idCodOrigin`, keep `agenceCode` optional but validated when filled, send `orderingQty` first as a JSON number, and avoid empty optional Rexel payload fields.
- Send optional delivery location fields to the price and stock endpoint payloads when configured, matching the extracted Rexel API documentation.
- Retry Rexel endpoints once with string `orderingQty` when Rexel rejects the documented numeric form with `BW-RESTJSON-100016`.
- Reject UUID-shaped OAuth2 client identifiers in the Rexel customer number field before calling the API.
- Require a numeric Rexel customer account number and retry schema rejections with numeric root scalar fields.
- Prefer explicit product references such as `3M_85851` for Rexel `supplierCode` and `supplierComRef` before falling back to the supplier reference.
- Add schema-compatibility retries for single-object `productDetails` payloads.
- Make the RexelSync log table SQL idempotent to avoid duplicate table and duplicate index errors on module reactivation.
- Add debug logs for Rexel API endpoint, masked payload, header names, HTTP status, and masked error bodies without exposing secrets.
- Add a Rexel API client version marker to request debug logs to detect incomplete deployments.
