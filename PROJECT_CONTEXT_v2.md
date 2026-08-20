# Tripromio — Project Context v2

> This document is the single source of truth for the Tripromio product, its business rules, current architecture, MVP scope, development phases, and AI coding-agent constraints.
>
> Every AI coding agent working on this repository must read this file completely before making implementation decisions or modifying code.

---

# 1. Product Identity

## Product Name

**Tripromio**

## Product Category

Travel Companion Discovery + Trip Planning Platform.

## Core Idea

Tripromio helps travellers find compatible travel companions for specific trips and destinations, communicate with them for travel-related planning, plan the trip together, travel together, and leave reviews afterward.

## Core Value Proposition

> **Travel Together. Explore Better.**

Tripromio is for people who want to travel but do not want to travel alone.

The platform should help a user:

1. Create a trip.
2. Make the trip discoverable.
3. Discover suitable travellers.
4. Evaluate compatibility with the specific trip.
5. Send a travel connection request.
6. Connect after mutual acceptance.
7. Chat only for travel-related purposes.
8. Plan the trip together.
9. Travel together.
10. Review each other after the trip.

---

# 2. Product Positioning

Tripromio is a **travel platform**, not a dating or generic social platform.

## Tripromio IS

- Travel companion discovery.
- Trip-specific traveller matching.
- Trip planning.
- Travel-related communication.
- Traveller reputation through ratings/reviews.
- Safety and trust features.

## Tripromio IS NOT

- Dating app.
- Friendship/dating matching app.
- Generic social media app.
- Random messaging application.
- Anonymous chat platform.
- General-purpose community chat.

## Critical Product Rule

A user's ability to communicate with another user must be connected to an actual travel context.

Random global messaging must not be introduced.

> **Tripromio is about people finding the right companion for the right trip — not about people randomly finding people.**

---

# 3. Target Users

Primary users:

- Solo travellers.
- Adventure lovers.
- Budget travellers.
- Backpackers.
- Weekend explorers.
- People who want travel companions.
- People who have a destination/date/budget but do not have a suitable companion.

Typical problem:

> "I want to visit a destination, but I don't have someone to travel with."

Tripromio's solution:

> "Find travellers with compatible destinations, dates, budgets, interests, and travel styles."

---

# 4. Core Product Loop

The main product loop is:

```text
User
 ↓
Create Trip
 ↓
Publish Trip
 ↓
Trip becomes discoverable
 ↓
Eligible travellers discover the Trip
 ↓
Trip-specific compatibility / matching
 ↓
Interested traveller sends connection request
 ↓
Trip owner accepts/rejects
 ↓
Accepted connection
 ↓
Travel-related chat
 ↓
Trip planning
 ↓
Travel together
 ↓
Trip completed
 ↓
Reviews / Ratings
 ↓
Traveller reputation
```

This loop is the highest-priority product workflow.

---

# 5. Current Technology Stack

## Backend

Laravel 13.x

## Language

PHP 8.3.x

## Database

MySQL 8.4.x

## API

REST API

## Authentication

Laravel Sanctum

## Frontend

Flutter for Android/iOS

## Local Development

Laragon

## Admin

Laravel + Filament is the planned direction.

## Push Notifications

Firebase Cloud Messaging (future phase)

## Maps

Google Maps / Places APIs (future phase)

## Realtime

Laravel Reverb/WebSockets + Redis are potential future infrastructure. Do not introduce them before the relevant phase.

## Storage

S3-compatible object storage is a future production direction. Local filesystem is sufficient for development.

---

# 6. Architecture Principles

Preferred request flow:

```text
Route
 ↓
Controller
 ↓
Form Request / Validation
 ↓
Service / Domain Logic
 ↓
Model / Query
 ↓
Database
```

Controllers should remain thin.

Use:

- Form Requests.
- API Resources.
- Policies.
- Services where business logic is non-trivial.
- Enums where domain values are controlled.
- Jobs where background processing is actually required.
- Events/listeners where justified.
- Notifications where justified.
- Database transactions for atomic multi-write operations.
- Proper pagination.
- Tests for important business logic.

Avoid unnecessary complexity.

Do NOT introduce repositories, microservices, CQRS, event sourcing, or similar abstractions unless there is a concrete requirement.

Prefer Laravel native features.

---

# 7. Current Implemented State

The following phases are implemented and committed:

```text
Phase 0   — Backend Foundation                    ✅
Phase 1A  — Authentication                        ✅
Phase 1B  — User Profile & Interests              ✅
Phase 2A  — Trip Domain & Database Foundation     ✅
Phase 2B  — Trip CRUD & Lifecycle                 ✅
Phase 2C  — Trip Discovery & Filtering            ✅
Phase 3A  — Matching Inputs Foundation            ✅
```

