# TIE configuration

TIE uses the existing `.env`/`config.local.php` loader and is disabled by default. Feature flags are `TIE_ENABLED`, `TIE_TRIP_PLANNER_ENABLED`, `TIE_RECOMMENDATIONS_ENABLED`, `TIE_LOCATION_ENABLED`, `TIE_ROUTING_ENABLED`, `TIE_LLM_ENABLED`, and `TIE_JOURNEY_ENABLED`.

Provider settings are reserved as environment variables only. Never commit API keys, provider secrets, or precise location data. Enabling `TIE_ENABLED` alone does not enable any advanced feature.
