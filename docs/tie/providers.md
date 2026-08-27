# TIE provider boundary

`Providers.php` defines provider-neutral Routing, Weather, and LLM interfaces. The Phase 2 kernel wires explicit unavailable providers, so no direct Gemini, Google Maps, weather, or routing call can be introduced by accident.

Phase 3+ must select providers after privacy, cost, timeout, quota, and fallback policies are approved. Provider responses must be normalized before domain modules receive them.
