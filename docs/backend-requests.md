# Backend contract requests

- Add `docs/openapi.yaml` covering `/api/v1` endpoints.
- Standardize authentication and validation errors as `{code, message, details}`. Current login failure returns only `{message}`.
- Document HTTP status codes and structured rejection reasons used by check-in and verification.
