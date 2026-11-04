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

readme_text = (ROOT / "readme.txt").read_text(encoding="utf-8")
changelog = readme_text.split("== Changelog ==", 1)[-1]
versions = re.findall(r"^= (\\d+\\.\\d+\\.\\d+) =$", changelog, re.M)
if not versions or versions[0] != plugin_field("Version"):
    raise SystemExit("Latest release must be the first changelog entry")
if len(versions) != len(set(versions)):
    raise SystemExit("Changelog contains a duplicate release heading")
if versions != sorted(versions, key=lambda version: tuple(map(int, version.split("."))), reverse=True):
    raise SystemExit("Changelog versions must be newest first")
