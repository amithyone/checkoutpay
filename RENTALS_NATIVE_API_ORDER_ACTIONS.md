# Rentals native API — ratings, pickup/return, cancel, complaints

Base URL: `https://check-outpay.com/api/v1`

Auth (unless noted): `Authorization: Bearer <renter Sanctum token>`

Content-Type: `application/json` (or `multipart/form-data` where image uploads are used).

These routes are already live under `/api/v1/rentals/…`.

---

## Order detail (context)

| Method | Path | Notes |
|--------|------|--------|
| `GET` | `/rentals/requests` | List renter orders |
| `GET` | `/rentals/requests/{rentalId}` | Detail — includes `cancellable`, `cancel_deadline`, items, escrow, timestamps |

Use `cancellable` from the detail payload to show/hide **Cancel** before pickup.

---

## Ratings & reviews

| Method | Path | Auth | When |
|--------|------|------|------|
| `GET` | `/rentals/items/{itemId}/reviews` | No | Catalog tile / product page |
| `POST` | `/rentals/requests/{rentalId}/reviews` | Yes | Only when rental `status === completed` |

### Submit review (single item)

```http
POST /api/v1/rentals/requests/{rentalId}/reviews
```

```json
{
  "item_id": 70,
  "rating": 5,
  "condition": "good",
  "missing_items": null,
  "remarks": "Worked perfectly."
}
```

### Submit reviews (batch — multi-item rental)

```json
{
  "reviews": [
    {
      "item_id": 70,
      "rating": 5,
      "condition": "good",
      "remarks": "Great"
    },
    {
      "item_id": 71,
      "rating": 4,
      "condition": "good",
      "remarks": "Fine"
    }
  ]
}
```

| Field | Type | Notes |
|-------|------|--------|
| `item_id` | int | Must be an item on this rental |
| `rating` | int \| null | `1`–`5` |
| `condition` | string \| null | `new` \| `good` \| `old` \| `bad` |
| `missing_items` | string \| null | Optional |
| `remarks` | string \| null | Comment / review text |

At least one of `rating`, `condition`, `missing_items`, or `remarks` must be present per entry.

### List item reviews (public)

```http
GET /api/v1/rentals/items/{itemId}/reviews?per_page=20
```

Response includes `average_rating`, `reviews_count`, `condition_summary`, and `reviews[]` with renter display name.

---

## Pickup / return (renter)

| Method | Path | Body | When |
|--------|------|------|------|
| `POST` | `/rentals/requests/{rentalId}/fulfillment` | See below | After pay — choose pickup vs delivery |
| `POST` | `/rentals/requests/{rentalId}/return-method` | See below | Return logistics preference |
| `POST` | `/rentals/requests/{rentalId}/request-return` | _(empty)_ | Renter starts return |
| `POST` | `/rentals/requests/{rentalId}/condition-report` | See below | Photos/notes at pickup or return |
| `POST` | `/rentals/requests/{rentalId}/cancel` | `{ "reason": "optional" }` | Cancel **before** pickup |

### Set fulfillment (pickup vs delivery)

```http
POST /api/v1/rentals/requests/{rentalId}/fulfillment
```

```json
{
  "fulfillment_method": "pickup",
  "delivery_address": null
}
```

Or delivery:

```json
{
  "fulfillment_method": "delivery",
  "delivery_address": "12 Example Street, Ikeja, Lagos"
}
```

`fulfillment_method`: `pickup` | `delivery`  
`delivery_address` is **required** when method is `delivery`.

### Set return method

```http
POST /api/v1/rentals/requests/{rentalId}/return-method
```

```json
{
  "return_method": "pickup_return"
}
```

`return_method`: `pickup_return` | `rider_return`

### Request return

```http
POST /api/v1/rentals/requests/{rentalId}/request-return
```

No body. Sets `renter_return_requested_at`.  
Allowed when status is `approved`, `active`, or `completed`.  
Host must still confirm return (see business endpoints).

### Condition report (pickup / return)

```http
POST /api/v1/rentals/requests/{rentalId}/condition-report
```

JSON:

```json
{
  "phase": "pickup",
  "notes": "Scratches on left side already present",
  "images": ["https://example.com/photo1.jpg"]
}
```

Or multipart form:

- `phase` — `pickup` | `return`
- `notes` — optional string
- `images_files[]` — image files (max 4MB each)

`phase`: `pickup` | `return`

### Cancel before pickup

```http
POST /api/v1/rentals/requests/{rentalId}/cancel
```

```json
{
  "reason": "Plans changed"
}
```

Only when detail says `cancellable === true` (status `pending` or `approved`, not yet `active` / no `started_at`). Refunds via escrow when applicable.

