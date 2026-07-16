# Backend API contract — Gear reviews & remarks

Base URL: `https://check-outpay.com/api/v1/rentals`

Optional post-return feedback from renters. Nothing is required — clients may skip the entire form.

---

## When feedback is allowed

- Rental status must be `completed`
- Authenticated renter must own the rental request
- One review row per `(rental_id, rental_item_id, renter_id)` — resubmitting updates the same row

Multi-item rentals: send one entry per gear in the `reviews` array.

---

## Submit feedback

`POST /api/v1/rentals/requests/{id}/reviews`  
Auth: Sanctum renter token.

### Multi-item body (preferred)

```json
{
  "reviews": [
    {
      "item_id": 12,
      "rating": 5,
      "condition": "Good",
      "missing_items": "",
      "remarks": "Worked perfectly, battery was fully charged."
    },
    {
      "item_id": 34,
      "rating": 3,
      "condition": "Old",
      "missing_items": "HDMI cap",
      "remarks": "Minor scuffs on body."
    }
  ]
}
```

### Single-item shorthand

```json
{
  "item_id": 12,
  "rating": 4,
  "condition": "Good",
  "remarks": "All good"
}
```

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| `item_id` | integer | yes* | Must belong to the rental |
| `rating` | integer 1–5 | no | Star rating |
| `condition` | string | no | `New`, `Good`, `Old`, or `Bad` (case-insensitive) |
| `missing_items` | string | no | Free text — what was missing on return |
| `remarks` | string | no | General notes |

\*Required when submitting that row. If all fields for an item are empty/null, that item is skipped.

### Responses

**200 — saved**

```json
{
  "success": true,
  "message": "Thank you for your feedback.",
  "data": {
    "saved": [
      {
        "id": 91,
        "item_id": 12,
        "rental_id": 440,
        "rating": 5,
        "condition": "Good",
        "missing_items": null,
        "remarks": "Worked perfectly, battery was fully charged.",
        "created_at": "2026-07-16T21:30:00+01:00",
        "renter": { "display_name": "A. M." }
      }
    ]
  }
}
```

**422 — rental not completed**

```json
{
  "success": false,
  "message": "Reviews can only be submitted after the rental is completed."
}
```

---

## List reviews for gear detail

`GET /api/v1/rentals/items/{id}/reviews`  
Public (no auth). `{id}` is numeric item id.

Query: `per_page` (default 20, max 50)

```json
{
  "success": true,
  "data": {
    "item_id": 12,
    "average_rating": 4.5,
    "reviews_count": 8,
    "condition_summary": {
      "new": 1,
      "good": 5,
      "old": 2,
      "bad": 0
    },
    "reviews": [
      {
        "id": 91,
        "item_id": 12,
        "rental_id": 440,
        "rating": 5,
        "condition": "Good",
        "missing_items": null,
        "remarks": "Worked perfectly.",
        "created_at": "2026-07-16T21:30:00+01:00",
        "renter": { "display_name": "A. M." }
      }
    ]
  },
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 8,
    "last_page": 1
  }
}
```

`condition_summary` counts reviews that included a condition value.

---

## Item payload aggregates

Catalog item payloads also include:

| Field | Type | Notes |
|-------|------|-------|
| `average_rating` | number \| null | 1 decimal; null when no star ratings yet |
| `reviews_count` | integer | Reviews with any content (rating, condition, missing, or remarks) |
| `rating` | number \| null | Alias of `average_rating` for older clients |

Use `GET /items/{slug}` for detail page header stats; use `GET /items/{id}/reviews` for the full list + condition chips.

---

## Client UX notes

- Show the post-return form after `completed` status (return flow finished).
- Per-gear forms for multi-item carts; allow skip on each card and a global skip.
- Gear detail: show average stars, `reviews_count`, condition chip totals, and paginated review list.
- Until backend is deployed, clients may show a local-only notice; these endpoints are now live on CheckoutPay backend.
