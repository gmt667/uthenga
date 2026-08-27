# TIE modules and dependency rules

Logical modules are Context, Query, Availability, Budget, Recommendation, Trip Planning, Location, Routing, Journey, Conversation, Notifications, LLM, Prompts, and Validation. Their interfaces live in `php_app/includes/tie/Services.php`.

Dependency direction is one-way: API → orchestration → domain modules → provider interfaces → providers. Query does not call Trip Planning; providers do not call APIs; booking remains outside TIE. Only Context, Query, Validation, and a draft-only Trip Planning composition are executable in Phase 2.