There is **no separate “cancel pickup”** route — use this cancel endpoint while the order is still pre-pickup.

---

## Complaints

### Rental dispute (damage / missing / late / other)

| Method | Path |
|--------|------|
| `POST` | `/rentals/requests/{rentalId}/disputes` |
| `GET` | `/rentals/requests/{rentalId}/disputes` |
| `POST` | `/rentals/disputes/{disputeId}/resolve` |

#### Open dispute

```http
POST /api/v1/rentals/requests/{rentalId}/disputes
```

```json
{
  "reason": "damage",
  "description": "Lens was cracked when I opened the case.",
  "requested_deposit_capture": 0
}
```

`reason`: `damage` | `missing` | `late` | `other`

#### List disputes

```http
GET /api/v1/rentals/requests/{rentalId}/disputes
```

#### Resolve dispute (ops / resolve flow — usually not guest UX)

```http
POST /api/v1/rentals/disputes/{disputeId}/resolve
```

```json
{
  "resolution": "release_deposit",
  "capture_amount": 0,
  "notes": "No fault found"
}
```

`resolution`: `release_deposit` | `capture_partial` | `capture_full`  
`capture_amount` required when `resolution` is `capture_partial`.

### Support tickets (general complaint)

| Method | Path |
|--------|------|
| `POST` | `/rentals/support/tickets` |
| `GET` | `/rentals/support/tickets` |
| `GET` | `/rentals/support/tickets/{ticketId}/messages` |
| `POST` | `/rentals/support/tickets/{ticketId}/messages` |

#### Create ticket

```json
{
  "subject": "Issue with my rental",
  "message": "Full description…"
}
```

#### Reply

```json
{
  "message": "Follow-up message…"
}
```

---

## Host / business side

Same renter Sanctum token; business is resolved by matching renter email → business account.

| Method | Path | Role |
|--------|------|------|
| `POST` | `/rentals/business/rentals/{rentalId}/mark-picked-up` | Host confirms pickup → status `active` |
| `POST` | `/rentals/business/rentals/{rentalId}/confirm-return` | Host confirms return (after renter `request-return`) |
| `POST` | `/rentals/business/rentals/{rentalId}/condition-report` | Host condition photos (`phase`: `pickup` \| `return`) |
| `POST` | `/rentals/business/rentals/{rentalId}/approve` | Approve booking |
| `POST` | `/rentals/business/rentals/{rentalId}/reject` | Reject booking |

### Mark picked up

```http
POST /api/v1/rentals/business/rentals/{rentalId}/mark-picked-up
```

No body required. Rental must be `approved` (or already `active`).

### Confirm return

```http
POST /api/v1/rentals/business/rentals/{rentalId}/confirm-return
```

Renter must have called `request-return` first (unless already returned).

### Host condition report

Same shape as renter condition report (`phase`, `notes`, `images` / file upload).

---

## Suggested native rental-detail flow

1. **Approved, not picked up**  
   - Show Cancel if `cancellable`  
   - Set fulfillment  
   - Optional condition report with `phase=pickup`

2. **Active**  
   - Condition report `phase=return`  
   - Set return method  
   - **Request return**

3. **Completed**  
   - **Submit reviews**

4. **Problem**  
   - Open dispute and/or create support ticket

---

## Quick reference (full paths)

```
GET    /api/v1/rentals/requests
GET    /api/v1/rentals/requests/{rentalId}

GET    /api/v1/rentals/items/{itemId}/reviews
POST   /api/v1/rentals/requests/{rentalId}/reviews

POST   /api/v1/rentals/requests/{rentalId}/fulfillment
POST   /api/v1/rentals/requests/{rentalId}/return-method
POST   /api/v1/rentals/requests/{rentalId}/request-return
POST   /api/v1/rentals/requests/{rentalId}/condition-report
POST   /api/v1/rentals/requests/{rentalId}/cancel

POST   /api/v1/rentals/requests/{rentalId}/disputes
GET    /api/v1/rentals/requests/{rentalId}/disputes
POST   /api/v1/rentals/disputes/{disputeId}/resolve

POST   /api/v1/rentals/support/tickets
GET    /api/v1/rentals/support/tickets
GET    /api/v1/rentals/support/tickets/{ticketId}/messages
POST   /api/v1/rentals/support/tickets/{ticketId}/messages

POST   /api/v1/rentals/business/rentals/{rentalId}/mark-picked-up
POST   /api/v1/rentals/business/rentals/{rentalId}/confirm-return
POST   /api/v1/rentals/business/rentals/{rentalId}/condition-report
POST   /api/v1/rentals/business/rentals/{rentalId}/approve
POST   /api/v1/rentals/business/rentals/{rentalId}/reject
```
