#!/usr/bin/env python3
"""Fail closed when the plugin header and WordPress readme release facts drift."""

from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
plugin_header = (ROOT / "woocommerce-egaline-calculator.php").read_text(encoding="utf-8").split("*/", 1)[0]
readme_header = (ROOT / "readme.txt").read_text(encoding="utf-8").split("== Description ==", 1)[0]


def plugin_field(name: str) -> str | None:
    match = re.search(r"^\s*\*\s*" + re.escape(name) + r":\s*(\S.*?)\s*$", plugin_header, re.M)
    return match.group(1) if match else None


def readme_field(name: str) -> str | None:
    match = re.search(r"^" + re.escape(name) + r":\s*(\S.*?)\s*$", readme_header, re.M)
    return match.group(1) if match else None


pairs = {
    "Version": "Stable tag",
    "Requires at least": "Requires at least",
    "Requires PHP": "Requires PHP",
    "Requires Plugins": "Requires Plugins",
    "Tested up to": "Tested up to",
    "WC requires at least": "WC requires at least",
    "WC tested up to": "WC tested up to",
}

for plugin_key, readme_key in pairs.items():
    actual = plugin_field(plugin_key)
    expected = readme_field(readme_key)
    if not actual or not expected or actual != expected:
        raise SystemExit(
            f"Release metadata mismatch: plugin {plugin_key}={actual!r}; "
            f"readme {readme_key}={expected!r}"
        )
    print(f"PASS {plugin_key}: {actual}")
