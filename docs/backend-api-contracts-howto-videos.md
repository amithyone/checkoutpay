# Backend API contract — How-to videos on rental items

Base URL: `https://check-outpay.com/api/v1/rentals`

How-to videos are YouTube links vendors attach to gear so renters can watch setup/usage guides in the app item detail screen.

---

## Item payload fields

Every catalog item returned by `GET /items`, `GET /items/{slug}`, featured slider item slides, favorites, and rental line items includes:

```json
{
  "how_to_videos": [
    { "title": "Komodo quick start", "url": "https://www.youtube.com/watch?v=abc123" },
    { "title": "Mounting lenses", "url": "https://youtu.be/xyz789" }
  ]
}
```

| Field | Type | Notes |
|-------|------|-------|
| `how_to_videos` | array | Always present; empty array when none configured |
| `how_to_videos[].title` | string | Display title (max 200 chars) |
| `how_to_videos[].url` | string | YouTube watch or youtu.be URL |

---

## Accepted input field names (vendor update)

When creating or updating inventory, vendors may send either snake_case or camelCase:

| Canonical | Aliases accepted on write |
|-----------|---------------------------|
| `how_to_videos` | `howToVideos` |
| `title` | `name`, `label` |
| `url` | `link`, `youtube_url`, `video_url` |

Invalid or non-YouTube URLs are silently dropped during normalization.

---

## Vendor APIs

### Create item

`POST /api/v1/rentals/business/items`  
Auth: Sanctum renter token linked to a business.

```json
{
  "name": "RED Komodo 6K",
  "daily_rate": 85000,
  "quantity_available": 2,
  "how_to_videos": [
    { "title": "Komodo quick start", "url": "https://www.youtube.com/watch?v=…" },
    { "title": "Mounting lenses", "url": "https://youtu.be/…" }
  ]
}
```

### Update item

`PATCH /api/v1/rentals/business/items/{item}`  
Also accepts `POST` with `_method=PATCH` for multipart.

Send `how_to_videos: []` to clear all links.

### Admin override

`PATCH /api/v1/rentals/admin/items/{item}`  
Same `how_to_videos` / `howToVideos` shape.

---

## Storage

Stored as JSON on `rental_items.how_to_videos`.

---

## Client notes

- Render as a horizontal list or stacked cards linking out to YouTube (in-app browser or external).
- Do not require videos to checkout.
- Hide the section when `how_to_videos.length === 0`.
