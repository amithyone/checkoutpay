# Python Extraction Service Setup Guide

## Architecture Overview

This system uses a **Python FastAPI microservice** for email extraction while keeping all matching logic in Laravel.

### What Each Part Does

**Python (Extraction Engine)**
- Email content analysis
- Amount extraction (HTML tables, text patterns)
- Currency detection
- Sender name extraction
- Confidence scoring
- Comprehensive diagnostics

**Laravel (System of Record & Matcher)**
- Email ingestion & storage
- Job queues
- **Matching logic** (transactions ↔ users)
- Business rules
- Admin UI
- Auditing & logging
- **Validates Python results** (confidence, amount rules)

## Installation

### Step 1: Setup Python Service

```bash
# Navigate to python-extractor directory
cd python-extractor

# Create virtual environment
python3 -m venv venv
source venv/bin/activate  # On Windows: venv\Scripts\activate

# Install dependencies
pip install -r requirements.txt
```

### Step 2: Run Python Service

**Development:**
```bash
python main.py
```

**Production with uvicorn:**
```bash
uvicorn main:app --host 0.0.0.0 --port 8000 --workers 4
```

**With Docker:**
```bash
docker build -t python-extractor .
docker run -p 8000:8000 python-extractor
```

### Step 3: Configure Laravel

Add to your `.env` file:

```env
# Python Extraction Service Configuration
PYTHON_EXTRACTOR_URL=http://localhost:8000
PYTHON_EXTRACTOR_TIMEOUT=10
PYTHON_EXTRACTOR_MIN_CONFIDENCE=0.7
PYTHON_EXTRACTOR_ENABLED=true
```

### Step 4: Test Integration

**Test Python service directly:**
```bash
curl -X POST http://localhost:8000/extract \
  -H "Content-Type: application/json" \
  -d '{
    "email_id": 1,
    "subject": "Credit Alert",
    "from_email": "noreply@gtbank.com",
    "text_body": "Your account has been credited with NGN 1000.00",
    "html_body": "<table><tr><td>Amount</td><td>NGN 1,000.00</td></tr></table>"
  }'
```

**Check health:**
```bash
curl http://localhost:8000/health
```

## How It Works

### Request Flow

```
1. Email arrives → Laravel stores in processed_emails table
2. Laravel calls Python extraction service via HTTP
3. Python extracts payment info (amount, name, etc.) + confidence score
4. Laravel validates:
   - Confidence >= 0.7 (configurable)
   - Amount >= 10 Naira
5. Laravel runs matching logic (transaction ↔ email)
6. Laravel stores match attempt with diagnostics
```

### API Contract

**Laravel → Python:**
```json
{
  "email_id": 12345,
  "subject": "Credit Alert",
  "from_email": "noreply@gtbank.com",
  "text_body": "...",
  "html_body": "...",
  "email_date": "2024-01-10T10:30:00Z"
}
```

**Python → Laravel:**
```json
{
  "success": true,
  "data": {
    "amount": 1000.00,
    "currency": "NGN",
    "direction": "credit",
    "confidence": 0.95,
    "source": "html_table",
    "sender_name": "john doe",
    "account_number": null
  },
  "errors": [],
  "diagnostics": {
    "steps": ["Attempting HTML table extraction", "Extraction successful"],
    "errors": [],
    "text_length": 234,
    "html_length": 5432
  }
}
```

## Deployment Options

### Option A: Same Server (Simplest)

```
┌─────────────────────────────┐
│  Server                     │
│                             │
│  ┌─────────┐  ┌──────────┐ │
│  │ Laravel │  │  Python  │ │
│  │  App    │──│  FastAPI │ │
│  ┌─────────┘  └──────────┘ │
│                             │
│  Nginx routes /extract      │
└─────────────────────────────┘
```

**Nginx config:**
```nginx
location /api/extract {
    proxy_pass http://localhost:8000/extract;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}
```