Current matching-engine work:

```text
Phase 3B — Companion Matching Engine
NOT IMPLEMENTED YET
```

Current matching API:

```text
Phase 3C — Companion Matching API
NOT IMPLEMENTED YET
```

Current connection workflow:

```text
Phase 4 — Connections
NOT IMPLEMENTED YET
```

---

# 8. Implemented Authentication

Implemented:

- Registration.
- Login.
- Logout.
- Authenticated user endpoint.
- Forgot password.
- Password reset.
- Email verification.
- Sanctum token authentication.
- Rate limiting for sensitive endpoints.
- API validation.
- Consistent JSON responses.

Phone OTP is planned but is NOT fully implemented yet.

Do not assume phone verification already exists just because it is in the product roadmap.

---

# 9. Implemented User Profile

The current profile foundation includes:

- Profile photo.
- Bio.
- City.
- Country.
- Languages.
- Travel style.
- Interests.
- Profile completion.
- Optional budget preference inputs.

Interests use normalized relational storage:

```text
users
  ↕
user_interests
  ↕
interests
```

Do NOT store interests as uncontrolled comma-separated text.

---

# 10. Implemented Matching Input Foundation

The current matching-input foundation contains:

## 10.1 Trip Interests

```text
trips
  ↕
trip_interests
  ↕
interests
```

Trip interests are managed through the Trip workflow.

The same master `interests` table is shared by user interests and trip interests.

Trip interests are limited to a reasonable MVP maximum (currently implemented as max 10).

## 10.2 User Budget Preferences

Stored on `user_profiles`:

```text
preferred_budget_min
preferred_budget_max
```

These are optional.

They represent the budget range a user is generally comfortable with.

Missing budget preference does NOT mean the user can afford everything.

## 10.3 Travel Availability

Users may have multiple availability windows:

```text
travel_availabilities
---------------------
id
user_id
start_date
end_date
created_at
updated_at
```

A user can have more than one availability range.

Overlapping ranges are currently allowed and can be resolved dynamically by the matching engine.

Travel availability is optional and is not part of profile-completion percentage.

---

# 11. REQUIRED NEXT MATCHING INPUT — PREFERRED DESTINATIONS

The matching system must not use a user's current city as a substitute for their desired destination.

Before the final matching engine is implemented, Tripromio must support explicit user destination preferences.

Planned structure:

```text
preferred_destinations
----------------------
id
user_id
destination
place_id
latitude
longitude
created_at
updated_at
```

A user may have multiple preferred destinations.

Destination matching priority:

1. Exact `place_id` match when both sides have a comparable place ID.
2. Normalized exact destination-string match as fallback.

Current city/country must NOT be treated as an automatic destination preference.

Duplicate preferred destinations for the same user should be prevented at the application level.

This input is a prerequisite for the final 40% destination matching factor.

---

# 12. Trip Domain

A Trip is a core business entity.

Current trip fields include:

```text
id
user_id
title
destination
place_id
latitude
longitude
start_date
end_date
budget_min
budget_max
trip_type
description
max_members
status
created_at
updated_at
```

## Trip Types

Current controlled values:

```text
weekend
adventure
backpacking
road_trip
nature
photography
cultural
beach
mountains
other
```

## Trip Status

Current controlled lifecycle:

```text
draft
published
ongoing
completed
cancelled
```

The lifecycle must remain controlled and invalid transitions must not be silently accepted.

## Max Members Semantics

`max_members` means the **total number of travellers in the trip, including the owner**.

Example:

```text
max_members = 4
```

means:

```text
owner + 3 additional members
```

---

# 13. Trip Membership

Current membership foundation:

```text
trip_members
```

Roles:

```text
owner
member
```

Statuses:

```text
active
left
removed
```

Trip request states must NOT be stored in `trip_members`.

Connection-request workflow will have a dedicated `trip_requests` model/table in Phase 4.

When a Trip is created, its owner is automatically added as an active owner member.

---

# 14. Implemented Trip APIs

Current Trip management APIs include:

```text
POST   /api/trips
GET    /api/trips/{trip}
PUT    /api/trips/{trip}
GET    /api/my/trips
POST   /api/trips/{trip}/publish
POST   /api/trips/{trip}/cancel
```

These are authenticated.

Owner authorization is enforced server-side.

Trip creation and owner-membership creation are performed atomically.

Trip deletion is not the standard way to cancel a trip; cancellation is represented through the trip lifecycle.

---

