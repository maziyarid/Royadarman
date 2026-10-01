# Locale key gaps and physical-direction CSS (RTL) - 2026-10-01

Status: DOCS ONLY. No translation, PHP, Blade or CSS is changed. Base main `dbcb114d83fdc1c3fdf7b8a1dc6f8a64977762dc`. Agiflow RPH-113; coordination issue #15.

## What this session could and could not read

Directory listings (names, types, sizes, blob SHAs) of `backend/lang/{fa,ar,en}` and `backend/resources/views/panel` were readable through the GitHub contents API. File TEXT was not: the contents API returns only a download status for files, `raw.githubusercontent.com` fetches fail for this private repository, and GitHub code search returns no results (the repository is not indexed). Therefore:
- Task item 1 (key-by-key locale comparison): NOT DETERMINED. No key is claimed missing or present. The comparison script below is the way to determine it.
- Task item 2 (physical-direction CSS in views): NOT DETERMINED. No view is claimed clean or dirty. The grep command below is the way to determine it. That `panel/layout.blade.php` already sets `html lang` and `dir` is taken from the task text and was not verified here.
- Task item 3 (Laravel localization behaviour): answered below from Laravel 13.x documentation via Context7.

## 1. Locale files

The same twelve filenames exist in `fa`, `ar` and `en` (verified from the directory listings). Byte sizes differ by locale because Persian and Arabic UTF-8 text uses more bytes per character than English; **size is not evidence of missing keys** and is listed only for reference.

| File | en (bytes) | fa (bytes) | ar (bytes) |
| --- | --- | --- | --- |
| administrators.php | 2036 | 2927 | 2520 |
| auth_ui.php | 1549 | 2246 | 2185 |
| integrations.php | 4087 | 5664 | 5038 |
| marketing.php | 2938 | 6214 | 4093 |
| network.php | 2691 | 3413 | 3347 |
| notifications.php | 355 | 477 | 452 |
| panel.php | 27305 | 36522 | 34572 |
| panel_case.php | 2088 | 2651 | 2603 |
| request.php | 4327 | 5817 | 5442 |
| site.php | 17343 | 29054 | 21037 |
| ui.php | 19771 | 25918 | 24560 |
| validation.php | 10209 | 12778 | 12580 |

### How to list the real key gaps

Run from the repository root with Python 3 (no PHP needed). It parses the plain `return [...]` arrays, flattens nested keys with dots, and prints keys present in one locale but missing in another. A file it cannot parse is reported, never skipped. It was exercised only on small synthetic fixtures (it correctly reported one nested key missing from one locale and one top-level key missing from two); it has not been run on the real files. It does not evaluate PHP, so concatenation, constants or function calls inside a lang file will make that file report "cannot parse".

