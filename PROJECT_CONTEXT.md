# Tripromio — Project Context

> This document is the single source of truth for the Tripromio product, its business rules, MVP scope, technical direction, architecture principles, and development constraints.
>
> **Every AI coding agent working on this repository must read this file before making implementation decisions or modifying code.**

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
2. Discover compatible travellers.
3. Send/receive travel connection requests.
4. Connect after mutual acceptance.
5. Chat only for travel-related purposes.
6. Plan the trip together.
7. Travel together.
8. Review each other after the trip.

---

# 2. Product Positioning

Tripromio is a **travel platform**, not a dating or generic social platform.

## Tripromio IS

* Travel companion discovery.
* Trip-specific traveller matching.
* Trip planning.
* Travel-related communication.
* Traveller reputation through ratings/reviews.
* Safety and trust features.

## Tripromio IS NOT

* Dating app.
* Friendship/dating matching app.
* Generic social media app.
* Random messaging application.
* Anonymous chat platform.
* General-purpose community chat.

## Critical Rule

A user's ability to communicate with another user must be connected to an actual travel context.

The product must strongly discourage random messaging and interactions unrelated to travel.

---

# 3. Target Users

Primary users:

* Solo travellers.
* Adventure lovers.
* Budget travellers.
* Backpackers.
* Weekend explorers.
* People who want travel companions.
* People who have a destination/date/budget but do not have a suitable companion.

Typical user problem:

> "I want to visit a destination, but I don't have someone to travel with."

Tripromio's solution:

> "Find travellers with compatible destinations, dates, budgets, interests, and travel styles."

---

# 4. Core Product Flow

The primary product loop is:

```text
User
 ↓
Create Trip
 ↓
Trip becomes discoverable
 ↓
Compatible travellers discover it
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
User builds reputation
 ↓
Next Trip
```

This loop is the most important product functionality.

Do not optimize secondary features before this loop works correctly.

---

# 5. Core MVP Features

The MVP should include the following modules.

## 5.1 Authentication

* Registration.
* Login.
* Logout.
* Forgot password.
* Password reset.
* Email verification.
* Phone verification/OTP.
* Authenticated API access.
* Secure token-based authentication.

Technology:

**Laravel Sanctum**

---

## 5.2 User Profile

Profile fields should include only information that is relevant to the product.

Possible fields:

* Name.
* Profile photo.
* Bio.
* City.
* Country.
* Languages.
* Travel style.
* Interests.
* Optional travel preferences.
* Verification status.
* Reputation/rating summary.

Do not unnecessarily collect sensitive personal information.

---

## 5.3 Interests

Users can select travel-related interests.

Examples:

* Trekking.
* Backpacking.
* Photography.
* Adventure.
* Camping.
* Nature.
* Road trips.
* Food.
* Culture.
* Beaches.
* Mountains.
* Wildlife.
* Spiritual travel.
* History.

Interests must be stored relationally rather than as an uncontrolled comma-separated string.

Recommended relationship:

```text
users
  ↕
user_interests
  ↕
interests
```

---

# 6. Trip Module

A Trip is a core business entity.

## Trip Creation

A user should be able to create a trip with:

* Title.
* Destination.
* Place ID where applicable.
* Latitude.
* Longitude.
* Start date.
* End date.
* Budget minimum.
* Budget maximum.
* Trip type.
* Description.
* Maximum companions/members.
* Trip status.

Potential trip types:

* Weekend.
* Adventure.
* Backpacking.
* Road trip.
* Nature.
* Photography.
* Cultural.
* Beach.
* Mountains.
* Other.

## Trip Lifecycle

A trip should have controlled states.

Possible states:

```text
draft
published
ongoing
completed
cancelled
```

Business rules must prevent invalid state transitions.

---

# 7. Companion Discovery & Matching

Companion discovery is one of Tripromio's main differentiating features.

Initially, do NOT use an AI/ML recommendation system.

Start with a transparent rule-based scoring system.

## Initial Matching Weights

```text
Destination      40%
Date              25%
Budget            15%
Interests         10%
Travel Style      10%
```

Total:

```text
100%
```

Example:

Traveller A:

```text
Destination: Udaipur
Dates: 25-28 Aug
Budget: ₹5,000-₹8,000
Interests: Trekking, Photography
Style: Adventure
```

Traveller B:

```text
Destination: Udaipur
Dates: 26-28 Aug
Budget: ₹6,000-₹9,000
Interests: Trekking, Photography
Style: Adventure
```

Possible result:

```text
Match Score: 90%
```