# 15. Implemented Trip Discovery

Current discovery endpoint:

```text
GET /api/trips
```

Authenticated users only.

Supported discovery filters currently include:

```text
destination
start_date
end_date
budget_min
budget_max
trip_type
sort
page
per_page
```

Discovery behavior:

- Published trips are discoverable.
- Draft trips are excluded.
- Cancelled trips are excluded.
- Completed trips are excluded.
- Past trips are excluded according to the current upcoming-trip rule.
- The authenticated user's own trips are excluded from the default discovery feed.
- Discovery is SQL/database driven.
- Results are paginated.
- N+1 queries must be avoided.

Trip discovery and companion matching are separate concerns.

---

# 16. Companion Matching — Product Rules

The matching system is **Trip-specific**.

It is NOT generic user-to-user social matching.

The conceptual API is:

```text
Trip
 ↓
Eligible Candidate Traveller
 ↓
Compatibility Calculation
 ↓
Explainable Score
```

The initial matching weights are:

```text
Destination       40%
Dates              25%
Budget             15%
Interests          10%
Travel Style       10%
--------------------------------
Total             100%
```

These weights are centralized product rules and must not be duplicated throughout the code.

---

# 17. Matching Data Sources

## Trip-side

```text
destination
place_id
start_date
end_date
budget_min
budget_max
trip_type
trip_interests
```

## Candidate-side

```text
preferred_destinations
travel_availabilities
preferred_budget_min
preferred_budget_max
interests
travel_style
```

The matching engine should not invent data that is not present.

---

# 18. Matching Missing-Data Policy

Critical rule:

> **Unknown data is not a perfect match.**

Use the following policy:

## Destination

Destination is a core 40% signal.

If the candidate has no preferred destinations:

```text
destination score = 0 / 40
```

Do NOT simply remove the 40% destination factor from normalization and allow a candidate with no destination preference to receive an artificial 100%.

## Dates

If the candidate has no availability records:

```text
dates factor = unavailable
```

Do not fabricate availability.

## Budget

If the candidate has no budget preference:

```text
budget factor = unavailable
```

Do NOT treat a missing budget as unlimited budget or a perfect 15/15 match.

## Interests

If the Trip has no interests:

```text
interests factor = unavailable
```

If the Trip has interests but the candidate has no overlapping interests:

```text
interests score = 0 / 10
```

This is an explicit mismatch, not an unavailable factor.

## Travel Style

If the candidate has no travel style:

```text
travel style factor = unavailable
```

---

# 19. Matching Normalization

For optional unavailable factors:

```text
normalized_score =
(raw_score / available_weight) * 100
```

Only genuinely unavailable optional factors can be removed from the available weight.

Destination is a special mandatory business signal: no preferred destination means 0/40, not removal of the entire 40-point factor.

Final score must always be bounded:

```text
0..100
```

The score must be deterministic and explainable.

---

# 20. Destination Matching Rules

Destination is the strongest matching signal.

When preferred destinations exist:

1. Compare `place_id` where available.
2. Otherwise compare normalized destination strings.
3. Do not use candidate current city as an automatic desired-destination match.

A successful destination preference match earns:

```text
40 / 40
```

No preferred destination match earns:

```text
0 / 40
```

---

# 21. Date Matching Rules

A Trip matches a candidate if at least one candidate availability window overlaps the Trip dates.

Overlap:

```text
availability_start <= trip_end
AND
availability_end >= trip_start
```

At the current MVP level:

```text
any valid overlap → 25 / 25
no overlap → 0 / 25
```

If there are no candidate availability windows, the date factor is unavailable.

---

# 22. Budget Matching Rules

The intent is to measure how much of the Trip's budget range is covered by the candidate's preferred budget range.

For a Trip where both budget bounds exist:

```text
overlap_width / trip_budget_width
```

then:

```text
budget_score = overlap_ratio * 15
```

If candidate budget preference is missing:

```text
budget factor = unavailable
```

Do NOT infer infinite budget.

If Trip budget information is missing:

```text
budget factor = unavailable
```

Open-ended ranges must be handled explicitly and safely; do not use mathematically invalid infinite-width calculations.

The detailed implementation must preserve the business meaning rather than inventing a complex economic model.

---

# 23. Interest Matching Rules

Trip interests come from:

```text
trip_interests
```

Candidate interests come from:

```text
user_interests
```

For a Trip with interests:

```text
interest_score =
matched_trip_interests / total_trip_interests * 10
```

Examples:

```text
3 matching out of 3 → 10/10
2 matching out of 3 → approximately 6.67/10
0 matching out of 3 → 0/10
```

