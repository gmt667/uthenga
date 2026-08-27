# TIE errors and observability

TIE normalizes validation, authentication, authorization, feature-disabled, provider, and internal errors. APIs return a correlation `request_id`; operational logs use the same ID.

Logs deliberately accept only module, feature, status, duration, provider, and error-type metadata. Raw prompts, booking contents, contact details, session values, and coordinates must not be logged.
