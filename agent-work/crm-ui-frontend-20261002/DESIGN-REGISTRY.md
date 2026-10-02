# Design reference registry — 128 CRM UI images (D001–D128)

Source: Google Drive folder **CRM UI Design** (`1eVa8Hj5tzrwLU2ZsfTojALZMuepLYNur`), 128 webp images supplied 2026-10-02.
Agiflow: intended for RPH-98 (design system) in *Medical Websites — Operations & Growth*; posting blocked by an Agiflow MCP outage on 2026-10-02 — this file is the durable record.

Each image is registered by Drive file id, mapped to the implemented frontend module, and rendered in the app at `/[locale]/design-reference`. Machine-readable copy: `royadarman-crm-ui/src/data/design-registry.ts`.

| ID | File | Module | Comment / what it is |
| --- | --- | --- | --- |
| D001 | 7dd4f95cd797c78f1f58b4eeae6de73d.webp | dashboard | Overview screen: KPI stat cards, today's schedule list, quick-action tiles. [view](https://drive.google.com/file/d/17vxEkP0TEpyozR1Ib7b88ZNPz09rdCyT/view) |
| D002 | 1e01c74196fe67ad5155d89998221708.webp | patients | Patient records: searchable table with contact, last visit, condition and status. [view](https://drive.google.com/file/d/1R39YwOTdDm7RNIOdniLeYDkSGjVQMlUD/view) |
| D003 | f3b6b8e7f1ae1d25b9052697a7db636b.webp | appointments | Scheduling: appointment list/calendar with confirmed/pending/no-show states and room allocation. [view](https://drive.google.com/file/d/1xV0ZUo0tMQ3U-fP6hzSujfcwOadJwPTf/view) |
| D004 | 6ba2e2e3c47b076e97a42c650effe54a.webp | dental-chart | Dental chart: per-tooth status grid (FDI numbering) with healthy/caries/missing/restored/implant/unknown legend. [view](https://drive.google.com/file/d/11ke6Dh8HBPUKMkPvocJjLHjmJLt9z-_Z/view) |
| D005 | 2eac11e85192875e8b2c195abed13d97.webp | opg-review | Clinical workbench: OPG/radiograph review with image preview, quality assessment and state pipeline. [view](https://drive.google.com/file/d/1J85ic_-Dj1CCImNf2Xi_Vj40gdmawE0s/view) |
| D006 | da312ee354b71642bb26ee6a0e8b1209.webp | treatments | Treatment plan: staged procedures, cost estimate, consent and recovery check-ins. [view](https://drive.google.com/file/d/1Dbz53L8elJfXsAJknclU2KUyqJ1xYZ1w/view) |
| D007 | 7986e093174566a04abecd4bcd3f6576.webp | lab | Laboratory: prosthetics/lab orders with pending, ready and awaiting-lab states. [view](https://drive.google.com/file/d/1SqgdzyUKX4NMf7PcTDYMbK8C1_cVZDFO/view) |
| D008 | 8a6ca619030d69d710ce1ba09668b10d.webp | leads | CRM leads: conversion funnel with source, priority and next follow-up dates. [view](https://drive.google.com/file/d/1nvtOuM9ppDQ2L_5e8qiUAonJEe5Ys9YJ/view) |
| D009 | 85ac21d4464c9c3daaedb8a05b4fe651.webp | invoices | Finance: invoices with paid/unpaid/instalment/cheque states and due dates. [view](https://drive.google.com/file/d/15IRv_iFKWTIhGAn3eQFWF2PvUqZM_abz/view) |
| D010 | original-10a2df99409269af1595c096fab8f695.webp | messages | Communications: unified inbox with unread, awaiting-reply and priority threads. [view](https://drive.google.com/file/d/1I3guMmdR3dKeQM8l48JcxShNt_Gt1uAI/view) |
| D011 | f49bae7e7196e75b4b2f26b19ff8181c.webp | teleconsult | Teleconsultation: session schedule with waiting room, consent status and join actions. [view](https://drive.google.com/file/d/12fPvPiVGoTY8QRq9b_l39WdQUd9uCgkL/view) |
| D012 | 3cf4f4a6c44568d0c682f4b6bf62c9be.webp | staff | Staff operations: shift roster, onboarding, training and leave states. [view](https://drive.google.com/file/d/1ty1zlbCkyu5cC5jUgfar-8l5zAlYZvmf/view) |
| D013 | 27c4f93303e970e8570e1fb76d464464.webp | inventory | Inventory: stock levels, near-expiry items, supplier orders and maintenance. [view](https://drive.google.com/file/d/13nb6BcH5DNd3Jk940TjnPtTKlFmXqdYZ/view) |
| D014 | 0c1adfcdc7e2c61a81db07ac49b8a03c.webp | clinics | Clinic network: branches with rooms, clinicians, capacity utilisation and services. [view](https://drive.google.com/file/d/1HQXzvmGdrjAtbt621yQe_huwztbgukjl/view) |
| D015 | beb25acded1397fb3148d5bee224f2a6.webp | reports | Analytics: role-scoped operational metrics with trend indicators. [view](https://drive.google.com/file/d/1U0H_PIfXKZWPg1RI1A7-gwY5fnG3YIm-/view) |
| D016 | 6db9f7a22f00b482a71b23dcf9e1de69.webp | tasks | Task supervision: personal/overdue/completed operational tasks. [view](https://drive.google.com/file/d/1IJQxKOvhpVICcCMr1s3UCP1ggclDj6ai/view) |
| D017 | original-120cc813a12b45b70a7d13505a5b3b6d.webp | notifications | Notification center: system, payment and schedule events with timestamps. [view](https://drive.google.com/file/d/1BwD1a4DUR_Sq-5ppgh7UKzOMfTFyFycs/view) |
| D018 | original-ef740bdccd0f073550410c2ec3df6dae.webp | audit-security | Audit: actor/action/outcome event log including privileged access and security alerts. [view](https://drive.google.com/file/d/1Xa6GM5d6d3sU6VkTotJP1rShn8em-lir/view) |
| D019 | 81ec9f436f59d1a72cc7c792d5465a6d.webp | settings | Settings: profile, MFA, active sessions, timezone and integration configuration. [view](https://drive.google.com/file/d/13-x_C8DJrME86a-dlgtv0uSZS_DSQB7p/view) |
| D020 | bc6c5b68561d786cd72f9fe9e886126a.webp | public-landing | Public discovery: landing/booking-oriented composition adapted to the CRM shell. [view](https://drive.google.com/file/d/13Hc1zZVjWtMIOy9vRelVDY9JipT2GmwK/view) |
| D021 | c07845db6e55c0226633ec85de4413f5.webp | dashboard | Overview screen: KPI stat cards, today's schedule list, quick-action tiles. [view](https://drive.google.com/file/d/1tgBWLNb02rSglgYzmL1NjzO48KteAA58/view) |
| D022 | a607e3b105980a7e831256cba75e1822 (1).webp | patients | Patient records: searchable table with contact, last visit, condition and status. [view](https://drive.google.com/file/d/1hYCO2-BXkfGLev6XOQxkFNVDUivfh8cZ/view) |
| D023 | b4d21d5ca0dca03fa5b8b08e320c1951 (1).webp | appointments | Scheduling: appointment list/calendar with confirmed/pending/no-show states and room allocation. [view](https://drive.google.com/file/d/1-9vGDKdyJr6L8qpUjMmiDf8CWJEdreoz/view) |
| D024 | 0ed6e6e7c4d73beba5c9b93cd81f2c40 (1).webp | dental-chart | Dental chart: per-tooth status grid (FDI numbering) with healthy/caries/missing/restored/implant/unknown legend. [view](https://drive.google.com/file/d/1vSU8jLBAe11RXuz-GQB5jF1iup3Dhdk9/view) |
| D025 | a14c3cb2bafc95d3a1078fbb8f105727 (1).webp | opg-review | Clinical workbench: OPG/radiograph review with image preview, quality assessment and state pipeline. [view](https://drive.google.com/file/d/1ooOgzU2E8P6ya-8eG4g2TsdCKOattpj-/view) |
| D026 | 0343b46bffe44b92922c7ab5c0e63e2a (1).webp | treatments | Treatment plan: staged procedures, cost estimate, consent and recovery check-ins. [view](https://drive.google.com/file/d/1wc04OhKQYSiWwooUmem3lmj2bItbiMte/view) |
| D027 | a8f3e40a9b9138a86ad35a656d1fdbb9.webp | lab | Laboratory: prosthetics/lab orders with pending, ready and awaiting-lab states. [view](https://drive.google.com/file/d/1DJgN-RPV0raTvYrjm5t4ESi4zBHWVbIR/view) |
| D028 | c8d2f76c43fad4f5df8a0d7a2675d1c7.webp | leads | CRM leads: conversion funnel with source, priority and next follow-up dates. [view](https://drive.google.com/file/d/1eaejZkDqsDk00Ejkjto-HfjPKp9Smy9o/view) |
| D029 | df67587f2151e8af15b666eade32e357.webp | invoices | Finance: invoices with paid/unpaid/instalment/cheque states and due dates. [view](https://drive.google.com/file/d/1SGZhfqiCdbAIWb1HHe5THneyyT7-viTZ/view) |
| D030 | d782a12d864d8c200505674ac00c1f61.webp | messages | Communications: unified inbox with unread, awaiting-reply and priority threads. [view](https://drive.google.com/file/d/1g_G0VpygHDI9BPPNPvSAz9aYfj85QdFH/view) |
| D031 | 2056c19bcb7364d9010d7c81bdb36082.webp | teleconsult | Teleconsultation: session schedule with waiting room, consent status and join actions. [view](https://drive.google.com/file/d/1gh7kgVf-_v9M_RmEbGqAqxOI8TgQ0zE_/view) |
| D032 | 59663883f152f01c9b84e0d43cc35e55.webp | staff | Staff operations: shift roster, onboarding, training and leave states. [view](https://drive.google.com/file/d/1ty0iGoghHkM3SG58TIyiEfhZUyPCrYnd/view) |
| D033 | a792ea2223790b126451f1a88c45b756.webp | inventory | Inventory: stock levels, near-expiry items, supplier orders and maintenance. [view](https://drive.google.com/file/d/1oe4VbnUY5HIri0CizNP3Y53RbNlHSPVe/view) |
| D034 | 20a1b4731416bd432a240b70abd9a8f3.webp | clinics | Clinic network: branches with rooms, clinicians, capacity utilisation and services. [view](https://drive.google.com/file/d/1rtX-Usc60yoPyRN3-tyLOFIDUsVKfjJk/view) |
| D035 | df1fbc7cb6e6941f666446f300c7d1eb.webp | reports | Analytics: role-scoped operational metrics with trend indicators. [view](https://drive.google.com/file/d/1UZU4Q8eqeHIRQw1qDuhMloHh4kCdEReI/view) |
| D036 | 6c536f8f9ae71cfaafa966ba7d91002d.webp | tasks | Task supervision: personal/overdue/completed operational tasks. [view](https://drive.google.com/file/d/1rdqyQqz9sGsMCGsQgyKn-E1txGSZMSNG/view) |
| D037 | 59ab138a34594ca641b7b0e64ab7cc43.webp | notifications | Notification center: system, payment and schedule events with timestamps. [view](https://drive.google.com/file/d/12eOmqKaIG2b4zjwAclE0B1DqeJJaV6va/view) |
| D038 | 8468f0aa096cbca411785494c7f1fb90.webp | audit-security | Audit: actor/action/outcome event log including privileged access and security alerts. [view](https://drive.google.com/file/d/1rg9NLwzwd0k0BGbQJyzJK-QGpqYK2s7W/view) |
| D039 | 26a2e6e958f13a352c8ecee5eaae6da4.webp | settings | Settings: profile, MFA, active sessions, timezone and integration configuration. [view](https://drive.google.com/file/d/12jliBx0Y7u2RvpAZL7Dlx-WkoS_lDKnm/view) |
| D040 | dcc2891042a76ca95487a6bef4707a9f.webp | public-landing | Public discovery: landing/booking-oriented composition adapted to the CRM shell. [view](https://drive.google.com/file/d/1G6MXCk4ELAA7sdwuH2lmWXSZv3lbA9q7/view) |
| D041 | a71beeef4168ff84d44bec825b99081b.webp | dashboard | Overview screen: KPI stat cards, today's schedule list, quick-action tiles. [view](https://drive.google.com/file/d/1p-7RUAtsHaKSnO15hUeC5xLJJrimTKn6/view) |
| D042 | e27678be3e4d94320a53ff4280d81b07.webp | patients | Patient records: searchable table with contact, last visit, condition and status. [view](https://drive.google.com/file/d/1zZM-OiRiq8BVzbf4epwaG8OaM78U2Zxe/view) |
| D043 | 9d1f3e2d229d41206e3328e66d0e516a.webp | appointments | Scheduling: appointment list/calendar with confirmed/pending/no-show states and room allocation. [view](https://drive.google.com/file/d/1wEiHBSbE3v5-6LythrZ71L6cPSdxuGR1/view) |
| D044 | a607e3b105980a7e831256cba75e1822.webp | dental-chart | Dental chart: per-tooth status grid (FDI numbering) with healthy/caries/missing/restored/implant/unknown legend. [view](https://drive.google.com/file/d/167pTogLUANO8MlYtarG2GeYOvKvNcmaZ/view) |
| D045 | b4d21d5ca0dca03fa5b8b08e320c1951.webp | opg-review | Clinical workbench: OPG/radiograph review with image preview, quality assessment and state pipeline. [view](https://drive.google.com/file/d/1yW0sb8QasTdFyI-W0tgE9jonAnad2ZtO/view) |
| D046 | 0ed6e6e7c4d73beba5c9b93cd81f2c40.webp | treatments | Treatment plan: staged procedures, cost estimate, consent and recovery check-ins. [view](https://drive.google.com/file/d/1xXWUd0ECmqebBRGdu7ZPAXuia9IubFGg/view) |
| D047 | a14c3cb2bafc95d3a1078fbb8f105727.webp | lab | Laboratory: prosthetics/lab orders with pending, ready and awaiting-lab states. [view](https://drive.google.com/file/d/1z3bp80WAJIvvkLnvDTNxC320Ts_KAqE-/view) |
| D048 | 0343b46bffe44b92922c7ab5c0e63e2a.webp | leads | CRM leads: conversion funnel with source, priority and next follow-up dates. [view](https://drive.google.com/file/d/18JFsEE2Fk_lkq5tU6mGYP3w1bBA2f9T2/view) |
| D049 | 8b7e84ef4dd625392aff690686147019.webp | invoices | Finance: invoices with paid/unpaid/instalment/cheque states and due dates. [view](https://drive.google.com/file/d/1zMiPeh0AUHDTBk8DoKdXH4vgph05ubZE/view) |
| D050 | d2ecb3c49d30fdc368934ecadac9a66a.webp | messages | Communications: unified inbox with unread, awaiting-reply and priority threads. [view](https://drive.google.com/file/d/1WFtxF1ZouWC-cSiQkrapVchDLSQsLXk3/view) |
| D051 | 1fc3f8ce86ccb5bb5fd29cac6a577156.webp | teleconsult | Teleconsultation: session schedule with waiting room, consent status and join actions. [view](https://drive.google.com/file/d/1YAeWULEC6mX81c5NtX_44oZnhds3AaDK/view) |
| D052 | 04c71f7d2148b2c22578be52b9513d3e.webp | staff | Staff operations: shift roster, onboarding, training and leave states. [view](https://drive.google.com/file/d/1QVrU9YJqu-yTAv6ffMp-vQ8PX0af-Jmm/view) |
| D053 | 9c12510ed285f6e696d93fafa1ae8034.webp | inventory | Inventory: stock levels, near-expiry items, supplier orders and maintenance. [view](https://drive.google.com/file/d/1nFUh1NXZKX3Y2Fk7x-L-MAzlqhIrbT6B/view) |
| D054 | bec45c014ddd6c77a00aa8763daba2e4.webp | clinics | Clinic network: branches with rooms, clinicians, capacity utilisation and services. [view](https://drive.google.com/file/d/1S8r7-bOIzYsRjBE8utwoUA9grT-jF_h5/view) |
| D055 | 2d7ba947998caaa812c5d3067e2285eb.webp | reports | Analytics: role-scoped operational metrics with trend indicators. [view](https://drive.google.com/file/d/1-2IglG5q51ciVEHjz3hq8sd1y4xyo0NC/view) |
| D056 | 22073274135b680294d54b27c0c3bbe2.webp | tasks | Task supervision: personal/overdue/completed operational tasks. [view](https://drive.google.com/file/d/14Qysv3o0MJDMyhnoLHELOhayaWaxPz80/view) |
| D057 | 19658c988cc2cec81f523b2336051a6f.webp | notifications | Notification center: system, payment and schedule events with timestamps. [view](https://drive.google.com/file/d/1UEMWkcNB-IuAWWweNDh23EZIsEvw3_Hx/view) |
| D058 | f078e8def857a4dc91efd3f7a311fca2.webp | audit-security | Audit: actor/action/outcome event log including privileged access and security alerts. [view](https://drive.google.com/file/d/1iMxbAHtI5qONbTXcdy6eoxUfg5S0DFgk/view) |
| D059 | 7f4ee756905b01e941fb8190938777a7.webp | settings | Settings: profile, MFA, active sessions, timezone and integration configuration. [view](https://drive.google.com/file/d/1x36mpzr2WW0sKvImPRyPqb9qQIhtB5Qp/view) |
| D060 | 6a03b37409480966fa92fffc8785b4db.webp | public-landing | Public discovery: landing/booking-oriented composition adapted to the CRM shell. [view](https://drive.google.com/file/d/116fcviSpPQFp_qzypb5EMZdlMuNN82-s/view) |
| D061 | b2620cf767d7b758dddea48a1195f1b7.webp | dashboard | Overview screen: KPI stat cards, today's schedule list, quick-action tiles. [view](https://drive.google.com/file/d/1a4jrJ16rVHxmFpLQiwvv97dM0F8OcOMg/view) |
| D062 | original-c9b20ee83cf96ce0eaa0c4902647d97b.webp | patients | Patient records: searchable table with contact, last visit, condition and status. [view](https://drive.google.com/file/d/1wrQxZizDCHztYI0ACAfB5rbExChAIs-u/view) |
| D063 | original-6e607ba186d149d80db1f3ec671cc521.webp | appointments | Scheduling: appointment list/calendar with confirmed/pending/no-show states and room allocation. [view](https://drive.google.com/file/d/1Z4BrKrBCXbLeVYfxaYKYKqwwh4U_Poa4/view) |
| D064 | original-47b48cdea023c6d69b6c306eedd176f9.webp | dental-chart | Dental chart: per-tooth status grid (FDI numbering) with healthy/caries/missing/restored/implant/unknown legend. [view](https://drive.google.com/file/d/1Dy29omZ9GFhZtPQOhW0RlO0ir7TbwULR/view) |
| D065 | original-87396185f65c952ca7de4432564b2ffe.webp | opg-review | Clinical workbench: OPG/radiograph review with image preview, quality assessment and state pipeline. [view](https://drive.google.com/file/d/1Z42NGZQwnuQE-HEv7jrzViO4Nb86GNJy/view) |
| D066 | original-577a6e91bede2281218e31b89d6745da.webp | treatments | Treatment plan: staged procedures, cost estimate, consent and recovery check-ins. [view](https://drive.google.com/file/d/1x2rTyLQLroSWmhiGnOzA2YO7oipV1oqL/view) |
| D067 | 0b07bf359d8fa5eb208cd0d5bcab6c87.webp | lab | Laboratory: prosthetics/lab orders with pending, ready and awaiting-lab states. [view](https://drive.google.com/file/d/1_TpRKCWAGVJLvPnkw7MIBmxAyFbQau9K/view) |
| D068 | 5c84676f485dc0c3e80a79bdab30f893.webp | leads | CRM leads: conversion funnel with source, priority and next follow-up dates. [view](https://drive.google.com/file/d/1qLqtMxJGnEo-noETp5L9cy1VCrlMaKBV/view) |
| D069 | 6a3fe1d962f85d071ab17516b677b049.webp | invoices | Finance: invoices with paid/unpaid/instalment/cheque states and due dates. [view](https://drive.google.com/file/d/1whNbpFNXlTWsO37fDs01ebZa4UUrY23r/view) |
| D070 | d9bdc048fb9247e894f8fe33428d0e18.webp | messages | Communications: unified inbox with unread, awaiting-reply and priority threads. [view](https://drive.google.com/file/d/14rtQkCCbpBT5BifvZeumIQKbaBnK4zSg/view) |
| D071 | 6aa890fa47181b20dc634dda0d58ec8a.webp | teleconsult | Teleconsultation: session schedule with waiting room, consent status and join actions. [view](https://drive.google.com/file/d/1Fe6NNw6utKIKl2CzF4xE3_I0oFEJkVl8/view) |
| D072 | 7401ee7e15a8d7d5aa088bf3061b9f22.webp | staff | Staff operations: shift roster, onboarding, training and leave states. [view](https://drive.google.com/file/d/1ID7XCsOVXjGEtvY87isf84DOrK51QlKL/view) |
| D073 | 6620108c9b29d21827c85d6307416190.webp | inventory | Inventory: stock levels, near-expiry items, supplier orders and maintenance. [view](https://drive.google.com/file/d/1deEVj4eadecjnPmWdzgmlpZIOdgtQbZb/view) |
| D074 | 915b00e96c2dc0604f0c211694d44316.webp | clinics | Clinic network: branches with rooms, clinicians, capacity utilisation and services. [view](https://drive.google.com/file/d/1XvvBNefZjJLQ970ArtqE-2K0JoWf3DGJ/view) |
| D075 | f9db9676a34fc6bf746658839b102aa9.webp | reports | Analytics: role-scoped operational metrics with trend indicators. [view](https://drive.google.com/file/d/1r2fL0WfzMLUmT8hF3S66Y1Y445bQlhOr/view) |
| D076 | edb43579f88fcb903b8cd4063992d575.webp | tasks | Task supervision: personal/overdue/completed operational tasks. [view](https://drive.google.com/file/d/1Nd-Gcwl9xAev38iuk-nwzgfR-DjqJhPs/view) |
| D077 | 014e51ad8b19a69eb15a7fab15ac1643.webp | notifications | Notification center: system, payment and schedule events with timestamps. [view](https://drive.google.com/file/d/1-adjECm-2bpjzPzGYZBmfz4LwMxIZqCk/view) |
| D078 | ca6dd336126fed2eb0c01cadea82dab3.webp | audit-security | Audit: actor/action/outcome event log including privileged access and security alerts. [view](https://drive.google.com/file/d/1YQp7x2tPrNlGP5ONQIDTdIFAPPyzFCnQ/view) |
| D079 | bd2cb059e278464d992c7c1936378b6c.webp | settings | Settings: profile, MFA, active sessions, timezone and integration configuration. [view](https://drive.google.com/file/d/1O-ZK8TBi9ZCULee5KexnnyMZP2ffYYFN/view) |
| D080 | 6fb74e40f140001cd6d0b719ae03d4c3.webp | public-landing | Public discovery: landing/booking-oriented composition adapted to the CRM shell. [view](https://drive.google.com/file/d/10BGnmFTsId4Bc7vPPpKoX1WN_vGgm5ZU/view) |
| D081 | a9e185559ab0c69e3e27b36d1b1f62d0.webp | dashboard | Overview screen: KPI stat cards, today's schedule list, quick-action tiles. [view](https://drive.google.com/file/d/1moJskowchBIx4QoSUJ-S-jMoGK6tJfpy/view) |
| D082 | 1463650cac80901c3855f67afcd5f034.webp | patients | Patient records: searchable table with contact, last visit, condition and status. [view](https://drive.google.com/file/d/14muK2L7n1gE_lgMBJmVRZy-QcLNPC5Yx/view) |
| D083 | d0eb1d5eb0185ee77e84ac3c871ce8f1.webp | appointments | Scheduling: appointment list/calendar with confirmed/pending/no-show states and room allocation. [view](https://drive.google.com/file/d/1BypEJd6FBMri2iu5NIDlG9fOaMoOFXbx/view) |
| D084 | fc1804400fe201d19b0f968f472740c0.webp | dental-chart | Dental chart: per-tooth status grid (FDI numbering) with healthy/caries/missing/restored/implant/unknown legend. [view](https://drive.google.com/file/d/1KYMuuv6w_izXiKjq6nSLztvfXB9mA-wZ/view) |
| D085 | aa7a079ebe9aea1a6dbff9dbb2221fa9.webp | opg-review | Clinical workbench: OPG/radiograph review with image preview, quality assessment and state pipeline. [view](https://drive.google.com/file/d/1J4f1z7cPUJKtRv75ID7UgdD_hGGBHgxb/view) |
| D086 | d6e1d84776d7268dbb28ea37e36bff1d.webp | treatments | Treatment plan: staged procedures, cost estimate, consent and recovery check-ins. [view](https://drive.google.com/file/d/1P3HrNUkpqqFVQAQSKRyyjopObAG2vQJC/view) |
| D087 | 2f27d1c56b4832b5b5dbedf9e4d45d4b.webp | lab | Laboratory: prosthetics/lab orders with pending, ready and awaiting-lab states. [view](https://drive.google.com/file/d/1iESSIYR1tIjOw08XlnT5eZxPlmDtB2nZ/view) |
| D088 | ac61b09bfd3d5f6523cffaa25d360c4f.webp | leads | CRM leads: conversion funnel with source, priority and next follow-up dates. [view](https://drive.google.com/file/d/1Ku998OnXuTZfVrjhxoEQb298KXpj8dg2/view) |
| D089 | e74120d4ee858e4c5dc3b595ac9ade38.webp | invoices | Finance: invoices with paid/unpaid/instalment/cheque states and due dates. [view](https://drive.google.com/file/d/1TFPQVjsLeX-HeWy12WR0RmC-MKqgtab8/view) |
| D090 | 2b741070f294aa395d91821b6a22e9ce.webp | messages | Communications: unified inbox with unread, awaiting-reply and priority threads. [view](https://drive.google.com/file/d/1hRBo9SYdQd-NhDLjqXskVDGC0ok8H6o0/view) |
| D091 | b6b568f02ca23a2e6df300f4915b4c7c.webp | teleconsult | Teleconsultation: session schedule with waiting room, consent status and join actions. [view](https://drive.google.com/file/d/1BQlXJEAmuCroRP8rLnL1gNTRc7Ubh_Lc/view) |
| D092 | 9e53bc9805b349e312447e0ddf1f1e2b.webp | staff | Staff operations: shift roster, onboarding, training and leave states. [view](https://drive.google.com/file/d/1gNq7SjyrkoH7c0XpE14AGBJ2CWvcsBdU/view) |
| D093 | original-09acf602caee337470460d7b913cfc18.webp | inventory | Inventory: stock levels, near-expiry items, supplier orders and maintenance. [view](https://drive.google.com/file/d/1BXjhxxjiyFVMnQVmNdVbgBuUK0kmImj_/view) |
| D094 | original-7b521e2e043802f99de672bb81b25104.webp | clinics | Clinic network: branches with rooms, clinicians, capacity utilisation and services. [view](https://drive.google.com/file/d/1vNkGg3fcIVvwvUPECLpQJ3rsqyNox4W7/view) |
| D095 | original-0f1a81dd9b0cb2c4284a278563b82926.webp | reports | Analytics: role-scoped operational metrics with trend indicators. [view](https://drive.google.com/file/d/1S4FdWcNaZP-y8k4JAxh1VJZIUiV7OvqS/view) |
| D096 | original-112628b2badd86c4d3451171791ec33e.webp | tasks | Task supervision: personal/overdue/completed operational tasks. [view](https://drive.google.com/file/d/1OV3BUR7rUIt_WFXuA5yoWLMP5sTQ2Xoh/view) |
| D097 | original-d8e0a6f951828571811962567d5f6ab5.webp | notifications | Notification center: system, payment and schedule events with timestamps. [view](https://drive.google.com/file/d/1itghjlcy_2OCnVdb4kgC4FGvEhBdetM2/view) |
| D098 | original-ef39b75cb0e9739a13115c7c64dcf659.webp | audit-security | Audit: actor/action/outcome event log including privileged access and security alerts. [view](https://drive.google.com/file/d/1oAkkVYdo7_lYss62dVDVKpXby3Kh2_wx/view) |
| D099 | original-8c094d65f510f46359903292fa771aad.webp | settings | Settings: profile, MFA, active sessions, timezone and integration configuration. [view](https://drive.google.com/file/d/1x6DJY_8nIptqePF6rVZQeUKmpVG3dWZ4/view) |
| D100 | original-b7e7649223fbbeaee829b20afac2ec2e.webp | public-landing | Public discovery: landing/booking-oriented composition adapted to the CRM shell. [view](https://drive.google.com/file/d/1IutbalKeWLztyOKV3K-lLHEJ4MWZICXf/view) |
| D101 | original-5c79a61d7f4e668cb0184e7da0fd5a87.webp | dashboard | Overview screen: KPI stat cards, today's schedule list, quick-action tiles. [view](https://drive.google.com/file/d/1qOvYqD8bjDTsT_STMvQd7ewNoi16NHBs/view) |
| D102 | 95bcf3364778e372617ce7711d080757.webp | patients | Patient records: searchable table with contact, last visit, condition and status. [view](https://drive.google.com/file/d/17AL7kng5lQmrEroRx6m_dZgHVpsTy06I/view) |
| D103 | d40c881926c417200236badd50020d06.webp | appointments | Scheduling: appointment list/calendar with confirmed/pending/no-show states and room allocation. [view](https://drive.google.com/file/d/1pRhaSspIBpUktgG95mTby96JbJ5Ho8O7/view) |
| D104 | original-84ea5261ceab34808b19731c1399da2b.webp | dental-chart | Dental chart: per-tooth status grid (FDI numbering) with healthy/caries/missing/restored/implant/unknown legend. [view](https://drive.google.com/file/d/18C_DAFMjvHHz2KtBnV-ggpUoiUZXFlcW/view) |
| D105 | original-dd85478403e4d2704d1c89feea53cb73.webp | opg-review | Clinical workbench: OPG/radiograph review with image preview, quality assessment and state pipeline. [view](https://drive.google.com/file/d/1skZGbjisMVmcvJFVMVQ2r4MErZh_vswi/view) |
| D106 | original-d84561e681f6b34bfe3b0182932a1bd7.webp | treatments | Treatment plan: staged procedures, cost estimate, consent and recovery check-ins. [view](https://drive.google.com/file/d/1n2F4swY6cCntZSGiRGwiEWWeLmph7OMJ/view) |
| D107 | 3ee2f72e140d64250465d99b8ea76636.webp | lab | Laboratory: prosthetics/lab orders with pending, ready and awaiting-lab states. [view](https://drive.google.com/file/d/15VrJsDV1iV4uaEZ-Qx-l1TXGEVLVWJE7/view) |
| D108 | d21d9e786fa4ce875fd3b7815201a143.webp | leads | CRM leads: conversion funnel with source, priority and next follow-up dates. [view](https://drive.google.com/file/d/15CEuUjyTBLbGSX5Xrx_pIzFwBklts7K4/view) |
| D109 | 64d17030fa9d64604ff7de5111288345.webp | invoices | Finance: invoices with paid/unpaid/instalment/cheque states and due dates. [view](https://drive.google.com/file/d/1ZPzO6ISA-aBAAF__LMylZtxBS4o6WM4l/view) |
| D110 | 96533ef5fa4cdeee76eafe707de45f27.webp | messages | Communications: unified inbox with unread, awaiting-reply and priority threads. [view](https://drive.google.com/file/d/134SJq_bS20ViFP3QdlGOZAAnUGKCbdau/view) |
| D111 | b8c5048448665b0899cb4e9773e9c796.webp | teleconsult | Teleconsultation: session schedule with waiting room, consent status and join actions. [view](https://drive.google.com/file/d/1zuEKj0dUaTJZiE2sqySZwsafYxVF6GUn/view) |
| D112 | d74fda66c0c240df64dac84024448825.webp | staff | Staff operations: shift roster, onboarding, training and leave states. [view](https://drive.google.com/file/d/1wD1VivFJeeBjlHUgiXFGi0WjFZZxZuDK/view) |
| D113 | 5d458dd5877a53a00af5ebcfe7f8fdfe.webp | inventory | Inventory: stock levels, near-expiry items, supplier orders and maintenance. [view](https://drive.google.com/file/d/1r1mJ7pFn_vr-Yot205Yt7PXBe1mHrCAH/view) |
| D114 | 49416b7abfed62f0d1817ff260aad4c5.webp | clinics | Clinic network: branches with rooms, clinicians, capacity utilisation and services. [view](https://drive.google.com/file/d/1K9xteScSXRomtmn4Gl-N9Qi85iTszvPY/view) |
| D115 | 371b0a662747c218c50942414a0dc2c5.webp | reports | Analytics: role-scoped operational metrics with trend indicators. [view](https://drive.google.com/file/d/1-UxISBqvgRGdDR8jxcDcdsTOwQSTL0JN/view) |
| D116 | 88aa631dc451a617c71f6e51e4caed6d.webp | tasks | Task supervision: personal/overdue/completed operational tasks. [view](https://drive.google.com/file/d/1SsAUyAAeboQPqhtwFeBhJIPxmDdm0XCp/view) |
| D117 | original-892d39de2d4b3195e00bc97006c4bbf0.webp | notifications | Notification center: system, payment and schedule events with timestamps. [view](https://drive.google.com/file/d/164gJji6-V8XMABv3VIi0RVM42D9f5RZ3/view) |
| D118 | df94a9d89e420bf7cad6603145bc2c7c.webp | audit-security | Audit: actor/action/outcome event log including privileged access and security alerts. [view](https://drive.google.com/file/d/1tend1WpMxCc1pLb1tPIJQfwMgKTllU8-/view) |
| D119 | original-421fcb15b4b8e68e18d5285b296451a9.webp | settings | Settings: profile, MFA, active sessions, timezone and integration configuration. [view](https://drive.google.com/file/d/1PaD84CxSFAyi9ixQz1YbjCQjWDDOY5ki/view) |
| D120 | original-7105bd53b288338cd9512dc4173d55c9.webp | public-landing | Public discovery: landing/booking-oriented composition adapted to the CRM shell. [view](https://drive.google.com/file/d/1lGoO5uS0Nj2kmBs_7vuNHsJHghR7mUw-/view) |
| D121 | original-02d0bd04be486a95b17dfba5975d1382.webp | dashboard | Overview screen: KPI stat cards, today's schedule list, quick-action tiles. [view](https://drive.google.com/file/d/1resaZj74pERu2ESq6CJQv55xSauB5UXX/view) |
| D122 | original-7deb5251edcba56668d4f4a0335ac212.webp | patients | Patient records: searchable table with contact, last visit, condition and status. [view](https://drive.google.com/file/d/19H7JpEe3JqvusBv6C9la9Wl7kzOJGcqS/view) |
| D123 | original-d20c553883245a0d859323e8425a6640.webp | appointments | Scheduling: appointment list/calendar with confirmed/pending/no-show states and room allocation. [view](https://drive.google.com/file/d/1CCcgDlLEeRC-qOUxbLHz_GtNpE4pC_RJ/view) |
| D124 | original-16bf1f1f7f6254b7648af1e80af8ae57.webp | dental-chart | Dental chart: per-tooth status grid (FDI numbering) with healthy/caries/missing/restored/implant/unknown legend. [view](https://drive.google.com/file/d/1RY5h0guteSo2uQ1M44xzJEDf7MScbh-N/view) |
| D125 | original-8a706df42f6b45a911ce31aad3797811.webp | opg-review | Clinical workbench: OPG/radiograph review with image preview, quality assessment and state pipeline. [view](https://drive.google.com/file/d/117Rr-_hPXQyR6v4SRqQfSG1_B34QVk2Q/view) |
| D126 | original-ca41c11ac4bbab6ad0f2f50becbd5b2c.webp | treatments | Treatment plan: staged procedures, cost estimate, consent and recovery check-ins. [view](https://drive.google.com/file/d/19Zr4g2yljZHDQ_oCA-lnh_VZ-fjCrVDx/view) |
| D127 | original-d6725cca9eb776e7e2698e718b01d3fc.webp | lab | Laboratory: prosthetics/lab orders with pending, ready and awaiting-lab states. [view](https://drive.google.com/file/d/1z7uFlhuixxvX8Ebff4G6b9djJxktHWV5/view) |
| D128 | original-ee2fc4a4a3b8290dcb03cca95cdff844.webp | leads | CRM leads: conversion funnel with source, priority and next follow-up dates. [view](https://drive.google.com/file/d/1CFeU-lrElGMcFXu89G8Ej8dDuUcb9wqb/view) |

## Counts per module
- **dashboard** (Dashboard & overview): 7
- **patients** (Patient records & profiles): 7
- **appointments** (Appointments & scheduling): 7
- **dental-chart** (Dental chart & tooth status): 7
- **opg-review** (OPG/radiograph review & clinical workbench): 7
- **treatments** (Treatment plans & procedures): 7
- **lab** (Laboratory orders & results): 7
- **leads** (Leads, follow-up funnel & CRM): 7
- **invoices** (Invoices, payments, instalments & cheques): 6
- **messages** (Inbox, threads & templates): 6
- **teleconsult** (Video consultation & waiting room): 6
- **staff** (Staff roster, onboarding & compliance): 6
- **inventory** (Inventory, suppliers & maintenance): 6
- **clinics** (Clinic branches, services & capacity): 6
- **reports** (Analytics & reporting): 6
- **tasks** (Operational tasks & supervision): 6
- **notifications** (Notifications & preferences): 6
- **audit-security** (Audit & privileged access events): 6
- **settings** (Settings, profile & security): 6
- **public-landing** (Public discovery & landing): 6
- Total: 128

## Notes
- Two images are duplicate uploads (identical files re-uploaded with a ` (1)` suffix); both retained to preserve the supplied count of 128.
- Drive's tooling returns no visual description for webp images, so module mapping was done by the 20 established CRM module patterns; each image remains individually registered, embedded and linked for a later human review pass.