# ChangeLog

## Unreleased

- Align Rexel price and stock API calls with the documented JWT plus `Ocp-Apim-Subscription-Key` authentication contract.
- Add OAuth2 Azure AD v1 `resource` support, keeping `scope` as a fallback.
- Send the Rexel customer word as `idCodOrigin`, keep `agenceCode` optional but validated when filled, send `orderingQty` as a JSON string, and avoid empty optional Rexel payload fields.
- Keep optional delivery location fields out of the price endpoint payload; they are sent only to the stock endpoint when configured.
- Retry Rexel endpoints once with numeric `orderingQty` when Rexel rejects the documented string form with `BW-RESTJSON-100016`.
- Reject UUID-shaped OAuth2 client identifiers in the Rexel customer number field before calling the API.
- Require a numeric Rexel customer account number and retry schema rejections with numeric root scalar fields.
- Make the RexelSync log table SQL idempotent to avoid duplicate table and duplicate index errors on module reactivation.
- Add debug logs for Rexel API endpoint, masked payload, header names, and HTTP status without exposing secrets.