If the Trip has no interests:

```text
interests factor = unavailable
```

---

# 24. Travel Style Matching

Tripromio currently has:

```text
TripType
```

and candidate:

```text
TravelStyle
```

These are not the same enum.

An initial deterministic compatibility matrix may be used as an MVP heuristic.

Initial concept:

```text
weekend     → relaxed strong, cultural moderate
adventure   → adventure strong, backpacking moderate
backpacking → backpacking strong, budget moderate, adventure moderate
road_trip   → road_trip strong, adventure moderate, relaxed moderate
nature      → nature strong, relaxed moderate, adventure moderate
photography → cultural strong, nature strong, relaxed moderate
cultural    → cultural strong, relaxed moderate, budget moderate
beach       → relaxed strong, luxury moderate, budget moderate
mountains   → adventure strong, nature strong, backpacking moderate
other       → no predefined travel-style match
```

This is an **initial MVP heuristic**, not permanent product truth.

Keep the mapping centralized and easy to change.

Unmapped combinations should not silently become strong matches.

---

# 25. Candidate Eligibility for Matching

The matching engine must exclude:

- Trip owner.
- Users who are already active members of the Trip.
- Ineligible/inactive accounts if an appropriate account-status rule already exists.

Do NOT invent a new block system inside the matching engine.

If the repository does not yet have a complete blocking subsystem, blocking is a future eligibility rule and will be implemented in the appropriate safety/connection phase.

Do not build a half-finished block feature solely for matching.

---

# 26. Connection Requests Are Separate

Matching does NOT automatically mean connection.

Flow:

```text
Match
 ↓
Candidate discovers score/profile
 ↓
Candidate sends connection request
 ↓
Owner accepts/rejects
```

Do not couple matching logic with request creation.

Matching is calculated data.

Connection is a separate business action.

---

# 27. Chat Rule

Chat is only available after a valid accepted travel connection.

There must never be generic random messaging.

---

# 28. Safety Rules

Safety is first-class.

Planned safety features include:

- Email verification.
- Phone verification.
- Report user.
- Block user.
- Emergency/trusted contacts.
- Share trip details.
- Safety guidelines.
- Community guidelines.

Do not assume all safety features are already implemented.

---

# 29. Reviews

Reviews are allowed only when users have a legitimate completed-trip relationship.

Users must not be able to review unrelated strangers.

---

# 30. Revenue Model

Long-term monetization:

- Premium Subscription.
- Trip Boost.
- Travel Booking Commission.
- Sponsored Listings.

MVP priority is the engagement loop:

```text
Discover
 → Connect
 → Chat
 → Plan
 → Travel
 → Review
```

Do not prioritize payments before the core loop is validated.

---

# 31. Features Outside MVP / Future Roadmap

Do not implement unless explicitly requested:

- Flight booking.
- Hotel booking.
- Full travel marketplace.
- AI/ML matching.
- Video calls.
- Audio calls.
- Social feed.
- Stories.
- Reels.
- Likes/followers.
- Generic public chat.
- Random private messaging.
- Sponsored listings.
- Advanced advertising.
- Complex subscription tiers.
- Live location tracking.
- Complex payment marketplace.

---

# 32. Development Phases — Current Roadmap

```text
Phase 0
Backend Foundation
✅ COMPLETE

Phase 1A
Authentication
✅ COMPLETE

Phase 1B
User Profile & Interests
✅ COMPLETE

Phase 2A
Trip Domain & Database Foundation
✅ COMPLETE

Phase 2B
Trip CRUD & Lifecycle
✅ COMPLETE

Phase 2C
Trip Discovery, Search & Filtering
✅ COMPLETE

Phase 3A
Matching Inputs Foundation
✅ COMPLETE

Phase 3A.1
Preferred Destinations
⏳ REQUIRED BEFORE FINAL MATCHING ENGINE

Phase 3B
Companion Matching Engine
⏳ NOT IMPLEMENTED

Phase 3C
Companion Matching API
⏳ NOT IMPLEMENTED

Phase 4
Connection Requests
⏳ NOT IMPLEMENTED

Phase 5
Chat & Notifications
⏳ NOT IMPLEMENTED

Phase 6
Trip Planning / Itinerary
⏳ NOT IMPLEMENTED

Phase 7
Safety & Trust
⏳ NOT IMPLEMENTED

Phase 8
Reviews & Reputation
⏳ NOT IMPLEMENTED

Phase 9
Admin
⏳ NOT IMPLEMENTED

Phase 10
Monetization
⏳ NOT IMPLEMENTED
```

---

# 33. MVP Definition of Done

