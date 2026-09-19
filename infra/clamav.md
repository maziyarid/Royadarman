# ClamAV expectations (OPG quarantine)

**Committing this file does not install ClamAV or run EICAR on the host.**

Royadarman never streams an OPG until the fail-closed scanner returns a clean
verdict. Application code: `backend/app/Infrastructure/Documents/ClamAvDocumentScanner.php`.

## Required binary

| Item | Documented default |
|---|---|
| Command | `/usr/bin/clamscan` (`ROYADARMAN_OPG_SCANNER_COMMAND`) |
| Must be | executable by the application user |
| Timeout | `ROYADARMAN_OPG_SCANNER_TIMEOUT` (default 60s) |
| Enable flag | `ROYADARMAN_OPG_SCANNER_ENABLED=true` only when intake is being activated |

`royadarman:preflight` **fails** when `INTAKE_ENABLED=true` and the scanner is
disabled or the command is empty.

## Verdict mapping (do not change)

| `clamscan` exit | Application result |
|---|---|
| 0 | clean → eligible for promotion |
| 1 | infected → remains quarantined |
| anything else / timeout / non-executable / disabled | throw → remains quarantined |

Invocation is `clamscan --no-summary <absolute-quarantine-path>`. No network
scan. No public object URL.

## Operator drills (approved window only)

1. Confirm `$CLAMSCAN -V` reports a current signature database.
2. Clean JPEG/PNG fixture → exit 0.
3. EICAR fixture → exit 1, document stays quarantined, audit recorded.
4. Forced timeout / missing binary → no promotion (fail closed).

Do not enable intake because this markdown exists. Do not store EICAR in the
repository.
