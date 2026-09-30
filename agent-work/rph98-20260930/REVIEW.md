# Codex integration review

Pending; not deployed or accepted.

Candidate view has inline JavaScript. Captured webroot CSP uses script-src 'self'
without inline allowance/nonce; filter handlers will be blocked. Move them to a
same-origin external asset, preserving CSP, and test actual response headers.

The synthetic fixture is not the Laravel-rendered authenticated screen; visual
test screenshots were not present in the transferred package. Static banned-word
scanning does not establish tenant/role isolation. Test actual payloads and
denied-access cases.

Probe confirms existing seams in 1403-12 and 1404-12. According to the original
handover the boundary test was changed to expect a known defect. That is a
diagnostic, not calendar correctness. Add a failing regression test for corrected
behaviour when fixing it. Stored-event/notification correlation and authenticated
walkthrough remain open.