MVP is functional when a user can:

```text
Register
 ↓
Complete Profile
 ↓
Set travel preferences
 ↓
Create a Trip
 ↓
Publish Trip
 ↓
Discover Trips
 ↓
View suitable travellers for a specific Trip
 ↓
See explainable compatibility
 ↓
Send connection request
 ↓
Owner accepts connection
 ↓
Traveller becomes valid Trip member
 ↓
Travel-related chat
 ↓
Plan Trip
 ↓
Travel
 ↓
Complete Trip
 ↓
Review Traveller
```

Safety must be available during this journey.

---

# 34. API Design Rules

All mobile-facing functionality should be exposed through APIs.

Success:

```json
{
  "success": true,
  "message": "Operation successful",
  "data": {}
}
```

Validation/error:

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {}
}
```

Use appropriate HTTP status codes.

Never expose:

- passwords
- password hashes
- authentication tokens in unsafe places
- secrets
- private information not required by the client

---

# 35. Database Principles

Use relational design.

Prefer:

- primary keys
- foreign keys
- unique constraints
- indexes
- appropriate nullable fields
- timestamps

Avoid uncontrolled comma-separated relational values.

Existing important relations include:

```text
users ↔ user_interests ↔ interests
trips ↔ trip_interests ↔ interests
users ↔ travel_availabilities
users ↔ user_profiles
users ↔ trips
users ↔ trip_members ↔ trips
users ↔ preferred_destinations   (planned prerequisite for final matching)
```

Do not duplicate master interest names across multiple tables.

---

# 36. Current Database Domain

The current core domain includes:

```text
users
user_profiles
interests
user_interests

trips
trip_members
trip_interests

travel_availabilities
```

Authentication-related infrastructure also includes:

```text
password_reset_tokens
personal_access_tokens
notifications
cache
jobs
```

Do not assume future business tables already exist.

Future examples:

```text
preferred_destinations
trip_requests
conversations
conversation_members
messages
reviews
reports
blocks
emergency_contacts
payments
subscriptions
```

These must be added in their respective phases.

---

# 37. Security Rules

Security is a product requirement.

Implement:

- authentication
- authorization
- policies
- validation
- rate limiting
- secure password hashing
- safe file upload validation
- private data protection
- appropriate CORS
- API throttling where needed
- safe error responses
- no secrets committed
- `.env` never committed
- backend-enforced permissions
- database constraints

Never trust frontend authorization.

The backend is the source of truth.

---

# 38. AI Coding-Agent Rules

Every AI coding agent must:

1. Read this file completely.
2. Inspect current code before changing anything.
3. Understand current phase and current implementation state.
4. Never assume a future table or feature already exists.
5. Never silently invent product requirements.
6. Avoid changing unrelated files.
7. Avoid unnecessary dependencies.
8. Preserve existing architecture unless there is a justified improvement.
9. Explain assumptions.
10. Write tests for important business logic.
11. Run relevant tests after implementation.
12. Report all changed files.
13. Do not automatically implement future phases.
14. Do not commit unless explicitly asked.

---

# 39. Important Matching-Agent Rule

Before implementing the final matching engine, the agent must verify that these inputs actually exist in the repository:

```text
Trip interests
Candidate interests
Candidate budget preferences
Candidate travel availability
Candidate travel style
Candidate preferred destinations
```

If one is missing:

**STOP and report the gap.**

Do not invent values.

Do not normalize missing mandatory information into a fake perfect score.

---

# 40. Current Development State

Environment:

```text
Laravel ✅
PHP ✅
Composer ✅
MySQL ✅
Node/NPM ✅
Git ✅
GitHub ✅
Laragon ✅
Antigravity ✅
```

Repository:

```text
D:\laragon\www\Tripromio
```

Branch:

```text
main
```

The repository has committed checkpoints for all completed phases above.

Current immediate development target:

```text
Phase 3A.1 — Preferred Destinations
```

After it is complete and committed:

```text
Phase 3B — Companion Matching Engine
```

Then:

```text
Phase 3C — Companion Matching API
```

---

# 41. Git / Change Safety

For every phase:

```text
Inspect
 ↓
Plan
 ↓
Implement
 ↓
Test
 ↓
Review
 ↓
Commit
```

Do not skip the review stage.

Do not make large unrelated refactors during a feature phase.

A phase should end with:

- passing tests
- documented decisions
- clean Git checkpoint
- clear next phase boundary

---

# 42. Core Product Principle

> **Tripromio is about people finding the right companion for the right trip — not about people randomly finding people.**

Every product, data-model, API, security, and UI decision should be evaluated against this principle.

