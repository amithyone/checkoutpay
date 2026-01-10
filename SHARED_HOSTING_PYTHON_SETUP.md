# Python Extraction Service - Shared Hosting Setup

## Important: Shared Hosting Limitations

Shared hosting typically has these limitations:
- ❌ Cannot create symlinks (virtual environments need symlinks)
- ❌ Cannot run long-running services (like FastAPI)
- ❌ Cannot bind to arbitrary ports (8000 may be blocked)
- ❌ Limited Python package installation options
- ✅ Python may be available but pip might not be in PATH

## Option 1: Use PHP Extraction Only (Simplest)

**Recommended for shared hosting** - The PHP extraction is already working and will continue to work. Python is an enhancement, not a requirement.

**To disable Python extraction:**
```env
PYTHON_EXTRACTOR_ENABLED=false
```

The system will automatically use PHP extraction (existing logic).

## Option 2: Python CGI Script (Shared Hosting Compatible)

If you want to use Python extraction on shared hosting, we can create a CGI script that Laravel calls via HTTP.

### Step 1: Upload Python Files

Upload the `python-extractor` directory to your server:
```bash
# On your local machine
cd /Users/amithy/Documents/checkoutpay
tar -czf python-extractor.tar.gz python-extractor/
# Upload python-extractor.tar.gz to your server via FTP/SFTP
# Extract on server: tar -xzf python-extractor.tar.gz
```

### Step 2: Check Python Availability

```bash
# Check if Python 3 is available
python3 --version
# or
python --version

# Check if pip is available
python3 -m pip --version
# or
pip3 --version
```

### Step 3: Install Packages (User Install)

On shared hosting, install packages with `--user` flag (no virtual environment needed):

```bash
cd ~/public_html/python-extractor  # or wherever you uploaded it

# Install packages to user directory
python3 -m pip install --user fastapi uvicorn beautifulsoup4 lxml pydantic

# Or if pip3 is available directly
pip3 install --user fastapi uvicorn beautifulsoup4 lxml pydantic
```

### Step 4: Create a Simple CGI Wrapper

Since you can't run FastAPI as a service, create a simple PHP script that calls Python:

```php
<?php
// public/extract-payment.php
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Call Python script
$pythonScript = __DIR__ . '/../python-extractor/extract_cgi.py';
$command = "python3 " . escapeshellarg($pythonScript) . " " . escapeshellarg(json_encode($data));
$output = shell_exec($command);

header('Content-Type: application/json');
echo $output;
```

## Option 3: Simplified Python Script (No FastAPI)

Create a simpler Python script that doesn't require FastAPI or uvicorn:

```python
#!/usr/bin/env python3
# python-extractor/extract_simple.py
import sys
import json
import re
from html.parser import HTMLParser

# Simple extraction logic (no external dependencies)
def extract_amount(text, html):
    # Your extraction logic here
    patterns = [
        r'(?:ngn|naira|₦)\s*([\d,]+\.?\d*)',
        r'amount[\s:]+(?:ngn|naira|₦)\s*([\d,]+\.?\d*)',
    ]
    
    for pattern in patterns:
        match = re.search(pattern, (html or text or ''), re.IGNORECASE)
        if match:
            try:
                amount = float(match.group(1).replace(',', ''))
                if amount >= 10:
                    return {
                        "success": True,
                        "data": {
                            "amount": amount,
                            "currency": "NGN",
                            "confidence": 0.85,
                            "source": "simple_regex"
                        }
                    }
            except:
                pass
    
    return {"success": False, "errors": ["No amount found"]}

if __name__ == "__main__":
    input_data = json.loads(sys.argv[1] if len(sys.argv) > 1 else sys.stdin.read())
    result = extract_amount(input_data.get('text_body'), input_data.get('html_body'))
    print(json.dumps(result))
```

## Option 4: Use External Python Service (Best for Shared Hosting)

Deploy Python service on a separate server/service:

1. **Use a free Python hosting service:**
   - PythonAnywhere (free tier available)
   - Heroku (free tier discontinued, but paid options)
   - Railway.app
   - Render.com

2. **Update Laravel `.env`:**
   ```env
   PYTHON_EXTRACTOR_URL=https://your-python-service.railway.app
   ```

## Option 5: Use Simple Python Script (NO Dependencies) - RECOMMENDED FOR SHARED HOSTING

I've created `extract_simple.py` which uses **only Python standard library** - no FastAPI, no BeautifulSoup, no external packages needed!

### Step 1: Upload Python Script

```bash
# On your local machine, upload python-extractor/extract_simple.py to your server
# Place it in: ~/public_html/python-extractor/extract_simple.py
# Make it executable:
chmod +x python-extractor/extract_simple.py
```

### Step 2: Configure Laravel

Add to `.env`:

```env
PYTHON_EXTRACTOR_ENABLED=true
PYTHON_EXTRACTOR_MODE=script
PYTHON_EXTRACTOR_SCRIPT_PATH=/home/checzspw/public_html/python-extractor/extract_simple.py
PYTHON_EXTRACTOR_COMMAND=python3
PYTHON_EXTRACTOR_MIN_CONFIDENCE=0.7
```

**Important:** Update the path to match where you uploaded the script.

### Step 3: Test Python Script Directly

```bash
# Test if Python works
python3 --version

# Test the script
cd ~/public_html/python-extractor
echo '{"text_body":"Your account credited with NGN 1000.00","html_body":"<table><tr><td>Amount</td><td>NGN 1,000.00</td></tr></table>"}' | python3 extract_simple.py
```

If this works, the system will automatically use Python extraction!

## Recommended Approach for Shared Hosting

### Option A: Use Simple Python Script (Recommended if Python 3 works)

1. Upload `python-extractor/extract_simple.py` to your server
2. Configure `.env` as shown in Option 5 above
3. Test and verify it works

### Option B: Use PHP Extraction Only (Simplest)

If Python doesn't work or you want to avoid setup:

1. Set in `.env`:
   ```env
   PYTHON_EXTRACTOR_ENABLED=false
   ```

2. The system will use the existing PHP extraction logic (which is already working)

3. When you have a VPS or dedicated server, you can switch to Python extraction

## Quick Check: What Python is Available?

Run these commands to see what's available:

```bash
# Check Python
which python3
python3 --version

# Check if Python works (should output version)
python3 -c "import sys; print(sys.version)"

# Test the simple extraction script
echo '{"text_body":"NGN 1000.00","html_body":""}' | python3 extract_simple.py
```

**If `python3 --version` works, use Option 5 (simple script mode).**  
**If not, use Option B (PHP extraction only).**