The scoring engine must be implemented as a reusable service so that it can later be replaced/improved without rewriting the discovery module.

---

# 8. Companion Search & Filters

Users should be able to discover travellers/trips using filters such as:

* Destination.
* Date range.
* Budget.
* Trip type.
* Interests.
* Travel style.
* Verification status where applicable.

Search results should be paginated.

Avoid loading the entire traveller/trip dataset.

The backend must use proper indexes and efficient queries.

---

# 9. Connection / Travel Request System

Users must not immediately become chat contacts.

Flow:

```text
User A
 ↓
Connect / Interested
 ↓
Trip Request
 ↓
User B
 ↓
Accept / Reject
```

Possible request statuses:

```text
pending
accepted
rejected
cancelled
```

Once accepted:

```text
trip_members
```

should represent the actual membership/connection.

---

# 10. Trip Membership

A trip has members.

Recommended roles:

```text
owner
member
```

Trip owner has additional permissions.

The backend must enforce authorization using Policies/permissions and must never rely only on frontend checks.

---

# 11. Chat

Chat exists only in the context of a valid travel connection.

## No Random Chat

A user must not be able to freely message every user.

Recommended flow:

```text
Trip
 ↓
Connection Request
 ↓
Accepted
 ↓
Conversation
 ↓
Messages
```

Chat should initially support:

* Text messages.
* Read/unread state.
* Timestamps.

Potential future message types:

* Image.
* Location.
* Trip/itinerary card.
* Shared place.
* System message.

Realtime technology can be introduced after the basic conversation/message model is stable.

Potential future stack:

* Laravel Reverb/WebSockets.
* Redis.
* Queue workers.
* Push notifications.

Do not introduce unnecessary realtime infrastructure before it is required.

---

# 12. Trip Planning / Itinerary

Tripromio should allow connected trip members to plan their trip together.

Possible itinerary fields:

* Trip ID.
* Title.
* Description.
* Date.
* Start time.
* End time.
* Location.
* Latitude/longitude where applicable.
* Notes.
* Created by.

Example:

```text
4:30 PM
Meet at Amer Road

6:00 PM
Sunset Explore

7:30 PM
Dinner
```

Permissions must be defined so unauthorized users cannot edit another user's private trip data.

---

# 13. Reviews & Ratings

After a trip is completed, travellers can review each other.

Review data:

* Trip.
* Reviewer.
* Reviewed traveller.
* Rating.
* Comment.
* Moderation status.
* Timestamp.

Rating scale:

```text
1–5 stars
```

Reviews should contribute to a traveller's public reputation.

Reviews must be associated with a legitimate trip/member relationship.

Users should not be able to review unrelated strangers.

---

# 14. Safety & Trust

Safety is a first-class feature.

## MVP safety features

* Email verification.
* Phone verification.
* Report user.
* Block user.
* Emergency/trusted contacts.
* Share trip details.
* Safety guidelines.
* Basic community guidelines.

## Report Reasons

Examples:

* Harassment.
* Spam.
* Fake profile.
* Scam.
* Inappropriate behavior.
* Unsafe behavior.
* Other.

Reports must have moderation states.

Example:

```text
pending
investigating
resolved
rejected
```

---

# 15. Emergency / Trusted Contact

Users can store trusted contacts.

Fields:

* Name.
* Phone.
* Relationship.

Future functionality may allow a user to share active trip details with a trusted contact.

Trip sharing should expose only the information necessary for safety.

Do not expose private user data unnecessarily.

---

# 16. Notifications

Notifications should support events such as:

* New connection request.
* Connection accepted.
* Connection rejected.
* New message.
* Trip member joined.
* Trip reminder.
* Trip starting soon.
* Trip completed.
* Review reminder.
* Safety-related events.

Initial backend notification model should be designed independently from any specific push provider.

Push notifications can later use:

**Firebase Cloud Messaging (FCM).**

---

# 17. Maps & Locations

Maps will eventually use Google Maps / Places APIs.

Store location data in a structured way.

Potential fields:

```text
destination
place_id
latitude
longitude
```

Do not rely only on a human-readable destination string for geographic functionality.

Maps integration should be isolated behind services/configuration where practical.

---

# 18. Revenue Model

Long-term monetization:

## Premium Subscription

Potential premium capabilities:

* Advanced matching.
* More visibility.
* Advanced filters.
* Higher connection limits.
* Priority discovery.
* Trip boost allowance.

## Trip Boost

A user may pay to increase trip visibility.

Possible future flow:

```text
Create Trip
 ↓
Boost Trip
 ↓
Payment
 ↓
Boost Activated
 ↓
Higher Discovery Visibility
```

## Travel Booking Commission

Future integration with:

* Hotels.
* Activities.
* Experiences.
* Travel services.

## Sponsored Listings

Future:

* Hotels.
* Activities.
* Travel brands.
* Destinations.

---

# 19. MVP Monetization Rule

Do not make payment/monetization the first development priority.

First validate:

```text
Discover
 → Connect
 → Chat
 → Plan
 → Travel
 → Review
```

Only after the core engagement loop is stable should monetization be integrated.

---

# 20. Features Explicitly Out of MVP

Do not implement these unless a later phase explicitly requests them:

* Flight booking.
* Hotel booking.
* Full travel marketplace.
* Complex AI/ML matching.
* Video calls.
* Audio calls.
* Social media feed.
* Stories.
* Reels.
* Likes/followers system.
* Generic public chat.
* Random private messaging.
* Sponsored listings.
* Advanced advertising platform.
* Complex subscription tiers.
* Live location tracking.
* Complex payment marketplace.

These belong to future roadmap phases.

---

# 21. Technical Stack

## Backend

**Laravel**

## Database

**MySQL**

## Authentication

**Laravel Sanctum**

## API

**REST API**

## Frontend

**Flutter**

Android + iOS from one codebase.

## Admin

Potentially:

**Laravel + Filament**

## Push Notifications

**Firebase Cloud Messaging**

## Maps

**Google Maps / Places APIs**

## Realtime

Potential future:

**Laravel Reverb/WebSockets + Redis**

Do not add realtime infrastructure until required.

## File Storage

Potential future:

**S3-compatible object storage**

---

# 22. Backend Architecture Principles

Preferred application flow:

```text
Route
 ↓
Controller
 ↓
Form Request / Validation
 ↓
Service
 ↓
Model / Query
 ↓
Database
```

Controllers should remain thin.

Business logic should live in services/domain-focused classes rather than giant controllers.

Use:

* Form Requests.
* API Resources.
* Policies.
* Services.
* Jobs where appropriate.
* Events/listeners where appropriate.
* Notifications.
* Proper database transactions.
* Exceptions/error handling.
* Pagination.

Avoid unnecessary architecture complexity.

Do not introduce repositories, microservices, event buses, CQRS, or other abstractions unless there is a real reason.

---

# 23. API Design Rules

All mobile-facing functionality should be exposed through APIs.

Use consistent response structures.

Success example:

```json
{
    "success": true,
    "message": "Operation successful",
    "data": {}
}
```

Validation error example:

```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {}
}
```

Use proper HTTP status codes.

Examples:

```text
200 OK
201 Created
400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
422 Unprocessable Entity
500 Server Error
```

API naming should remain consistent and predictable.

---

# 24. Security Rules

Security must be treated as a product requirement.

Implement:

* Authentication.
* Authorization.
* Policies.
* Request validation.
* Rate limiting.
* Secure password hashing.
* Secure file upload validation.
* Private data protection.
* Proper CORS configuration where needed.
* API throttling where appropriate.
* Safe error responses.
* No secret/API key committed to Git.
* `.env` must never be committed.
* Admin authorization.
* Report/block enforcement.
* Database constraints.
* Input validation.

Never trust frontend authorization checks.

The backend is the source of truth.

---

# 25. Database Principles

Use relational design.

Prefer foreign keys and proper relationships.

Avoid storing relational lists as uncontrolled comma-separated strings.

Example:

Do NOT prefer:

```text
interests = "Trekking,Photography,Adventure"
```

Prefer:

```text
users
user_interests
interests
```

Use:

* Primary keys.
* Foreign keys.
* Unique constraints.
* Indexes.
* Appropriate nullable fields.
* Timestamps.
* Soft deletes only where they provide a real business benefit.

Important query/index candidates will include:

```text
trips.destination
trips.start_date
trips.end_date
trips.status

trip_requests.trip_id
trip_requests.sender_id
trip_requests.receiver_id

trip_members.trip_id
trip_members.user_id

messages.conversation_id
messages.created_at
```

---

# 26. Project Coding Rules

AI coding agents must:

1. Read `PROJECT_CONTEXT.md` before implementation.
2. Inspect existing code before changing it.
3. Avoid modifying unrelated files.
4. Avoid unnecessary dependencies.
5. Avoid duplicate implementations.
6. Preserve existing architecture unless a change is justified.
7. Explain assumptions.
8. Write tests for important business logic.
9. Run relevant tests after implementation.
10. Never silently introduce breaking changes.
11. Never expose secrets.
12. Never change database structure without understanding existing relationships.
13. Prefer small, reviewable changes.
14. Keep naming consistent with existing conventions.

