#!/usr/bin/env python3
"""Replace Tailwind CDN with compiled CSS partial — safe placement before </head>."""
from __future__ import annotations

import re
from pathlib import Path

VIEWS = Path(__file__).resolve().parents[1] / "resources" / "views"
INCLUDE = "    @include('partials.tailwind-assets')\n"

# tailwind.config = { ... }; (multiline, nested braces)
CONFIG_RE = re.compile(
    r"\n?\s*<script>\s*\n?\s*tailwind\.config\s*=\s*\{[\s\S]*?\}\s*\n?\s*</script>",
    re.IGNORECASE,
)
# inline one-liner config
CONFIG_INLINE_RE = re.compile(
    r"\n?\s*<script>\s*tailwind\.config\s*=\s*\{[^<]*\}\s*</script>",
    re.IGNORECASE,
)
CDN_SCRIPT_RE = re.compile(
    r'\n?\s*<script[^>]*src="https://cdn\.tailwindcss\.com"[^>]*></script>\s*',
    re.IGNORECASE,
)
PRECONNECT_RE = re.compile(
    r'\n?\s*<link[^>]*href="https://cdn\.tailwindcss\.com"[^>]*/>\s*',
    re.IGNORECASE,
)
FA_RE = re.compile(
    r'\n?\s*<link[^>]*href="https://cdnjs\.cloudflare\.com/ajax/libs/font-awesome/[^"]*"[^>]*/>\s*',
    re.IGNORECASE,
)
COMMENT_TAILWIND_RE = re.compile(
    r"\n?\s*<!--[^>]*[Tt]ailwind[^>]*-->\s*",
)


def migrate(content: str) -> str:
    if "cdn.tailwindcss.com" not in content and "partials.tailwind-assets" not in content:
        return content

    original = content
    content = CDN_SCRIPT_RE.sub("\n", content)
    content = CONFIG_RE.sub("", content)
    content = CONFIG_INLINE_RE.sub("", content)
    content = PRECONNECT_RE.sub("", content)
    content = COMMENT_TAILWIND_RE.sub("\n", content)
    content = FA_RE.sub("\n", content)

    if "partials.tailwind-assets" not in content and "</head>" in content:
        content = content.replace("</head>", INCLUDE + "</head>", 1)

    if content == original and "partials.tailwind-assets" in content:
        return content
    return content


def main() -> None:
    changed = 0
    for path in sorted(VIEWS.rglob("*.blade.php")):
        text = path.read_text(encoding="utf-8")
        new = migrate(text)
        if new != text:
            path.write_text(new, encoding="utf-8")
            changed += 1
            print(path.relative_to(VIEWS.parents[1]))

    # sample-payment-page.html if present
    html = VIEWS / "sample-payment-page.html"
    if html.exists():
        text = html.read_text(encoding="utf-8")
        new = migrate(text)
        if new != text:
            html.write_text(new, encoding="utf-8")
            changed += 1
            print(html.relative_to(VIEWS.parents[1]))

    print(f"Updated {changed} file(s)")


if __name__ == "__main__":
    main()
