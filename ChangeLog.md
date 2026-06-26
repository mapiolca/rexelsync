# ChangeLog

## Unreleased

- Align Rexel price and stock API calls with the documented JWT plus `Ocp-Apim-Subscription-Key` authentication contract.
- Add OAuth2 Azure AD v1 `resource` support, keeping `scope` as a fallback.
- Require and validate `agenceCode`, send `orderingQty` as a JSON string, and avoid empty optional Rexel payload fields.
- Add debug logs for Rexel API endpoint, masked payload, header names, and HTTP status without exposing secrets.