---

# 27. AI Agent Workflow

For every task:

```text
1. Read PROJECT_CONTEXT.md
2. Inspect existing project
3. Understand current phase
4. Identify required files
5. Explain implementation plan
6. Implement
7. Run tests
8. Fix failures
9. Report changed files
10. Report assumptions
11. Wait for next task
```

The AI agent must not independently implement future phases.

For example, while implementing authentication, it must not automatically add:

* Trips.
* Chat.
* Payments.
* Matching.
* Admin.

unless explicitly requested.

---

# 28. Development Phases

## Phase 0 — Foundation

* Laravel project.
* Git.
* Environment configuration.
* Database.
* API structure.
* Coding conventions.
* Global API response format.
* Exception handling.
* Basic test foundation.

## Phase 1 — Authentication & Profile

* Registration.
* Login.
* Logout.
* Email verification.
* Password reset.
* Phone verification.
* User profile.
* Interests.
* Travel preferences.

## Phase 2 — Trips

* Create trip.
* Update trip.
* Cancel trip.
* Publish trip.
* Trip details.
* My trips.
* Trip members.
* Trip lifecycle.

## Phase 3 — Companion Discovery

* Search.
* Filtering.
* Matching score.
* Destination/date matching.
* Budget matching.
* Interest matching.
* Travel-style matching.

## Phase 4 — Connections

* Send request.
* Accept.
* Reject.
* Cancel.
* Block.

## Phase 5 — Chat & Notifications

* Conversations.
* Members.
* Messages.
* Read status.
* Realtime.
* Push notifications.

## Phase 6 — Trip Planning

* Itinerary.
* Schedule.
* Locations.
* Shared planning.

## Phase 7 — Safety & Trust

* Reports.
* Blocks.
* Verification.
* Emergency contacts.
* Trip sharing.
* Safety center.

## Phase 8 — Reviews

* Ratings.
* Reviews.
* Reputation.
* Review moderation.

## Phase 9 — Admin

* Dashboard.
* Users.
* Trips.
* Reports.
* Reviews.
* Moderation.
* Basic analytics.

## Phase 10 — Monetization

* Premium.
* Trip Boost.
* Payments.
* Subscription management.

---

# 29. MVP Definition of Done

The MVP is considered functional when a user can:

```text
Register
 ↓
Complete Profile
 ↓
Create a Trip
 ↓
Discover compatible travellers
 ↓
Send connection request
 ↓
Accept connection
 ↓
Join trip
 ↓
Chat with connected travellers
 ↓
Plan trip
 ↓
Complete trip
 ↓
Review traveller
```

Safety must also be available during this flow.

---

# 30. Current Development State

Current environment:

```text
Laravel        ✅
PHP            ✅
Composer       ✅
MySQL          ✅
Node/NPM       ✅
Git            ✅
GitHub         ✅
Laragon        ✅
Antigravity    ✅
```

Current repository:

```text
D:\laragon\www\Tripromio
```

Current branch:

```text
main
```

The project already has an initial Git commit.

---

# 31. Current Task

The immediate development goal is:

**Phase 0 — Foundation**

Before building business features:

1. Configure API support.
2. Configure MySQL.
3. Verify migrations.
4. Establish API response convention.
5. Establish exception handling.
6. Establish authentication foundation.
7. Confirm project test setup.
8. Document architecture decisions.

Do not implement future business modules until Phase 0 is stable.

---

# 32. Long-Term Product Goal

Tripromio should become a trusted travel companion platform where people can safely discover suitable travel partners, create trips, plan together, travel together, and build a trustworthy traveller reputation.

The product should prioritize:

```text
Trust
Safety
Relevant Connections
Good Matching
Simple UX
Travel Context
```

over unnecessary social features.

---

# 33. Core Product Principle

> **Tripromio is about people finding the right companion for the right trip — not about people randomly finding people.**

Every product and technical decision should be evaluated against this principle.

---

# 34. Instruction to AI Coding Agents

Before making any implementation decision:

**Read this file completely.**

When there is ambiguity:

1. Preserve the documented business rules.
2. Prefer the simplest maintainable architecture.
3. Do not invent new product behavior.
4. Ask for clarification rather than silently changing product scope.
5. Do not implement future-phase functionality unless explicitly requested.
6. Keep changes isolated and testable.

**This document is the project's primary product and architecture context.**