### Option B: Docker Compose (Recommended)

Create `docker-compose.yml`:

```yaml
version: '3.8'

services:
  laravel:
    build: .
    ports:
      - "80:80"
    depends_on:
      - python-extractor
    environment:
      - PYTHON_EXTRACTOR_URL=http://python-extractor:8000

  python-extractor:
    build: ./python-extractor
    ports:
      - "8000:8000"
    environment:
      - PYTHONUNBUFFERED=1
```

### Option C: Separate Servers (Scalable)

```
Laravel Server ──HTTP──▶ Python Server
     │                      │
     └── Validates JSON ◀───┘
```

## Fallback Behavior

If Python service is unavailable:
- Laravel automatically falls back to PHP extraction (existing logic)
- Logs warning about Python service unavailability
- System continues to work normally
- No breaking changes

## Configuration Options

### Environment Variables

- `PYTHON_EXTRACTOR_URL`: Python service URL (default: `http://localhost:8000`)
- `PYTHON_EXTRACTOR_TIMEOUT`: Request timeout in seconds (default: `10`)
- `PYTHON_EXTRACTOR_MIN_CONFIDENCE`: Minimum confidence score (0.0-1.0, default: `0.7`)
- `PYTHON_EXTRACTOR_ENABLED`: Enable/disable Python extraction (default: `true`)

### Confidence Scores

- `0.95-1.0`: HTML table extraction (highest accuracy)
- `0.85-0.94`: HTML text extraction
- `0.75-0.84`: HTML rendered to text
- `0.70-0.74`: Plain text extraction (lowest accuracy)

Anything below `PYTHON_EXTRACTOR_MIN_CONFIDENCE` is rejected.

## Monitoring

### Health Check

Laravel automatically checks Python service health:
- Cached for 60 seconds
- Used to decide whether to call Python or use PHP fallback
- Endpoint: `GET /health`

### Logging

All extraction attempts are logged:
- Success/failure
- Confidence scores
- Extraction method used
- Diagnostics from Python
- Fallback to PHP if Python unavailable

Check logs at: `storage/logs/laravel.log`

## Troubleshooting

### Python service not responding

1. Check if service is running: `curl http://localhost:8000/health`
2. Check logs: `tail -f python-extractor/logs/app.log`
3. Verify environment variables in Laravel `.env`
4. Check firewall/network connectivity
5. System will automatically fallback to PHP extraction

### Low confidence scores

1. Check email format (might not match expected patterns)
2. Review Python extraction patterns in `main.py`
3. Check diagnostics in match attempt details
4. Adjust `PYTHON_EXTRACTOR_MIN_CONFIDENCE` if needed

### Integration issues

1. Verify `PythonExtractionService.php` is loaded
2. Check Laravel logs for Python service errors
3. Test Python service directly with curl
4. Verify JSON format matches API contract

## Next Steps

1. **Deploy Python service** on your server
2. **Update Laravel `.env`** with Python service URL
3. **Test extraction** with real emails
4. **Monitor logs** for confidence scores and fallbacks
5. **Fine-tune patterns** in Python based on real email samples

## Benefits of This Architecture

✅ **High extraction accuracy** - Python's BeautifulSoup handles HTML better than regex  
✅ **Fast processing** - Async FastAPI handles concurrent requests  
✅ **Minimal risk** - PHP fallback if Python fails  
✅ **No framework rewrite** - Laravel stays for matching logic  
✅ **Scalable** - Python service can scale independently  
✅ **Language-agnostic** - Clean HTTP/JSON contract  

## Important Notes

❌ **Python should NOT:**
- Touch Laravel database
- Know about users/transactions
- Do wallet matching
- Access Laravel models

✅ **Python should ONLY:**
- Extract facts from email content
- Return structured JSON
- Provide confidence scores

✅ **Laravel handles:**
- All business logic
- Database operations
- Matching algorithms
- User/wallet management