```python
#!/usr/bin/env python3
"""Report PHP lang-array keys present in one locale but missing in another.
Usage: python3 compare_lang_keys.py backend/lang   (expects fa/ ar/ en/ sub-folders)
Handles the plain `return [ 'key' => 'text', 'group' => [ ... ] ];` files used by Laravel.
Does not evaluate PHP. A file it cannot parse is reported, never silently skipped.
"""
import re, sys, pathlib

TOKEN = re.compile(r"""\s*(?:(//[^\n]*|\#[^\n]*|/\*.*?\*/)|('(?:\\.|[^'\\])*'|"(?:\\.|[^"\\])*")|(=>|\[|\]|,|;)|([^\s\[\],;=']+))""", re.S)

def tokens(text):
    body = text.split("return", 1)[1]
    pos = 0
    while pos < len(body):
        m = TOKEN.match(body, pos)
        if not m:
            if body[pos:].strip() == "":
                return
            raise ValueError("unparsable near: " + body[pos:pos + 40])
        pos = m.end()
        if m.group(1):
            continue
        yield m.group(2) or m.group(3) or m.group(4)

def unq(s):
    return s[1:-1].replace("\\'", "'").replace('\\"', '"').replace("\\\\", "\\")

def parse(tk):
    """Parse one array body; the opening '[' has already been consumed."""
    out = {}
    auto = 0
    while True:
        t = next(tk)
        if t == "]":
            return out
        if t == ",":
            continue
        if t == "[":
            out[str(auto)] = parse(tk)
            auto += 1
            continue
        a = next(tk)
        if a == "=>":
            key = unq(t) if t[0] in "'\"" else t
            v = next(tk)
            out[key] = parse(tk) if v == "[" else (unq(v) if v[0] in "'\"" else v)
        else:
            out[str(auto)] = unq(t) if t[0] in "'\"" else t
            auto += 1
            if a == "]":
                return out


def flat(d, prefix=""):
    for k, v in d.items():
        if isinstance(v, dict):
            yield from flat(v, prefix + k + ".")
        else:
            yield prefix + k

def keys(path):
    tk = tokens(path.read_text(encoding="utf-8"))
    if next(tk) != "[":
        raise ValueError("file does not start with return [")
    return set(flat(parse(tk)))

def main(root):
    root = pathlib.Path(root)
    locales = ["fa", "ar", "en"]
    files = sorted({p.name for l in locales for p in (root / l).glob("*.php")})
    for f in files:
        sets, bad = {}, []
        for l in locales:
            p = root / l / f
            if not p.exists():
                bad.append(f"{l}: FILE MISSING")
                continue
            try:
                sets[l] = keys(p)
            except Exception as e:
                bad.append(f"{l}: cannot parse ({e})")
        union = set().union(*sets.values()) if sets else set()
        for l in sets:
            miss = sorted(union - sets[l])
            if miss:
                print(f"{f} [{l}] missing {len(miss)}: " + ", ".join(miss))
        for b in bad:
            print(f"{f} {b}")

main(sys.argv[1] if len(sys.argv) > 1 else "backend/lang")
```

Command: `python3 compare_lang_keys.py backend/lang`

## 2. Physical-direction CSS in views

Listing read: `backend/resources/views/panel` contains `layout.blade.php` (8088 bytes), `dashboard.blade.php`, `case.blade.php`, `patient-new-request.blade.php`, `profile.blade.php` and the sub-folders administrators, analytics, calendar, cases, deliveries, home-service, integrations, launch-readiness, marketing, network, policies, search, support and tasks. Public layout partials were not listed in this session.

To find physical properties, run from the repository root (read-only; edits nothing):

```
grep -rnE "(margin|padding|border)-(left|right)|float:[[:space:]]*(left|right)|text-align:[[:space:]]*(left|right)|(^|[^-a-z])(left|right):[[:space:]]*[0-9-]" backend/resources/views backend/public/assets backend/resources/css 2>/dev/null
```

Expected caveats: the pattern also matches legitimate uses (for example `left: 0` for a full-width overlay); each hit needs a human decision. It does not find Tailwind-style classes such as `ml-4` or `text-left`, or inline `style` attributes built in PHP. Add `grep -rnE "\b(ml|mr|pl|pr|text-left|text-right|float-left|float-right)-?[0-9a-z]*"` if the stack uses utility classes.

## 3. Laravel 13 localization: missing key behaviour

In Laravel 13 a missing translation key is first looked up in the configured fallback locale (`fallback_locale`), and only if it is missing there too does `trans()`/`__()` return the key string itself, which is shown in the page (Laravel documentation, Localization and Helpers/Strings pages; behaviour shown in the 13.x `Illuminate\Translation\Translator::get`). Which locale is the fallback for this application depends on `config/app.php`, which was not read in this session.

Version basis: `backend/composer.lock` could not be read as text. Used instead: `backend/composer.json` requires `laravel/framework ^13.17` (issue #15 packet 4), and Laravel 13.29.0 as reported in `backend/AGENTS.md` and in Codex's PR #20 run from the existing lock.

## 4. Recorded, not implemented

- Prefer logical CSS (`margin-inline`, `padding-inline`, `text-align: start`) over `left`/`right` in a later, separately approved change.
- Never mirror a dental chart or radiograph under RTL. FDI tooth numbers stay Western digits in anatomical order.
- No translation was written, and none is proposed here.
